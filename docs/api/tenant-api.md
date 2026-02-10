# Tenant API Reference — Condominium Events Manager API

## Status do Documento

**Status:** Ativo\
**Ultima atualizacao:** 2026-02-10\
**Versao da API:** v1

---

## 1. Visão Geral

A Tenant API fornece todos os endpoints necessários para operação dentro do contexto de um condomínio (tenant). Todos os endpoints requerem autenticação JWT com `tenant_id` válido.

### 1.1 Base URL

```
/api/v1/tenant
```

### 1.2 Autenticação

Todos os endpoints (exceto auth/login, auth/register, auth/forgot-password, auth/reset-password) requerem:

```
Authorization: Bearer {access_token}
```

O token JWT deve conter a claim `tenant_id` válida.

### 1.3 Headers Comuns

| Header | Obrigatório | Descrição |
|--------|:-----------:|-----------|
| `Authorization` | Sim* | `Bearer {token}` |
| `Content-Type` | Sim | `application/json` |
| `Accept` | Sim | `application/json` |
| `X-Request-ID` | Não | UUID de correlação (gerado pelo servidor se ausente) |
| `X-Idempotency-Key` | Não | Chave de idempotência para operações de escrita |

### 1.4 Formato de Resposta

**Recurso único:**
```json
{
  "data": { ... },
  "meta": {
    "request_id": "uuid",
    "timestamp": "2026-01-15T10:30:00Z"
  }
}
```

**Lista com paginação (cursor-based):**
```json
{
  "data": [ ... ],
  "meta": {
    "request_id": "uuid",
    "timestamp": "2026-01-15T10:30:00Z",
    "per_page": 20,
    "has_more": true
  },
  "links": {
    "next": "/api/v1/tenant/resources?cursor=eyJpZCI6...&per_page=20",
    "prev": null
  }
}
```

### 1.5 Formato de Erro

```json
{
  "error": {
    "code": "RESERVATION_CONFLICT",
    "message": "Já existe uma reserva confirmada para este horário.",
    "details": [
      {
        "field": "start_datetime",
        "message": "Conflita com reserva existente (ID: uuid)"
      }
    ]
  }
}
```

### 1.6 Paginação

- **Tipo:** Cursor-based
- **Parâmetros:** `?cursor={cursor}&per_page={10-100}`
- **Default:** `per_page=20`
- **Máximo:** `per_page=100`

### 1.7 Roles do Tenant

| Role | Descrição |
|------|-----------|
| `sindico` | Síndico — acesso total dentro do tenant |
| `administradora` | Administradora — mesmas permissões do síndico |
| `condomino` | Condômino/morador — acesso a recursos próprios |
| `funcionario` | Funcionário (porteiro) — check-in/check-out |

---

## 2. Autenticação do Tenant

### 2.1 Login

```
POST /api/v1/tenant/auth/login
```

**Roles:** Pública (sem autenticação)

**Request Body:**
```json
{
  "email": "morador@email.com",
  "password": "SenhaSegura123",
  "tenant_slug": "condominio-solar"
}
```

| Campo | Tipo | Obrigatório | Validação |
|-------|------|:-----------:|-----------|
| `email` | string | Sim | E-mail válido, max 255 |
| `password` | string | Sim | Min 8 |
| `tenant_slug` | string | Sim | Slug existente e ativo |

**Response 200:**
```json
{
  "data": {
    "access_token": "eyJhbG...",
    "refresh_token": "dGhpcyBpcyBh...",
    "token_type": "bearer",
    "expires_in": 900,
    "user": {
      "id": "uuid",
      "name": "João Silva",
      "email": "morador@email.com",
      "role": "condomino",
      "mfa_enabled": false
    },
    "tenant": {
      "id": "uuid",
      "name": "Condomínio Solar",
      "slug": "condominio-solar",
      "type": "vertical"
    }
  }
}
```

**Erros:**
| Status | Código | Descrição |
|--------|--------|-----------|
| 401 | `AUTH_INVALID_CREDENTIALS` | Credenciais inválidas |
| 403 | `TENANT_INACTIVE` | Tenant suspenso/cancelado |
| 403 | `AUTH_ACCOUNT_LOCKED` | Conta bloqueada por tentativas |
| 422 | `VALIDATION_ERROR` | Campos inválidos |
| 200 | `AUTH_MFA_REQUIRED` | MFA necessário (retorna mfa_required_token) |

---

### 2.2 Verificação MFA

```
POST /api/v1/tenant/auth/mfa/verify
```

**Headers:** `Authorization: Bearer {mfa_required_token}`

**Request Body:**
```json
{
  "code": "123456"
}
```

**Response 200:** Mesmo formato do login (com access_token completo)

**Erros:**
| Status | Código | Descrição |
|--------|--------|-----------|
| 401 | `AUTH_INVALID_MFA_CODE` | Código MFA inválido |
| 401 | `AUTH_MFA_TOKEN_EXPIRED` | Token MFA expirado |
| 403 | `AUTH_ACCOUNT_LOCKED` | Conta bloqueada (5 falhas MFA) |

---

### 2.3 Setup MFA

```
POST /api/v1/tenant/auth/mfa/setup
```

**Roles:** Autenticado (qualquer role)

**Response 200:**
```json
{
  "data": {
    "secret": "JBSWY3DPEHPK3PXP",
    "qr_code_uri": "otpauth://totp/CondominiumEvents:morador@email.com?secret=JBSWY3DPEHPK3PXP&issuer=CondominiumEvents",
    "recovery_codes": ["abc123", "def456", "ghi789"]
  }
}
```

**Confirmação do Setup:**
```
POST /api/v1/tenant/auth/mfa/setup/confirm
Body: { "code": "123456" }
Response 200: { "data": { "mfa_enabled": true } }
```

---

### 2.4 Refresh Token

```
POST /api/v1/tenant/auth/refresh
```

**Request Body:**
```json
{
  "refresh_token": "dGhpcyBpcyBh..."
}
```

**Response 200:**
```json
{
  "data": {
    "access_token": "eyJhbG...",
    "refresh_token": "bmV3IHJlZnJlc2g...",
    "token_type": "bearer",
    "expires_in": 900
  }
}
```

