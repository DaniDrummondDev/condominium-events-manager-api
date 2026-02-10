# Technical Architecture — Arquitetura Técnica

## 1. Visão Geral

O **Condominium Events Manager API** é uma aplicação **API-first**, construída com:

- **Framework:** Laravel (PHP)
- **Banco de dados:** PostgreSQL (produção), SQLite (testes)
- **Extensões:** pgvector (embeddings de IA)
- **Arquitetura:** DDD + Clean Architecture + SOLID
- **Padrão:** Multi-tenant com isolamento por database/schema

---

## 2. Princípios Arquiteturais

### 2.1 Clean Architecture

O sistema segue Clean Architecture com **4 camadas concêntricas**:

```
┌─────────────────────────────────────────────┐
│              Interface Layer                │
│         (Controllers, Middleware)           │
│  ┌───────────────────────────────────────┐  │
│  │          Application Layer            │  │
│  │     (Use Cases, DTOs, Services)       │  │
│  │  ┌─────────────────────────────────┐  │  │
│  │  │         Domain Layer            │  │  │
│  │  │  (Entities, VOs, Events, Rules) │  │  │
│  │  └─────────────────────────────────┘  │  │
│  └───────────────────────────────────────┘  │
└─────────────────────────────────────────────┘
┌─────────────────────────────────────────────┐
│          Infrastructure Layer               │
│   (DB, Queues, APIs, Gateways, Providers)  │
└─────────────────────────────────────────────┘
```

### 2.2 Regra de Dependência

Dependências apontam **sempre para dentro**:

- Interface → Application → Domain
- Infrastructure → Application → Domain
- **Domain nunca depende de nada externo**

### 2.3 DDD (Domain-Driven Design)

- **Entities:** objetos com identidade própria
- **Value Objects:** objetos imutáveis sem identidade
- **Aggregates:** clusters de entidades com raiz (Aggregate Root)
- **Domain Events:** fatos que ocorreram no domínio
- **Repositories:** interfaces de persistência (definidas na Application, implementadas na Infrastructure)
- **Domain Services:** lógica que não pertence a uma entidade específica
- **Policies:** regras de autorização codificadas

---

## 3. Camadas em Detalhe

### 3.1 Domain Layer

**Responsabilidade:** Regras de negócio puras.

**Contém:**
- Entities (Reservation, Space, Unit, etc.)
- Value Objects (DateRange, Money, etc.)
- Domain Events (ReservationConfirmed, PenaltyApplied)
- Domain Services (ConflictChecker, PenaltyCalculator)
- Enums de domínio (ReservationStatus, ViolationType)
- Exceptions de domínio (ConflictException, PenaltyBlockedException)

**Não contém:**
- Referências a framework (Laravel)
- Acesso a banco de dados
- Chamadas HTTP
- Referências a filas ou jobs

---

### 3.2 Application Layer

**Responsabilidade:** Orquestração de casos de uso.

**Contém:**
- Use Cases (CreateReservation, ApproveReservation, etc.)
- Application Services
- DTOs (Data Transfer Objects)
- Repository Interfaces (contratos)
- Event Dispatcher Interface
- Notification Interface
- AI Service Interfaces

**Não contém:**
- Regras de negócio (delegadas ao Domain)
- Implementações de infraestrutura
- Lógica de framework

---

### 3.3 Infrastructure Layer

**Responsabilidade:** Implementações técnicas concretas.

**Contém:**
- Eloquent Repositories (implementam interfaces da Application)
- Eloquent Models (mapeamento ORM)
- Migrations
- Event Bus Implementation (Laravel Events/Queues)
- Gateway Adapters (Stripe, payment providers)
- AI Provider Adapters (OpenAI, etc.)
- Notification Adapters (e-mail, push)
- External API Clients

**Não contém:**
- Regras de negócio
- Lógica de casos de uso

---

### 3.4 Interface Layer (Presentation)

**Responsabilidade:** Tradução HTTP ↔ Application.

**Contém:**
- Controllers (recebem request, chamam Use Case, retornam response)
- Form Requests (validação de input)
- Resources/Transformers (formatação de output)
- Middleware (autenticação, tenant resolution, rate limiting)
- Route definitions

**Não contém:**
- Regras de negócio
- Acesso direto a banco
- Lógica de domínio

---

