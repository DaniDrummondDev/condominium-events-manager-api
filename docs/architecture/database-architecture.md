# Arquitetura de Banco de Dados — Condominium Events Manager API

## Status do Documento

**Status:** Ativo\
**Ultima atualizacao:** 2026-02-10\
**Versao da API:** v1

---

## 1. Visão Geral

O sistema utiliza **PostgreSQL** como banco de dados principal em produção, com **isolamento por database/schema** para multi-tenancy. Em ambiente de testes, utiliza **SQLite** para velocidade de execução. A extensão **pgvector** é usada para armazenamento de embeddings de IA.

### 1.1 Dois Contextos de Banco de Dados

```
┌─────────────────────────────────────────────────────────┐
│                 BANCO DA PLATAFORMA (Global)            │
│                                                         │
│  tenants, plans, plan_versions, plan_features,          │
│  features, tenant_feature_overrides, subscriptions,     │
│  invoices, invoice_items, payments, dunning_policies,   │
│  gateway_events, platform_users, tenant_admin_actions,  │
│  platform_audit_logs                                    │
│                                                         │
│  Conexão: Estática (.env principal)                     │
└─────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────┐
│              BANCO DO TENANT (Isolado por tenant)       │
│                                                         │
│  tenant_users, blocks, units, residents,                │
│  spaces, space_availabilities, space_blocks,            │
│  space_rules, reservations, guests,                     │
│  service_providers, condominium_rules,                  │
│  condominium_documents, document_sections,              │
│  violations, violation_contestations,                   │
│  penalties, penalty_policies,                           │
│  announcements, announcement_reads,                     │
│  support_requests, support_messages,                    │
│  tenant_audit_logs, ai_embeddings, ai_usage_logs,       │
│  ai_action_logs, idempotency_keys                       │
│                                                         │
│  Conexão: Dinâmica (resolvida por request)              │
└─────────────────────────────────────────────────────────┘
```

### 1.2 Stack

| Componente | Tecnologia | Uso |
|-----------|-----------|-----|
| Banco de produção | PostgreSQL 16+ | Dados da plataforma e tenants |
| Extensão vetorial | pgvector | Embeddings de IA |
| Banco de testes | SQLite | Testes unitários e integração |
| Cache de queries | Redis | Cache, sessions, revocation list |

---

## 2. Banco da Plataforma (Global)

### 2.1 tenants

Representa cada condomínio/administradora cadastrada na plataforma.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `slug` | `VARCHAR(60)` | NOT NULL | — | Identificador público, único e imutável |
| `name` | `VARCHAR(255)` | NOT NULL | — | Nome do condomínio |
| `type` | `VARCHAR(20)` | NOT NULL | — | `horizontal`, `vertical`, `mixed` |
| `status` | `VARCHAR(20)` | NOT NULL | `'prospect'` | Estado do ciclo de vida |
| `config` | `JSONB` | NULL | — | Configurações específicas do tenant |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `UNIQUE`: `slug`
- `CHECK`: `type IN ('horizontal', 'vertical', 'mixed')`
- `CHECK`: `status IN ('prospect', 'trial', 'provisioning', 'active', 'past_due', 'suspended', 'canceled', 'archived')`

**Índices:**
- `idx_tenants_slug` UNIQUE ON (`slug`)
- `idx_tenants_status` ON (`status`)
- `idx_tenants_type` ON (`type`)
- `idx_tenants_created_at` ON (`created_at`)

---

### 2.2 platform_users

Usuários com acesso ao painel de gestão da plataforma SaaS.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `email` | `VARCHAR(255)` | NOT NULL | — | E-mail único para login |
| `password_hash` | `VARCHAR(255)` | NOT NULL | — | Hash bcrypt/argon2 |
| `role` | `VARCHAR(30)` | NOT NULL | — | Papel na plataforma |
| `status` | `VARCHAR(20)` | NOT NULL | `'active'` | Estado da conta |
| `mfa_secret` | `VARCHAR(255)` | NULL | — | Secret TOTP (criptografado) |
| `mfa_enabled` | `BOOLEAN` | NOT NULL | `false` | MFA ativo |
| `last_login_at` | `TIMESTAMP` | NULL | — | Último login |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `UNIQUE`: `email`
- `CHECK`: `role IN ('platform_owner', 'platform_admin', 'platform_support')`
- `CHECK`: `status IN ('active', 'inactive')`

**Índices:**
- `idx_platform_users_email` UNIQUE ON (`email`)
- `idx_platform_users_role` ON (`role`)

---

### 2.3 tenant_admin_actions

Registro de ações administrativas da plataforma sobre tenants.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `tenant_id` | `UUID` | NOT NULL | — | FK para tenants |
| `action` | `VARCHAR(100)` | NOT NULL | — | Tipo da ação |
| `reason` | `TEXT` | NOT NULL | — | Justificativa obrigatória |
| `performed_by` | `UUID` | NOT NULL | — | FK para platform_users |
| `metadata` | `JSONB` | NULL | — | Dados adicionais |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data da ação |

**Constraints:**
- `PK`: `id`
- `FK`: `tenant_id` → `tenants(id)` ON DELETE RESTRICT
- `FK`: `performed_by` → `platform_users(id)` ON DELETE RESTRICT

**Índices:**
- `idx_tenant_admin_actions_tenant` ON (`tenant_id`)
- `idx_tenant_admin_actions_performed_by` ON (`performed_by`)
- `idx_tenant_admin_actions_created_at` ON (`created_at`)
- `idx_tenant_admin_actions_action` ON (`action`)

---

### 2.4 plans

Planos comerciais disponíveis na plataforma.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `name` | `VARCHAR(100)` | NOT NULL | — | Nome do plano |
| `slug` | `VARCHAR(60)` | NOT NULL | — | Identificador público |
| `status` | `VARCHAR(20)` | NOT NULL | `'active'` | Estado |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `UNIQUE`: `slug`
- `CHECK`: `status IN ('active', 'archived')`

**Índices:**
- `idx_plans_slug` UNIQUE ON (`slug`)
- `idx_plans_status` ON (`status`)

---

### 2.5 plan_versions

Versão específica de um plano. Cada versão pode ter N preços (um por ciclo de cobrança), definidos na tabela `plan_prices`.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `plan_id` | `UUID` | NOT NULL | — | FK para plans |
| `version` | `INTEGER` | NOT NULL | — | Número sequencial |
| `status` | `VARCHAR(20)` | NOT NULL | `'active'` | Estado da versão |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |

**Constraints:**
- `PK`: `id`
- `FK`: `plan_id` → `plans(id)` ON DELETE RESTRICT
- `CHECK`: `status IN ('active', 'deprecated')`

**Índices:**
- `idx_plan_versions_plan` ON (`plan_id`)
- `idx_plan_versions_status` ON (`status`)

---

### 2.6 plan_prices

Preços associados a uma versão de plano, permitindo múltiplos ciclos de cobrança por versão.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `plan_version_id` | `UUID` | NOT NULL | — | FK para plan_versions |
| `billing_cycle` | `VARCHAR(20)` | NOT NULL | — | `monthly`, `semiannual`, `yearly` |
| `price` | `DECIMAL(10,2)` | NOT NULL | — | Preço do plano |
| `currency` | `VARCHAR(3)` | NOT NULL | `'BRL'` | Moeda ISO 4217 |
| `trial_days` | `INTEGER UNSIGNED` | NOT NULL | `0` | Dias de trial |

**Constraints:**
- `PK`: `id`
- `FK`: `plan_version_id` → `plan_versions(id)` ON DELETE CASCADE
- `UNIQUE`: (`plan_version_id`, `billing_cycle`)
- `CHECK`: `billing_cycle IN ('monthly', 'semiannual', 'yearly')`
- `CHECK`: `price >= 0`
- `CHECK`: `trial_days >= 0`

**Índices:**
- `idx_plan_prices_plan_version` ON (`plan_version_id`)
- `idx_plan_prices_unique` UNIQUE ON (`plan_version_id`, `billing_cycle`)

---

### 2.8 plan_features

Features associadas a uma versão de plano.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `plan_version_id` | `UUID` | NOT NULL | — | FK para plan_versions |
| `feature_key` | `VARCHAR(100)` | NOT NULL | — | Chave da feature |
| `value` | `VARCHAR(255)` | NOT NULL | — | Valor da feature |
| `type` | `VARCHAR(20)` | NOT NULL | — | Tipo: `boolean`, `integer`, `string` |

