# Roadmap de Implementacao — Condominium Events Manager API

## 1. Principios de Priorizacao

Este roadmap organiza a implementacao do **Condominium Events Manager API** respeitando quatro principios fundamentais:

- **Dependencias tecnicas respeitadas:** Nenhuma fase inicia sem que suas dependencias estejam concluidas e validadas. Infraestrutura antes de features, autenticacao antes de qualquer recurso protegido, tenants antes de funcionalidades de tenant.
- **Valor de negocio entregue incrementalmente:** Cada fase produz um artefato funcional, testavel e potencialmente deployavel. O sistema cresce de forma iterativa, nunca em big-bang.
- **Testes em cada fase (nao ao final):** Testes unitarios, de feature, arquiteturais e de contrato sao escritos junto com o codigo da fase. Nao existe fase separada de testes.
- **Cada fase e deployavel e testavel:** Ao final de cada fase, o sistema pode ser executado, testado e validado de forma independente, mesmo que funcionalidades posteriores ainda nao existam.

### Ordem de Dependencia

```
Infraestrutura e fundacao (Fase 0)
  --> Multi-tenancy (Fase 1)
    --> Autenticacao e autorizacao (Fase 2)
      --> Billing e assinaturas (Fase 3)
      --> Unidades e moradores (Fase 4)
        --> Espacos (Fase 5)
          --> Reservas (Fase 6) <-- CORE FEATURE
            --> Governanca (Fase 7)
            --> Controle de pessoas (Fase 8)
            --> Dashboards (Fase 10)
        --> Comunicacao (Fase 9)
          --> IA (Fase 11)
  --> Observabilidade (Fase 12) <-- paralela a partir da Fase 6
```

---

## 2. Fase 0 — Setup do Projeto (Foundation)

**Prioridade:** CRITICAL — bloqueia tudo
**Dependencias:** Nenhuma
**Estimativa:** Base para todo o desenvolvimento

### 0.1 Inicializacao do Laravel

- Criar projeto Laravel (versao estavel mais recente)
- Configuracao PHP 8.2+ com extensoes necessarias (pdo_pgsql, redis, mbstring, openssl, bcmath)
- Dependencias iniciais via Composer:
  - `laravel/framework`
  - `pestphp/pest` + `pestphp/pest-plugin-laravel`
  - `larastan/larastan`
  - `laravel/pint` (PHP CS Fixer)
- Configuracao de ambiente (`.env`, `.env.example`, `.env.testing`)
- Docker setup completo:
  - `Dockerfile` (PHP-FPM com extensoes)
  - `docker-compose.yml` (PHP, PostgreSQL 16+, Redis)
  - Volume para persistencia de dados
  - Network isolada

### 0.2 Estrutura de Pastas (DDD + Clean Architecture)

Criar a estrutura de pastas conforme definido em `project-structure.md`:

```
app/
  Domain/
    Shared/
      ValueObjects/
        Uuid.php              # UUIDv7 com validacao
        DateRange.php         # Periodo com validacao start < end
        Money.php             # Valor monetario com moeda
      Events/
        DomainEvent.php       # Interface base para eventos de dominio
      Exceptions/
        DomainException.php   # Exception base de dominio
    Tenant/
    Billing/
    Unit/
    Space/
    Reservation/
    Governance/
    People/
    Communication/
  Application/
    Shared/
      Contracts/
      DTOs/
    Tenant/
    Billing/
    Unit/
    Space/
    Reservation/
    Governance/
    People/
    Communication/
    AI/
  Infrastructure/
    Persistence/
      Platform/
        Models/
        Repositories/
      Tenant/
        Models/
        Repositories/
    MultiTenancy/
    Events/
    Jobs/
    Gateways/
    Notifications/
    Services/
  Interface/
    Http/
      Controllers/
        Platform/
        Tenant/
        Auth/
        AI/
        Webhook/
      Middleware/
      Requests/
        Platform/
        Tenant/
      Resources/
        Platform/
        Tenant/
    Console/
      Commands/
```

Classes base a implementar:

- `App\Domain\Shared\Events\DomainEvent` — interface com `occurredAt()`, `aggregateId()`, `eventName()`
- `App\Domain\Shared\Exceptions\DomainException` — exception base com `code` e `context`
- `App\Domain\Shared\ValueObjects\Uuid` — wrapper para UUIDv7 com validacao e imutabilidade
- `App\Domain\Shared\ValueObjects\DateRange` — periodo com invariante `start < end`
- `App\Domain\Shared\ValueObjects\Money` — valor monetario com `amount` (int, centavos) e `currency`

### 0.3 Configuracao de Banco de Dados

- `config/database.php` com duas conexoes:
  - `platform` — conexao fixa, banco global (configurada via `.env`)
  - `tenant` — conexao dinamica, banco isolado por tenant (configurada em runtime)
- Estrutura de migrations separadas:
  - `database/migrations/platform/` — migrations do banco global
  - `database/migrations/tenant/` — migrations do banco de cada tenant
- Estrutura de seeders:
  - `database/seeders/platform/` — seeds da plataforma
  - `database/seeders/tenant/` — seeds padrao de cada tenant
- Estrutura de factories:
  - `database/factories/` — factories para testes

### 0.4 Testes Arquiteturais

- Instalar `pestphp/pest-plugin-arch`
- Criar `tests/Architecture/LayerDependencyTest.php`:
  - Domain nao depende de nenhuma camada externa (sem `use Illuminate\...`, sem `use App\Infrastructure\...`, sem `use App\Application\...`)
  - Application nao depende de Infrastructure nem Interface
  - Infrastructure pode depender de Domain e Application
  - Interface pode depender de Application, nao de Domain diretamente
- Criar `tests/Architecture/NamingConventionTest.php`:
  - Models Eloquent terminam com `Model` (ex: `TenantModel`)
  - Repositories terminam com `Repository` (ex: `EloquentTenantRepository`)
  - Use Cases possuem metodo `execute()`
  - Events nomeados no passado (ex: `TenantCreated`)
  - Jobs terminam com `Job` (ex: `ProvisionTenantJob`)
- Criar `tests/Architecture/TenantIsolationTest.php`:
  - Models de tenant usam conexao `tenant`
  - Models de plataforma usam conexao `platform`

### 0.5 CI/CD Base

- GitHub Actions workflow (`.github/workflows/ci.yml`):
  - Lint: `laravel/pint --test`
  - Analise estatica: `phpstan analyse` (nivel 5+)
  - Testes: `php artisan test`
  - Cobertura minima (quando aplicavel)
- Configuracao PHPStan / Larastan:
  - `phpstan.neon` com nivel 5+ e regras especificas para Laravel
- Configuracao PHP CS Fixer:
  - `pint.json` com regras de estilo do projeto

### 0.6 Estrutura de Rotas

- `routes/platform.php` — rotas da plataforma (admin, billing)
- `routes/tenant.php` — rotas do tenant (espacos, reservas, governanca)
- `routes/webhook.php` — rotas de webhooks (billing, integracoes externas)
- Registro dos arquivos de rota no `RouteServiceProvider`

### Entregavel da Fase 0

Projeto vazio com estrutura correta, classes base implementadas, testes arquiteturais passando, pipeline CI funcional. Nenhuma feature de negocio, mas a fundacao completa para iniciar o desenvolvimento.

---

## 3. Fase 1 — Multi-Tenancy Core

**Prioridade:** CRITICAL — necessaria para qualquer feature de tenant
**Dependencias:** Fase 0
**Contexto:** Platform Domain > Tenant Management

### 1.1 Platform Database Schema

Migrations em `database/migrations/platform/`:

- `create_tenants_table` — tabela principal de tenants:
  - `id` (uuid, PK), `slug` (string, unique), `name`, `type` (enum), `status` (enum), `config` (jsonb), `database_name`, `provisioned_at`, timestamps
- `create_plans_table`:
  - `id` (uuid, PK), `name`, `slug` (unique), `description`, `is_active`, timestamps
- `create_plan_versions_table`:
  - `id` (uuid, PK), `plan_id` (FK), `version`, `price_monthly`, `price_yearly`, `features` (jsonb), `limits` (jsonb), `is_current`, `effective_from`, timestamps
- `create_platform_users_table`:
  - `id` (uuid, PK), `name`, `email` (unique), `password`, `role` (enum), `is_active`, timestamps
- `create_platform_audit_logs_table`:
  - `id` (uuid, PK), `actor_type`, `actor_id`, `action`, `resource_type`, `resource_id`, `context` (jsonb), `ip_address`, `created_at`

