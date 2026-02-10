# Domain Model — Modelo de Dominio

## Condominium Events Manager API

**Tipo:** SaaS B2B Multi-Tenant
**Stack:** Laravel, PostgreSQL, pgvector
**Arquitetura:** DDD, Clean Architecture, SOLID
**Modo:** API-first

---

## 1. Visao Geral

Este documento define o **modelo de dominio completo** do sistema, incluindo:

- Todas as entidades e seus campos tipados
- Value Objects identificados
- Aggregate Roots e suas fronteiras
- Relacionamentos entre entidades
- Domain Events por agregado
- Invariantes e regras de negocio por entidade

O modelo esta organizado por **Bounded Context**, conforme definido em `bounded-contexts.md`.

---

## 2. Mapa de Bounded Contexts e Agregados

```
+====================================================================+
|                       PLATFORM DOMAIN                              |
|                                                                    |
|  +-------------------------+  +-------------------------------+    |
|  | << Aggregate >>         |  | << Aggregate >>               |    |
|  | Tenant Management       |  | Billing & Plans               |    |
|  |                         |  |                               |    |
|  |  [Tenant] (Root)        |  |  [Plan] ------+               |    |
|  |                         |  |  [PlanVersion] (Root)         |    |
|  +-------------------------+  |    +-- PlanFeature            |    |
|                                |  [Feature]                    |    |
|  +-------------------------+  |  [TenantFeatureOverride]      |    |
|  | << Aggregate >>         |  |                               |    |
|  | Platform Admin          |  |  [Subscription] (Root)        |    |
|  |                         |  |  [Invoice] (Root)             |    |
|  |  [PlatformUser] (Root)  |  |    +-- InvoiceItem           |    |
|  |  [TenantAdminAction]    |  |  [Payment]                    |    |
|  +-------------------------+  |  [DunningPolicy]              |    |
|                                |  [GatewayEvent]               |    |
|                                +-------------------------------+    |
+====================================================================+

+====================================================================+
|                        TENANT DOMAIN                               |
|                                                                    |
|  +-------------------------+  +-------------------------------+    |
|  | << Aggregate >>         |  | << Aggregate >>               |    |
|  | Units & Residents       |  | Spaces Management             |    |
|  |                         |  |                               |    |
|  |  [Block]                |  |  [Space] (Root)               |    |
|  |  [Unit] (Root)          |  |    +-- SpaceAvailability      |    |
|  |    +-- Resident         |  |    +-- SpaceBlock             |    |
|  |  [TenantUser]           |  |    +-- SpaceRule              |    |
|  +-------------------------+  +-------------------------------+    |
|                                                                    |
|  +-------------------------+  +-------------------------------+    |
|  | << Aggregate >>         |  | << Aggregate >>               |    |
|  | Reservations            |  | Governance                    |    |
|  |                         |  |                               |    |
|  |  [Reservation] (Root)   |  |  [CondominiumRule]            |    |
|  |    +-- Guest            |  |  [Violation] (Root)           |    |
|  |    +-- ReservationSP    |  |    +-- ViolationContestation  |    |
|  +-------------------------+  |  [Penalty]                     |    |
|                                |  [PenaltyPolicy]              |    |
|  +-------------------------+  +-------------------------------+    |
|  | << Aggregate >>         |                                       |
|  | People Control          |  +-------------------------------+    |
|  |                         |  | << Aggregate >>               |    |
|  |  [ServiceProvider]      |  | Communication                 |    |
|  +-------------------------+  |                               |    |
|                                |  [Announcement] (Root)        |    |
|                                |    +-- AnnouncementRead       |    |
|                                |  [SupportRequest] (Root)      |    |
|                                |    +-- SupportMessage         |    |
|                                +-------------------------------+    |
+====================================================================+

+====================================================================+
|                    CONTEXTOS TRANSVERSAIS                           |
|                                                                    |
|  [AuditLog]  [IdempotencyKey]  [AIEmbedding]  [AIUsageLog]        |
|  [AIActionLog]  [GatewayEvent]                                     |
+====================================================================+
```

---

## 3. Platform Domain

### 3.1 Tenant Management Context

#### 3.1.1 Tenant (Aggregate Root)

Representa um condominio cadastrado na plataforma. Unidade principal de isolamento de dados, seguranca e billing.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `slug` | `string(60)` | No | Identificador publico, unico globalmente, imutavel apos criacao |
| `name` | `string(255)` | No | Nome do condominio |
| `type` | `enum` | No | Tipo do condominio: `horizontal`, `vertical`, `mixed` |
| `status` | `enum` | No | Estado do ciclo de vida |
| `config` | `jsonb` | Yes | Configuracoes especificas do tenant |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Status (Maquina de Estados):**

```
prospect --> trial --> provisioning --> active --> past_due --> suspended --> canceled --> archived
                                         |                        |
                                         +--- past_due -----------+
                                         |
                                         +--- suspended (admin) --+
                                         |                        |
                                         +--- canceled -----------+---> archived
```

Transicoes permitidas:

| De | Para | Trigger |
|----|------|---------|
| `prospect` | `trial` | Inicio do trial |
| `prospect` | `provisioning` | Contratacao direta |
| `trial` | `provisioning` | Conversao do trial |
| `trial` | `canceled` | Expiracao sem conversao |
| `provisioning` | `active` | Provisionamento concluido (DB/schema criado, migrations executadas) |
| `active` | `past_due` | Falha de pagamento |
| `active` | `suspended` | Acao administrativa |
| `active` | `canceled` | Cancelamento voluntario |
| `past_due` | `active` | Pagamento regularizado |
| `past_due` | `suspended` | Grace period expirado |
| `suspended` | `active` | Reativacao apos pagamento |
| `suspended` | `canceled` | Periodo de suspensao excedido |
| `canceled` | `archived` | Retencao de dados expirada |

**Value Objects:**

- `TenantType`: enum imutavel (`horizontal`, `vertical`, `mixed`)
- `TenantStatus`: enum com regras de transicao
- `TenantConfig`: objeto JSON estruturado com configuracoes do condominio

**Invariantes:**

1. `slug` deve ser unico globalmente e imutavel apos criacao
2. `name` e obrigatorio e nao-vazio
3. Transicoes de status seguem exclusivamente a maquina de estados definida
4. Tenant em status `provisioning` nao pode receber dados de dominio
5. Tenant `canceled` ou `archived` nao pode ser reativado (novo tenant necessario)
6. Todo tenant deve ter exatamente uma Subscription ativa ou em trial
7. Provisionamento cria database/schema, executa migrations e cria usuario inicial

**Domain Events:**

- `TenantCreated`
- `TenantProvisioned`
- `TenantActivated`
- `TenantSuspended`
- `TenantPastDue`
- `TenantCanceled`
- `TenantArchived`
- `TenantReactivated`

---

#### 3.1.2 PlatformUser (Aggregate Root)

Usuarios com acesso ao painel de gestao da plataforma SaaS. Nao sao usuarios de tenant.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `email` | `string(255)` | No | E-mail unico, usado para login |
| `password_hash` | `string` | No | Hash da senha (bcrypt/argon2) |
| `role` | `enum` | No | Papel na plataforma |
| `status` | `enum` | No | Estado da conta: `active`, `inactive` |
| `last_login_at` | `timestamp` | Yes | Ultimo login bem-sucedido |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Roles:** `platform_owner`, `platform_admin`, `platform_support`

**Value Objects:**

- `Email`: string validada com formato de e-mail
- `PasswordHash`: string opaca, nunca exposta em API
- `PlatformRole`: enum imutavel

**Invariantes:**

1. `email` deve ser unico entre PlatformUsers
2. `password_hash` nunca e retornado em respostas de API
3. `platform_owner` e papel supremo; nao pode ser removido pelo `platform_admin`
4. `platform_support` tem acesso somente-leitura a dados de tenant (metadata, nunca dados de dominio)
5. MFA obrigatorio para `platform_owner` e `platform_admin`

**Domain Events:**

- `PlatformUserCreated`
- `PlatformUserDeactivated`
- `PlatformUserLoggedIn`

---

#### 3.1.3 TenantAdminAction

Registro de acoes administrativas executadas pela equipe da plataforma sobre um tenant.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant |
| `action` | `string(100)` | No | Tipo da acao (suspend, reactivate, override_feature, change_plan) |
| `reason` | `text` | No | Justificativa obrigatoria |
| `performed_by` | `UUID` | No | FK para PlatformUser |
| `metadata` | `jsonb` | Yes | Dados adicionais da acao |
| `created_at` | `timestamp` | No | Data da acao |

**Invariantes:**

1. `reason` e obrigatoria; acoes sem justificativa sao proibidas
2. Registro e imutavel (append-only)
3. `performed_by` deve referenciar um PlatformUser ativo

---

### 3.2 Billing & Plans Context

#### 3.2.1 Plan

Define um plano comercial disponivel na plataforma.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `name` | `string(100)` | No | Nome do plano (ex: "Basico", "Pro") |
| `slug` | `string(60)` | No | Identificador publico unico |
| `status` | `enum` | No | `active`, `archived` |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Invariantes:**

1. `slug` deve ser unico e imutavel
2. Plano `archived` nao aceita novas assinaturas
3. Plano com assinaturas ativas nao pode ser excluido, apenas arquivado

---

#### 3.2.2 PlanVersion (Aggregate Root do contexto de precificacao)

Versao especifica de um plano, contendo preco, ciclo e features.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `plan_id` | `UUID` | No | FK para Plan |
| `version` | `integer` | No | Numero sequencial da versao |
| `price` | `decimal(10,2)` | No | Preco do plano |
| `currency` | `string(3)` | No | Moeda (ISO 4217, ex: "BRL") |
| `billing_cycle` | `enum` | No | `monthly`, `yearly` |
| `trial_days` | `integer` | No | Dias de trial (0 = sem trial) |
| `status` | `enum` | No | `active`, `deprecated` |
| `created_at` | `timestamp` | No | Data de criacao |

**Value Objects:**

- `Money`: composto por `amount: decimal` + `currency: string(3)`
- `BillingCycle`: enum (`monthly`, `yearly`)

**Invariantes:**

1. Apenas uma PlanVersion pode estar `active` por Plan e BillingCycle
2. PlanVersion `deprecated` nao aceita novas assinaturas
3. PlanVersion com assinaturas ativas nao pode ser excluida
4. `price` deve ser >= 0
5. `trial_days` deve ser >= 0
6. `version` e auto-incremental por Plan

**Domain Events:**

- `PlanVersionCreated`
- `PlanVersionDeprecated`

