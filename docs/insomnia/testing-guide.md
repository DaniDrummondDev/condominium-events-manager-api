# Guia de Testes via Insomnia

**Projeto:** Condominium Events Manager API
**Base URL:** `http://localhost:8000`

---

## Setup Inicial

### 1. Importar a Collection

1. Abrir Insomnia
2. **Application > Preferences > Data > Import Data**
3. Selecionar `docs/insomnia/insomnia-collection.json`
4. A collection "Condominium Events Manager API" aparecerá com os ambientes configurados

### 2. Ambiente (Environment)

O ambiente "Local Docker" já vem com as variáveis base. Após cada login, copie os tokens retornados para as variáveis do ambiente:

| Variável | Onde pegar |
|----------|-----------|
| `platform_access_token` | Response do Platform Login |
| `tenant_access_token` | Response do Tenant Login |
| `refresh_token` | Response de qualquer Login |
| `tenant_slug` | Pré-configurado: `condominio-solar` |

### 3. Headers Padrão

Todos os endpoints autenticados precisam de:
- `Accept: application/json`
- `Authorization: Bearer {{token}}`

Endpoints de escrita (POST/PUT/PATCH) também precisam de:
- `Content-Type: application/json`

### 4. Dados Seed Disponíveis

| Recurso | Dados |
|---------|-------|
| **Admin Plataforma** | `admin@plataforma.com.br` / `SenhaSegura@123` (platform_owner) |
| **Síndico** | `sindico@condominio.com.br` / `SenhaSegura123` (sindico) |
| **Morador** | `morador@email.com` / `SenhaSegura123` (condomino) |
| **Tenant** | `condominio-solar` (vertical, ativo) |
| **Plano** | "Básico" com features (max_units: 50, max_spaces: 10, etc.) |
| **Bloco A** | 10 andares, 2 unidades (101 ocupada, 102 vazia) |
| **Espaços** | Salão de Festas (party_hall, requer aprovação) + Piscina (pool) |
| **Reservas** | 1 confirmed (Churrasco), 1 pending_approval (Piscina) |
| **Regras** | "Limite de barulho" + "Horário de silêncio" |
| **Violação** | 1 no_show aberta |
| **Política de penalidade** | no_show, 2 ocorrências = block 15 dias |

---

## Parte 1 — Auth

### 1.1 Platform Login

```
POST /platform/auth/login
```
```json
{
  "email": "admin@plataforma.com.br",
  "password": "SenhaSegura@123"
}
```

**Response 200:**
```json
{
  "data": {
    "access_token": "eyJ...",
    "refresh_token": "abc123...",
    "token_type": "bearer",
    "expires_in": 900
  }
}
```

Copie o `access_token` para a variável `platform_access_token` do ambiente.

### 1.2 Tenant Login (Síndico)

```
POST /tenant/auth/login
```
```json
{
  "email": "sindico@condominio.com.br",
  "password": "SenhaSegura123",
  "tenant_slug": "condominio-solar"
}
```

Copie o `access_token` para `tenant_access_token`.

### 1.3 Tenant Login (Morador)

```
POST /tenant/auth/login
```
```json
{
  "email": "morador@email.com",
  "password": "SenhaSegura123",
  "tenant_slug": "condominio-solar"
}
```

Use este token para testar endpoints com perspectiva de condômino.

### 1.4 Refresh Token

```
POST /platform/auth/refresh
POST /tenant/auth/refresh
```
```json
{
  "refresh_token": "{{refresh_token}}"
}
```

> O refresh do tenant requer os headers JWT + resolve tenant (middleware: `auth.jwt`, `tenant.resolve`, `tenant.active`).

### 1.5 MFA (Multi-Factor Authentication)

**Setup (requer JWT):**
```
POST /platform/auth/mfa/setup
```
> Retorna `secret` e `qr_code_url`. Escaneie o QR code num app TOTP (Google Authenticator, Authy).

**Confirmar Setup:**
```
POST /platform/auth/mfa/confirm
```
```json
{
  "secret": "{{secret_do_setup}}",
  "code": "123456"
}
```

**Verificar MFA (no login, quando retorna `mfa_required`):**
```
POST /platform/auth/mfa/verify
```
```json
{
  "mfa_token": "{{mfa_token}}",
  "code": "123456"
}
```

