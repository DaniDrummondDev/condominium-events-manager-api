# Platform API - Condominium Events Manager

## Status do Documento

**Status:** Ativo\
**Ultima atualizacao:** 2026-02-10\
**Versao da API:** v1

---

## Sumario

1. [Visao Geral](#1-visao-geral)
2. [Autenticacao (Platform Auth)](#2-autenticacao-platform-auth)
3. [Tenants](#3-tenants)
4. [Plans](#4-plans)
5. [Subscriptions](#5-subscriptions)
6. [Invoices](#6-invoices)
7. [Payments](#7-payments)
8. [Feature Flags](#8-feature-flags)
9. [Platform Users](#9-platform-users)
10. [Dunning Policies](#10-dunning-policies)
11. [Webhooks (Incoming)](#11-webhooks-incoming)
12. [Audit Logs](#12-audit-logs)
13. [Health Check](#13-health-check)
14. [Resumo dos Endpoints](#14-resumo-dos-endpoints)

---

## 1. Visao Geral

A Platform API e utilizada pelos administradores da plataforma (`super_admin`, `admin`, `support`) para gerenciar tenants, cobrancas, planos e feature flags do Condominium Events Manager.

Esta e uma aplicacao **Laravel PHP SaaS B2B Multi-Tenant**.

### Base URL

```
/api/v1/platform
```

### Autenticacao

Todas as requisicoes (exceto login, webhooks e health check) exigem um token JWT da plataforma enviado no header `Authorization`:

```
Authorization: Bearer {access_token}
```

### Formato de Resposta

Todas as respostas sao retornadas em formato JSON.

**Resposta de sucesso:**

```json
{
    "data": { },
    "meta": { }
}
```

**Resposta de erro:**

```json
{
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Descricao legivel do erro.",
        "details": {
            "field": ["Mensagem de validacao."]
        }
    }
}
```

### Identificadores

Todos os IDs utilizam o formato **UUIDv7**, que garante unicidade e ordenacao temporal.

Exemplo: `01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a`

### Roles da Plataforma

| Role           | Descricao                                       |
|----------------|-------------------------------------------------|
| `super_admin`  | Acesso total a plataforma                       |
| `admin`        | Gerenciamento de tenants, planos e cobrancas    |
| `support`      | Acesso somente leitura para suporte ao cliente  |

### Paginacao

Endpoints que retornam listas utilizam paginacao com os seguintes parametros:

| Parametro  | Tipo    | Padrao | Descricao                                 |
|------------|---------|--------|-------------------------------------------|
| `page`     | integer | 1      | Numero da pagina                          |
| `per_page` | integer | 15     | Quantidade de itens por pagina (max: 100) |

**Exemplo de resposta paginada:**

```json
{
    "data": [],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 87,
        "last_page": 6
    }
}
```

### Codigos de Erro Comuns

| Codigo HTTP | Codigo do Erro           | Descricao                            |
|-------------|--------------------------|--------------------------------------|
| 400         | `VALIDATION_ERROR`       | Dados da requisicao invalidos        |
| 401         | `UNAUTHENTICATED`        | Token ausente ou invalido            |
| 403         | `FORBIDDEN`              | Sem permissao para esta acao         |
| 404         | `NOT_FOUND`              | Recurso nao encontrado               |
| 409         | `CONFLICT`               | Conflito de estado do recurso        |
| 422         | `UNPROCESSABLE_ENTITY`   | Entidade nao processavel             |
| 429         | `TOO_MANY_REQUESTS`      | Limite de requisicoes excedido       |
| 500         | `INTERNAL_ERROR`         | Erro interno do servidor             |

---

## 2. Autenticacao (Platform Auth)

Endpoints responsaveis pela autenticacao de usuarios da plataforma (equipe interna do SaaS).

---

### POST /api/v1/auth/platform/login

Realiza o login de um usuario da plataforma.

- **Autenticacao:** Nao requerida
- **Roles permitidas:** N/A

**Request Body:**

```json
{
    "email": "admin@plataforma.com.br",
    "password": "SenhaSegura@123"
}
```

| Campo      | Tipo   | Obrigatorio | Descricao                        |
|------------|--------|-------------|----------------------------------|
| `email`    | string | Sim         | Email do usuario da plataforma   |
| `password` | string | Sim         | Senha do usuario                 |

**Resposta de sucesso (200 OK) - Sem MFA:**

```json
{
    "data": {
        "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
        "refresh_token": "def50200a1b2c3d4e5f6...",
        "expires_in": 3600,
        "user": {
            "id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
            "name": "Carlos Admin",
            "email": "admin@plataforma.com.br",
            "role": "admin"
        }
    }
}
```

**Resposta de sucesso (200 OK) - MFA requerido:**

```json
{
    "data": {
        "mfa_token": "mfa_01912e4a7b3c7d8e9f0a1b2c3d4e5f6a",
        "mfa_method": "totp"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro                  | Descricao                                                          |
|--------|-----------------------|--------------------------------------------------------------------|
| 401    | `INVALID_CREDENTIALS` | Email ou senha invalidos                                           |
| 422    | `VALIDATION_ERROR`    | Campos obrigatorios ausentes ou formato invalido                   |
| 429    | `TOO_MANY_REQUESTS`   | Muitas tentativas de login. Tente novamente em {seconds} segundos  |

**Exemplo de erro (401):**

```json
{
    "error": {
        "code": "INVALID_CREDENTIALS",
        "message": "Email ou senha invalidos."
    }
}
```

**Exemplo de erro (429):**

```json
{
    "error": {
        "code": "TOO_MANY_REQUESTS",
        "message": "Muitas tentativas de login. Tente novamente em 60 segundos.",
        "details": {
            "retry_after": 60
        }
    }
}
```

---

### POST /api/v1/auth/platform/mfa/verify

Verifica o codigo MFA (Multi-Factor Authentication) apos o login indicar que MFA e obrigatorio.

- **Autenticacao:** Nao requerida (utiliza mfa_token)
- **Roles permitidas:** N/A

**Request Body:**

```json
{
    "mfa_token": "mfa_01912e4a7b3c7d8e9f0a1b2c3d4e5f6a",
    "code": "482910"
}
```

| Campo       | Tipo   | Obrigatorio | Descricao                                              |
|-------------|--------|-------------|--------------------------------------------------------|
| `mfa_token` | string | Sim         | Token temporario recebido no login                     |
| `code`      | string | Sim         | Codigo TOTP de 6 digitos gerado pelo app autenticador  |

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
        "refresh_token": "def50200a1b2c3d4e5f6...",
        "expires_in": 3600
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro                | Descricao                                    |
|--------|---------------------|----------------------------------------------|
| 401    | `INVALID_MFA_CODE`  | Codigo MFA invalido ou expirado              |
| 422    | `VALIDATION_ERROR`  | Campos obrigatorios ausentes                 |
| 429    | `TOO_MANY_REQUESTS` | Muitas tentativas de verificacao MFA         |

**Exemplo de erro (401):**

```json
{
    "error": {
        "code": "INVALID_MFA_CODE",
        "message": "Codigo MFA invalido ou expirado."
    }
}
```

---

### POST /api/v1/auth/token/refresh

Renova o access token utilizando um refresh token valido. O refresh token anterior e invalidado apos o uso (rotacao de tokens).

- **Autenticacao:** Nao requerida (utiliza refresh_token no body)
- **Roles permitidas:** N/A

**Request Body:**

```json
{
    "refresh_token": "def50200a1b2c3d4e5f6..."
}
```

| Campo           | Tipo   | Obrigatorio | Descricao                             |
|-----------------|--------|-------------|---------------------------------------|
| `refresh_token` | string | Sim         | Refresh token valido e nao expirado   |

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
        "refresh_token": "def50200f6e5d4c3b2a1...",
        "expires_in": 3600
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro                     | Descricao                                         |
|--------|--------------------------|---------------------------------------------------|
| 401    | `INVALID_REFRESH_TOKEN`  | Refresh token invalido, expirado ou ja utilizado   |
| 422    | `VALIDATION_ERROR`       | Campo refresh_token ausente                        |

**Exemplo de erro (401):**

```json
{
    "error": {
        "code": "INVALID_REFRESH_TOKEN",
        "message": "Refresh token invalido ou expirado."
    }
}
```

---

### POST /api/v1/auth/logout

Revoga o access token e o refresh token associado, encerrando a sessao.

- **Autenticacao:** Requerida (Bearer token)
- **Roles permitidas:** Todas

**Headers:**

```
Authorization: Bearer {access_token}
```

**Request Body:** Nenhum

**Resposta de sucesso (204 No Content):**

Sem corpo de resposta.

**Cenarios de Erro:**

| Codigo | Erro              | Descricao                             |
|--------|-------------------|---------------------------------------|
| 401    | `UNAUTHENTICATED` | Token ausente, invalido ou expirado   |

---

## 3. Tenants

Endpoints para gerenciamento dos condominios (tenants) cadastrados na plataforma.

---

### GET /api/v1/platform/tenants

Retorna a lista paginada de todos os tenants da plataforma.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`, `support`

**Query Parameters:**

| Parametro  | Tipo    | Obrigatorio | Descricao                                                                    |
|------------|---------|-------------|------------------------------------------------------------------------------|
| `status`   | string  | Nao         | Filtrar por status: `provisioning`, `active`, `suspended`, `canceled`        |
| `search`   | string  | Nao         | Busca por nome, slug ou documento do tenant                                  |
| `sort`     | string  | Nao         | Campo de ordenacao: `name`, `created_at`, `status` (padrao: `created_at`)    |
| `order`    | string  | Nao         | Direcao da ordenacao: `asc`, `desc` (padrao: `desc`)                        |
| `page`     | integer | Nao         | Numero da pagina (padrao: 1)                                                 |
| `per_page` | integer | Nao         | Itens por pagina (padrao: 15, max: 100)                                      |

**Exemplo de Requisicao:**

```
GET /api/v1/platform/tenants?status=active&search=residencial&sort=name&order=asc&page=1&per_page=20
```

**Resposta de sucesso (200 OK):**

```json
{
    "data": [
        {
            "id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
            "name": "Condominio Residencial Aurora",
            "slug": "residencial-aurora",
            "document": "12.345.678/0001-90",
            "type": "vertical",
            "status": "active",
            "plan": {
                "id": "01912e4a-8c4d-7e9f-0a1b-2c3d4e5f6a7b",
                "name": "Profissional"
            },
            "created_at": "2026-01-15T10:30:00Z",
            "updated_at": "2026-02-01T14:20:00Z"
        },
        {
            "id": "01912e4a-9d5e-7f0a-1b2c-3d4e5f6a7b8c",
            "name": "Condominio Residencial Bela Vista",
            "slug": "residencial-bela-vista",
            "document": "98.765.432/0001-10",
            "type": "horizontal",
            "status": "active",
            "plan": {
                "id": "01912e4a-ae6f-7011-2c3d-4e5f6a7b8c9d",
                "name": "Basico"
            },
            "created_at": "2026-01-20T08:15:00Z",
            "updated_at": "2026-01-20T08:15:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 45,
        "last_page": 3
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                         |
|--------|--------------------|------------------------------------|
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido          |
| 403    | `FORBIDDEN`        | Role do usuario nao tem permissao  |
| 422    | `VALIDATION_ERROR` | Parametros de query invalidos      |

---

### POST /api/v1/platform/tenants

Cria um novo tenant na plataforma. O tenant e criado com status `provisioning` e um job assincrono (`ProvisionTenantJob`) e despachado para provisionar o ambiente. O evento `TenantCreated` e emitido.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`

**Request Body:**

```json
{
    "name": "Condominio Residencial Aurora",
    "slug": "residencial-aurora",
    "document": "12.345.678/0001-90",
    "type": "vertical",
    "plan_id": "01912e4a-8c4d-7e9f-0a1b-2c3d4e5f6a7b",
    "admin_name": "Maria Silva",
    "admin_email": "maria.silva@aurora.com.br"
}
```

| Campo         | Tipo   | Obrigatorio | Descricao                                          |
|---------------|--------|-------------|-----------------------------------------------------|
| `name`        | string | Sim         | Nome do condominio                                  |
| `slug`        | string | Sim         | Slug unico para identificacao na URL                |
| `document`    | string | Sim         | CNPJ do condominio                                  |
| `type`        | string | Sim         | Tipo: `horizontal`, `vertical` ou `mixed`           |
| `plan_id`     | uuid   | Sim         | ID do plano a ser associado                         |
| `admin_name`  | string | Sim         | Nome do administrador inicial do tenant             |
| `admin_email` | string | Sim         | Email do administrador inicial (recebera convite)   |

**Resposta de sucesso (201 Created):**

```json
{
    "data": {
        "id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
        "name": "Condominio Residencial Aurora",
        "slug": "residencial-aurora",
        "document": "12.345.678/0001-90",
        "type": "vertical",
        "status": "provisioning",
        "plan": {
            "id": "01912e4a-8c4d-7e9f-0a1b-2c3d4e5f6a7b",
            "name": "Profissional"
        },
        "admin": {
            "name": "Maria Silva",
            "email": "maria.silva@aurora.com.br"
        },
        "created_at": "2026-02-10T12:00:00Z",
        "updated_at": "2026-02-10T12:00:00Z"
    }
}
```

**Eventos disparados:**

- `TenantCreated` - Evento de dominio emitido apos a criacao do registro
- `ProvisionTenantJob` - Job assincrono despachado para provisionar banco de dados, cache e configuracoes do tenant

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                                         |
|--------|--------------------|----------------------------------------------------|
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido                          |
| 403    | `FORBIDDEN`        | Role do usuario nao tem permissao                  |
| 409    | `CONFLICT`         | Slug ou documento ja existente                     |
| 422    | `VALIDATION_ERROR` | Campos obrigatorios ausentes ou formato invalido   |

**Exemplo de erro (409):**

```json
{
    "error": {
        "code": "CONFLICT",
        "message": "Ja existe um tenant com este slug.",
        "details": {
            "slug": ["O slug 'residencial-aurora' ja esta em uso."]
        }
    }
}
```

**Exemplo de erro (422):**

```json
{
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Os dados fornecidos sao invalidos.",
        "details": {
            "name": ["O campo nome e obrigatorio."],
            "document": ["O formato do CNPJ e invalido."],
            "type": ["O tipo deve ser: horizontal, vertical ou mixed."],
            "plan_id": ["O plano informado nao foi encontrado."]
        }
    }
}
```

---

### GET /api/v1/platform/tenants/{id}

Retorna os detalhes completos de um tenant, incluindo informacoes de assinatura.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`, `support`

**Parametros de Rota:**

| Parametro | Tipo | Descricao      |
|-----------|------|----------------|
| `id`      | uuid | ID do tenant   |

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
        "name": "Condominio Residencial Aurora",
        "slug": "residencial-aurora",
        "document": "12.345.678/0001-90",
        "type": "vertical",
        "status": "active",
        "settings": {
            "timezone": "America/Sao_Paulo",
            "locale": "pt_BR",
            "features": {
                "reservations_enabled": true,
                "violations_enabled": true,
                "financial_enabled": false
            }
        },
        "subscription": {
            "id": "01912e4a-bf70-7122-3d4e-5f6a7b8c9d0e",
            "plan": {
                "id": "01912e4a-8c4d-7e9f-0a1b-2c3d4e5f6a7b",
                "name": "Profissional"
            },
            "status": "active",
            "trial_ends_at": null,
            "current_period_start": "2026-02-01T00:00:00Z",
            "current_period_end": "2026-02-28T23:59:59Z"
        },
        "created_at": "2026-01-15T10:30:00Z",
        "updated_at": "2026-02-01T14:20:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro              | Descricao                          |
|--------|-------------------|------------------------------------|
| 401    | `UNAUTHENTICATED` | Token ausente ou invalido          |
| 403    | `FORBIDDEN`       | Role do usuario nao tem permissao  |
| 404    | `NOT_FOUND`       | Tenant nao encontrado              |

**Exemplo de erro (404):**

```json
{
    "error": {
        "code": "NOT_FOUND",
        "message": "Tenant nao encontrado."
    }
}
```

---

### PUT /api/v1/platform/tenants/{id}

Atualiza os dados de um tenant existente.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`

**Parametros de Rota:**

| Parametro | Tipo | Descricao      |
|-----------|------|----------------|
| `id`      | uuid | ID do tenant   |

**Request Body:**

```json
{
    "name": "Condominio Residencial Aurora Premium",
    "settings": {
        "timezone": "America/Sao_Paulo",
        "locale": "pt_BR",
        "features": {
            "reservations_enabled": true,
            "violations_enabled": true,
            "financial_enabled": true
        }
    }
}
```

| Campo      | Tipo   | Obrigatorio | Descricao                          |
|------------|--------|-------------|------------------------------------|
| `name`     | string | Nao         | Novo nome do condominio            |
| `settings` | object | Nao         | Objeto com configuracoes do tenant |

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
        "name": "Condominio Residencial Aurora Premium",
        "slug": "residencial-aurora",
        "document": "12.345.678/0001-90",
        "type": "vertical",
        "status": "active",
        "settings": {
            "timezone": "America/Sao_Paulo",
            "locale": "pt_BR",
            "features": {
                "reservations_enabled": true,
                "violations_enabled": true,
                "financial_enabled": true
            }
        },
        "created_at": "2026-01-15T10:30:00Z",
        "updated_at": "2026-02-10T15:45:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                          |
|--------|--------------------|------------------------------------|
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido          |
| 403    | `FORBIDDEN`        | Role do usuario nao tem permissao  |
| 404    | `NOT_FOUND`        | Tenant nao encontrado              |
| 422    | `VALIDATION_ERROR` | Dados invalidos                    |

---

### POST /api/v1/platform/tenants/{id}/suspend

Suspende um tenant ativo. Usuarios do tenant perdem acesso durante a suspensao.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`

**Parametros de Rota:**

| Parametro | Tipo | Descricao      |
|-----------|------|----------------|
| `id`      | uuid | ID do tenant   |

**Request Body:**

```json
{
    "reason": "Inadimplencia - 3 faturas vencidas"
}
```

| Campo    | Tipo   | Obrigatorio | Descricao                      |
|----------|--------|-------------|--------------------------------|
| `reason` | string | Sim         | Motivo da suspensao do tenant  |

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
        "name": "Condominio Residencial Aurora",
        "slug": "residencial-aurora",
        "status": "suspended",
        "suspended_at": "2026-02-10T16:00:00Z",
        "suspended_reason": "Inadimplencia - 3 faturas vencidas",
        "updated_at": "2026-02-10T16:00:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                              |
|--------|--------------------|-----------------------------------------|
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido               |
| 403    | `FORBIDDEN`        | Role do usuario nao tem permissao       |
| 404    | `NOT_FOUND`        | Tenant nao encontrado                   |
| 409    | `CONFLICT`         | Tenant ja esta suspenso ou cancelado    |
| 422    | `VALIDATION_ERROR` | Campo reason ausente                    |

**Exemplo de erro (409):**

```json
{
    "error": {
        "code": "CONFLICT",
        "message": "O tenant ja esta suspenso."
    }
}
```

---

### POST /api/v1/platform/tenants/{id}/reactivate

Reativa um tenant que estava suspenso, restaurando o acesso dos usuarios.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`

**Parametros de Rota:**

| Parametro | Tipo | Descricao      |
|-----------|------|----------------|
| `id`      | uuid | ID do tenant   |

**Request Body:** Nenhum

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
        "name": "Condominio Residencial Aurora",
        "slug": "residencial-aurora",
        "status": "active",
        "reactivated_at": "2026-02-10T17:30:00Z",
        "updated_at": "2026-02-10T17:30:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro              | Descricao                                          |
|--------|-------------------|----------------------------------------------------|
| 401    | `UNAUTHENTICATED` | Token ausente ou invalido                          |
| 403    | `FORBIDDEN`       | Role do usuario nao tem permissao                  |
| 404    | `NOT_FOUND`       | Tenant nao encontrado                              |
| 409    | `CONFLICT`        | Tenant nao esta suspenso (nao pode ser reativado)  |

---

### POST /api/v1/platform/tenants/{id}/cancel

Cancela um tenant permanentemente. Somente `super_admin` pode executar esta acao. Os dados do tenant serao retidos pelo periodo especificado antes da exclusao definitiva.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`

**Parametros de Rota:**

| Parametro | Tipo | Descricao      |
|-----------|------|----------------|
| `id`      | uuid | ID do tenant   |

**Request Body:**

```json
{
    "reason": "Solicitacao do cliente - encerramento de contrato",
    "retention_days": 30
}
```

| Campo            | Tipo    | Obrigatorio | Descricao                                                          |
|------------------|---------|-------------|--------------------------------------------------------------------|
| `reason`         | string  | Sim         | Motivo do cancelamento                                             |
| `retention_days` | integer | Nao         | Dias para retencao dos dados antes da exclusao (padrao: 30)        |

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
        "name": "Condominio Residencial Aurora",
        "slug": "residencial-aurora",
        "status": "canceled",
        "canceled_at": "2026-02-10T18:00:00Z",
        "canceled_reason": "Solicitacao do cliente - encerramento de contrato",
        "data_retention_until": "2026-03-12T18:00:00Z",
        "updated_at": "2026-02-10T18:00:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                                  |
|--------|--------------------|--------------------------------------------|
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido                  |
| 403    | `FORBIDDEN`        | Apenas super_admin pode cancelar tenants   |
| 404    | `NOT_FOUND`        | Tenant nao encontrado                      |
| 409    | `CONFLICT`         | Tenant ja esta cancelado                   |
| 422    | `VALIDATION_ERROR` | Campo reason ausente                       |

---

### GET /api/v1/platform/tenants/{id}/metrics

Retorna as metricas operacionais de um tenant especifico.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`, `support`

**Parametros de Rota:**

| Parametro | Tipo | Descricao      |
|-----------|------|----------------|
| `id`      | uuid | ID do tenant   |

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "tenant_id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
        "units_count": 120,
        "users_count": 245,
        "spaces_count": 8,
        "reservations_this_month": 47,
        "active_violations": 3,
        "measured_at": "2026-02-10T19:00:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro              | Descricao                          |
|--------|-------------------|------------------------------------|
| 401    | `UNAUTHENTICATED` | Token ausente ou invalido          |
| 403    | `FORBIDDEN`       | Role do usuario nao tem permissao  |
| 404    | `NOT_FOUND`       | Tenant nao encontrado              |

---

## 4. Plans

Endpoints para gerenciamento dos planos de assinatura da plataforma.

---

### GET /api/v1/platform/plans

Retorna a lista de todos os planos disponiveis na plataforma.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`, `support`

**Resposta de sucesso (200 OK):**

```json
{
    "data": [
        {
            "id": "01912e4a-ae6f-7011-2c3d-4e5f6a7b8c9d",
            "name": "Basico",
            "slug": "basico",
            "description": "Plano ideal para condominios pequenos com ate 50 unidades.",
            "is_active": true,
            "current_version": {
                "id": "01912e4a-b123-7011-2c3d-4e5f6a7b8c9d",
                "version": 1,
                "status": "active",
                "created_at": "2026-01-01T00:00:00Z",
                "prices": [
                    {
                        "id": "01912e4a-c001-7011-2c3d-4e5f6a7b8c9d",
                        "billing_cycle": "monthly",
                        "price_in_cents": 19990,
                        "currency": "BRL",
                        "trial_days": 14
                    },
                    {
                        "id": "01912e4a-c002-7011-2c3d-4e5f6a7b8c9d",
                        "billing_cycle": "yearly",
                        "price_in_cents": 199900,
                        "currency": "BRL",
                        "trial_days": 14
                    }
                ],
                "features": [
                    { "key": "max_units", "value": "50", "type": "integer" },
                    { "key": "max_users", "value": "100", "type": "integer" },
                    { "key": "max_spaces", "value": "3", "type": "integer" }
                ]
            },
            "created_at": "2026-01-01T00:00:00Z",
            "updated_at": "2026-01-01T00:00:00Z"
        },
        {
            "id": "01912e4a-8c4d-7e9f-0a1b-2c3d4e5f6a7b",
            "name": "Profissional",
            "slug": "profissional",
            "description": "Plano completo para condominios de medio porte.",
            "is_active": true,
            "current_version": {
                "id": "01912e4a-d456-7e9f-0a1b-2c3d4e5f6a7b",
                "version": 2,
                "status": "active",
                "created_at": "2026-01-15T10:00:00Z",
                "prices": [
                    {
                        "id": "01912e4a-d501-7e9f-0a1b-2c3d4e5f6a7b",
                        "billing_cycle": "monthly",
                        "price_in_cents": 49990,
                        "currency": "BRL",
                        "trial_days": 14
                    },
                    {
                        "id": "01912e4a-d502-7e9f-0a1b-2c3d4e5f6a7b",
                        "billing_cycle": "semiannual",
                        "price_in_cents": 249950,
                        "currency": "BRL",
                        "trial_days": 14
                    },
                    {
                        "id": "01912e4a-d503-7e9f-0a1b-2c3d4e5f6a7b",
                        "billing_cycle": "yearly",
                        "price_in_cents": 449900,
                        "currency": "BRL",
                        "trial_days": 14
                    }
                ],
                "features": [
                    { "key": "max_units", "value": "200", "type": "integer" },
                    { "key": "max_users", "value": "500", "type": "integer" },
                    { "key": "max_spaces", "value": "10", "type": "integer" }
                ]
            },
            "created_at": "2026-01-01T00:00:00Z",
            "updated_at": "2026-01-15T10:00:00Z"
        }
    ]
}
```

**Cenarios de Erro:**

| Codigo | Erro              | Descricao                          |
|--------|-------------------|------------------------------------|
| 401    | `UNAUTHENTICATED` | Token ausente ou invalido          |
| 403    | `FORBIDDEN`       | Role do usuario nao tem permissao  |

---

### POST /api/v1/platform/plans

Cria um novo plano na plataforma.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`

**Request Body:**

```json
{
    "name": "Enterprise",
    "slug": "enterprise",
    "description": "Plano para grandes condominios e redes de condominios.",
    "prices": [
        {
            "billing_cycle": "monthly",
            "price": 99990,
            "currency": "BRL",
            "trial_days": 30
        },
        {
            "billing_cycle": "semiannual",
            "price": 499950,
            "currency": "BRL",
            "trial_days": 30
        },
        {
            "billing_cycle": "yearly",
            "price": 899900,
            "currency": "BRL",
            "trial_days": 30
        }
    ],
    "features": [
        { "key": "max_units", "value": "1000", "type": "integer" },
        { "key": "max_users", "value": "2000", "type": "integer" },
        { "key": "max_spaces", "value": "50", "type": "integer" },
        { "key": "max_reservations_per_month", "value": "5000", "type": "integer" },
        { "key": "api_access", "value": "true", "type": "boolean" },
        { "key": "custom_branding", "value": "true", "type": "boolean" }
    ]
}
```

| Campo                        | Tipo     | Obrigatorio | Descricao                                                |
|------------------------------|----------|-------------|----------------------------------------------------------|
| `name`                       | string   | Sim         | Nome do plano                                            |
| `slug`                       | string   | Sim         | Slug unico do plano                                      |
| `description`                | string   | Nao         | Descricao do plano                                       |
| `prices`                     | array    | Sim         | Array de precos (min: 1)                                 |
| `prices.*.billing_cycle`     | string   | Sim         | Ciclo: `monthly`, `semiannual`, `yearly`                 |
| `prices.*.price`             | integer  | Sim         | Preco em centavos (ex: 9990 = R$ 99,90)                  |
| `prices.*.currency`          | string   | Nao         | Moeda ISO 4217 (padrao: "BRL")                           |
| `prices.*.trial_days`        | integer  | Nao         | Dias de teste gratuito (padrao: 0)                       |
| `features`                   | array    | Sim         | Lista de features do plano                               |
| `features.*.key`             | string   | Sim         | Chave da feature                                         |
| `features.*.value`           | string   | Sim         | Valor da feature                                         |
| `features.*.type`            | string   | Sim         | Tipo: `boolean`, `integer`, `string`                     |

**Resposta de sucesso (201 Created):**

```json
{
    "data": {
        "id": "01912e4a-c081-7233-4e5f-6a7b8c9d0e1f",
        "name": "Enterprise",
        "slug": "enterprise",
        "description": "Plano para grandes condominios e redes de condominios.",
        "is_active": true,
        "current_version": {
            "id": "01912e4a-c082-7233-4e5f-6a7b8c9d0e1f",
            "version": 1,
            "status": "active",
            "created_at": "2026-02-10T20:00:00Z",
            "prices": [
                {
                    "id": "01912e4a-c091-7233-4e5f-6a7b8c9d0e1f",
                    "billing_cycle": "monthly",
                    "price_in_cents": 99990,
                    "currency": "BRL",
                    "trial_days": 30
                },
                {
                    "id": "01912e4a-c092-7233-4e5f-6a7b8c9d0e1f",
                    "billing_cycle": "semiannual",
                    "price_in_cents": 499950,
                    "currency": "BRL",
                    "trial_days": 30
                },
                {
                    "id": "01912e4a-c093-7233-4e5f-6a7b8c9d0e1f",
                    "billing_cycle": "yearly",
                    "price_in_cents": 899900,
                    "currency": "BRL",
                    "trial_days": 30
                }
            ],
            "features": [
                { "key": "max_units", "value": "1000", "type": "integer" },
                { "key": "max_users", "value": "2000", "type": "integer" },
                { "key": "max_spaces", "value": "50", "type": "integer" },
                { "key": "max_reservations_per_month", "value": "5000", "type": "integer" },
                { "key": "api_access", "value": "true", "type": "boolean" },
                { "key": "custom_branding", "value": "true", "type": "boolean" }
            ]
        },
        "created_at": "2026-02-10T20:00:00Z",
        "updated_at": "2026-02-10T20:00:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                                         |
|--------|--------------------|----------------------------------------------------|
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido                          |
| 403    | `FORBIDDEN`        | Apenas super_admin pode criar planos               |
| 409    | `CONFLICT`         | Slug do plano ja existente                         |
| 422    | `VALIDATION_ERROR` | Campos obrigatorios ausentes ou formato invalido   |

---

### GET /api/v1/platform/plans/{id}

Retorna os detalhes de um plano, incluindo o historico de versoes.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`, `support`

**Parametros de Rota:**

| Parametro | Tipo | Descricao     |
|-----------|------|---------------|
| `id`      | uuid | ID do plano   |

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "id": "01912e4a-8c4d-7e9f-0a1b-2c3d4e5f6a7b",
        "name": "Profissional",
        "slug": "profissional",
        "description": "Plano completo para condominios de medio porte.",
        "is_active": true,
        "current_version": {
            "id": "01912e4a-d456-7e9f-0a1b-2c3d4e5f6a7b",
            "version": 2,
            "status": "active",
            "created_at": "2026-01-15T10:00:00Z",
            "prices": [
                {
                    "id": "01912e4a-d501-7e9f-0a1b-2c3d4e5f6a7b",
                    "billing_cycle": "monthly",
                    "price_in_cents": 49990,
                    "currency": "BRL",
                    "trial_days": 14
                },
                {
                    "id": "01912e4a-d502-7e9f-0a1b-2c3d4e5f6a7b",
                    "billing_cycle": "yearly",
                    "price_in_cents": 449900,
                    "currency": "BRL",
                    "trial_days": 14
                }
            ],
            "features": [
                { "key": "max_units", "value": "200", "type": "integer" },
                { "key": "max_users", "value": "500", "type": "integer" },
                { "key": "max_spaces", "value": "10", "type": "integer" },
                { "key": "max_reservations_per_month", "value": "500", "type": "integer" }
            ]
        },
        "versions": [
            {
                "version": 2,
                "status": "active",
                "prices": [
                    { "billing_cycle": "monthly", "price_in_cents": 49990 },
                    { "billing_cycle": "yearly", "price_in_cents": 449900 }
                ],
                "created_at": "2026-01-15T10:00:00Z"
            },
            {
                "version": 1,
                "status": "deprecated",
                "prices": [
                    { "billing_cycle": "monthly", "price_in_cents": 39990 },
                    { "billing_cycle": "yearly", "price_in_cents": 399900 }
                ],
                "created_at": "2026-01-01T00:00:00Z"
            }
        ],
        "created_at": "2026-01-01T00:00:00Z",
        "updated_at": "2026-01-15T10:00:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro              | Descricao                          |
|--------|-------------------|------------------------------------|
| 401    | `UNAUTHENTICATED` | Token ausente ou invalido          |
| 403    | `FORBIDDEN`       | Role do usuario nao tem permissao  |
| 404    | `NOT_FOUND`       | Plano nao encontrado               |

---

### PUT /api/v1/platform/plans/{id}

Atualiza um plano existente. Cada atualizacao cria uma nova `PlanVersion` para manter o historico de alteracoes. Tenants existentes mantem a versao contratada ate a proxima renovacao.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`

**Parametros de Rota:**

| Parametro | Tipo | Descricao     |
|-----------|------|---------------|
| `id`      | uuid | ID do plano   |

**Request Body:**

```json
{
    "name": "Profissional Plus",
    "prices": [
        {
            "billing_cycle": "monthly",
            "price": 54990,
            "currency": "BRL",
            "trial_days": 14
        },
        {
            "billing_cycle": "yearly",
            "price": 499900,
            "currency": "BRL",
            "trial_days": 14
        }
    ],
    "features": [
        { "key": "max_units", "value": "250", "type": "integer" },
        { "key": "max_users", "value": "600", "type": "integer" },
        { "key": "max_spaces", "value": "15", "type": "integer" },
        { "key": "max_reservations_per_month", "value": "700", "type": "integer" },
        { "key": "api_access", "value": "true", "type": "boolean" }
    ]
}
```

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "id": "01912e4a-8c4d-7e9f-0a1b-2c3d4e5f6a7b",
        "name": "Profissional Plus",
        "slug": "profissional",
        "description": "Plano completo para condominios de medio porte.",
        "is_active": true,
        "current_version": {
            "id": "01912e4a-e789-7e9f-0a1b-2c3d4e5f6a7b",
            "version": 3,
            "status": "active",
            "created_at": "2026-02-10T21:00:00Z",
            "prices": [
                {
                    "id": "01912e4a-e801-7e9f-0a1b-2c3d4e5f6a7b",
                    "billing_cycle": "monthly",
                    "price_in_cents": 54990,
                    "currency": "BRL",
                    "trial_days": 14
                },
                {
                    "id": "01912e4a-e802-7e9f-0a1b-2c3d4e5f6a7b",
                    "billing_cycle": "yearly",
                    "price_in_cents": 499900,
                    "currency": "BRL",
                    "trial_days": 14
                }
            ],
            "features": [
                { "key": "max_units", "value": "250", "type": "integer" },
                { "key": "max_users", "value": "600", "type": "integer" },
                { "key": "max_spaces", "value": "15", "type": "integer" },
                { "key": "max_reservations_per_month", "value": "700", "type": "integer" },
                { "key": "api_access", "value": "true", "type": "boolean" }
            ]
        },
        "created_at": "2026-01-01T00:00:00Z",
        "updated_at": "2026-02-10T21:00:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                                  |
|--------|--------------------|--------------------------------------------|
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido                  |
| 403    | `FORBIDDEN`        | Apenas super_admin pode atualizar planos   |
| 404    | `NOT_FOUND`        | Plano nao encontrado                       |
| 422    | `VALIDATION_ERROR` | Dados invalidos                            |

---

### POST /api/v1/platform/plans/{id}/deactivate

Desativa um plano. Planos desativados nao ficam disponiveis para novos tenants, mas tenants existentes mantem o acesso ate o fim do ciclo.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`

**Parametros de Rota:**

| Parametro | Tipo | Descricao     |
|-----------|------|---------------|
| `id`      | uuid | ID do plano   |

**Request Body:** Nenhum

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "id": "01912e4a-8c4d-7e9f-0a1b-2c3d4e5f6a7b",
        "name": "Profissional",
        "slug": "profissional",
        "is_active": false,
        "deactivated_at": "2026-02-10T22:00:00Z",
        "updated_at": "2026-02-10T22:00:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro              | Descricao                                 |
|--------|-------------------|-------------------------------------------|
| 401    | `UNAUTHENTICATED` | Token ausente ou invalido                 |
| 403    | `FORBIDDEN`       | Apenas super_admin pode desativar planos  |
| 404    | `NOT_FOUND`       | Plano nao encontrado                      |
| 409    | `CONFLICT`        | Plano ja esta desativado                  |

---

## 5. Subscriptions

Endpoints para gerenciamento das assinaturas dos tenants.

---

### GET /api/v1/platform/subscriptions

Retorna a lista paginada de assinaturas da plataforma.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`, `support`

**Query Parameters:**

| Parametro   | Tipo    | Obrigatorio | Descricao                                                         |
|-------------|---------|-------------|-------------------------------------------------------------------|
| `tenant_id` | uuid    | Nao         | Filtrar por tenant especifico                                     |
| `status`    | string  | Nao         | Filtrar por status: `active`, `trialing`, `past_due`, `canceled`  |
| `page`      | integer | Nao         | Numero da pagina (padrao: 1)                                      |
| `per_page`  | integer | Nao         | Itens por pagina (padrao: 15, max: 100)                           |

**Exemplo de Requisicao:**

```
GET /api/v1/platform/subscriptions?status=active&page=1&per_page=20
```

**Resposta de sucesso (200 OK):**

```json
{
    "data": [
        {
            "id": "01912e4a-bf70-7122-3d4e-5f6a7b8c9d0e",
            "tenant": {
                "id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
                "name": "Condominio Residencial Aurora"
            },
            "plan": {
                "id": "01912e4a-8c4d-7e9f-0a1b-2c3d4e5f6a7b",
                "name": "Profissional",
                "version": 2
            },
            "status": "active",
            "trial_ends_at": null,
            "current_period_start": "2026-02-01T00:00:00Z",
            "current_period_end": "2026-02-28T23:59:59Z",
            "cancel_at_period_end": false,
            "created_at": "2026-01-15T10:30:00Z",
            "updated_at": "2026-02-01T00:00:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 20,
        "total": 45,
        "last_page": 3
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro              | Descricao                          |
|--------|-------------------|------------------------------------|
| 401    | `UNAUTHENTICATED` | Token ausente ou invalido          |
| 403    | `FORBIDDEN`       | Role do usuario nao tem permissao  |

---

### GET /api/v1/platform/subscriptions/{id}

Retorna os detalhes completos de uma assinatura, incluindo informacoes do plano.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`, `support`

**Parametros de Rota:**

| Parametro | Tipo | Descricao          |
|-----------|------|--------------------|
| `id`      | uuid | ID da assinatura   |

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "id": "01912e4a-bf70-7122-3d4e-5f6a7b8c9d0e",
        "tenant": {
            "id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
            "name": "Condominio Residencial Aurora",
            "slug": "residencial-aurora"
        },
        "plan": {
            "id": "01912e4a-8c4d-7e9f-0a1b-2c3d4e5f6a7b",
            "name": "Profissional",
            "slug": "profissional",
            "price": 499.90,
            "billing_cycle": "monthly",
            "version": 2
        },
        "status": "active",
        "trial_ends_at": null,
        "current_period_start": "2026-02-01T00:00:00Z",
        "current_period_end": "2026-02-28T23:59:59Z",
        "cancel_at_period_end": false,
        "canceled_at": null,
        "cancel_reason": null,
        "stripe_subscription_id": "sub_1N2m3O4p5Q6r7S8t",
        "created_at": "2026-01-15T10:30:00Z",
        "updated_at": "2026-02-01T00:00:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro              | Descricao                          |
|--------|-------------------|------------------------------------|
| 401    | `UNAUTHENTICATED` | Token ausente ou invalido          |
| 403    | `FORBIDDEN`       | Role do usuario nao tem permissao  |
| 404    | `NOT_FOUND`       | Assinatura nao encontrada          |

---

### POST /api/v1/platform/subscriptions/{id}/change-plan

Altera o plano de uma assinatura ativa. Pode aplicar prorrateamento (prorate) do valor.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`

**Parametros de Rota:**

| Parametro | Tipo | Descricao          |
|-----------|------|--------------------|
| `id`      | uuid | ID da assinatura   |

**Request Body:**

```json
{
    "plan_id": "01912e4a-c081-7233-4e5f-6a7b8c9d0e1f",
    "prorate": true
}
```

| Campo     | Tipo    | Obrigatorio | Descricao                                        |
|-----------|---------|-------------|--------------------------------------------------|
| `plan_id` | uuid    | Sim         | ID do novo plano                                 |
| `prorate` | boolean | Nao         | Aplicar prorrateamento do valor (padrao: true)   |

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "id": "01912e4a-bf70-7122-3d4e-5f6a7b8c9d0e",
        "tenant": {
            "id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
            "name": "Condominio Residencial Aurora"
        },
        "plan": {
            "id": "01912e4a-c081-7233-4e5f-6a7b8c9d0e1f",
            "name": "Enterprise",
            "version": 1
        },
        "previous_plan": {
            "id": "01912e4a-8c4d-7e9f-0a1b-2c3d4e5f6a7b",
            "name": "Profissional"
        },
        "status": "active",
        "prorate_amount": 250.00,
        "current_period_start": "2026-02-01T00:00:00Z",
        "current_period_end": "2026-02-28T23:59:59Z",
        "updated_at": "2026-02-10T23:00:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                                                  |
|--------|--------------------|------------------------------------------------------------|
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido                                  |
| 403    | `FORBIDDEN`        | Role do usuario nao tem permissao                          |
| 404    | `NOT_FOUND`        | Assinatura ou plano nao encontrado                         |
| 409    | `CONFLICT`         | Assinatura nao esta ativa ou ja possui este plano          |
| 422    | `VALIDATION_ERROR` | Dados invalidos                                            |

**Exemplo de erro (409):**

```json
{
    "error": {
        "code": "CONFLICT",
        "message": "A assinatura ja possui o plano selecionado."
    }
}
```

---

### POST /api/v1/platform/subscriptions/{id}/cancel

Cancela uma assinatura. Por padrao, o cancelamento ocorre ao final do periodo corrente.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`

**Parametros de Rota:**

| Parametro | Tipo | Descricao          |
|-----------|------|--------------------|
| `id`      | uuid | ID da assinatura   |

**Request Body:**

```json
{
    "reason": "Solicitacao do cliente - migrando para outro sistema",
    "cancel_at_period_end": true
}
```

| Campo                  | Tipo    | Obrigatorio | Descricao                                                                             |
|------------------------|---------|-------------|---------------------------------------------------------------------------------------|
| `reason`               | string  | Sim         | Motivo do cancelamento                                                                |
| `cancel_at_period_end` | boolean | Nao         | Cancelar ao fim do periodo corrente (padrao: true). Se false, cancela imediatamente   |

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "id": "01912e4a-bf70-7122-3d4e-5f6a7b8c9d0e",
        "status": "active",
        "cancel_at_period_end": true,
        "canceled_at": "2026-02-10T23:30:00Z",
        "cancel_reason": "Solicitacao do cliente - migrando para outro sistema",
        "effective_cancel_date": "2026-02-28T23:59:59Z",
        "updated_at": "2026-02-10T23:30:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                          |
|--------|--------------------|------------------------------------|
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido          |
| 403    | `FORBIDDEN`        | Role do usuario nao tem permissao  |
| 404    | `NOT_FOUND`        | Assinatura nao encontrada          |
| 409    | `CONFLICT`         | Assinatura ja esta cancelada       |
| 422    | `VALIDATION_ERROR` | Campo reason ausente               |

---

## 6. Invoices

Endpoints para gerenciamento de faturas da plataforma.

---

### GET /api/v1/platform/invoices

Retorna a lista paginada de faturas da plataforma.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`, `support`

**Query Parameters:**

| Parametro   | Tipo    | Obrigatorio | Descricao                                                                     |
|-------------|---------|-------------|-------------------------------------------------------------------------------|
| `tenant_id` | uuid    | Nao         | Filtrar por tenant especifico                                                 |
| `status`    | string  | Nao         | Filtrar por status: `draft`, `open`, `paid`, `overdue`, `refunded`, `void`    |
| `date_from` | date    | Nao         | Data inicial do filtro (formato: YYYY-MM-DD)                                  |
| `date_to`   | date    | Nao         | Data final do filtro (formato: YYYY-MM-DD)                                    |
| `page`      | integer | Nao         | Numero da pagina (padrao: 1)                                                  |
| `per_page`  | integer | Nao         | Itens por pagina (padrao: 15, max: 100)                                       |

**Exemplo de Requisicao:**

```
GET /api/v1/platform/invoices?status=overdue&date_from=2026-01-01&date_to=2026-02-10&page=1
```

**Resposta de sucesso (200 OK):**

```json
{
    "data": [
        {
            "id": "01912e4a-d192-7344-5f6a-7b8c9d0e1f20",
            "tenant": {
                "id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
                "name": "Condominio Residencial Aurora"
            },
            "number": "INV-2026-000042",
            "status": "overdue",
            "amount": 499.90,
            "currency": "BRL",
            "due_date": "2026-02-05",
            "paid_at": null,
            "created_at": "2026-01-25T00:00:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 3,
        "last_page": 1
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                          |
|--------|--------------------|------------------------------------|
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido          |
| 403    | `FORBIDDEN`        | Role do usuario nao tem permissao  |
| 422    | `VALIDATION_ERROR` | Parametros de data invalidos       |

---

### GET /api/v1/platform/invoices/{id}

Retorna os detalhes de uma fatura, incluindo os itens detalhados.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`, `support`

**Parametros de Rota:**

| Parametro | Tipo | Descricao       |
|-----------|------|-----------------|
| `id`      | uuid | ID da fatura    |

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "id": "01912e4a-d192-7344-5f6a-7b8c9d0e1f20",
        "tenant": {
            "id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
            "name": "Condominio Residencial Aurora",
            "slug": "residencial-aurora",
            "document": "12.345.678/0001-90"
        },
        "subscription": {
            "id": "01912e4a-bf70-7122-3d4e-5f6a7b8c9d0e",
            "plan_name": "Profissional"
        },
        "number": "INV-2026-000042",
        "status": "overdue",
        "subtotal": 499.90,
        "discount": 0.00,
        "tax": 0.00,
        "amount": 499.90,
        "currency": "BRL",
        "due_date": "2026-02-05",
        "paid_at": null,
        "items": [
            {
                "description": "Plano Profissional - Fevereiro 2026",
                "quantity": 1,
                "unit_price": 499.90,
                "amount": 499.90
            }
        ],
        "stripe_invoice_id": "in_1N2m3O4p5Q6r7S8t",
        "created_at": "2026-01-25T00:00:00Z",
        "updated_at": "2026-02-06T00:00:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro              | Descricao                          |
|--------|-------------------|------------------------------------|
| 401    | `UNAUTHENTICATED` | Token ausente ou invalido          |
| 403    | `FORBIDDEN`       | Role do usuario nao tem permissao  |
| 404    | `NOT_FOUND`       | Fatura nao encontrada              |

---

### POST /api/v1/platform/invoices/{id}/refund

Realiza o reembolso total ou parcial de uma fatura paga. Somente faturas com status `paid` podem ser reembolsadas.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`

**Parametros de Rota:**

| Parametro | Tipo | Descricao       |
|-----------|------|-----------------|
| `id`      | uuid | ID da fatura    |

**Request Body:**

```json
{
    "amount": 250.00,
    "reason": "Reembolso parcial - servico indisponivel por 15 dias"
}
```

| Campo    | Tipo    | Obrigatorio | Descricao                                                       |
|----------|---------|-------------|-----------------------------------------------------------------|
| `amount` | decimal | Nao         | Valor do reembolso em BRL. Se omitido, realiza reembolso total  |
| `reason` | string  | Sim         | Motivo do reembolso                                             |

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "id": "01912e4a-d192-7344-5f6a-7b8c9d0e1f20",
        "number": "INV-2026-000042",
        "status": "refunded",
        "amount": 499.90,
        "refunded_amount": 250.00,
        "refund_reason": "Reembolso parcial - servico indisponivel por 15 dias",
        "refunded_at": "2026-02-10T14:00:00Z",
        "updated_at": "2026-02-10T14:00:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                                                                 |
|--------|---------------------|---------------------------------------------------------------------------|
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido                                                 |
| 403    | `FORBIDDEN`        | Apenas super_admin pode realizar reembolsos                               |
| 404    | `NOT_FOUND`        | Fatura nao encontrada                                                     |
| 409    | `CONFLICT`         | Fatura nao esta com status paid ou ja foi totalmente reembolsada          |
| 422    | `VALIDATION_ERROR` | Valor de reembolso excede o valor da fatura ou campo reason ausente       |

**Exemplo de erro (409):**

```json
{
    "error": {
        "code": "CONFLICT",
        "message": "Somente faturas com status 'paid' podem ser reembolsadas."
    }
}
```

**Exemplo de erro (422):**

```json
{
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Os dados fornecidos sao invalidos.",
        "details": {
            "amount": ["O valor de reembolso nao pode exceder o valor da fatura (R$ 499,90)."]
        }
    }
}
```

---

## 7. Payments

Endpoints para consulta de pagamentos registrados na plataforma.

---

### GET /api/v1/platform/payments

Retorna a lista paginada de pagamentos da plataforma.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`, `support`

**Query Parameters:**

| Parametro   | Tipo    | Obrigatorio | Descricao                                                         |
|-------------|---------|-------------|-------------------------------------------------------------------|
| `tenant_id` | uuid    | Nao         | Filtrar por tenant especifico                                     |
| `status`    | string  | Nao         | Filtrar por status: `succeeded`, `pending`, `failed`, `refunded`  |
| `method`    | string  | Nao         | Filtrar por metodo: `credit_card`, `boleto`, `pix`                |
| `date_from` | date    | Nao         | Data inicial do filtro (formato: YYYY-MM-DD)                      |
| `date_to`   | date    | Nao         | Data final do filtro (formato: YYYY-MM-DD)                        |
| `page`      | integer | Nao         | Numero da pagina (padrao: 1)                                      |
| `per_page`  | integer | Nao         | Itens por pagina (padrao: 15, max: 100)                           |

**Exemplo de Requisicao:**

```
GET /api/v1/platform/payments?status=succeeded&method=pix&date_from=2026-01-01&page=1
```

**Resposta de sucesso (200 OK):**

```json
{
    "data": [
        {
            "id": "01912e4a-e2a3-7455-6a7b-8c9d0e1f2031",
            "tenant": {
                "id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
                "name": "Condominio Residencial Aurora"
            },
            "invoice": {
                "id": "01912e4a-d192-7344-5f6a-7b8c9d0e1f20",
                "number": "INV-2026-000041"
            },
            "amount": 499.90,
            "currency": "BRL",
            "method": "pix",
            "status": "succeeded",
            "paid_at": "2026-01-30T14:22:00Z",
            "created_at": "2026-01-30T14:20:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 120,
        "last_page": 8
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                          |
|--------|--------------------|------------------------------------|
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido          |
| 403    | `FORBIDDEN`        | Role do usuario nao tem permissao  |
| 422    | `VALIDATION_ERROR` | Parametros de filtro invalidos     |

---

### GET /api/v1/platform/payments/{id}

Retorna os detalhes completos de um pagamento.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`, `support`

**Parametros de Rota:**

| Parametro | Tipo | Descricao         |
|-----------|------|--------------------|
| `id`      | uuid | ID do pagamento    |

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "id": "01912e4a-e2a3-7455-6a7b-8c9d0e1f2031",
        "tenant": {
            "id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
            "name": "Condominio Residencial Aurora",
            "slug": "residencial-aurora"
        },
        "invoice": {
            "id": "01912e4a-d192-7344-5f6a-7b8c9d0e1f20",
            "number": "INV-2026-000041"
        },
        "amount": 499.90,
        "currency": "BRL",
        "method": "pix",
        "status": "succeeded",
        "gateway": "stripe",
        "gateway_payment_id": "pi_1N2m3O4p5Q6r7S8t",
        "gateway_response": {
            "charge_id": "ch_1N2m3O4p5Q6r7S8t",
            "receipt_url": "https://pay.stripe.com/receipts/..."
        },
        "paid_at": "2026-01-30T14:22:00Z",
        "failed_at": null,
        "failure_reason": null,
        "refunded_at": null,
        "refunded_amount": null,
        "created_at": "2026-01-30T14:20:00Z",
        "updated_at": "2026-01-30T14:22:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro              | Descricao                          |
|--------|-------------------|------------------------------------|
| 401    | `UNAUTHENTICATED` | Token ausente ou invalido          |
| 403    | `FORBIDDEN`       | Role do usuario nao tem permissao  |
| 404    | `NOT_FOUND`       | Pagamento nao encontrado           |

---

## 8. Feature Flags

Endpoints para gerenciamento de feature flags que controlam funcionalidades por tenant.

---

### GET /api/v1/platform/feature-flags

Retorna a lista de todas as feature flags configuradas na plataforma.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`, `support`

**Resposta de sucesso (200 OK):**

```json
{
    "data": [
        {
            "id": "01912e4a-f3b4-7566-7b8c-9d0e1f203142",
            "key": "financial_module",
            "name": "Modulo Financeiro",
            "description": "Habilita o modulo de gestao financeira para o condominio.",
            "default_value": false,
            "overrides_count": 5,
            "created_at": "2026-01-01T00:00:00Z",
            "updated_at": "2026-01-15T10:00:00Z"
        },
        {
            "id": "01912e4b-04c5-7677-8c9d-0e1f20314253",
            "key": "new_reservation_ui",
            "name": "Nova Interface de Reservas",
            "description": "Habilita a nova interface de reservas com calendario interativo.",
            "default_value": false,
            "overrides_count": 12,
            "created_at": "2026-01-20T00:00:00Z",
            "updated_at": "2026-02-01T08:00:00Z"
        },
        {
            "id": "01912e4b-15d6-7788-9d0e-1f2031425364",
            "key": "ai_violation_detection",
            "name": "Deteccao de Infracoes por IA",
            "description": "Habilita deteccao automatica de infracoes usando inteligencia artificial.",
            "default_value": false,
            "overrides_count": 2,
            "created_at": "2026-02-01T00:00:00Z",
            "updated_at": "2026-02-01T00:00:00Z"
        }
    ]
}
```

**Cenarios de Erro:**

| Codigo | Erro              | Descricao                          |
|--------|-------------------|------------------------------------|
| 401    | `UNAUTHENTICATED` | Token ausente ou invalido          |
| 403    | `FORBIDDEN`       | Role do usuario nao tem permissao  |

---

### POST /api/v1/platform/feature-flags

Cria uma nova feature flag na plataforma.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`

**Request Body:**

```json
{
    "key": "bulk_messaging",
    "name": "Envio de Mensagens em Massa",
    "description": "Habilita o envio de comunicados para multiplos moradores simultaneamente.",
    "default_value": false
}
```

| Campo           | Tipo    | Obrigatorio | Descricao                                         |
|-----------------|---------|-------------|---------------------------------------------------|
| `key`           | string  | Sim         | Chave unica da feature flag (snake_case)          |
| `name`          | string  | Sim         | Nome legivel da feature flag                      |
| `description`   | string  | Nao         | Descricao da funcionalidade                       |
| `default_value` | boolean | Sim         | Valor padrao (habilitado/desabilitado para todos) |

**Resposta de sucesso (201 Created):**

```json
{
    "data": {
        "id": "01912e4b-26e7-7899-0e1f-203142536475",
        "key": "bulk_messaging",
        "name": "Envio de Mensagens em Massa",
        "description": "Habilita o envio de comunicados para multiplos moradores simultaneamente.",
        "default_value": false,
        "overrides_count": 0,
        "created_at": "2026-02-10T10:00:00Z",
        "updated_at": "2026-02-10T10:00:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                                         |
|--------|--------------------|----------------------------------------------------|
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido                          |
| 403    | `FORBIDDEN`        | Apenas super_admin pode criar feature flags        |
| 409    | `CONFLICT`         | Ja existe uma feature flag com esta key            |
| 422    | `VALIDATION_ERROR` | Campos obrigatorios ausentes ou formato invalido   |

**Exemplo de erro (409):**

```json
{
    "error": {
        "code": "CONFLICT",
        "message": "Ja existe uma feature flag com a key 'bulk_messaging'."
    }
}
```

---

### PUT /api/v1/platform/feature-flags/{id}

Atualiza uma feature flag existente.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`

**Parametros de Rota:**

| Parametro | Tipo | Descricao             |
|-----------|------|-----------------------|
| `id`      | uuid | ID da feature flag    |

**Request Body:**

```json
{
    "name": "Envio de Mensagens em Massa v2",
    "description": "Habilita o envio de comunicados para multiplos moradores com suporte a templates.",
    "default_value": true
}
```

| Campo           | Tipo    | Obrigatorio | Descricao                                         |
|-----------------|---------|-------------|---------------------------------------------------|
| `name`          | string  | Nao         | Nome legivel da feature flag                      |
| `description`   | string  | Nao         | Descricao da funcionalidade                       |
| `default_value` | boolean | Nao         | Valor padrao (habilitado/desabilitado para todos) |

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "id": "01912e4b-26e7-7899-0e1f-203142536475",
        "key": "bulk_messaging",
        "name": "Envio de Mensagens em Massa v2",
        "description": "Habilita o envio de comunicados para multiplos moradores com suporte a templates.",
        "default_value": true,
        "overrides_count": 0,
        "created_at": "2026-02-10T10:00:00Z",
        "updated_at": "2026-02-10T11:30:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                                         |
|--------|--------------------|----------------------------------------------------|
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido                          |
| 403    | `FORBIDDEN`        | Apenas super_admin pode atualizar feature flags    |
| 404    | `NOT_FOUND`        | Feature flag nao encontrada                        |
| 422    | `VALIDATION_ERROR` | Dados invalidos                                    |

---

### POST /api/v1/platform/feature-flags/{id}/overrides

Cria um override de feature flag para um tenant especifico, permitindo habilitar ou desabilitar a funcionalidade independentemente do valor padrao.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`

**Parametros de Rota:**

| Parametro | Tipo | Descricao             |
|-----------|------|-----------------------|
| `id`      | uuid | ID da feature flag    |

**Request Body:**

```json
{
    "tenant_id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
    "value": true,
    "reason": "Tenant participando do beta da funcionalidade"
}
```

| Campo       | Tipo    | Obrigatorio | Descricao                                    |
|-------------|---------|-------------|----------------------------------------------|
| `tenant_id` | uuid    | Sim         | ID do tenant para o override                 |
| `value`     | boolean | Sim         | Valor do override para este tenant           |
| `reason`    | string  | Sim         | Motivo do override                           |

**Resposta de sucesso (201 Created):**

```json
{
    "data": {
        "id": "01912e4b-37f8-79aa-1f20-314253647586",
        "feature_flag": {
            "id": "01912e4b-26e7-7899-0e1f-203142536475",
            "key": "bulk_messaging"
        },
        "tenant": {
            "id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
            "name": "Condominio Residencial Aurora"
        },
        "value": true,
        "reason": "Tenant participando do beta da funcionalidade",
        "created_by": {
            "id": "01912e4a-4809-7bcd-ef01-234567890abc",
            "name": "Carlos Admin"
        },
        "created_at": "2026-02-10T12:00:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                                                  |
|--------|--------------------|------------------------------------------------------------|
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido                                  |
| 403    | `FORBIDDEN`        | Role do usuario nao tem permissao                          |
| 404    | `NOT_FOUND`        | Feature flag ou tenant nao encontrado                      |
| 409    | `CONFLICT`         | Ja existe um override para este tenant nesta feature flag  |
| 422    | `VALIDATION_ERROR` | Campos obrigatorios ausentes ou formato invalido           |

**Exemplo de erro (409):**

```json
{
    "error": {
        "code": "CONFLICT",
        "message": "Ja existe um override desta feature flag para o tenant informado."
    }
}
```

---

### DELETE /api/v1/platform/feature-flags/{id}/overrides/{override_id}

Remove um override de feature flag. O tenant voltara a utilizar o valor padrao da feature flag.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`

**Parametros de Rota:**

| Parametro     | Tipo | Descricao             |
|---------------|------|-----------------------|
| `id`          | uuid | ID da feature flag    |
| `override_id` | uuid | ID do override       |

**Request Body:** Nenhum

**Resposta de sucesso (204 No Content):**

Sem corpo de resposta.

**Cenarios de Erro:**

| Codigo | Erro              | Descricao                                |
|--------|-------------------|------------------------------------------|
| 401    | `UNAUTHENTICATED` | Token ausente ou invalido                |
| 403    | `FORBIDDEN`       | Role do usuario nao tem permissao        |
| 404    | `NOT_FOUND`       | Feature flag ou override nao encontrado  |

---

## 9. Platform Users

Endpoints para gerenciamento de usuarios internos da plataforma (administradores).

---

### GET /api/v1/platform/users

Retorna a lista de usuarios da plataforma (administradores internos).

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`

**Resposta de sucesso (200 OK):**

```json
{
    "data": [
        {
            "id": "01912e4a-4809-7bcd-ef01-234567890abc",
            "name": "Carlos Super Admin",
            "email": "carlos@plataforma.com.br",
            "role": "super_admin",
            "is_active": true,
            "last_login_at": "2026-02-10T08:00:00Z",
            "created_at": "2026-01-01T00:00:00Z",
            "updated_at": "2026-02-10T08:00:00Z"
        },
        {
            "id": "01912e4a-591a-7cde-f012-3456789abcde",
            "name": "Ana Administradora",
            "email": "ana@plataforma.com.br",
            "role": "admin",
            "is_active": true,
            "last_login_at": "2026-02-09T17:30:00Z",
            "created_at": "2026-01-05T10:00:00Z",
            "updated_at": "2026-02-09T17:30:00Z"
        },
        {
            "id": "01912e4a-6a2b-7def-0123-456789abcdef",
            "name": "Pedro Suporte",
            "email": "pedro@plataforma.com.br",
            "role": "support",
            "is_active": true,
            "last_login_at": "2026-02-10T09:15:00Z",
            "created_at": "2026-01-10T14:00:00Z",
            "updated_at": "2026-02-10T09:15:00Z"
        }
    ]
}
```

**Cenarios de Erro:**

| Codigo | Erro              | Descricao                                                  |
|--------|-------------------|------------------------------------------------------------|
| 401    | `UNAUTHENTICATED` | Token ausente ou invalido                                  |
| 403    | `FORBIDDEN`       | Apenas super_admin pode listar usuarios da plataforma      |

---

### POST /api/v1/platform/users

Cria um novo usuario da plataforma. Um email de convite com link para definir senha sera enviado automaticamente.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`

**Request Body:**

```json
{
    "name": "Julia Suporte",
    "email": "julia@plataforma.com.br",
    "role": "support"
}
```

| Campo  | Tipo   | Obrigatorio | Descricao                                         |
|--------|--------|-------------|---------------------------------------------------|
| `name` | string | Sim         | Nome completo do usuario                          |
| `email`| string | Sim         | Email do usuario (deve ser unico)                 |
| `role` | string | Sim         | Role: `super_admin`, `admin` ou `support`         |

**Resposta de sucesso (201 Created):**

```json
{
    "data": {
        "id": "01912e4b-4909-7abb-2031-42536475869a",
        "name": "Julia Suporte",
        "email": "julia@plataforma.com.br",
        "role": "support",
        "is_active": true,
        "invitation_sent_at": "2026-02-10T13:00:00Z",
        "created_at": "2026-02-10T13:00:00Z",
        "updated_at": "2026-02-10T13:00:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                                              |
|--------|--------------------|---------------------------------------------------------|
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido                               |
| 403    | `FORBIDDEN`        | Apenas super_admin pode criar usuarios da plataforma    |
| 409    | `CONFLICT`         | Ja existe um usuario com este email                     |
| 422    | `VALIDATION_ERROR` | Campos obrigatorios ausentes ou formato invalido        |

**Exemplo de erro (409):**

```json
{
    "error": {
        "code": "CONFLICT",
        "message": "Ja existe um usuario com o email 'julia@plataforma.com.br'."
    }
}
```

**Exemplo de erro (422):**

```json
{
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Os dados fornecidos sao invalidos.",
        "details": {
            "email": ["O campo email deve ser um endereco de email valido."],
            "role": ["O campo role deve ser: super_admin, admin ou support."]
        }
    }
}
```

---

### PUT /api/v1/platform/users/{id}

Atualiza os dados de um usuario da plataforma.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`

**Parametros de Rota:**

| Parametro | Tipo | Descricao        |
|-----------|------|-------------------|
| `id`      | uuid | ID do usuario     |

**Request Body:**

```json
{
    "name": "Julia Souza Suporte",
    "role": "admin"
}
```

| Campo  | Tipo   | Obrigatorio | Descricao                                  |
|--------|--------|-------------|---------------------------------------------|
| `name` | string | Nao         | Nome completo do usuario                    |
| `role` | string | Nao         | Role: `super_admin`, `admin` ou `support`   |

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "id": "01912e4b-4909-7abb-2031-42536475869a",
        "name": "Julia Souza Suporte",
        "email": "julia@plataforma.com.br",
        "role": "admin",
        "is_active": true,
        "created_at": "2026-02-10T13:00:00Z",
        "updated_at": "2026-02-10T14:30:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                                                  |
|--------|--------------------|------------------------------------------------------------|
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido                                  |
| 403    | `FORBIDDEN`        | Apenas super_admin pode atualizar usuarios da plataforma   |
| 404    | `NOT_FOUND`        | Usuario nao encontrado                                     |
| 422    | `VALIDATION_ERROR` | Dados invalidos                                            |

---

### POST /api/v1/platform/users/{id}/deactivate

Desativa um usuario da plataforma. O usuario perde acesso imediatamente e todas as sessoes ativas sao invalidadas.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`

**Parametros de Rota:**

| Parametro | Tipo | Descricao        |
|-----------|------|-------------------|
| `id`      | uuid | ID do usuario     |

**Request Body:** Nenhum

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "id": "01912e4b-4909-7abb-2031-42536475869a",
        "name": "Julia Souza Suporte",
        "email": "julia@plataforma.com.br",
        "role": "admin",
        "is_active": false,
        "deactivated_at": "2026-02-10T15:00:00Z",
        "updated_at": "2026-02-10T15:00:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro              | Descricao                                                      |
|--------|-------------------|----------------------------------------------------------------|
| 401    | `UNAUTHENTICATED` | Token ausente ou invalido                                      |
| 403    | `FORBIDDEN`       | Apenas super_admin pode desativar usuarios da plataforma       |
| 404    | `NOT_FOUND`       | Usuario nao encontrado                                         |
| 409    | `CONFLICT`        | Usuario ja esta desativado ou e o ultimo super_admin ativo     |

**Exemplo de erro (409):**

```json
{
    "error": {
        "code": "CONFLICT",
        "message": "Nao e possivel desativar o ultimo super_admin ativo da plataforma."
    }
}
```

---

## 10. Dunning Policies

Politicas de cobranca automatica (dunning) definem acoes que devem ser executadas quando faturas permanecem em atraso por um determinado numero de dias.

---

### GET /api/v1/platform/dunning-policies

Retorna a lista de politicas de dunning configuradas.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`, `support`

**Resposta de sucesso (200 OK):**

```json
{
    "data": [
        {
            "id": "01912e4b-5a1a-7bcc-3142-536475869a0b",
            "days_overdue": 3,
            "action": "send_reminder",
            "is_active": true,
            "created_at": "2026-01-01T00:00:00Z",
            "updated_at": "2026-01-01T00:00:00Z"
        },
        {
            "id": "01912e4b-6b2b-7cdd-4253-6475869a0b1c",
            "days_overdue": 7,
            "action": "send_warning",
            "is_active": true,
            "created_at": "2026-01-01T00:00:00Z",
            "updated_at": "2026-01-01T00:00:00Z"
        },
        {
            "id": "01912e4b-7c3c-7dee-5364-75869a0b1c2d",
            "days_overdue": 15,
            "action": "restrict_features",
            "is_active": true,
            "created_at": "2026-01-01T00:00:00Z",
            "updated_at": "2026-01-01T00:00:00Z"
        },
        {
            "id": "01912e4b-8d4d-7eff-6475-869a0b1c2d3e",
            "days_overdue": 30,
            "action": "suspend_tenant",
            "is_active": true,
            "created_at": "2026-01-01T00:00:00Z",
            "updated_at": "2026-01-01T00:00:00Z"
        }
    ]
}
```

**Cenarios de Erro:**

| Codigo | Erro              | Descricao                          |
|--------|-------------------|------------------------------------|
| 401    | `UNAUTHENTICATED` | Token ausente ou invalido          |
| 403    | `FORBIDDEN`       | Role do usuario nao tem permissao  |

---

### POST /api/v1/platform/dunning-policies

Cria uma nova politica de dunning.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`

**Request Body:**

```json
{
    "days_overdue": 45,
    "action": "cancel_subscription",
    "is_active": true
}
```

| Campo          | Tipo    | Obrigatorio | Descricao                                                                                                        |
|----------------|---------|-------------|------------------------------------------------------------------------------------------------------------------|
| `days_overdue` | integer | Sim         | Numero de dias em atraso para acionar a politica                                                                 |
| `action`       | string  | Sim         | Acao: `send_reminder`, `send_warning`, `restrict_features`, `suspend_tenant`, `cancel_subscription`              |
| `is_active`    | boolean | Sim         | Se a politica esta ativa                                                                                         |

**Resposta de sucesso (201 Created):**

```json
{
    "data": {
        "id": "01912e4b-9e5e-7f00-7586-9a0b1c2d3e4f",
        "days_overdue": 45,
        "action": "cancel_subscription",
        "is_active": true,
        "created_at": "2026-02-10T16:00:00Z",
        "updated_at": "2026-02-10T16:00:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                                            |
|--------|--------------------|----------------------------------------------------- |
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido                            |
| 403    | `FORBIDDEN`        | Apenas super_admin pode criar politicas de dunning   |
| 409    | `CONFLICT`         | Ja existe uma politica para este numero de dias      |
| 422    | `VALIDATION_ERROR` | Campos obrigatorios ausentes ou formato invalido     |

---

### PUT /api/v1/platform/dunning-policies/{id}

Atualiza uma politica de dunning existente.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`

**Parametros de Rota:**

| Parametro | Tipo | Descricao                  |
|-----------|------|----------------------------|
| `id`      | uuid | ID da politica de dunning  |

**Request Body:**

```json
{
    "days_overdue": 45,
    "action": "suspend_tenant",
    "is_active": false
}
```

| Campo          | Tipo    | Obrigatorio | Descricao                                                                                                        |
|----------------|---------|-------------|------------------------------------------------------------------------------------------------------------------|
| `days_overdue` | integer | Nao         | Numero de dias em atraso                                                                                         |
| `action`       | string  | Nao         | Acao: `send_reminder`, `send_warning`, `restrict_features`, `suspend_tenant`, `cancel_subscription`              |
| `is_active`    | boolean | Nao         | Se a politica esta ativa                                                                                         |

**Resposta de sucesso (200 OK):**

```json
{
    "data": {
        "id": "01912e4b-9e5e-7f00-7586-9a0b1c2d3e4f",
        "days_overdue": 45,
        "action": "suspend_tenant",
        "is_active": false,
        "created_at": "2026-02-10T16:00:00Z",
        "updated_at": "2026-02-10T17:00:00Z"
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                                                |
|--------|--------------------|---------------------------------------------------------  |
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido                                |
| 403    | `FORBIDDEN`        | Apenas super_admin pode atualizar politicas de dunning   |
| 404    | `NOT_FOUND`        | Politica de dunning nao encontrada                       |
| 422    | `VALIDATION_ERROR` | Dados invalidos                                          |

---

## 11. Webhooks (Incoming)

Endpoint para recebimento de notificacoes de eventos de gateways de pagamento.

---

### POST /api/v1/webhooks/stripe

Recebe notificacoes de eventos do Stripe via webhook. A autenticacao e feita por verificacao de assinatura do Stripe (header `Stripe-Signature`), nao por JWT.

- **Autenticacao:** Verificacao de assinatura Stripe (nao requer Bearer token)
- **Roles permitidas:** N/A

**Headers obrigatorios:**

```
Stripe-Signature: t=1614556800,v1=abc123...,v0=def456...
Content-Type: application/json
```

**Eventos tratados:**

| Evento Stripe                           | Acao na plataforma                                      |
|-----------------------------------------|---------------------------------------------------------|
| `invoice.paid`                          | Marca fatura como paga, registra pagamento              |
| `invoice.payment_failed`                | Marca fatura como inadimplente, inicia dunning          |
| `customer.subscription.updated`         | Atualiza status da assinatura                           |
| `customer.subscription.deleted`         | Marca assinatura como cancelada                         |
| `charge.refunded`                       | Registra reembolso na fatura correspondente             |
| `payment_intent.succeeded`              | Registra pagamento bem-sucedido                         |
| `payment_intent.payment_failed`         | Registra falha de pagamento                             |

**Exemplo de payload recebido (invoice.paid):**

```json
{
    "id": "evt_1N2m3O4p5Q6r7S8t",
    "object": "event",
    "type": "invoice.paid",
    "data": {
        "object": {
            "id": "in_1N2m3O4p5Q6r7S8t",
            "customer": "cus_1N2m3O4p5Q6r7S8t",
            "subscription": "sub_1N2m3O4p5Q6r7S8t",
            "amount_paid": 49990,
            "currency": "brl",
            "status": "paid"
        }
    }
}
```

**Resposta de sucesso (200 OK):**

```json
{
    "received": true
}
```

**Cenarios de Erro:**

| Codigo | Erro                  | Descricao                                   |
|--------|-----------------------|---------------------------------------------|
| 400    | `INVALID_PAYLOAD`     | Payload do webhook e invalido ou malformado |
| 403    | `INVALID_SIGNATURE`   | Assinatura do Stripe invalida               |

**Exemplo de erro (403):**

```json
{
    "error": {
        "code": "INVALID_SIGNATURE",
        "message": "A assinatura do webhook nao pode ser verificada."
    }
}
```

---

## 12. Audit Logs

Endpoint para consulta de logs de auditoria da plataforma.

---

### GET /api/v1/platform/audit-logs

Retorna a lista paginada de logs de auditoria da plataforma. Os logs registram todas as acoes administrativas realizadas no sistema.

- **Autenticacao:** Requerida
- **Roles permitidas:** `super_admin`, `admin`

**Query Parameters:**

| Parametro        | Tipo    | Obrigatorio | Descricao                                                                                               |
|------------------|---------|-------------|---------------------------------------------------------------------------------------------------------|
| `user_id`        | uuid    | Nao         | Filtrar por usuario que realizou a acao                                                                 |
| `action`         | string  | Nao         | Filtrar por tipo de acao: `created`, `updated`, `deleted`, `suspended`, `reactivated`, `canceled`       |
| `entity_type`    | string  | Nao         | Filtrar por tipo de entidade: `tenant`, `plan`, `subscription`, `invoice`, `feature_flag`, `user`, `dunning_policy` |
| `date_from`      | date    | Nao         | Data inicial do filtro (formato: YYYY-MM-DD)                                                            |
| `date_to`        | date    | Nao         | Data final do filtro (formato: YYYY-MM-DD)                                                              |
| `correlation_id` | string  | Nao         | Filtrar por correlation ID para rastrear acoes relacionadas                                             |
| `page`           | integer | Nao         | Numero da pagina (padrao: 1)                                                                            |
| `per_page`       | integer | Nao         | Itens por pagina (padrao: 15, max: 100)                                                                 |

**Exemplo de Requisicao:**

```
GET /api/v1/platform/audit-logs?entity_type=tenant&action=suspended&date_from=2026-02-01&page=1
```

**Resposta de sucesso (200 OK):**

```json
{
    "data": [
        {
            "id": "01912e4b-af6f-7011-8697-a0b1c2d3e4f5",
            "user": {
                "id": "01912e4a-4809-7bcd-ef01-234567890abc",
                "name": "Carlos Super Admin",
                "email": "carlos@plataforma.com.br"
            },
            "action": "suspended",
            "entity_type": "tenant",
            "entity_id": "01912e4a-7b3c-7d8e-9f0a-1b2c3d4e5f6a",
            "entity_name": "Condominio Residencial Aurora",
            "changes": {
                "status": {
                    "old": "active",
                    "new": "suspended"
                },
                "suspended_reason": {
                    "old": null,
                    "new": "Inadimplencia - 3 faturas vencidas"
                }
            },
            "metadata": {
                "ip_address": "203.0.113.42",
                "user_agent": "Mozilla/5.0..."
            },
            "correlation_id": "corr_01912e4b-af6f-7011-8697-a0b1c2d3e4f5",
            "created_at": "2026-02-10T16:00:00Z"
        },
        {
            "id": "01912e4b-c070-7122-9708-b1c2d3e4f506",
            "user": {
                "id": "01912e4a-591a-7cde-f012-3456789abcde",
                "name": "Ana Administradora",
                "email": "ana@plataforma.com.br"
            },
            "action": "created",
            "entity_type": "tenant",
            "entity_id": "01912e4a-9d5e-7f0a-1b2c-3d4e5f6a7b8c",
            "entity_name": "Condominio Residencial Bela Vista",
            "changes": null,
            "metadata": {
                "ip_address": "203.0.113.55",
                "user_agent": "Mozilla/5.0..."
            },
            "correlation_id": "corr_01912e4b-c070-7122-9708-b1c2d3e4f506",
            "created_at": "2026-02-09T10:00:00Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 234,
        "last_page": 16
    }
}
```

**Cenarios de Erro:**

| Codigo | Erro               | Descricao                                                    |
|--------|--------------------|------------------------------------------------------------- |
| 401    | `UNAUTHENTICATED`  | Token ausente ou invalido                                    |
| 403    | `FORBIDDEN`        | Apenas super_admin e admin podem acessar logs de auditoria   |
| 422    | `VALIDATION_ERROR` | Parametros de filtro invalidos                               |

---

## 13. Health Check

Endpoint para verificacao do estado de saude da aplicacao.

---

### GET /api/v1/health

Verifica o estado de saude da aplicacao e seus servicos dependentes. Este endpoint nao requer autenticacao e e destinado ao uso por load balancers e sistemas de monitoramento.

- **Autenticacao:** Nao requerida
- **Roles permitidas:** N/A

**Resposta de sucesso (200 OK):**

```json
{
    "status": "healthy",
    "checks": {
        "database": "ok",
        "redis": "ok",
        "queue": "ok"
    },
    "timestamp": "2026-02-10T19:30:00Z"
}
```

**Resposta degradada (200 OK):**

Quando algum servico esta com problemas mas a aplicacao ainda funciona parcialmente:

```json
{
    "status": "degraded",
    "checks": {
        "database": "ok",
        "redis": "ok",
        "queue": "failing"
    },
    "timestamp": "2026-02-10T19:30:00Z"
}
```

**Resposta de falha (503 Service Unavailable):**

Quando servicos criticos estao indisponiveis:

```json
{
    "status": "unhealthy",
    "checks": {
        "database": "failing",
        "redis": "failing",
        "queue": "failing"
    },
    "timestamp": "2026-02-10T19:30:00Z"
}
```

**Cenarios de Erro:**

| Codigo | Erro        | Descricao                                  |
|--------|-------------|---------------------------------------------|
| 503    | `UNHEALTHY` | Um ou mais servicos criticos indisponiveis  |

---

## 14. Resumo dos Endpoints

| Metodo   | Endpoint                                                            | Auth        | Roles                          |
|----------|---------------------------------------------------------------------|-------------|--------------------------------|
| `POST`   | `/api/v1/auth/platform/login`                                       | Nao         | Publico                        |
| `POST`   | `/api/v1/auth/platform/mfa/verify`                                  | mfa_token   | Publico                        |
| `POST`   | `/api/v1/auth/token/refresh`                                        | refresh_token | Publico                      |
| `POST`   | `/api/v1/auth/logout`                                               | Bearer      | Todas                          |
| `GET`    | `/api/v1/platform/tenants`                                          | Bearer      | super_admin, admin, support    |
| `POST`   | `/api/v1/platform/tenants`                                          | Bearer      | super_admin, admin             |
| `GET`    | `/api/v1/platform/tenants/{id}`                                     | Bearer      | super_admin, admin, support    |
| `PUT`    | `/api/v1/platform/tenants/{id}`                                     | Bearer      | super_admin, admin             |
| `POST`   | `/api/v1/platform/tenants/{id}/suspend`                             | Bearer      | super_admin, admin             |
| `POST`   | `/api/v1/platform/tenants/{id}/reactivate`                          | Bearer      | super_admin, admin             |
| `POST`   | `/api/v1/platform/tenants/{id}/cancel`                              | Bearer      | super_admin                    |
| `GET`    | `/api/v1/platform/tenants/{id}/metrics`                             | Bearer      | super_admin, admin, support    |
| `GET`    | `/api/v1/platform/plans`                                            | Bearer      | super_admin, admin, support    |
| `POST`   | `/api/v1/platform/plans`                                            | Bearer      | super_admin                    |
| `GET`    | `/api/v1/platform/plans/{id}`                                       | Bearer      | super_admin, admin, support    |
| `PUT`    | `/api/v1/platform/plans/{id}`                                       | Bearer      | super_admin                    |
| `POST`   | `/api/v1/platform/plans/{id}/deactivate`                            | Bearer      | super_admin                    |
| `GET`    | `/api/v1/platform/subscriptions`                                    | Bearer      | super_admin, admin, support    |
| `GET`    | `/api/v1/platform/subscriptions/{id}`                               | Bearer      | super_admin, admin, support    |
| `POST`   | `/api/v1/platform/subscriptions/{id}/change-plan`                   | Bearer      | super_admin, admin             |
| `POST`   | `/api/v1/platform/subscriptions/{id}/cancel`                        | Bearer      | super_admin, admin             |
| `GET`    | `/api/v1/platform/invoices`                                         | Bearer      | super_admin, admin, support    |
| `GET`    | `/api/v1/platform/invoices/{id}`                                    | Bearer      | super_admin, admin, support    |
| `POST`   | `/api/v1/platform/invoices/{id}/refund`                             | Bearer      | super_admin                    |
| `GET`    | `/api/v1/platform/payments`                                         | Bearer      | super_admin, admin, support    |
| `GET`    | `/api/v1/platform/payments/{id}`                                    | Bearer      | super_admin, admin, support    |
| `GET`    | `/api/v1/platform/feature-flags`                                    | Bearer      | super_admin, admin, support    |
| `POST`   | `/api/v1/platform/feature-flags`                                    | Bearer      | super_admin                    |
| `PUT`    | `/api/v1/platform/feature-flags/{id}`                               | Bearer      | super_admin                    |
| `POST`   | `/api/v1/platform/feature-flags/{id}/overrides`                     | Bearer      | super_admin, admin             |
| `DELETE` | `/api/v1/platform/feature-flags/{id}/overrides/{override_id}`       | Bearer      | super_admin, admin             |
| `GET`    | `/api/v1/platform/users`                                            | Bearer      | super_admin                    |
| `POST`   | `/api/v1/platform/users`                                            | Bearer      | super_admin                    |
| `PUT`    | `/api/v1/platform/users/{id}`                                       | Bearer      | super_admin                    |
| `POST`   | `/api/v1/platform/users/{id}/deactivate`                            | Bearer      | super_admin                    |
| `GET`    | `/api/v1/platform/dunning-policies`                                 | Bearer      | super_admin, admin, support    |
| `POST`   | `/api/v1/platform/dunning-policies`                                 | Bearer      | super_admin                    |
| `PUT`    | `/api/v1/platform/dunning-policies/{id}`                            | Bearer      | super_admin                    |
| `POST`   | `/api/v1/webhooks/stripe`                                           | Assinatura  | N/A (Stripe)                   |
| `GET`    | `/api/v1/platform/audit-logs`                                       | Bearer      | super_admin, admin             |
| `GET`    | `/api/v1/health`                                                    | Nao         | Publico                        |
