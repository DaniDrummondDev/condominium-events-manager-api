# RAG e Estrategia de Memoria — Condominium Events Manager API

## Objetivo

Definir estrategia clara para uso de RAG (Retrieval-Augmented Generation) e memoria de IA em ambiente SaaS multi-tenant, com isolamento total, conformidade LGPD e rastreabilidade.

---

## 1. Separacao Conceitual

| Conceito | Funcao | Persistencia | Escopo |
|----------|--------|-------------|--------|
| **RAG** | Recupera fatos, historicos e documentos | Embeddings em pgvector | Tenant |
| **Memoria** | Armazena preferencias e padroes recorrentes | Tabela ai_memory | Tenant + Usuario |
| **Contexto de Sessao** | Mantem continuidade na conversa atual | Redis (TTL) | Sessao |

Estes tres conceitos sao complementares, mas **nunca devem ser misturados**.

- RAG explica o passado (fatos)
- Memoria orienta o futuro (preferencias)
- Tools executam o presente (acoes)
- Contexto de sessao mantem a conversa coerente

---

## 2. RAG — O que Indexar

### 2.1 Fontes Permitidas

| Fonte | Tipo | Frequencia de Indexacao | Exemplo |
|-------|------|----------------------|---------|
| **Regulamentos** | Documento institucional | Na publicacao/atualizacao | Regras de uso da piscina |
| **Politicas do condominio** | Documento institucional | Na publicacao/atualizacao | Politica de cancelamento |
| **Historico de reservas** | Dados operacionais | Diaria (batch) | Reservas dos ultimos 12 meses |
| **Decisoes de aprovacao** | Logs de decisao | Na criacao | Motivos de aprovacao/rejeicao |
| **Penalidades aplicadas** | Logs de governanca | Na criacao | Infracoes e contestacoes |
| **Atas de assembleia** | Documento institucional | Na publicacao | Decisoes registradas |
| **Metricas agregadas** | Dados operacionais | Semanal (batch) | Taxas de ocupacao, tendencias |

### 2.2 Fontes Proibidas

| Fonte | Motivo |
|-------|--------|
| Conversas completas com IA | Privacidade, volume excessivo |
| Preferencias pessoais | Vai para Memoria, nao RAG |
| Dados financeiros individuais | Dados sensiveis |
| Documentos biometricos | Dados sensiveis |
| Credenciais ou tokens | Dados criticos |
| Opinioes ou sugestoes da IA | Nao sao fatos |
| Dados de outros tenants | Violacao de isolamento |

---

## 3. RAG — Arquitetura Tecnica

### 3.1 Pipeline de Ingestao

```
Fonte (documento/evento)
  → Normalizacao (limpar formatacao, remover PII)
  → Chunking semantico
  → Hash de conteudo (deduplicacao)
  → Geracao de embedding via Provider
  → Persistencia em pgvector com metadados
  → Registro em ai_data_access_logs
```

### 3.2 Chunking Strategy

| Tipo de Documento | Estrategia | Tamanho do Chunk | Overlap |
|-------------------|-----------|-----------------|---------|
| Regulamentos | Por secao/artigo | 500-800 tokens | 100 tokens |
| Atas de assembleia | Por topico/pauta | 300-500 tokens | 50 tokens |
| Historico de reservas | Por reserva individual | 100-200 tokens | 0 |
| Decisoes/penalidades | Por decisao individual | 200-400 tokens | 0 |
| Metricas agregadas | Por periodo + espaco | 100-300 tokens | 0 |

Principios:
- Chunks devem ser semanticamente completos (nao cortar no meio de uma regra)
- Metadados de contexto preservados (secao, data, autor)
- Chunks muito grandes diluem relevancia; muito pequenos perdem contexto

### 3.3 Schema de Embeddings (pgvector)