### 1.6 Logout

```
POST /platform/auth/logout
POST /tenant/auth/logout
```
> Sem body. Invalida o refresh token atual.

---

## Parte 2 — Platform API

> Todos os endpoints desta seção requerem `Authorization: Bearer {{platform_access_token}}`.

### 2.1 Health Checks (Públicos)

```
GET /platform/health          → Liveness (sempre 200)
GET /platform/health/live     → Liveness (sempre 200)
GET /platform/health/ready    → Readiness (200 healthy / 503 degraded)
```

Verifique nos response headers:
- `X-Correlation-ID` — gerado automaticamente
- `Strict-Transport-Security`, `X-Content-Type-Options`, `X-Frame-Options`, etc.

### 2.2 Dashboard da Plataforma

```
GET /platform/dashboard
```
> Retorna métricas gerais: total de tenants, subscriptions, receita, etc.

### 2.3 Plans (Planos)

**Listar planos:**
```
GET /platform/plans
```

**Ver plano específico:**
```
GET /platform/plans/{{plan_id}}
```

**Criar plano:**
```
POST /platform/plans
```
```json
{
  "name": "Premium",
  "slug": "premium",
  "price": 59990,
  "currency": "BRL",
  "billing_cycle": "monthly",
  "trial_days": 30,
  "features": [
    { "key": "max_units", "value": "200", "type": "integer" },
    { "key": "max_spaces", "value": "50", "type": "integer" },
    { "key": "max_users", "value": "500", "type": "integer" }
  ]
}
```

**Criar nova versão:**
```
POST /platform/plans/{{plan_id}}/versions
```
```json
{
  "price": 69990,
  "currency": "BRL",
  "billing_cycle": "monthly",
  "trial_days": 15,
  "features": [
    { "key": "max_units", "value": "300", "type": "integer" }
  ]
}
```

### 2.4 Subscriptions (Assinaturas)

**Listar:**
```
GET /platform/subscriptions
```

**Ver detalhes:**
```
GET /platform/subscriptions/{{subscription_id}}
```

**Cancelar:**
```
POST /platform/subscriptions/{{subscription_id}}/cancel
```
```json
{
  "cancellation_type": "end_of_period"
}
```
> Opções: `immediate` ou `end_of_period`.

### 2.5 Invoices (Faturas)

```
GET /platform/invoices
GET /platform/invoices/{{invoice_id}}
```

### 2.6 Payments (Pagamentos)

**Ver pagamento:**
```
GET /platform/payments/{{payment_id}}
```

**Reembolso:**
```
POST /platform/payments/{{payment_id}}/refund
```
```json
{
  "amount": 29990,
  "reason": "Cliente solicitou cancelamento dentro do período de trial."
}
```

### 2.7 Features (Feature Flags)

**Listar:**
```
GET /platform/features
```

**Criar feature:**
```
POST /platform/features
```
```json
{
  "code": "ai_enabled",
  "name": "IA Habilitada",
  "type": "boolean",
  "description": "Habilita o assistente de IA para o tenant"
}
```

**Ver feature:**
```
GET /platform/features/{{feature_id}}
```

### 2.8 Tenant Feature Overrides

**Listar overrides de um tenant:**
```
GET /platform/tenants/{{tenant_id}}/features
```

**Criar override:**
```
POST /platform/tenants/{{tenant_id}}/features
```
```json
{
  "feature_id": "{{feature_id}}",
  "value": "true",
  "reason": "Liberação de IA para teste piloto com Condomínio Solar.",
  "expires_at": "2026-12-31"
}
```

**Remover override:**
```
DELETE /platform/tenants/{{tenant_id}}/features/{{override_id}}
```

### 2.9 Tenant Metrics

```
GET /platform/tenants/{{tenant_id}}/metrics
```
> Retorna métricas operacionais do tenant (reservas, violações, etc.).

### 2.10 Billing Webhook

```
POST /platform/webhooks/billing
```
> Não requer JWT — validado por assinatura do gateway. Header `Stripe-Signature` necessário para ambiente real.

---

## Parte 3 — Tenant API