---

#### 3.2.3 PlanFeature

Feature associada a uma versao de plano.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `plan_version_id` | `UUID` | No | FK para PlanVersion |
| `feature_key` | `string(100)` | No | Chave da feature (ex: "max_spaces", "allow_recurring_reservations") |
| `value` | `string(255)` | No | Valor da feature |
| `type` | `enum` | No | `boolean`, `integer`, `string` |

**Invariantes:**

1. `feature_key` deve ser unica por PlanVersion
2. `value` deve ser compativel com `type` declarado
3. PlanFeature pertence exclusivamente a uma PlanVersion

---

#### 3.2.4 Feature

Catalogo global de features configuraveis da plataforma.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `code` | `string(100)` | No | Codigo unico da feature |
| `name` | `string(255)` | No | Nome legivel |
| `type` | `enum` | No | `boolean`, `integer`, `enum` |
| `description` | `text` | Yes | Descricao da feature |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Invariantes:**

1. `code` deve ser unico globalmente
2. Feature e imutavel em `code` apos criacao
3. Toda PlanFeature.feature_key deve corresponder a um Feature.code existente

---

#### 3.2.5 TenantFeatureOverride

Override de feature para um tenant especifico (excecao ao plano).

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant |
| `feature_id` | `UUID` | No | FK para Feature |
| `value` | `string(255)` | No | Valor do override |
| `reason` | `text` | No | Justificativa obrigatoria |
| `expires_at` | `timestamp` | Yes | Data de expiracao (null = permanente) |
| `created_by` | `UUID` | No | FK para PlatformUser que criou o override |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Invariantes:**

1. Override prevalece sobre PlanFeature enquanto ativo
2. `reason` e obrigatoria (governanca)
3. Override expirado e automaticamente ignorado pelo FeatureResolver
4. Apenas PlatformUser pode criar overrides

**Domain Events:**

- `TenantFeatureOverridden`
- `TenantFeatureOverrideExpired`

---

#### 3.2.6 Subscription (Aggregate Root)

Vinculo ativo entre um Tenant e um PlanVersion. Controla acesso ao sistema.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant |
| `plan_version_id` | `UUID` | No | FK para PlanVersion |
| `status` | `enum` | No | Estado da assinatura |
| `billing_cycle` | `enum` | No | `monthly`, `yearly` |
| `current_period_start` | `timestamp` | No | Inicio do periodo atual |
| `current_period_end` | `timestamp` | No | Fim do periodo atual |
| `grace_period_end` | `timestamp` | Yes | Fim do periodo de carencia |
| `canceled_at` | `timestamp` | Yes | Data do cancelamento |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Status (Maquina de Estados):**

```
trialing --> active --> past_due --> grace_period --> suspended --> canceled --> expired
              |                                         |
              +--- canceled (voluntario) ---------------+
```

| De | Para | Trigger |
|----|------|---------|
| `trialing` | `active` | Conversao (pagamento bem-sucedido) |
| `trialing` | `canceled` | Trial expirado sem conversao |
| `active` | `past_due` | Falha de pagamento |
| `active` | `canceled` | Cancelamento voluntario |
| `past_due` | `active` | Pagamento regularizado |
| `past_due` | `grace_period` | Retry falhou, inicia carencia |
| `grace_period` | `active` | Pagamento regularizado |
| `grace_period` | `suspended` | Carencia expirada |
| `suspended` | `active` | Pagamento + reativacao |
| `suspended` | `canceled` | Periodo de suspensao excedido |
| `canceled` | `expired` | Retencao de dados expirada |

**Value Objects:**

- `SubscriptionPeriod`: composto por `start: timestamp` + `end: timestamp`
- `BillingCycle`: enum (`monthly`, `yearly`)

**Invariantes:**

1. Um Tenant pode ter no maximo uma Subscription ativa (nao-canceled/expired) por vez
2. `current_period_end` deve ser posterior a `current_period_start`
3. Transicoes de status seguem exclusivamente a maquina de estados
4. Cancelamento voluntario encerra no fim do periodo corrente (nao imediato)
5. Subscription controla acesso: tenant so opera se subscription esta `trialing` ou `active`
6. `grace_period_end` so e preenchido quando status = `grace_period`

**Domain Events:**

- `SubscriptionCreated`
- `SubscriptionActivated`
- `SubscriptionPastDue`
- `SubscriptionGracePeriodStarted`
- `SubscriptionSuspended`
- `SubscriptionCanceled`
- `SubscriptionExpired`
- `SubscriptionRenewed`

---

#### 3.2.7 Invoice (Aggregate Root)

Fatura gerada por ciclo de assinatura.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant |
| `subscription_id` | `UUID` | No | FK para Subscription |
| `invoice_number` | `string(50)` | No | Numero sequencial unico |
| `status` | `enum` | No | Estado da fatura |
| `currency` | `string(3)` | No | Moeda (ISO 4217) |
| `subtotal` | `decimal(10,2)` | No | Subtotal antes de impostos e descontos |
| `tax_amount` | `decimal(10,2)` | No | Valor de impostos |
| `discount_amount` | `decimal(10,2)` | No | Valor de descontos |
| `total_amount` | `decimal(10,2)` | No | Valor total final |
| `issued_at` | `timestamp` | No | Data de emissao |
| `due_date` | `date` | No | Data de vencimento |
| `paid_at` | `timestamp` | Yes | Data do pagamento |
| `voided_at` | `timestamp` | Yes | Data de anulacao |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Status:** `draft`, `open`, `paid`, `past_due`, `void`, `uncollectible`

```
draft --> open --> paid
                   |
           open --> past_due --> paid
                   |                |
           open --> void        past_due --> uncollectible
```

**Value Objects:**

- `Money`: `amount: decimal(10,2)` + `currency: string(3)`
- `InvoiceNumber`: string com formato sequencial unico

**Invariantes:**

1. `invoice_number` e unico globalmente
2. `total_amount` = `subtotal` + `tax_amount` - `discount_amount`
3. Invoice `paid` ou `void` e imutavel
4. `paid_at` so e preenchido quando status = `paid`
5. `voided_at` so e preenchido quando status = `void`
6. Invoice `draft` nao e visivel para o tenant
7. Valores monetarios nunca sao negativos

**Domain Events:**

- `InvoiceIssued`
- `InvoicePaid`
- `InvoicePastDue`
- `InvoiceVoided`

---

#### 3.2.8 InvoiceItem

Item de linha dentro de uma Invoice.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `invoice_id` | `UUID` | No | FK para Invoice |
| `type` | `enum` | No | `plan`, `add_on`, `adjustment`, `credit` |
| `description` | `string(255)` | No | Descricao do item |
| `quantity` | `integer` | No | Quantidade |
| `unit_price` | `decimal(10,2)` | No | Preco unitario |
| `total_price` | `decimal(10,2)` | No | Preco total do item |

**Invariantes:**

1. `total_price` = `quantity` * `unit_price`
2. `quantity` deve ser >= 1
3. InvoiceItem so pode ser adicionado a Invoice em status `draft`
4. Soma de InvoiceItems.total_price deve ser consistente com Invoice.subtotal

---

#### 3.2.9 Payment

Transacao financeira vinculada a uma fatura.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant |
| `invoice_id` | `UUID` | No | FK para Invoice |
| `gateway` | `string(50)` | No | Nome do gateway (ex: "stripe", "pagarme") |
| `gateway_payment_id` | `string(255)` | No | ID da transacao no gateway |
| `status` | `enum` | No | Estado do pagamento |
| `amount` | `decimal(10,2)` | No | Valor do pagamento |
| `currency` | `string(3)` | No | Moeda |
| `payment_method` | `string(50)` | No | Metodo (credit_card, boleto, pix) |
| `paid_at` | `timestamp` | Yes | Data do pagamento confirmado |
| `failed_at` | `timestamp` | Yes | Data da falha |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Status:** `pending`, `authorized`, `paid`, `failed`, `canceled`, `refunded`

```
pending --> authorized --> paid --> refunded
         |              |
         +-- failed     +-- canceled
```

**Value Objects:**

- `Money`: `amount` + `currency`
- `PaymentMethod`: enum (`credit_card`, `boleto`, `pix`)
- `GatewayReference`: `gateway` + `gateway_payment_id`

**Invariantes:**

1. `gateway_payment_id` deve ser unico por `gateway`
2. Payment `paid` ou `refunded` e imutavel
3. `paid_at` preenchido somente quando status = `paid`
4. `failed_at` preenchido somente quando status = `failed`
5. `amount` deve corresponder a Invoice.total_amount (ou parcial, se suportado)
6. Retry de pagamento cria novo Payment, nunca reutiliza o anterior

**Domain Events:**

- `PaymentCreated`
- `PaymentAuthorized`
- `PaymentPaid`
- `PaymentFailed`
- `PaymentRefunded`

---

#### 3.2.10 DunningPolicy

Politica de inadimplencia que define regras de cobranca automatica.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `name` | `string(100)` | No | Nome da politica |
| `grace_period_days` | `integer` | No | Dias de carencia apos falha |
| `retry_attempts` | `integer` | No | Numero de tentativas de cobranca |
| `retry_interval_days` | `integer` | No | Intervalo entre tentativas |
| `suspension_day` | `integer` | No | Dia em que o tenant e suspenso |
| `cancellation_day` | `integer` | No | Dia em que o tenant e cancelado |

**Invariantes:**

1. `grace_period_days` >= 0
2. `retry_attempts` >= 1
3. `suspension_day` > `grace_period_days`
4. `cancellation_day` > `suspension_day`
5. Apenas uma DunningPolicy ativa por vez (ou associada por plano)

---

### 3.3 Relacionamentos do Platform Domain