```sql
CREATE TABLE ai_embeddings (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL,           -- Isolamento obrigatorio
    source_type VARCHAR(50) NOT NULL,  -- regulation, reservation, penalty, assembly, metric
    source_id UUID NOT NULL,           -- ID do documento/recurso original
    chunk_index INT NOT NULL,          -- Posicao do chunk no documento
    content_text TEXT NOT NULL,         -- Texto original do chunk
    embedding VECTOR NOT NULL,         -- Dimensao configuravel (1536, 768, 1024...)
    model_version VARCHAR(50) NOT NULL,-- Versao do modelo de embedding
    content_hash VARCHAR(64) NOT NULL, -- SHA-256 para deduplicacao
    metadata JSONB DEFAULT '{}',       -- { section, author, date, data_classification }
    created_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NULL,         -- Para dados com retencao definida

    CONSTRAINT fk_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

-- Indice de busca vetorial (IVFFlat para performance)
-- Nota: dimensao do indice deve corresponder a dimensao configurada (AI_EMBEDDING_DIMENSIONS)
CREATE INDEX idx_embeddings_vector ON ai_embeddings
    USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);

-- Indice de isolamento por tenant
CREATE INDEX idx_embeddings_tenant ON ai_embeddings (tenant_id);

-- Indice de deduplicacao
CREATE UNIQUE INDEX idx_embeddings_dedupe ON ai_embeddings (tenant_id, content_hash, model_version);

-- Indice para busca por fonte
CREATE INDEX idx_embeddings_source ON ai_embeddings (tenant_id, source_type, source_id);
```

> **Dimensao configuravel:** O tipo `VECTOR` sem dimensao fixa aceita vetores de qualquer tamanho.
> Para performance, recomenda-se definir a dimensao no indice IVFFlat.
> Ver [ai-provider-strategy.md](ai-provider-strategy.md) secao 8 para estrategia de migracao de embeddings.

### 3.4 Retrieval Strategy

| Parametro | Valor | Justificativa |
|-----------|-------|---------------|
| **Similarity threshold** | 0.75 | Abaixo disso, resultado irrelevante |
| **Top-K resultados** | 5 | Equilibrio entre contexto e ruido |
| **Busca hibrida** | Sim | Combinar vector similarity + keyword match |
| **Re-ranking** | Por recencia + relevancia | Documentos mais recentes tem prioridade em caso de empate |
| **Filtros obrigatorios** | `tenant_id`, `source_type` | Isolamento + precisao |

Fluxo de retrieval:

```
Pergunta do usuario
  → Gerar embedding da pergunta
  → Busca por similaridade em pgvector (filtrado por tenant_id)
  → Filtrar resultados abaixo do threshold
  → Re-ranking (recencia + relevancia)
  → Top-K chunks retornados como contexto
  → Incluir metadados de fonte para citacao
```

### 3.5 Versionamento de Embeddings

- Embeddings nunca sao sobrescritos — nova versao do modelo gera novos registros.
- `model_version` rastreia qual modelo gerou cada embedding.
- Migracao entre versoes de modelo: re-indexacao batch em background.
- Embeddings antigos sao deletados apenas apos confirmacao da nova indexacao.

---

## 4. Memoria da IA

### 4.1 Conceito

Memoria e informacao **curada, explicita e justificavel** sobre preferencias e padroes de um usuario. Diferente de RAG (fatos), memoria e sobre **como o usuario prefere interagir**.

### 4.2 Caracteristicas

- **Pequena**: maximo 50 itens por usuario
- **Curada**: nao cresce automaticamente sem validacao
- **Explicita**: usuario pode ver, editar e excluir
- **Justificavel**: cada memoria tem origem rastreavel
- **Escopada**: por tenant + usuario, nunca global
- **Opt-in**: usuario deve aceitar que memorias sejam armazenadas

### 4.3 Exemplos de Memorias Validas

| Tipo | Exemplo | Confianca |
|------|---------|-----------|
| Preferencia de horario | "Usuario prefere reservas no periodo da manha" | 0.8 |
| Padrao de aprovacao | "Sindico geralmente aprova reservas com menos de 20 convidados" | 0.7 |
| Estilo de comunicacao | "Usuario prefere respostas objetivas e curtas" | 0.9 |
| Espaco favorito | "Usuario reserva salao de festas com mais frequencia" | 0.85 |
| Antecedencia padrao | "Usuario costuma reservar com 7 dias de antecedencia" | 0.75 |

