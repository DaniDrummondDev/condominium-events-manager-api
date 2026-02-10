# Event-Driven Architecture — Arquitetura Orientada a Eventos
## FASE 6 — Operação & Confiabilidade  
**Condominium Events Manager API**

---

## 1. Objetivo

Definir a arquitetura oficial de **eventos de domínio, integração e sistema**, garantindo:

- Desacoplamento entre módulos
- Escalabilidade e extensibilidade
- Confiabilidade operacional
- Isolamento absoluto por tenant
- Observabilidade e auditoria completas
- Compatibilidade com DDD, Clean Architecture e arquitetura SaaS

Eventos **representam fatos que já aconteceram**.  
Eventos **não executam lógica de negócio**.

---

## 2. Princípios Não Negociáveis

- Eventos são imutáveis
- Eventos representam fatos passados
- Eventos não contêm lógica
- Eventos não substituem Use Cases
- Publicar evento nunca é obrigatório para sucesso da operação
- Falha ao consumir evento não invalida o fato ocorrido
- Todo evento pertence a um tenant
- Todo evento é auditável

---

## 3. Tipos de Eventos

### 3.1 Eventos de Domínio

Representam mudanças significativas no domínio.

**Exemplos:**
- TenantCreated
- ReservationConfirmed
- SubscriptionCanceled
- InvoiceIssued
- AIActionConfirmed

**Regras:**
- Emitidos dentro do domínio
- Nomeados no passado
- Não dependem de infraestrutura

---

### 3.2 Eventos de Integração

Representam comunicação entre contextos ou sistemas externos.

**Exemplos:**
- BillingPaymentConfirmed
- ExternalWebhookReceived
- WhatsAppMessageDelivered

**Regras:**
- Sempre via adapters
- Podem falhar sem quebrar o domínio
- Nunca alteram estado diretamente

---

### 3.3 Eventos Técnicos / de Sistema

Eventos operacionais e internos.

**Exemplos:**
- JobFailed
- EmbeddingGenerated
- ProviderTimeout
- CircuitBreakerOpened

---

## 4. Posicionamento na Arquitetura

### Camadas

**Domain Layer**
- Define eventos de domínio

**Application Layer**
- Publica eventos
- Reage a eventos via handlers
- Orquestra reações

**Infrastructure Layer**
- Implementa event bus
- Implementa filas, streams ou brokers

**Presentation Layer**
- Nunca publica eventos diretamente

### Fluxo Padrão

Controller → Use Case → Evento → Handler → Job (opcional)


---

## 5. Event Bus

Características obrigatórias:

- Assíncrono
- Não bloqueante
- Suporte a retry
- Suporte a DLQ
- Observável
- Isolado por tenant (lógico ou físico)

> A tecnologia utilizada é um detalhe de infraestrutura.

---

## 6. Event Handlers

**Regras:**

- Um handler = uma responsabilidade
- Handlers não contêm lógica de domínio
- Handlers podem:
  - Disparar jobs
  - Chamar use cases
  - Integrar sistemas externos via adapters
- Handlers devem ser idempotentes

---

## 7. Ordenamento e Consistência

- Eventos não garantem ordenação global
- Se ordenação for crítica:
  - Usar versionamento
  - Usar agregados
- Consistência eventual é o padrão
- Nunca assumir execução imediata

---

## 8. Isolamento por Tenant

Todo evento **DEVE** conter:

- tenant_id
- event_id
- occurred_at
- correlation_id
- trace_id

**Regra absoluta:**  
Eventos nunca atravessam tenants.

---

## 9. Observabilidade de Eventos

Integração direta com:

- audit-logging
- ai-observability
- job-architecture

### Eventos observáveis mínimos

- event.published
- event.consumed
- event.failed
- event.retried
- event.sent_to_dlq

### Métricas

- Eventos por tipo
- Eventos por tenant
- Latência de consumo
- Taxa de falha
- Tempo em fila

---

## 10. Segurança

- Payloads sempre validados
- Nenhum dado sensível desnecessário
- PII minimizada
- Criptografia em trânsito
- Controle de acesso aos consumidores

> Eventos **não são** API pública.

---

## 11. Retry e Dead Letter Queue (DLQ)

- Retry com backoff exponencial
- Limite máximo por evento
- Eventos problemáticos enviados para DLQ
- Reprocessamento somente manual
- Toda falha é auditada

---

## 12. Eventos e IA (Integração com Fase 5)

**Regras específicas:**

- IA pode reagir a eventos
- IA pode gerar sugestões
- IA nunca executa ações
- Eventos de IA sempre exigem confirmação humana antes da execução
- Observabilidade obrigatória

---

## 13. Anti-Padrões

❌ Usar eventos como comandos  
❌ Lógica de negócio em handlers  
❌ Eventos síncronos bloqueantes  
❌ Eventos sem tenant_id  
❌ Eventos mutáveis  
❌ Eventos sem auditoria  
❌ Dependência direta de broker externo no domínio  

---

## 14. Checklist de Conformidade

- [ ] Eventos representam fatos
- [ ] Eventos são imutáveis
- [ ] Isolamento por tenant garantido
- [ ] Handlers idempotentes
- [ ] Retry configurado
- [ ] DLQ ativa
- [ ] Observabilidade ativa
- [ ] Segurança aplicada

---

## 15. Status

Documento **OBRIGATÓRIO** para qualquer fluxo assíncrono baseado em eventos.

Qualquer violação invalida o uso de **Event-Driven Architecture** no sistema.