```
+----------------+        +----------------+        +------------------+
|    Tenant      |1------*|  Subscription  |*------1| PlanVersion      |
|                |        |                |        |                  |
|  slug          |        |  status        |        |  version         |
|  type          |        |  billing_cycle |        |  price + currency|
|  status        |        |  period_start  |        |  trial_days      |
|  config        |        |  period_end    |        |  status          |
+-------+--------+        +-------+--------+        +--------+---------+
        |                         |                           |
        |1                        |1                          |1
        |                         |                           |
        |*                        |*                          |*
+-------+--------+        +------+--------+         +--------+---------+
| TenantFeature  |        |    Invoice    |         |   PlanFeature    |
| Override       |        |               |         |                  |
|  value         |        |  invoice_num  |         |  feature_key     |
|  reason        |        |  status       |         |  value           |
|  expires_at    |        |  total_amount |         |  type            |
+----------------+        +------+--------+         +------------------+
                                  |
                           +------+--------+
                           |               |
                    +------+----+   +------+------+
                    |InvoiceItem|   |   Payment   |
                    |           |   |             |
                    | type      |   | gateway     |
                    | quantity  |   | status      |
                    | unit_price|   | amount      |
                    +-----------+   | method      |
                                    +-------------+

+------------------+        +------------------+
|  PlatformUser    |1------*| TenantAdminAction|
|                  |        |                  |
|  email           |        |  action          |
|  role            |        |  reason          |
|  status          |        |  metadata        |
+------------------+        +------------------+

+------------------+        +------------------+
|     Plan         |1------*|  PlanVersion     |
|                  |        |                  |
|  name            |        |  version         |
|  slug            |        |  price           |
|  status          |        |  billing_cycle   |
+------------------+        +------------------+

+------------------+
|    Feature       |1------* PlanFeature (via feature_key)
|                  |1------* TenantFeatureOverride (via feature_id)
|  code            |
|  name            |
|  type            |
+------------------+

+------------------+
|  DunningPolicy   |  (configuracao global da plataforma)
|                  |
|  grace_period    |
|  retry_attempts  |
|  suspension_day  |
|  cancellation_day|
+------------------+
```

---

## 4. Tenant Domain

### 4.1 Units & Residents Context

#### 4.1.1 Block

Edificacao dentro de um condominio vertical ou misto. Opcional para condominios horizontais.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant (isolamento) |
| `name` | `string(100)` | No | Nome do bloco (ex: "Bloco A", "Torre 1") |
| `identifier` | `string(20)` | No | Codigo curto (ex: "A", "1") |
| `floors` | `integer` | Yes | Numero de andares |
| `status` | `enum` | No | `active`, `inactive` |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Invariantes:**

1. `identifier` deve ser unico por tenant
2. Bloco e opcional: condominios horizontais nao possuem blocos
3. Bloco `inactive` nao impede acesso a unidades existentes, mas impede criacao de novas
4. `floors` deve ser > 0 quando informado

**Domain Events:**

- `BlockCreated`
- `BlockDeactivated`

---

#### 4.1.2 Unit (Aggregate Root)

Unidade residencial (apartamento ou casa). Base para reservas, penalidades e controle de acesso.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant (isolamento) |
| `block_id` | `UUID` | Yes | FK para Block (null para casas) |
| `number` | `string(20)` | No | Numero da unidade (ex: "101", "Casa 3") |
| `floor` | `integer` | Yes | Andar (apenas para apartamentos) |
| `type` | `enum` | No | `apartment`, `house` |
| `status` | `enum` | No | `active`, `inactive` |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Value Objects:**

- `UnitType`: enum (`apartment`, `house`)
- `UnitNumber`: string representando numero/identificador da unidade

**Invariantes:**

1. `number` deve ser unico dentro do bloco (ou do tenant se sem bloco)
2. `block_id` e obrigatorio quando `type` = `apartment` e tenant.type = `vertical`
3. `block_id` deve ser null quando `type` = `house` e tenant.type = `horizontal`
4. Unidade `inactive` nao pode fazer reservas
5. Unidade deve ter pelo menos um Resident com `is_primary` = true enquanto ativa
6. `floor` so e preenchido para `apartment`

**Domain Events:**

- `UnitCreated`
- `UnitDeactivated`
- `UnitReactivated`

---

#### 4.1.3 Resident

Morador vinculado a uma unidade. Representa a relacao pessoa-unidade.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant (isolamento) |
| `user_id` | `UUID` | No | FK para TenantUser |
| `unit_id` | `UUID` | No | FK para Unit |
| `role_in_unit` | `enum` | No | Papel na unidade |
| `is_primary` | `boolean` | No | Se e o morador principal da unidade |
| `moved_in_at` | `date` | No | Data de mudanca |
| `moved_out_at` | `date` | Yes | Data de saida |
| `status` | `enum` | No | `active`, `inactive` |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Value Objects:**

- `ResidentRole`: enum (`owner`, `tenant_resident`, `dependent`)

**Roles:**

| Role | Descricao |
|------|-----------|
| `owner` | Proprietario da unidade |
| `tenant_resident` | Inquilino (locatario) |
| `dependent` | Familiar ou pessoa autorizada |

**Invariantes:**

1. Um User pode ser Resident de multiplas unidades (ex: proprietario de 2 apartamentos)
2. Cada unidade ativa deve ter exatamente um Resident com `is_primary` = true
3. `moved_out_at` preenchido implica `status` = `inactive`
4. Resident `inactive` nao pode fazer reservas
5. `dependent` nao pode ser `is_primary`
6. Apenas `owner` ou `tenant_resident` podem ser `is_primary`

**Domain Events:**

- `ResidentInvited`
- `ResidentActivated`
- `ResidentDeactivated`
- `ResidentMovedOut`

---

#### 4.1.4 TenantUser

Conta de acesso ao sistema dentro de um tenant especifico.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant (isolamento) |
| `name` | `string(255)` | No | Nome completo |
| `email` | `string(255)` | No | E-mail unico por tenant |
| `phone` | `string(20)` | Yes | Telefone |
| `document` | `string(20)` | Yes | CPF (dado pessoal, LGPD) |
| `role` | `enum` | No | Papel no condominio |
| `status` | `enum` | No | Estado da conta |
| `invited_by` | `UUID` | Yes | FK para TenantUser que convidou |
| `invited_at` | `timestamp` | Yes | Data do convite |
| `activated_at` | `timestamp` | Yes | Data de ativacao |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Roles:** `condomino`, `sindico`, `administradora`, `funcionario`

**Status:** `invited`, `active`, `inactive`

```
invited --> active --> inactive
```

**Value Objects:**

- `TenantUserRole`: enum (`condomino`, `sindico`, `administradora`, `funcionario`)
- `Document`: CPF validado (formato e digito verificador)

**Invariantes:**

1. `email` deve ser unico por tenant
2. `document` (CPF) deve ser valido quando informado
3. Apenas `sindico` e `administradora` podem convidar novos usuarios
4. Convite tem prazo de expiracao configuravel
5. TenantUser `inactive` nao pode acessar o sistema
6. `activated_at` preenchido somente quando status muda para `active`
7. Dados pessoais (email, phone, document) sujeitos a LGPD

**Domain Events:**

- `TenantUserInvited`
- `TenantUserActivated`
- `TenantUserDeactivated`

---

#### 4.1.5 Relacionamentos do Units & Residents Context

```
+----------------+
|     Tenant     |
|  (type: h/v/m) |
+-------+--------+
        |
   +----+-----+------------------+
   |1         |1                  |1
   |*         |*                  |*
+--+-------+ ++-----------+ +----+-------+
|  Block   | |   Unit     | | TenantUser |
| (vertical| |            | |            |
|  only)   | |  number    | |  name      |
|  name    | |  type      | |  email     |
|  floors  | |  floor     | |  role      |
+--+-------+ +--+----+----+ +---+--------+
   |1            |1   |*         |1
   |*            |    |          |
   +---> Unit    |    +------+---+
    (block_id)   |           |
                 |    +------+-------+
                 |    |   Resident   |
                 +----+              |
                      | role_in_unit |
                      | is_primary   |
                      | moved_in_at  |
                      +--------------+
```

---

### 4.2 Spaces Management Context

#### 4.2.1 Space (Aggregate Root)

Espaco comum do condominio. Entidade central sobre a qual reservas, governanca e inteligencia operam.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant (isolamento) |
| `name` | `string(255)` | No | Nome do espaco |
| `slug` | `string(100)` | No | Identificador URL-friendly, unico por tenant |
| `description` | `text` | Yes | Descricao do espaco |
| `type` | `string(50)` | No | Tipo (party_hall, bbq_area, court, pool, gym, playground, meeting_room, gourmet) |
| `capacity` | `integer` | No | Capacidade maxima de pessoas |
| `status` | `enum` | No | Estado do espaco |
| `requires_approval` | `boolean` | No | Se reserva precisa de aprovacao do sindico |
| `advance_booking_days` | `integer` | No | Antecedencia minima para reserva (dias) |
| `max_booking_days_ahead` | `integer` | No | Antecedencia maxima para reserva (dias) |
| `min_duration_minutes` | `integer` | No | Duracao minima de reserva (minutos) |
| `max_duration_minutes` | `integer` | No | Duracao maxima de reserva (minutos) |
| `allow_recurring` | `boolean` | No | Se permite reservas recorrentes |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Status:** `active`, `inactive`, `maintenance`

```
active <--> inactive
active <--> maintenance
```

**Value Objects:**

- `SpaceType`: enum ou string classificadora (party_hall, bbq_area, court, pool, gym, etc.)
- `Duration`: `min_minutes` + `max_minutes`
- `BookingWindow`: `advance_booking_days` + `max_booking_days_ahead`

**Invariantes:**

1. `name` obrigatorio e unico por tenant
2. `slug` unico por tenant, imutavel apos criacao
3. `capacity` > 0
4. `advance_booking_days` >= 0
5. `max_booking_days_ahead` > `advance_booking_days`
6. `min_duration_minutes` > 0
7. `max_duration_minutes` >= `min_duration_minutes`
8. Espaco `inactive` ou `maintenance` nao aceita novas reservas
9. Espaco em `maintenance` gera bloqueio automatico
10. Quantidade de espacos por tenant limitada pelo plano (feature flag `max_spaces`)

**Domain Events:**

- `SpaceCreated`
- `SpaceUpdated`
- `SpaceDeactivated`
- `SpaceReactivated`
- `SpaceMaintenanceStarted`
- `SpaceMaintenanceEnded`

---

#### 4.2.2 SpaceAvailability

Horarios em que o espaco pode ser reservado, por dia da semana.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `space_id` | `UUID` | No | FK para Space |
| `day_of_week` | `integer` | No | Dia da semana (0=domingo, 6=sabado) |
| `start_time` | `time` | No | Horario de inicio |
| `end_time` | `time` | No | Horario de fim |
| `is_available` | `boolean` | No | Se o periodo esta disponivel |

**Value Objects:**

- `TimeRange`: `start_time` + `end_time`
- `DayOfWeek`: integer 0-6

**Invariantes:**

1. `end_time` > `start_time`
2. Periodos do mesmo dia nao podem se sobrepor para o mesmo Space
3. `day_of_week` entre 0 e 6

---

#### 4.2.3 SpaceBlock

