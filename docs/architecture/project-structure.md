# Project Structure — Estrutura de Pastas do Projeto

## 1. Visão Geral

A estrutura segue **Clean Architecture + DDD**, separando claramente Domain, Application, Infrastructure e Interface. A organização é **por contexto delimitado** (Bounded Context), não por tipo de arquivo.

---

## 2. Estrutura Raiz

```
condominium-events-manage-api/
├── app/
│   ├── Domain/                    # Domain Layer (regras de negócio puras)
│   ├── Application/               # Application Layer (use cases, DTOs, interfaces)
│   ├── Infrastructure/            # Infrastructure Layer (implementações)
│   └── Interface/                 # Interface Layer (controllers, middleware, requests)
│
├── config/                        # Configurações do Laravel
├── database/
│   ├── migrations/
│   │   ├── platform/              # Migrations do banco global
│   │   └── tenant/                # Migrations do banco de cada tenant
│   ├── seeders/
│   │   ├── platform/              # Seeds da plataforma
│   │   └── tenant/                # Seeds padrão de cada tenant
│   └── factories/                 # Factories para testes
│
├── routes/
│   ├── platform.php               # Rotas da plataforma (admin, billing)
│   ├── tenant.php                 # Rotas do tenant (espaços, reservas, etc.)
│   └── webhook.php                # Rotas de webhooks (billing, integrações)
│
├── tests/
│   ├── Unit/                      # Testes de domínio e unitários
│   ├── Feature/                   # Testes de use cases e integração
│   ├── Architecture/              # Testes arquiteturais
│   └── Contract/                  # Testes de contrato de API
│
├── docs/                          # Documentação do projeto
├── .claude/                       # Skills e configuração do Claude
└── ...
```

---

## 3. Domain Layer

```
app/Domain/
├── Shared/                        # Tipos e contratos compartilhados
│   ├── ValueObjects/
│   │   ├── DateRange.php
│   │   ├── Money.php
│   │   └── Uuid.php
│   ├── Events/
│   │   └── DomainEvent.php        # Interface base
│   └── Exceptions/
│       └── DomainException.php    # Exception base de domínio
│
├── Tenant/                        # Contexto: Gestão de Tenants
│   ├── Entities/
│   │   └── Tenant.php
│   ├── Enums/
│   │   └── TenantStatus.php
│   └── Events/
│       ├── TenantCreated.php
│       └── TenantSuspended.php
│
├── Billing/                       # Contexto: Billing
│   ├── Entities/
│   │   ├── Subscription.php
│   │   ├── Invoice.php
│   │   ├── InvoiceItem.php
│   │   └── Payment.php
│   ├── Enums/
│   │   ├── SubscriptionStatus.php
│   │   ├── InvoiceStatus.php
│   │   └── PaymentStatus.php
│   └── Events/
│       ├── SubscriptionActivated.php
│       ├── InvoiceIssued.php
│       └── PaymentConfirmed.php
│
├── Unit/                          # Contexto: Unidades e Moradores
│   ├── Entities/
│   │   ├── Block.php
│   │   ├── Unit.php
│   │   ├── Resident.php
│   │   └── TenantUser.php
│   ├── Enums/
│   │   ├── UnitType.php
│   │   └── ResidentRole.php
│   └── Events/
│       ├── ResidentInvited.php
│       └── ResidentActivated.php
│
├── Space/                         # Contexto: Espaços
│   ├── Entities/
│   │   ├── Space.php
│   │   ├── SpaceAvailability.php
│   │   ├── SpaceBlock.php
│   │   └── SpaceRule.php
│   ├── Enums/
│   │   ├── SpaceStatus.php
│   │   └── SpaceType.php
│   └── Events/
│       └── SpaceCreated.php
│
├── Reservation/                   # Contexto: Reservas (Aggregate Root)
│   ├── Entities/
│   │   └── Reservation.php        # Aggregate Root
│   ├── Enums/
│   │   └── ReservationStatus.php
│   ├── Services/
│   │   └── ConflictChecker.php    # Domain Service
│   ├── Exceptions/
│   │   ├── ConflictException.php
│   │   └── BlockedException.php
│   └── Events/
│       ├── ReservationRequested.php
│       ├── ReservationConfirmed.php
│       ├── ReservationCanceled.php
│       └── ReservationNoShow.php
│
├── Governance/                    # Contexto: Governança
│   ├── Entities/
│   │   ├── CondominiumRule.php
│   │   ├── Violation.php
│   │   ├── Penalty.php
│   │   ├── PenaltyPolicy.php
│   │   └── ViolationContestation.php
│   ├── Enums/
│   │   ├── ViolationType.php
│   │   ├── PenaltyType.php
│   │   └── Severity.php
│   └── Events/
│       ├── ViolationRegistered.php
│       └── PenaltyApplied.php
│
├── People/                        # Contexto: Controle de Pessoas
│   ├── Entities/
│   │   ├── Guest.php
│   │   ├── ServiceProvider.php
│   │   └── ReservationServiceProvider.php
│   ├── Enums/
│   │   └── GuestStatus.php
│   └── Events/
│       ├── GuestCheckedIn.php
│       └── AccessDenied.php
│
└── Communication/                 # Contexto: Comunicação
    ├── Entities/
    │   ├── Announcement.php
    │   ├── AnnouncementRead.php
    │   ├── SupportRequest.php
    │   └── SupportMessage.php
    ├── Enums/
    │   ├── AnnouncementStatus.php
    │   └── SupportRequestStatus.php
    └── Events/
        ├── AnnouncementPublished.php
        └── SupportRequestCreated.php
```