**Erros:**
| Status | Código | Descrição |
|--------|--------|-----------|
| 401 | `AUTH_TOKEN_EXPIRED` | Refresh token expirado |
| 401 | `AUTH_TOKEN_REVOKED` | Token revogado (possível roubo detectado) |

---

### 2.5 Logout

```
POST /api/v1/tenant/auth/logout
```

**Roles:** Autenticado

**Response:** `204 No Content`

---

### 2.6 Registro via Convite

```
POST /api/v1/tenant/auth/register
```

**Roles:** Pública (com invitation_token válido)

**Request Body:**
```json
{
  "invitation_token": "token-do-convite",
  "name": "Maria Santos",
  "email": "maria@email.com",
  "password": "SenhaSegura123",
  "password_confirmation": "SenhaSegura123",
  "phone": "11999998888"
}
```

| Campo | Tipo | Obrigatório | Validação |
|-------|------|:-----------:|-----------|
| `invitation_token` | string | Sim | Token válido e não expirado |
| `name` | string | Sim | Min 2, max 255 |
| `email` | string | Sim | E-mail válido, único no tenant |
| `password` | string | Sim | Min 8, 1 maiúscula, 1 minúscula, 1 dígito |
| `password_confirmation` | string | Sim | Igual ao password |
| `phone` | string | Não | Formato de telefone válido |

**Response 201:** Mesmo formato do login

---

### 2.7 Recuperação de Senha

```
POST /api/v1/tenant/auth/forgot-password
Body: { "email": "morador@email.com", "tenant_slug": "condominio-solar" }
Response 200: { "data": { "message": "Se o e-mail existir, um link de recuperação será enviado." } }
```

```
POST /api/v1/tenant/auth/reset-password
Body: { "token": "reset-token", "password": "NovaSenha123", "password_confirmation": "NovaSenha123" }
Response 200: { "data": { "message": "Senha alterada com sucesso." } }
```

---

### 2.8 Perfil do Usuário Autenticado

```
GET /api/v1/tenant/auth/me
```

**Roles:** Autenticado

**Response 200:**
```json
{
  "data": {
    "id": "uuid",
    "name": "João Silva",
    "email": "morador@email.com",
    "phone": "11999998888",
    "role": "condomino",
    "status": "active",
    "mfa_enabled": false,
    "units": [
      {
        "id": "uuid",
        "identifier": "101",
        "block": { "id": "uuid", "identifier": "A" },
        "role_in_unit": "owner",
        "is_primary": true
      }
    ],
    "created_at": "2026-01-15T10:00:00Z"
  }
}
```

---

## 3. Blocos (Blocks)

Blocos são opcionais — existem apenas em condomínios verticais ou mistos.

### 3.1 Listar Blocos

```
GET /api/v1/tenant/blocks
```

**Roles:** sindico, administradora (todos), condomino (próprio bloco), funcionario (todos)

**Query Params:** `status`, `cursor`, `per_page`

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Bloco A",
      "identifier": "A",
      "status": "active",
      "units_count": 20,
      "created_at": "2026-01-15T10:00:00Z"
    }
  ],
  "meta": { "per_page": 20, "has_more": false },
  "links": { "next": null }
}
```

### 3.2 Criar Bloco

```
POST /api/v1/tenant/blocks
```

**Roles:** sindico, administradora

**Request Body:**
```json
{
  "name": "Bloco A",
  "identifier": "A"
}
```

| Campo | Tipo | Obrigatório | Validação |
|-------|------|:-----------:|-----------|
| `name` | string | Sim | Max 100 |
| `identifier` | string | Sim | Max 20, único no tenant |

**Response 201:** Recurso criado

**Erros:**
| Status | Código | Descrição |
|--------|--------|-----------|
| 409 | `BLOCK_IDENTIFIER_EXISTS` | Identificador já existe |
| 403 | `FEATURE_NOT_AVAILABLE` | Plano não permite esta funcionalidade |

### 3.3 Obter Bloco

```
GET /api/v1/tenant/blocks/{id}
```

### 3.4 Atualizar Bloco

```
PUT /api/v1/tenant/blocks/{id}
```

### 3.5 Desativar Bloco

```
DELETE /api/v1/tenant/blocks/{id}
```

**Nota:** Não exclui fisicamente. Altera status para `inactive`. Bloco com unidades ativas não pode ser desativado.

---

## 4. Unidades (Units)

### 4.1 Listar Unidades

```
GET /api/v1/tenant/units
```

**Roles:** sindico, administradora, funcionario (todas); condomino (próprias)

**Query Params:** `block_id`, `status`, `type`, `cursor`, `per_page`

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "block": { "id": "uuid", "identifier": "A" },
      "identifier": "101",
      "type": "apartment",
      "floor": 1,
      "status": "active",
      "residents_count": 3,
      "created_at": "2026-01-15T10:00:00Z"
    }
  ]
}
```

### 4.2 Criar Unidade

```
POST /api/v1/tenant/units
```

**Roles:** sindico, administradora

**Request Body:**
```json
{
  "block_id": "uuid",
  "identifier": "101",
  "type": "apartment",
  "floor": 1
}
```

| Campo | Tipo | Obrigatório | Validação |
|-------|------|:-----------:|-----------|
| `block_id` | uuid | Não | UUID válido de bloco ativo (null para condo horizontal) |
| `identifier` | string | Sim | Max 50, único por bloco |
| `type` | string | Sim | `apartment`, `house`, `commercial`, `other` |
| `floor` | integer | Não | Número do andar |

**Erros:**
| Status | Código | Descrição |
|--------|--------|-----------|
| 409 | `UNIT_IDENTIFIER_EXISTS` | Identificador já existe neste bloco |
| 422 | `PLAN_LIMIT_REACHED` | Limite de unidades do plano atingido |

### 4.3 Obter Unidade

```
GET /api/v1/tenant/units/{id}
```

### 4.4 Atualizar Unidade

```
PUT /api/v1/tenant/units/{id}
```

### 4.5 Alterar Status da Unidade

```
PATCH /api/v1/tenant/units/{id}/status
Body: { "status": "inactive" }
```

