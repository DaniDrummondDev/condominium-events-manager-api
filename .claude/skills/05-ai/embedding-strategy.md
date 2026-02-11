# embedding-strategy.md — Estratégia de Embeddings

## 1. Objetivo

Definir a estratégia oficial de geração, armazenamento, versionamento, isolamento e uso de **embeddings vetoriais** no **Condominium Events Manager API**, garantindo:

- Isolamento absoluto por tenant
- Governança e rastreabilidade completas
- Compatibilidade com DDD e Clean Architecture
- Independência de provedores de IA
- Segurança, compliance e auditabilidade
- Evolução segura de modelos e vetores ao longo do tempo

Embeddings **não executam decisões** e **não acionam regras de negócio**. Servem exclusivamente como **mecanismo de recuperação semântica (RAG)** e apoio analítico.

---

## 2. Princípios Arquiteturais

### 2.1 Princípios Não Negociáveis

- Embeddings **nunca** são usados diretamente por controllers
- Todo acesso ocorre via **Use Cases**
- Nenhum embedding cruza limites de tenant
- Nenhum dado sensível é embedado sem classificação explícita
- Todo embedding é rastreável até sua origem
- Embeddings são **descartáveis e regeneráveis**

---

## 3. Casos de Uso Permitidos

### 3.1 Casos Válidos

- Busca semântica em:
  - Regras internas
  - Políticas do condomínio
  - Histórico de reservas
  - Logs interpretáveis (não sensíveis)
- Assistente conversacional com RAG
- Classificação semântica (ex: tipo de solicitação)
- Sugestão de respostas ou ações (com confirmação humana)

### 3.2 Casos Proibidos

- Tomada automática de decisão
- Avaliação de usuários ou moradores
- Score de comportamento
- Geração de embeddings de:
  - Documentos financeiros brutos
  - Dados biométricos
  - Credenciais
  - Tokens ou segredos
  - Dados pessoais sensíveis sem anonimização

---

## 4. Arquitetura de Embeddings

### 4.1 Fluxo Geral

[Entidade de Domínio]\
↓\
[DTO de Embedding]\
↓\
[Embedding Service (Interface)]\
↓\
[Provider Adapter]\
↓\
[Vector Store (pgvector)]


---

## 5. Modelagem de Dados (PostgreSQL + pgvector)

### 5.1 Tabela Base

Cada tenant possui seu próprio schema ou database.

```sql
CREATE TABLE embeddings (
  id UUID PRIMARY KEY,
  tenant_id UUID NOT NULL,
  source_type VARCHAR(50) NOT NULL,
  source_id UUID NOT NULL,
  embedding VECTOR NOT NULL,        -- Dimensão configurável (1536, 768, 1024...)
  model_version VARCHAR(50) NOT NULL,
  content_hash CHAR(64) NOT NULL,
  metadata JSONB,
  created_at TIMESTAMP NOT NULL
);
```
### 5.2 Índices

```sql
CREATE INDEX idx_embeddings_vector
ON embeddings
USING ivfflat (embedding vector_cosine_ops);

CREATE INDEX idx_embeddings_source
ON embeddings (source_type, source_id);
```

## 6. Versionamento de Embeddings

### 6.1 Estratégia

* Nenhum embedding é sobrescrito
* Mudança de modelo → nova versão
* Conteúdo alterado → novo embedding

### 6.2 Campos Críticos

* model_version
* content_hash

### 6.3 Regra

> Embeddings antigos podem coexistir até serem explicitamente invalidados por política de retenção.

---

## 7. Isolamento por Tenant

### 7.1 Regras

* tenant_id é obrigatório em todas as queries
* Nunca usar embeddings cross-tenant
* Schema-per-tenant ou DB-per-tenant obrigatório
* Queries vetoriais sempre escopadas

Exemplo:

```sql
SELECT *
FROM embeddings
WHERE tenant_id = :tenant_id
ORDER BY embedding <-> :query_vector
LIMIT 5;
```

## 8. Geração de Embeddings

### 8.1 Responsabilidade

* Apenas Application Layer
* Nunca diretamente do Controller
* Sempre via EmbeddingUseCase

### 8.2 Pipeline

1. Normalização do texto
2. Sanitização (remoção de dados sensíveis)
3. Hash do conteúdo
4. Verificação de duplicidade
5. Chamada ao provider
6. Persistência
7. Log de auditoria

---

## 9. Governança de Dados

### 9.1 Classificação Obrigatória

Todo conteúdo embedado deve conter metadados:

```json
{
  "data_classification": "internal",
  "pii": false,
  "source": "regulamento_condominio",
  "created_by": "system"
}
```

### 9.2 Dados Pessoais

* Preferir anonimização ou pseudonimização
* Nunca embedar dados sensíveis sem aprovação explícita
* Embeddings não substituem dados originais

## 10. Retenção e Expurgo

### 10.1 Políticas

* Seguem data-retention-policy.md
* Embeddings são apagados quando:
  * Tenant é removido
  * Fonte é removida
  * Política de retenção expira
  * Modelo fica obsoleto

### 10.2 Regra Importante

> Embeddings não são backup e não garantem preservação de informação.

## 11.  Segurança

### 11.1 Proteções

* Embeddings não são expostos via API pública
* Nenhum retorno bruto de vetor
* Apenas resultados interpretados

### 11.2 Logs

Toda ação gera evento de auditoria:

* Geração
* Consulta
* Expurgo
* Regeneração

## 12. Observabilidade (Integração)

* Métricas de:
  * Quantidade por tenant
  * Tamanho do índice
  * Latência de busca
* Integração direta com:
  * ai-observability.md

## 13.  Anti-Padrões

❌ Usar embeddings como cache
❌ Usar embeddings para regras de negócio
❌ Compartilhar embeddings entre tenants
❌ Embedar dados sem classificação
❌ Atualizar embeddings in-place

## 14. Checklist de Conformidade

 * [ ] Isolamento por tenant garantido
 * [ ] Versionamento implementado
 * [ ] Classificação de dados aplicada
 * [ ] Auditoria ativa
 * [ ] Providers desacoplados
 * [ ] Regeneração possível
 * [ ] Compliance LGPD atendido

## 15. Status

Obrigatório para qualquer funcionalidade baseada em IA ou busca semântica.

Qualquer violação desta estratégia invalida a feature.