# Bounded Contexts — Contextos Delimitados

## 1. Visão Geral

O sistema é dividido em **contextos delimitados** (Bounded Contexts) que definem fronteiras claras de responsabilidade. Cada contexto possui suas próprias entidades, regras e linguagem.

Os contextos são organizados em **dois domínios principais**:

1. **Platform Domain** — Gestão do SaaS (plataforma)
2. **Tenant Domain** — Gestão do condomínio (domínio de negócio)

Além destes, existem contextos de suporte transversais.

---

## 2. Mapa de Contextos

```
┌─────────────────────────────────────────────────────────────────┐
│                     PLATFORM DOMAIN                             │
│                                                                 │
│  ┌──────────────┐  ┌──────────────┐  ┌───────────────────────┐ │
│  │   Tenant     │  │   Billing    │  │   Platform Admin      │ │
│  │  Management  │  │   & Plans    │  │   & Governance        │ │
│  └──────┬───────┘  └──────┬───────┘  └───────────┬───────────┘ │
│         │                 │                       │             │
└─────────┼─────────────────┼───────────────────────┼─────────────┘
          │                 │                       │
          │    ┌────────────┴────────────┐          │
          │    │  Subscription/Feature   │          │
          │    │     (controla acesso)   │          │
          │    └────────────┬────────────┘          │
          │                 │                       │
┌─────────┼─────────────────┼───────────────────────┼─────────────┐
│         ▼                 ▼                       ▼             │
│                      TENANT DOMAIN                              │
│                                                                 │
│  ┌──────────────┐  ┌──────────────┐  ┌───────────────────────┐ │
│  │   Units &    │  │   Spaces     │  │   Reservations        │ │
│  │  Residents   │  │  Management  │  │   (Aggregate Root)    │ │
│  └──────┬───────┘  └──────┬───────┘  └───────────┬───────────┘ │
│         │                 │                       │             │
│  ┌──────┴───────┐  ┌──────┴───────┐  ┌───────────┴───────────┐ │
│  │ Communication│  │  Governance  │  │   People Control      │ │
│  │  (Avisos &   │  │  (Regras &   │  │   (Convidados &       │ │
│  │  Solicit.)   │  │  Penalid.)   │  │    Prestadores)       │ │
│  └──────────────┘  └──────────────┘  └───────────────────────┘ │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────────┐
│                  CONTEXTOS TRANSVERSAIS                         │
│                                                                 │
│  ┌──────────────┐  ┌──────────────┐  ┌───────────────────────┐ │
│  │   Identity   │  │   Audit &    │  │   AI Assistant        │ │
│  │   & Auth     │  │  Compliance  │  │   (Conversational)    │ │
│  └──────────────┘  └──────────────┘  └───────────────────────┘ │
│                                                                 │
│  ┌──────────────┐  ┌──────────────┐                            │
│  │ Notification │  │ Observability│                            │
│  └──────────────┘  └──────────────┘                            │
└─────────────────────────────────────────────────────────────────┘
```

---

## 3. Contextos do Platform Domain

### 3.1 Tenant Management

**Responsabilidade:** Ciclo de vida completo do tenant.

**Entidades principais:**
- Tenant
- TenantConfiguration

**Operações:**
- Provisionar tenant (criar database/schema, executar migrations)
- Ativar, suspender, cancelar tenant
- Gerenciar configurações do condomínio (tipo: horizontal/vertical/misto)

**Fronteira:** Não conhece o domínio interno do tenant (reservas, espaços, etc.).

**Skills:** `saas-architecture.md`, `platform-architecture.md`, `tenant-lifecycle.md`, `migration-strategy.md`

---

### 3.2 Billing & Plans

**Responsabilidade:** Monetização do SaaS.

**Entidades principais:**
- Plan, PlanVersion, PlanFeature
- Subscription
- Invoice, InvoiceItem
- Payment
- DunningPolicy