### 1.2 Tenant Management (Domain + Application)

**Domain Layer:**

- `App\Domain\Tenant\Entities\Tenant` — entidade com regras de transicao de status:
  - Metodos: `activate()`, `suspend()`, `cancel()`, `archive()`
  - Invariante: transicoes de status validadas conforme maquina de estados
- `App\Domain\Tenant\Enums\TenantStatus` — enum com estados:
  - `prospect`, `trial`, `provisioning`, `active`, `past_due`, `suspended`, `canceled`, `archived`
- `App\Domain\Tenant\Events\TenantCreated`
- `App\Domain\Tenant\Events\TenantSuspended`
- `App\Domain\Tenant\Events\TenantProvisioned`

**Application Layer:**

- `App\Application\Tenant\Contracts\TenantRepositoryInterface`:
  - `findById()`, `findBySlug()`, `save()`, `findAllActive()`, `findAllForMigration()`
- `App\Application\Tenant\UseCases\ProvisionTenant`:
  - Valida dados, cria registro, despacha `ProvisionTenantJob`
- `App\Application\Tenant\UseCases\SuspendTenant`:
  - Valida status atual, executa transicao, emite evento
- `App\Application\Tenant\DTOs\CreateTenantDTO`

**Infrastructure Layer:**

- `App\Infrastructure\Persistence\Platform\Models\TenantModel`
- `App\Infrastructure\Persistence\Platform\Repositories\EloquentTenantRepository`

### 1.3 Multi-Tenancy Infrastructure

- `App\Infrastructure\MultiTenancy\TenantManager`:
  - Responsabilidade: resolver tenant e configurar conexao dinamica
  - Metodos: `resolve(string $tenantId)`, `switchConnection(Tenant $tenant)`, `resetConnection()`
- `App\Infrastructure\MultiTenancy\TenantContext`:
  - Objeto imutavel com dados do tenant atual (id, slug, name, type, status, database_name, resolved_at)
  - Registrado no container como singleton por request
- `App\Infrastructure\MultiTenancy\TenantDatabaseCreator`:
  - Cria database/schema PostgreSQL: `tenant_{slug}`
  - Metodos: `createDatabase(string $name)`, `dropDatabase(string $name)`, `databaseExists(string $name)`
- `App\Infrastructure\Jobs\Tenant\ProvisionTenantJob`:
  - Fluxo: criar DB -> executar migrations -> executar seeds iniciais -> criar usuario admin -> atualizar status para `active`
  - Idempotente: pode ser re-executado com seguranca
  - Registra falhas e mantem tenant em `provisioning` se houver erro
- `App\Interface\Http\Middleware\ResolveTenantMiddleware`:
  - Extrai `tenant_id` do JWT, busca tenant, configura conexao, injeta TenantContext
- `App\Interface\Http\Middleware\EnsureTenantActive`:
  - Verifica se o tenant resolvido esta com status `active`
  - Retorna 403 com mensagem especifica se suspenso/cancelado

### 1.4 Tenant Migration Runner

- `App\Interface\Console\Commands\RunTenantMigrationsCommand`:
  - Comando: `php artisan tenant:migrate`
  - Opcoes: `--tenant={slug}` (tenant especifico) ou todos os ativos
  - Opcao: `--seed` para rodar seeders apos migrations
  - Suporte a execucao paralela (configuravel)
  - Output com status por tenant (sucesso/falha)
  - Falha em um tenant nao bloqueia outros

### 1.5 Testes da Fase 1

- **Unit:**
  - `tests/Unit/Domain/Tenant/TenantTest.php` — transicoes de status validas e invalidas, invariantes
  - `tests/Unit/Domain/Tenant/TenantStatusTest.php` — maquina de estados, transicoes permitidas
- **Feature:**
  - `tests/Feature/Tenant/ProvisionTenantTest.php` — fluxo completo de provisionamento (com SQLite ou PostgreSQL de teste)
  - `tests/Feature/Tenant/SuspendTenantTest.php` — suspensao com validacao de pre-condicoes
- **Architecture:**
  - Middleware execution order test
  - TenantContext immutability test

### Entregavel da Fase 1

Sistema capaz de criar tenants, provisionar databases isolados, executar migrations por tenant, resolver tenant por requisicao HTTP e garantir isolamento de dados. Base para todas as features de tenant.

---

## 4. Fase 2 — Autenticacao e Autorizacao

**Prioridade:** CRITICAL — necessaria antes de qualquer endpoint de API
**Dependencias:** Fase 1
**Contexto:** Contexto Transversal > Identity & Auth

### 2.1 Platform Authentication

- `App\Domain\Shared\Entities\PlatformUser` — entidade de usuario da plataforma (Platform Owner, Platform Admin)
- Use cases:
  - `LoginPlatformUser` — valida credenciais, gera JWT (RS256)
  - `RefreshPlatformToken` — renova token antes da expiracao
  - `LogoutPlatformUser` — revoga token (blacklist via Redis)
- JWT com claims:
  - `sub` (user_id), `role`, `type` (platform), `iss`, `exp`, `iat`
- Auth middleware para rotas de plataforma

### 2.2 Tenant Authentication

**Domain:**

- `App\Domain\Unit\Entities\TenantUser`:
  - Campos: id, tenant_id, name, email, password_hash, role, status, invited_at, activated_at, last_login_at
  - Status: `invited`, `active`, `inactive`, `blocked`

**Migration (tenant DB):**

- `create_tenant_users_table` em `database/migrations/tenant/`

**Use Cases:**

- `LoginTenantUser`:
  - Resolve tenant a partir do header/subdomain
  - Valida credenciais contra o banco do tenant
  - Gera JWT com `tenant_id` claim
- `ActivateTenantAccount`:
  - Valida token de convite
  - Define senha
  - Atualiza status para `active`
- `InviteTenantUser`:
  - Gera token de convite com expiracao
  - Envia e-mail de convite
- `ResetPassword`:
  - Gera token de reset
  - Envia e-mail com link
  - Valida token e atualiza senha

**JWT Claims (Tenant):**

- `sub` (user_id), `tenant_id`, `role`, `type` (tenant), `iss`, `exp`, `iat`

### 2.3 Authorization Framework

- RBAC com papeis definidos:
  - Platform: `platform_owner`, `platform_admin`
  - Tenant: `sindico`, `administradora`, `condomino`, `portaria`
- Policy base class (`App\Interface\Http\Policies\BasePolicy`)
- Policies por contexto:
  - `TenantPolicy`, `UnitPolicy`, `SpacePolicy`, `ReservationPolicy`, etc.
- Registro de policies no `AuthServiceProvider`
- `App\Interface\Http\Middleware\CheckFeatureAccess`:
  - Verifica se o tenant tem acesso a feature especifica (baseado no plano/overrides)
- `App\Interface\Http\Middleware\EnsureSubscriptionValid`:
  - Verifica se a assinatura do tenant esta em estado valido (`active`, `trialing`)
  - Retorna 402 Payment Required se a assinatura estiver expirada

### 2.4 Audit Logging

- `App\Domain\Shared\Entities\AuditLog`:
  - Campos: id, actor_type, actor_id, action, resource_type, resource_id, context (jsonb), ip_address, user_agent, created_at
- Migration: `create_tenant_audit_logs_table` em `database/migrations/tenant/`
  - (Platform audit logs ja criados na Fase 1)
- `App\Infrastructure\Services\AuditLogger`:
  - Metodo: `log(string $action, string $resourceType, string $resourceId, array $context = [])`
  - Automaticamente captura actor do contexto de autenticacao
  - Automaticamente captura IP e User-Agent do request
- Middleware/observer para logging automatico de acoes criticas

### 2.5 Testes da Fase 2

- **Unit:**
  - JWT generation e validation (claims corretos, expiracao, assinatura RS256)
  - Role checks e autorizacao
  - Password hashing e verificacao
- **Feature:**
  - Login flow (platform e tenant): credenciais validas, invalidas, usuario bloqueado
  - Token refresh: token valido, expirado, revogado
  - Invitation activation: token valido, expirado, ja usado
  - Password reset: fluxo completo
- **Contract:**
  - `POST /platform/auth/login` — response schema
  - `POST /tenant/auth/login` — response schema
  - `POST /auth/refresh` — response schema
  - `POST /auth/logout` — response schema

### Entregavel da Fase 2

Sistema completo de autenticacao (platform + tenant), autorizacao baseada em papeis, middleware de feature access e subscription validation, audit logging funcional. Todos os endpoints subsequentes serao protegidos.

---

## 5. Fase 3 — Billing e Assinaturas

