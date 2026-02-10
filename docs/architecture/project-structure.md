# Project Structure — Estrutura de Pastas do Projeto

## 1. Visão Geral

A estrutura segue **Clean Architecture + DDD**, separando claramente Domain, Application, Infrastructure e Interface. A organização é **por contexto delimitado** (Bounded Context), não por tipo de arquivo.

As camadas Domain e Application vivem em `src/` com namespaces próprios (`Domain\` e `Application\`), completamente desacopladas do Laravel. As camadas Infrastructure e Interface permanecem em `app/` sob o namespace `App\`.

---

## 2. Estrutura Raiz

```
condominium-events-manage-api/
├── src/
│   ├── Domain/                    # Domain Layer (regras de negócio puras, zero Laravel)
│   └── Application/               # Application Layer (use cases, DTOs, ports/interfaces)
│
├── app/
│   ├── Infrastructure/            # Infrastructure Layer (implementações concretas)
│   ├── Interface/                 # Interface Layer (controllers, middleware, requests)
│   ├── Http/                      # Laravel default (mantido para compatibilidade)
│   ├── Models/                    # Laravel default (mantido para compatibilidade)
│   └── Providers/                 # Service Providers (bindings)
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

## 3. Mapeamento de Namespaces

| Layer | Namespace | Diretório | PSR-4 (composer.json) |
|-------|-----------|-----------|----------------------|
| Domain | `Domain\` | `src/Domain/` | `"Domain\\": "src/Domain/"` |
| Application | `Application\` | `src/Application/` | `"Application\\": "src/Application/"` |
| Infrastructure | `App\Infrastructure\` | `app/Infrastructure/` | `"App\\": "app/"` |
| Interface | `App\Interface\` | `app/Interface/` | `"App\\": "app/"` |

### Regras de Dependência

```
Domain\           → NÃO depende de nada externo (sem Illuminate\, sem App\, sem Application\)
Application\      → Pode usar Domain\. NÃO pode usar App\, Illuminate\
App\Infrastructure\ → Pode usar Domain\ e Application\
App\Interface\    → Pode usar Application\. NÃO usa Domain\ diretamente
```

A separação física em `src/` torna impossível Domain ou Application importarem classes do Laravel acidentalmente. Isso é reforçado por testes arquiteturais com Pest.

---

## 4. Domain Layer

```
src/Domain/
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

## 5. Application Layer

```
src/Application/
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

## 6. Infrastructure Layer

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

## 7. Interface Layer

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

## 8. Testes

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

## 9. Referências Cross-Layer

Infrastructure referencia Domain e Application via injeção de dependência:

```
App\Infrastructure\Persistence\Tenant\Repositories\EloquentReservationRepository
  implements Application\Reservation\Contracts\ReservationRepositoryInterface
  e trabalha com Domain\Reservation\Entities\Reservation

App\Infrastructure\Events\LaravelEventDispatcher
  implements Application\Shared\Contracts\EventDispatcherInterface
```

Service Providers em `app/Providers/` fazem o binding:

```php
// app/Providers/RepositoryServiceProvider.php
$this->app->bind(
    \Application\Reservation\Contracts\ReservationRepositoryInterface::class,
    \App\Infrastructure\Persistence\Tenant\Repositories\EloquentReservationRepository::class
);
```

---

## 10. Convenções

| Item | Convenção |
|------|-----------|
| Namespaces (Domain) | `Domain\Reservation\Entities\Reservation` |
| Namespaces (Application) | `Application\Reservation\UseCases\CreateReservation` |
| Namespaces (Infrastructure) | `App\Infrastructure\Persistence\...` |
| Namespaces (Interface) | `App\Interface\Http\Controllers\...` |
| Use Cases | Uma classe por caso de uso, método `execute()` |
| DTOs | Imutáveis, tipados, sem lógica |
| Events | Nomeados no passado: `ReservationConfirmed` |
| Jobs | Sufixo `Job`: `ProvisionTenantJob` |
| Repositories | Interface: `ReservationRepositoryInterface`, Impl: `EloquentReservationRepository` |
| Controllers | Magros: recebem, delegam, retornam |
| Models (Eloquent) | Sufixo `Model`: `ReservationModel` (para não confundir com Entity) |

---

## 11. Status

Documento **ATIVO**. Define a organização física do código.

A estrutura deve ser protegida por testes arquiteturais.
