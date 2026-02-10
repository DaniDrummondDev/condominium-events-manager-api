# payment-gateway-integration.md

**Skill: Integração com Gateway de Pagamento**
**Fase:** 3 — Billing & Assinaturas
**Domínio:** Billing
**Tipo:** Infrastructure Skill

---

## Objetivo

Definir a arquitetura, regras e responsabilidades relacionadas à **integração com gateways de pagamento**, garantindo:

* Desacoplamento entre domínio de billing e provedores externos
* Processamento seguro de pagamentos
* Suporte a múltiplos gateways no futuro
* Sincronização consistente de estados de pagamento
* Idempotência em todas as operações críticas

Esta skill trata exclusivamente da **comunicação com o provedor de pagamento**, não das regras de fatura ou assinatura.

---

## Dependências

Esta skill depende das seguintes skills:

* `invoice-management.md`
* `billing-subscription.md`
* `billing-security.md`
* `audit-logging.md`
* `api-security.md`
* `idempotency-strategy.md`

Nenhuma integração com gateway deve ignorar essas dependências.

---

## Princípios Arquiteturais

### 1. Gateway é infraestrutura, não domínio

O gateway:

* É um provedor externo
* Pode ser substituído
* Não deve contaminar o domínio de billing

O domínio deve trabalhar com:

* `Payment`
* `Invoice`
* `Subscription`

Nunca com:

* `StripeInvoice`
* `PagarmeCharge`
* `MercadoPagoPayment`

---

### 2. Camada de abstração obrigatória

Toda integração deve passar por uma **interface de gateway**, por exemplo:

```
PaymentGatewayInterface
```

Implementações concretas:

* `StripeGateway`
* `PagarmeGateway`
* `MercadoPagoGateway`

O domínio nunca deve conhecer implementações específicas.

---

### 3. Sistema é a fonte de verdade da fatura

O gateway:

* Não define o valor da cobrança
* Não define o estado final da fatura
* Não controla o ciclo da assinatura

A fonte de verdade é:

* A entidade `Invoice` do sistema

Eventos do gateway apenas **sincronizam o estado de pagamento**.

---

## Responsabilidades desta Skill

Esta skill é responsável por:

* Criação de cobranças no gateway
* Registro de transações de pagamento
* Processamento de webhooks
* Sincronização de status
* Validação de assinaturas de webhook
* Idempotência em eventos externos

---

## Modelo Conceitual de Pagamento

### Entidade: Payment

Representa um pagamento ou tentativa de pagamento.

Campos conceituais:

* `id`
* `tenant_id`
* `invoice_id`
* `gateway`
* `gateway_payment_id`
* `status`
* `amount`
* `currency`
* `payment_method`
* `paid_at`
* `failed_at`
* `created_at`
* `updated_at`

---

## Estados de Pagamento

Estados sugeridos:

1. `pending`
2. `authorized` (opcional)
3. `paid`
4. `failed`
5. `canceled`
6. `refunded` (opcional)

O estado do pagamento **não substitui o estado da fatura**.

---

## Fluxo de Cobrança

### Fluxo padrão

1. Sistema gera uma `Invoice`
2. Sistema solicita criação de cobrança ao gateway
3. Gateway retorna:

   * `gateway_payment_id`
   * status inicial
4. Sistema cria um registro `Payment`
5. Gateway envia webhooks de atualização
6. Sistema processa o webhook
7. Sistema atualiza o `Payment`
8. Sistema atualiza a `Invoice` conforme o pagamento

---

## Webhooks

### Regras obrigatórias

1. Todo webhook deve ser:

   * Autenticado
   * Validado
   * Idempotente

2. O sistema nunca deve confiar cegamente no webhook.

3. O webhook deve:

   * Localizar o `Payment` pelo `gateway_payment_id`
   * Atualizar o estado interno
   * Registrar auditoria

---

### Idempotência

O processamento de webhooks deve:

* Registrar `event_id` do gateway
* Impedir processamento duplicado
* Garantir consistência do estado

Tabela conceitual:

```
gateway_events
- id
- gateway
- event_id
- event_type
- processed_at
```

Se um evento já foi processado:

* Deve ser ignorado
* Sem gerar efeitos colaterais

---

## Segurança de Integração

### Assinatura de Webhook

Todo webhook deve:

* Validar assinatura criptográfica
* Validar origem
* Validar timestamp (se disponível)

Se a validação falhar:

* O evento deve ser rejeitado
* Deve ser auditado

---

### Armazenamento de credenciais

Credenciais do gateway:

* Nunca devem estar no código
* Devem ser armazenadas em:

  * Variáveis de ambiente
  * Secret manager

---

## Regras de Sincronização com Invoice

### Quando pagamento é confirmado

Se:

```
payment.status = paid
```

Então:

* Registrar pagamento na `Invoice`
* Verificar total pago
* Se quitado:

  * Atualizar invoice para `paid`

---

### Quando pagamento falha

Se:

```
payment.status = failed
```

Então:

* Atualizar registro de pagamento
* Não alterar a invoice diretamente
* Deixar a lógica para o dunning

---

## Auditoria Obrigatória

As seguintes ações devem ser auditadas:

* Criação de pagamento
* Recebimento de webhook
* Mudança de estado de pagamento
* Falha de validação de webhook
* Reprocessamento manual

---

## Regras Não Negociáveis

1. O domínio não pode depender de SDK específico de gateway.
2. Toda integração deve usar uma interface abstrata.
3. Webhooks devem ser idempotentes.
4. Webhooks devem ser autenticados.
5. A fatura é a fonte de verdade financeira.
6. Pagamento não altera invoice sem validação de valor.

---

## O que esta skill NÃO cobre

Esta skill não deve tratar:

* Geração de faturas
* Estados da assinatura
* Estratégia de inadimplência
* Suspensão de tenants
* Notificações de cobrança

Esses pontos pertencem às skills:

* `invoice-management.md`
* `subscription-lifecycle.md`
* `dunning-strategy.md`