**Constraints:**
- `PK`: `id`
- `FK`: `plan_version_id` → `plan_versions(id)` ON DELETE CASCADE
- `UNIQUE`: (`plan_version_id`, `feature_key`)
- `CHECK`: `type IN ('boolean', 'integer', 'string')`

**Índices:**
- `idx_plan_features_plan_version` ON (`plan_version_id`)
- `idx_plan_features_unique` UNIQUE ON (`plan_version_id`, `feature_key`)

---

### 2.9 features

Catálogo global de features configuráveis da plataforma.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `code` | `VARCHAR(100)` | NOT NULL | — | Código único da feature |
| `name` | `VARCHAR(255)` | NOT NULL | — | Nome legível |
| `type` | `VARCHAR(20)` | NOT NULL | — | Tipo de valor |
| `description` | `TEXT` | NULL | — | Descrição |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `UNIQUE`: `code`
- `CHECK`: `type IN ('boolean', 'integer', 'enum')`

**Índices:**
- `idx_features_code` UNIQUE ON (`code`)

---

### 2.10 tenant_feature_overrides

Override de feature para um tenant específico.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `tenant_id` | `UUID` | NOT NULL | — | FK para tenants |
| `feature_id` | `UUID` | NOT NULL | — | FK para features |
| `value` | `VARCHAR(255)` | NOT NULL | — | Valor do override |
| `reason` | `TEXT` | NOT NULL | — | Justificativa obrigatória |
| `expires_at` | `TIMESTAMP` | NULL | — | Expiração (null = permanente) |
| `created_by` | `UUID` | NOT NULL | — | FK para platform_users |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `FK`: `tenant_id` → `tenants(id)` ON DELETE CASCADE
- `FK`: `feature_id` → `features(id)` ON DELETE RESTRICT
- `FK`: `created_by` → `platform_users(id)` ON DELETE RESTRICT
- `UNIQUE`: (`tenant_id`, `feature_id`)

**Índices:**
- `idx_tfo_tenant_feature` UNIQUE ON (`tenant_id`, `feature_id`)
- `idx_tfo_expires_at` ON (`expires_at`) WHERE `expires_at IS NOT NULL`

---

### 2.11 subscriptions

Vínculo entre tenant e plano. Controla acesso ao sistema.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `tenant_id` | `UUID` | NOT NULL | — | FK para tenants |
| `plan_version_id` | `UUID` | NOT NULL | — | FK para plan_versions |
| `status` | `VARCHAR(20)` | NOT NULL | — | Estado da assinatura |
| `billing_cycle` | `VARCHAR(10)` | NOT NULL | — | Ciclo de cobrança |
| `current_period_start` | `TIMESTAMP` | NOT NULL | — | Início do período |
| `current_period_end` | `TIMESTAMP` | NOT NULL | — | Fim do período |
| `grace_period_end` | `TIMESTAMP` | NULL | — | Fim da carência |
| `canceled_at` | `TIMESTAMP` | NULL | — | Data do cancelamento |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `FK`: `tenant_id` → `tenants(id)` ON DELETE RESTRICT
- `FK`: `plan_version_id` → `plan_versions(id)` ON DELETE RESTRICT
- `CHECK`: `status IN ('trialing', 'active', 'past_due', 'grace_period', 'suspended', 'canceled', 'expired')`
- `CHECK`: `billing_cycle IN ('monthly', 'yearly')`
- `CHECK`: `current_period_end > current_period_start`

**Índices:**
- `idx_subscriptions_tenant` ON (`tenant_id`)
- `idx_subscriptions_tenant_status` ON (`tenant_id`, `status`)
- `idx_subscriptions_status` ON (`status`)

---

### 2.12 invoices

Faturas geradas por ciclo de assinatura.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `tenant_id` | `UUID` | NOT NULL | — | FK para tenants |
| `subscription_id` | `UUID` | NOT NULL | — | FK para subscriptions |
| `invoice_number` | `VARCHAR(50)` | NOT NULL | — | Número sequencial único |
| `status` | `VARCHAR(20)` | NOT NULL | `'draft'` | Estado da fatura |
| `currency` | `VARCHAR(3)` | NOT NULL | `'BRL'` | Moeda |
| `subtotal` | `DECIMAL(10,2)` | NOT NULL | — | Subtotal |
| `tax_amount` | `DECIMAL(10,2)` | NOT NULL | `0` | Impostos |
| `discount_amount` | `DECIMAL(10,2)` | NOT NULL | `0` | Descontos |
| `total` | `DECIMAL(10,2)` | NOT NULL | — | Total |
| `due_date` | `DATE` | NOT NULL | — | Data de vencimento |
| `paid_at` | `TIMESTAMP` | NULL | — | Data do pagamento |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `FK`: `tenant_id` → `tenants(id)` ON DELETE RESTRICT
- `FK`: `subscription_id` → `subscriptions(id)` ON DELETE RESTRICT
- `UNIQUE`: `invoice_number`
- `CHECK`: `status IN ('draft', 'pending', 'paid', 'overdue', 'canceled', 'refunded')`
- `CHECK`: `total >= 0`

**Índices:**
- `idx_invoices_tenant` ON (`tenant_id`)
- `idx_invoices_subscription` ON (`subscription_id`)
- `idx_invoices_number` UNIQUE ON (`invoice_number`)
- `idx_invoices_status` ON (`status`)
- `idx_invoices_due_date` ON (`due_date`)

---

### 2.13 invoice_items

Itens detalhados de cada fatura.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `invoice_id` | `UUID` | NOT NULL | — | FK para invoices |
| `description` | `VARCHAR(255)` | NOT NULL | — | Descrição do item |
| `quantity` | `INTEGER` | NOT NULL | `1` | Quantidade |
| `unit_price` | `DECIMAL(10,2)` | NOT NULL | — | Preço unitário |
| `total` | `DECIMAL(10,2)` | NOT NULL | — | Total do item |

**Constraints:**
- `PK`: `id`
- `FK`: `invoice_id` → `invoices(id)` ON DELETE CASCADE
- `CHECK`: `quantity > 0`
- `CHECK`: `unit_price >= 0`

**Índices:**
- `idx_invoice_items_invoice` ON (`invoice_id`)

---

### 2.14 payments

Pagamentos recebidos via gateway.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `invoice_id` | `UUID` | NOT NULL | — | FK para invoices |
| `gateway` | `VARCHAR(50)` | NOT NULL | — | Nome do gateway |
| `gateway_transaction_id` | `VARCHAR(255)` | NULL | — | ID da transação no gateway |
| `amount` | `DECIMAL(10,2)` | NOT NULL | — | Valor pago |
| `currency` | `VARCHAR(3)` | NOT NULL | `'BRL'` | Moeda |
| `status` | `VARCHAR(20)` | NOT NULL | `'pending'` | Estado do pagamento |
| `method` | `VARCHAR(50)` | NULL | — | Método de pagamento |
| `paid_at` | `TIMESTAMP` | NULL | — | Data efetiva do pagamento |
| `metadata` | `JSONB` | NULL | — | Dados adicionais do gateway |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `FK`: `invoice_id` → `invoices(id)` ON DELETE RESTRICT
- `CHECK`: `status IN ('pending', 'processing', 'succeeded', 'failed', 'refunded')`
- `CHECK`: `amount > 0`

**Índices:**
- `idx_payments_invoice` ON (`invoice_id`)
- `idx_payments_gateway_tx` ON (`gateway`, `gateway_transaction_id`)
- `idx_payments_status` ON (`status`)

---

### 2.15 dunning_policies

Políticas de tratamento de inadimplência.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `name` | `VARCHAR(100)` | NOT NULL | — | Nome da política |
| `max_retries` | `INTEGER` | NOT NULL | `3` | Máximo de tentativas |
| `retry_intervals` | `JSONB` | NOT NULL | — | Intervalos em dias (ex: [3,5,7]) |
| `suspend_after_days` | `INTEGER` | NOT NULL | `15` | Dias até suspensão |
| `cancel_after_days` | `INTEGER` | NOT NULL | `30` | Dias até cancelamento |
| `is_default` | `BOOLEAN` | NOT NULL | `false` | Política padrão |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `CHECK`: `max_retries >= 0`
- `CHECK`: `suspend_after_days > 0`
- `CHECK`: `cancel_after_days > suspend_after_days`

