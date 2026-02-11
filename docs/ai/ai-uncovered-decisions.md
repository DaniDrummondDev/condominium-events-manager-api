# Decisoes Tecnicas — Areas Nao Cobertas de IA

## Objetivo

Documento consolidado com todas as decisoes tecnicas tomadas para as ~40 areas identificadas em [uncovered-areas.md](uncovered-areas.md). Cada decisao foi discutida e aprovada antes de ser registrada.

**Data:** 2026-02-11
**Status:** Aprovado

---

## 1. Conversational Assistant

### 1.1 Intent Detection Engine

**Decisao:** Function Calling nativo do modelo.

- O modelo recebe as tool definitions e identifica a intencao diretamente via function calling
- Sem classificador separado, sem pipeline de 2 estagios
- **Evolucao futura:** Hibrida (Function Calling + fallback classification) quando necessario

**Justificativa:** Function Calling e nativo dos provedores (OpenAI, Azure), elimina uma camada intermediaria, e o modelo ja retorna parametros estruturados.

### 1.2 Multi-turn Conversation State Machine

**Decisao:** 6 estados com transicoes controladas.

| Estado | Descricao | Timeout |
|--------|-----------|---------|
| `IDLE` | Sem conversa ativa | Inactivity TTL (10 min) |
| `PROCESSING_INTENT` | Processando mensagem do usuario | 30s |
| `TOOL_PROPOSED` | Acao proposta aguardando confirmacao | 5 min (fixo) |
| `EXECUTING` | Executando acao confirmada | 30s |
| `RESPONDING` | Gerando resposta ao usuario | 30s |
| `AWAITING_INPUT` | Aguardando mais informacoes do usuario | Inactivity TTL |

**Transicoes validas:**

| De | Para | Trigger |
|----|------|---------|
| IDLE | PROCESSING_INTENT | Usuario envia mensagem |
| PROCESSING_INTENT | TOOL_PROPOSED | Intent requer acao |
| PROCESSING_INTENT | RESPONDING | Intent e consulta/conversa |
| PROCESSING_INTENT | AWAITING_INPUT | Falta informacao |
| TOOL_PROPOSED | EXECUTING | Usuario confirma |
| TOOL_PROPOSED | RESPONDING | Usuario rejeita |
| TOOL_PROPOSED | IDLE | Timeout (5 min) |
| EXECUTING | RESPONDING | Acao concluida |
| EXECUTING | RESPONDING | Acao falhou (com mensagem de erro) |
| RESPONDING | IDLE | Resposta entregue |
| AWAITING_INPUT | PROCESSING_INTENT | Usuario responde |
| AWAITING_INPUT | IDLE | Timeout (inactivity TTL) |

**Regras de seguranca:**
- `TOOL_PROPOSED → EXECUTING` requer nonce valido
- `EXECUTING` nao pode ser cancelado (acao ja em andamento)
- Qualquer estado com timeout retorna a `IDLE`
- Transicoes invalidas sao rejeitadas silenciosamente com log

### 1.3 Conversation History Summarization

**Decisao:** Sumarizacao incremental a cada 10 mensagens.

- **Trigger:** `message_count % 10 == 0`
- **Modelo:** LLaMA 8B local via Ollama (tarefa estruturada, nao criativa)
- **Algoritmo:** Resumo anterior + novas mensagens → novo resumo consolidado
- **Max output:** 200 palavras
- **Fallback:** Se Ollama indisponivel, concatenar ultimas 3 mensagens como resumo basico

**O que preservar no resumo:**
- Intencoes expressas pelo usuario
- Acoes ja executadas (tools)
- Decisoes tomadas
- Informacoes relevantes para continuidade

**O que descartar:**
- Saudacoes e small talk
- Mensagens de confirmacao simples
- Detalhes tecnicos internos

### 1.4 Fallback Responses Catalog

**Decisao:** 9 cenarios com mensagens PT-BR em `config/ai.php`.

| Cenario | Mensagem |
|---------|----------|
| `intent_not_understood` | "Desculpe, nao entendi sua solicitacao. Pode reformular?" |
| `out_of_scope` | "Essa solicitacao esta fora do que posso ajudar. Posso ajudar com reservas, regras do condominio e informacoes gerais." |
| `provider_error` | "Estou com dificuldades tecnicas no momento. Tente novamente em alguns instantes." |
| `rate_limited` | "Voce fez muitas solicitacoes. Aguarde um momento antes de tentar novamente." |
| `action_requires_confirmation` | "Para executar esta acao, preciso da sua confirmacao. Deseja prosseguir?" |
| `confirmation_expired` | "A proposta de acao expirou. Deseja que eu refaca?" |
| `action_failed` | "Nao foi possivel completar a acao. Motivo: {reason}. Deseja tentar novamente?" |
| `ai_disabled` | "O assistente de IA esta temporariamente indisponivel." |
| `general_error` | "Ocorreu um erro inesperado. Por favor, tente novamente." |

### 1.5 Conversation Analytics

**Decisao:** Nao implementar no MVP. Baixa prioridade.

Documentar como evolucao futura. Metricas que serao relevantes quando implementadas:
- Taxa de resolucao (conversas com acao concluida / total)
- Numero medio de turnos por conversa
- Intencoes mais frequentes
- Taxa de fallback (conversas que cairam em fallback / total)

---

## 2. Action Orchestrator

### 2.1 Orchestrator State Machine

**Decisao:** 6 estados para o ciclo de vida de uma acao.

```
DETECTED → PROPOSED → EXECUTING → COMPLETED
                  ↘               ↗
                   → CANCELLED
                   → FAILED
```

