# Areas Nao Cobertas — Condominium Events Manager API (IA)

## Objetivo

Mapear areas da implementacao de IA que **ainda nao estao detalhadas** na documentacao existente (skills + docs/ai/), para servir como guia de desenvolvimento e priorizacao.

Este documento cruza as 5 skills da fase 05-ai com os 7 documentos em `docs/ai/` e identifica gaps.

> **Decisoes tomadas:** Todas as ~40 areas listadas neste documento foram analisadas e tiveram decisoes tecnicas definidas. Ver **[ai-uncovered-decisions.md](ai-uncovered-decisions.md)** para o documento consolidado de decisoes.

---

## Legenda

| Status | Significado |
|--------|-------------|
| **Coberto** | Documentado em skills + docs/ai, pronto para implementacao |
| **Parcial** | Mencionado mas sem detalhe suficiente para implementar |
| **Nao coberto** | Nao documentado, precisa de definicao antes de implementar |

---

## 1. Conversational Assistant

### O que esta coberto

- Arquitetura geral (ai-integration skill + ai-overview)
- Papel do assistente: interface conversacional, nao executor
- Fluxo de conversa → intencao → proposta → confirmacao → execucao
- Prompts base e por caso de uso (prompt-registry)
- Guardrails de seguranca (prompt-registry, tooling-security-guidelines)

### O que NAO esta coberto

| Area | Descricao | Prioridade | Complexidade |
|------|-----------|-----------|--------------|
| **Intent Detection Engine** | Como a IA classifica a intencao do usuario (classification vs function calling vs prompt-based). Qual abordagem tecnica? | Alta | Media |
| **Multi-turn Conversation State Machine** | Estados da conversa (greeting, information_gathering, tool_proposal, awaiting_confirmation, completed). Transicoes validas. | Alta | Alta |
| **Conversation History Summarization** | Algoritmo de sumarizacao progressiva quando a conversa excede o context window. Quando sumarizar? Que informacoes preservar? | Media | Media |
| **Fallback Responses Catalog** | Catalogo de respostas para cenarios nao mapeados (IA nao entende, fora do escopo, erro tecnico). Mensagens padrao por idioma. | Media | Baixa |
| **Conversation Analytics** | Metricas de qualidade de conversa: taxa de resolucao, satisfacao, numero medio de turnos, intencoes mais frequentes. | Baixa | Media |

---

## 2. Action Orchestrator

### O que esta coberto

- Padrao de proposta de acao (ai-action-orchestration skill + tooling-catalog)
- Confirmacao humana obrigatoria (tooling-security-guidelines)
- Auditoria de acoes (ai-observability skill)
- Segregacao por agentes (tooling-security-guidelines secao 9)

### O que NAO esta coberto

| Area | Descricao | Prioridade | Complexidade |
|------|-----------|-----------|--------------|
| **Orchestrator State Machine** | Estados de uma acao: proposed → confirmed → executing → completed/failed. Timeouts entre estados. | Alta | Media |
| **Confirmation Token Flow** | Implementacao tecnica do nonce de confirmacao (geracao, storage Redis, TTL 5min, validacao). | Alta | Baixa |
| **Multi-step Actions** | Acoes que requerem multiplas confirmacoes ou etapas (ex: reserva com convidados + fornecedor). Workflow engine? | Media | Alta |
| **Action Rollback Strategy** | O que acontece quando uma acao confirmada falha parcialmente? Compensating actions? | Media | Alta |
| **Concurrent Action Handling** | Como tratar 2 propostas de acao simultaneas do mesmo usuario. Fila? Rejeicao da segunda? | Baixa | Media |

---

## 3. Tool Registry

### O que esta coberto

- Catalogo completo de 28 tools com schema formal (tooling-catalog)
- Categorias: Contexto, Consulta, Validacao, Transacional, Comunicacao, Suporte, Admin
- Rate limits por tool
- Feature flags por tool
- Versionamento de tools

### O que NAO esta coberto