> Todos os endpoints desta seção requerem `Authorization: Bearer {{tenant_access_token}}`.
> O tenant é resolvido automaticamente a partir do JWT (campo `tenant_id` no token).

### 3.1 Health Checks (Públicos)

```
GET /tenant/health
GET /tenant/health/live
GET /tenant/health/ready
```

### 3.2 Dashboard

**Visão geral (síndico/administradora):**
```
GET /tenant/dashboard
```
> Retorna: totalUnits, totalResidents, totalSpaces, reservationsThisMonth, pendingApprovals, openViolations, etc.

**Visão do morador:**
```
GET /tenant/dashboard/resident
```
> Use o token do morador. Retorna métricas pessoais: minhas reservas, minhas violações, etc.

### 3.3 Blocks (Blocos)

**Listar:**
```
GET /tenant/blocks
```

**Criar bloco:**
```
POST /tenant/blocks
```
```json
{
  "identifier": "B",
  "name": "Bloco B",
  "floors": 8
}
```

**Ver bloco:**
```
GET /tenant/blocks/{{block_id}}
```

**Atualizar:**
```
PUT /tenant/blocks/{{block_id}}
```
```json
{
  "name": "Bloco B - Torre Norte",
  "floors": 12
}
```

**Remover:**
```
DELETE /tenant/blocks/{{block_id}}
```

### 3.4 Units (Unidades)

**Listar:**
```
GET /tenant/units
```

**Criar unidade:**
```
POST /tenant/units
```
```json
{
  "block_id": "{{block_id}}",
  "number": "201",
  "floor": 2,
  "type": "apartment"
}
```
> Tipos: `apartment`, `house`, `store`, `office`, `other`

**Ver unidade:**
```
GET /tenant/units/{{unit_id}}
```

**Atualizar:**
```
PUT /tenant/units/{{unit_id}}
```
```json
{
  "number": "201-A",
  "floor": 2,
  "type": "apartment"
}
```

**Desativar:**
```
POST /tenant/units/{{unit_id}}/deactivate
```

### 3.5 Residents (Moradores)

**Listar moradores de uma unidade:**
```
GET /tenant/units/{{unit_id}}/residents
```

**Convidar morador (síndico):**
```
POST /tenant/residents/invite
```
```json
{
  "unit_id": "{{unit_id}}",
  "name": "Carlos Oliveira",
  "email": "carlos@email.com",
  "phone": "11988887777",
  "document": "12345678901",
  "role_in_unit": "owner"
}
```
> Roles: `owner`, `tenant_resident`, `dependent`, `authorized`

**Ver morador:**
```
GET /tenant/residents/{{resident_id}}
```

**Ativar conta (público — link do convite):**
```
POST /tenant/residents/activate
```
```json
{
  "token": "{{invitation_token}}",
  "password": "NovaSenha@123",
  "password_confirmation": "NovaSenha@123"
}
```

**Desativar morador:**
```
POST /tenant/residents/{{resident_id}}/deactivate
```

### 3.6 Spaces (Espaços Comuns)

**Listar:**
```
GET /tenant/spaces
```

**Criar espaço:**
```
POST /tenant/spaces
```
```json
{
  "name": "Churrasqueira",
  "description": "Churrasqueira com área coberta e pia",
  "type": "bbq",
  "capacity": 20,
  "requires_approval": false,
  "max_duration_hours": 6,
  "max_advance_days": 15,
  "min_advance_hours": 24,
  "cancellation_deadline_hours": 12
}
```
> Tipos: `party_hall`, `bbq`, `pool`, `gym`, `playground`, `sports_court`, `meeting_room`, `other`

**Ver espaço:**
```
GET /tenant/spaces/{{space_id}}
```

**Atualizar:**
```
PUT /tenant/spaces/{{space_id}}
```
```json
{
  "capacity": 25,
  "max_duration_hours": 8
}
```

**Alterar status:**
```
PATCH /tenant/spaces/{{space_id}}/status
```
```json
{
  "status": "maintenance"
}
```
> Status: `active`, `inactive`, `maintenance`

#### Availability (Disponibilidade)

**Listar horários:**
```
GET /tenant/spaces/{{space_id}}/availability
```