**Operações:**
- Gerenciar planos e versões
- Controlar assinaturas e ciclos de cobrança
- Processar pagamentos via gateway
- Executar dunning (inadimplência)
- Gerar faturas

**Fronteira:** Billing controla acesso ao sistema via estado da assinatura. Nunca altera dados de domínio.

**Skills:** `billing-subscription.md`, `subscription-lifecycle.md`, `invoice-management.md`, `payment-gateway-integration.md`, `dunning-strategy.md`, `billing-security.md`, `plan-management.md`

---

### 3.3 Platform Admin & Governance

**Responsabilidade:** Gestão operacional da plataforma pelo SaaS owner.

**Entidades principais:**
- PlatformUser
- Feature, TenantFeatureOverride
- TenantAdminAction

**Operações:**
- Suspender/reativar/bloquear tenants
- Gerenciar feature flags e overrides
- Visualizar métricas globais
- Alterar planos de tenants

**Fronteira:** Admin da plataforma nunca acessa dados de domínio do tenant.

**Skills:** `platform-admin.md`, `feature-flag-strategy.md`, `tenant-administration.md`

---

## 4. Contextos do Tenant Domain

### 4.1 Units & Residents (Unidades e Moradores)

**Responsabilidade:** Estrutura do condomínio e seus moradores.

**Entidades principais:**
- Block
- Unit
- Resident
- TenantUser

**Operações:**
- Cadastrar blocos e unidades
- Convidar moradores (onboarding)
- Gerenciar mudanças e transferências
- Associar papéis a moradores

**Fronteira:** Base estrutural para todos os outros contextos de tenant. Reservas, penalidades e comunicação dependem de unidades.

**Skill:** `units-management.md`

---

### 4.2 Spaces Management (Espaços Comuns)

**Responsabilidade:** Cadastro e configuração de espaços do condomínio.

**Entidades principais:**
- Space
- SpaceAvailability
- SpaceBlock
- SpaceRule

**Operações:**
- Criar e configurar espaços
- Definir horários de disponibilidade
- Criar bloqueios temporários
- Configurar regras por espaço

**Fronteira:** Define a infraestrutura sobre a qual reservas operam. Não conhece reservas diretamente.

**Skill:** `spaces-management.md`

---

### 4.3 Reservations (Reservas)

**Responsabilidade:** Gestão completa de reservas de espaços comuns.

**Entidades principais:**
- Reservation (Aggregate Root)

**Operações:**
- Solicitar reserva
- Aprovar/rejeitar reserva
- Cancelar reserva
- Marcar como concluída ou no-show
- Prevenir conflitos de agenda

**Fronteira:** Aggregate Root que coordena convidados e prestadores. Emite eventos para governança e notificações.

**Skill:** `reservation-system.md`

---

### 4.4 Governance (Governança e Regras)

**Responsabilidade:** Regulamento interno, infrações e penalidades.

**Entidades principais:**
- CondominiumRule
- Violation
- Penalty
- PenaltyPolicy
- ViolationContestation

**Operações:**
- Definir regulamento interno
- Registrar infrações (automáticas e manuais)
- Aplicar penalidades
- Gerenciar contestações
- Bloquear condôminos

**Fronteira:** Reage a eventos de reserva (no-show, cancelamento tardio). Não cria reservas nem gerencia espaços.

**Skill:** `governance-rules.md`

---

### 4.5 People Control (Controle de Pessoas)

**Responsabilidade:** Gestão de convidados e prestadores de serviço.

**Entidades principais:**
- Guest
- ServiceProvider
- ReservationServiceProvider

**Operações:**
- Registrar convidados por reserva
- Cadastrar prestadores de serviço
- Check-in/check-out pela portaria
- Validar acesso

**Fronteira:** Sempre vinculado a uma reserva. Portaria consulta e valida, não administra.

**Skill:** `people-control.md`

---