| Estado | Descricao | Proximo |
|--------|-----------|---------|
| `DETECTED` | Intent classificada como acao | PROPOSED |
| `PROPOSED` | Acao apresentada ao usuario | EXECUTING, CANCELLED |
| `EXECUTING` | Backend processando a acao | COMPLETED, FAILED |
| `COMPLETED` | Acao concluida com sucesso | (terminal) |
| `FAILED` | Acao falhou | (terminal) |
| `CANCELLED` | Usuario rejeitou ou timeout | (terminal) |

**Timeouts:**
- PROPOSED → CANCELLED: 5 minutos (confirmation TTL)
- EXECUTING → FAILED: 30 segundos (provider + use case timeout)

### 2.2 Confirmation Token Flow

**Decisao:** Nonce UUID v4 armazenado em Redis com TTL de 5 minutos.

**Fluxo:**
```
1. IA propoe acao
2. Backend gera nonce (UUID v4)
3. Armazena em Redis: ai_confirmation:{tenant_id}:{session_id}:{nonce}
   - TTL: 300s (5 min, fixo)
   - Valor: { tool, parameters, proposed_at }
4. Retorna ao usuario: descricao da acao + nonce
5. Usuario confirma via POST /tenant/ai/confirm { nonce }
6. Backend valida:
   - Nonce existe no Redis?
   - Nao expirou?
   - Pertence ao tenant/session correto?
7. Se valido: executa tool, deleta nonce
8. Se invalido: rejeita com 400/410
```

**Regras:**
- Um nonce por acao, nunca reutilizar
- Apenas uma confirmacao pendente por sessao
- Nova proposta cancela a anterior automaticamente
- Nonce validado exclusivamente no backend

### 2.3 Multi-step Actions

**Decisao:** Decomposicao em acoes atomicas (sem workflow engine).

Acoes complexas sao decompostas em etapas independentes, cada uma com sua propria confirmacao:

**Exemplo — Reserva com convidados + fornecedor:**
```
Etapa 1: Verificar disponibilidade (automatica, sem confirmacao)
Etapa 2: Criar reserva (requer confirmacao)
Etapa 3: Registrar convidados (requer confirmacao)
Etapa 4: Solicitar fornecedor (requer confirmacao)
```

**Regras:**
- Cada etapa e uma tool atomica do catalogo
- IA propoe uma etapa por vez, em sequencia
- Se uma etapa falha, as anteriores ja confirmadas permanecem (compensating actions)
- IA informa o progresso: "Etapa 2 de 4: Criar reserva. Confirma?"
- Maximo de 5 etapas por fluxo multi-step

### 2.4 Action Rollback Strategy

**Decisao:** Compensating Actions (nao rollback transacional).

Quando uma acao confirmada falha parcialmente, o sistema executa acoes compensatorias:

| Tool Original | Compensating Action | Automatico? |
|---------------|-------------------|-------------|
| `criar_reserva` | `cancelar_reserva` | Sim |
| `registrar_convidados` | `remover_convidados` | Sim |
| `solicitar_fornecedor` | `cancelar_solicitacao` | Sim |
| `aplicar_penalidade` | Nenhuma (irreversivel) | Nao |
| `publicar_aviso` | Nenhuma (ja publicado) | Nao |

**Regras:**
- Compensating action e executada automaticamente quando a etapa seguinte falha
- Para acoes irreversiveis, o sistema notifica o usuario e sugere acao manual
- Toda compensacao e registrada no audit log
- Campo `compensatingAction` no `ToolDefinition` define a tool de compensacao

### 2.5 Concurrent Action Handling

**Decisao:** Uma confirmacao pendente por sessao. Nova proposta cancela a anterior.

**Fluxo:**
```
Proposta A pendente
  → Usuario envia nova mensagem que gera Proposta B
  → Proposta A cancelada automaticamente (nonce deletado do Redis)
  → Proposta B se torna a pendente
  → Log: "Proposta anterior cancelada por nova solicitacao"
```

**Justificativa:** Simplifica UX e implementacao. O usuario nao precisa gerenciar multiplas propostas simultaneas.

---

## 3. Tool Registry

### 3.1 ToolRegistry Service

**Decisao:** Classe `ToolRegistryService` que carrega tools, filtra por role + feature flags, e serializa para o modelo.

**Value Object — ToolDefinition:**
```
ToolDefinition:
  - name: string (ex: 'verificar_disponibilidade')
  - description: string (em PT-BR, para o modelo)
  - parameters: JSON Schema (input esperado)
  - category: enum (Context, Query, Validation, Transactional, Communication, Support, Admin)
  - requiresConfirmation: bool
  - allowedRoles: TenantRole[] (ex: [Sindico, Administradora])
  - featureFlag: ?string (ex: 'ai_reservations')
  - version: string (ex: 'v1')
  - compensatingAction: ?string (ex: 'cancelar_reserva')
  - rateLimit: int (requests/min para esta tool)
```

**Metodo principal:**
```
getAvailableTools(role, tenantFeatureFlags): ToolDefinition[]
  → Carrega todas as tools do catalogo
  → Filtra por role (allowedRoles)
  → Filtra por feature flag (tenant tem a flag ativa?)
  → Retorna lista filtrada
```

### 3.2 Tool Schema Validation

**Decisao:** Validacao automatica dos parametros via JSON Schema.

Quando o modelo propoe uma tool_call, os parametros sao validados contra o schema definido no `ToolDefinition.parameters` antes de qualquer execucao.

**Pipeline:**
```
Modelo retorna tool_call { name, parameters }
  → ToolRegistry busca ToolDefinition por name
  → Valida parameters contra JSON Schema
  → Se invalido: rejeita, retorna erro ao modelo/usuario
  → Se valido: prossegue para autorizacao
```