**Prioridade:** HIGH — controla acesso e monetizacao do SaaS
**Dependencias:** Fase 2
**Contexto:** Platform Domain > Billing & Plans

### 3.1 Plans e Subscriptions

**Domain:**

- `App\Domain\Billing\Entities\Plan` — plano do SaaS
- `App\Domain\Billing\Entities\PlanVersion` — versao do plano com precos e features
- `App\Domain\Billing\Entities\Subscription` — assinatura com maquina de estados:
  - Status: `trialing`, `active`, `past_due`, `suspended`, `canceled`, `expired`
  - Transicoes controladas por metodos de dominio
- `App\Domain\Billing\Enums\SubscriptionStatus`

**Use Cases:**

- `CreateSubscription` — cria assinatura vinculada a um tenant e plan_version
- `RenewSubscription` — renova assinatura no ciclo de cobranca
- `CancelSubscription` — cancela assinatura (imediato ou ao final do ciclo)
- `ChangeSubscriptionPlan` — altera plano com proration (upgrade/downgrade)

**Migrations (platform DB):**

- `create_subscriptions_table`

### 3.2 Invoicing

**Domain:**

- `App\Domain\Billing\Entities\Invoice`:
  - Campos: id, tenant_id, subscription_id, number, status, subtotal, tax, total, due_date, paid_at
- `App\Domain\Billing\Entities\InvoiceItem`:
  - Campos: id, invoice_id, description, quantity, unit_price, total
- `App\Domain\Billing\Enums\InvoiceStatus`:
  - `draft`, `issued`, `paid`, `overdue`, `canceled`, `refunded`

**Use Cases:**

- `GenerateInvoice` — gera fatura a partir da assinatura
- Invoice number generator (formato: `INV-{YEAR}-{SEQUENCE}`)

**Migrations (platform DB):**

- `create_invoices_table`
- `create_invoice_items_table`

### 3.3 Payments

**Domain:**

- `App\Domain\Billing\Entities\Payment`:
  - Campos: id, invoice_id, amount, status, gateway, gateway_id, paid_at, failed_at, metadata
- `App\Domain\Billing\Enums\PaymentStatus`:
  - `pending`, `processing`, `confirmed`, `failed`, `refunded`

**Application:**

- `App\Application\Billing\Contracts\PaymentGatewayInterface`:
  - `charge(Money $amount, array $paymentMethod): PaymentResult`
  - `refund(string $gatewayId, Money $amount): RefundResult`
  - `getPaymentStatus(string $gatewayId): PaymentStatus`

**Infrastructure:**

- `App\Infrastructure\Gateways\Payment\StripeGateway` — implementa `PaymentGatewayInterface`

**Use Cases:**

- `ProcessPayment` — processa pagamento via gateway
- `HandlePaymentWebhook` — processa eventos do gateway (confirmacao, falha, disputa)

**Migrations (platform DB):**

- `create_payments_table`
- `create_gateway_events_table` (log de eventos do gateway)

**Webhook:**

- `App\Interface\Http\Controllers\Webhook\BillingWebhookController`:
  - Valida assinatura do webhook (Stripe signature)
  - Despacha para handler correto baseado no tipo de evento

### 3.4 Dunning (Cobranca de Inadimplencia)

**Domain:**

- `App\Domain\Billing\Entities\DunningPolicy`:
  - Define regras de tentativa de cobranca (intervalos, max tentativas)
- `App\Domain\Billing\Entities\DunningAttempt`:
  - Registro de cada tentativa de cobranca

**Use Cases:**

- `ProcessDunning` — executado via cron/scheduler:
  - Identifica faturas overdue
  - Executa tentativa de cobranca conforme policy
  - Notifica tenant apos cada tentativa
  - Suspende tenant apos esgotar tentativas

**Jobs:**

- `App\Infrastructure\Jobs\Billing\ProcessPastDueInvoicesJob`
- `App\Infrastructure\Jobs\Billing\RetryFailedPaymentsJob`
- `App\Infrastructure\Jobs\Billing\SuspendOverdueTenantsJob`

**Migrations (platform DB):**

- `create_dunning_policies_table`
- `create_dunning_attempts_table`

### 3.5 Platform Admin API

