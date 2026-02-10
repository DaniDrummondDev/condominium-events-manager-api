# ai-observability.md — Observabilidade de IA

## 1. Objetivo

Definir a estratégia de **observabilidade, rastreabilidade e controle operacional** de todos os componentes de IA no **Condominium Events Manager API**, garantindo:

- Transparência total das ações propostas por IA
- Auditoria completa e imutável
- Detecção precoce de falhas, desvios e abusos
- Isolamento absoluto por tenant
- Conformidade com LGPD, segurança e governança
- Capacidade de explicar *o que*, *quando*, *por quem* e *com base em quê* uma sugestão de IA foi gerada

Observabilidade de IA é **obrigatória** e **não opcional**.

---

## 2. Princípios Não Negociáveis

- IA **nunca** atua sem rastreamento
- Nenhuma ação de IA é invisível
- Toda sugestão é explicável
- Logs de IA são **imutáveis**
- Não existe “black box” operacional
- Observabilidade é independente do provider de IA

---

## 3. Escopo da Observabilidade

A observabilidade cobre **todo o ciclo de vida da interação com IA**:

1. Entrada (input)
2. Processamento
3. Recuperação de contexto (RAG / embeddings)
4. Resposta gerada
5. Proposta de ação
6. Confirmação ou rejeição humana
7. Execução (fora da IA)
8. Resultado final

---

## 4. Componentes Observáveis

### 4.1 Componentes Monitorados

- AI Orchestrator
- Embedding Service
- Vector Store (pgvector)
- AI Providers (via adapters)
- Use Cases acionados por sugestões
- Confirmações humanas
- Workers assíncronos relacionados à IA

---

## 5. Tipos de Observabilidade

### 5.1 Logs Estruturados

Todos os logs de IA devem ser **estruturados (JSON)**.

Eventos mínimos:

- `ai.request.received`
- `ai.context.retrieved`
- `ai.response.generated`
- `ai.action.proposed`
- `ai.action.confirmed`
- `ai.action.rejected`
- `ai.action.executed`
- `ai.error`

---

### 5.2 Métricas

Métricas obrigatórias por **tenant**:

- Total de requisições de IA
- Latência média por provider
- Latência de busca vetorial
- Taxa de erro
- Taxa de rejeição humana
- Volume de embeddings consultados
- Custo estimado por tenant (quando aplicável)

---

### 5.3 Tracing Distribuído

Cada interação de IA deve possuir:

- `trace_id`
- `correlation_id`
- `tenant_id`
- `user_id` (quando aplicável)

Toda cadeia deve ser rastreável do input à execução final.

---

## 6. Modelo de Evento de Auditoria de IA

### 6.1 Estrutura Base

```json
{
  "event_id": "uuid",
  "event_type": "ai.action.proposed",
  "tenant_id": "uuid",
  "user_id": "uuid | null",
  "trace_id": "uuid",
  "ai_model": "provider:model@version",
  "input_hash": "sha256",
  "context_sources": [
    {
      "type": "embedding",
      "source_type": "regulamento",
      "source_id": "uuid",
      "model_version": "v1"
    }
  ],
  "confidence_score": 0.82,
  "timestamp": "ISO-8601"
}
```
---

## 7. Explicabilidade (Explainability)

### 7.1 Requisitos

Toda resposta de IA deve permitir responder:

* Quais dados influenciaram a resposta?
* Quais embeddings foram consultados?
* Qual modelo foi usado?
* Qual versão do prompt/orquestração?
* A ação foi confirmada ou rejeitada?

### 7.2 Regra

> Se não for possível explicar, a resposta não pode ser usada.

---

## 8. Observabilidade de Embeddings

Integração direta com embedding-strategy.md.

**Métricas específicas:**

* Total de embeddings por tenant
* Embeddings ativos vs obsoletos
* Latência de consulta vetorial
* Taxa de acerto semântico (quando mensurável)
* Versões de modelo em uso

---

## 9. Segurança e Privacidade

### 9.1 Proteções Obrigatórias

* Inputs sensíveis devem ser hashados
* Nunca logar texto bruto sensível
* Logs segregados por tenant
* Criptografia em repouso
* Controle de acesso aos logs

### 9.2 LGPD

* Logs de IA seguem data-retention-policy.md
* Direito ao esquecimento respeitado
* Logs não podem ser usados para reidentificação

---

## 10.  Alertas e Detecção de Anomalias

### 10.1 Alertas Obrigatórios

* Pico anormal de uso de IA
* Latência acima do SLA
* Erros consecutivos de provider
* Ações propostas sem confirmação humana
* Tentativa de bypass de confirmação

### 10.2 Anomalias

* Mudança súbita de padrão de respostas
* Queda brusca de confiança média
* Uso de embeddings fora do padrão esperado

---

## 11. Anti-Padrões

❌ Logs de IA não estruturados
❌ IA sem trace_id
❌ Falta de tenant_id nos eventos
❌ Logs contendo dados sensíveis em texto puro
❌ Ações executadas sem evento de confirmação
❌ Dependência de logs do provider externo

---

## 12. Checklist de Conformidade

 * [ ] Todos os eventos de IA são logados
 * [ ] Logs estruturados e imutáveis
 * [ ] Métricas por tenant disponíveis
 * [ ] Tracing distribuído ativo
 * [ ] Explicabilidade garantida
 * [ ] Alertas configurados
 * [ ] Compliance LGPD atendido
 * [ ] Integração com embeddings validada

---

## 13. Status

Obrigatório para qualquer funcionalidade baseada em IA.

Sem observabilidade completa, IA é considerada insegura e não pode ser ativada em produção.