### 3.3 Tool Execution Pipeline

**Decisao:** Pipeline de 7 etapas.

```
1. PARSE     → Extrair tool_call do response do modelo
2. VALIDATE  → Validar parametros contra JSON Schema da tool
3. AUTHORIZE → Verificar role + feature flags + rate limit da tool
4. CLASSIFY  → Requer confirmacao? (requiresConfirmation)
               → Sim: ir para PROPOSED state, aguardar confirmacao
               → Nao: prosseguir
5. EXECUTE   → Chamar use case correspondente no Application layer
6. FORMAT    → Formatar resultado para o modelo e para o usuario
7. LOG       → Registrar em audit log (tool, params, resultado, duracao)
```

### 3.4 Tool Discovery for AI

**Decisao:** Format function calling JSON para o modelo.

As tools sao apresentadas no formato de function calling nativo do provider:

```json
{
  "type": "function",
  "function": {
    "name": "verificar_disponibilidade",
    "description": "Verifica se um espaco comum esta disponivel para reserva em uma data e horario especificos.",
    "parameters": {
      "type": "object",
      "properties": {
        "space_id": { "type": "string", "description": "ID do espaco comum" },
        "date": { "type": "string", "format": "date" },
        "start_time": { "type": "string", "format": "time" },
        "end_time": { "type": "string", "format": "time" }
      },
      "required": ["space_id", "date", "start_time", "end_time"]
    }
  }
}
```

`ToolRegistryService` serializa as `ToolDefinition[]` neste formato.

### 3.5 Tool Usage Analytics

**Decisao:** Nao implementar no MVP. Baixa prioridade.

Dados ja capturados no audit log (etapa 7 do pipeline) permitem analise manual quando necessario.

---

## 4. RAG (Retrieval-Augmented Generation)

### 4.1 Ingestion Job Implementation

**Decisao:** `IndexDocumentEmbeddingsJob` como job dedicado.

| Parametro | Valor |
|-----------|-------|
| Queue | `ai-embeddings` |
| Retry | 3 tentativas, backoff exponencial (30s, 120s, 480s) |
| Batch | 100 chunks por execucao |
| Idempotencia | `content_hash` (SHA-256) — mesmo conteudo nao gera duplicata |
| Timeout | 5 minutos por execucao |

**Pipeline do job:**
```
Recebe: source_type, source_id, tenant_id
  → Busca documento original no banco do tenant
  → Aplica PII Scrubbing (regex)
  → Aplica chunking strategy (conforme tipo de documento)
  → Para cada chunk:
    → Calcula content_hash (SHA-256)
    → Verifica se hash+model_version ja existe (dedup)
    → Se nao existe: gera embedding via provider
    → Persiste em ai_embeddings com metadados
  → Registra em ai_data_access_logs
```

### 4.2 Document Change Detection

**Decisao:** Event-driven via domain events.

| Domain Event | Acao RAG |
|-------------|----------|
| `RegulationPublished` | Indexar novo regulamento |
| `RegulationUpdated` | Re-indexar (deletar antigos, indexar novos) |
| `RegulationRevoked` | Deletar embeddings correspondentes |
| `AssemblyMinutesPublished` | Indexar ata |
| `PenaltyApplied` | Indexar decisao de penalidade |
| `ReservationCompleted` | Indexar no batch diario (nao individual) |

**Implementacao:** Listeners que disparam `IndexDocumentEmbeddingsJob` no evento correspondente.

### 4.3 PII Scrubbing Pipeline

**Decisao:** Regex patterns para MVP, evoluindo para Regex + NER quando necessario.

**Patterns implementados:**

| Tipo | Pattern | Substituicao |
|------|---------|-------------|
| CPF | `\d{3}\.?\d{3}\.?\d{3}-?\d{2}` | `[CPF_REMOVIDO]` |
| Telefone | `\(?\d{2}\)?\s?\d{4,5}-?\d{4}` | `[TELEFONE_REMOVIDO]` |
| Email | `[\w.-]+@[\w.-]+\.\w+` | `[EMAIL_REMOVIDO]` |
| CEP | `\d{5}-?\d{3}` | `[CEP_REMOVIDO]` |
| Nomes proprios | Lista de patterns comuns (Sr./Sra. + nome) | `[NOME_REMOVIDO]` |

**Servico:** `PiiScrubberInterface` com metodo `scrub(string $text): ScrubResult` que retorna texto limpo + lista de itens removidos (para auditoria).

### 4.4 Hybrid Search Implementation

**Decisao:** Vector similarity (pgvector) + keyword match (tsvector) com Reciprocal Rank Fusion.

**Nova coluna na tabela ai_embeddings:**
```sql
content_tsv TSVECTOR GENERATED ALWAYS AS (to_tsvector('portuguese', content_text)) STORED
```
Com indice GIN: `CREATE INDEX idx_embeddings_tsv ON ai_embeddings USING GIN (content_tsv);`

**Algoritmo RRF (Reciprocal Rank Fusion):**
```
score_final = w1 * (1 / (k + rank_vector)) + w2 * (1 / (k + rank_keyword))
```

| Parametro | Valor |
|-----------|-------|
| w1 (peso vector) | 0.7 |
| w2 (peso keyword) | 0.3 |
| k (constante RRF) | 60 |

**Fluxo:**
```
Query do usuario
  → Gerar embedding da query
  → Busca vector: top 20 por cosine similarity (filtrado por tenant_id)
  → Busca keyword: top 20 por ts_rank (filtrado por tenant_id)
  → Merge via RRF
  → Filtrar abaixo de threshold (0.75)
  → Re-ranking (recencia)
  → Retornar top 5
```