**Criar horário:**
```
POST /tenant/spaces/{{space_id}}/availability
```
```json
{
  "day_of_week": 0,
  "start_time": "10:00",
  "end_time": "18:00"
}
```
> `day_of_week`: 0=Domingo, 1=Segunda, ..., 6=Sábado

**Remover:**
```
DELETE /tenant/spaces/{{space_id}}/availability/{{availability_id}}
```

#### Space Blocks (Bloqueios de Espaço)

**Listar bloqueios:**
```
GET /tenant/spaces/{{space_id}}/blocks
```

**Criar bloqueio:**
```
POST /tenant/spaces/{{space_id}}/blocks
```
```json
{
  "reason": "maintenance",
  "start_datetime": "2026-03-01",
  "end_datetime": "2026-03-05",
  "notes": "Manutenção preventiva do piso"
}
```
> Razões: `maintenance`, `holiday`, `event`, `administrative`

**Remover:**
```
DELETE /tenant/spaces/{{space_id}}/blocks/{{space_block_id}}
```

#### Space Rules (Regras do Espaço)

**Listar:**
```
GET /tenant/spaces/{{space_id}}/rules
```

**Criar regra:**
```
POST /tenant/spaces/{{space_id}}/rules
```
```json
{
  "rule_key": "deposit_brl",
  "rule_value": "200.00",
  "description": "Caução para reserva do espaço"
}
```

**Atualizar:**
```
PUT /tenant/spaces/{{space_id}}/rules/{{space_rule_id}}
```
```json
{
  "rule_key": "deposit_brl",
  "rule_value": "250.00",
  "description": "Caução atualizada"
}
```

**Remover:**
```
DELETE /tenant/spaces/{{space_id}}/rules/{{space_rule_id}}
```

#### Available Slots

**Ver slots disponíveis para reserva:**
```
GET /tenant/spaces/{{space_id}}/available-slots?date=2026-03-15
```

### 3.7 Reservations (Reservas)

**Listar:**
```
GET /tenant/reservations
```

**Criar reserva:**
```
POST /tenant/reservations
```
```json
{
  "space_id": "{{space_id}}",
  "unit_id": "{{unit_id}}",
  "resident_id": "{{resident_id}}",
  "title": "Festa de Aniversário",
  "start_datetime": "2026-03-20 14:00:00",
  "end_datetime": "2026-03-20 22:00:00",
  "expected_guests": 25,
  "notes": "Decoração será instalada às 12h"
}
```

**Ver reserva:**
```
GET /tenant/reservations/{{reservation_id}}
```

**Aprovar (síndico — para espaços que requerem aprovação):**
```
POST /tenant/reservations/{{reservation_id}}/approve
```

**Rejeitar:**
```
POST /tenant/reservations/{{reservation_id}}/reject
```
```json
{
  "rejection_reason": "Espaço já reservado para evento do condomínio nesta data."
}
```

**Cancelar:**
```
POST /tenant/reservations/{{reservation_id}}/cancel
```
```json
{
  "cancellation_reason": "Mudança de planos, não precisaremos mais do espaço."
}
```

**Check-in (dia do evento):**
```
POST /tenant/reservations/{{reservation_id}}/check-in
```

**Completar:**
```
POST /tenant/reservations/{{reservation_id}}/complete
```

**Marcar como no-show:**
```
POST /tenant/reservations/{{reservation_id}}/no-show
```

### 3.8 Guests (Convidados)

> Convidados são vinculados a uma reserva.

**Listar convidados da reserva:**
```
GET /tenant/reservations/{{reservation_id}}/guests
```

**Adicionar convidado:**
```
POST /tenant/reservations/{{reservation_id}}/guests
```
```json
{
  "name": "Ana Paula Silva",
  "document": "98765432100",
  "phone": "11977776666",
  "vehicle_plate": "ABC-1234",
  "relationship": "amiga"
}
```

**Check-in (portaria):**
```
POST /tenant/guests/{{guest_id}}/check-in
```

**Check-out:**
```
POST /tenant/guests/{{guest_id}}/check-out
```

**Negar acesso:**
```
POST /tenant/guests/{{guest_id}}/deny
```
```json
{
  "reason": "Documento não confere com o cadastro."
}
```