Periodo em que o espaco esta indisponivel (sobrepoem disponibilidade regular).

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `space_id` | `UUID` | No | FK para Space |
| `reason` | `enum` | No | `maintenance`, `holiday`, `event`, `administrative` |
| `start_datetime` | `timestamp` | No | Inicio do bloqueio |
| `end_datetime` | `timestamp` | No | Fim do bloqueio |
| `blocked_by` | `UUID` | No | FK para TenantUser que bloqueou |
| `notes` | `text` | Yes | Observacoes |
| `created_at` | `timestamp` | No | Data de criacao |

**Value Objects:**

- `DateTimeRange`: `start_datetime` + `end_datetime`
- `BlockReason`: enum (`maintenance`, `holiday`, `event`, `administrative`)

**Invariantes:**

1. `end_datetime` > `start_datetime`
2. Bloqueio sobrepoe disponibilidade regular
3. Reservas existentes no periodo bloqueado devem ser tratadas (cancelamento ou notificacao)
4. `blocked_by` deve ser sindico ou administradora

**Domain Events:**

- `SpaceBlocked`
- `SpaceUnblocked`

---

#### 4.2.4 SpaceRule

Regra de uso configuravel por espaco.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `space_id` | `UUID` | No | FK para Space |
| `rule_type` | `string(50)` | No | Tipo da regra (max_guests, cleaning_fee, deposit_required, noise_curfew, etc.) |
| `value` | `string(255)` | No | Valor da regra (interpretado conforme tipo) |
| `description` | `text` | Yes | Descricao da regra para o condomino |
| `is_active` | `boolean` | No | Se a regra esta ativa |

**Exemplos de regras:**

| rule_type | value | Descricao |
|-----------|-------|-----------|
| `max_guests` | `50` | Maximo de convidados por reserva |
| `cleaning_fee` | `150.00` | Taxa de limpeza |
| `deposit_required` | `true` | Exige caucao |
| `noise_curfew` | `22:00` | Horario limite de barulho |
| `min_interval_hours` | `4` | Intervalo minimo entre reservas |
| `max_reservations_per_month` | `2` | Limite de reservas por unidade/mes |

**Invariantes:**

1. `rule_type` + `space_id` deve ser unico (uma regra por tipo por espaco)
2. `value` deve ser interpretavel conforme `rule_type`
3. Regra `is_active` = false e ignorada pelo sistema

**Domain Events:**

- `SpaceRuleCreated`
- `SpaceRuleUpdated`
- `SpaceRuleDeactivated`

---

#### 4.2.5 Relacionamentos do Spaces Context

```
+----------------+
|     Tenant     |
+-------+--------+
        |1
        |*
+-------+---------+
|     Space       |  (Aggregate Root)
|                 |
|  name, slug     |
|  type, capacity |
|  status         |
|  requires_appr. |
|  booking rules  |
+--+---------+----+
   |1   |1   |1
   |*   |*   |*
+--+------+ +-+--------+ +-+---------+
|SpaceAvai| |SpaceBlock| |SpaceRule  |
|lability | |          | |           |
|         | | reason   | | rule_type |
| day_of  | | start    | | value     |
| week    | | end      | | is_active |
| start   | | notes    | +-----------+
| end     | +----------+
+---------+
```

---

### 4.3 Reservations Context

#### 4.3.1 Reservation (Aggregate Root)

Solicitacao de uso de espaco comum por um condomino. Principal caso de uso do produto e Aggregate Root central do dominio.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant (isolamento) |
| `space_id` | `UUID` | No | FK para Space |
| `unit_id` | `UUID` | No | FK para Unit (unidade do condomino) |
| `requested_by` | `UUID` | No | FK para TenantUser que solicitou |
| `approved_by` | `UUID` | Yes | FK para TenantUser que aprovou |
| `status` | `enum` | No | Estado da reserva |
| `date` | `date` | No | Data da reserva |
| `start_time` | `time` | No | Horario de inicio |
| `end_time` | `time` | No | Horario de fim |
| `expected_guests_count` | `integer` | No | Numero previsto de convidados |
| `purpose` | `string(255)` | Yes | Finalidade/descricao do evento |
| `notes` | `text` | Yes | Observacoes adicionais |
| `canceled_at` | `timestamp` | Yes | Data do cancelamento |
| `canceled_by` | `UUID` | Yes | FK para TenantUser que cancelou |
| `cancellation_reason` | `text` | Yes | Motivo do cancelamento |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Status (Maquina de Estados):**

```
                     +--- confirmed ---> completed
                     |
Solicitacao --> pending_approval ---> confirmed ---> completed
                     |                   |
                     +--- rejected       +--- canceled
                     |                   |
                     +--- canceled       +--- no_show

(Se requires_approval = false):
Solicitacao --> confirmed ---> completed
                   |
                   +--- canceled
                   |
                   +--- no_show
```

| De | Para | Trigger |
|----|------|---------|
| `pending_approval` | `confirmed` | Sindico aprova |
| `pending_approval` | `rejected` | Sindico rejeita |
| `pending_approval` | `canceled` | Condomino ou sindico cancela |
| `confirmed` | `canceled` | Condomino ou sindico cancela |
| `confirmed` | `completed` | Evento realizado com sucesso |
| `confirmed` | `no_show` | Reserva nao utilizada |

**Value Objects:**

- `ReservationPeriod`: composto por `date: date` + `start_time: time` + `end_time: time`
- `ReservationStatus`: enum com regras de transicao

**Invariantes:**

1. Para um dado Space + date + periodo: so pode existir **uma reserva ativa** (`confirmed` ou `pending_approval`)
2. `end_time` > `start_time`
3. Reserva deve caber dentro do horario de SpaceAvailability do dia
4. Reserva nao pode coincidir com SpaceBlock ativo
5. `expected_guests_count` <= Space.capacity
6. `expected_guests_count` <= SpaceRule.max_guests (quando configurado)
7. Data da reserva deve respeitar `advance_booking_days` e `max_booking_days_ahead` do Space
8. Duracao da reserva deve respeitar `min_duration_minutes` e `max_duration_minutes` do Space
9. Unidade nao pode ter penalidade ativa do tipo `temporary_block`
10. Unidade nao pode exceder `max_reservations_per_month` (configuravel por SpaceRule)
11. Limites do plano (feature flags) devem ser respeitados: `max_reservations_per_month` (tenant), `max_guests_per_reservation`
12. Transicoes de status seguem exclusivamente a maquina de estados
13. `approved_by` so e preenchido quando status muda para `confirmed` via aprovacao
14. `canceled_at`, `canceled_by`, `cancellation_reason` so preenchidos quando status = `canceled`
15. Cancelamento tardio (apos prazo configuravel) pode gerar infracao automatica
16. No-show gera infracao automatica
17. Rejeicao exige justificativa (via `cancellation_reason` ou campo dedicado)

**Domain Events:**

- `ReservationRequested`
- `ReservationConfirmed`
- `ReservationRejected`
- `ReservationCanceled`
- `ReservationCompleted`
- `ReservationNoShow`

---

### 4.4 Governance Context

#### 4.4.1 CondominiumRule

Regra do regulamento interno do condominio.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant (isolamento) |
| `category` | `string(50)` | No | Categoria (reservas, convidados, barulho, limpeza, etc.) |
| `title` | `string(255)` | No | Titulo da regra |
| `description` | `text` | No | Descricao completa da regra |
| `is_active` | `boolean` | No | Se a regra esta vigente |
| `applies_to` | `jsonb` | No | Escopo de aplicacao (all_spaces ou lista de space_ids) |
| `created_by` | `UUID` | No | FK para TenantUser |
| `document_section_id` | `UUID` | Yes | FK para DocumentSection (se regra derivada de documento legal) |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Value Objects:**

- `RuleCategory`: string classificadora (reservas, convidados, barulho, limpeza, seguranca)
- `RuleScope`: all_spaces ou lista de space_ids

**Invariantes:**

1. `title` e `description` sao obrigatorios
2. Regra `is_active` = false nao e aplicada
3. Alteracoes sao versionadas e auditadas
4. Apenas sindico e administradora podem criar/alterar regras
5. Condominos podem consultar regras vigentes (somente leitura)
6. `document_section_id` permite rastrear a origem legal da regra

**Domain Events:**

- `CondominiumRuleCreated`
- `CondominiumRuleUpdated`
- `CondominiumRuleDeactivated`

---

#### 4.4.2 CondominiumDocument

Documento legal do condominio (Convencao, Regimento Interno, Ata de Assembleia). Suporta versionamento — apenas um documento `active` por tipo.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant (isolamento) |
| `type` | `enum` | No | Tipo do documento |
| `title` | `string(255)` | No | Titulo do documento |
| `version` | `integer` | No | Numero da versao |
| `status` | `enum` | No | Estado do documento |
| `full_text` | `text` | No | Conteudo textual completo |
| `file_path` | `string(500)` | Yes | Caminho do arquivo original (PDF) |
| `file_hash` | `string(64)` | Yes | Hash SHA-256 do arquivo para integridade |
| `approved_at` | `timestamp` | Yes | Data de aprovacao em assembleia |
| `approved_in` | `string(255)` | Yes | Referencia da assembleia que aprovou |
| `created_by` | `UUID` | No | FK para TenantUser |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Type:** `convencao`, `regimento_interno`, `ata_assembleia`, `other`

**Status:** `draft`, `active`, `archived`

**Value Objects:**

- `DocumentType`: tipo do documento legal (convencao, regimento_interno, ata_assembleia, other)
- `DocumentStatus`: estado do ciclo de vida (draft → active → archived)

**Invariantes:**

1. Apenas um documento `active` por `type` no mesmo tenant
2. Ativar um novo documento arquiva automaticamente o anterior do mesmo tipo
3. `full_text` e obrigatorio — documento sem texto nao e valido
4. Convencao requer quorum de 2/3 para alteracao (informativo, controlado por `approved_in`)
5. Regimento Interno requer maioria simples (informativo)
6. Apenas sindico e administradora podem criar/ativar documentos
7. Condominos podem consultar documentos ativos (somente leitura)

**Domain Events:**

- `DocumentUploaded`
- `DocumentActivated`
- `DocumentArchived`
- `DocumentSectionsParsed`

---

#### 4.4.3 DocumentSection

Secao hierarquica de um documento legal (artigo, capitulo, paragrafo). Unidade minima para embedding de IA e consulta granular.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `document_id` | `UUID` | No | FK para CondominiumDocument |
| `parent_section_id` | `UUID` | Yes | FK auto-referencia (secao pai para hierarquia) |
| `section_number` | `string(50)` | No | Numeracao (ex: "Art. 15", "Cap. III", "§ 2o") |
| `title` | `string(255)` | Yes | Titulo da secao (quando aplicavel) |
| `content` | `text` | No | Conteudo textual da secao |
| `order_index` | `integer` | No | Ordem de exibicao dentro do documento |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Invariantes:**