### 4.5 Re-ranking Algorithm

**Decisao:** 85% relevancia + 15% recencia.

```
score_final = score_hybrid * 0.85 + recency_bonus * 0.15
```

**Recency bonus (4 niveis):**

| Idade do documento | Bonus |
|-------------------|-------|
| < 30 dias | 1.0 |
| 30-90 dias | 0.7 |
| 90-365 dias | 0.4 |
| > 365 dias | 0.1 |

### 4.6 RAG Evaluation/Quality

**Decisao:** Nao implementar no MVP. Baixa prioridade.

Avaliacao de qualidade (precision@K, recall@K, MRR) requer dataset anotado e volume de dados reais. Documentar como evolucao futura.

---

## 5. Memoria da IA

### 5.1 Memory Retrieval for Prompts

**Decisao:** Top 5 memorias por confianca, filtradas por tipo relevante.

**Query:**
```sql
SELECT description, memory_type, confidence
FROM ai_memory
WHERE tenant_id = :tenant_id
  AND user_id = :user_id
  AND active = true
  AND confidence >= 0.3
ORDER BY confidence DESC
LIMIT 5
```

**Regras:**
- Maximo 5 memorias no prompt (~200 tokens budget)
- Confianca minima: 0.3
- Sem PII no conteudo da memoria (validado na escrita)
- Formatadas como texto simples no bloco de contexto do prompt

### 5.2 Memory Detection Logic

**Decisao:** Deteccao via instrucao no prompt (Option A).

O system prompt instrui o modelo a identificar padroes e propor memorias na resposta:

```
Se durante a conversa voce identificar uma preferencia ou padrao recorrente do usuario,
proponha como memoria usando o formato:
[MEMORY_PROPOSAL]
{
  "tipo": "preference|pattern|style",
  "descricao": "...",
  "confianca": 0.6-1.0
}
[/MEMORY_PROPOSAL]
```

**Fluxo:**
```
Modelo retorna resposta com [MEMORY_PROPOSAL]
  → Backend extrai proposta
  → Valida: nao contem PII, confianca >= 0.6, nao excede 50 memorias
  → Apresenta ao usuario: "Posso lembrar que voce prefere reservas pela manha?"
  → Usuario confirma → salva memoria
  → Usuario rejeita → descarta
```

### 5.3 Memory Deduplication

**Decisao:** Comparacao por similarity de descricao.

Antes de salvar nova memoria:
```
1. Buscar memorias ativas do mesmo tipo para o usuario
2. Comparar descricao (case-insensitive, normalizada)
3. Se similarity > 0.8 (Levenshtein ou similar):
   → Incrementar confianca da existente (min(1.0, confianca + 0.1))
   → Atualizar updated_at
   → Nao criar nova
4. Se nao similar: criar nova
```

### 5.4 Decay Job

**Decisao:** Job semanal `DecayMemoryConfidenceJob`.

| Parametro | Valor |
|-----------|-------|
| Frequencia | Semanal (domingo 3h) |
| Queue | `ai-maintenance` |
| Logica | Memorias nao acessadas em 90 dias → confianca - 0.1 |
| Desativacao | Confianca < 0.3 → active = false |
| Excecao | `source = user_explicit` nao sofre decay |

### 5.5 User Memory Management API

**Decisao:** CRUD completo via endpoints do tenant.

| Endpoint | Metodo | Descricao |
|----------|--------|-----------|
| `/tenant/ai/memories` | GET | Listar memorias do usuario autenticado |
| `/tenant/ai/memories/{id}` | GET | Detalhe de uma memoria |
| `/tenant/ai/memories/{id}` | PATCH | Editar descricao ou desativar |
| `/tenant/ai/memories/{id}` | DELETE | Excluir permanentemente |

Todos escopados por `tenant_id + user_id` do JWT.

### 5.6 Memory Consent Flow

**Decisao:** Opt-in na primeira interacao com IA.

```
Usuario envia primeira mensagem para IA
  → Verificar se usuario ja tem consent registrado
  → Se nao:
    → Responder normalmente + incluir banner:
      "Posso lembrar suas preferencias para melhorar o atendimento.
       Voce aceita? [Sim] [Nao] [Saber mais]"
    → Resposta do usuario registrada como consent (aceite/recusa)
    → Consent armazenado na tabela do usuario (ai_memory_consent: bool)
  → Se ja tem consent e aceitou: memorias ativas
  → Se ja tem consent e recusou: memorias desabilitadas
```

Consent pode ser alterado a qualquer momento nas configuracoes do usuario.

---

## 6. Contexto de Sessao

### 6.1 Session Service Implementation

**Decisao:** `AiSessionServiceInterface` no Application layer + `RedisAiSessionService` na Infrastructure.

**Contract:**
```php
interface AiSessionServiceInterface
{
    public function getOrCreate(Uuid $tenantId, Uuid $userId): AiSession;
    public function addMessage(string $sessionId, SessionMessage $message): void;
    public function getPendingConfirmation(string $sessionId): ?PendingConfirmation;
    public function setPendingConfirmation(string $sessionId, PendingConfirmation $confirmation): void;
    public function clearPendingConfirmation(string $sessionId): void;
    public function getContextForPrompt(string $sessionId): SessionContext;
    public function destroy(string $sessionId): void;
    public function destroyAllForUser(Uuid $tenantId, Uuid $userId): void;
}
```

`getContextForPrompt()` retorna DTO `SessionContext` com: `summary`, `recentMessages[]`, `toolsExecuted[]`, `ragSourcesUsed[]`.

### 6.2 Summarization Trigger