**Nota:** Unidade inativa não pode fazer reservas.

### 4.6 Listar Moradores da Unidade

```
GET /api/v1/tenant/units/{id}/residents
```

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "user": {
        "id": "uuid",
        "name": "João Silva",
        "email": "joao@email.com",
        "role": "condomino"
      },
      "role_in_unit": "owner",
      "is_primary": true,
      "moved_in_at": "2026-01-01",
      "moved_out_at": null
    }
  ]
}
```

### 4.7 Adicionar Morador à Unidade

```
POST /api/v1/tenant/units/{id}/residents
```

**Roles:** sindico, administradora

**Request Body:**
```json
{
  "tenant_user_id": "uuid",
  "role_in_unit": "owner",
  "is_primary": true,
  "moved_in_at": "2026-01-01"
}
```

**Erros:**
| Status | Código | Descrição |
|--------|--------|-----------|
| 422 | `PLAN_LIMIT_REACHED` | Limite de moradores por unidade atingido |
| 409 | `RESIDENT_ALREADY_EXISTS` | Morador já vinculado a esta unidade |

### 4.8 Remover Morador da Unidade

```
DELETE /api/v1/tenant/units/{id}/residents/{residentId}
```

**Nota:** Define `moved_out_at` = data atual. Não exclui fisicamente.

---

## 5. Usuários do Tenant (Tenant Users)

### 5.1 Listar Usuários

```
GET /api/v1/tenant/users
```

**Roles:** sindico, administradora (todos); funcionario (todos, read-only)

**Query Params:** `role`, `status`, `search` (nome/email), `cursor`, `per_page`

### 5.2 Convidar Usuário

```
POST /api/v1/tenant/users/invite
```

**Roles:** sindico, administradora

**Request Body:**
```json
{
  "email": "novo.morador@email.com",
  "name": "Maria Santos",
  "role": "condomino",
  "unit_id": "uuid",
  "role_in_unit": "owner"
}
```

| Campo | Tipo | Obrigatório | Validação |
|-------|------|:-----------:|-----------|
| `email` | string | Sim | E-mail válido, único no tenant |
| `name` | string | Sim | Min 2, max 255 |
| `role` | string | Sim | `condomino`, `funcionario` |
| `unit_id` | uuid | Não | Obrigatório para condôminos |
| `role_in_unit` | string | Não | `owner`, `tenant_resident`, `dependent` |

**Response 201:**
```json
{
  "data": {
    "id": "uuid",
    "email": "novo.morador@email.com",
    "name": "Maria Santos",
    "role": "condomino",
    "status": "invited",
    "invitation_expires_at": "2026-01-22T10:00:00Z"
  }
}
```

**Erros:**
| Status | Código | Descrição |
|--------|--------|-----------|
| 409 | `USER_EMAIL_EXISTS` | E-mail já registrado neste tenant |
| 422 | `PLAN_LIMIT_REACHED` | Limite de usuários do plano atingido |

### 5.3 Obter Usuário

```
GET /api/v1/tenant/users/{id}
```

### 5.4 Atualizar Usuário

```
PUT /api/v1/tenant/users/{id}
```

### 5.5 Alterar Status do Usuário

```
PATCH /api/v1/tenant/users/{id}/status
Body: { "status": "blocked", "reason": "Motivo do bloqueio" }
```

---

## 6. Espaços Comuns (Spaces)

### 6.1 Listar Espaços

```
GET /api/v1/tenant/spaces
```

**Roles:** Todas

**Query Params:** `type`, `status`, `cursor`, `per_page`

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "name": "Salão de Festas",
      "description": "Salão com capacidade para 100 pessoas",
      "type": "party_hall",
      "status": "active",
      "capacity": 100,
      "requires_approval": true,
      "max_duration_hours": 12,
      "max_advance_days": 30,
      "min_advance_hours": 48,
      "cancellation_deadline_hours": 24,
      "created_at": "2026-01-15T10:00:00Z"
    }
  ]
}
```

### 6.2 Criar Espaço

```
POST /api/v1/tenant/spaces
```

**Roles:** sindico, administradora

**Request Body:**
```json
{
  "name": "Salão de Festas",
  "description": "Salão com capacidade para 100 pessoas",
  "type": "party_hall",
  "capacity": 100,
  "requires_approval": true,
  "max_duration_hours": 12,
  "max_advance_days": 30,
  "min_advance_hours": 48,
  "cancellation_deadline_hours": 24
}
```

| Campo | Tipo | Obrigatório | Validação |
|-------|------|:-----------:|-----------|
| `name` | string | Sim | Max 255 |
| `description` | string | Não | Text |
| `type` | string | Sim | `party_hall`, `bbq`, `pool`, `gym`, `playground`, `sports_court`, `meeting_room`, `other` |
| `capacity` | integer | Sim | > 0 |
| `requires_approval` | boolean | Não | Default: false |
| `max_duration_hours` | integer | Não | > 0 |
| `max_advance_days` | integer | Não | Default: 30, > 0 |
| `min_advance_hours` | integer | Não | Default: 24, >= 0 |
| `cancellation_deadline_hours` | integer | Não | Default: 24, >= 0 |

### 6.3 Obter Espaço

```
GET /api/v1/tenant/spaces/{id}
```

### 6.4 Atualizar Espaço

```
PUT /api/v1/tenant/spaces/{id}
```

### 6.5 Alterar Status do Espaço

```
PATCH /api/v1/tenant/spaces/{id}/status
Body: { "status": "maintenance" }
```

### 6.6 Disponibilidade

**Listar:**
```
GET /api/v1/tenant/spaces/{id}/availability
```

**Criar slot:**
```
POST /api/v1/tenant/spaces/{id}/availability
Body: { "day_of_week": 1, "start_time": "08:00", "end_time": "22:00" }
```

**Atualizar slot:**
```
PUT /api/v1/tenant/spaces/{id}/availability/{availabilityId}
```

**Remover slot:**
```
DELETE /api/v1/tenant/spaces/{id}/availability/{availabilityId}
```

### 6.7 Bloqueios de Espaço