| Area | Descricao | Prioridade | Complexidade |
|------|-----------|-----------|--------------|
| **ToolRegistry Service Implementation** | Classe que carrega tools do catalogo, filtra por role + feature flags, serializa para o modelo. | Alta | Media |
| **Tool Schema Validation** | Validacao automatica dos parametros da tool contra o schema definido (como JSON Schema). | Alta | Baixa |
| **Tool Execution Pipeline** | Pipeline: parse proposta → validate schema → check permissions → execute use case → format response. | Alta | Media |
| **Tool Discovery for AI** | Formato em que as tools sao apresentadas ao modelo (function calling schema, texto descritivo). | Media | Baixa |
| **Tool Usage Analytics** | Metricas: tools mais usadas, taxa de sucesso por tool, tempo medio de execucao. | Baixa | Baixa |

---

## 4. RAG (Retrieval-Augmented Generation)

### O que esta coberto

- Fontes permitidas e proibidas (rag-memory-strategy)
- Pipeline de ingestao completo
- Chunking strategy por tipo de documento
- **Schema pgvector com dimensao configuravel** (suporta 768, 1024, 1536...)
- Retrieval: threshold 0.75, Top-K 5, busca hibrida
- Versionamento de embeddings (model_version)
- **Embedding Migration Strategy** (zero-downtime, re-indexacao batch) — ver ai-provider-strategy secao 8
- Seguranca e LGPD

### O que NAO esta coberto

| Area | Descricao | Prioridade | Complexidade |
|------|-----------|-----------|--------------|
| **Ingestion Job Implementation** | Job(s) que processam documentos novos/atualizados: `IndexDocumentEmbeddingsJob`. Queue, retry, idempotencia. | Alta | Media |
| **Document Change Detection** | Como detectar que um regulamento foi atualizado e precisa re-indexar. Event-driven? Polling? Webhook? | Alta | Baixa |
| **PII Scrubbing Pipeline** | Implementacao tecnica da remocao de PII antes de gerar embeddings. Regex? NER? Lista de patterns? | Alta | Media |
| **Hybrid Search Implementation** | Combinacao de vector similarity + keyword match (tsvector). Peso relativo de cada. | Media | Media |
| **Re-ranking Algorithm** | Implementacao do re-ranking por recencia + relevancia. Formula de score combinado. | Media | Media |
| **RAG Evaluation/Quality** | Como medir qualidade do RAG: precision@K, recall@K, MRR. Dataset de avaliacao. | Baixa | Alta |

---

## 5. Memoria da IA

### O que esta coberto

- Conceito e separacao de RAG (rag-memory-strategy)
- Schema ai_memory completo
- Tipos de memorias validas e proibidas
- Fluxo de escrita com validacao
- Decay e expiracao (90 dias, confianca minima 0.3)
- LGPD: opt-in, transparencia, edicao, exclusao

### O que NAO esta coberto

| Area | Descricao | Prioridade | Complexidade |
|------|-----------|-----------|--------------|
| **Memory Detection Logic** | Como a IA identifica um padrao ou preferencia durante a conversa para propor como memoria. Criterios. | Media | Alta |
| **Memory Deduplication** | Algoritmo para detectar que uma nova memoria e similar a uma existente e incrementar confianca em vez de duplicar. | Media | Media |
| **Memory Retrieval for Prompts** | Query que recupera memorias relevantes para a conversa atual. Por tipo? Por recencia? Por confianca? | Alta | Baixa |
| **Decay Job** | Job agendado que executa decay de confianca. Frequencia: diaria? semanal? | Media | Baixa |
| **User Memory Management API** | Endpoints para usuario listar, editar e excluir suas memorias. CRUD completo. | Media | Baixa |
| **Memory Consent Flow** | Tela/fluxo de opt-in para armazenamento de memorias. Quando pedir? No onboarding? Na primeira interacao com IA? | Media | Baixa |

---

## 6. Contexto de Sessao

### O que esta coberto

- Armazenamento em Redis com TTL 30min (rag-memory-strategy)
- Schema do JSON de sessao
- Context window management (curta, media, longa)
- Isolamento por tenant + usuario

### O que NAO esta coberto

| Area | Descricao | Prioridade | Complexidade |
|------|-----------|-----------|--------------|
| **Session Service Implementation** | Classe que gerencia leitura/escrita do contexto de sessao no Redis. | Alta | Baixa |
| **Summarization Trigger** | Logica que decide quando sumarizar mensagens antigas (>10 msgs? >X tokens?). | Media | Media |
| **Summarization Prompt** | Prompt especifico para sumarizar a conversa preservando informacoes criticas. | Media | Baixa |
| **Session Recovery** | O que acontece quando Redis falha? Perda de sessao? Fallback? Sessao em banco? | Baixa | Media |