**Decisao:** Threshold fixo de 10 mensagens, dentro do `addMessage()`.

```
Ao adicionar mensagem:
  → message_count++
  → Se message_count % 10 == 0:
    → Pegar mensagens [0..N-10]
    → Enviar para SummarizationService
    → Atualizar campo "summary" (append ao resumo existente)
    → Remover mensagens sumarizadas do array
    → Manter apenas ultimas 10
```

Sem contagem de tokens no MVP — threshold de mensagens e simples e previsivel.

### 6.3 Summarization Prompt

**Decisao:** Prompt `session_summarize_v1` ja definido no session-management.md.

- **Modelo:** LLaMA 8B local via Ollama
- **Contract:** `SummarizationServiceInterface` com `summarize(string $previousSummary, array $messages): string`
- **Prompt incremental:** resumo anterior + novas mensagens → resumo consolidado
- **Max output:** 200 palavras
- **Fallback:** Se Ollama indisponivel, concatenar ultimas 3 mensagens como resumo basico

### 6.4 Session Recovery

**Decisao:** Sem recovery. Sessoes sao descartaveis.

| Cenario | Comportamento |
|---------|---------------|
| Redis indisponivel | IA retorna "Assistente temporariamente indisponivel" |
| Redis restart | Todas sessoes perdidas, usuarios iniciam novas |
| Sessao corrompida | Destruir e criar nova, log de erro |

**Justificativa:** Sessoes nao contem dados de dominio. Custo de recovery nao se justifica. Redis RDB cobre o cenario de restart como configuracao de infraestrutura.

---

## 7. Prompt Management

### 7.1 PromptBuilder Service

**Decisao:** `PromptBuilderInterface` no Application layer com pipeline de 8 etapas.

**PromptRequest DTO:**
- `templateId` — ex: `reservation_assistant_v1`
- `variables` — array associativo com valores de template
- `userInput` — mensagem do usuario (sera sanitizada)
- `sessionContext` — SessionContext (summary + recent messages)
- `memories` — AiMemory[] (ate 5, ja filtradas)
- `ragChunks` — RagChunk[] (ate 5, ja filtradas)
- `availableTools` — ToolDefinition[] (ja filtradas por role/feature flag)

**Pipeline do build():**
```
1. Carregar template por ID + versao ativa
2. Injetar variaveis no template ({condominium_name}, {user_role}, etc.)
3. Montar bloco de memorias (formatado como texto)
4. Montar bloco de contexto de sessao (summary + mensagens recentes)
5. Montar bloco RAG (chunks com citacao de fonte)
6. Montar bloco de tools (function calling schema)
7. Sanitizar userInput (PII scrubbing + injection detection)
8. Compor prompt final respeitando o token budget (5200 tokens input)
```

**BuiltPrompt DTO:**
- `systemPrompt` — string final do system prompt
- `messages` — array de mensagens (history + user input)
- `tools` — array de tool definitions (function calling format)
- `estimatedTokens` — contagem estimada

### 7.2 Prompt Testing Framework

**Decisao:** Testes baseados em Pest com trait `AssertsPrompts`.

| Tipo de teste | O que valida |
|---------------|-------------|
| Guardrail assertion | Prompt contém guardrails obrigatorios |
| Variable injection | Variaveis substituidas corretamente |
| Token budget | Prompt nao excede 5200 tokens |
| Injection resistance | Input malicioso nao corrompe estrutura |
| Template completeness | Nenhuma variavel {sem_valor} no prompt final |

**Trait:**
```php
trait AssertsPrompts
{
    protected function assertPromptContains(BuiltPrompt $prompt, string $expected): void;
    protected function assertNoUnresolvedVariables(BuiltPrompt $prompt): void;
    protected function assertTokenCountBelow(BuiltPrompt $prompt, int $max): void;
    protected function assertPromptStructureIntact(BuiltPrompt $prompt): void;
}
```

Fixtures em `tests/fixtures/prompts/`.

### 7.3 Prompt A/B Testing

**Decisao:** Nao implementar. Baixa prioridade.

Volume insuficiente para significancia estatistica no inicio. Campo `version` no template + metricas de observabilidade permitem comparacao manual quando necessario.

### 7.4 Dynamic Prompt Selection

**Decisao:** Mapa `intent → template_id` em config.

```php
// config/ai.php
'prompt_templates' => [
    'reservation_create'    => 'reservation_assistant_v1',
    'reservation_cancel'    => 'reservation_assistant_v1',
    'reservation_query'     => 'reservation_assistant_v1',
    'rule_query'            => 'rule_explanation_v1',
    'announcement_draft'    => 'announcement_draft_v1',
    'support_response'      => 'support_suggestion_v1',
    'general_query'         => 'general_assistant_v1',
    'default'               => 'general_assistant_v1',
],
```

**Fluxo:**
```
Mensagem do usuario
  → Function Calling detecta intent (ex: reservation_create)
  → Lookup no mapa: 'reservation_create' → 'reservation_assistant_v1'
  → PromptBuilder.build() usa template_id selecionado
  → Se intent nao mapeada → usa 'default'
```

### 7.5 Prompt Injection Detection Service

**Decisao:** Regex + heuristicas para MVP. Evoluir para modelo classificador quando necessario.

| Padrao | Exemplo | Acao |
|--------|---------|------|
| Role override | "ignore your instructions", "you are now" | Flag + sanitize |
| System prompt leak | "repeat your system prompt" | Block + fallback |
| Delimiter injection | `###`, `[SYSTEM]`, `<\|im_start\|>` | Sanitize delimiters |
| Encoding evasion | Base64, Unicode tricks, homoglyphs | Normalize antes de verificar |
| Excessive length | > 2000 chars | Truncate com aviso |