**Listar:**
```
GET /api/v1/tenant/spaces/{id}/blocks
```

**Criar bloqueio:**
```
POST /api/v1/tenant/spaces/{id}/blocks
Body: {
  "reason": "Manutenção programada",
  "start_datetime": "2026-02-01T08:00:00Z",
  "end_datetime": "2026-02-01T18:00:00Z"
}
```

**Remover bloqueio:**
```
DELETE /api/v1/tenant/spaces/{id}/blocks/{blockId}
```

### 6.8 Regras do Espaço

**Listar:**
```
GET /api/v1/tenant/spaces/{id}/rules
```

**Criar:**
```
POST /api/v1/tenant/spaces/{id}/rules
Body: { "rule_key": "max_sound_db", "rule_value": "80", "description": "Limite de som em decibéis" }
```

**Atualizar:**
```
PUT /api/v1/tenant/spaces/{id}/rules/{ruleId}
```

**Remover:**
```
DELETE /api/v1/tenant/spaces/{id}/rules/{ruleId}
```

---

## 7. Reservas (Reservations)

**Aggregate Root** do domínio principal.

### 7.1 Listar Reservas

```
GET /api/v1/tenant/reservations
```

**Roles:** sindico, administradora, funcionario (todas); condomino (próprias)

**Query Params:**

| Param | Tipo | Descrição |
|-------|------|-----------|
| `space_id` | uuid | Filtrar por espaço |
| `unit_id` | uuid | Filtrar por unidade |
| `tenant_user_id` | uuid | Filtrar por usuário |
| `status` | string | Filtrar por status |
| `date_from` | date | Data mínima (YYYY-MM-DD) |
| `date_to` | date | Data máxima (YYYY-MM-DD) |
| `cursor` | string | Cursor de paginação |
| `per_page` | integer | Itens por página |

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "space": {
        "id": "uuid",
        "name": "Salão de Festas",
        "type": "party_hall"
      },
      "unit": {
        "id": "uuid",
        "identifier": "101",
        "block": { "id": "uuid", "identifier": "A" }
      },
      "user": {
        "id": "uuid",
        "name": "João Silva"
      },
      "status": "confirmed",
      "start_datetime": "2026-02-15T14:00:00Z",
      "end_datetime": "2026-02-15T22:00:00Z",
      "expected_guests": 30,
      "notes": "Aniversário",
      "approved_by": { "id": "uuid", "name": "Síndico" },
      "approved_at": "2026-02-10T08:00:00Z",
      "guests_count": 15,
      "service_providers_count": 2,
      "created_at": "2026-02-08T10:00:00Z"
    }
  ]
}
```

### 7.2 Criar Reserva

```
POST /api/v1/tenant/spaces/{spaceId}/reservations
```

**Roles:** sindico, administradora, condomino (própria unidade)

**Request Body:**
```json
{
  "unit_id": "uuid",
  "start_datetime": "2026-02-15T14:00:00Z",
  "end_datetime": "2026-02-15T22:00:00Z",
  "expected_guests": 30,
  "notes": "Aniversário de 15 anos"
}
```

| Campo | Tipo | Obrigatório | Validação |
|-------|------|:-----------:|-----------|
| `unit_id` | uuid | Sim | Unidade ativa do condomino |
| `start_datetime` | datetime | Sim | Futuro, dentro da disponibilidade, respeita min_advance_hours |
| `end_datetime` | datetime | Sim | > start, respeita max_duration_hours |
| `expected_guests` | integer | Sim | >= 0, <= capacity do espaço |
| `notes` | string | Não | Max 1000 |

**Response 201:**
```json
{
  "data": {
    "id": "uuid",
    "status": "pending_approval",
    "space": { ... },
    "unit": { ... },
    "start_datetime": "2026-02-15T14:00:00Z",
    "end_datetime": "2026-02-15T22:00:00Z",
    "expected_guests": 30,
    "notes": "Aniversário de 15 anos",
    "created_at": "2026-02-08T10:00:00Z"
  }
}
```

**Status inicial:** `pending_approval` se espaço requer aprovação; `confirmed` caso contrário.

**Erros:**
| Status | Código | Descrição |
|--------|--------|-----------|
| 409 | `RESERVATION_CONFLICT` | Conflito com reserva existente |
| 403 | `UNIT_INACTIVE` | Unidade desativada |
| 403 | `PENALTY_ACTIVE` | Penalidade ativa bloqueia reservas |
| 422 | `SPACE_NOT_AVAILABLE` | Espaço não disponível no horário |
| 422 | `SPACE_BLOCKED` | Espaço bloqueado no período |
| 422 | `SPACE_CAPACITY_EXCEEDED` | Número de convidados excede capacidade |
| 422 | `RESERVATION_TOO_EARLY` | Não respeita antecedência mínima |
| 422 | `RESERVATION_TOO_FAR` | Excede antecedência máxima |
| 422 | `RESERVATION_TOO_LONG` | Excede duração máxima |
| 422 | `FEATURE_NOT_AVAILABLE` | Funcionalidade não disponível no plano |

### 7.3 Obter Reserva

```
GET /api/v1/tenant/reservations/{id}
```

Inclui detalhes completos: espaço, unidade, convidados, prestadores.

### 7.4 Máquina de Estados da Reserva

```
pending_approval ──→ confirmed ──→ in_use ──→ completed
       │                 │           │
       ├──→ rejected     ├──→ canceled │
       │                 │           │
       └──→ canceled     └──→ no_show└──→ no_show