---

## 4. Application Layer

```
app/Application/
├── Shared/
│   ├── Contracts/
│   │   ├── EventDispatcherInterface.php
│   │   ├── NotificationServiceInterface.php
│   │   └── FeatureResolverInterface.php
│   └── DTOs/
│       └── PaginationDTO.php
│
├── Tenant/
│   ├── UseCases/
│   │   ├── ProvisionTenant.php
│   │   ├── SuspendTenant.php
│   │   └── ...
│   ├── Contracts/
│   │   └── TenantRepositoryInterface.php
│   └── DTOs/
│       └── CreateTenantDTO.php
│
├── Billing/
│   ├── UseCases/
│   │   ├── CreateSubscription.php
│   │   ├── GenerateInvoice.php
│   │   ├── ProcessPayment.php
│   │   └── ...
│   ├── Contracts/
│   │   ├── SubscriptionRepositoryInterface.php
│   │   ├── InvoiceRepositoryInterface.php
│   │   └── PaymentGatewayInterface.php
│   └── DTOs/
│
├── Unit/
│   ├── UseCases/
│   │   ├── CreateBlock.php
│   │   ├── CreateUnit.php
│   │   ├── InviteResident.php
│   │   └── ...
│   ├── Contracts/
│   │   ├── UnitRepositoryInterface.php
│   │   └── ResidentRepositoryInterface.php
│   └── DTOs/
│
├── Space/
│   ├── UseCases/
│   │   ├── CreateSpace.php
│   │   ├── SetSpaceAvailability.php
│   │   └── ...
│   ├── Contracts/
│   │   └── SpaceRepositoryInterface.php
│   └── DTOs/
│
├── Reservation/
│   ├── UseCases/
│   │   ├── CreateReservation.php
│   │   ├── ApproveReservation.php
│   │   ├── CancelReservation.php
│   │   ├── MarkAsNoShow.php
│   │   └── ...
│   ├── Contracts/
│   │   └── ReservationRepositoryInterface.php
│   └── DTOs/
│
├── Governance/
│   ├── UseCases/
│   │   ├── RegisterViolation.php
│   │   ├── ApplyPenalty.php
│   │   ├── ContestViolation.php
│   │   └── ...
│   ├── Contracts/
│   └── DTOs/
│
├── People/
│   ├── UseCases/
│   │   ├── RegisterGuest.php
│   │   ├── CheckInGuest.php
│   │   ├── RegisterServiceProvider.php
│   │   └── ...
│   ├── Contracts/
│   └── DTOs/
│
├── Communication/
│   ├── UseCases/
│   │   ├── PublishAnnouncement.php
│   │   ├── CreateSupportRequest.php
│   │   └── ...
│   ├── Contracts/
│   └── DTOs/
│
└── AI/
    ├── ConversationalAssistant.php
    ├── ActionOrchestrator.php
    ├── ToolRegistry.php
    ├── Contracts/
    │   ├── AIProviderInterface.php
    │   └── EmbeddingServiceInterface.php
    ├── UseCases/
    │   ├── ProcessConversation.php
    │   ├── GenerateEmbedding.php
    │   └── SemanticSearch.php
    └── DTOs/
```

---

## 5. Infrastructure Layer