**Resultado:** `InjectionAnalysis` com `risk_level` (NONE, LOW, MEDIUM, HIGH) e `action` (ALLOW, SANITIZE, BLOCK).

- LOW → permite, loga
- MEDIUM → sanitiza input, permite, loga
- HIGH → bloqueia, retorna fallback, loga como alerta

**Performance:** < 5ms por analise, cobre ~90% dos ataques conhecidos.

---

## 8. Provider & Infrastructure

### 8.1 Prism Integration Details

**Decisao:** Prism como HTTP client layer. Contracts proprios na Application permanecem independentes.

**Arquitetura em camadas:**
```
Application Layer (nossos contracts)
  → TextGenerationInterface
  → EmbeddingGenerationInterface

Infrastructure Layer (implementacoes)
  → OpenAITextGeneration  ← usa Prism internamente
  → OllamaTextGeneration  ← usa Prism internamente

Decorators (cross-cutting)
  → AIProviderFallbackDecorator  ← circuit breaker + fallback
  → AIProviderCostDecorator      ← tracking de custo
```

**Regra:** Use cases na Application nunca importam Prism — apenas os contracts proprios.

### 8.2 Streaming Responses

**Decisao:** Nao implementar no MVP. Baixa prioridade.

**Justificativa:**
- Complexidade significativa (SSE/WebSocket + frontend handler)
- Respostas da IA no contexto de condominio sao curtas (< 500 tokens)
- Function calling nao suporta streaming nativamente em todos os providers

**Futuro:** SSE via `GET /tenant/ai/stream/{session_id}`.

### 8.3 Batch Processing

**Decisao:** Jobs dedicados por tipo de operacao batch.

| Operacao Batch | Job | Queue | Trigger |
|----------------|-----|-------|---------|
| Re-indexar embeddings | `ReindexTenantEmbeddingsJob` | `ai-embeddings` | Manual (admin) |
| Indexar docs pendentes | `IndexPendingDocumentsJob` | `ai-embeddings` | Scheduled (diario) |
| Decay de memorias | `DecayMemoryConfidenceJob` | `ai-maintenance` | Scheduled (semanal) |
| Limpar embeddings expirados | `CleanExpiredEmbeddingsJob` | `ai-maintenance` | Scheduled (semanal) |

**Padrao comum:** Processamento por tenant, batch 100, idempotente, retry com backoff (3 tentativas). Rate limit: max 10 chamadas/segundo ao provider.

### 8.4 Cost Attribution to Plans

**Decisao:** Tracking por tenant com limites definidos no plano.

| Plano | Limite mensal IA (USD) | Requests/mes | Embeddings max |
|-------|----------------------|--------------|----------------|
| Basic | $10 | 5.000 | 10.000 |
| Professional | $50 | 25.000 | 50.000 |
| Enterprise | $200 | 100.000 | 200.000 |

**Tabela `ai_usage_summaries`** (platform database):
```sql
CREATE TABLE ai_usage_summaries (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL,
    period_month DATE NOT NULL,
    total_requests INT DEFAULT 0,
    total_input_tokens BIGINT DEFAULT 0,
    total_output_tokens BIGINT DEFAULT 0,
    total_embeddings_generated INT DEFAULT 0,
    estimated_cost_usd DECIMAL(10,4) DEFAULT 0,
    plan_limit_usd DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    UNIQUE (tenant_id, period_month)
);
```

**Controle:**
- Soft limit (80%): notifica tenant admin
- Hard limit (100%): bloqueia chamadas de IA, sistema core continua funcionando

---

## 9. Observabilidade

### 9.1 Log Correlation Setup

**Decisao:** `correlation_id` propagado via middleware, presente em todos os logs de IA.

**Fluxo:**
```
Request HTTP chega
  → Middleware gera correlation_id (UUID) ou le do header X-Correlation-ID
  → correlation_id armazenado no container (singleton per-request)
  → Toda chamada de IA inclui correlation_id no contexto de log
  → Tools executadas herdam o mesmo correlation_id
  → Audit logs registram correlation_id
  → Resposta HTTP retorna header X-Correlation-ID
```

**Onde aparece:**

| Camada | Campo |
|--------|-------|
| Log HTTP | `correlation_id` |
| Log IA (provider call) | `correlation_id` + `ai_request_id` |
| Log Tool execution | `correlation_id` + `tool_name` |
| Audit log | `correlation_id` |
| Session (Redis) | `last_correlation_id` |

**Implementacao:** `CorrelationIdMiddleware` no grupo global. Classe `CorrelationContext` (singleton) com `getId()`.

### 9.2 Alert Rules Implementation

**Decisao:** Regras simples em config, job scheduled (5 min), notificacao via Laravel Notification.

```php
// config/ai.php
'alerts' => [
    'provider_error_rate' => [
        'threshold' => 0.10,        // 10% de erro em 5 min
        'window_seconds' => 300,
        'severity' => 'critical',
    ],
    'latency_p95' => [
        'threshold_ms' => 5000,     // P95 > 5s
        'window_seconds' => 300,
        'severity' => 'warning',
    ],
    'cost_daily_soft' => [
        'threshold_usd' => 50.00,
        'severity' => 'warning',
    ],
    'cost_daily_hard' => [
        'threshold_usd' => 100.00,
        'severity' => 'critical',
        'action' => 'disable_ai',
    ],
    'circuit_breaker_open' => [
        'severity' => 'warning',
    ],
],
```

**Verificacao:**
- Por request: CostDecorator verifica limites de custo
- Por schedule: `CheckAiHealthMetricsJob` (5 min) calcula error rate e latencia
- Notificacao: Laravel Notification para platform_admin (email + log)