### 3.9 Service Providers (Prestadores de Serviço)

**Listar:**
```
GET /tenant/service-providers
```

**Cadastrar:**
```
POST /tenant/service-providers
```
```json
{
  "company_name": "Buffet Delícias",
  "name": "Roberto Santos",
  "document": "12345678000190",
  "phone": "11966665555",
  "service_type": "buffet",
  "notes": "Fornecedor habitual de eventos"
}
```
> Tipos: `buffet`, `cleaning`, `decoration`, `dj`, `security`, `maintenance`, `moving`, `other`

**Ver:**
```
GET /tenant/service-providers/{{service_provider_id}}
```

**Atualizar:**
```
PUT /tenant/service-providers/{{service_provider_id}}
```
```json
{
  "phone": "11955554444",
  "notes": "Novo contato atualizado"
}
```

#### Service Provider Visits (Visitas)

**Listar visitas:**
```
GET /tenant/service-provider-visits
```

**Agendar visita:**
```
POST /tenant/service-provider-visits
```
```json
{
  "service_provider_id": "{{service_provider_id}}",
  "unit_id": "{{unit_id}}",
  "reservation_id": "{{reservation_id}}",
  "scheduled_date": "2026-03-20",
  "purpose": "Montagem do buffet para festa de aniversário",
  "notes": "Chegada prevista às 12h"
}
```

**Ver visita:**
```
GET /tenant/service-provider-visits/{{service_provider_visit_id}}
```

**Check-in (portaria):**
```
POST /tenant/service-provider-visits/{{service_provider_visit_id}}/check-in
```

**Check-out:**
```
POST /tenant/service-provider-visits/{{service_provider_visit_id}}/check-out
```

### 3.10 Condominium Rules (Regras do Condomínio)

**Listar:**
```
GET /tenant/rules
```

**Criar regra:**
```
POST /tenant/rules
```
```json
{
  "title": "Uso da piscina",
  "description": "É obrigatório o uso de touca para entrar na piscina. Crianças menores de 12 anos devem estar acompanhadas por um responsável.",
  "category": "pool",
  "order": 3
}
```

**Ver regra:**
```
GET /tenant/rules/{{rule_id}}
```

**Atualizar:**
```
PUT /tenant/rules/{{rule_id}}
```
```json
{
  "description": "Texto atualizado da regra de uso da piscina.",
  "order": 5
}
```

**Remover:**
```
DELETE /tenant/rules/{{rule_id}}
```

### 3.11 Governance — Violations (Infrações)

**Listar:**
```
GET /tenant/violations
```

**Registrar infração (síndico):**
```
POST /tenant/violations
```
```json
{
  "unit_id": "{{unit_id}}",
  "tenant_user_id": "{{tenant_user_id}}",
  "rule_id": "{{rule_id}}",
  "type": "noise_complaint",
  "severity": "medium",
  "description": "Barulho excessivo após as 22h no salão de festas, reclamação de 3 moradores vizinhos."
}
```
> Tipos: `no_show`, `late_cancellation`, `capacity_exceeded`, `noise_complaint`, `damage`, `rule_violation`, `other`
> Severidade: `low`, `medium`, `high`, `critical`

**Ver infração:**
```
GET /tenant/violations/{{violation_id}}
```

**Manter infração (após análise):**
```
POST /tenant/violations/{{violation_id}}/uphold
```

**Revogar infração:**
```
POST /tenant/violations/{{violation_id}}/revoke
```
```json
{
  "reason": "Infração registrada por engano, morador apresentou evidência de que não estava presente."
}
```

**Contestar infração (morador):**
```
POST /tenant/violations/{{violation_id}}/contest
```
```json
{
  "reason": "Eu não estava presente na data da infração. Tenho comprovante de viagem para o período citado. Solicito revisão."
}
```

### 3.12 Governance — Contestations (Contestações)

**Listar:**
```
GET /tenant/contestations
```

**Ver contestação:**
```
GET /tenant/contestations/{{contestation_id}}
```

**Revisar contestação (síndico):**
```
POST /tenant/contestations/{{contestation_id}}/review
```
```json
{
  "accepted": true,
  "response": "Contestação aceita. Comprovante de viagem verificado. Infração será revogada."
}
```