**Índices:**
- `idx_dunning_policies_default` ON (`is_default`) WHERE `is_default = true`

---

### 2.16 gateway_events

Eventos recebidos via webhooks do gateway de pagamento.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `tenant_id` | `UUID` | NULL | — | FK para tenants (se aplicável) |
| `gateway` | `VARCHAR(50)` | NOT NULL | — | Nome do gateway |
| `event_type` | `VARCHAR(100)` | NOT NULL | — | Tipo do evento |
| `payload` | `JSONB` | NOT NULL | — | Payload completo |
| `processed` | `BOOLEAN` | NOT NULL | `false` | Se já processado |
| `processed_at` | `TIMESTAMP` | NULL | — | Data de processamento |
| `idempotency_key` | `VARCHAR(255)` | NOT NULL | — | Chave de idempotência |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de recebimento |

**Constraints:**
- `PK`: `id`
- `FK`: `tenant_id` → `tenants(id)` ON DELETE SET NULL
- `UNIQUE`: `idempotency_key`

**Índices:**
- `idx_gateway_events_idempotency` UNIQUE ON (`idempotency_key`)
- `idx_gateway_events_tenant` ON (`tenant_id`)
- `idx_gateway_events_processed` ON (`processed`) WHERE `processed = false`
- `idx_gateway_events_created_at` ON (`created_at`)

---

### 2.17 platform_audit_logs

Logs de auditoria da plataforma. Tabela append-only.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `actor_id` | `UUID` | NULL | — | UUID do ator |
| `actor_type` | `VARCHAR(50)` | NOT NULL | — | Tipo do ator |
| `action` | `VARCHAR(100)` | NOT NULL | — | Ação realizada |
| `resource_type` | `VARCHAR(100)` | NOT NULL | — | Tipo do recurso |
| `resource_id` | `UUID` | NOT NULL | — | ID do recurso |
| `tenant_id` | `UUID` | NULL | — | Tenant afetado (se aplicável) |
| `changes` | `JSONB` | NULL | — | Mudanças realizadas |
| `ip_address` | `VARCHAR(45)` | NULL | — | IPv4 ou IPv6 |
| `user_agent` | `TEXT` | NULL | — | Header User-Agent |
| `correlation_id` | `UUID` | NOT NULL | — | ID de correlação |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Timestamp do evento |

**Constraints:**
- `PK`: `id`

**Índices:**
- `idx_pal_actor` ON (`actor_id`)
- `idx_pal_resource` ON (`resource_type`, `resource_id`)
- `idx_pal_tenant` ON (`tenant_id`)
- `idx_pal_correlation` ON (`correlation_id`)
- `idx_pal_created_at` ON (`created_at`)
- `idx_pal_action` ON (`action`)

**Particionamento:** Recomendado por mês em `created_at` para tabelas com alto volume.

---

## 3. Banco do Tenant (Isolado)

Cada tenant possui as tabelas abaixo em seu próprio database/schema.

### 3.1 tenant_users

Usuários que operam dentro do condomínio.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `email` | `VARCHAR(255)` | NOT NULL | — | E-mail único dentro do tenant |
| `password_hash` | `VARCHAR(255)` | NOT NULL | — | Hash bcrypt |
| `name` | `VARCHAR(255)` | NOT NULL | — | Nome completo |
| `phone` | `VARCHAR(20)` | NULL | — | Telefone |
| `role` | `VARCHAR(20)` | NOT NULL | — | Papel no condomínio |
| `status` | `VARCHAR(20)` | NOT NULL | `'invited'` | Estado da conta |
| `mfa_secret` | `VARCHAR(255)` | NULL | — | Secret TOTP (criptografado) |
| `mfa_enabled` | `BOOLEAN` | NOT NULL | `false` | MFA ativo |
| `last_login_at` | `TIMESTAMP` | NULL | — | Último login |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `UNIQUE`: `email`
- `CHECK`: `role IN ('sindico', 'administradora', 'condomino', 'funcionario')`
- `CHECK`: `status IN ('active', 'inactive', 'invited', 'blocked')`

**Índices:**
- `idx_tenant_users_email` UNIQUE ON (`email`)
- `idx_tenant_users_role` ON (`role`)
- `idx_tenant_users_status` ON (`status`)

---

### 3.2 blocks

Blocos de um condomínio vertical ou misto. Opcional para condomínios horizontais.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `name` | `VARCHAR(100)` | NOT NULL | — | Nome do bloco |
| `identifier` | `VARCHAR(20)` | NOT NULL | — | Identificador curto (ex: "A", "Torre 1") |
| `status` | `VARCHAR(20)` | NOT NULL | `'active'` | Estado |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `UNIQUE`: `identifier`
- `CHECK`: `status IN ('active', 'inactive')`

**Índices:**
- `idx_blocks_identifier` UNIQUE ON (`identifier`)

---

### 3.3 units

Unidades do condomínio (apartamentos, casas, salas comerciais).

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `block_id` | `UUID` | NULL | — | FK para blocks (null para condos horizontais) |
| `identifier` | `VARCHAR(50)` | NOT NULL | — | Identificador (ex: "101", "Casa 5") |
| `type` | `VARCHAR(20)` | NOT NULL | — | Tipo da unidade |
| `floor` | `INTEGER` | NULL | — | Andar (null para casas) |
| `status` | `VARCHAR(20)` | NOT NULL | `'active'` | Estado |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `FK`: `block_id` → `blocks(id)` ON DELETE RESTRICT
- `UNIQUE`: (`block_id`, `identifier`) — tratamento especial para block_id NULL
- `CHECK`: `type IN ('apartment', 'house', 'commercial', 'other')`
- `CHECK`: `status IN ('active', 'inactive')`

**Índices:**
- `idx_units_block` ON (`block_id`)
- `idx_units_identifier` ON (`identifier`)
- `idx_units_block_identifier` UNIQUE ON (`block_id`, `identifier`) WHERE `block_id IS NOT NULL`
- `idx_units_identifier_no_block` UNIQUE ON (`identifier`) WHERE `block_id IS NULL`

---

### 3.4 residents

Vínculo entre morador (tenant_user) e unidade.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `unit_id` | `UUID` | NOT NULL | — | FK para units |
| `tenant_user_id` | `UUID` | NOT NULL | — | FK para tenant_users |
| `role_in_unit` | `VARCHAR(20)` | NOT NULL | — | Papel na unidade |
| `is_primary` | `BOOLEAN` | NOT NULL | `false` | Responsável principal |
| `moved_in_at` | `DATE` | NOT NULL | — | Data de entrada |
| `moved_out_at` | `DATE` | NULL | — | Data de saída (null = ativo) |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `FK`: `unit_id` → `units(id)` ON DELETE RESTRICT
- `FK`: `tenant_user_id` → `tenant_users(id)` ON DELETE RESTRICT
- `CHECK`: `role_in_unit IN ('owner', 'tenant_resident', 'dependent')`
- `CHECK`: `moved_out_at IS NULL OR moved_out_at >= moved_in_at`

**Índices:**
- `idx_residents_unit` ON (`unit_id`)
- `idx_residents_user` ON (`tenant_user_id`)
- `idx_residents_active` ON (`unit_id`) WHERE `moved_out_at IS NULL`

---

### 3.5 spaces

Espaços comuns do condomínio.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `name` | `VARCHAR(255)` | NOT NULL | — | Nome do espaço |
| `description` | `TEXT` | NULL | — | Descrição |
| `type` | `VARCHAR(30)` | NOT NULL | — | Tipo do espaço |
| `status` | `VARCHAR(20)` | NOT NULL | `'active'` | Estado |
| `capacity` | `INTEGER` | NOT NULL | — | Capacidade máxima |
| `requires_approval` | `BOOLEAN` | NOT NULL | `false` | Requer aprovação |
| `max_duration_hours` | `INTEGER` | NULL | — | Duração máxima em horas |
| `max_advance_days` | `INTEGER` | NOT NULL | `30` | Antecedência máxima em dias |
| `min_advance_hours` | `INTEGER` | NOT NULL | `24` | Antecedência mínima em horas |
| `cancellation_deadline_hours` | `INTEGER` | NOT NULL | `24` | Prazo de cancelamento |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `CHECK`: `type IN ('party_hall', 'bbq', 'pool', 'gym', 'playground', 'sports_court', 'meeting_room', 'other')`
- `CHECK`: `status IN ('active', 'inactive', 'maintenance')`
- `CHECK`: `capacity > 0`
- `CHECK`: `max_advance_days > 0`
- `CHECK`: `min_advance_hours >= 0`
- `CHECK`: `cancellation_deadline_hours >= 0`