```

### 7.5 Aprovar Reserva

```
PATCH /api/v1/tenant/reservations/{id}/approve
```

**Roles:** sindico, administradora

**Pré-condição:** status = `pending_approval`

**Response 200:** Reserva com status `confirmed`

### 7.6 Rejeitar Reserva

```
PATCH /api/v1/tenant/reservations/{id}/reject
Body: { "reason": "Motivo da rejeição" }
```

**Roles:** sindico, administradora

### 7.7 Cancelar Reserva

```
PATCH /api/v1/tenant/reservations/{id}/cancel
Body: { "reason": "Motivo do cancelamento" }
```

**Roles:** sindico, administradora (qualquer), condomino (próprias)

**Nota:** Se cancelamento for tardio (após prazo de `cancellation_deadline_hours`), gera infração automática `late_cancellation`.

### 7.8 Check-in (Iniciar Uso)

```
PATCH /api/v1/tenant/reservations/{id}/check-in
```

**Roles:** sindico, administradora, funcionario

**Pré-condição:** status = `confirmed`, data = hoje

### 7.9 Completar Reserva

```
PATCH /api/v1/tenant/reservations/{id}/complete
```

**Roles:** sindico, administradora, funcionario

**Pré-condição:** status = `in_use`

### 7.10 Convidados da Reserva

**Listar:**
```
GET /api/v1/tenant/reservations/{id}/guests
```

**Adicionar:**
```
POST /api/v1/tenant/reservations/{id}/guests
Body: {
  "name": "Carlos Santos",
  "document": "12345678900",
  "document_type": "cpf",
  "phone": "11999997777"
}
```

| Campo | Tipo | Obrigatório | Validação |
|-------|------|:-----------:|-----------|
| `name` | string | Sim | Max 255 |
| `document` | string | Não | Max 20 |
| `document_type` | string | Não | `cpf`, `rg`, `cnh`, `passport`, `other` |
| `phone` | string | Não | Max 20 |

**Atualizar:**
```
PUT /api/v1/tenant/reservations/{id}/guests/{guestId}
```

**Remover:**
```
DELETE /api/v1/tenant/reservations/{id}/guests/{guestId}
```

**Check-in:**
```
PATCH /api/v1/tenant/reservations/{id}/guests/{guestId}/check-in
```
**Roles:** sindico, administradora, funcionario

**Check-out:**
```
PATCH /api/v1/tenant/reservations/{id}/guests/{guestId}/check-out
```
**Roles:** sindico, administradora, funcionario

### 7.11 Prestadores de Serviço

**Listar:**
```
GET /api/v1/tenant/reservations/{id}/service-providers
```

**Adicionar:**
```
POST /api/v1/tenant/reservations/{id}/service-providers
Body: {
  "name": "Pedro Técnico",
  "company": "SomPro LTDA",
  "document": "12345678000190",
  "document_type": "cnpj",
  "phone": "11999996666",
  "service_description": "Serviço de som e iluminação"
}
```

| Campo | Tipo | Obrigatório | Validação |
|-------|------|:-----------:|-----------|
| `name` | string | Sim | Max 255 |
| `company` | string | Não | Max 255 |
| `document` | string | Sim | Max 20 |
| `document_type` | string | Sim | `cpf`, `cnpj` |
| `phone` | string | Não | Max 20 |
| `service_description` | string | Sim | Text |

**Atualizar/Remover/Check-in/Check-out:** Mesmo padrão dos convidados.

**Regra:** Prestador sem vínculo com reserva = acesso negado na portaria.

---

## 8. Governança (Governance)

### 8.1 Regras do Condomínio

**Listar:**
```
GET /api/v1/tenant/rules
```
**Roles:** Todas (leitura)

**Query Params:** `category`, `is_active`, `cursor`, `per_page`

**Criar:**
```
POST /api/v1/tenant/rules
Body: {
  "title": "Proibido som alto após 22h",
  "description": "Descrição detalhada da regra...",
  "category": "noise"
}
```
**Roles:** sindico, administradora

**Obter/Atualizar/Desativar:**
```
GET    /api/v1/tenant/rules/{id}
PUT    /api/v1/tenant/rules/{id}
DELETE /api/v1/tenant/rules/{id}
```

### 8.2 Documentos Legais (Condominium Documents)

Gerenciamento de documentos legais do condomínio: Convenção do Condomínio, Regimento Interno e Atas de Assembleia. Suporta versionamento — apenas um documento ativo por tipo.

**Listar Documentos:**
```
GET /api/v1/tenant/documents
```
**Roles:** Todas (leitura)

**Query Params:** `type` (convencao, regimento_interno, ata_assembleia, other), `status` (draft, active, archived), `cursor`, `per_page`

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "type": "convencao",
      "title": "Convenção do Condomínio Residencial Parque das Flores",
      "version": 3,
      "status": "active",
      "approved_at": "2025-03-15T00:00:00Z",
      "approved_in": "Assembleia Geral Extraordinária de 15/03/2025",
      "sections_count": 45,
      "created_by": { "id": "uuid", "name": "Maria Silva" },
      "created_at": "2025-03-20T10:00:00Z"
    }
  ]
}
```

**Criar Documento (upload):**
```
POST /api/v1/tenant/documents
Body: {
  "type": "regimento_interno",
  "title": "Regimento Interno - Versão 2026",
  "full_text": "Capítulo I - Das Disposições Gerais\nArt. 1º - ...",
  "file_path": "/documents/regimento-2026.pdf",
  "approved_at": "2026-01-20T00:00:00Z",
  "approved_in": "Assembleia Geral Ordinária de 20/01/2026"
}
```
**Roles:** sindico, administradora

**Response 201:** Documento criado com status `draft`.

**Obter Documento Completo:**
```
GET /api/v1/tenant/documents/{id}
```
**Roles:** Todas (leitura)

**Response 200:** Inclui `full_text` e lista de `sections`.

**Ativar Documento:**
```
PATCH /api/v1/tenant/documents/{id}/activate
```
**Roles:** sindico

**Regra:** Ativar um documento arquiva automaticamente o anterior do mesmo tipo. Apenas documentos em `draft` podem ser ativados.

**Arquivar Documento:**
```
PATCH /api/v1/tenant/documents/{id}/archive
```
**Roles:** sindico

**Listar Seções de um Documento:**
```
GET /api/v1/tenant/documents/{id}/sections
```
**Roles:** Todas (leitura)

