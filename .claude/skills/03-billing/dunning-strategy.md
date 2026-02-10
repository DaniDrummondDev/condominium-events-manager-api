# dunning-strategy.md

**Skill: Estratégia de Dunning (Gestão de Inadimplência)**
**Fase:** 3 — Billing & Assinaturas
**Domínio:** Billing
**Tipo:** Domain Process Skill

---

## Objetivo

Definir a arquitetura, regras e responsabilidades relacionadas à **gestão de inadimplência (dunning)** no SaaS, garantindo:

* Recuperação automática de pagamentos falhos
* Comunicação estruturada com o tenant
* Suspensão controlada de acesso
* Reativação automática após pagamento

O dunning é o processo responsável por **gerenciar faturas vencidas e falhas de pagamento**.

---

## Dependências

Esta skill depende das seguintes skills:

* `invoice-management.md`
* `subscription-lifecycle.md`
* `billing-subscription.md`
* `payment-gateway-integration.md`
* `tenant-lifecycle.md`
* `audit-logging.md`
* `job-architecture.md`

Nenhuma estratégia de dunning deve ignorar essas dependências.

---

## Princípios Arquiteturais

### 1. Dunning é um processo, não uma entidade

Dunning:

* Não é uma tabela principal
* Não é uma entidade de domínio isolada
* É um **fluxo baseado em estados da fatura e assinatura**

O dunning reage a:

* Faturas `past_due`
* Pagamentos `failed`

---

### 2. Dunning não pertence à Invoice

A entidade `Invoice`:

* Não deve conter lógica de dunning
* Não deve saber sobre retries
* Não deve saber sobre suspensão

Toda a lógica pertence a:

* Serviços de domínio de dunning
* Jobs agendados

---

### 3. Billing controla acesso por estado

O dunning nunca deve:

* Alterar permissões diretamente
* Alterar roles
* Alterar configurações do tenant

O que o dunning pode fazer:

* Alterar o **estado da assinatura**
* Solicitar suspensão do tenant

---

## Conceitos Fundamentais

### Estados relevantes

#### Invoice

* `open`
* `past_due`
* `paid`
* `uncollectible`

#### Subscription

* `active`
* `past_due`
* `suspended`
* `canceled`

---

## Fluxo de Dunning

### Etapa 1 — Falha de pagamento

Evento:

* Webhook indica `payment.failed`
  ou
* Fatura atinge `due_date` sem pagamento

Ações:

* Invoice → `past_due`
* Subscription → `past_due`
* Registrar evento de dunning

---

### Etapa 2 — Período de carência (grace period)

Durante o grace period:

* Tenant continua ativo
* Sistema tenta recuperar o pagamento
* Notificações são enviadas

Exemplo de estratégia:

| Dia | Ação                  |
| --- | --------------------- |
| 0   | Falha de pagamento    |
| 1   | 1ª notificação        |
| 3   | 1ª tentativa de retry |
| 5   | 2ª notificação        |
| 7   | 2ª tentativa de retry |
| 10  | Aviso de suspensão    |
| 14  | Suspensão do tenant   |

Os valores exatos devem ser configuráveis por plano ou política.

---

### Etapa 3 — Retentativas de pagamento

As retentativas devem:

* Ser feitas automaticamente
* Usar método de pagamento padrão
* Ser executadas por jobs assíncronos

Regras:

* Número máximo de retries definido por política
* Intervalos entre retries configuráveis
* Cada tentativa deve ser auditada

---

### Etapa 4 — Suspensão do tenant

Se:

* Grace period expira
* Pagamento não foi recuperado

Então:

* Subscription → `suspended`
* Tenant → `suspended`

Efeitos da suspensão:

* Acesso à API bloqueado
* Dados preservados
* Nenhuma exclusão automática

---

### Etapa 5 — Recuperação

Se o pagamento for confirmado:

* Invoice → `paid`
* Subscription → `active`
* Tenant → `active`

Reativação deve ser:

* Automática
* Sem intervenção manual

---

### Etapa 6 — Perda definitiva

Se após todos os retries:

* Pagamento não for recuperado
* Tempo máximo de cobrança for atingido

Então:

* Invoice → `uncollectible`
* Subscription pode ser:

  * `canceled`
  * ou mantida suspensa, conforme política

---

## Configuração de Estratégia de Dunning

A estratégia deve ser configurável por:

* Plano
* Tenant
* Política global

Campos conceituais:

```
dunning_policy
- id
- name
- grace_period_days
- retry_attempts
- retry_interval_days
- suspension_day
- cancellation_day
```

---

## Jobs de Dunning

O processo de dunning deve ser orientado por jobs.

### Jobs principais

1. `ProcessPastDueInvoicesJob`

   * Identifica invoices vencidas
   * Inicia processo de dunning

2. `RetryFailedPaymentsJob`

   * Executa retentativas de pagamento

3. `SuspendOverdueTenantsJob`

   * Suspende tenants fora do grace period

4. `ReactivateRecoveredTenantsJob`

   * Reativa tenants com pagamento confirmado

Todos os jobs devem:

* Ser idempotentes
* Ser auditados
* Suportar reprocessamento

---

## Auditoria Obrigatória

As seguintes ações devem ser auditadas:

* Entrada em estado `past_due`
* Cada tentativa de retry
* Notificações enviadas
* Suspensão de tenant
* Reativação
* Cancelamento por inadimplência

---

## Regras Não Negociáveis

1. Dunning nunca altera permissões diretamente.
2. Dunning só atua via estados de subscription e tenant.
3. Suspensão nunca apaga dados do tenant.
4. Reativação deve ser automática após pagamento.
5. Todas as ações de dunning devem ser auditadas.
6. Retentativas devem ser idempotentes.

---

## O que esta skill NÃO cobre

Esta skill não deve tratar:

* Estrutura da invoice
* Integração técnica com gateway
* Lógica interna de assinatura
* Envio de e-mails ou notificações específicas

Esses pontos pertencem às skills:

* `invoice-management.md`
* `payment-gateway-integration.md`
* `subscription-lifecycle.md`
* (futura skill de notification system)