**Índices:**
- `idx_spaces_status` ON (`status`)
- `idx_spaces_type` ON (`type`)

---

### 3.6 space_availabilities

Horários de disponibilidade por dia da semana.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `space_id` | `UUID` | NOT NULL | — | FK para spaces |
| `day_of_week` | `INTEGER` | NOT NULL | — | 0=Dom, 1=Seg...6=Sáb |
| `start_time` | `TIME` | NOT NULL | — | Hora de início |
| `end_time` | `TIME` | NOT NULL | — | Hora de fim |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `FK`: `space_id` → `spaces(id)` ON DELETE CASCADE
- `UNIQUE`: (`space_id`, `day_of_week`, `start_time`)
- `CHECK`: `day_of_week BETWEEN 0 AND 6`
- `CHECK`: `end_time > start_time`

**Índices:**
- `idx_sa_space_day` ON (`space_id`, `day_of_week`)

---

### 3.7 space_blocks

Bloqueios temporários de espaços.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `space_id` | `UUID` | NOT NULL | — | FK para spaces |
| `reason` | `VARCHAR(255)` | NOT NULL | — | Motivo do bloqueio |
| `start_datetime` | `TIMESTAMP` | NOT NULL | — | Início do bloqueio |
| `end_datetime` | `TIMESTAMP` | NOT NULL | — | Fim do bloqueio |
| `blocked_by` | `UUID` | NOT NULL | — | FK para tenant_users |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |

**Constraints:**
- `PK`: `id`
- `FK`: `space_id` → `spaces(id)` ON DELETE CASCADE
- `FK`: `blocked_by` → `tenant_users(id)` ON DELETE RESTRICT
- `CHECK`: `end_datetime > start_datetime`

**Índices:**
- `idx_space_blocks_space` ON (`space_id`)
- `idx_space_blocks_period` ON (`space_id`, `start_datetime`, `end_datetime`)

---

### 3.8 space_rules

Regras específicas de cada espaço.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `space_id` | `UUID` | NOT NULL | — | FK para spaces |
| `rule_key` | `VARCHAR(100)` | NOT NULL | — | Chave da regra |
| `rule_value` | `VARCHAR(255)` | NOT NULL | — | Valor da regra |
| `description` | `TEXT` | NULL | — | Descrição da regra |

**Constraints:**
- `PK`: `id`
- `FK`: `space_id` → `spaces(id)` ON DELETE CASCADE
- `UNIQUE`: (`space_id`, `rule_key`)

**Índices:**
- `idx_space_rules_space` ON (`space_id`)

---

### 3.9 reservations

**Aggregate Root** do domínio de reservas.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `space_id` | `UUID` | NOT NULL | — | FK para spaces |
| `unit_id` | `UUID` | NOT NULL | — | FK para units |
| `tenant_user_id` | `UUID` | NOT NULL | — | FK para tenant_users |
| `status` | `VARCHAR(20)` | NOT NULL | — | Estado da reserva |
| `start_datetime` | `TIMESTAMP` | NOT NULL | — | Início da reserva |
| `end_datetime` | `TIMESTAMP` | NOT NULL | — | Fim da reserva |
| `expected_guests` | `INTEGER` | NOT NULL | `0` | Convidados esperados |
| `notes` | `TEXT` | NULL | — | Observações |
| `approved_by` | `UUID` | NULL | — | FK para tenant_users |
| `approved_at` | `TIMESTAMP` | NULL | — | Data da aprovação |
| `canceled_at` | `TIMESTAMP` | NULL | — | Data do cancelamento |
| `cancellation_reason` | `TEXT` | NULL | — | Motivo do cancelamento |
| `completed_at` | `TIMESTAMP` | NULL | — | Data de conclusão |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `FK`: `space_id` → `spaces(id)` ON DELETE RESTRICT
- `FK`: `unit_id` → `units(id)` ON DELETE RESTRICT
- `FK`: `tenant_user_id` → `tenant_users(id)` ON DELETE RESTRICT
- `FK`: `approved_by` → `tenant_users(id)` ON DELETE SET NULL
- `CHECK`: `status IN ('pending_approval', 'confirmed', 'in_use', 'completed', 'canceled', 'rejected', 'no_show')`
- `CHECK`: `end_datetime > start_datetime`
- `CHECK`: `expected_guests >= 0`

**Índices:**
- `idx_reservations_space_period` ON (`space_id`, `start_datetime`, `end_datetime`) — Detecção de conflitos
- `idx_reservations_unit` ON (`unit_id`)
- `idx_reservations_user` ON (`tenant_user_id`)
- `idx_reservations_status` ON (`status`)
- `idx_reservations_space_status` ON (`space_id`, `status`)
- `idx_reservations_start` ON (`start_datetime`)

**Exclusion Constraint (prevenção de conflitos):**
```sql
ALTER TABLE reservations
ADD CONSTRAINT no_overlapping_reservations
EXCLUDE USING gist (
    space_id WITH =,
    tsrange(start_datetime, end_datetime) WITH &&
) WHERE (status NOT IN ('canceled', 'rejected', 'no_show'));
```

---

### 3.10 guests

Convidados vinculados a reservas.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `reservation_id` | `UUID` | NOT NULL | — | FK para reservations |
| `name` | `VARCHAR(255)` | NOT NULL | — | Nome do convidado |
| `document` | `VARCHAR(20)` | NULL | — | Número do documento |
| `document_type` | `VARCHAR(20)` | NULL | — | Tipo do documento |
| `phone` | `VARCHAR(20)` | NULL | — | Telefone |
| `checked_in_at` | `TIMESTAMP` | NULL | — | Check-in |
| `checked_out_at` | `TIMESTAMP` | NULL | — | Check-out |
| `checked_in_by` | `UUID` | NULL | — | FK para tenant_users |
| `checked_out_by` | `UUID` | NULL | — | FK para tenant_users |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `FK`: `reservation_id` → `reservations(id)` ON DELETE CASCADE
- `FK`: `checked_in_by` → `tenant_users(id)` ON DELETE SET NULL
- `FK`: `checked_out_by` → `tenant_users(id)` ON DELETE SET NULL
- `CHECK`: `document_type IN ('cpf', 'rg', 'cnh', 'passport', 'other')` (quando não null)

**Índices:**
- `idx_guests_reservation` ON (`reservation_id`)
- `idx_guests_document` ON (`document`) WHERE `document IS NOT NULL`

---

### 3.11 service_providers

Prestadores de serviço vinculados a reservas.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `reservation_id` | `UUID` | NOT NULL | — | FK para reservations |
| `name` | `VARCHAR(255)` | NOT NULL | — | Nome |
| `company` | `VARCHAR(255)` | NULL | — | Empresa |
| `document` | `VARCHAR(20)` | NOT NULL | — | CPF ou CNPJ |
| `document_type` | `VARCHAR(10)` | NOT NULL | — | Tipo do documento |
| `phone` | `VARCHAR(20)` | NULL | — | Telefone |
| `service_description` | `TEXT` | NOT NULL | — | Descrição do serviço |
| `checked_in_at` | `TIMESTAMP` | NULL | — | Check-in |
| `checked_out_at` | `TIMESTAMP` | NULL | — | Check-out |
| `checked_in_by` | `UUID` | NULL | — | FK para tenant_users |
| `checked_out_by` | `UUID` | NULL | — | FK para tenant_users |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `FK`: `reservation_id` → `reservations(id)` ON DELETE CASCADE
- `FK`: `checked_in_by` → `tenant_users(id)` ON DELETE SET NULL
- `FK`: `checked_out_by` → `tenant_users(id)` ON DELETE SET NULL
- `CHECK`: `document_type IN ('cpf', 'cnpj')`

**Índices:**
- `idx_service_providers_reservation` ON (`reservation_id`)
- `idx_service_providers_document` ON (`document`)

---

### 3.12 condominium_rules