Controllers em `App\Interface\Http\Controllers\Platform\`:

- `TenantController` — CRUD + suspend/reactivate/cancel
- `PlanController` — CRUD de planos e versoes
- `SubscriptionController` — visualizacao, alteracao de plano, cancelamento
- `InvoiceController` — listagem, detalhes, emissao manual
- `PaymentController` — listagem, detalhes, estorno

Rotas em `routes/platform.php`:

```
GET/POST       /platform/tenants
GET/PUT/DELETE /platform/tenants/{id}
POST           /platform/tenants/{id}/suspend
POST           /platform/tenants/{id}/reactivate
GET/POST       /platform/plans
GET/PUT        /platform/plans/{id}
POST           /platform/plans/{id}/versions
GET            /platform/subscriptions
GET/PUT        /platform/subscriptions/{id}
GET            /platform/invoices
GET            /platform/invoices/{id}
GET            /platform/payments
```

### 3.6 Feature Flags

**Domain:**

- `App\Domain\Billing\Entities\Feature`:
  - Campos: id, key (unique), name, description, type (boolean/limit/percentage)
- `App\Domain\Billing\Entities\TenantFeatureOverride`:
  - Campos: id, tenant_id, feature_key, value, reason, overridden_by, expires_at

**Infrastructure:**

- `App\Infrastructure\Services\FeatureResolver`:
  - Resolve features combinando: plano base + overrides por tenant
  - Cache com TTL curto (Redis)
  - Invalidacao ao mudar plano ou override

**Migrations (platform DB):**

- `create_features_table`
- `create_plan_features_table` (pivot)
- `create_tenant_feature_overrides_table`

### 3.7 Testes da Fase 3

- **Unit:**
  - Subscription state machine: todas as transicoes validas e invalidas
  - Invoice generation: calculo de totais, numeracao sequencial
  - DunningPolicy: logica de tentativas e intervalos
- **Feature:**
  - Fluxo completo: criar assinatura -> gerar fatura -> processar pagamento -> renovar
  - Fluxo de inadimplencia: fatura vencida -> dunning -> suspensao
  - Feature flags: resolucao com plano + override
- **Contract:**
  - Platform API endpoints: response schemas, status codes, error formats

### Entregavel da Fase 3

Sistema completo de billing com gestao de planos, assinaturas com maquina de estados, geracao de faturas, processamento de pagamentos via Stripe, dunning automatizado e feature flags. Platform Admin API funcional.

---

## 6. Fase 4 — Units e Moradores (Inicio do Tenant Domain)

**Prioridade:** HIGH — fundacao para todas as features de tenant
**Dependencias:** Fase 2 (autenticacao e autorizacao)
**Contexto:** Tenant Domain > Units & Residents

### 4.1 Blocks e Units

**Domain:**

- `App\Domain\Unit\Entities\Block`:
  - Campos: id, name, identifier (ex: "Bloco A"), description, is_active
- `App\Domain\Unit\Entities\Unit`:
  - Campos: id, block_id, number, floor, type, is_active, is_occupied
- `App\Domain\Unit\Enums\UnitType`:
  - `apartment`, `house`, `store`, `office`, `other`

**Migrations (tenant DB):**

- `create_blocks_table`
- `create_units_table`

**Infrastructure:**

- `App\Infrastructure\Persistence\Tenant\Models\BlockModel`
- `App\Infrastructure\Persistence\Tenant\Models\UnitModel`
- `App\Application\Unit\Contracts\BlockRepositoryInterface`
- `App\Application\Unit\Contracts\UnitRepositoryInterface`
- `App\Infrastructure\Persistence\Tenant\Repositories\EloquentBlockRepository`
- `App\Infrastructure\Persistence\Tenant\Repositories\EloquentUnitRepository`

**Use Cases:**

- `CreateBlock` — cria bloco com validacao de unicidade do identifier no tenant
- `UpdateBlock` — atualiza dados do bloco
- `CreateUnit` — cria unidade vinculada a um bloco, valida unicidade number+block
- `UpdateUnit` — atualiza dados da unidade
- `DeactivateUnit` — desativa unidade (cascade: desativa residents vinculados)

### 4.2 Residents

**Domain:**

- `App\Domain\Unit\Entities\Resident`:
  - Campos: id, unit_id, tenant_user_id, name, email, phone, role, is_primary, is_active, moved_in_at, moved_out_at
- `App\Domain\Unit\Enums\ResidentRole`:
  - `owner` (proprietario), `tenant_resident` (inquilino), `dependent` (dependente), `authorized` (autorizado)
- `App\Domain\Unit\Events\ResidentInvited`
- `App\Domain\Unit\Events\ResidentActivated`

**Migrations (tenant DB):**

- `create_residents_table`

**Infrastructure:**

- `App\Infrastructure\Persistence\Tenant\Models\ResidentModel`
- `App\Application\Unit\Contracts\ResidentRepositoryInterface`
- `App\Infrastructure\Persistence\Tenant\Repositories\EloquentResidentRepository`

**Use Cases:**

- `InviteResident`:
  - Cria registro de resident + tenant_user com status `invited`
  - Valida limite de residents por unidade (baseado no plano)
  - Gera token de convite
  - Despacha envio de e-mail
- `ActivateResident`:
  - Valida token de convite
  - Atualiza status para `active`
  - Vincula ao tenant_user ativado
- `DeactivateResident`:
  - Desativa resident e marca `moved_out_at`
  - Desativa tenant_user vinculado (se nao houver outro resident ativo)
- `TransferUnit`:
  - Desativa residents atuais, prepara unidade para novos moradores

### 4.3 Tenant API (Units)

Controllers em `App\Interface\Http\Controllers\Tenant\`:

- `BlockController` — CRUD de blocos
- `UnitController` — CRUD de unidades
- `ResidentController` — convite, listagem, desativacao

Form Requests:

- `CreateBlockRequest`, `UpdateBlockRequest`
- `CreateUnitRequest`, `UpdateUnitRequest`
- `InviteResidentRequest`, `DeactivateResidentRequest`

API Resources:

- `BlockResource`, `UnitResource`, `ResidentResource`

Policies:

- `BlockPolicy`, `UnitPolicy`, `ResidentPolicy`
  - Sindico/Administradora: acesso total
  - Condomino: leitura de sua unidade e residents
  - Portaria: leitura apenas

Rotas em `routes/tenant.php`:

```
GET/POST       /tenant/blocks
GET/PUT/DELETE /tenant/blocks/{id}
GET/POST       /tenant/units
GET/PUT        /tenant/units/{id}
POST           /tenant/units/{id}/deactivate
GET/POST       /tenant/residents
GET            /tenant/residents/{id}
POST           /tenant/residents/{id}/deactivate
POST           /tenant/residents/invite
```

### 4.4 Integracao de Notificacoes

- `App\Application\Shared\Contracts\NotificationServiceInterface`:
  - `send(string $channel, string $to, string $template, array $data): void`
- `App\Infrastructure\Notifications\EmailNotificationAdapter`:
  - Implementa `NotificationServiceInterface` para canal email
  - Usa Laravel Mail com filas
- Templates:
  - E-mail de convite de morador
  - E-mail de desativacao de morador
  - E-mail de boas-vindas apos ativacao

### 4.5 Testes da Fase 4

- **Unit:**
  - Block/Unit/Resident entities: validacoes, invariantes
  - Limites por plano (max units, max residents per unit)
  - ResidentRole validacoes
- **Feature:**
  - CRUD de blocos e unidades (com autenticacao e autorizacao)
  - Fluxo de convite: criar resident -> enviar convite -> ativar conta
  - Desativacao em cascata: desativar unidade -> desativar residents
  - Transferencia de unidade
- **Contract:**
  - Tenant Units API: response schemas para todas as rotas

### Entregavel da Fase 4

Gestao completa de blocos, unidades e moradores com fluxo de convite, ativacao e desativacao. Notificacoes por e-mail integradas. Base estrutural para espacos, reservas e governanca.

---

## 7. Fase 5 — Espacos

**Prioridade:** HIGH — necessaria para reservas
**Dependencias:** Fase 4 (unidades devem existir para contexto)
**Contexto:** Tenant Domain > Spaces Management

### 5.1 Space Domain

**Entidades:**

- `App\Domain\Space\Entities\Space`:
  - Campos: id, name, description, type, status, capacity, location, requires_approval, advance_booking_days, max_duration_hours, cancellation_deadline_hours, is_active
- `App\Domain\Space\Entities\SpaceAvailability`:
  - Campos: id, space_id, day_of_week, start_time, end_time, is_active
  - Define janelas de disponibilidade recorrentes
- `App\Domain\Space\Entities\SpaceBlock`:
  - Campos: id, space_id, reason, blocked_by, start_date, end_date, created_at
  - Bloqueio temporario do espaco (manutencao, evento, etc.)
- `App\Domain\Space\Entities\SpaceRule`:
  - Campos: id, space_id, rule_key, rule_value, description
  - Regras especificas por espaco (ex: max_guests, requires_deposit, noise_limit_hour)

**Enums:**

- `App\Domain\Space\Enums\SpaceType`:
  - `party_room`, `barbecue`, `sports_court`, `pool`, `gym`, `playground`, `meeting_room`, `garden`, `other`
- `App\Domain\Space\Enums\SpaceStatus`:
  - `active`, `inactive`, `maintenance`, `blocked`

**Events:**

- `App\Domain\Space\Events\SpaceCreated`
- `App\Domain\Space\Events\SpaceBlocked`
- `App\Domain\Space\Events\SpaceDeactivated`

**Migrations (tenant DB):**

- `create_spaces_table`
- `create_space_availabilities_table`
- `create_space_blocks_table`
- `create_space_rules_table`

### 5.2 Space Application

**Contracts:**

- `App\Application\Space\Contracts\SpaceRepositoryInterface`:
  - `findById()`, `findAllActive()`, `findAvailableForDate()`, `save()`

**Use Cases:**

- `CreateSpace` — cria espaco com validacao de limite do plano
- `UpdateSpace` — atualiza dados do espaco
- `SetSpaceAvailability` — define janelas de disponibilidade por dia da semana
  - Valida que nao ha sobreposicao de horarios no mesmo dia
- `BlockSpace` — cria bloqueio temporario
  - Valida que nao ha reservas confirmadas no periodo
  - Notifica condominios com reservas pendentes no periodo
- `UnblockSpace` — remove bloqueio temporario
- `DeactivateSpace` — desativa espaco
  - Valida tratamento de reservas futuras (cancela ou rejeita)
- `ConfigureSpaceRules` — define regras especificas do espaco

**Infrastructure:**

- `App\Infrastructure\Persistence\Tenant\Models\SpaceModel`
- `App\Infrastructure\Persistence\Tenant\Models\SpaceAvailabilityModel`
- `App\Infrastructure\Persistence\Tenant\Models\SpaceBlockModel`
- `App\Infrastructure\Persistence\Tenant\Models\SpaceRuleModel`
- `App\Infrastructure\Persistence\Tenant\Repositories\EloquentSpaceRepository`

### 5.3 Tenant API (Espacos)

Controller: `App\Interface\Http\Controllers\Tenant\SpaceController`

Form Requests:

- `CreateSpaceRequest`, `UpdateSpaceRequest`
- `SetSpaceAvailabilityRequest`
- `BlockSpaceRequest`
- `ConfigureSpaceRulesRequest`

API Resources:

- `SpaceResource`, `SpaceDetailResource` (com availabilities, blocks e rules)
- `SpaceAvailabilityResource`, `SpaceBlockResource`

Policies:

- `SpacePolicy`:
  - Sindico/Administradora: CRUD completo
  - Condomino: leitura, verificar disponibilidade
  - Portaria: leitura

Rotas em `routes/tenant.php`:

```
GET/POST       /tenant/spaces
GET/PUT        /tenant/spaces/{id}
POST           /tenant/spaces/{id}/deactivate
GET/POST       /tenant/spaces/{id}/availabilities
PUT/DELETE     /tenant/spaces/{id}/availabilities/{availId}
POST           /tenant/spaces/{id}/block
DELETE         /tenant/spaces/{id}/block/{blockId}
GET/PUT        /tenant/spaces/{id}/rules
```

### 5.4 Testes da Fase 5

- **Unit:**
  - Space entity: validacoes de campos, status transitions
  - SpaceAvailability: validacao de horarios, deteccao de sobreposicao
  - SpaceBlock: validacao de datas
- **Feature:**
  - CRUD de espacos (com autenticacao, autorizacao, tenant isolation)
  - Configuracao de disponibilidade: criar, atualizar, remover janelas
  - Bloqueio de espaco: criar, remover, validar impacto em reservas
  - Desativacao de espaco com tratamento de reservas futuras
- **Contract:**
  - Tenant Spaces API: response schemas, status codes

### Entregavel da Fase 5

Gestao completa de espacos comuns com configuracao de disponibilidade, bloqueios temporarios e regras por espaco. Base completa para o sistema de reservas.

---

## 8. Fase 6 — Reservas (Core Feature)

**Prioridade:** CRITICAL — a feature principal do produto
**Dependencias:** Fase 5 (espacos com disponibilidade configurada)
**Contexto:** Tenant Domain > Reservations (Aggregate Root)

### 6.1 Reservation Domain

- `App\Domain\Reservation\Entities\Reservation` (Aggregate Root):
  - Campos: id, space_id, unit_id, resident_id, title, description, status, start_datetime, end_datetime, guest_count, requires_approval, approved_by, approved_at, canceled_by, canceled_at, cancellation_reason, completed_at, no_show_at, no_show_by, created_at, updated_at
- `App\Domain\Reservation\Enums\ReservationStatus` (maquina de estados):
  - `pending_approval`, `confirmed`, `rejected`, `canceled`, `in_progress`, `completed`, `no_show`
  - Transicoes:
    - `pending_approval` -> `confirmed` | `rejected` | `canceled`
    - `confirmed` -> `canceled` | `in_progress`
    - `in_progress` -> `completed` | `no_show`
- `App\Domain\Reservation\Services\ConflictChecker` (Domain Service):
  - Verifica conflitos de horario para o mesmo espaco
  - Usa lock pessimista (SELECT FOR UPDATE) para evitar race conditions
  - Metodo: `checkConflicts(string $spaceId, DateRange $period, ?string $excludeReservationId): bool`
- `App\Domain\Reservation\Exceptions\ConflictException` — reserva conflita com outra existente
- `App\Domain\Reservation\Exceptions\BlockedException` — espaco bloqueado no periodo

**Events:**

- `App\Domain\Reservation\Events\ReservationRequested`
- `App\Domain\Reservation\Events\ReservationConfirmed`
- `App\Domain\Reservation\Events\ReservationRejected`
- `App\Domain\Reservation\Events\ReservationCanceled`
- `App\Domain\Reservation\Events\ReservationCompleted`
- `App\Domain\Reservation\Events\ReservationNoShow`

**Migration (tenant DB):**

- `create_reservations_table`

### 6.2 Reservation Application

**Contracts:**

- `App\Application\Reservation\Contracts\ReservationRepositoryInterface`:
  - `findById()`, `findBySpace()`, `findByUnit()`, `findByResident()`, `findConflicting()`, `save()`
  - `countMonthlyByUnit(string $unitId, string $spaceId, Carbon $month): int`

**Use Cases:**

- **`CreateReservation`** — O CASO DE USO PRINCIPAL (mais complexo do sistema):
  1. Validar que o espaco existe e esta ativo
  2. Validar que a unidade existe e esta ativa
  3. Validar que o resident existe e esta ativo
  4. Validar que o horario esta dentro da disponibilidade do espaco
  5. Validar advance booking (antecedencia minima/maxima)
  6. Validar duracao (min/max conforme regra do espaco)
  7. Verificar conflito de horario com lock pessimista no banco
  8. Validar capacidade (guest_count <= space.capacity)
  9. Validar limite mensal por unidade (conforme regra do espaco)
  10. Verificar penalidades ativas do resident/unidade que bloqueiam reservas
  11. Verificar bloqueios do espaco no periodo
  12. Se `requires_approval`: status = `pending_approval`; senao: status = `confirmed`
  13. Emitir evento `ReservationRequested` ou `ReservationConfirmed`

- `ApproveReservation`:
  - Valida que o ator tem permissao (sindico/administradora)
  - Transiciona `pending_approval` -> `confirmed`
  - Emite `ReservationConfirmed`

- `RejectReservation`:
  - Requer justificativa obrigatoria
  - Transiciona `pending_approval` -> `rejected`
  - Notifica o solicitante

- `CancelReservation`:
  - Pode ser feito pelo solicitante ou admin
  - Valida regra de cancelamento (prazo minimo antes do evento)
  - Se cancelamento tardio: registra flag para possivel violacao
  - Transiciona `confirmed` | `pending_approval` -> `canceled`
  - Emite `ReservationCanceled`

- `CompleteReservation`:
  - Portaria ou sistema marca como concluida
  - Transiciona `in_progress` -> `completed`
  - Emite `ReservationCompleted`

- `MarkAsNoShow`:
  - Portaria ou sistema (automatico apos X minutos sem check-in)
  - Transiciona `in_progress` -> `no_show`
  - Emite `ReservationNoShow`

- `ListAvailableSlots`:
  - Dado um espaco e uma data, retorna horarios disponiveis
  - Considera: disponibilidade configurada, reservas existentes, bloqueios

**Infrastructure:**

- `App\Infrastructure\Persistence\Tenant\Models\ReservationModel`
- `App\Infrastructure\Persistence\Tenant\Repositories\EloquentReservationRepository`

### 6.3 Tenant API (Reservas)

Controller: `App\Interface\Http\Controllers\Tenant\ReservationController`

Form Requests (validacao extensiva):

- `CreateReservationRequest` — validacao de campos, formato de datas, guest_count
- `ApproveReservationRequest`
- `RejectReservationRequest` — requer `rejection_reason`
- `CancelReservationRequest` — requer `cancellation_reason`

API Resources:

- `ReservationResource` — dados basicos
- `ReservationDetailResource` — com espaco, unidade, resident, guests, timeline

Policies:

- `ReservationPolicy`:
  - Condomino: criar (propria unidade), cancelar (proprias), listar (proprias)
  - Sindico/Administradora: aprovar, rejeitar, cancelar, listar todas
  - Portaria: marcar completed/no_show, listar do dia

Rotas em `routes/tenant.php`:

```
GET/POST       /tenant/reservations
GET            /tenant/reservations/{id}
POST           /tenant/reservations/{id}/approve
POST           /tenant/reservations/{id}/reject
POST           /tenant/reservations/{id}/cancel
POST           /tenant/reservations/{id}/complete
POST           /tenant/reservations/{id}/no-show
GET            /tenant/spaces/{id}/available-slots?date={date}
```

### 6.4 Events e Side Effects

Event handlers em `App\Infrastructure\Events\Handlers\Reservation\`:

- `NotifyOnReservationRequested` — notifica sindico de nova reserva pendente
- `NotifyOnReservationConfirmed` — notifica solicitante de confirmacao
- `NotifyOnReservationRejected` — notifica solicitante de rejeicao
- `NotifyOnReservationCanceled` — notifica partes envolvidas
- `NotifyOnReservationCompleted` — registro e notificacao
- `RegisterViolationOnNoShow` — dispara criacao automatica de violacao (integracao com Governanca, implementada na Fase 7)

### 6.5 Testes da Fase 6 (Extensivos)

- **Unit:**
  - Reservation entity: todas as transicoes de status, invariantes
  - ConflictChecker: deteccao de conflitos em cenarios variados (sobreposicao parcial, total, adjacente)
  - DateRange: validacoes de periodo
- **Feature:**
  - Criar reserva (happy path): espaco ativo, horario disponivel, sem conflitos
  - Criar reserva com aprovacao: pending_approval -> approve
  - Criar reserva com todos os cenarios de erro:
    - Espaco inativo/inexistente
    - Unidade inativa
    - Resident inativo
    - Fora da disponibilidade
    - Conflito de horario
    - Limite mensal excedido
    - Penalidade ativa
    - Espaco bloqueado
    - Capacidade excedida
    - Antecedencia invalida
    - Duracao invalida
  - Aprovacao e rejeicao (com e sem permissao)
  - Cancelamento (dentro e fora do prazo)
  - Completar reserva
  - No-show
  - Listar slots disponiveis
- **Concurrency:**
  - Duas reservas simultaneas para o mesmo horario: apenas uma deve ser confirmada
  - Race condition test com lock pessimista
- **Contract:**
  - Reservation API: todos os endpoints, response schemas, error responses

### Entregavel da Fase 6

Sistema completo de reservas com prevencao de conflitos via lock pessimista, maquina de estados, aprovacao, cancelamento com regras, no-show, e listagem de disponibilidade. A feature principal do produto esta funcional.

---

## 9. Fase 7 — Governanca

**Prioridade:** MEDIUM-HIGH — complementa reservas com regras e penalidades
**Dependencias:** Fase 6 (reservas geram eventos que alimentam governanca)
**Contexto:** Tenant Domain > Governance

### 7.1 Governance Domain

**Entidades:**

- `App\Domain\Governance\Entities\CondominiumRule`:
  - Campos: id, title, description, category, is_active, order, created_by, created_at
- `App\Domain\Governance\Entities\Violation`:
  - Campos: id, unit_id, resident_id, type, description, severity, status, source, source_id, registered_by, confirmed_by, confirmed_at, dismissed_by, dismissed_at, dismissed_reason, created_at
  - Status: `pending`, `confirmed`, `dismissed`, `contested`
- `App\Domain\Governance\Entities\Penalty`:
  - Campos: id, violation_id, unit_id, type, description, severity, starts_at, ends_at, is_active, applied_by, revoked_by, revoked_at, revoked_reason
- `App\Domain\Governance\Entities\PenaltyPolicy`:
  - Campos: id, violation_type, severity, penalty_type, duration_days, is_escalating, max_escalation_level, is_active
  - Define regras automaticas: para cada tipo/severidade de violacao, qual penalidade aplicar
- `App\Domain\Governance\Entities\ViolationContestation`:
  - Campos: id, violation_id, resident_id, reason, evidence_description, status, reviewed_by, reviewed_at, review_notes, created_at
  - Status: `pending`, `accepted`, `rejected`

**Enums:**

- `ViolationType`: `no_show`, `late_cancellation`, `noise`, `damage`, `unauthorized_access`, `rule_violation`, `other`
- `Severity`: `low`, `medium`, `high`, `critical`
- `PenaltyType`: `warning`, `temporary_ban`, `permanent_ban`, `fine`

**Events:**

- `ViolationRegistered`, `ViolationConfirmed`, `ViolationDismissed`
- `PenaltyApplied`, `PenaltyRevoked`
- `ContestationSubmitted`, `ContestationReviewed`

**Migrations (tenant DB):**

- `create_condominium_rules_table`
- `create_violations_table`
- `create_penalties_table`
- `create_penalty_policies_table`
- `create_violation_contestations_table`

### 7.2 Governance Application

**Use Cases:**

- `RegisterViolation` — registra violacao (manual por sindico ou automatica por sistema)
- `ConfirmViolation` — sindico confirma violacao, dispara avaliacao de penalidade
- `DismissViolation` — sindico descarta violacao com justificativa
- `ContestViolation` — condomino contesta violacao com argumentos
- `ReviewContestation` — sindico avalia contestacao (aceita ou rejeita)
  - Se aceita: violacao descartada
  - Se rejeitada: violacao confirmada, penalidade aplicada
- `ApplyPenalty` — aplica penalidade baseada na PenaltyPolicy:
  - Verifica historico de violacoes do resident/unidade
  - Se PenaltyPolicy e escalating: aumenta severidade conforme reincidencia
  - Cria registro de Penalty com periodo (starts_at, ends_at)
- `RevokePenalty` — revoga penalidade antecipadamente com justificativa
- `ConfigurePenaltyPolicy` — sindico configura politicas de penalidade automatica

### 7.3 Integracao Event-Driven com Reservas

Handlers que conectam Reservas -> Governanca:

- `ReservationNoShow` -> `RegisterViolationOnNoShow`:
  - Cria Violation automatica com type `no_show`, source `reservation`, source_id = reservation_id
  - Severity baseada na PenaltyPolicy configurada
- `ReservationCanceled` (late) -> `RegisterViolationOnLateCancellation`:
  - Se cancelamento ocorreu depois do prazo configurado
  - Cria Violation com type `late_cancellation`
- `ViolationConfirmed` -> `EvaluatePenaltyPolicy`:
  - Busca PenaltyPolicy para o tipo/severidade
  - Se encontrada, aplica penalidade automaticamente
  - Se escalating, verifica historico e ajusta severidade

### 7.4 Tenant API (Governanca)

Controllers:

- `App\Interface\Http\Controllers\Tenant\GovernanceController`:
  - Violations: listagem, registro, confirmacao, descarte
  - Penalties: listagem, revogacao
  - PenaltyPolicies: CRUD
  - CondominiumRules: CRUD
- `App\Interface\Http\Controllers\Tenant\ContestationController`:
  - Submissao e revisao de contestacoes

Rotas em `routes/tenant.php`:

```
GET/POST       /tenant/violations
GET            /tenant/violations/{id}
POST           /tenant/violations/{id}/confirm
POST           /tenant/violations/{id}/dismiss
POST           /tenant/violations/{id}/contest
GET            /tenant/contestations
GET            /tenant/contestations/{id}
POST           /tenant/contestations/{id}/review
GET            /tenant/penalties
GET            /tenant/penalties/{id}
POST           /tenant/penalties/{id}/revoke
GET/POST       /tenant/penalty-policies
GET/PUT/DELETE /tenant/penalty-policies/{id}
GET/POST       /tenant/rules
GET/PUT/DELETE /tenant/rules/{id}
```

### 7.5 Testes da Fase 7

- **Unit:**
  - Violation entity: status transitions, invariantes
  - PenaltyPolicy: logica de escalation, match de tipo/severidade
  - Penalty: calculo de periodo, verificacao de atividade
- **Feature:**
  - Fluxo completo: registrar violacao -> confirmar -> penalidade automatica
  - Fluxo de contestacao: registrar -> contestar -> revisar (aceitar/rejeitar)
  - Fluxo de escalation: multiplas violacoes -> penalidades crescentes
  - Revogacao de penalidade
  - CRUD de regras e politicas
- **Integration:**
  - No-show em reserva dispara violacao automatica + penalidade
  - Cancelamento tardio dispara violacao automatica
  - Penalidade ativa bloqueia criacao de novas reservas (integracao com Fase 6)
- **Contract:**
  - Governance API: todos os endpoints

### Entregavel da Fase 7

Sistema completo de governanca com regulamento interno configuravel, registro de violacoes (automaticas e manuais), penalidades com escalation, contestacao formal e integracao event-driven com reservas.

---

## 10. Fase 8 — Controle de Pessoas

**Prioridade:** MEDIUM — complementa reservas com gestao de convidados e prestadores
**Dependencias:** Fase 6 (reservas como contexto para convidados)
**Contexto:** Tenant Domain > People Control

### 8.1 People Domain

**Entidades:**

- `App\Domain\People\Entities\Guest`:
  - Campos: id, reservation_id, name, document, phone, status, checked_in_at, checked_out_at, checked_in_by, denied_by, denied_reason
- `App\Domain\People\Entities\ServiceProvider`:
  - Campos: id, company_name, name, document, phone, service_type, is_active, created_by, created_at
- `App\Domain\People\Entities\ServiceProviderVisit`:
  - Campos: id, service_provider_id, unit_id, scheduled_date, purpose, status, checked_in_at, checked_out_at, checked_in_by, notes
- `App\Domain\People\Enums\GuestStatus`:
  - `registered`, `checked_in`, `checked_out`, `denied`, `no_show`

**Events:**

- `GuestCheckedIn`, `GuestCheckedOut`, `GuestAccessDenied`
- `ServiceProviderCheckedIn`, `ServiceProviderCheckedOut`

**Migrations (tenant DB):**

- `create_guests_table`
- `create_service_providers_table`
- `create_service_provider_visits_table`

### 8.2 People Application

**Use Cases:**

- `RegisterGuest`:
  - Vincula guest a uma reserva
  - Valida limite de convidados (guest_count do espaco)
  - Valida que a reserva esta confirmada
- `CheckInGuest`:
  - Portaria registra entrada
  - Valida que a reserva esta no horario (in_progress)
  - Atualiza status para `checked_in`
- `CheckOutGuest`:
  - Portaria registra saida
  - Atualiza status para `checked_out`
- `DenyGuestAccess`:
  - Portaria nega acesso com justificativa obrigatoria
  - Emite evento `GuestAccessDenied`
- `RegisterServiceProvider`:
  - Cadastro de prestador de servico no condominio
- `ScheduleServiceProviderVisit`:
  - Agenda visita de prestador vinculada a uma unidade
- `CheckInServiceProvider`:
  - Portaria registra entrada do prestador
- `CheckOutServiceProvider`:
  - Portaria registra saida do prestador

### 8.3 Tenant API (Pessoas)

Controllers:

- `App\Interface\Http\Controllers\Tenant\GuestController`
- `App\Interface\Http\Controllers\Tenant\ServiceProviderController`
- `App\Interface\Http\Controllers\Tenant\ServiceProviderVisitController`

Rotas em `routes/tenant.php`:

```
GET/POST       /tenant/reservations/{id}/guests
POST           /tenant/guests/{id}/check-in
POST           /tenant/guests/{id}/check-out
POST           /tenant/guests/{id}/deny
GET/POST       /tenant/service-providers
GET/PUT        /tenant/service-providers/{id}
GET/POST       /tenant/service-provider-visits
GET            /tenant/service-provider-visits/{id}
POST           /tenant/service-provider-visits/{id}/check-in
POST           /tenant/service-provider-visits/{id}/check-out
```

### 8.4 Testes da Fase 8

- **Unit:**
  - Guest entity: status transitions, validacoes
  - ServiceProvider: validacoes de documento, dados
- **Feature:**
  - Registro de convidado + check-in + check-out (fluxo completo)
  - Negacao de acesso com justificativa
  - Cadastro de prestador + agendamento + check-in + check-out
  - Validacoes: limite de convidados, reserva fora de horario, reserva nao confirmada
- **Contract:**
  - People API: todos os endpoints

### Entregavel da Fase 8

Controle completo de convidados (vinculados a reservas) e prestadores de servico com check-in/check-out pela portaria. Rastreabilidade total de acessos.

---

## 11. Fase 9 — Comunicacao

**Prioridade:** MEDIUM — melhora experiencia do usuario
**Dependencias:** Fase 4 (unidades como base de audiencia)
**Contexto:** Tenant Domain > Communication

### 9.1 Communication Domain

**Entidades:**

- `App\Domain\Communication\Entities\Announcement`:
  - Campos: id, title, body, priority, audience_type (all, block, units), audience_ids (jsonb), status, published_by, published_at, archived_at
- `App\Domain\Communication\Entities\AnnouncementRead`:
  - Campos: id, announcement_id, tenant_user_id, read_at
- `App\Domain\Communication\Entities\SupportRequest`:
  - Campos: id, unit_id, resident_id, subject, category, status, priority, assigned_to, created_at, resolved_at, closed_at
- `App\Domain\Communication\Entities\SupportMessage`:
  - Campos: id, support_request_id, author_id, author_type, body, created_at

**Enums:**

- `AnnouncementStatus`: `draft`, `published`, `archived`
- `SupportRequestStatus`: `open`, `in_progress`, `waiting_response`, `resolved`, `closed`

**Events:**

- `AnnouncementPublished`, `SupportRequestCreated`, `SupportRequestResolved`

**Migrations (tenant DB):**

- `create_announcements_table`
- `create_announcement_reads_table`
- `create_support_requests_table`
- `create_support_messages_table`

### 9.2 Communication Application

**Use Cases:**

- `PublishAnnouncement`:
  - Sindico/administradora publica aviso
  - Define audiencia (todos, bloco especifico, unidades especificas)
  - Emite evento para notificacao
- `MarkAnnouncementAsRead`:
  - Condomino marca aviso como lido
  - Idempotente
- `ArchiveAnnouncement`:
  - Sindico arquiva aviso antigo
- `CreateSupportRequest`:
  - Condomino cria solicitacao de atendimento
  - Categorias: manutencao, reclamacao, sugestao, duvida, outro
- `ReplySupportRequest`:
  - Condomino ou sindico adiciona mensagem
  - Atualiza status conforme autor
- `ResolveSupportRequest`:
  - Sindico marca como resolvida
- `CloseSupportRequest`:
  - Condomino confirma resolucao ou sistema fecha automaticamente apos periodo

### 9.3 Tenant API (Comunicacao)

Controllers:

- `App\Interface\Http\Controllers\Tenant\AnnouncementController`
- `App\Interface\Http\Controllers\Tenant\SupportRequestController`

Rotas em `routes/tenant.php`:

```
GET/POST       /tenant/announcements
GET            /tenant/announcements/{id}
POST           /tenant/announcements/{id}/archive
POST           /tenant/announcements/{id}/read
GET/POST       /tenant/support-requests
GET            /tenant/support-requests/{id}
POST           /tenant/support-requests/{id}/messages
POST           /tenant/support-requests/{id}/resolve
POST           /tenant/support-requests/{id}/close
```

### 9.4 Integracao de Notificacao

Event handlers:

- `AnnouncementPublished` -> enviar push/email para audiencia do aviso
- `SupportRequestCreated` -> notificar sindico/administradora
- `SupportRequestResolved` -> notificar solicitante

### 9.5 Testes da Fase 9

- **Unit:**
  - Announcement: validacao de audiencia, status transitions
  - SupportRequest: status transitions, invariantes
- **Feature:**
  - Publicar aviso para audiencias diferentes
  - Marcar como lido (idempotencia)
  - Fluxo de solicitacao: criar -> responder -> resolver -> fechar
- **Contract:**
  - Communication API: todos os endpoints

### Entregavel da Fase 9

Modulo completo de comunicacao com avisos segmentados (por audiencia), confirmacao de leitura, solicitacoes de atendimento com thread de mensagens e notificacoes integradas.

---

## 12. Fase 10 — Dashboards e Relatorios

**Prioridade:** MEDIUM — adiciona visibilidade operacional
**Dependencias:** Fase 6 (reservas como principal fonte de dados)
**Contexto:** Transversal

### 10.1 Tenant Dashboard

**Endpoints:**

- `GET /tenant/dashboard` (sindico/administradora):
  - Reservas: total do mes, por status, por espaco
  - Espacos: taxa de ocupacao, espaco mais utilizado
  - Violacoes: total aberto, pendentes de revisao
  - Penalidades: ativas, aplicadas no mes
  - Residents: total ativos, novos no mes
  - Support requests: abertas, tempo medio de resolucao

- `GET /tenant/dashboard/resident` (condomino):
  - Minhas reservas: proximas, historico
  - Minhas violacoes/penalidades (se houver)
  - Avisos nao lidos
  - Solicitacoes abertas

### 10.2 Platform Dashboard

**Endpoints:**

- `GET /platform/dashboard`:
  - Tenants: total ativos, novos no mes, churn rate
  - Revenue: MRR, ARR, crescimento
  - Subscriptions: por plano, em trial, inadimplentes
  - Usage: reservas totais na plataforma, tenants mais ativos

- `GET /platform/tenants/{id}/metrics`:
  - Metricas de uso do tenant especifico
  - Reservas, usuarios ativos, storage

### 10.3 Testes da Fase 10

- **Feature:**
  - Dashboard tenant: dados agregados corretos
  - Dashboard platform: metricas globais corretas
  - Isolamento: tenant so ve seus dados
- **Contract:**
  - Dashboard API: response schemas

### Entregavel da Fase 10

Dashboards operacionais para sindico/administradora (visao do condominio), condomino (visao pessoal) e platform admin (visao global). Dados agregados para tomada de decisao.

---

## 13. Fase 11 — IA e Assistente

**Prioridade:** LOW — diferenciador, construido apos core estavel
**Dependencias:** Fase 9 (comunicacao como fonte de dados para embeddings)
**Contexto:** Contexto Transversal > AI Assistant

### 11.1 AI Infrastructure

**Application:**

- `App\Application\AI\Contracts\AIProviderInterface`:
  - `chat(array $messages, array $tools = []): AIResponse`
  - `generateEmbedding(string $text): array`
- `App\Application\AI\Contracts\EmbeddingServiceInterface`:
  - `embed(string $text): array`
  - `search(array $embedding, int $limit): Collection`

**Infrastructure:**

- `App\Infrastructure\Gateways\AI\OpenAIProvider` — implementa `AIProviderInterface`
- Configuracao: API key, modelo, limites de tokens

**Migrations (tenant DB):**

- `create_embeddings_table` (com pgvector):
  - id, content_type, content_id, content_text, embedding (vector), metadata (jsonb), created_at
- `create_ai_usage_logs_table`:
  - id, tenant_user_id, action, tokens_used, model, cost, created_at
- `create_ai_action_logs_table`:
  - id, conversation_id, action, parameters, status, result, created_at

### 11.2 Conversational Assistant

- `App\Application\AI\ConversationalAssistant`:
  - Orquestra conversas com o usuario
  - Mantém contexto da conversa
  - Resolve intencoes e despacha para use cases
- `App\Application\AI\ActionOrchestrator`:
  - Mapeia intencoes para acoes do sistema
  - Requer confirmacao humana para mutacoes
- `App\Application\AI\ToolRegistry`:
  - Registra ferramentas disponiveis (consultas, acoes)
  - Mapeia para use cases existentes:
    - "Quais horarios disponiveis?" -> `ListAvailableSlots`
    - "Reservar o salao" -> `CreateReservation` (com confirmacao)
    - "Minhas reservas" -> `ListReservations`
    - "Regras do condominio" -> busca semantica em CondominiumRules

**Use Cases:**

- `ProcessConversation`:
  - Recebe mensagem do usuario
  - Enriquece com contexto (tenant, usuario, historico)
  - Envia para AI provider com tools disponiveis
  - Processa tool calls (leitura direta, mutacao com confirmacao)
  - Retorna resposta
- `GenerateEmbedding`:
  - Gera embedding para conteudo (avisos, regras, FAQs)
  - Armazena no banco com pgvector
- `SemanticSearch`:
  - Busca por similaridade semantica
  - Filtra por tipo de conteudo
  - Retorna resultados rankeados

### 11.3 AI API

Controller: `App\Interface\Http\Controllers\AI\ConversationController`

Rotas em `routes/tenant.php`:

```
POST           /tenant/ai/conversations
POST           /tenant/ai/conversations/{id}/messages
POST           /tenant/ai/conversations/{id}/confirm-action
GET            /tenant/ai/conversations/{id}
```

Fluxo de confirmacao:

1. Usuario envia mensagem com intencao de mutacao
2. AI identifica acao e parametros
3. Sistema retorna preview da acao para confirmacao
4. Usuario confirma
5. Sistema executa acao e retorna resultado

### 11.4 Background Jobs

- `App\Infrastructure\Jobs\AI\GenerateEmbeddingsJob`:
  - Processado quando novos avisos, regras ou FAQs sao criados
  - Gera embeddings em batch
  - Idempotente (reprocessa se necessario)

### 11.5 Testes da Fase 11

- **Unit:**
  - ToolRegistry: registro, busca, mapeamento correto
  - ActionOrchestrator: identificacao de acoes, validacao de parametros
- **Feature:**
  - Fluxo de conversa: pergunta -> resposta (sem acao)
  - Fluxo de acao: intencao -> preview -> confirmacao -> execucao
  - Busca semantica: indexacao + consulta
  - Rate limiting e controle de custo
- **Integration:**
  - Embedding generation para avisos e regras
  - Busca semantica retornando resultados relevantes

### Entregavel da Fase 11

Assistente conversacional com IA capaz de responder perguntas sobre o condominio, consultar disponibilidade, sugerir reservas (com confirmacao) e realizar busca semantica em regras e avisos. Isolamento total por tenant.

---

## 14. Fase 12 — Observabilidade e Hardening

**Prioridade:** LOW — maturidade operacional
**Dependencias:** Fase 6 (pode iniciar em paralelo a partir deste ponto)
**Contexto:** Contexto Transversal > Observability

### 12.1 Observability

- Structured JSON logging (substituir logs texto por JSON):
  - Formato: `{"timestamp", "level", "message", "tenant_id", "user_id", "correlation_id", "trace_id", "context"}`
- Health check endpoint: `GET /health`
  - Verifica: database platform, database tenant (sample), Redis, queue worker
  - Retorna: status, latency por componente
- Metricas (formato Prometheus):
  - Requests por segundo, latencia P50/P95/P99
  - Reservas criadas/canceladas por minuto
  - Erros por tipo
  - Queue depth e processing time
- SLI/SLO:
  - Disponibilidade: 99.5%
  - Latencia P95: < 500ms
  - Error rate: < 1%
- Alertas:
  - Error rate > threshold
  - Latencia > SLO
  - Queue backlog crescente
  - Tenant provisioning failure

### 12.2 Performance

- Query optimization:
  - Indices para queries frequentes (reservations por space+date, violations por unit)
  - EXPLAIN ANALYZE nas queries mais criticas
  - N+1 detection e correcao
- Cache strategy (Redis):
  - Tenant config: cache por request
  - Feature flags: cache com TTL 5 min
  - Space availability: cache com invalidacao ao criar/cancelar reserva
  - Dashboard metrics: cache com TTL 1 min
- Connection pooling tuning:
  - PgBouncer ou configuracao nativa
  - Pool size por tipo de conexao (platform vs tenant)
- Load testing:
  - Cenario: 100 tenants, 1000 usuarios simultaneos
  - Foco: criacao de reserva com conflict check
  - Ferramentas: k6 ou JMeter

### 12.3 Security Hardening

- Penetration testing checklist
- OWASP API Security Top 10:
  - Broken Object Level Authorization (BOLA)
  - Broken Authentication
  - Broken Object Property Level Authorization
  - Unrestricted Resource Consumption
  - Broken Function Level Authorization
  - Server Side Request Forgery
  - Security Misconfiguration
  - Lack of Protection from Automated Threats
  - Improper Assets Management
  - Unsafe Consumption of APIs
- Rate limiting fine-tuning:
  - Por tenant
  - Por usuario
  - Por endpoint (mais restritivo em mutacoes)
- LGPD compliance verification:
  - Dados pessoais mapeados
  - Direitos do titular implementados (acesso, correcao, exclusao)
  - Politica de retencao funcionando
  - Anonimizacao implementada

### Entregavel da Fase 12

Sistema com logging estruturado, health checks, metricas, alertas, performance otimizada, caching configurado, security hardening aplicado e compliance LGPD verificado. Pronto para operacao em producao com SLOs definidos.

---

## 15. Resumo de Fases

| Fase | Nome | Prioridade | Dependencias | Entregas Principais |
|------|------|-----------|-------------|-------------------|
| 0 | Setup do Projeto | CRITICAL | Nenhuma | Estrutura DDD, CI/CD, testes arquiteturais |
| 1 | Multi-Tenancy | CRITICAL | Fase 0 | Tenants, provisionamento, resolucao por request |
| 2 | Auth e Autorizacao | CRITICAL | Fase 1 | Login (platform+tenant), JWT RS256, RBAC, audit |
| 3 | Billing | HIGH | Fase 2 | Planos, assinaturas, faturas, pagamentos, dunning |
| 4 | Units e Moradores | HIGH | Fase 2 | Blocos, unidades, convites, ativacao |
| 5 | Espacos | HIGH | Fase 4 | Espacos, disponibilidade, bloqueios, regras |
| 6 | Reservas | CRITICAL | Fase 5 | Reservas, conflitos, aprovacao, no-show |
| 7 | Governanca | MEDIUM-HIGH | Fase 6 | Violacoes, penalidades, contestacao, escalation |
| 8 | Controle de Pessoas | MEDIUM | Fase 6 | Convidados, prestadores, check-in/out |
| 9 | Comunicacao | MEDIUM | Fase 4 | Avisos segmentados, solicitacoes, suporte |
| 10 | Dashboards | MEDIUM | Fase 6 | Metricas agregadas, visao operacional |
| 11 | IA | LOW | Fase 9 | Assistente conversacional, embeddings, busca semantica |
| 12 | Observabilidade | LOW | Fase 6 | Logs estruturados, metricas, alertas, hardening |

---

## 16. Diagrama de Dependencias

```
Fase 0 (Setup)
  └── Fase 1 (Multi-Tenancy)
       └── Fase 2 (Auth)
            ├── Fase 3 (Billing)
            └── Fase 4 (Units)
                 ├── Fase 5 (Espacos)
                 │    └── Fase 6 (Reservas) ← CORE FEATURE
                 │         ├── Fase 7 (Governanca)
                 │         ├── Fase 8 (Pessoas)
                 │         └── Fase 10 (Dashboards)
                 └── Fase 9 (Comunicacao)
                      └── Fase 11 (IA)