## 4. Fluxo de uma Requisição

```
HTTP Request
    │
    ▼
[Middleware: Auth + Tenant Resolution]
    │
    ▼
[Controller] → valida input via FormRequest
    │
    ▼
[Use Case] → orquestra a operação
    │
    ├── [Domain Entity] → aplica regras de negócio
    │
    ├── [Repository] → persiste dados (via interface)
    │
    ├── [Event Dispatcher] → emite eventos de domínio
    │
    └── retorna resultado
    │
    ▼
[Controller] → formata resposta via Resource
    │
    ▼
HTTP Response (JSON)
```

---

## 5. Eventos e Jobs

### 5.1 Fluxo de Eventos

```
Use Case → Domain Event → Event Handler → Job (se assíncrono)
```

### 5.2 Regras

- Eventos representam fatos passados (imutáveis)
- Handlers não contêm lógica de negócio
- Jobs são idempotentes
- Falhas vão para DLQ
- Todo evento carrega `tenant_id`, `correlation_id`, `trace_id`

---

## 6. Multi-Tenancy

### 6.1 Estratégia

- Banco da **Plataforma**: único, contém tenants, billing, planos
- Banco do **Tenant**: um database/schema por tenant (PostgreSQL)

### 6.2 Resolução de Tenant

```
Request → Middleware → Token JWT (contém tenant_id) → Conexão ao banco do tenant
```

- Tenant é resolvido **antes** de qualquer acesso a dados
- Conexão ao banco é trocada dinamicamente
- Detalhes em `multi-tenancy-implementation.md`

---

## 7. Autenticação e Autorização

### 7.1 Autenticação

- OAuth 2.1 com JWT
- Tokens de curta duração
- Refresh tokens rotacionados
- MFA para perfis sensíveis

### 7.2 Autorização

- RBAC + Policies
- Roles: identificação de perfil
- Policies: decisão final (código testável)
- Avaliação: autenticado → tenant ativo → policy permite → contexto válido

---

## 8. IA

### 8.1 Posição na Arquitetura

```
Application Layer
 └── AI/
     ├── ConversationalAssistant
     ├── ActionOrchestrator
     ├── ToolRegistry
     ├── Services (interfaces)
     └── DTOs
```

### 8.2 Regras

- IA vive na Application Layer
- IA nunca acessa o Domain diretamente
- Ações passam por Use Cases formais
- Confirmação humana obrigatória
- Provedores abstraídos por interface

---

## 9. Stack Tecnológica

| Componente | Tecnologia |
|-----------|------------|
| Framework | Laravel (PHP) |
| Banco (produção) | PostgreSQL |
| Banco (testes) | SQLite |
| Embeddings | pgvector (PostgreSQL) |
| Filas | Laravel Queues (Redis/Database) |
| Cache | Redis |
| Autenticação | JWT (OAuth 2.1) |
| Gateway de pagamento | Abstrato (Stripe como referência) |
| IA | Abstrato (OpenAI como referência) |
| CI/CD | GitHub Actions (referência) |
| Monitoramento | Logs estruturados JSON |

---

## 10. Padrões de Design Utilizados

| Padrão | Onde é usado |
|--------|-------------|
| Repository Pattern | Persistência (interface na Application, implementação na Infrastructure) |
| Strategy Pattern | Gateways de pagamento, provedores de IA |
| Observer Pattern | Domain Events → Event Handlers |
| Factory Pattern | Criação de entidades complexas |
| Specification Pattern | Regras de validação compostas |
| State Machine | Reservation, Subscription, Invoice, Tenant |
| Adapter Pattern | Integrações externas (gateway, IA, notificação) |
| Decorator Pattern | Middleware (auth, tenant, rate limit) |

---

## 11. Testes

| Tipo | Banco | Escopo |
|------|-------|--------|
| Domínio | SQLite | Entidades, VOs, regras |
| Application | SQLite | Use Cases, orquestração |
| Integração | SQLite | Repositórios, adapters |
| Contrato API | SQLite | Endpoints, payloads |
| Arquitetural | Nenhum | Dependências entre camadas |
| E2E | SQLite | Fluxos críticos completos |

---

## 12. Status

Documento **ATIVO**. Define a arquitetura técnica do sistema.

Qualquer mudança arquitetural deve ser registrada como Decision Record.