Regulamento interno configurável.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `title` | `VARCHAR(255)` | NOT NULL | — | Título da regra |
| `description` | `TEXT` | NOT NULL | — | Descrição detalhada |
| `category` | `VARCHAR(100)` | NOT NULL | — | Categoria |
| `is_active` | `BOOLEAN` | NOT NULL | `true` | Se está ativa |
| `created_by` | `UUID` | NOT NULL | — | FK para tenant_users |
| `document_section_id` | `UUID` | NULL | — | FK para document_sections (se regra derivada de documento legal) |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `FK`: `created_by` → `tenant_users(id)` ON DELETE RESTRICT
- `FK`: `document_section_id` → `document_sections(id)` ON DELETE SET NULL

**Índices:**
- `idx_condo_rules_category` ON (`category`)
- `idx_condo_rules_active` ON (`is_active`)
- `idx_condo_rules_document_section` ON (`document_section_id`) WHERE `document_section_id IS NOT NULL`

---

### 3.13 condominium_documents

Documentos legais do condomínio (Convenção, Regimento Interno, Atas de Assembleia). Suporta versionamento — apenas um documento `active` por `type`.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `type` | `VARCHAR(30)` | NOT NULL | — | Tipo do documento |
| `title` | `VARCHAR(255)` | NOT NULL | — | Título do documento |
| `version` | `INTEGER` | NOT NULL | `1` | Número da versão |
| `status` | `VARCHAR(20)` | NOT NULL | `'draft'` | Estado do documento |
| `full_text` | `TEXT` | NOT NULL | — | Conteúdo textual completo |
| `file_path` | `VARCHAR(500)` | NULL | — | Caminho do arquivo original (PDF) |
| `file_hash` | `VARCHAR(64)` | NULL | — | Hash SHA-256 do arquivo para integridade |
| `approved_at` | `TIMESTAMP` | NULL | — | Data de aprovação em assembleia |
| `approved_in` | `VARCHAR(255)` | NULL | — | Referência da assembleia que aprovou |
| `created_by` | `UUID` | NOT NULL | — | FK para tenant_users |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `FK`: `created_by` → `tenant_users(id)` ON DELETE RESTRICT
- `CHECK`: `type IN ('convencao', 'regimento_interno', 'ata_assembleia', 'other')`
- `CHECK`: `status IN ('draft', 'active', 'archived')`
- `UNIQUE PARTIAL`: `(type)` WHERE `status = 'active'` — Apenas um documento ativo por tipo

**Índices:**
- `idx_condo_docs_type` ON (`type`)
- `idx_condo_docs_status` ON (`status`)
- `idx_condo_docs_type_active` UNIQUE ON (`type`) WHERE `status = 'active'`

---

### 3.14 document_sections

Seções hierárquicas de documentos legais (artigos, capítulos, parágrafos). Cada seção é a unidade mínima para embedding de IA e consulta granular.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `document_id` | `UUID` | NOT NULL | — | FK para condominium_documents |
| `parent_section_id` | `UUID` | NULL | — | FK auto-referência (seção pai) |
| `section_number` | `VARCHAR(50)` | NOT NULL | — | Numeração (ex: "Art. 15", "Cap. III", "§ 2º") |
| `title` | `VARCHAR(255)` | NULL | — | Título da seção (quando aplicável) |
| `content` | `TEXT` | NOT NULL | — | Conteúdo textual da seção |
| `order_index` | `INTEGER` | NOT NULL | — | Ordem de exibição dentro do documento |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `FK`: `document_id` → `condominium_documents(id)` ON DELETE CASCADE
- `FK`: `parent_section_id` → `document_sections(id)` ON DELETE SET NULL
- `UNIQUE`: `(document_id, section_number)` — Numeração única por documento

**Índices:**
- `idx_doc_sections_document` ON (`document_id`)
- `idx_doc_sections_parent` ON (`parent_section_id`) WHERE `parent_section_id IS NOT NULL`
- `idx_doc_sections_order` ON (`document_id`, `order_index`)

**Integração com IA:**
- Cada `document_section` pode ter embeddings na tabela `ai_embeddings` via `source_type = 'document_section'` e `source_id = document_sections.id`
- Permite busca semântica por artigos/parágrafos específicos de documentos legais

---

### 3.15 violations

Infrações registradas contra unidades/moradores.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `unit_id` | `UUID` | NOT NULL | — | FK para units |
| `tenant_user_id` | `UUID` | NULL | — | FK para tenant_users (null para unidade-nível) |
| `reservation_id` | `UUID` | NULL | — | FK para reservations (se vinculada) |
| `rule_id` | `UUID` | NULL | — | FK para condominium_rules |
| `type` | `VARCHAR(30)` | NOT NULL | — | Tipo da infração |
| `severity` | `VARCHAR(20)` | NOT NULL | — | Severidade |
| `description` | `TEXT` | NOT NULL | — | Descrição |
| `status` | `VARCHAR(20)` | NOT NULL | `'open'` | Estado |
| `is_automatic` | `BOOLEAN` | NOT NULL | `false` | Se foi gerada automaticamente |
| `created_by` | `UUID` | NULL | — | FK para tenant_users (null para automáticas) |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `FK`: `unit_id` → `units(id)` ON DELETE RESTRICT
- `FK`: `tenant_user_id` → `tenant_users(id)` ON DELETE SET NULL
- `FK`: `reservation_id` → `reservations(id)` ON DELETE SET NULL
- `FK`: `rule_id` → `condominium_rules(id)` ON DELETE SET NULL
- `FK`: `created_by` → `tenant_users(id)` ON DELETE SET NULL
- `CHECK`: `type IN ('no_show', 'late_cancellation', 'capacity_exceeded', 'noise_complaint', 'damage', 'rule_violation', 'other')`
- `CHECK`: `severity IN ('low', 'medium', 'high', 'critical')`
- `CHECK`: `status IN ('open', 'contested', 'upheld', 'revoked')`

**Índices:**
- `idx_violations_unit` ON (`unit_id`)
- `idx_violations_user` ON (`tenant_user_id`)
- `idx_violations_reservation` ON (`reservation_id`)
- `idx_violations_status` ON (`status`)
- `idx_violations_type` ON (`type`)

---

### 3.16 violation_contestations

Contestações de infrações pelos moradores.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `violation_id` | `UUID` | NOT NULL | — | FK para violations |
| `tenant_user_id` | `UUID` | NOT NULL | — | FK para tenant_users |
| `reason` | `TEXT` | NOT NULL | — | Motivo da contestação |
| `status` | `VARCHAR(20)` | NOT NULL | `'pending'` | Estado |
| `response` | `TEXT` | NULL | — | Resposta da administração |
| `responded_by` | `UUID` | NULL | — | FK para tenant_users |
| `responded_at` | `TIMESTAMP` | NULL | — | Data da resposta |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |

**Constraints:**
- `PK`: `id`
- `FK`: `violation_id` → `violations(id)` ON DELETE RESTRICT
- `FK`: `tenant_user_id` → `tenant_users(id)` ON DELETE RESTRICT
- `FK`: `responded_by` → `tenant_users(id)` ON DELETE SET NULL
- `CHECK`: `status IN ('pending', 'accepted', 'rejected')`

**Índices:**
- `idx_vc_violation` ON (`violation_id`)
- `idx_vc_status` ON (`status`)

---

### 3.17 penalties

Penalidades aplicadas após infrações.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `violation_id` | `UUID` | NOT NULL | — | FK para violations |
| `unit_id` | `UUID` | NOT NULL | — | FK para units |
| `type` | `VARCHAR(20)` | NOT NULL | — | Tipo da penalidade |
| `starts_at` | `TIMESTAMP` | NOT NULL | — | Início da penalidade |
| `ends_at` | `TIMESTAMP` | NULL | — | Fim (null para permanente) |
| `status` | `VARCHAR(20)` | NOT NULL | `'active'` | Estado |
| `revoked_at` | `TIMESTAMP` | NULL | — | Data da revogação |
| `revoked_by` | `UUID` | NULL | — | FK para tenant_users |
| `revoked_reason` | `TEXT` | NULL | — | Motivo da revogação |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |

**Constraints:**
- `PK`: `id`
- `FK`: `violation_id` → `violations(id)` ON DELETE RESTRICT
- `FK`: `unit_id` → `units(id)` ON DELETE RESTRICT
- `FK`: `revoked_by` → `tenant_users(id)` ON DELETE SET NULL
- `CHECK`: `type IN ('warning', 'temporary_block', 'permanent_block')`
- `CHECK`: `status IN ('active', 'expired', 'revoked')`

**Índices:**
- `idx_penalties_unit` ON (`unit_id`)
- `idx_penalties_violation` ON (`violation_id`)
- `idx_penalties_active` ON (`unit_id`, `status`) WHERE `status = 'active'`

---

### 3.18 penalty_policies

Políticas automáticas de penalidade.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `violation_type` | `VARCHAR(30)` | NOT NULL | — | Tipo de violação |
| `occurrence_threshold` | `INTEGER` | NOT NULL | — | Número de ocorrências para acionar |
| `penalty_type` | `VARCHAR(20)` | NOT NULL | — | Tipo de penalidade a aplicar |
| `block_days` | `INTEGER` | NULL | — | Dias de bloqueio (para temporary_block) |
| `is_active` | `BOOLEAN` | NOT NULL | `true` | Se está ativa |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `CHECK`: `violation_type IN ('no_show', 'late_cancellation', 'capacity_exceeded', 'noise_complaint', 'damage', 'rule_violation', 'other')`
- `CHECK`: `penalty_type IN ('warning', 'temporary_block', 'permanent_block')`
- `CHECK`: `occurrence_threshold > 0`
- `CHECK`: `block_days IS NULL OR block_days > 0`

**Índices:**
- `idx_pp_violation_type` ON (`violation_type`)
- `idx_pp_active` ON (`is_active`)

---

### 3.19 announcements

Avisos publicados pelo síndico.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `title` | `VARCHAR(255)` | NOT NULL | — | Título |
| `body` | `TEXT` | NOT NULL | — | Conteúdo |
| `priority` | `VARCHAR(10)` | NOT NULL | `'normal'` | Prioridade |
| `audience_type` | `VARCHAR(10)` | NOT NULL | — | Escopo da audiência |
| `audience_ids` | `JSONB` | NULL | — | IDs de blocos/unidades quando escopo limitado |
| `published_by` | `UUID` | NOT NULL | — | FK para tenant_users |
| `published_at` | `TIMESTAMP` | NOT NULL | — | Data de publicação |
| `expires_at` | `TIMESTAMP` | NULL | — | Data de expiração |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `FK`: `published_by` → `tenant_users(id)` ON DELETE RESTRICT
- `CHECK`: `priority IN ('low', 'normal', 'high', 'urgent')`
- `CHECK`: `audience_type IN ('all', 'block', 'units')`

**Índices:**
- `idx_announcements_published_at` ON (`published_at`)
- `idx_announcements_priority` ON (`priority`)
- `idx_announcements_audience` ON (`audience_type`)

---

### 3.20 announcement_reads

Registro de confirmação de leitura de avisos.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `announcement_id` | `UUID` | NOT NULL | — | FK para announcements |
| `tenant_user_id` | `UUID` | NOT NULL | — | FK para tenant_users |
| `read_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data da leitura |

**Constraints:**
- `PK`: `id`
- `FK`: `announcement_id` → `announcements(id)` ON DELETE CASCADE
- `FK`: `tenant_user_id` → `tenant_users(id)` ON DELETE CASCADE
- `UNIQUE`: (`announcement_id`, `tenant_user_id`)

**Índices:**
- `idx_ar_announcement_user` UNIQUE ON (`announcement_id`, `tenant_user_id`)

---

### 3.21 support_requests

Solicitações de suporte dos moradores.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `tenant_user_id` | `UUID` | NOT NULL | — | FK para tenant_users |
| `subject` | `VARCHAR(255)` | NOT NULL | — | Assunto |
| `category` | `VARCHAR(100)` | NOT NULL | — | Categoria |
| `status` | `VARCHAR(20)` | NOT NULL | `'open'` | Estado |
| `priority` | `VARCHAR(10)` | NOT NULL | `'normal'` | Prioridade |
| `closed_at` | `TIMESTAMP` | NULL | — | Data de fechamento |
| `closed_reason` | `VARCHAR(20)` | NULL | — | Motivo do fechamento |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `updated_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Última atualização |

**Constraints:**
- `PK`: `id`
- `FK`: `tenant_user_id` → `tenant_users(id)` ON DELETE RESTRICT
- `CHECK`: `status IN ('open', 'in_progress', 'resolved', 'closed')`
- `CHECK`: `priority IN ('low', 'normal', 'high')`
- `CHECK`: `closed_reason IN ('resolved', 'auto_closed', 'admin_closed')` (quando não null)

**Índices:**
- `idx_sr_user` ON (`tenant_user_id`)
- `idx_sr_status` ON (`status`)
- `idx_sr_created_at` ON (`created_at`)

---

### 3.22 support_messages

Mensagens em threads de solicitações de suporte.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `support_request_id` | `UUID` | NOT NULL | — | FK para support_requests |
| `sender_id` | `UUID` | NOT NULL | — | FK para tenant_users |
| `body` | `TEXT` | NOT NULL | — | Conteúdo da mensagem |
| `is_internal` | `BOOLEAN` | NOT NULL | `false` | Visível apenas para staff |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |

**Constraints:**
- `PK`: `id`
- `FK`: `support_request_id` → `support_requests(id)` ON DELETE CASCADE
- `FK`: `sender_id` → `tenant_users(id)` ON DELETE RESTRICT

**Índices:**
- `idx_sm_request` ON (`support_request_id`)
- `idx_sm_created_at` ON (`support_request_id`, `created_at`)

---

### 3.23 tenant_audit_logs

Logs de auditoria do tenant. Tabela append-only.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `actor_id` | `UUID` | NULL | — | UUID do ator |
| `actor_type` | `VARCHAR(50)` | NOT NULL | — | Tipo do ator |
| `action` | `VARCHAR(100)` | NOT NULL | — | Ação realizada |
| `resource_type` | `VARCHAR(100)` | NOT NULL | — | Tipo do recurso |
| `resource_id` | `UUID` | NOT NULL | — | ID do recurso |
| `changes` | `JSONB` | NULL | — | Mudanças realizadas |
| `ip_address` | `VARCHAR(45)` | NULL | — | IP do ator |
| `user_agent` | `TEXT` | NULL | — | User-Agent |
| `correlation_id` | `UUID` | NOT NULL | — | ID de correlação |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Timestamp |

**Constraints:**
- `PK`: `id`

**Índices:**
- `idx_tal_actor` ON (`actor_id`)
- `idx_tal_resource` ON (`resource_type`, `resource_id`)
- `idx_tal_correlation` ON (`correlation_id`)
- `idx_tal_created_at` ON (`created_at`)
- `idx_tal_action` ON (`action`)

---

### 3.24 ai_embeddings

Embeddings vetoriais para funcionalidades de IA (requer pgvector). Dimensão do vetor é configurável via `AI_EMBEDDING_DIMENSIONS` para suportar estratégia híbrida local+cloud. Ver `docs/ai/ai-provider-strategy.md` seção 8.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `source_type` | `VARCHAR(100)` | NOT NULL | — | Tipo da origem (ex: "condominium_rule", "announcement") |
| `source_id` | `UUID` | NOT NULL | — | ID do registro de origem |
| `chunk_index` | `INT` | NOT NULL | `0` | Posição do chunk no documento |
| `content_text` | `TEXT` | NOT NULL | — | Texto original do chunk (para re-indexação) |
| `embedding` | `VECTOR` | NOT NULL | — | Vetor de embedding (dimensão configurável: 768, 1024, 1536...) |
| `model_version` | `VARCHAR(50)` | NOT NULL | — | Versão do modelo que gerou o embedding |
| `content_hash` | `VARCHAR(64)` | NOT NULL | — | SHA-256 do conteúdo original |
| `metadata` | `JSONB` | NULL | — | Metadados adicionais (section, author, date, data_classification) |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |
| `expires_at` | `TIMESTAMP` | NULL | — | Expiração para dados com retenção definida |

**Constraints:**
- `PK`: `id`
- `UNIQUE`: (`source_type`, `source_id`, `chunk_index`, `model_version`)