1. `section_number` unico por documento
2. `content` obrigatorio
3. Hierarquia: secao pode ter pai (capitulo > artigo > paragrafo)
4. `order_index` determina ordem de exibicao
5. Secoes sao imutaveis apos ativacao do documento — nova versao = novo documento com novas secoes

**Integracao com IA:**

- Cada `DocumentSection` pode ter embeddings via `ai_embeddings` (source_type = 'document_section')
- Permite busca semantica: "quais artigos falam sobre horario de silencio?"
- CondominiumRule pode referenciar a secao de origem via `document_section_id`

---

#### 4.4.4 Violation (Aggregate Root)

Registro de infracao contra uma unidade/condomino.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant (isolamento) |
| `unit_id` | `UUID` | No | FK para Unit |
| `user_id` | `UUID` | No | FK para TenantUser infrator |
| `reservation_id` | `UUID` | Yes | FK para Reservation (se infracao vinculada a reserva) |
| `rule_id` | `UUID` | Yes | FK para CondominiumRule violada |
| `type` | `enum` | No | Tipo da infracao |
| `description` | `text` | No | Descricao da ocorrencia |
| `severity` | `enum` | No | Gravidade |
| `status` | `enum` | No | Estado da infracao |
| `registered_by` | `UUID` | No | FK para TenantUser que registrou |
| `created_at` | `timestamp` | No | Data do registro |
| `resolved_at` | `timestamp` | Yes | Data da resolucao |

**Type:** `no_show`, `late_cancellation`, `damage`, `noise`, `overcrowding`, `rule_breach`, `other`

**Severity:** `minor`, `moderate`, `severe`

**Status:** `open`, `acknowledged`, `contested`, `resolved`

```
open --> acknowledged --> resolved
  |         |
  +--- contested --> resolved (accepted/rejected)
```

**Tipos e origens:**

| Tipo | Origem | Severidade tipica |
|------|--------|-------------------|
| `no_show` | Automatica (evento ReservationNoShow) | minor/moderate |
| `late_cancellation` | Automatica (cancelamento apos prazo) | minor |
| `damage` | Manual (registro pelo sindico) | severe |
| `noise` | Manual | moderate |
| `overcrowding` | Manual | moderate |
| `rule_breach` | Manual (qualquer regra violada) | variavel |
| `other` | Manual | variavel |

**Invariantes:**

1. Toda infracao deve ter origem rastreavel (evento ou registro manual)
2. `description` e obrigatoria
3. Infracao `resolved` e imutavel
4. `resolved_at` preenchido somente quando status = `resolved`
5. Infracoes automaticas (`no_show`, `late_cancellation`) seguem configuracao do espaco e regulamento
6. Infracao pode existir sem `reservation_id` (infracoes manuais nao vinculadas a reserva)
7. Infracao pode existir sem `rule_id` (infracoes automaticas ou ad-hoc)

**Domain Events:**

- `ViolationRegistered`
- `ViolationAcknowledged`
- `ViolationContested`
- `ViolationResolved`

---

#### 4.4.5 Penalty

Consequencia aplicada a uma unidade/condomino por infracao.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant (isolamento) |
| `unit_id` | `UUID` | No | FK para Unit |
| `user_id` | `UUID` | No | FK para TenantUser penalizado |
| `violation_id` | `UUID` | No | FK para Violation que originou |
| `type` | `enum` | No | Tipo da penalidade |
| `description` | `text` | No | Descricao/justificativa |
| `starts_at` | `timestamp` | No | Inicio da vigencia |
| `ends_at` | `timestamp` | Yes | Fim da vigencia (null para warnings e multas) |
| `status` | `enum` | No | `active`, `expired`, `revoked` |
| `applied_by` | `UUID` | No | FK para TenantUser ou "system" |
| `revoked_by` | `UUID` | Yes | FK para TenantUser que revogou |
| `revocation_reason` | `text` | Yes | Motivo da revogacao |
| `created_at` | `timestamp` | No | Data de criacao |

**Type:** `warning`, `temporary_block`, `reservation_limit_reduction`, `fine`

| Tipo | Efeito no sistema |
|------|-------------------|
| `warning` | Aviso formal, sem bloqueio. Conta para threshold |
| `temporary_block` | Impede criacao de reservas no periodo |
| `reservation_limit_reduction` | Reduz cota mensal de reservas |
| `fine` | Multa informativa (nao cobrada pelo sistema) |

**Status:**

```
active --> expired (automatico, quando ends_at e atingido)
active --> revoked (manual, pelo sindico)
```

**Invariantes:**

1. Toda penalidade deve ter `violation_id` (origem rastreavel)
2. `description` e obrigatoria (justificativa)
3. Penalidade `expired` ou `revoked` e imutavel
4. `temporary_block` deve ter `ends_at` definido
5. Penalidade `active` do tipo `temporary_block` impede criacao de reservas pela unidade
6. Penalidade comunicada ao condomino via notificacao
7. `revoked_by` e `revocation_reason` obrigatorios quando status = `revoked`
8. Expiracao e verificada automaticamente (job ou query)

**Domain Events:**

- `PenaltyApplied`
- `PenaltyExpired`
- `PenaltyRevoked`
- `UserBlocked` (quando temporary_block e aplicado)
- `UserUnblocked` (quando temporary_block expira ou e revogado)

---

#### 4.4.6 PenaltyPolicy

Configuracao de penalidades automaticas. Define quando penalidades sao aplicadas automaticamente.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant (isolamento) |
| `space_id` | `UUID` | Yes | FK para Space (null = aplica a todos os espacos) |
| `violation_type` | `enum` | No | Tipo de infracao que ativa a politica |
| `threshold` | `integer` | No | Numero de infracoes para disparar |
| `penalty_type` | `enum` | No | Tipo de penalidade a aplicar |
| `penalty_duration_days` | `integer` | Yes | Duracao em dias (para temporary_block) |
| `is_active` | `boolean` | No | Se a politica esta ativa |
| `created_at` | `timestamp` | No | Data de criacao |

**Exemplos de configuracao:**

| violation_type | threshold | penalty_type | duration |
|---------------|-----------|--------------|----------|
| `no_show` | 2 em 30 dias | `temporary_block` | 15 dias |
| `late_cancellation` | 3 em 30 dias | `warning` | - |
| `noise` | 1 | `temporary_block` | 30 dias |
| `damage` | 1 | `temporary_block` | 60 dias |

**Invariantes:**

1. `threshold` >= 1
2. `penalty_duration_days` obrigatorio quando `penalty_type` = `temporary_block`
3. Politica `is_active` = false e ignorada
4. Quando `space_id` = null, politica aplica a todos os espacos do tenant
5. Verificacao de threshold considera janela temporal (ultimos 30 dias por padrao)

---

#### 4.4.7 ViolationContestation

Recurso de um condomino contra uma infracao registrada.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `violation_id` | `UUID` | No | FK para Violation |
| `contested_by` | `UUID` | No | FK para TenantUser |
| `reason` | `text` | No | Motivo da contestacao |
| `status` | `enum` | No | `pending`, `accepted`, `rejected` |
| `decided_by` | `UUID` | Yes | FK para TenantUser que decidiu |
| `decision_notes` | `text` | Yes | Notas da decisao |
| `created_at` | `timestamp` | No | Data da contestacao |
| `decided_at` | `timestamp` | Yes | Data da decisao |

**Status:**

```
pending --> accepted (infracao revertida, penalidade revogada)
pending --> rejected (infracao mantida)
```

**Invariantes:**

1. Apenas o condomino afetado (unit_id/user_id da Violation) pode contestar
2. `reason` e obrigatoria
3. Contestacao `accepted` ou `rejected` e imutavel
4. Se `accepted`: Violation.status -> `resolved`, penalidades associadas -> `revoked`
5. `decided_by` e `decision_notes` obrigatorios na decisao
6. Uma Violation pode ter no maximo uma contestacao `pending` por vez

**Domain Events:**

- `ViolationContestationCreated`
- `ViolationContestationAccepted`
- `ViolationContestationRejected`

---

#### 4.4.8 Relacionamentos do Governance Context

```
+----------------------+       +------------------+
| CondominiumDocument  |       |  PenaltyPolicy   |
|                      |       |                  |
| type (convencao,     |       | violation_type   |
|  regimento, ata)     |       | threshold        |
| version              |       | penalty_type     |
| status               |       | penalty_duration |
| full_text            |       +------------------+
+----------+-----------+
           |1
           |*
+----------+-----------+
|   DocumentSection    |
|                      |
| section_number       |
| title                |
| content              |
| parent_section_id?   |
+----------+-----------+
           |0..1
           |
+------------------+
| CondominiumRule  |
|                  |
| category         |
| title            |
| is_active        |
| applies_to       |
| document_section_id? (FK opcional para DocumentSection)
+--------+---------+
         |0..1
         |
+--------+---------+        +------------------+
|    Violation     |1------*|    Penalty       |
|  (Aggregate Root)|        |                  |
|                  |        | type             |
| type             |        | starts_at        |
| severity         |        | ends_at          |
| status           |        | status           |
| unit_id          |        | applied_by       |
| user_id          |        | revoked_by       |
| reservation_id?  |        +------------------+
+--------+---------+
         |1
         |0..1
+--------+---------+
| ViolationContest.|
|                  |
| reason           |
| status           |
| decided_by       |
+------------------+
```

---

### 4.5 People Control Context

#### 4.5.1 Guest

Pessoa externa convidada para um evento/reserva.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant (isolamento) |
| `reservation_id` | `UUID` | No | FK para Reservation |
| `name` | `string(255)` | No | Nome do convidado |
| `document` | `string(20)` | Yes | CPF ou RG (configuravel se obrigatorio) |
| `phone` | `string(20)` | Yes | Telefone |
| `vehicle_plate` | `string(10)` | Yes | Placa do veiculo |
| `relationship` | `string(50)` | Yes | Relacao com o condomino (amigo, familiar, outro) |
| `status` | `enum` | No | Estado do convidado |
| `checked_in_at` | `timestamp` | Yes | Data/hora de entrada |
| `checked_out_at` | `timestamp` | Yes | Data/hora de saida |
| `registered_by` | `UUID` | No | FK para TenantUser que registrou |
| `created_at` | `timestamp` | No | Data de criacao |