### 3.13 Governance — Penalties (Penalidades)

**Listar:**
```
GET /tenant/penalties
```

**Ver penalidade:**
```
GET /tenant/penalties/{{penalty_id}}
```

**Revogar penalidade:**
```
POST /tenant/penalties/{{penalty_id}}/revoke
```
```json
{
  "reason": "Penalidade revogada após contestação aceita pelo síndico."
}
```

### 3.14 Penalty Policies (Políticas de Penalidade)

**Listar:**
```
GET /tenant/penalty-policies
```

**Criar política:**
```
POST /tenant/penalty-policies
```
```json
{
  "violation_type": "noise_complaint",
  "occurrence_threshold": 3,
  "penalty_type": "temporary_block",
  "block_days": 30
}
```
> Tipos de penalidade: `warning`, `temporary_block`, `permanent_block`

**Ver:**
```
GET /tenant/penalty-policies/{{penalty_policy_id}}
```

**Atualizar:**
```
PUT /tenant/penalty-policies/{{penalty_policy_id}}
```
```json
{
  "occurrence_threshold": 2,
  "block_days": 15
}
```

**Remover:**
```
DELETE /tenant/penalty-policies/{{penalty_policy_id}}
```

### 3.15 Communication — Announcements (Avisos)

**Listar:**
```
GET /tenant/announcements
```

**Criar aviso (síndico):**
```
POST /tenant/announcements
```
```json
{
  "title": "Manutenção da piscina",
  "body": "Informamos que a piscina ficará fechada para manutenção de 01/04 a 05/04. Pedimos desculpas pelo inconveniente.",
  "priority": "high",
  "audience_type": "all",
  "expires_at": "2026-04-06"
}
```
> Prioridade: `low`, `normal`, `high`, `urgent`
> Audiência: `all`, `block` (passar `audience_ids` com IDs dos blocos), `units` (IDs das unidades)

**Ver aviso:**
```
GET /tenant/announcements/{{announcement_id}}
```

**Marcar como lido (morador):**
```
POST /tenant/announcements/{{announcement_id}}/read
```

**Arquivar (síndico):**
```
POST /tenant/announcements/{{announcement_id}}/archive
```

### 3.16 Communication — Support Requests (Solicitações de Suporte)

**Listar:**
```
GET /tenant/support-requests
```

**Criar solicitação (morador):**
```
POST /tenant/support-requests
```
```json
{
  "subject": "Vazamento no teto do apartamento 101",
  "category": "maintenance",
  "priority": "high"
}
```
> Categorias: `maintenance`, `noise`, `security`, `general`, `other`
> Prioridade: `low`, `normal`, `high`

**Ver solicitação (com mensagens):**
```
GET /tenant/support-requests/{{support_request_id}}
```

**Adicionar mensagem:**
```
POST /tenant/support-requests/{{support_request_id}}/messages
```
```json
{
  "body": "O encanador visitou e identificou o problema. Reparo agendado para amanhã.",
  "is_internal": false
}
```
> `is_internal: true` = mensagem visível apenas para staff (síndico/administradora)

**Resolver:**
```
POST /tenant/support-requests/{{support_request_id}}/resolve
```

**Fechar:**
```
POST /tenant/support-requests/{{support_request_id}}/close
```
```json
{
  "reason": "resolved"
}
```
> Razões: `resolved`, `admin_closed`

**Reabrir:**
```
POST /tenant/support-requests/{{support_request_id}}/reopen
```

### 3.17 AI Assistant

> Rate limit: 20 requests/minuto por usuário+tenant.

**Chat:**
```
POST /tenant/ai/chat
```
```json
{
  "message": "Quais espaços estão disponíveis para reserva na próxima semana?",
  "session_id": null
}
```
> O `session_id` é retornado na primeira resposta. Envie nas mensagens seguintes para manter o contexto da conversa.

**Sugestões:**
```
POST /tenant/ai/suggest
```
```json
{
  "context": "Quero fazer uma festa de aniversário para 30 pessoas",
  "space_id": "{{space_id}}",
  "date": "2026-04-15"
}
```

**Listar ações pendentes:**
```
GET /tenant/ai/actions
```