**Índices:**
- `idx_ai_embeddings_source` ON (`source_type`, `source_id`)
- `idx_ai_embeddings_vector` USING ivfflat (`embedding` vector_cosine_ops) WITH (lists = 100)
- `idx_ai_embeddings_dedupe` UNIQUE ON (`content_hash`, `model_version`)

---

### 3.25 ai_usage_logs

Logs de uso das funcionalidades de IA.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `tenant_user_id` | `UUID` | NULL | — | FK para tenant_users |
| `action` | `VARCHAR(100)` | NOT NULL | — | Ação executada |
| `model` | `VARCHAR(100)` | NOT NULL | — | Modelo usado |
| `tokens_input` | `INTEGER` | NOT NULL | — | Tokens de entrada |
| `tokens_output` | `INTEGER` | NOT NULL | — | Tokens de saída |
| `latency_ms` | `INTEGER` | NOT NULL | — | Latência em ms |
| `metadata` | `JSONB` | NULL | — | Dados adicionais |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |

**Constraints:**
- `PK`: `id`
- `FK`: `tenant_user_id` → `tenant_users(id)` ON DELETE SET NULL

**Índices:**
- `idx_ai_usage_user` ON (`tenant_user_id`)
- `idx_ai_usage_created_at` ON (`created_at`)

---

### 3.26 ai_action_logs

Ações propostas/executadas pela IA.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `tenant_user_id` | `UUID` | NOT NULL | — | FK para tenant_users |
| `tool_name` | `VARCHAR(100)` | NOT NULL | — | Nome da tool usada |
| `input_data` | `JSONB` | NOT NULL | — | Dados de entrada |
| `output_data` | `JSONB` | NULL | — | Dados de saída |
| `status` | `VARCHAR(20)` | NOT NULL | — | Estado da ação |
| `confirmed_by` | `UUID` | NULL | — | FK para tenant_users |
| `executed_at` | `TIMESTAMP` | NULL | — | Data de execução |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |

**Constraints:**
- `PK`: `id`
- `FK`: `tenant_user_id` → `tenant_users(id)` ON DELETE RESTRICT
- `FK`: `confirmed_by` → `tenant_users(id)` ON DELETE SET NULL
- `CHECK`: `status IN ('proposed', 'confirmed', 'rejected', 'executed', 'failed')`

**Índices:**
- `idx_ai_actions_user` ON (`tenant_user_id`)
- `idx_ai_actions_status` ON (`status`)

---

### 3.27 idempotency_keys

Chaves de idempotência para operações críticas.

| Coluna | Tipo | Nullable | Default | Descrição |
|--------|------|----------|---------|-----------|
| `id` | `UUID` | NOT NULL | `gen_random_uuid()` | Identificador único |
| `key` | `VARCHAR(255)` | NOT NULL | — | Chave única |
| `response` | `JSONB` | NOT NULL | — | Resposta armazenada |
| `status_code` | `INTEGER` | NOT NULL | — | Status HTTP da resposta |
| `expires_at` | `TIMESTAMP` | NOT NULL | — | Expiração da chave |
| `created_at` | `TIMESTAMP` | NOT NULL | `NOW()` | Data de criação |

**Constraints:**
- `PK`: `id`
- `UNIQUE`: `key`

**Índices:**
- `idx_idempotency_key` UNIQUE ON (`key`)
- `idx_idempotency_expires` ON (`expires_at`)

---

## 4. Diagrama de Relacionamentos

### 4.1 Banco da Plataforma

```
┌──────────┐     ┌──────────────┐     ┌───────────────┐
│  plans   │────<│ plan_versions│────<│ plan_features  │
└──────────┘     └──────┬───────┘     └───────────────┘
                        │
                        │
┌──────────┐     ┌──────┴───────┐     ┌───────────────┐
│ tenants  │────<│ subscriptions│────<│   invoices     │
└────┬─────┘     └──────────────┘     └──────┬────────┘
     │                                       │
     │           ┌──────────────┐     ┌──────┴────────┐
     ├──────────<│ tenant_admin │     │ invoice_items  │
     │           │  _actions    │     └───────────────┘
     │           └──────────────┘
     │                                ┌───────────────┐
     ├──────────<│ tenant_feature    │←────│  features     │
     │           │  _overrides       │     └───────────────┘
     │           └──────────────────┘
     │
     ├──────────<│ gateway_events    │
     │           └──────────────────┘
     │
     └──────────<│ platform_audit    │
                 │  _logs            │
                 └──────────────────┘

┌──────────────┐
│platform_users│────<│ tenant_admin_actions │
└──────────────┘     │ tenant_feature_overrides │
                     │ platform_audit_logs │

┌──────────────┐     ┌───────────────┐
│   invoices   │────<│   payments     │
└──────────────┘     └───────────────┘

┌──────────────┐
│dunning_policy│  (referenciado por lógica de negócio, sem FK direta)
└──────────────┘
```

### 4.2 Banco do Tenant

```
┌──────────┐     ┌──────────┐     ┌──────────────┐
│  blocks  │────<│  units   │────<│  residents   │────>│ tenant_users │
└──────────┘     └────┬─────┘     └──────────────┘     └──────┬───────┘
                      │                                        │
                      │           ┌──────────────┐             │
                      ├──────────<│ reservations │─────────────┤
                      │           └──────┬───────┘             │
                      │                  │                     │
                      │           ┌──────┴───────┐             │
                      │           │    guests    │             │
                      │           └──────────────┘             │
                      │           ┌──────────────┐             │
                      │           │service_provs │             │
                      │           └──────────────┘             │
                      │                                        │
                      │           ┌──────────────┐             │
                      ├──────────<│  violations  │─────────────┤
                      │           └──────┬───────┘             │
                      │                  │                     │
                      │           ┌──────┴───────┐             │
                      │           │ contestations│             │
                      │           └──────────────┘             │
                      │           ┌──────────────┐             │
                      ├──────────<│  penalties   │             │
                      │           └──────────────┘             │
                                                               │
┌──────────┐     ┌──────────────────┐                          │
│  spaces  │────<│space_availabilit.│                          │
└────┬─────┘     └──────────────────┘                          │
     │           ┌──────────────────┐                          │
     ├──────────<│  space_blocks    │──────────────────────────┤
     │           └──────────────────┘                          │
     │           ┌──────────────────┐                          │
     ├──────────<│  space_rules     │                          │
     │           └──────────────────┘                          │
     │                                                         │
     └──────────<│   reservations   │──────────────────────────┘

┌──────────────┐     ┌──────────────────┐
│announcements │────<│announcement_reads│
└──────────────┘     └──────────────────┘

┌──────────────────┐     ┌──────────────────┐
│support_requests  │────<│support_messages  │
└──────────────────┘     └──────────────────┘

┌────────────────────┐     ┌──────────────────┐
│condo_documents     │────<│document_sections │──┐ (self-ref: parent)
└────────────────────┘     └──────────────────┘  │
                                    │             │
                           condominium_rules ─────┘ (optional FK)
                                    │
                           ai_embeddings (source_type='document_section')

┌──────────────┐  ┌──────────────┐  ┌──────────────┐
│ai_embeddings │  │ai_usage_logs │  │ai_action_logs│
└──────────────┘  └──────────────┘  └──────────────┘

┌──────────────────┐  ┌──────────────────┐
│tenant_audit_logs │  │idempotency_keys  │
└──────────────────┘  └──────────────────┘
```

---

## 5. Estratégia de Indexação

### 5.1 Princípios

1. **Todo FK tem índice** — Essencial para JOINs performáticos
2. **Unique constraints automáticos** — PostgreSQL cria índice automaticamente
3. **Índices compostos** — Para queries frequentes com múltiplas colunas
4. **Índices parciais** — Para filtrar apenas subconjuntos relevantes
5. **Exclusion constraints** — Para prevenção de conflitos de reserva

### 5.2 Queries Críticas e Índices Correspondentes

| Query | Índice | Tipo |
|-------|--------|------|
| Detecção de conflitos de reserva | `idx_reservations_space_period` + exclusion constraint | GiST |
| Busca de penalidades ativas por unidade | `idx_penalties_active` (partial) | B-tree |
| Busca de overrides de features ativos | `idx_tfo_expires_at` (partial) | B-tree |
| Reservas por status e espaço | `idx_reservations_space_status` | B-tree |
| Audit logs por correlação | `idx_tal_correlation` | B-tree |
| Busca semântica por embedding | `idx_ai_embeddings_vector` | IVFFlat |
| Gateway events não processados | `idx_gateway_events_processed` (partial) | B-tree |
| Moradores ativos por unidade | `idx_residents_active` (partial) | B-tree |