### 4.4 Exemplos de Memorias Proibidas

| Proibido | Motivo |
|----------|--------|
| Dados pessoais (CPF, telefone) | LGPD — dados sensíveis |
| Opiniao sobre moradores | Subjetivo e potencialmente prejudicial |
| Score/classificacao de usuario | Discriminatorio |
| Informacoes financeiras | Dados sensiveis |
| Historico medico | Dados sensiveis |

### 4.5 Schema de Memoria

```sql
CREATE TABLE ai_memory (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL,
    user_id UUID NOT NULL,
    memory_type VARCHAR(50) NOT NULL,    -- preference, pattern, style
    description TEXT NOT NULL,            -- Descricao da memoria
    scope VARCHAR(20) NOT NULL,           -- user, unit, condominium
    confidence DECIMAL(3,2) NOT NULL,     -- 0.00 a 1.00
    source VARCHAR(50) NOT NULL,          -- conversation, behavior_analysis, user_explicit
    source_reference UUID NULL,           -- ID da conversa/evento que originou
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    expires_at TIMESTAMP NULL,            -- Memoria pode expirar

    CONSTRAINT fk_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
    CONSTRAINT chk_confidence CHECK (confidence >= 0 AND confidence <= 1)
);

CREATE INDEX idx_memory_user ON ai_memory (tenant_id, user_id, active);
CREATE INDEX idx_memory_type ON ai_memory (tenant_id, memory_type);
```

### 4.6 Fluxo de Escrita de Memoria

```
1. IA identifica padrao ou preferencia durante interacao
2. IA propoe memoria ao backend:
   {
     "tipo": "preference",
     "descricao": "Usuario prefere reservas no periodo da manha",
     "escopo": "user",
     "confianca": 0.8,
     "fonte": "conversation",
     "fonte_referencia": "uuid-da-conversa"
   }
3. Backend valida:
   - Nao contem dados sensiveis
   - Nao excede limite de 50 memorias
   - Nao duplica memoria existente
   - Confianca minima: 0.6
4. Memoria e salva como ativa
5. Log de auditoria registrado
```

### 4.7 Decay e Expiracao

- Memorias nao acessadas em 90 dias tem confianca reduzida em 0.1
- Memorias com confianca abaixo de 0.3 sao desativadas automaticamente
- Memorias podem ter `expires_at` definido (ex: "usuario esta de ferias ate 15/03")
- Memorias explicitas do usuario (source = user_explicit) nao sofrem decay

---

## 5. Contexto de Sessao

> **Documento completo:** [session-management.md](session-management.md)

### 5.1 Conceito

Contexto de sessao mantem continuidade dentro de uma conversa. Expira com a sessao.

### 5.2 Resumo de Tempos

| Parametro | Valor Padrao | Configuravel |
|-----------|-------------|-------------|
| Inactivity TTL | 10 minutos | Sim (admin do tenant, 5-30 min) |
| Duracao maxima absoluta | 2 horas | Sim (admin do tenant, 30 min - 4h) |
| Confirmacao pendente | 5 minutos | Nao (fixo, seguranca) |
| Sessoes concorrentes | 3 por usuario | Sim (admin do tenant, 1-5) |

### 5.3 Armazenamento

- Redis com TTL configuravel (padrao: 10 minutos, renovavel por atividade do usuario)
- Chave: `ai_session:{tenant_id}:{user_id}:{session_id}`
- Dados: ultimas N mensagens + resumo + tools usadas + confirmacoes pendentes

### 5.4 Context Window Management

| Situacao | Estrategia |
|----------|-----------|
| Conversa curta (< 10 mensagens) | Manter historico completo |
| Conversa media (10-30 mensagens) | Ultimas 10 + resumo das anteriores |
| Conversa longa (> 30 mensagens) | Ultimas 5 + resumo progressivo |
| Contexto excede limite do modelo | Summarization automatica dos turnos mais antigos |

Para schema completo, ciclo de vida, confirmacoes e configuracao, ver [session-management.md](session-management.md).