```
app/Infrastructure/
├── Persistence/
│   ├── Platform/                  # Models e Repos do banco global
│   │   ├── Models/
│   │   │   ├── TenantModel.php
│   │   │   ├── PlanModel.php
│   │   │   ├── SubscriptionModel.php
│   │   │   └── ...
│   │   └── Repositories/
│   │       ├── EloquentTenantRepository.php
│   │       └── ...
│   │
│   └── Tenant/                    # Models e Repos do banco do tenant
│       ├── Models/
│       │   ├── UnitModel.php
│       │   ├── SpaceModel.php
│       │   ├── ReservationModel.php
│       │   └── ...
│       └── Repositories/
│           ├── EloquentUnitRepository.php
│           ├── EloquentSpaceRepository.php
│           ├── EloquentReservationRepository.php
│           └── ...
│
├── MultiTenancy/
│   ├── TenantManager.php          # Gerencia resolução e troca de conexão
│   ├── TenantContext.php           # Contexto do tenant atual
│   └── TenantDatabaseCreator.php  # Cria database/schema para novos tenants
│
├── Events/
│   ├── LaravelEventDispatcher.php  # Implementa EventDispatcherInterface
│   └── Handlers/                   # Event handlers
│       ├── Reservation/
│       │   ├── NotifyOnReservationConfirmed.php
│       │   └── RegisterViolationOnNoShow.php
│       ├── Billing/
│       └── ...
│
├── Jobs/
│   ├── Tenant/
│   │   └── ProvisionTenantJob.php
│   ├── Billing/
│   │   ├── ProcessPastDueInvoicesJob.php
│   │   ├── RetryFailedPaymentsJob.php
│   │   └── SuspendOverdueTenantsJob.php
│   ├── AI/
│   │   └── GenerateEmbeddingsJob.php
│   └── Maintenance/
│       └── CleanupExpiredDataJob.php
│
├── Gateways/
│   ├── Payment/
│   │   ├── StripeGateway.php       # Implementa PaymentGatewayInterface
│   │   └── ...
│   └── AI/
│       ├── OpenAIProvider.php       # Implementa AIProviderInterface
│       └── ...
│
├── Notifications/
│   ├── EmailNotificationAdapter.php
│   └── Templates/
│
└── Services/
    ├── FeatureResolver.php          # Implementa FeatureResolverInterface
    └── ...
```

---

## 6. Interface Layer

```
app/Interface/
├── Http/
│   ├── Controllers/
│   │   ├── Platform/
│   │   │   ├── TenantController.php
│   │   │   ├── PlanController.php
│   │   │   ├── SubscriptionController.php
│   │   │   └── PlatformAdminController.php
│   │   │
│   │   ├── Tenant/
│   │   │   ├── UnitController.php
│   │   │   ├── SpaceController.php
│   │   │   ├── ReservationController.php
│   │   │   ├── GovernanceController.php
│   │   │   ├── GuestController.php
│   │   │   ├── ServiceProviderController.php
│   │   │   ├── AnnouncementController.php
│   │   │   └── SupportRequestController.php
│   │   │
│   │   ├── Auth/
│   │   │   └── AuthController.php
│   │   │
│   │   ├── AI/
│   │   │   └── ConversationController.php
│   │   │
│   │   └── Webhook/
│   │       └── BillingWebhookController.php
│   │
│   ├── Middleware/
│   │   ├── ResolveTenantMiddleware.php
│   │   ├── EnsureTenantActive.php
│   │   ├── EnsureSubscriptionValid.php
│   │   └── CheckFeatureAccess.php
│   │
│   ├── Requests/                   # Form Requests (validação)
│   │   ├── Platform/
│   │   └── Tenant/
│   │
│   └── Resources/                  # API Resources (transformação)
│       ├── Platform/
│       └── Tenant/
│
└── Console/
    └── Commands/
        ├── RunTenantMigrationsCommand.php
        └── ...
```

---

## 7. Testes

```
tests/
├── Unit/
│   ├── Domain/
│   │   ├── Reservation/
│   │   │   ├── ReservationTest.php
│   │   │   └── ConflictCheckerTest.php
│   │   ├── Governance/
│   │   └── ...
│   └── Application/
│       └── ...
│
├── Feature/
│   ├── Reservation/
│   │   ├── CreateReservationTest.php
│   │   ├── ApproveReservationTest.php
│   │   └── ...
│   ├── Space/
│   ├── Governance/
│   └── ...
│
├── Architecture/
│   ├── LayerDependencyTest.php
│   ├── TenantIsolationTest.php
│   └── ...
│
└── Contract/
    ├── Platform/
    │   └── TenantApiContractTest.php
    └── Tenant/
        ├── ReservationApiContractTest.php
        └── ...
```

---

## 8. Convenções

| Item | Convenção |
|------|-----------|
| Namespaces | `App\Domain\Reservation\Entities\Reservation` |
| Use Cases | Uma classe por caso de uso, método `execute()` |
| DTOs | Imutáveis, tipados, sem lógica |
| Events | Nomeados no passado: `ReservationConfirmed` |
| Jobs | Sufixo `Job`: `ProvisionTenantJob` |
| Repositories | Interface: `ReservationRepositoryInterface`, Impl: `EloquentReservationRepository` |
| Controllers | Magros: recebem, delegam, retornam |
| Models (Eloquent) | Sufixo `Model`: `ReservationModel` (para não confundir com Entity) |

---

## 9. Status

Documento **ATIVO**. Define a organização física do código.

A estrutura deve ser protegida por testes arquiteturais.