**Query Params:** `parent_section_id` (para navegação hierárquica), `cursor`, `per_page`

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "section_number": "Art. 15",
      "title": "Do Horário de Silêncio",
      "content": "É vedado produzir ruídos que perturbem...",
      "order_index": 15,
      "parent_section_id": null,
      "children_count": 3
    }
  ]
}
```

**Obter Seção Específica:**
```
GET /api/v1/tenant/documents/{documentId}/sections/{sectionId}
```
**Roles:** Todas (leitura)

**Response 200:** Inclui `content` completo e seções filhas.

**Buscar em Documentos (texto):**
```
GET /api/v1/tenant/documents/search
```
**Roles:** Todas (leitura)

**Query Params:** `q` (termo de busca), `type` (filtro por tipo de documento), `cursor`, `per_page`

**Response 200:** Retorna seções que contêm o termo, com highlight e contexto.

**Parsear Seções de um Documento:**
```
POST /api/v1/tenant/documents/{id}/parse-sections
Body: {
  "sections": [
    {
      "section_number": "Art. 1",
      "title": "Das Disposições Gerais",
      "content": "O presente regimento...",
      "order_index": 1,
      "parent_section_number": null
    }
  ]
}
```
**Roles:** sindico, administradora

**Regra:** Pode ser feito manualmente ou via processo automatizado (IA). Apenas documentos em `draft` podem ter seções parseadas.

### 8.3 Infrações (Violations)

**Listar:**
```
GET /api/v1/tenant/violations
```
**Roles:** sindico, administradora (todas); condomino (próprias)

**Query Params:** `unit_id`, `status`, `type`, `severity`, `cursor`, `per_page`

**Response 200:**
```json
{
  "data": [
    {
      "id": "uuid",
      "unit": { "id": "uuid", "identifier": "101" },
      "user": { "id": "uuid", "name": "João Silva" },
      "reservation": { "id": "uuid" },
      "rule": { "id": "uuid", "title": "..." },
      "type": "no_show",
      "severity": "medium",
      "description": "Não compareceu à reserva confirmada.",
      "status": "open",
      "is_automatic": true,
      "created_at": "2026-02-15T22:30:00Z"
    }
  ]
}
```

**Criar Infração Manual:**
```
POST /api/v1/tenant/violations
Body: {
  "unit_id": "uuid",
  "tenant_user_id": "uuid",
  "type": "noise_complaint",
  "severity": "medium",
  "description": "Barulho excessivo após 22h em 15/02.",
  "rule_id": "uuid"
}
```
**Roles:** sindico, administradora

**Contestar:**
```
PATCH /api/v1/tenant/violations/{id}/contest
Body: { "reason": "Motivo da contestação..." }
```
**Roles:** condomino (próprias infrações)

**Manter Infração (após contestação):**
```
PATCH /api/v1/tenant/violations/{id}/uphold
Body: { "response": "Infração mantida. Evidências confirmam..." }
```
**Roles:** sindico, administradora

**Revogar:**
```
PATCH /api/v1/tenant/violations/{id}/revoke
Body: { "reason": "Infração revogada por..." }
```
**Roles:** sindico, administradora

### 8.4 Penalidades (Penalties)

**Listar:**
```
GET /api/v1/tenant/penalties
```
**Roles:** sindico, administradora (todas); condomino (próprias)

**Query Params:** `unit_id`, `status`, `type`, `cursor`, `per_page`

**Obter:**
```
GET /api/v1/tenant/penalties/{id}
```

**Revogar:**
```
PATCH /api/v1/tenant/penalties/{id}/revoke
Body: { "reason": "Motivo da revogação" }
```
**Roles:** sindico, administradora

### 8.5 Políticas de Penalidade

**Listar:**
```
GET /api/v1/tenant/penalty-policies
```

**Criar:**
```
POST /api/v1/tenant/penalty-policies
Body: {
  "violation_type": "no_show",
  "occurrence_threshold": 3,
  "penalty_type": "temporary_block",
  "block_days": 30
}
```
**Roles:** sindico, administradora

**Atualizar:**
```
PUT /api/v1/tenant/penalty-policies/{id}
```

---

## 9. Comunicação (Communication)

### 9.1 Avisos (Announcements)

**Listar:**
```
GET /api/v1/tenant/announcements
```
**Roles:** sindico, administradora (todos); condomino, funcionario (direcionados a ele)

**Query Params:** `priority`, `audience_type`, `cursor`, `per_page`

**Publicar:**
```
POST /api/v1/tenant/announcements
Body: {
  "title": "Manutenção na piscina",
  "body": "A piscina estará fechada de 01/03 a 05/03 para manutenção.",
  "priority": "high",
  "audience_type": "all",
  "audience_ids": null,
  "expires_at": "2026-03-05T23:59:59Z"
}
```
**Roles:** sindico, administradora

| Campo | Tipo | Obrigatório | Validação |
|-------|------|:-----------:|-----------|
| `title` | string | Sim | Max 255 |
| `body` | string | Sim | Text |
| `priority` | string | Não | `low`, `normal`, `high`, `urgent`. Default: `normal` |
| `audience_type` | string | Sim | `all`, `block`, `units` |
| `audience_ids` | array | Cond. | Obrigatório se `audience_type` = `block` ou `units` |
| `expires_at` | datetime | Não | Futuro |

**Marcar como Lido:**
```
POST /api/v1/tenant/announcements/{id}/read
```
**Roles:** Todas

**Verificar Status de Leitura:**
```
GET /api/v1/tenant/announcements/{id}/reads
```
**Roles:** sindico, administradora

**Response 200:**
```json
{
  "data": {
    "total_audience": 50,
    "total_read": 35,
    "read_percentage": 70.0,
    "reads": [
      {
        "user": { "id": "uuid", "name": "João Silva" },
        "read_at": "2026-02-10T08:30:00Z"
      }
    ]
  }
}
```

### 9.2 Solicitações de Suporte (Support Requests)

**Listar:**
```
GET /api/v1/tenant/support-requests
```
**Roles:** sindico, administradora (todas); condomino, funcionario (próprias)

**Query Params:** `status`, `category`, `priority`, `cursor`, `per_page`

**Criar:**
```
POST /api/v1/tenant/support-requests
Body: {
  "subject": "Vazamento no banheiro",
  "category": "maintenance",
  "priority": "high"
}
```
**Roles:** Todas

**Obter:**
```
GET /api/v1/tenant/support-requests/{id}
```

**Alterar Status:**
```
PATCH /api/v1/tenant/support-requests/{id}/status
Body: { "status": "in_progress" }
```
**Roles:** sindico, administradora

**Máquina de estados:** `open → in_progress → resolved → closed`

**Listar Mensagens:**
```
GET /api/v1/tenant/support-requests/{id}/messages
```

**Nota:** Mensagens com `is_internal = true` são visíveis apenas para sindico/administradora.

**Adicionar Mensagem:**
```
POST /api/v1/tenant/support-requests/{id}/messages
Body: {
  "body": "Texto da mensagem...",
  "is_internal": false
}
```
**Roles:** sindico, administradora (todas + internas); condomino (não internas, apenas próprios)

---

## 10. Portaria (Gate Control)

### 10.1 Reservas de Hoje

```
GET /api/v1/tenant/gate/today
```
**Roles:** sindico, administradora, funcionario

**Response 200:**
```json
{
  "data": [
    {
      "reservation": {
        "id": "uuid",
        "space": { "name": "Salão de Festas" },
        "unit": { "identifier": "101" },
        "user": { "name": "João Silva" },
        "status": "confirmed",
        "start_datetime": "2026-02-15T14:00:00Z",
        "end_datetime": "2026-02-15T22:00:00Z"
      },
      "guests": [
        {
          "id": "uuid",
          "name": "Carlos Santos",
          "document": "***456***",
          "checked_in_at": null
        }
      ],
      "service_providers": [
        {
          "id": "uuid",
          "name": "Pedro Técnico",
          "company": "SomPro LTDA",
          "document": "***678***",
          "checked_in_at": null
        }
      ]
    }
  ]
}
```

### 10.2 Chegadas Esperadas

```
GET /api/v1/tenant/gate/expected
```
**Roles:** sindico, administradora, funcionario

Retorna convidados e prestadores que ainda não fizeram check-in para reservas de hoje.

### 10.3 Check-in Rápido (por documento)

```
POST /api/v1/tenant/gate/check-in
Body: { "document": "12345678900" }
```
**Roles:** funcionario, sindico, administradora

Busca o convidado ou prestador pelo documento nas reservas de hoje e realiza check-in.

**Erros:**
| Status | Código | Descrição |
|--------|--------|-----------|
| 404 | `PERSON_NOT_FOUND` | Nenhum convidado/prestador com este documento para hoje |
| 409 | `ALREADY_CHECKED_IN` | Já realizou check-in |

### 10.4 Check-out Rápido

```
POST /api/v1/tenant/gate/check-out
Body: { "document": "12345678900" }
```
**Roles:** funcionario, sindico, administradora

---

## 11. Configurações do Condomínio

### 11.1 Obter Configurações

```
GET /api/v1/tenant/settings
```
**Roles:** sindico, administradora

**Response 200:**
```json
{
  "data": {
    "tenant_name": "Condomínio Solar",
    "tenant_type": "vertical",
    "timezone": "America/Sao_Paulo",
    "locale": "pt-BR",
    "notifications_enabled": true,
    "auto_close_support_days": 7
  }
}
```

### 11.2 Atualizar Configurações

```
PUT /api/v1/tenant/settings
```
**Roles:** sindico, administradora

### 11.3 Features do Tenant

```
GET /api/v1/tenant/features
```
**Roles:** sindico, administradora

**Response 200:**
```json
{
  "data": {
    "max_units": 200,
    "max_users": 500,
    "max_residents_per_unit": 6,
    "max_spaces": 10,
    "max_reservations_per_month": 100,
    "can_use_ai": true,
    "can_use_support": true,
    "allow_recurring_reservations": false
  }
}
```

---

## 12. Dashboard / Relatórios

### 12.1 Overview

```
GET /api/v1/tenant/dashboard/overview
```
**Roles:** sindico, administradora

**Response 200:**
```json
{
  "data": {
    "total_units": 120,
    "total_residents": 280,
    "total_spaces": 8,
    "active_reservations": 15,
    "pending_approvals": 3,
    "open_violations": 5,
    "open_support_requests": 12,
    "unread_announcements_avg": 30.5
  }
}
```

### 12.2 Estatísticas de Reservas

```
GET /api/v1/tenant/dashboard/reservations
```
**Query Params:** `period` (7d, 30d, 90d)

### 12.3 Estatísticas de Governança

```
GET /api/v1/tenant/dashboard/governance
```

### 12.4 Ocupação dos Espaços

```
GET /api/v1/tenant/dashboard/occupancy
```
**Query Params:** `space_id`, `period` (7d, 30d, 90d)

---

## 13. IA (Artificial Intelligence)

### 13.1 Chat com IA

```
POST /api/v1/tenant/ai/chat
Body: {
  "message": "Quais são os horários disponíveis para o salão de festas na próxima semana?"
}
```
**Roles:** sindico, administradora, condomino

**Response 200:**
```json
{
  "data": {
    "response": "Os horários disponíveis para o Salão de Festas na próxima semana são: ...",
    "suggested_actions": [
      {
        "id": "uuid",
        "tool_name": "create_reservation",
        "description": "Criar reserva para Salão de Festas em 20/02 das 14h às 22h",
        "input_data": { ... },
        "requires_confirmation": true
      }
    ]
  }
}
```

### 13.2 Sugestões da IA

```
POST /api/v1/tenant/ai/suggest
Body: {
  "context": "reservation",
  "space_id": "uuid",
  "date": "2026-02-20"
}
```
**Roles:** sindico, administradora, condomino

### 13.3 Ações Pendentes da IA

```
GET /api/v1/tenant/ai/actions
```
**Roles:** sindico, administradora, condomino (próprias)

### 13.4 Confirmar Ação da IA

```
PATCH /api/v1/tenant/ai/actions/{id}/confirm
```
**Roles:** Quem solicitou a ação ou sindico/administradora

### 13.5 Rejeitar Ação da IA

```
PATCH /api/v1/tenant/ai/actions/{id}/reject
Body: { "reason": "Não desejo fazer essa reserva" }
```

---

## 14. Códigos de Erro

### 14.1 Autenticação

| Código | HTTP | Descrição |
|--------|:----:|-----------|
| `AUTH_INVALID_CREDENTIALS` | 401 | E-mail ou senha inválidos |
| `AUTH_TOKEN_EXPIRED` | 401 | Token expirado |
| `AUTH_TOKEN_REVOKED` | 401 | Token revogado |
| `AUTH_MFA_REQUIRED` | 200 | MFA necessário (retorna mfa_required_token) |
| `AUTH_INVALID_MFA_CODE` | 401 | Código MFA inválido |
| `AUTH_MFA_TOKEN_EXPIRED` | 401 | Token MFA expirado |
| `AUTH_ACCOUNT_LOCKED` | 403 | Conta bloqueada por tentativas |
| `AUTH_ACCOUNT_DISABLED` | 403 | Conta desativada |
| `AUTH_INVITATION_EXPIRED` | 422 | Convite expirado |
| `AUTH_INVITATION_INVALID` | 422 | Convite inválido |

### 14.2 Tenant

| Código | HTTP | Descrição |
|--------|:----:|-----------|
| `TENANT_NOT_FOUND` | 404 | Tenant não encontrado |
| `TENANT_INACTIVE` | 403 | Tenant suspenso/cancelado |
| `TENANT_READ_ONLY` | 403 | Tenant em modo somente leitura (assinatura em atraso) |
| `SUBSCRIPTION_INVALID` | 403 | Assinatura inválida ou expirada |

### 14.3 Reservas

| Código | HTTP | Descrição |
|--------|:----:|-----------|
| `RESERVATION_CONFLICT` | 409 | Conflito com reserva existente |
| `RESERVATION_NOT_FOUND` | 404 | Reserva não encontrada |
| `RESERVATION_INVALID_TRANSITION` | 422 | Transição de status inválida |
| `RESERVATION_TOO_EARLY` | 422 | Não respeita antecedência mínima |
| `RESERVATION_TOO_FAR` | 422 | Excede antecedência máxima |
| `RESERVATION_TOO_LONG` | 422 | Excede duração máxima |

### 14.4 Espaços

| Código | HTTP | Descrição |
|--------|:----:|-----------|
| `SPACE_NOT_FOUND` | 404 | Espaço não encontrado |
| `SPACE_NOT_AVAILABLE` | 422 | Espaço não disponível no horário |
| `SPACE_BLOCKED` | 422 | Espaço bloqueado no período |
| `SPACE_CAPACITY_EXCEEDED` | 422 | Capacidade excedida |
| `SPACE_INACTIVE` | 422 | Espaço inativo ou em manutenção |

### 14.5 Unidades e Moradores

| Código | HTTP | Descrição |
|--------|:----:|-----------|
| `UNIT_NOT_FOUND` | 404 | Unidade não encontrada |
| `UNIT_INACTIVE` | 403 | Unidade desativada |
| `UNIT_IDENTIFIER_EXISTS` | 409 | Identificador já existe |
| `BLOCK_IDENTIFIER_EXISTS` | 409 | Identificador de bloco já existe |
| `USER_EMAIL_EXISTS` | 409 | E-mail já registrado |
| `RESIDENT_ALREADY_EXISTS` | 409 | Morador já vinculado |

### 14.6 Governança

| Código | HTTP | Descrição |
|--------|:----:|-----------|
| `PENALTY_ACTIVE` | 403 | Penalidade ativa impede a ação |
| `VIOLATION_NOT_FOUND` | 404 | Infração não encontrada |
| `VIOLATION_ALREADY_CONTESTED` | 409 | Já existe contestação |
| `PENALTY_NOT_FOUND` | 404 | Penalidade não encontrada |

### 14.7 Pessoas

| Código | HTTP | Descrição |
|--------|:----:|-----------|
| `PERSON_NOT_FOUND` | 404 | Convidado/prestador não encontrado |
| `ALREADY_CHECKED_IN` | 409 | Já realizou check-in |
| `NOT_CHECKED_IN` | 422 | Não é possível check-out sem check-in |
| `NO_LINKED_RESERVATION` | 403 | Prestador sem vínculo com reserva |

### 14.8 Plano e Features

| Código | HTTP | Descrição |
|--------|:----:|-----------|
| `PLAN_LIMIT_REACHED` | 422 | Limite do plano atingido |
| `FEATURE_NOT_AVAILABLE` | 403 | Funcionalidade não disponível no plano |

### 14.9 Gerais

| Código | HTTP | Descrição |
|--------|:----:|-----------|
| `VALIDATION_ERROR` | 422 | Erro de validação (detalhes nos campos) |
| `FORBIDDEN` | 403 | Sem permissão para esta ação |
| `NOT_FOUND` | 404 | Recurso não encontrado |
| `RATE_LIMIT_EXCEEDED` | 429 | Limite de requisições excedido |
| `INTERNAL_ERROR` | 500 | Erro interno do servidor |

---

## 15. Rate Limiting

### 15.1 Limites por Grupo

| Grupo | Limite | Janela | Escopo |
|-------|--------|--------|--------|
| Autenticação | 10 req/min | 1 minuto | Por IP + endpoint |
| Endpoints padrão | 100 req/min | 1 minuto | Por usuário |
| Endpoints de escrita | 30 req/min | 1 minuto | Por usuário |
| Portaria | 200 req/min | 1 minuto | Por usuário |
| IA | 20 req/min | 1 minuto | Por usuário |
| Dashboard | 30 req/min | 1 minuto | Por usuário |

### 15.2 Headers de Rate Limit

```
X-RateLimit-Limit: 100
X-RateLimit-Remaining: 95
X-RateLimit-Reset: 1707998400
```

### 15.3 Resposta 429

```json
{
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Limite de requisições excedido. Tente novamente em 45 segundos.",
    "details": [
      { "field": "retry_after", "message": "45" }
    ]
  }
}
```

Header adicional: `Retry-After: 45`

---

## 16. Status

**Documento ATIVO** — Referência definitiva da Tenant API para implementação.

| Campo | Valor |
|-------|-------|
| Última atualização | 2026-02-10 |
| Versão | 1.0.0 |
| Total de endpoints | ~95 |
| Responsável | Equipe Backend |