Fase 12 (Observabilidade) ← paralela a partir da Fase 6
```

### Leitura do Diagrama

- Cada fase so pode iniciar apos a conclusao de suas dependencias diretas
- Fases 3 e 4 podem ser desenvolvidas em paralelo (ambas dependem apenas da Fase 2)
- Fases 7, 8 e 10 podem ser desenvolvidas em paralelo (todas dependem da Fase 6)
- Fase 12 e independente e pode iniciar assim que a Fase 6 estiver estavel
- O caminho critico e: 0 -> 1 -> 2 -> 4 -> 5 -> 6 (seis fases ate o core feature)

---

## 17. Status

Documento **ATIVO**.

Este roadmap e um documento vivo. Qualquer alteracao deve ser consciente, justificada e registrada. A ordem de fases reflete dependencias tecnicas reais e nao deve ser alterada sem analise de impacto.

Documentos relacionados:

- `project-overview.md` — visao geral do produto
- `bounded-contexts.md` — contextos delimitados e fronteiras
- `project-structure.md` — estrutura de pastas do projeto
- `multi-tenancy-implementation.md` — estrategia de multi-tenancy
- `domain-model.md` — modelo de dominio completo
- `use-cases.md` — catalogo de casos de uso
- `roadmap-tecnico-skills.md` — roadmap de skills do Claude