### 9.3 Dashboard de IA

**Decisao:** Nao implementar dashboard. Expor dados via API endpoints.

| Endpoint | Dados |
|----------|-------|
| `GET /platform/ai/metrics/usage` | Requests, tokens, custo por tenant/periodo |
| `GET /platform/ai/metrics/providers` | Health, latencia, error rate por provider |
| `GET /platform/ai/metrics/cost` | Custo agregado diario/mensal, projecao |

Dados de `ai_usage_summaries` + Redis counters. Frontend consome quando existir.

---

## 10. Seguranca

### 10.1 AI-specific Middleware

**Decisao:** Middleware `EnsureAiEnabled` com 5 verificacoes sequenciais.

```
Request para /tenant/ai/*
  → 1. AI_ENABLED = true? (master switch global)
  → 2. Tenant tem feature flag ai_enabled = true?
  → 3. Plano do tenant inclui IA?
  → 4. Tenant nao atingiu hard limit de custo?
  → 5. Rate limit do tenant/usuario nao excedido?
  → Tudo OK → prossegue
```

**Respostas de bloqueio:**

| Verificacao falhou | HTTP Status | Codigo |
|-------------------|-------------|--------|
| AI desabilitada (global) | 503 | `ai_unavailable` |
| AI desabilitada (tenant) | 403 | `ai_disabled_for_tenant` |
| Plano nao inclui IA | 403 | `ai_not_in_plan` |
| Hard limit atingido | 429 | `ai_cost_limit_reached` |
| Rate limit excedido | 429 | `ai_rate_limit_exceeded` |

### 10.2 Output Validation Service

**Decisao:** `AiOutputValidatorInterface` que valida toda resposta do modelo.

| Regra | Acao |
|-------|------|
| Tamanho maximo | Truncar em 2000 tokens + aviso |
| Formato esperado | tool_call → validar JSON schema. texto → validar UTF-8 |
| Conteudo proibido | Regex para PII na resposta (CPF, telefone, email de outros) |
| Alucinacao de dados | IDs de recursos mencionados devem existir no tenant |
| Referencia cross-tenant | tenant_id diferente do atual → bloquear + alerta |
| Encoding malicioso | Sanitizar HTML/scripts |

**Resultado:** `OutputValidation` com `isValid`, `sanitizedContent`, `warnings[]`, `blocked`.

### 10.3 Prompt Injection Test Suite

**Decisao:** Fixture JSON com ~30 payloads + testes Pest por categoria.

**Fixture:** `tests/fixtures/prompts/injection-payloads.json`

**Categorias de ataque:**
- `role_override` — tentativas de mudar o comportamento do modelo
- `system_leak` — tentativas de extrair o system prompt
- `delimiter` — injecao de delimitadores de prompt
- `encoding` — evasao via Base64, Unicode
- `context_manipulation` — tentativas de alterar permissoes
- `data_exfil` — tentativas de acessar dados de outros tenants
- `sql_injection` — payloads SQL nos inputs

Cada categoria com pelo menos 3 variacoes. Expandivel.

### 10.4 Data Classification Service

**Decisao:** Classificacao estatica por source_type em config, 4 niveis.

```php
// config/ai.php
'data_classification' => [
    'operational' => [            // Pode ir para qualquer provider
        'reservation_history', 'space_availability', 'occupancy_metrics',
    ],
    'institutional' => [          // Pode ir para provider, sem PII
        'regulation', 'assembly_minutes', 'condominium_policies',
    ],
    'personal' => [               // NUNCA enviar ao provider cloud
        'user_profile', 'guest_information', 'penalty_details',
    ],
    'sensitive' => [              // NUNCA sair do sistema
        'financial_data', 'biometric_data', 'credentials',
    ],
],
```

**Fluxo:**
```
Antes de incluir dados no prompt:
  → Verificar classificacao do source_type
  → Se 'personal' ou 'sensitive' → nao incluir no prompt para provider cloud
  → Se provider e local (Ollama) → dados operacionais e institucionais permitidos
  → PII Scrubbing (regex) roda ANTES como camada adicional
```

Duas camadas (classificacao estatica + scrubbing) garantem protecao em profundidade.

---

## 11. Testes

### 11.1 AI Test Strategy

**Decisao:** Piramide de testes com fakes obrigatorios.

| Camada | O que testar | Mocks/Fakes | Quantidade |
|--------|-------------|-------------|------------|
| Unit (Domain) | Value objects, enums, regras puras | Nenhum | ~60% |
| Unit (Application) | Use cases, PromptBuilder, InjectionDetector, OutputValidator | Fake providers, fake Redis | ~25% |
| Integration | Repositories, Redis session, migrations | SQLite, Redis real (test) | ~10% |
| Feature (HTTP) | Endpoints, middleware, fluxo request→response | Fake providers | ~5% |

**Regra:** Testes nunca chamam providers reais. Toda interacao com IA e via fakes.

### 11.2 RAG Quality Test Suite

**Decisao:** Testes de contrato e pipeline, nao testes de qualidade ML.

| Teste | Tipo | Validacao |
|-------|------|-----------|
| Ingestion pipeline | Integration | Documento → chunks → embeddings → persistencia |
| Deduplication | Unit | Mesmo content_hash nao gera duplicata |
| Tenant isolation | Integration | Query tenant A nao retorna embeddings tenant B |
| Similarity threshold | Unit | Resultados < 0.75 filtrados |
| Hybrid search merge | Unit | RRF combina vector + keyword corretamente |
| Re-ranking | Unit | Score aplica 85% relevancia + 15% recencia |
| PII scrubbing | Unit | CPF, telefone, email removidos |
| Expired embeddings | Integration | Embeddings expirados nao retornam |