**Status:** `expected`, `checked_in`, `checked_out`, `no_show`

```
expected --> checked_in --> checked_out
expected --> no_show
```

**Value Objects:**

- `Document`: CPF ou RG validado
- `VehiclePlate`: string com formato de placa

**Invariantes:**

1. Convidado deve estar vinculado a uma reserva ativa (`confirmed` ou `pending_approval`)
2. Numero total de convidados por reserva <= Space.capacity
3. Numero total de convidados por reserva <= SpaceRule.max_guests (quando configurado)
4. `checked_in_at` preenchido somente quando status = `checked_in`
5. `checked_out_at` preenchido somente quando status = `checked_out`
6. Check-in so pode ser feito por `funcionario` (portaria)
7. Dados pessoais (name, document, phone) sujeitos a LGPD: retidos enquanto reserva ativa + periodo legal, depois anonimizados

**Domain Events:**

- `GuestRegistered`
- `GuestCheckedIn`
- `GuestCheckedOut`
- `GuestNoShow`

---

#### 4.5.2 ServiceProvider (Aggregate Root)

Prestador de servico cadastrado no condominio.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant (isolamento) |
| `name` | `string(255)` | No | Nome do prestador |
| `company_name` | `string(255)` | Yes | Nome da empresa |
| `document` | `string(20)` | No | CPF ou CNPJ |
| `phone` | `string(20)` | No | Telefone |
| `email` | `string(255)` | Yes | E-mail |
| `service_type` | `string(50)` | No | Tipo de servico (buffet, limpeza, decoracao, dj, seguranca, outro) |
| `status` | `enum` | No | `active`, `inactive`, `blocked` |
| `notes` | `text` | Yes | Observacoes |
| `created_by` | `UUID` | No | FK para TenantUser que cadastrou |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Invariantes:**

1. `document` (CPF/CNPJ) deve ser valido
2. Prestador `blocked` nao pode ser vinculado a novas reservas
3. Prestador `inactive` nao aparece em buscas (mas mantem historico)
4. Prestador pode ser reutilizado em multiplas reservas
5. Dados pessoais sujeitos a LGPD

**Domain Events:**

- `ServiceProviderRegistered`
- `ServiceProviderUpdated`
- `ServiceProviderBlocked`
- `ServiceProviderDeactivated`

---

#### 4.5.3 ReservationServiceProvider

Vinculacao de um prestador a uma reserva especifica.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `reservation_id` | `UUID` | No | FK para Reservation |
| `service_provider_id` | `UUID` | No | FK para ServiceProvider |
| `service_description` | `string(255)` | Yes | Descricao do servico para esta reserva |
| `arrival_time` | `time` | Yes | Horario previsto de chegada |
| `departure_time` | `time` | Yes | Horario previsto de saida |
| `status` | `enum` | No | `expected`, `checked_in`, `checked_out` |
| `checked_in_at` | `timestamp` | Yes | Data/hora de entrada |
| `checked_out_at` | `timestamp` | Yes | Data/hora de saida |
| `created_at` | `timestamp` | No | Data de criacao |

**Invariantes:**

1. ServiceProvider deve estar `active` para ser vinculado
2. Reservation deve estar `confirmed` ou `pending_approval`
3. Check-in so por `funcionario` (portaria)
4. Prestador sem vinculo com reserva ativa = acesso negado

**Domain Events:**

- `ServiceProviderLinkedToReservation`
- `ServiceProviderCheckedIn`
- `ServiceProviderCheckedOut`

---

#### 4.5.4 Relacionamentos do People Control Context

```
+------------------+        +--------------------+
|   Reservation    |1------*|      Guest         |
|  (de outro       |        |                    |
|   contexto)      |        | name               |
|                  |        | document           |
|                  |        | status             |
|                  |        | checked_in/out_at  |
+--------+---------+        +--------------------+
         |1
         |*
+--------+------------------+
| ReservationServiceProvider|
|                           |
| service_description       |
| arrival_time              |
| status                    |
| checked_in/out_at         |
+--------+------------------+
         |*
         |1
+--------+---------+
| ServiceProvider  |  (Aggregate Root)
|                  |
| name             |
| company_name     |
| document         |
| service_type     |
| status           |
+------------------+
```

---

### 4.6 Communication Context

#### 4.6.1 Announcement (Aggregate Root)

Aviso oficial do sindico/administracao para moradores.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant (isolamento) |
| `title` | `string(255)` | No | Titulo do aviso |
| `content` | `text` | No | Conteudo completo do aviso |
| `priority` | `enum` | No | `normal`, `urgent` |
| `audience` | `enum` | No | `all`, `block`, `units` |
| `audience_filter` | `jsonb` | Yes | IDs de blocos ou unidades (quando audience != all) |
| `published_at` | `timestamp` | Yes | Data de publicacao |
| `expires_at` | `timestamp` | Yes | Data de expiracao |
| `status` | `enum` | No | `draft`, `published`, `archived` |
| `created_by` | `UUID` | No | FK para TenantUser |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Status:**

```
draft --> published --> archived
```

**Value Objects:**

- `Priority`: enum (`normal`, `urgent`)
- `Audience`: enum (`all`, `block`, `units`) + `audience_filter: jsonb`

**Invariantes:**

1. `title` e `content` sao obrigatorios
2. Aviso `draft` nao e visivel para condominos
3. `published_at` preenchido somente quando status muda para `published`
4. Aviso expirado (`expires_at` no passado) nao aparece em listagens ativas
5. Apenas sindico e administradora podem criar/publicar
6. `audience_filter` obrigatorio quando `audience` = `block` ou `units`
7. Conteudo de avisos pode conter dados pessoais (sujeito a LGPD)

**Domain Events:**

- `AnnouncementCreated`
- `AnnouncementPublished`
- `AnnouncementArchived`

---

#### 4.6.2 AnnouncementRead

Confirmacao de leitura de um aviso por um morador.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `announcement_id` | `UUID` | No | FK para Announcement |
| `user_id` | `UUID` | No | FK para TenantUser |
| `read_at` | `timestamp` | No | Data/hora da leitura |

**Invariantes:**

1. Combinacao `announcement_id` + `user_id` deve ser unica (uma leitura por usuario por aviso)
2. `read_at` e imutavel apos criacao
3. Apenas avisos `published` podem ter leituras registradas

---

#### 4.6.3 SupportRequest (Aggregate Root)

Solicitacao de atendimento do morador para a administracao.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant (isolamento) |
| `unit_id` | `UUID` | No | FK para Unit |
| `subject` | `string(255)` | No | Assunto da solicitacao |
| `category` | `enum` | No | `maintenance`, `noise`, `security`, `general`, `other` |
| `status` | `enum` | No | Estado da solicitacao |
| `priority` | `enum` | No | `low`, `normal`, `high` |
| `created_by` | `UUID` | No | FK para TenantUser |
| `assigned_to` | `UUID` | Yes | FK para TenantUser responsavel |
| `resolved_at` | `timestamp` | Yes | Data de resolucao |
| `closed_at` | `timestamp` | Yes | Data de fechamento |
| `created_at` | `timestamp` | No | Data de criacao |
| `updated_at` | `timestamp` | No | Data da ultima atualizacao |

**Status:**

```
open --> in_progress --> resolved --> closed
  |                                    ^
  +--- closed (cancelamento)           |
       resolved --> open (reabertura) -+
```

| De | Para | Trigger |
|----|------|---------|
| `open` | `in_progress` | Administracao assume |
| `in_progress` | `resolved` | Administracao resolve |
| `resolved` | `closed` | Morador confirma ou timeout |
| `resolved` | `open` | Morador reabre |
| `open` | `closed` | Morador cancela |

**Value Objects:**

- `SupportCategory`: enum (`maintenance`, `noise`, `security`, `general`, `other`)
- `SupportPriority`: enum (`low`, `normal`, `high`)

**Invariantes:**

1. `subject` e obrigatorio
2. `category` e obrigatoria
3. `resolved_at` preenchido somente quando status = `resolved`
4. `closed_at` preenchido somente quando status = `closed`
5. Solicitacao `closed` e imutavel
6. Apenas condomino pode criar solicitacoes (de sua propria unidade)
7. Apenas sindico, administradora e funcionario podem responder

**Domain Events:**

- `SupportRequestCreated`
- `SupportRequestAssigned`
- `SupportRequestUpdated`
- `SupportRequestResolved`
- `SupportRequestClosed`
- `SupportRequestReopened`

---

#### 4.6.4 SupportMessage

Mensagem dentro de uma thread de solicitacao.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `support_request_id` | `UUID` | No | FK para SupportRequest |
| `sender_id` | `UUID` | No | FK para TenantUser |
| `content` | `text` | No | Conteudo da mensagem |
| `is_internal` | `boolean` | No | Se visivel apenas para administracao |
| `created_at` | `timestamp` | No | Data de criacao |

**Invariantes:**

1. `content` e obrigatorio e nao-vazio
2. Mensagem e imutavel apos criacao (append-only)
3. Mensagens `is_internal` = true nao sao visiveis para o condomino
4. Mensagem so pode ser adicionada a SupportRequest que nao esta `closed`
5. Conteudo pode conter dados pessoais (sujeito a LGPD)

**Domain Events:**

- `SupportMessageSent`

---

#### 4.6.5 Relacionamentos do Communication Context

```
+-------------------+        +---------------------+
|   Announcement    |1------*|  AnnouncementRead   |
|  (Aggregate Root) |        |                     |
|                   |        | user_id             |
| title             |        | read_at             |
| content           |        +---------------------+
| priority          |
| audience          |
| status            |
+-------------------+

+-------------------+        +---------------------+
|  SupportRequest   |1------*|  SupportMessage     |
|  (Aggregate Root) |        |                     |
|                   |        | sender_id           |
| subject           |        | content             |
| category          |        | is_internal         |
| status            |        +---------------------+
| priority          |
| assigned_to       |
+-------------------+
```

---

## 5. Contextos Transversais (Shared/Infrastructure)

### 5.1 AuditLog

Registro imutavel de acoes criticas no sistema. Suporta rastreabilidade e compliance.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | Yes | FK para Tenant (null para acoes de plataforma) |
| `event_type` | `string(100)` | No | Tipo do evento (ex: "reservation.confirmed", "penalty.applied") |
| `actor_id` | `UUID` | No | ID do usuario que executou a acao |
| `resource_type` | `string(50)` | No | Tipo do recurso afetado (ex: "Reservation", "Space") |
| `resource_id` | `UUID` | No | ID do recurso afetado |
| `metadata` | `jsonb` | Yes | Dados adicionais do evento (sem dados pessoais sensíveis) |
| `origin` | `string(50)` | No | Origem da acao (api, job, system, ai_assistant) |
| `timestamp` | `timestamp` | No | Momento exato da acao |