### 5.3 pgvector — Índice de Embeddings

A dimensão do vetor é configurável para suportar estratégia híbrida (cloud: 1536, local: 768/1024). Ver `docs/ai/ai-provider-strategy.md`.

```sql
-- Criar extensão
CREATE EXTENSION IF NOT EXISTS vector;

-- Índice IVFFlat (recomendado para até 1M registros)
-- Nota: funciona com vetores de qualquer dimensão
CREATE INDEX idx_ai_embeddings_vector
ON ai_embeddings USING ivfflat (embedding vector_cosine_ops)
WITH (lists = 100);

-- Para volumes maiores, considerar HNSW:
-- CREATE INDEX idx_ai_embeddings_vector
-- ON ai_embeddings USING hnsw (embedding vector_cosine_ops)
-- WITH (m = 16, ef_construction = 64);
```

---

## 6. Particionamento e Performance

### 6.1 Tabelas Candidatas a Particionamento

| Tabela | Estratégia | Coluna | Motivo |
|--------|-----------|--------|--------|
| `platform_audit_logs` | Range por mês | `created_at` | Alto volume, queries por período |
| `tenant_audit_logs` | Range por mês | `created_at` | Alto volume, queries por período |
| `gateway_events` | Range por mês | `created_at` | Alto volume de webhooks |
| `ai_usage_logs` | Range por mês | `created_at` | Logs de uso de IA |

### 6.2 Connection Pooling

```
Aplicação → PgBouncer → PostgreSQL
           (pool por tenant)

Configuração recomendada:
- Mode: transaction
- Max connections por pool: 20
- Default pool size: 5
- Reserve pool: 2
```

### 6.3 Otimizações de Query

- **Paginação:** Cursor-based (não OFFSET) para performance consistente
- **SELECT específico:** Nunca `SELECT *` — listar colunas necessárias
- **Eager loading:** Usar `WITH` (CTEs) ou JOINs para evitar N+1
- **Materialized views:** Considerar para dashboards e relatórios

---

## 7. Backup e Recovery

### 7.1 Estratégia por Tenant

| Componente | Frequência | Retenção | Ferramenta |
|-----------|-----------|----------|-----------|
| Platform DB | Diário (full) + WAL contínuo | 30 dias | pg_basebackup + WAL-G |
| Tenant DBs | Diário (full) + WAL contínuo | 30 dias | pg_dump + WAL-G |
| Point-in-time | Contínuo via WAL | 7 dias | pg_pitr |

### 7.2 Restauração

- **Tenant individual:** `pg_restore` do dump específico
- **Point-in-time:** WAL replay até timestamp desejado
- **Disaster recovery:** Restauração completa do basebackup + WAL

### 7.3 Verificação

- Teste de restauração automatizado mensal
- Validação de integridade pós-restore
- Monitoramento de tamanho de WAL e espaço em disco

---

## 8. Estratégia de Migrations

### 8.1 Dois Conjuntos de Migrations

| Tipo | Quando executar | Escopo |
|------|----------------|--------|
| Platform migrations | Uma vez, no deploy | Banco global |
| Tenant migrations | Para cada tenant, no deploy e no provisionamento | Banco do tenant |

### 8.2 Fluxo de Deploy

```
1. Deploy → Executar platform migrations
2. Listar tenants ativos
3. Para cada tenant (paralelo controlado):
   a. Conectar ao banco do tenant
   b. Executar migrations pendentes
   c. Registrar sucesso/falha
4. Tenants com falha → fila de retry
```

### 8.3 Zero-Downtime Patterns

- **Adição de coluna:** Sempre com `DEFAULT NULL` ou valor default
- **Remoção de coluna:** Primeiro remover do código, depois drop na próxima release
- **Criação de índice:** Sempre `CREATE INDEX CONCURRENTLY`
- **Alteração de tipo:** Nova coluna → migrar dados → drop coluna antiga

Referência completa: `migration-strategy.md` (skill)

---

## 9. Segurança de Dados

### 9.1 Encryption at Rest

- PostgreSQL com `data_at_rest_encryption` via TDE ou volume encryption
- Colunas sensíveis (`mfa_secret`) criptografadas na aplicação (Laravel Crypt)
- Backups criptografados com chave separada

### 9.2 Colunas Sensíveis

| Coluna | Tratamento |
|--------|-----------|
| `password_hash` | Nunca exposta em API. Hash via bcrypt (custo 12) |
| `mfa_secret` | Criptografado com APP_KEY. Nunca retornado após setup |
| `document` (guests/providers) | Retenção limitada conforme LGPD. Mascarado em listagens |
| `ip_address` | Dados pessoais conforme LGPD. Retenção controlada |

### 9.3 Conexões

- SSL obrigatório para todas as conexões PostgreSQL
- Credenciais em secret manager (nunca no código)
- Rotação de credenciais programada

### 9.4 Row-Level Security (Consideração)

Para camada adicional de segurança, considerar RLS no PostgreSQL para garantir que queries sempre incluam `tenant_id` no banco da plataforma:

```sql
ALTER TABLE subscriptions ENABLE ROW LEVEL SECURITY;
CREATE POLICY tenant_isolation ON subscriptions
    USING (tenant_id = current_setting('app.current_tenant_id')::uuid);
```

---

## 10. Compatibilidade SQLite (Testes)

### 10.1 Diferenças a Tratar

| Feature | PostgreSQL | SQLite | Estratégia |
|---------|-----------|--------|-----------|
| UUID | `gen_random_uuid()` | `TEXT` | Gerar UUID no application layer |
| JSONB | Nativo, indexável | `TEXT` | Serializar/deserializar como JSON string |
| ENUM | Via `CHECK` constraint | Via `CHECK` constraint | Compatível |
| `VECTOR` (pgvector) | Nativo | Não disponível | Mock/stub nos testes |
| Exclusion constraint | GiST | Não disponível | Validação via application layer |
| Partial index | Suportado | Suportado | Compatível |
| TIMESTAMP | `TIMESTAMP` | `TEXT` | Cast no model (Laravel) |
| DECIMAL | `DECIMAL(p,s)` | `REAL` | Precisão numérica via application layer |

### 10.2 Mock de pgvector

```php
// Em testes, usar mock para busca vetorial
interface EmbeddingSearchInterface
{
    public function findSimilar(array $embedding, int $limit): Collection;
}

// Implementação de teste retorna resultados predefinidos
class FakeEmbeddingSearch implements EmbeddingSearchInterface
{
    public function findSimilar(array $embedding, int $limit): Collection
    {
        return collect(); // ou fixtures predefinidos
    }
}
```

### 10.3 Configuração Laravel

```php
// config/database.php
'testing' => [
    'driver' => 'sqlite',
    'database' => ':memory:',
    'prefix' => '',
    'foreign_key_constraints' => true,
],
```

---

## 11. Contagem de Tabelas

### 11.1 Banco da Plataforma: 16 tabelas

1. tenants
2. platform_users
3. tenant_admin_actions
4. plans
5. plan_versions
6. plan_prices
7. plan_features
8. features
9. tenant_feature_overrides
10. subscriptions
11. invoices
12. invoice_items
13. payments
14. dunning_policies
15. gateway_events
16. platform_audit_logs

### 11.2 Banco do Tenant: 27 tabelas

1. tenant_users
2. blocks
3. units
4. residents
5. spaces
6. space_availabilities
7. space_blocks
8. space_rules
9. reservations
10. guests
11. service_providers
12. condominium_rules
13. condominium_documents
14. document_sections
15. violations
16. violation_contestations
17. penalties
18. penalty_policies
19. announcements
20. announcement_reads
21. support_requests
22. support_messages
23. tenant_audit_logs
24. ai_embeddings
25. ai_usage_logs
26. ai_action_logs
27. idempotency_keys

**Total: 43 tabelas** (16 plataforma + 27 tenant)

---

## 12. Status

**Documento ATIVO** — Referência definitiva para a arquitetura de banco de dados do sistema.

| Campo | Valor |
|-------|-------|
| Última atualização | 2026-02-10 |
| Versão | 1.0.0 |
| Responsável | Equipe Backend |
