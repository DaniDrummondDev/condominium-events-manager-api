# AI Overview — Condominium Events Manager API

## 1. Visao Geral

A IA neste sistema atua como **camada de interface e orquestracao**, nunca como executor autonomo de regras de negocio.

Tres conceitos fundamentais definem o papel da IA:

| Conceito | Funcao | Escopo temporal |
|----------|--------|-----------------|
| **RAG** | Recupera fatos, historicos e documentos | Explica o passado |
| **Memoria** | Armazena preferencias e padroes recorrentes | Orienta o futuro |
| **Tools** | Executa acoes de negocio com confirmacao humana | Atua no presente |

A IA nunca decide sozinha. Toda acao critica exige confirmacao humana explicita.

---

## 2. Principios Arquiteturais

### 2.1 IA como Proposta, Nunca como Decisao

A IA propoe acoes via Tool Registry. O backend valida permissoes, regras de negocio e isolamento. O usuario confirma. O Use Case executa.

```
Usuario → IA interpreta → Tool proposta → Confirmacao humana → Use Case → Auditoria
```

### 2.2 Isolamento Total por Tenant

- Embeddings isolados por `tenant_id` (pgvector)
- Memorias escopadas por tenant e usuario
- Logs, metricas e acoes isolados
- Nenhum dado de um tenant influencia outro
- Violacao de isolamento e falha critica

### 2.3 Desacoplamento de Providers

A IA funciona via contratos (interfaces). O provider (OpenAI, Azure, local) e um detalhe de infraestrutura. O sistema deve funcionar normalmente sem IA — degradacao graciosa.

### 2.4 LGPD como Requisito Estrutural

- Dados pessoais anonimizados antes de envio a providers externos
- Base legal obrigatoria para todo uso de dados
- Direito ao esquecimento implementado (delete embeddings, anonymize logs)
- Consentimento explicito para comunicacoes com terceiros

### 2.5 Auditoria Completa

Toda interacao com IA e rastreavel: quem pediu, o que a IA propôs, se foi confirmado, o que foi executado, qual o resultado.

---

## 3. Componentes da Arquitetura de IA

```
┌─────────────────────────────────────────────────────┐
│                   Interface Layer                     │
│  ChatController  │  AIActionController               │
├─────────────────────────────────────────────────────┤
│                  Application Layer                    │
│  ConversationalAssistant                             │
│  ActionOrchestrator                                  │
│  ToolRegistry ──→ Tools (mapeiam para Use Cases)     │
│  PromptBuilder                                       │
│  EmbeddingService (RAG)                              │
│  MemoryService                                       │
├─────────────────────────────────────────────────────┤
│                  Domain Layer                         │
│  Use Cases existentes (Reservations, Governance...)  │
│  Regras de negocio intactas                          │
├─────────────────────────────────────────────────────┤
│                Infrastructure Layer                   │
│  AIProvider (OpenAI/Azure/Local)                     │
│  pgvector (Embeddings)                               │
│  Redis (Cache de sessao)                             │
│  ai_usage_logs, ai_action_logs, ai_memory            │
└─────────────────────────────────────────────────────┘
```

---

## 4. Bounded Contexts Atendidos

A IA interage com os seguintes contextos de dominio:

| Bounded Context | Tipo de Interacao | Exemplos |
|-----------------|-------------------|----------|
| **Reservas** | Tools transacionais + consulta | Criar, cancelar, verificar disponibilidade |
| **Espacos Comuns** | Consulta + sugestao | Listar espacos, sugerir horarios |
| **Governanca** | Consulta + RAG | Consultar regras, simular penalidades |
| **Controle de Pessoas** | Consulta + registro | Registrar convidados, sugerir prestadores |
| **Comunicacao** | Sugestao + rascunho | Rascunhar avisos, sugerir respostas |
| **Unidades/Moradores** | Contexto | Informacoes do usuario e unidade |

---

## 5. Acesso por Role

| Role | Chat | Tools Read | Tools Transacionais | Escopo |
|------|:----:|:----------:|:-------------------:|--------|
| **sindico** | Sim | Todas | Todas | Condominio inteiro |
| **administradora** | Sim | Todas | Todas | Condominio inteiro |
| **condomino** | Sim | Proprias | Proprias | Unidade e reservas proprias |
| **funcionario** | Nao | Nao | Nao | Sem acesso a IA |

---

## 6. Feature Flags

| Flag | Descricao | Impacto |
|------|-----------|---------|
| `ai_assistant` | Habilita chat e tools basicas | Master switch |
| `ai_vendor_integration` | Habilita tools de fornecedores | suggest_vendors, request_quote |
| `ai_advanced_features` | Habilita features premium de IA | Memorias, RAG avancado |

---

## 7. Documentos Complementares

| Documento | Escopo |
|-----------|--------|
| [tooling-catalog.md](tooling-catalog.md) | Catalogo completo de tools com schema formal |
| [tooling-security-guidelines.md](tooling-security-guidelines.md) | Regras de seguranca para tooling |
| [rag-memory-strategy.md](rag-memory-strategy.md) | Estrategia de RAG, memoria e retrieval |
| [prompt-registry.md](prompt-registry.md) | Templates de prompts e guardrails |
| [ai-provider-strategy.md](ai-provider-strategy.md) | Abstracao de providers, fallback, custos |
| [session-management.md](session-management.md) | Gestao de sessao conversacional, tempos, context window |
| [uncovered-areas.md](uncovered-areas.md) | Areas nao cobertas para desenvolvimento futuro |

---

## 8. Relacao com Skills Existentes

Esta documentacao em `docs/ai/` consolida e expande o conteudo das skills da Fase 05:

| Skill (fonte da verdade) | Doc consolidado |
|--------------------------|-----------------|
| `05-ai/ai-integration.md` | ai-overview.md + tooling-catalog.md |
| `05-ai/ai-action-orchestration.md` | tooling-catalog.md + tooling-security-guidelines.md |
| `05-ai/embedding-strategy.md` | rag-memory-strategy.md |
| `05-ai/ai-data-governance.md` | tooling-security-guidelines.md + rag-memory-strategy.md |
| `05-ai/ai-observability.md` | tooling-security-guidelines.md (auditoria) |

Em caso de conflito, **as skills sao a fonte da verdade**. Estes documentos expandem e detalham.