**Invariantes:**

1. Registro e **imutavel** (append-only, nunca editado ou excluido)
2. `actor_id` e obrigatorio (toda acao tem um autor)
3. `tenant_id` = null apenas para acoes no escopo de plataforma
4. Metadata nao deve conter dados pessoais sensiveis (PII)
5. Logs de tenant nunca acessiveis entre tenants
6. Retencao conforme data-retention-policy

---

### 5.2 IdempotencyKey

Garante que operacoes criticas nao sejam executadas em duplicidade.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant |
| `key` | `string(255)` | No | Chave de idempotencia (enviada pelo cliente) |
| `operation_type` | `string(100)` | No | Tipo da operacao |
| `status` | `enum` | No | `processing`, `completed`, `failed` |
| `response_snapshot` | `jsonb` | Yes | Resposta armazenada para replay |
| `created_at` | `timestamp` | No | Data de criacao |
| `expires_at` | `timestamp` | No | Data de expiracao |

**Invariantes:**

1. Combinacao `tenant_id` + `key` deve ser unica
2. Se `status` = `completed`, retornar `response_snapshot` sem reprocessar
3. Chave expirada pode ser reutilizada
4. Expiracao tipica: 24 horas

---

### 5.3 AIEmbedding

Representacao vetorial de conteudo para busca semantica (pgvector).

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant (isolamento absoluto) |
| `source_type` | `string(50)` | No | Tipo da entidade origem (ex: "CondominiumRule", "Reservation") |
| `source_id` | `UUID` | No | ID da entidade origem |
| `embedding` | `vector(1536)` | No | Vetor de embedding (pgvector) |
| `model_version` | `string(50)` | No | Versao do modelo de embedding usado |
| `content_hash` | `char(64)` | No | SHA-256 do conteudo original |
| `metadata` | `jsonb` | Yes | Classificacao, PII flag, data_classification |
| `created_at` | `timestamp` | No | Data de criacao |

**Invariantes:**

1. `tenant_id` obrigatorio em todas as queries (isolamento absoluto)
2. Embeddings nunca sao compartilhados entre tenants
3. Embeddings nunca sao sobrescritos; mudanca de conteudo gera novo registro
4. `content_hash` permite detectar duplicidades
5. Embeddings sao descartaveis e regeneraveis
6. Dados pessoais sensiveis devem ser anonimizados antes de gerar embedding
7. Embeddings nao sao expostos via API publica

---

### 5.4 AIUsageLog

Registro de uso de IA para observabilidade e controle de custos.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant |
| `user_id` | `UUID` | No | FK para TenantUser |
| `action` | `string(100)` | No | Acao executada (ex: "semantic_search", "generate_summary") |
| `prompt_hash` | `char(64)` | No | Hash do prompt (sem armazenar conteudo) |
| `model` | `string(50)` | No | Modelo de IA utilizado |
| `tokens_used` | `integer` | No | Tokens consumidos |
| `created_at` | `timestamp` | No | Data de uso |

**Invariantes:**

1. Prompt original nao e armazenado (apenas hash)
2. `tokens_used` >= 0
3. Isolamento por tenant obrigatorio

---

### 5.5 AIActionLog

Registro de acoes propostas/executadas pela IA com confirmacao humana.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `tenant_id` | `UUID` | No | FK para Tenant |
| `user_id` | `UUID` | No | FK para TenantUser |
| `action` | `string(100)` | No | Acao proposta (ex: "create_reservation", "cancel_reservation") |
| `parameters_hash` | `char(64)` | No | Hash dos parametros (sem armazenar conteudo) |
| `confirmed` | `boolean` | No | Se o usuario confirmou a acao |
| `executed` | `boolean` | No | Se a acao foi executada com sucesso |
| `created_at` | `timestamp` | No | Data do registro |

**Invariantes:**

1. `executed` = true implica `confirmed` = true (acao so executa com confirmacao)
2. IA nunca executa acoes criticas sem confirmacao humana
3. Parametros originais nao sao armazenados (apenas hash)

---

### 5.6 GatewayEvent

Registro de eventos recebidos de gateways de pagamento para reconciliacao.

| Campo | Tipo | Nullable | Descricao |
|-------|------|----------|-----------|
| `id` | `UUID` | No | Identificador unico |
| `gateway` | `string(50)` | No | Nome do gateway |
| `event_id` | `string(255)` | No | ID do evento no gateway |
| `event_type` | `string(100)` | No | Tipo do evento (ex: "payment.succeeded", "charge.failed") |
| `processed_at` | `timestamp` | Yes | Data de processamento |

**Invariantes:**

1. Combinacao `gateway` + `event_id` deve ser unica (deduplicacao)
2. Evento com `processed_at` preenchido nao e reprocessado
3. Eventos nao processados sao retentados conforme politica de retry

---

## 6. Catalogo Completo de Value Objects

| Value Object | Composicao | Usado em |
|-------------|-----------|----------|
| `Money` | `amount: decimal(10,2)` + `currency: string(3)` | PlanVersion, Invoice, Payment |
| `Email` | `value: string(255)` validado | PlatformUser, TenantUser |
| `Document` | `value: string(20)` CPF/CNPJ validado | TenantUser, Guest, ServiceProvider |
| `PasswordHash` | `value: string` opaco | PlatformUser |
| `TenantType` | enum: `horizontal`, `vertical`, `mixed` | Tenant |
| `TenantStatus` | enum com maquina de estados | Tenant |
| `BillingCycle` | enum: `monthly`, `yearly` | PlanVersion, Subscription |
| `SubscriptionPeriod` | `start: timestamp` + `end: timestamp` | Subscription |
| `InvoiceNumber` | `value: string(50)` sequencial unico | Invoice |
| `PaymentMethod` | enum: `credit_card`, `boleto`, `pix` | Payment |
| `GatewayReference` | `gateway: string` + `gateway_payment_id: string` | Payment |
| `UnitType` | enum: `apartment`, `house` | Unit |
| `UnitNumber` | `value: string(20)` | Unit |
| `ResidentRole` | enum: `owner`, `tenant_resident`, `dependent` | Resident |
| `SpaceType` | string classificadora | Space |
| `BookingWindow` | `advance_days: int` + `max_days_ahead: int` | Space |
| `Duration` | `min_minutes: int` + `max_minutes: int` | Space |
| `TimeRange` | `start_time: time` + `end_time: time` | SpaceAvailability, Reservation |
| `DateTimeRange` | `start: timestamp` + `end: timestamp` | SpaceBlock |
| `ReservationPeriod` | `date: date` + `start_time: time` + `end_time: time` | Reservation |
| `ReservationStatus` | enum com maquina de estados | Reservation |
| `ViolationType` | enum: `no_show`, `late_cancellation`, `damage`, `noise`, `overcrowding`, `rule_breach`, `other` | Violation |
| `Severity` | enum: `minor`, `moderate`, `severe` | Violation |
| `PenaltyType` | enum: `warning`, `temporary_block`, `reservation_limit_reduction`, `fine` | Penalty, PenaltyPolicy |
| `Priority` | enum: `normal`, `urgent` | Announcement |
| `Audience` | `type: enum` + `filter: jsonb` | Announcement |
| `SupportCategory` | enum: `maintenance`, `noise`, `security`, `general`, `other` | SupportRequest |
| `SupportPriority` | enum: `low`, `normal`, `high` | SupportRequest |
| `BlockReason` | enum: `maintenance`, `holiday`, `event`, `administrative` | SpaceBlock |
| `DayOfWeek` | integer 0-6 | SpaceAvailability |
| `VehiclePlate` | `value: string(10)` | Guest |
| `RuleCategory` | string classificadora | CondominiumRule |
| `RuleScope` | `all_spaces` ou lista de `space_ids` | CondominiumRule |
| `PlatformRole` | enum: `platform_owner`, `platform_admin`, `platform_support` | PlatformUser |
| `TenantUserRole` | enum: `condomino`, `sindico`, `administradora`, `funcionario` | TenantUser |

---

## 7. Catalogo Completo de Domain Events

### 7.1 Platform Domain

| Evento | Aggregate | Trigger |
|--------|-----------|---------|
| `TenantCreated` | Tenant | Novo tenant cadastrado |
| `TenantProvisioned` | Tenant | DB/schema criado e migrations executadas |
| `TenantActivated` | Tenant | Tenant ativado apos provisionamento |
| `TenantSuspended` | Tenant | Tenant suspenso (admin ou dunning) |
| `TenantPastDue` | Tenant | Falha de pagamento detectada |
| `TenantCanceled` | Tenant | Tenant cancelado |
| `TenantArchived` | Tenant | Dados expirados, tenant arquivado |
| `TenantReactivated` | Tenant | Tenant reativado apos suspensao |
| `PlatformUserCreated` | PlatformUser | Novo admin da plataforma |
| `PlatformUserDeactivated` | PlatformUser | Admin desativado |
| `PlatformUserLoggedIn` | PlatformUser | Login bem-sucedido |
| `PlanVersionCreated` | PlanVersion | Nova versao de plano criada |
| `PlanVersionDeprecated` | PlanVersion | Versao de plano descontinuada |
| `TenantFeatureOverridden` | TenantFeatureOverride | Override de feature criado |
| `TenantFeatureOverrideExpired` | TenantFeatureOverride | Override expirado |
| `SubscriptionCreated` | Subscription | Nova assinatura criada |
| `SubscriptionActivated` | Subscription | Assinatura ativada (apos trial ou pagamento) |
| `SubscriptionPastDue` | Subscription | Pagamento em atraso |
| `SubscriptionGracePeriodStarted` | Subscription | Periodo de carencia iniciado |
| `SubscriptionSuspended` | Subscription | Assinatura suspensa |
| `SubscriptionCanceled` | Subscription | Assinatura cancelada |
| `SubscriptionExpired` | Subscription | Assinatura expirada |
| `SubscriptionRenewed` | Subscription | Assinatura renovada automaticamente |
| `InvoiceIssued` | Invoice | Fatura emitida |
| `InvoicePaid` | Invoice | Fatura paga |
| `InvoicePastDue` | Invoice | Fatura vencida |
| `InvoiceVoided` | Invoice | Fatura anulada |
| `PaymentCreated` | Payment | Pagamento criado |
| `PaymentAuthorized` | Payment | Pagamento autorizado pelo gateway |
| `PaymentPaid` | Payment | Pagamento confirmado |
| `PaymentFailed` | Payment | Pagamento falhou |
| `PaymentRefunded` | Payment | Pagamento reembolsado |

