# invoice-management.md

**Skill: Gestão de Faturas (Invoice Management)**
**Fase:** 3 — Billing & Assinaturas
**Domínio:** Billing
**Tipo:** Core Domain Skill

---

## Objetivo

Definir a arquitetura, regras e responsabilidades relacionadas à **gestão de faturas** no SaaS, garantindo:

* Separação clara entre **assinatura, cobrança e pagamento**
* Estados consistentes de fatura
* Suporte a automação de billing
* Base para integração com gateway e estratégia de dunning

A fatura é a **unidade central de cobrança** do sistema.

---

## Dependências

Esta skill depende das seguintes skills:

* `tenant-lifecycle.md`
* `billing-subscription.md`
* `subscription-lifecycle.md`
* `billing-security.md`
* `audit-logging.md`
* `data-retention-policy.md`

Nenhuma implementação de invoice deve ignorar essas dependências.

---

## Princípios Arquiteturais

### 1. Fatura é um objeto financeiro imutável

Após emitida:

* Valores não devem ser alterados
* Itens não devem ser modificados
* Moeda e impostos não podem mudar

Qualquer ajuste deve ocorrer via:

* Nova fatura
* Crédito
* Estorno
* Nota de ajuste

---

### 2. Assinatura não é fatura

A assinatura representa:

* O contrato de cobrança
* O plano ativo
* O ciclo de faturamento

A fatura representa:

* Uma cobrança específica
* Um período específico
* Um valor específico

**Nunca misturar regras de assinatura dentro da entidade de fatura.**

---

### 3. Fatura não depende do gateway

A fatura:

* Existe independentemente do método de pagamento
* Pode ser manual, automática ou offline
* Não deve conter lógica específica de gateway

Integrações com gateway pertencem à skill:

`payment-gateway-integration.md`

---

## Responsabilidades da Invoice

A entidade **Invoice** deve ser responsável por:

* Representar uma cobrança formal
* Manter estado financeiro
* Registrar eventos de pagamento
* Fornecer base para dunning
* Servir como registro contábil

---

## Estrutura Conceitual da Invoice

### Entidade principal: Invoice

Campos conceituais:

* `id`
* `tenant_id`
* `subscription_id`
* `invoice_number` (único por tenant)
* `status`
* `currency`
* `subtotal`
* `tax_amount`
* `discount_amount`
* `total_amount`
* `issued_at`
* `due_date`
* `paid_at`
* `voided_at`
* `created_at`
* `updated_at`

---

### Itens da fatura: InvoiceItem

Cada fatura deve conter:

* Descrição
* Quantidade
* Valor unitário
* Valor total
* Referência ao plano, add-on ou ajuste

Campos conceituais:

* `id`
* `invoice_id`
* `type` (plan, add_on, adjustment, credit)
* `description`
* `quantity`
* `unit_price`
* `total_price`

---

## Estados da Fatura

A fatura deve possuir um **state machine explícito**.

### Estados permitidos

1. `draft`
2. `open`
3. `paid`
4. `past_due`
5. `void`
6. `uncollectible` (opcional, para casos de perda definitiva)

---

### Transições permitidas

| Estado atual | Evento           | Próximo estado |
| ------------ | ---------------- | -------------- |
| draft        | issue            | open           |
| open         | payment_received | paid           |
| open         | due_date_passed  | past_due       |
| past_due     | payment_received | paid           |
| open         | void             | void           |
| past_due     | write_off        | uncollectible  |

Transições inválidas devem:

* Gerar erro de domínio
* Ser registradas em audit log

---

## Regras de Geração de Fatura

### 1. Faturas são geradas por ciclo de assinatura

Eventos que geram fatura:

* Início de ciclo de cobrança
* Upgrade de plano
* Downgrade com ajuste
* Cobrança de add-on
* Reativação

---

### 2. Faturas devem ser idempotentes

Para um mesmo:

* `subscription_id`
* `billing_period_start`
* `billing_period_end`

Deve existir **apenas uma fatura**.

Tentativas duplicadas devem:

* Retornar a fatura existente
* Nunca criar uma nova

---

### 3. Numeração por tenant

O número da fatura deve ser:

* Sequencial por tenant
* Único dentro do tenant
* Independente de outros tenants

Exemplo:

* Tenant A: INV-0001, INV-0002
* Tenant B: INV-0001, INV-0002

---

## Datas e Prazos

Cada fatura deve ter:

### issued_at

Data de emissão da fatura.

### due_date

Data limite para pagamento.

Regras:

* Definida com base na política do plano
* Pode variar por tenant ou contrato

---

## Pagamentos

A fatura pode receber:

* Pagamento total
* Pagamento parcial (opcional, conforme modelo)

A fatura só deve ser marcada como `paid` quando:

```
total_paid >= total_amount
```

---

## Integração com Dunning

A fatura é a base para:

* Detecção de inadimplência
* Re-tentativas de pagamento
* Suspensão de tenant

Nenhuma lógica de dunning deve existir dentro da entidade Invoice.

Toda estratégia pertence a:

`dunning-strategy.md`

---

## Auditoria Obrigatória

As seguintes ações devem ser auditadas:

* Criação de fatura
* Emissão
* Alteração de estado
* Pagamento registrado
* Cancelamento
* Write-off

Os logs devem incluir:

* actor (sistema ou usuário)
* timestamp
* estado anterior
* estado novo
* motivo

---

## Regras de Segurança

* Acesso a invoices é sempre **escopado por tenant**
* Nenhuma fatura pode ser acessada entre tenants
* Todas as operações devem passar por:

  * Autorização
  * Validação de escopo

---

## Regras Não Negociáveis

1. Fatura não contém lógica de assinatura.
2. Fatura não contém lógica de gateway.
3. Fatura emitida não pode ter valores alterados.
4. Toda transição de estado deve ser auditada.
5. Não pode existir mais de uma fatura para o mesmo período.
6. Numeração é isolada por tenant.

---

## O que esta skill NÃO cobre

Esta skill não deve tratar:

* Integração com gateway
* Webhooks de pagamento
* Estratégia de retries
* Suspensão de tenants
* Notificações de cobrança

Esses pontos pertencem às skills:

* `payment-gateway-integration.md`
* `dunning-strategy.md`