---

## 7. Prompt Management

### O que esta coberto

- Estrutura de prompt: System → Context → Task → User Input (prompt-registry)
- System prompt base com guardrails
- 5 templates por caso de uso
- Versionamento e ciclo de vida
- Variaveis de template
- Prevencao de prompt injection

### O que NAO esta coberto

| Area | Descricao | Prioridade | Complexidade |
|------|-----------|-----------|--------------|
| **PromptBuilder Service** | Classe que monta o prompt final: carrega template, injeta variaveis, adiciona guardrails, sanitiza input. | Alta | Media |
| **Prompt Testing Framework** | Como testar prompts automaticamente: assertions de guardrails, resistencia a injection, qualidade de resposta. | Media | Alta |
| **Prompt A/B Testing** | Mecanismo para testar duas versoes de prompt simultaneamente e medir qual performa melhor. | Baixa | Alta |
| **Dynamic Prompt Selection** | Logica que seleciona o template correto baseado na intencao detectada. | Alta | Baixa |
| **Prompt Injection Detection Service** | Servico que analisa input do usuario antes de enviar ao modelo: regex patterns, scoring. | Media | Media |

---

## 8. Provider & Infrastructure

### O que esta coberto

- Abstracao de providers via interfaces (ai-provider-strategy)
- **Estrategia hibrida local + cloud** (roteamento por caso de uso)
- **Modelos locais** (LLaMA via Ollama) para tarefas simples, cloud para complexas
- **Embedding Migration Strategy** (zero-downtime, re-indexacao batch)
- **Schema de embeddings flexivel** (dimensao configuravel, nao hardcoded)
- Fallback chain com circuit breaker (local → cloud → degradacao)
- Controle de custos com tracking e limites
- Rate limiting em 3 camadas
- Health checks (incluindo Ollama)
- Integracao com Laravel Prism
- Docker Ollama service configuration

### O que NAO esta coberto

| Area | Descricao | Prioridade | Complexidade |
|------|-----------|-----------|--------------|
| **Prism Integration Details** | Configuracao especifica do Prism: como registrar providers, como fazer fallback via Prism vs decorators nossos. | Alta | Media |
| **Streaming Responses** | Se/como suportar streaming de respostas para UX em tempo real. SSE? WebSocket? Ou apenas polling? | Baixa | Alta |
| **Batch Processing** | Pipeline para processamento em lote (ex: re-indexar todos embeddings, gerar resumos em massa). | Media | Media |
| **Cost Attribution to Plans** | Como o custo de IA e atribuido ao plano do tenant e como afeta billing/limites. | Media | Media |

---

## 9. Observabilidade

### O que esta coberto

- Logs estruturados (ai-observability skill)
- Metricas obrigatorias por tenant
- Tracing distribuido
- Explicabilidade
- Alertas e deteccao de anomalias

### O que NAO esta coberto

| Area | Descricao | Prioridade | Complexidade |
|------|-----------|-----------|--------------|
| **Dashboard de IA** | Tela admin com metricas de uso, custo, qualidade, erros. Quais graficos? Quais filtros? | Baixa | Media |
| **Alert Rules Implementation** | Regras especificas: limites numericos, canais de notificacao, escalation. | Media | Baixa |
| **Log Correlation Setup** | Como conectar trace_id entre IA, tools, use cases e audit logs. Middleware? Interceptor? | Alta | Media |

---

## 10. Seguranca

### O que esta coberto

- Isolamento de tenant (tooling-security-guidelines)
- Autorizacao no backend
- Sanitizacao de input da IA
- Confirmacao humana
- Auditoria obrigatoria
- Protecao contra prompt injection
- LGPD compliance

### O que NAO esta coberto

| Area | Descricao | Prioridade | Complexidade |
|------|-----------|-----------|--------------|
| **AI-specific Middleware** | Middleware que intercepta requests de IA: verifica AI_ENABLED, feature flags, rate limits. | Alta | Baixa |
| **Output Validation Service** | Servico que valida output do modelo antes de retornar ao usuario: formato, tamanho, conteudo proibido. | Alta | Media |
| **Prompt Injection Test Suite** | Suite de testes com payloads conhecidos de prompt injection para validar resistencia. | Media | Media |
| **Data Classification Service** | Servico que classifica dados como operacionais/pessoais/sensiveis antes de enviar ao provider. | Media | Media |