---

## 6. Hierarquia de Recuperacao

Quando a IA precisa responder, a recuperacao segue esta ordem de prioridade:

```
1. Memoria do usuario (preferencias, padroes)
     ↓
2. Contexto de sessao (conversa atual)
     ↓
3. RAG (fatos, historico, documentos)
     ↓
4. Tools (consultas ao sistema em tempo real)
     ↓
5. Conhecimento base do modelo (ultimo recurso)
```

Regras:
- Se a Memoria tem uma preferencia relevante, ela influencia a resposta.
- Se o Contexto de sessao ja abordou o assunto, nao repetir busca RAG.
- Se RAG retorna dados relevantes, citar a fonte.
- Se nenhuma fonte interna resolve, usar Tools para buscar dados atuais.
- Conhecimento base do modelo so e usado para formulacao, nunca para fatos do dominio.

---

## 7. Seguranca e LGPD

### 7.1 RAG

| Requisito | Implementacao |
|-----------|---------------|
| Isolamento | `tenant_id` obrigatorio em toda query de embeddings |
| Anonimizacao | PII removido antes da geracao de embedding |
| Retencao | Embeddings seguem politica de retencao do dado fonte |
| Exclusao | Quando dado fonte e excluido, embeddings correspondentes sao deletados |
| Auditoria | Todo acesso a embeddings registrado em ai_data_access_logs |

### 7.2 Memoria

| Requisito | Implementacao |
|-----------|---------------|
| Opt-in | Usuario deve aceitar armazenamento de memorias |
| Transparencia | Usuario pode listar todas suas memorias |
| Edicao | Usuario pode editar ou excluir qualquer memoria |
| Sem dados sensiveis | Validacao no backend impede armazenamento de PII |
| Expiracao | Memorias tem TTL ou decay de confianca |
| Base legal | Consentimento explicito (LGPD Art. 7, I) |

### 7.3 Contexto de Sessao

| Requisito | Implementacao |
|-----------|---------------|
| Efemero | TTL configuravel no Redis (padrao 10 min), sem persistencia permanente |
| Sem PII | Dados pessoais nao sao armazenados no contexto |
| Isolamento | Chave Redis inclui tenant_id + user_id |

---

## 8. Observabilidade

### 8.1 Metricas de RAG

| Metrica | Descricao |
|---------|-----------|
| `rag.query.count` | Queries por tenant por periodo |
| `rag.query.latency` | Latencia P50/P95/P99 |
| `rag.results.avg` | Media de resultados por query |
| `rag.threshold.filtered` | % de resultados abaixo do threshold |
| `rag.embeddings.total` | Total de embeddings por tenant |
| `rag.embeddings.stale` | Embeddings nao acessados em 90 dias |

### 8.2 Metricas de Memoria

| Metrica | Descricao |
|---------|-----------|
| `memory.total` | Total de memorias ativas por tenant |
| `memory.writes` | Novas memorias por periodo |
| `memory.decayed` | Memorias desativadas por decay |
| `memory.user_deleted` | Memorias excluidas pelo usuario |

---

## 9. Anti-padroes

| Anti-padrao | Por que e ruim | Alternativa |
|-------------|---------------|-------------|
| Usar RAG como cache | RAG e para busca semantica, nao cache | Usar Redis para cache |
| Salvar conversa inteira como embedding | Volume excessivo, baixa relevancia | Salvar apenas decisoes e fatos |
| Misturar tenants em embeddings | Violacao de isolamento | tenant_id obrigatorio |
| "IA que lembra tudo" | Viola LGPD, cria dependencia | Memoria curada com limites |
| Embeddings sem versionamento | Modelo muda, resultados degradam | model_version obrigatorio |
| Contexto de sessao sem TTL | Dados ficam indefinidamente | TTL configuravel no Redis (padrao 10 min) |
| Memoria sem decay | Memorias obsoletas influenciam respostas | Decay de confianca a cada 90 dias |
| RAG sem threshold de similaridade | Resultados irrelevantes poluem contexto | Threshold minimo de 0.75 |