### 7.2 Tenant Domain

| Evento | Aggregate | Trigger |
|--------|-----------|---------|
| `BlockCreated` | Block | Novo bloco cadastrado |
| `BlockDeactivated` | Block | Bloco desativado |
| `UnitCreated` | Unit | Nova unidade cadastrada |
| `UnitDeactivated` | Unit | Unidade desativada |
| `UnitReactivated` | Unit | Unidade reativada |
| `ResidentInvited` | Resident | Morador convidado |
| `ResidentActivated` | Resident | Morador aceitou convite |
| `ResidentDeactivated` | Resident | Morador desativado |
| `ResidentMovedOut` | Resident | Morador saiu da unidade |
| `TenantUserInvited` | TenantUser | Usuario convidado |
| `TenantUserActivated` | TenantUser | Usuario ativou conta |
| `TenantUserDeactivated` | TenantUser | Usuario desativado |
| `SpaceCreated` | Space | Novo espaco cadastrado |
| `SpaceUpdated` | Space | Espaco atualizado |
| `SpaceDeactivated` | Space | Espaco desativado |
| `SpaceReactivated` | Space | Espaco reativado |
| `SpaceMaintenanceStarted` | Space | Espaco entrou em manutencao |
| `SpaceMaintenanceEnded` | Space | Manutencao concluida |
| `SpaceBlocked` | Space | Bloqueio de periodo criado |
| `SpaceUnblocked` | Space | Bloqueio removido |
| `SpaceRuleCreated` | Space | Nova regra de espaco |
| `SpaceRuleUpdated` | Space | Regra atualizada |
| `SpaceRuleDeactivated` | Space | Regra desativada |
| `ReservationRequested` | Reservation | Reserva solicitada |
| `ReservationConfirmed` | Reservation | Reserva confirmada/aprovada |
| `ReservationRejected` | Reservation | Reserva rejeitada |
| `ReservationCanceled` | Reservation | Reserva cancelada |
| `ReservationCompleted` | Reservation | Evento concluido com sucesso |
| `ReservationNoShow` | Reservation | Nao comparecimento |
| `CondominiumRuleCreated` | CondominiumRule | Nova regra do regulamento |
| `CondominiumRuleUpdated` | CondominiumRule | Regra atualizada |
| `CondominiumRuleDeactivated` | CondominiumRule | Regra desativada |
| `ViolationRegistered` | Violation | Infracao registrada |
| `ViolationAcknowledged` | Violation | Infracao reconhecida |
| `ViolationContested` | Violation | Infracao contestada |
| `ViolationResolved` | Violation | Infracao resolvida |
| `ViolationContestationCreated` | ViolationContestation | Contestacao criada |
| `ViolationContestationAccepted` | ViolationContestation | Contestacao aceita |
| `ViolationContestationRejected` | ViolationContestation | Contestacao rejeitada |
| `PenaltyApplied` | Penalty | Penalidade aplicada |
| `PenaltyExpired` | Penalty | Penalidade expirou |
| `PenaltyRevoked` | Penalty | Penalidade revogada pelo sindico |
| `UserBlocked` | Penalty | Bloqueio temporario aplicado |
| `UserUnblocked` | Penalty | Bloqueio removido |
| `GuestRegistered` | Guest | Convidado registrado |
| `GuestCheckedIn` | Guest | Convidado fez check-in |
| `GuestCheckedOut` | Guest | Convidado fez check-out |
| `GuestNoShow` | Guest | Convidado nao compareceu |
| `ServiceProviderRegistered` | ServiceProvider | Prestador cadastrado |
| `ServiceProviderUpdated` | ServiceProvider | Prestador atualizado |
| `ServiceProviderBlocked` | ServiceProvider | Prestador bloqueado |
| `ServiceProviderDeactivated` | ServiceProvider | Prestador desativado |
| `ServiceProviderLinkedToReservation` | ReservationServiceProvider | Prestador vinculado a reserva |
| `ServiceProviderCheckedIn` | ReservationServiceProvider | Prestador fez check-in |
| `ServiceProviderCheckedOut` | ReservationServiceProvider | Prestador fez check-out |
| `AccessDenied` | - | Tentativa de acesso sem vinculo |
| `AnnouncementCreated` | Announcement | Aviso criado |
| `AnnouncementPublished` | Announcement | Aviso publicado |
| `AnnouncementArchived` | Announcement | Aviso arquivado |
| `SupportRequestCreated` | SupportRequest | Solicitacao criada |
| `SupportRequestAssigned` | SupportRequest | Solicitacao atribuida |
| `SupportRequestUpdated` | SupportRequest | Status atualizado |
| `SupportRequestResolved` | SupportRequest | Solicitacao resolvida |
| `SupportRequestClosed` | SupportRequest | Solicitacao fechada |
| `SupportRequestReopened` | SupportRequest | Solicitacao reaberta |
| `SupportMessageSent` | SupportRequest | Mensagem enviada |

---

## 8. Diagrama de Relacionamentos Cross-Context

```
+====================================================================+
|                     FLUXO DE EVENTOS ENTRE CONTEXTOS               |
+====================================================================+

  Billing & Plans                    Tenant Management
  +----------------+                +------------------+
  | Subscription   |--- estado --->|     Tenant       |
  | (controla      |    da         | (acesso ao       |
  |  acesso)       |    assinatura |  sistema)        |
  +-------+--------+                +--------+---------+
          |                                  |
          | PaymentFailed                    | TenantSuspended
          | PaymentPaid                      | TenantActivated
          v                                  v

  +====================================================================+
  |                        TENANT DOMAIN                               |
  +====================================================================+

  Units & Residents          Spaces Management
  +------------------+       +------------------+
  | Unit             |       | Space            |
  | (referencia)     |       | (disponibilidade)|
  +--------+---------+       +--------+---------+
           |                          |
           | unit_id                  | space_id
           |                          | (consulta disponibilidade)
           v                          v
  +------------------------------------------+
  |            Reservations                  |
  |                                          |
  |  Reservation (Aggregate Root)            |
  |                                          |
  +-----+------+------+---------------------+
        |      |      |
        |      |      | ReservationNoShow
        |      |      | ReservationCanceled (tardio)
        |      |      v
        |      |  +------------------+
        |      |  |   Governance     |
        |      |  |                  |
        |      |  | Violation        |
        |      |  | Penalty          |
        |      |  | (bloqueia        |
        |      |  |  reservas)       |
        |      |  +------------------+
        |      |
        |      | reservation_id
        |      v
        |  +------------------+
        |  | People Control   |
        |  |                  |
        |  | Guest            |
        |  | ServiceProvider  |
        |  +------------------+
        |
        | ReservationConfirmed
        | ReservationCanceled
        v
  +------------------+       +------------------+
  | Notification     |       | Communication    |
  | (transversal)    |       | (avisos e        |
  +------------------+       |  solicitacoes)   |
                              +------------------+

  +------------------+       +------------------+
  | Audit &          |       | AI Assistant     |
  | Compliance       |       | (via Use Cases)  |
  +------------------+       +------------------+
```

---

## 9. Regras de Comunicacao entre Contextos

### 9.1 Mecanismos Permitidos

| De | Para | Mecanismo | Descricao |
|----|------|-----------|-----------|
| Billing | Tenant Management | Evento de dominio | Estado da assinatura controla acesso |
| Tenant Management | Billing | Evento de dominio | Criacao/cancelamento de tenant |
| Reservations | Governance | Evento de dominio | NoShow e LateCancellation geram infracoes |
| Reservations | People Control | Composicao | Convidados e prestadores vinculados a reserva |
| Reservations | Notification | Evento de dominio | Confirmacao, cancelamento, lembrete |
| Governance | Notification | Evento de dominio | Penalidade aplicada, contestacao decidida |
| Governance | Reservations | Consulta | Verificacao de bloqueio na criacao de reserva |
| Spaces | Reservations | Consulta | Verificacao de disponibilidade e regras |
| Units | Reservations | Referencia por ID | unit_id na reserva |
| Units | Governance | Referencia por ID | unit_id na penalidade |
| Units | Communication | Referencia por ID | Audiencia de avisos |
| AI Assistant | Reservations | Orquestracao via Use Cases | Com confirmacao humana |
| Platform Admin | Tenant Management | Acoes administrativas | Suspend, reactivate, override |

### 9.2 Mecanismos Proibidos

- Acesso direto ao banco de outro contexto
- Compartilhamento de entidades entre contextos
- Dependencia circular entre contextos
- Contexto de tenant acessando dados de outro tenant

---

## 10. Aggregate Boundaries — Resumo

| Aggregate Root | Entidades Internas | Contexto |
|---------------|-------------------|----------|
| **Tenant** | - | Tenant Management |
| **PlatformUser** | TenantAdminAction | Platform Admin |
| **PlanVersion** | PlanFeature | Billing & Plans |
| **Subscription** | - | Billing & Plans |
| **Invoice** | InvoiceItem | Billing & Plans |
| **Unit** | Resident | Units & Residents |
| **Space** | SpaceAvailability, SpaceBlock, SpaceRule | Spaces Management |
| **Reservation** | Guest, ReservationServiceProvider | Reservations + People |
| **Violation** | ViolationContestation | Governance |
| **ServiceProvider** | - | People Control |
| **Announcement** | AnnouncementRead | Communication |
| **SupportRequest** | SupportMessage | Communication |

**Regra fundamental de agregados:**

- Entidades internas so sao acessadas via Aggregate Root
- Referencia entre agregados e feita exclusivamente por ID
- Cada agregado garante sua propria consistencia transacional
- Consistencia entre agregados e eventual (via eventos de dominio)

---

## 11. Status

Documento **ATIVO**. Define o modelo de dominio completo do sistema.

Toda implementacao deve respeitar este modelo. Alteracoes devem ser justificadas via Decision Record e refletidas neste documento.

---

## 12. Documentos Relacionados

- `bounded-contexts.md` — Fronteiras entre contextos
- `ubiquitous-language.md` — Glossario oficial
- `../architecture/` — Arquitetura tecnica
- `.claude/skills/08-domain/` — Skills detalhadas de cada subdominio
- `.claude/skills/06-operations/event-driven-architecture.md` — Arquitetura de eventos
- `.claude/skills/06-operations/idempotency-strategy.md` — Estrategia de idempotencia