### 4.6 Communication (Comunicação Interna)

**Responsabilidade:** Avisos, comunicados e canal de atendimento.

**Entidades principais:**
- Announcement
- AnnouncementRead
- SupportRequest
- SupportMessage

**Operações:**
- Publicar avisos (para todos, bloco ou unidades)
- Criar solicitações de atendimento
- Responder e resolver solicitações
- Confirmar leitura de avisos

**Fronteira:** Não interfere em reservas ou governança. É canal de comunicação formal.

**Skill:** `communication.md`

---

## 5. Contextos Transversais

### 5.1 Identity & Auth

**Responsabilidade:** Autenticação e autorização.

- Autenticação centralizada (OAuth 2.1, JWT)
- Autorização contextual ao tenant (RBAC + Policies)
- MFA para perfis sensíveis

**Skills:** `auth-architecture.md`, `access-control.md`

---

### 5.2 Audit & Compliance

**Responsabilidade:** Rastreabilidade e conformidade.

- Logs imutáveis de ações críticas
- Conformidade com LGPD
- Política de retenção de dados

**Skills:** `audit-logging.md`, `lgpd-compliance.md`, `security-compliance.md`, `data-retention-policy.md`

---

### 5.3 AI Assistant

**Responsabilidade:** Interface conversacional e orquestração de ações.

- Assistente conversacional
- Tool Registry para ações
- Embeddings isolados por tenant (pgvector)
- Confirmação humana obrigatória

**Skills:** `ai-integration.md`, `ai-action-orchestration.md`, `ai-data-governance.md`, `embedding-strategy.md`, `ai-observability.md`

---

### 5.4 Notification

**Responsabilidade:** Entrega de comunicações do sistema.

- Canais: e-mail, push (futuro), SMS (futuro)
- Templates versionados
- Fila assíncrona

**Skill:** `notification-strategy.md`

---

### 5.5 Observability

**Responsabilidade:** Monitoramento e rastreabilidade técnica.

- Logs estruturados, métricas, tracing
- Health checks
- Alertas

**Skills:** `observability-strategy.md`, `ai-observability.md`

---

## 6. Relações entre Contextos

### 6.1 Regras de Comunicação

| De | Para | Mecanismo |
|----|------|-----------|
| Billing | Tenant Management | Estado da assinatura (controla acesso) |
| Tenant Management | Billing | Eventos de criação/cancelamento |
| Reservations | Governance | Eventos (NoShow, LateCancellation) |
| Reservations | People Control | Composição (convidados e prestadores na reserva) |
| Reservations | Notification | Eventos (confirmação, cancelamento) |
| Governance | Notification | Eventos (penalidade aplicada) |
| Spaces | Reservations | Consulta (verificação de disponibilidade) |
| Units | Reservations | Referência (unit_id na reserva) |
| Units | Governance | Referência (unit_id na penalidade) |
| Units | Communication | Referência (audiência de avisos) |
| AI Assistant | Reservations | Orquestração via Use Cases |
| Platform Admin | Tenant Management | Ações administrativas |

### 6.2 Regra Fundamental

Contextos se comunicam via:

- **Eventos de domínio** (assíncronos, desacoplados)
- **Consultas** (síncronas, read-only)
- **Referências por ID** (nunca acesso direto a entidades de outro contexto)

Nunca via:

- Acesso direto ao banco de outro contexto
- Compartilhamento de entidades entre contextos
- Dependência circular

---

## 7. Integrações Externas (Futuro)

### 7.1 Marketplace de Serviços

- Sistema separado (outra API)
- Integração via API para consulta de fornecedores
- Recomendações contextuais via IA
- Desenvolvimento após conclusão da API principal e front-end

---

## 8. Status

Documento **ATIVO**. Define as fronteiras arquiteturais do sistema.

Qualquer nova funcionalidade deve ser mapeada a um contexto existente ou justificar a criação de um novo.