**Confirmar ação:**
```
PATCH /tenant/ai/actions/{{ai_action_id}}/confirm
```

**Rejeitar ação:**
```
PATCH /tenant/ai/actions/{{ai_action_id}}/reject
```
```json
{
  "reason": "Prefiro fazer a reserva manualmente."
}
```

---

## Fluxos de Teste Completos

### Fluxo 1 — Reserva Completa (Happy Path)

1. **Login** como síndico → copiar token
2. `GET /tenant/spaces` → copiar `space_id` do Salão de Festas
3. `GET /tenant/spaces/{id}/available-slots?date=2026-04-10` → ver slots disponíveis
4. `GET /tenant/units` → copiar `unit_id` da unidade 101
5. `GET /tenant/units/{unit_id}/residents` → copiar `resident_id`
6. `POST /tenant/reservations` → criar reserva para o slot disponível
7. `POST /tenant/reservations/{id}/approve` → aprovar (espaço requer aprovação)
8. `POST /tenant/reservations/{id}/guests` → adicionar convidados
9. `POST /tenant/reservations/{id}/check-in` → check-in no dia do evento
10. `POST /tenant/guests/{id}/check-in` → check-in dos convidados
11. `POST /tenant/guests/{id}/check-out` → check-out dos convidados
12. `POST /tenant/reservations/{id}/complete` → encerrar reserva
13. `GET /tenant/dashboard` → verificar métricas atualizadas

### Fluxo 2 — Governança (Infração → Contestação)

1. **Login** como síndico
2. `POST /tenant/violations` → registrar infração contra morador
3. `GET /tenant/violations/{id}` → ver detalhes
4. **Login** como morador
5. `POST /tenant/violations/{id}/contest` → contestar infração
6. **Login** como síndico
7. `GET /tenant/contestations` → ver contestação pendente
8. `POST /tenant/contestations/{id}/review` → aceitar ou rejeitar

### Fluxo 3 — Comunicação

1. **Login** como síndico
2. `POST /tenant/announcements` → criar aviso
3. **Login** como morador
4. `GET /tenant/announcements` → ver avisos
5. `POST /tenant/announcements/{id}/read` → marcar como lido
6. `POST /tenant/support-requests` → abrir solicitação
7. `POST /tenant/support-requests/{id}/messages` → adicionar mensagem
8. **Login** como síndico
9. `POST /tenant/support-requests/{id}/messages` → responder (com `is_internal` para nota interna)
10. `POST /tenant/support-requests/{id}/resolve` → resolver

### Fluxo 4 — Prestador de Serviço

1. **Login** como síndico
2. `POST /tenant/service-providers` → cadastrar prestador
3. `POST /tenant/service-provider-visits` → agendar visita vinculada a reserva
4. `POST /tenant/service-provider-visits/{id}/check-in` → portaria registra entrada
5. `POST /tenant/service-provider-visits/{id}/check-out` → portaria registra saída

### Fluxo 5 — Platform Admin

1. **Login** como admin plataforma
2. `GET /platform/dashboard` → ver métricas gerais
3. `GET /platform/plans` → ver planos
4. `POST /platform/plans` → criar plano Premium
5. `POST /platform/features` → criar feature flag
6. `POST /platform/tenants/{id}/features` → liberar feature para tenant
7. `GET /platform/tenants/{id}/metrics` → ver métricas do tenant
8. `GET /platform/subscriptions` → ver assinaturas

---

## Troubleshooting

| Problema | Solução |
|----------|---------|
| `401 Unauthorized` | Token expirado (TTL: 15min). Faça login novamente. |
| `403 Forbidden` | Role sem permissão. Verifique se está usando o token correto (síndico vs morador). |
| `422 Unprocessable` | Validação falhou. Verifique os campos obrigatórios no body. |
| `429 Too Many Requests` | Rate limit atingido. Aguarde 1 minuto. |
| `503 Service Unavailable` | Health check degraded. Verifique se Docker (postgres/redis) está rodando. |
| Redirect ao invés de JSON | Faltou o header `Accept: application/json`. |
| Token não funciona no tenant | Verifique se usou `/tenant/auth/login` (não `/platform/auth/login`). |