Sem precision/recall no MVP — requer dataset anotado.

### 11.3 Prompt Regression Tests

**Decisao:** Testes de estrutura e invariantes, nao de qualidade de resposta.

| Teste | Validacao |
|-------|-----------|
| Template integrity | Todos os templates carregam sem erro |
| Variable resolution | Nenhuma {variavel} nao resolvida |
| Guardrails present | System prompt contem guardrails obrigatorios |
| Token budget | Prompt < 5200 tokens |
| Tool schema valid | Tools no prompt sao JSON Schema valido |
| Injection resistance | Payloads maliciosos nao corrompem estrutura |
| Version consistency | v1 e v2 mantem mesmas variaveis obrigatorias |

### 11.4 Tenant Isolation Tests (IA)

**Decisao:** Testes dedicados por camada, obrigatorios para cada feature nova.

| Teste | Camada | Validacao |
|-------|--------|-----------|
| RAG query isolation | Integration | Embeddings tenant A invisiveis para B |
| Memory isolation | Integration | Memorias user X no tenant A invisiveis no B |
| Session isolation | Integration | Sessao Redis tenant A inacessivel por B |
| Tool execution isolation | Feature | Tool no contexto A nao afeta B |
| Cost tracking isolation | Integration | Custo de A nao contabilizado em B |
| Output validation | Unit | Output nao referencia tenant_id diferente |

**Trait:** `SetsUpMultiTenantAi` — cria 2 tenants com bancos separados e dados distintos.

**Regra:** Qualquer nova feature de IA deve ter teste de isolamento correspondente.

### 11.5 End-to-End Conversation Tests

**Decisao:** 6 fluxos completos via HTTP com fake providers.

| Fluxo | Steps |
|-------|-------|
| Reserva simples | Msg → intent → tool_proposed → confirm → executed → response |
| Consulta RAG | Msg → intent → RAG retrieval → response com citacao |
| Acao rejeitada | Msg → tool_proposed → user rejects → cancelled → response |
| Confirmacao expirada | Msg → tool_proposed → 5min timeout → expired → retry prompt |
| Fallback | Msg incompreensivel → no intent → fallback response |
| Multi-turn | Msg1 → Msg2(mais info) → Msg3(confirma) → executed |

Todos E2E usam fake providers — deterministicos, sem custo, reproduziveis.

---

## 12. Resumo de Decisoes por Prioridade

### Implementar Primeiro (Alta Prioridade)

1. Function Calling nativo para intent detection
2. Conversation State Machine (6 estados)
3. Orchestrator State Machine (6 estados de acao)
4. Confirmation Token Flow (nonce Redis, 5 min TTL)
5. ToolRegistry Service + Tool Execution Pipeline (7 etapas)
6. IndexDocumentEmbeddingsJob (queue, idempotente, batch)
7. PromptBuilder Service (8 etapas, token budget)
8. Session Service (Redis, TTL, summarization)
9. Memory Retrieval (top 5, confianca >= 0.3)
10. AI Middleware (EnsureAiEnabled, 5 checks)
11. Output Validation Service
12. Log Correlation (correlation_id global)
13. Prism Integration (HTTP layer, decorators)

### Implementar Depois (Media Prioridade)

14. PII Scrubbing Pipeline (regex patterns)
15. Hybrid Search + Re-ranking (RRF 0.7/0.3)
16. Memory Detection Logic (prompt-based)
17. Prompt Injection Detection (regex + heuristicas)
18. Document Change Detection (event-driven)
19. Alert Rules (config + scheduled job)
20. Batch Processing (jobs dedicados)
21. Cost Attribution to Plans (limites por plano)
22. Decay Job + Memory Consent Flow
23. Data Classification (estatica por source_type)
24. Tenant Isolation Tests

### Pode Esperar (Baixa Prioridade / Nao Implementar MVP)

25. Conversation Analytics — documentar apenas
26. Streaming Responses — SSE futuro
27. Prompt A/B Testing — volume insuficiente
28. RAG Quality Evaluation — requer dataset anotado
29. Tool Usage Analytics — audit log ja captura
30. Dashboard de IA — expor via API apenas

---

## 13. Decisoes de Evolucao (MVP → Futuro)

| Area | MVP | Evolucao |
|------|-----|----------|
| Intent Detection | Function Calling | Hibrido (FC + classification fallback) |
| PII Scrubbing | Regex patterns | Regex + NER |
| Prompt Injection | Regex + heuristicas | Modelo classificador dedicado |
| Summarization | LLaMA 8B local | Token-based trigger |
| Session Recovery | Sem recovery | Redis RDB / snapshot |
| RAG Evaluation | Sem avaliacao | precision@K, recall@K, MRR |
| Streaming | Sem streaming | SSE via endpoint dedicado |

---

## 14. Documentacao Relacionada

| Documento | Relevancia |
|-----------|-----------|
| [uncovered-areas.md](uncovered-areas.md) | Mapeamento original dos gaps |
| [ai-provider-strategy.md](ai-provider-strategy.md) | Providers, fallback, Prism, custos |
| [rag-memory-strategy.md](rag-memory-strategy.md) | RAG, memoria, contexto de sessao |
| [session-management.md](session-management.md) | Ciclo de vida de sessoes |
| [prompt-registry.md](prompt-registry.md) | Templates, guardrails, versionamento |
| [tooling-catalog.md](tooling-catalog.md) | Catalogo de 28 tools |
| [tooling-security-guidelines.md](tooling-security-guidelines.md) | Seguranca de IA |