---

## 11. Testes

### O que esta coberto

- Principios gerais de teste (skills + CLAUDE.md)
- Fake providers para testes (ai-provider-strategy)

### O que NAO esta coberto

| Area | Descricao | Prioridade | Complexidade |
|------|-----------|-----------|--------------|
| **AI Test Strategy Document** | Documento dedicado: o que testar em cada camada, mocks vs fakes, fixtures de prompts. | Alta | Baixa |
| **RAG Quality Test Suite** | Testes que validam qualidade do retrieval: dado um input, retorna chunks corretos. | Media | Alta |
| **Prompt Regression Tests** | Testes que detectam se mudanca em prompt degrada qualidade das respostas. | Media | Alta |
| **Tenant Isolation Tests (IA)** | Testes especificos que validam que IA do tenant A nunca acessa dados do tenant B. | Alta | Media |
| **End-to-End Conversation Tests** | Testes de fluxo completo: mensagem → intencao → proposta → confirmacao → execucao → resposta. | Media | Alta |

---

## 12. Resumo de Prioridades

### Alta Prioridade (implementar primeiro)

1. Intent Detection Engine — define como a conversa funciona
2. Orchestrator State Machine — estados da acao proposta
3. ToolRegistry Service — como tools sao carregadas e filtradas
4. Tool Execution Pipeline — parse → validate → execute
5. Ingestion Job — como documentos viram embeddings
6. PromptBuilder Service — montagem do prompt final
7. Session Service — gerencia contexto no Redis
8. Memory Retrieval — como memorias chegam ao prompt
9. Confirmation Token Flow — nonce de confirmacao
10. AI Middleware + Output Validation — seguranca na borda

### Media Prioridade (segunda fase)

11. PII Scrubbing Pipeline
12. Hybrid Search + Re-ranking
13. Memory Detection Logic
14. Prompt Injection Detection Service
15. Document Change Detection
16. Alert Rules + Log Correlation
17. Batch Processing
18. Cost Attribution to Plans
19. Decay Job + Memory Consent Flow
20. Data Classification Service

### Baixa Prioridade (pode esperar)

21. Conversation Analytics
22. Streaming Responses
23. Prompt A/B Testing
24. RAG Quality / Prompt Regression Tests
25. Dashboard de IA
26. Session Recovery
27. Concurrent Action Handling

---

## 13. Relacao com Roadmap Existente

| Fase do Roadmap | Areas Relacionadas deste Documento |
|-----------------|-----------------------------------|
| Fase 5 (05-ai/) | Todas as areas — esta e a fase principal de IA |
| Fase 8 (08-domain/) | Tools de reserva, governanca, comunicacao dependem do dominio |
| Fase 6 (06-operations/) | Jobs de ingestao, decay, eventos de IA |
| Fase 1 (01-security/) | Middleware de IA, output validation, prompt injection |
| Fase 2 (02-compliance/) | LGPD, PII scrubbing, data classification |
| Fase 7 (07-quality/) | Testes de IA, regression tests, isolation tests |

---

## 14. Documentacao Relacionada

| Documento | Relevancia |
|-----------|-----------|
| [ai-provider-strategy.md](ai-provider-strategy.md) | Estrategia hibrida local+cloud, embedding migration, Docker Ollama |
| [session-management.md](session-management.md) | Tempos configuraveis, context window, confirmacao pendente |
| [external-service-abstraction.md](../architecture/external-service-abstraction.md) | Abstracao completa para todos servicos externos (IA, pagamento, email, etc.) |
| [database-architecture.md](../architecture/database-architecture.md) | Schema ai_embeddings com dimensao configuravel |

---

## 15. Proximos Passos Sugeridos

1. Priorizar as 10 areas de **alta prioridade** como primeira sprint de implementacao de IA
2. Criar skills detalhadas para cada area nao coberta (ou expandir skills existentes)
3. Definir Decision Records para escolhas tecnicas pendentes (intent detection approach, streaming, batch)
4. Implementar Fake providers e test infrastructure antes das features reais
5. Validar com testes de isolamento cross-tenant antes de liberar para producao
6. **Configurar Ollama em Docker** quando volume justificar (> 10K requests/mes ou > $100/mes cloud)
