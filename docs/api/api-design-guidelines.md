# Diretrizes de Design da API - Condominium Events Manager

> Documento de referencia para o design e padronizacao da API RESTful do **Condominium Events Manager**.

> **Status:** Documento ativo\
> **Versao atual da API:** v1\
> **Ultima atualizacao:** 2026-02-10

---

## Sumario

1. [Visao Geral](#1-visao-geral)
2. [Convencoes de URL](#2-convencoes-de-url)
3. [Metodos HTTP](#3-metodos-http)
4. [Formato de Resposta](#4-formato-de-resposta)
5. [Formato de Erros](#5-formato-de-erros)
6. [Paginacao](#6-paginacao)
7. [Filtros e Ordenacao](#7-filtros-e-ordenacao)
8. [Includes (Eager Loading)](#8-includes-eager-loading)
9. [Versionamento](#9-versionamento)
10. [Rate Limiting](#10-rate-limiting)
11. [Headers](#11-headers)
12. [Datas e Horarios](#12-datas-e-horarios)
13. [Identificadores (IDs)](#13-identificadores-ids)
14. [Convencoes de Nomenclatura](#14-convencoes-de-nomenclatura)
15. [HATEOAS](#15-hateoas)
16. [Status](#16-status)

---

## 1. Visao Geral

O Condominium Events Manager e uma aplicacao **SaaS B2B Multi-Tenant** construida com **Laravel (PHP)**, seguindo uma abordagem **API-first**.

### 1.1 Principios Fundamentais

| Principio | Descricao |
|-----------|-----------|
| **API-first** | Nao existe frontend acoplado. Toda interacao ocorre exclusivamente via API REST. Clientes (web, mobile, integracoes) consomem a mesma API. |
| **JSON only** | Todas as respostas sao em formato JSON. Nao ha suporte a XML, HTML ou outros formatos de resposta. |
| **Autenticacao OAuth 2.1 + JWT** | Autenticacao baseada em OAuth 2.1 com tokens JWT. O JWT carrega claims de identidade, permissoes e contexto de tenant. |
| **Multi-tenant via JWT** | A resolucao do tenant e feita a partir dos claims presentes no JWT (claim `tenant_id`). Nao ha resolucao por subdominio ou header customizado. |
| **API versionada** | Todas as rotas utilizam o prefixo `/api/v1/`. Novas versoes sao introduzidas apenas para breaking changes. |
| **RESTful** | A API segue os principios REST, utilizando recursos (substantivos), metodos HTTP adequados e codigos de status semanticos. |
| **Stateless** | Toda requisicao deve conter todas as informacoes necessarias para ser processada. Nao ha estado de sessao no servidor. |
| **Consistencia** | Todos os endpoints seguem os mesmos padroes de nomenclatura, formato de resposta e tratamento de erros descritos neste documento. |

### 1.2 Stack Tecnico

- **Framework:** Laravel (PHP 8.2+)
- **Banco de dados:** PostgreSQL (single database, tenant isolation via coluna `tenant_id`)
- **Cache:** Redis
- **Fila:** Redis / Amazon SQS
- **Autenticacao:** Laravel Passport (OAuth 2.1) + JWT

---

## 2. Convencoes de URL

### 2.1 Estrutura Base

A API e organizada em quatro grupos de rotas, cada um com um proposito especifico:

```
Platform API:  /api/v1/platform/{resource}
Tenant API:    /api/v1/tenant/{resource}
Webhooks:      /api/v1/webhooks/{provider}
Auth:          /api/v1/auth/{action}
```

| Grupo | Descricao | Autenticacao |
|-------|-----------|--------------|
| **Platform** | Endpoints de administracao da plataforma (gerenciamento de tenants, planos, configuracoes globais). Acessivel apenas por administradores da plataforma. | JWT com role `platform_admin` |
| **Tenant** | Endpoints do contexto do condominio (espacos, reservas, moradores, unidades, etc.). O tenant e resolvido automaticamente a partir do JWT. | JWT com `tenant_id` valido |
| **Webhooks** | Endpoints para receber notificacoes de provedores externos (gateway de pagamento, provedores de email, etc.). | Assinatura/token do provedor |
| **Auth** | Endpoints de autenticacao e gerenciamento de sessao (login, logout, refresh token, reset de senha). | Publico ou JWT (dependendo do endpoint) |

### 2.2 Regras de URL

As seguintes regras **devem** ser seguidas em todas as rotas da API:

1. **kebab-case para recursos compostos:** Recursos com nomes compostos utilizam kebab-case.
   - Correto: `/support-requests`, `/service-providers`, `/common-areas`
   - Incorreto: `/supportRequests`, `/service_providers`, `/commonAreas`

2. **Substantivos no plural para colecoes:** Recursos que representam colecoes utilizam substantivos no plural.
   - Correto: `/spaces`, `/reservations`, `/residents`, `/units`
   - Incorreto: `/space`, `/reservation`, `/resident`, `/unit`

3. **Singular para acoes:** Endpoints de acao utilizam substantivos ou verbos no singular.
   - Correto: `/auth/login`, `/auth/logout`, `/auth/refresh`
   - Incorreto: `/auth/logins`, `/auth/logouts`

4. **Recursos aninhados para relacionamentos fortes:** Quando um recurso filho tem sentido apenas no contexto do recurso pai, utilizar aninhamento.
   - Correto: `/spaces/{id}/availabilities`, `/reservations/{id}/guests`
   - Limite: maximo de **2 niveis** de aninhamento (shallow nesting)
   - Incorreto: `/buildings/{id}/spaces/{id}/availabilities/{id}/slots` (muito profundo)

5. **Sem verbos na URL:** As acoes sao representadas pelos metodos HTTP, nao pela URL.
   - Correto: `POST /reservations` (criar reserva)
   - Incorreto: `/reservations/create`, `/reservations/new`
   - **Excecao:** Acoes de negocio que nao se encaixam em CRUD utilizam `POST` com o nome da acao.
     - Exemplo: `POST /reservations/{id}/cancel`, `POST /reservations/{id}/approve`

6. **IDs na URL:** Sempre usar o UUID do recurso, nunca IDs sequenciais.
   - Correto: `/spaces/01912c8e-7b3a-7d1f-a5c2-3e8f9b1d4a6e`
   - Incorreto: `/spaces/42`

### 2.3 Exemplos Completos

#### Espacos (Spaces)

```
GET    /api/v1/tenant/spaces                         # Listar espacos
POST   /api/v1/tenant/spaces                         # Criar espaco
GET    /api/v1/tenant/spaces/{id}                     # Obter espaco por ID
PUT    /api/v1/tenant/spaces/{id}                     # Atualizar espaco (completo)
PATCH  /api/v1/tenant/spaces/{id}                     # Atualizar espaco (parcial)
DELETE /api/v1/tenant/spaces/{id}                     # Desativar espaco (soft delete)
GET    /api/v1/tenant/spaces/{id}/availabilities      # Listar disponibilidades do espaco
POST   /api/v1/tenant/spaces/{id}/availabilities      # Criar disponibilidade para o espaco
```

#### Reservas (Reservations)

```
GET    /api/v1/tenant/reservations                    # Listar reservas
POST   /api/v1/tenant/reservations                    # Criar reserva
GET    /api/v1/tenant/reservations/{id}               # Obter reserva por ID
PUT    /api/v1/tenant/reservations/{id}               # Atualizar reserva
DELETE /api/v1/tenant/reservations/{id}               # Cancelar reserva
POST   /api/v1/tenant/reservations/{id}/cancel        # Cancelar reserva (acao de negocio)
POST   /api/v1/tenant/reservations/{id}/approve       # Aprovar reserva
POST   /api/v1/tenant/reservations/{id}/reject        # Rejeitar reserva
GET    /api/v1/tenant/reservations/{id}/guests         # Listar convidados da reserva
```

#### Autenticacao (Auth)

```
POST   /api/v1/auth/login                             # Login (obter access token)
POST   /api/v1/auth/logout                            # Logout (revogar token)
POST   /api/v1/auth/refresh                           # Refresh token
POST   /api/v1/auth/password/forgot                   # Solicitar reset de senha
POST   /api/v1/auth/password/reset                    # Redefinir senha
```

#### Plataforma (Platform)

```
GET    /api/v1/platform/tenants                       # Listar tenants
POST   /api/v1/platform/tenants                       # Criar tenant
GET    /api/v1/platform/tenants/{id}                  # Obter tenant por ID
PUT    /api/v1/platform/tenants/{id}                  # Atualizar tenant
POST   /api/v1/platform/tenants/{id}/suspend          # Suspender tenant
POST   /api/v1/platform/tenants/{id}/activate         # Ativar tenant
```

---

## 3. Metodos HTTP

| Metodo | Uso | Idempotente | Request Body | Resposta Tipica |
|--------|-----|:-----------:|:------------:|-----------------|
| **GET** | Recuperar recurso(s) | Sim | Nao | `200 OK` |
| **POST** | Criar recurso ou executar acao | Nao | Sim | `201 Created` ou `200 OK` |
| **PUT** | Atualizacao completa do recurso | Sim | Sim | `200 OK` |
| **PATCH** | Atualizacao parcial do recurso | Sim | Sim | `200 OK` |
| **DELETE** | Remover ou desativar recurso | Sim | Nao | `204 No Content` |

### 3.1 Detalhamento

- **GET:** Nunca deve causar efeitos colaterais. Utilizado para listagens e detalhes de recursos. Suporta filtros, ordenacao, paginacao e includes via query parameters.

- **POST:** Utilizado para duas situacoes:
  1. **Criacao de recurso:** Retorna `201 Created` com o recurso criado no body e o header `Location` apontando para o recurso.
  2. **Execucao de acao:** Retorna `200 OK` com o resultado da acao ou `204 No Content` quando nao ha corpo de resposta.

- **PUT:** Envia a representacao **completa** do recurso. Todos os campos devem estar presentes (campos omitidos serao definidos como `null` ou valor default). Retorna `200 OK`.

- **PATCH:** Envia **apenas os campos** a serem alterados. Campos omitidos nao sao modificados. Retorna `200 OK`.

- **DELETE:** Utilizado para remocao ou desativacao (soft delete) de recursos. Retorna `204 No Content`. Em caso de soft delete, o recurso e marcado como inativo mas permanece no banco de dados.

### 3.2 Acoes que Nao Sao CRUD

Acoes de dominio que vao alem do CRUD basico utilizam `POST` com um verbo de acao no final da URL:

```
POST /api/v1/tenant/reservations/{id}/approve       # Aprovar reserva
POST /api/v1/tenant/reservations/{id}/cancel        # Cancelar reserva
POST /api/v1/tenant/reservations/{id}/mark-no-show  # Registrar no-show
POST /api/v1/tenant/violations/{id}/confirm         # Confirmar violacao
POST /api/v1/tenant/violations/{id}/contest         # Contestar violacao
POST /api/v1/tenant/guests/{id}/check-in            # Registrar entrada de convidado
```

Essas acoes:

- Sempre utilizam **POST** (pois alteram estado e podem nao ser idempotentes).
- Podem aceitar um corpo JSON com dados adicionais (ex: motivo do cancelamento).
- Retornam `200 OK` com o recurso atualizado ou `204 No Content` quando apropriado.

---

## 4. Formato de Resposta

Todas as respostas da API seguem uma estrutura padronizada baseada em um envelope `data` para respostas de sucesso.

### 4.1 Resposta de Sucesso - Recurso Unico

HTTP `200 OK` ou `201 Created`

```json
{
  "data": {
    "id": "01912c8e-7b3a-7d1f-a5c2-3e8f9b1d4a6e",
    "type": "reservation",
    "attributes": {
      "space_id": "01912c8e-6a2b-7c0e-b4d1-2f7e8a0c3b5d",
      "unit_id": "01912c8e-5f1a-7b9d-c3e0-1d6f7b9a2c4e",
      "resident_id": "01912c8e-4e0f-7a8c-d2f1-0c5e6a8b1d3f",
      "start_datetime": "2025-01-15T14:00:00-03:00",
      "end_datetime": "2025-01-15T18:00:00-03:00",
      "status": "confirmed",
      "guest_count": 15,
      "is_recurring": false,
      "created_at": "2025-01-10T09:30:00-03:00",
      "updated_at": "2025-01-10T09:30:00-03:00"
    }
  }
}
```

**Estrutura:**

| Campo | Tipo | Descricao |
|-------|------|-----------|
| `data` | `object` | Objeto raiz contendo o recurso. |
| `data.id` | `string` | UUID v7 do recurso. |
| `data.type` | `string` | Tipo do recurso (singular, snake_case). |
| `data.attributes` | `object` | Atributos proprios do recurso. |

### 4.2 Resposta de Sucesso - Colecao (Lista Paginada)

HTTP `200 OK`

```json
{
  "data": [
    {
      "id": "01912c8e-7b3a-7d1f-a5c2-3e8f9b1d4a6e",
      "type": "space",
      "attributes": {
        "name": "Salao de Festas",
        "description": "Salao de festas com capacidade para 100 pessoas.",
        "max_capacity": 100,
        "is_active": true,
        "created_at": "2025-01-10T09:30:00-03:00",
        "updated_at": "2025-01-10T09:30:00-03:00"
      }
    },
    {
      "id": "01912c8e-8c4b-7e2f-b6d3-4f9a0c2e5d7b",
      "type": "space",
      "attributes": {
        "name": "Churrasqueira",
        "description": "Area de churrasqueira com espaco para 30 pessoas.",
        "max_capacity": 30,
        "is_active": true,
        "created_at": "2025-01-11T10:00:00-03:00",
        "updated_at": "2025-01-11T10:00:00-03:00"
      }
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  },
  "links": {
    "first": "/api/v1/tenant/spaces?page=1",
    "last": "/api/v1/tenant/spaces?page=8",
    "prev": null,
    "next": "/api/v1/tenant/spaces?page=2"
  }
}
```

**Estrutura:**

| Campo | Tipo | Descricao |
|-------|------|-----------|
| `data` | `array` | Array de recursos. |
| `meta` | `object` | Metadados de paginacao. |
| `meta.current_page` | `integer` | Pagina atual. |
| `meta.per_page` | `integer` | Itens por pagina. |
| `meta.total` | `integer` | Total de itens. |
| `meta.last_page` | `integer` | Numero da ultima pagina. |
| `links` | `object` | Links de navegacao entre paginas. |
| `links.first` | `string` | URL da primeira pagina. |
| `links.last` | `string` | URL da ultima pagina. |
| `links.prev` | `string\|null` | URL da pagina anterior (ou `null`). |
| `links.next` | `string\|null` | URL da proxima pagina (ou `null`). |

### 4.3 Resposta de Sucesso - Acao sem Corpo (204)

Quando uma acao e executada com sucesso e nao ha corpo de resposta:

```
HTTP/1.1 204 No Content
X-Request-ID: req_abc123def456
```

Utilizado para:
- `DELETE` de recursos
- Acoes que nao retornam dados (ex: logout, certas operacoes de batch)

### 4.4 Resposta de Criacao (201)

Quando um recurso e criado com sucesso:

```
HTTP/1.1 201 Created
Location: /api/v1/tenant/reservations/01912c8e-7b3a-7d1f-a5c2-3e8f9b1d4a6e
Content-Type: application/json

{
  "data": {
    "id": "01912c8e-7b3a-7d1f-a5c2-3e8f9b1d4a6e",
    "type": "reservation",
    "attributes": {
      "space_id": "01912c8e-6a2b-7c0e-b4d1-2f7e8a0c3b5d",
      "unit_id": "01912c8e-5f1a-7b9d-c3e0-1d6f7b9a2c4e",
      "status": "pending_approval",
      "start_datetime": "2025-01-15T14:00:00-03:00",
      "end_datetime": "2025-01-15T18:00:00-03:00",
      "created_at": "2025-01-10T09:30:00-03:00",
      "updated_at": "2025-01-10T09:30:00-03:00"
    }
  }
}
```

O header `Location` **sempre** deve estar presente em respostas `201 Created`, apontando para a URI do recurso criado.

---

## 5. Formato de Erros

Todas as respostas de erro seguem uma estrutura padronizada com um envelope `error`.

### 5.1 Resposta de Erro Padrao

```json
{
  "error": {
    "code": "RESERVATION_CONFLICT",
    "message": "The selected time slot conflicts with an existing reservation.",
    "details": [
      {
        "field": "start_datetime",
        "message": "Time slot 14:00-18:00 is already booked."
      }
    ]
  }
}
```

| Campo | Tipo | Obrigatorio | Descricao |
|-------|------|:-----------:|-----------|
| `error.code` | `string` | Sim | Codigo de erro unico da aplicacao (UPPER_SNAKE_CASE). Utilizado pelo cliente para tratamento programatico de erros. |
| `error.message` | `string` | Sim | Mensagem legivel descrevendo o erro. Pode ser exibida ao usuario final. |
| `error.details` | `array` | Nao | Lista de detalhes adicionais do erro. Presente em erros de validacao e erros de negocio com multiplos problemas. |
| `error.details[].field` | `string` | Nao | Campo especifico relacionado ao erro (quando aplicavel). |
| `error.details[].message` | `string` | Sim | Mensagem descritiva do detalhe do erro. |

### 5.2 Erro de Validacao (422 Unprocessable Entity)

Quando os dados enviados pelo cliente nao passam na validacao:

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": [
      {
        "field": "start_datetime",
        "message": "The start_datetime field is required."
      },
      {
        "field": "guest_count",
        "message": "The guest_count must not exceed 50."
      }
    ]
  }
}
```

O campo `details` e um **array** de objetos, cada um representando um campo com erro.

### 5.3 Codigos de Status HTTP

| Codigo | Nome | Uso |
|:------:|------|-----|
| `200` | OK | Sucesso em requisicoes GET, PUT e PATCH. |
| `201` | Created | Recurso criado com sucesso via POST. Inclui header `Location`. |
| `204` | No Content | Sucesso sem corpo de resposta (DELETE, acoes sem retorno). |
| `400` | Bad Request | Requisicao malformada (JSON invalido, parametros incorretos). |
| `401` | Unauthorized | Nao autenticado. Token ausente, expirado ou invalido. |
| `403` | Forbidden | Autenticado, porem sem permissao para a acao. Tambem utilizado quando o tenant esta inativo ou suspenso. |
| `404` | Not Found | Recurso nao encontrado. Tambem retornado quando o recurso pertence a outro tenant (para evitar information disclosure). |
| `409` | Conflict | Conflito de recurso (ex: conflito de horario em reserva, recurso duplicado). |
| `422` | Unprocessable Entity | Erros de validacao nos dados enviados. |
| `429` | Too Many Requests | Rate limit excedido. Inclui headers `X-RateLimit-*` e `Retry-After`. |
| `500` | Internal Server Error | Erro interno do servidor. Detalhes nao sao expostos ao cliente em producao. |

### 5.4 Codigos de Erro de Negocio

Todos os codigos de erro de negocio sao constantes em UPPER_SNAKE_CASE, categorizados por dominio.

#### Tenant e Assinatura

| Codigo | HTTP Status | Descricao |
|--------|:-----------:|-----------|
| `TENANT_INACTIVE` | `403` | O tenant esta inativo e nao pode realizar operacoes. |
| `TENANT_SUSPENDED` | `403` | O tenant esta suspenso (ex: inadimplencia). Acesso somente leitura pode ser permitido. |
| `SUBSCRIPTION_EXPIRED` | `403` | A assinatura do tenant expirou. |
| `SUBSCRIPTION_SUSPENDED` | `403` | A assinatura do tenant esta suspensa. |
| `FEATURE_NOT_AVAILABLE` | `403` | A funcionalidade solicitada nao esta disponivel no plano atual do tenant. |

#### Reservas

| Codigo | HTTP Status | Descricao |
|--------|:-----------:|-----------|
| `RESERVATION_CONFLICT` | `409` | O horario solicitado conflita com uma reserva existente. |
| `RESERVATION_BLOCKED` | `409` | O espaco esta bloqueado para reservas no periodo solicitado (manutencao, evento do condominio, etc.). |

#### Espacos

| Codigo | HTTP Status | Descricao |
|--------|:-----------:|-----------|
| `SPACE_INACTIVE` | `422` | O espaco esta inativo e nao aceita novas reservas. |
| `SPACE_BLOCKED` | `422` | O espaco esta temporariamente bloqueado. |

#### Unidades e Moradores

| Codigo | HTTP Status | Descricao |
|--------|:-----------:|-----------|
| `UNIT_INACTIVE` | `422` | A unidade esta inativa. |
| `RESIDENT_INACTIVE` | `422` | O morador esta inativo. |

#### Penalidades e Violacoes

| Codigo | HTTP Status | Descricao |
|--------|:-----------:|-----------|
| `PENALTY_ACTIVE` | `403` | O morador/unidade possui penalidade ativa (bloqueio temporario) que impede a acao solicitada. |
| `VIOLATION_ALREADY_CONTESTED` | `409` | A violacao ja foi contestada e nao pode ser contestada novamente. |

#### Limites

| Codigo | HTTP Status | Descricao |
|--------|:-----------:|-----------|
| `LIMIT_EXCEEDED` | `422` | Limite excedido. Aplicavel a diversos contextos: `max_units`, `max_users`, `max_reservations` por periodo, etc. O campo `details` contera informacoes sobre qual limite foi excedido. |
| `GUEST_LIMIT_EXCEEDED` | `422` | O numero de convidados excede o limite permitido para o espaco ou reserva. |

#### Convites

| Codigo | HTTP Status | Descricao |
|--------|:-----------:|-----------|
| `INVITATION_EXPIRED` | `422` | O convite expirou e nao pode mais ser utilizado. |
| `INVITATION_ALREADY_USED` | `409` | O convite ja foi utilizado. |

#### Autenticacao e Autorizacao

| Codigo | HTTP Status | Descricao |
|--------|:-----------:|-----------|
| `VALIDATION_ERROR` | `422` | Erro de validacao generico. Os detalhes dos campos invalidos estarao em `details`. |
| `UNAUTHORIZED` | `401` | Nao autenticado. Token ausente, expirado ou invalido. |
| `FORBIDDEN` | `403` | Autenticado, porem sem permissao para a acao solicitada. |

---

## 6. Paginacao

A API suporta dois tipos de paginacao, escolhidos conforme o caso de uso.

### 6.1 Paginacao por Offset (Padrao)

Utilizada para listagens administrativas onde a navegacao por paginas e necessaria.

**Query Parameters:**

| Parametro | Tipo | Default | Min | Max | Descricao |
|-----------|------|:-------:|:---:|:---:|-----------|
| `page` | `integer` | `1` | `1` | - | Numero da pagina. |
| `per_page` | `integer` | `20` | `1` | `100` | Itens por pagina. |

**Exemplo de Requisicao:**

```
GET /api/v1/tenant/spaces?page=2&per_page=15
```

**Resposta (meta e links):**

```json
{
  "data": [ "..." ],
  "meta": {
    "current_page": 2,
    "per_page": 15,
    "total": 150,
    "last_page": 10
  },
  "links": {
    "first": "/api/v1/tenant/spaces?page=1&per_page=15",
    "last": "/api/v1/tenant/spaces?page=10&per_page=15",
    "prev": "/api/v1/tenant/spaces?page=1&per_page=15",
    "next": "/api/v1/tenant/spaces?page=3&per_page=15"
  }
}
```

### 6.2 Paginacao por Cursor

Utilizada para datasets grandes e/ou em tempo real (logs de auditoria, listagem de reservas com muitos registros, feeds de atividades).

**Query Parameters:**

| Parametro | Tipo | Default | Descricao |
|-----------|------|:-------:|-----------|
| `cursor` | `string` | `null` | Cursor opaco retornado pela requisicao anterior. Omitir para a primeira pagina. |
| `per_page` | `integer` | `20` | Itens por pagina (max: `100`). |

**Exemplo de Requisicao:**

```
GET /api/v1/tenant/audit-logs?cursor=eyJpZCI6MTAwfQ&per_page=50
```

**Resposta (meta e links):**

```json
{
  "data": [ "..." ],
  "meta": {
    "per_page": 50,
    "has_more": true
  },
  "links": {
    "next": "/api/v1/tenant/audit-logs?cursor=eyJpZCI6MTUwfQ&per_page=50",
    "prev": "/api/v1/tenant/audit-logs?cursor=eyJpZCI6OTl9&per_page=50"
  }
}
```

### 6.3 Quando Usar Cada Tipo

| Tipo | Quando Usar | Vantagem |
|------|-------------|----------|
| **Offset** | Listas administrativas, tabelas com navegacao por pagina, relatorios. | Permite ir para qualquer pagina diretamente; fornece `total` de registros. |
| **Cursor** | Logs de auditoria, timelines, feeds, listagens com muitos registros (> 10.000). | Performance constante independente do tamanho do dataset; consistente com insercoes concorrentes. |

### 6.4 Regras Gerais

- O valor padrao de `per_page` e **20**.
- O valor maximo de `per_page` e **100**. Valores acima sao limitados automaticamente a 100.
- Listas vazias retornam `"data": []` com `"total": 0` (offset) ou sem cursor (cursor).
- O campo `meta` sempre esta presente em respostas de listagem.

---

## 7. Filtros e Ordenacao

### 7.1 Filtros

Filtros sao passados como query parameters. Cada recurso define quais filtros estao disponiveis (apenas campos indexados).

**Regras:**

- Filtros de **valor exato:** `?status=confirmed`, `?space_id=uuid`
- Filtros de **intervalo de datas:** `?date_from=2025-01-01&date_to=2025-01-31`
- Filtros de **enum:** correspondencia exata com os valores validos do enum
- **Busca textual:** `?search=termo` para busca full-text em campos indexados para texto

**Exemplos:**

```
# Filtrar reservas por status e espaco
GET /api/v1/tenant/reservations?status=confirmed&space_id=01912c8e-6a2b-7c0e-b4d1-2f7e8a0c3b5d

# Filtrar reservas por intervalo de datas
GET /api/v1/tenant/reservations?date_from=2025-01-01&date_to=2025-01-31

# Combinar filtros
GET /api/v1/tenant/reservations?status=confirmed&space_id=uuid&date_from=2025-01-01&date_to=2025-01-31

# Busca textual em espacos
GET /api/v1/tenant/spaces?search=churrasqueira

# Filtrar moradores por status
GET /api/v1/tenant/residents?is_active=true&unit_id=uuid
```

### 7.2 Ordenacao

A ordenacao e controlada pelos query parameters `sort` e `order`, ou pelo prefixo `-` para ordem descendente.

**Formato 1 - Parametros separados:**

| Parametro | Tipo | Default | Descricao |
|-----------|------|:-------:|-----------|
| `sort` | `string` | `created_at` | Campo para ordenacao. |
| `order` | `string` | `desc` | Direcao: `asc` ou `desc`. |

```
GET /api/v1/tenant/reservations?sort=start_datetime&order=asc
```

**Formato 2 - Prefixo (alternativo):**

Prefixo `-` indica ordem descendente. Sem prefixo, ordem ascendente.

```
GET /api/v1/tenant/violations?sort=-created_at
GET /api/v1/tenant/spaces?sort=name
```

**Regras:**

- Apenas campos indexados podem ser utilizados para ordenacao.
- Cada recurso define sua lista de campos ordenaveis (whitelist).
- Apenas um campo de ordenacao por requisicao.

---

## 8. Includes (Eager Loading)

O parametro `include` permite carregar relacionamentos junto com o recurso principal, evitando multiplas requisicoes (N+1).

### 8.1 Uso

```
GET /api/v1/tenant/reservations/{id}?include=space,unit,resident,guests
GET /api/v1/tenant/reservations?include=space,unit&status=confirmed
```

### 8.2 Regras

| Regra | Descricao |
|-------|-----------|
| **Whitelist** | Cada recurso define quais relacionamentos podem ser incluidos. Tentar incluir um relacionamento nao permitido resulta em erro `400 Bad Request`. |
| **Profundidade maxima** | 1 nivel. Nao e permitido incluir sub-relacionamentos (ex: `include=space.building` nao e valido). |
| **Multiplos includes** | Separar por virgula: `?include=space,unit,resident`. |
| **Performance** | Includes utilizam eager loading (Laravel `with()`) para evitar queries N+1. |

### 8.3 Formato da Resposta com Includes

Os relacionamentos incluidos sao incorporados dentro do objeto `attributes`:

```json
{
  "data": {
    "id": "01912c8e-7b3a-7d1f-a5c2-3e8f9b1d4a6e",
    "type": "reservation",
    "attributes": {
      "start_datetime": "2025-01-15T14:00:00-03:00",
      "end_datetime": "2025-01-15T18:00:00-03:00",
      "status": "confirmed",
      "space": {
        "id": "01912c8e-6a2b-7c0e-b4d1-2f7e8a0c3b5d",
        "name": "Salao de Festas",
        "max_capacity": 100
      },
      "unit": {
        "id": "01912c8e-5f1a-7b9d-c3e0-1d6f7b9a2c4e",
        "number": "101",
        "block": "A"
      },
      "resident": {
        "id": "01912c8e-4e0f-7a8c-d2f1-0c5e6a8b1d3f",
        "name": "Joao Silva"
      }
    }
  }
}
```

---

## 9. Versionamento

### 9.1 Estrategia

A API utiliza **versionamento baseado em URL**:

```
/api/v1/tenant/spaces
/api/v2/tenant/spaces   (futuro, apenas se necessario)
```

### 9.2 Regras

| Regra | Descricao |
|-------|-----------|
| **Versao atual** | `v1` |
| **Nova versao major** | Introduzida **apenas** para breaking changes (remocao de campos, mudanca de formato de resposta, alteracao de comportamento de endpoints existentes). |
| **Retrocompatibilidade** | Adicoes de campos, novos endpoints e novos query parameters **nao** sao considerados breaking changes e sao feitos na versao atual. |
| **Deprecacao** | Endpoints ou versoes a serem removidos terao um aviso com **6 meses** de antecedencia. |
| **Header Sunset** | Endpoints depreciados incluem o header `Sunset` com a data de remocao: `Sunset: Sat, 01 Jan 2027 00:00:00 GMT`. |
| **Header Deprecation** | Endpoints depreciados incluem o header `Deprecation: true`. |

### 9.3 O Que Constitui Breaking Change

**Breaking changes (requerem nova versao):**

- Remocao de endpoint
- Remocao de campo obrigatorio da resposta
- Mudanca de tipo de campo existente
- Mudanca de semantica de endpoint existente
- Mudanca no formato padrao de resposta
- Tornar um parametro opcional em obrigatorio

**Nao sao breaking changes (podem ser feitos na versao atual):**

- Adicao de novo endpoint
- Adicao de novo campo opcional na resposta
- Adicao de novo query parameter opcional
- Adicao de novo header opcional
- Adicao de novo valor em enum (quando o cliente trata valores desconhecidos)
- Adicao de novos codigos de erro
- Melhoria em mensagens de erro (texto)

### 9.4 Politica de Deprecacao

Quando uma versao ou endpoint precisa ser descontinuado:

1. **Anuncio:** publicar aviso com no minimo **6 meses** de antecedencia.
2. **Header Sunset:** adicionar o header `Sunset` com a data de descontinuacao.
3. **Header Deprecation:** adicionar o header `Deprecation: true`.
4. **Documentacao:** marcar o endpoint como deprecated na documentacao OpenAPI.
5. **Monitoramento:** monitorar uso para identificar clientes que ainda nao migraram.
6. **Remocao:** apos o periodo de aviso, retornar `410 Gone` no endpoint descontinuado.

**Exemplo de headers de deprecacao:**

```
Sunset: Sat, 01 Jul 2027 00:00:00 GMT
Deprecation: true
Link: </api/v2/tenant/spaces>; rel="successor-version"
```

---

## 10. Rate Limiting

A API implementa rate limiting para proteger contra abuso e garantir disponibilidade.

### 10.1 Limites por Tipo de Endpoint

| Tipo de Endpoint | Limite | Escopo |
|------------------|:------:|--------|
| Auth (login) | 5 requisicoes/minuto | Por IP |
| Auth (password reset) | 3 requisicoes/hora | Por email |
| Tenant API (leitura - GET) | 60 requisicoes/minuto | Por usuario autenticado |
| Tenant API (escrita - POST/PUT/PATCH/DELETE) | 30 requisicoes/minuto | Por usuario autenticado |
| Platform API | 120 requisicoes/minuto | Por usuario autenticado |
| Webhooks | 100 requisicoes/minuto | Por IP |

### 10.2 Headers de Rate Limiting

Todas as respostas incluem headers de rate limiting:

| Header | Descricao |
|--------|-----------|
| `X-RateLimit-Limit` | Numero maximo de requisicoes permitidas na janela atual. |
| `X-RateLimit-Remaining` | Numero de requisicoes restantes na janela atual. |
| `X-RateLimit-Reset` | Timestamp Unix (em segundos) de quando a janela de rate limiting sera resetada. |

**Exemplo:**

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1705312800
```

### 10.3 Resposta ao Exceder o Limite (429)

```
HTTP/1.1 429 Too Many Requests
Retry-After: 30
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 0
X-RateLimit-Reset: 1705312800

{
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Too many requests. Please try again in 30 seconds."
  }
}
```

O header `Retry-After` indica o numero de segundos que o cliente deve aguardar antes de tentar novamente.

### 10.4 Boas Praticas para Clientes

- Implementar backoff exponencial ao receber `429`.
- Respeitar o header `Retry-After`.
- Cachear respostas de leitura quando possivel.
- Evitar polling frequente; preferir webhooks para eventos.

---

## 11. Headers

### 11.1 Headers de Requisicao (Request)

| Header | Obrigatorio | Descricao | Exemplo |
|--------|:-----------:|-----------|---------|
| `Authorization` | Sim (exceto endpoints de auth) | Token de acesso no formato Bearer. | `Bearer eyJhbGciOiJSUzI1...` |
| `Content-Type` | Sim (POST/PUT/PATCH) | Tipo de conteudo do corpo da requisicao. Sempre `application/json`. | `application/json` |
| `Accept` | Sim | Tipo de conteudo aceito na resposta. Sempre `application/json`. | `application/json` |
| `X-Request-ID` | Nao | ID unico gerado pelo cliente para rastreamento (tracing). Se nao enviado, o servidor gera um automaticamente. | `req_abc123def456` |
| `Accept-Language` | Nao | Idioma preferido para mensagens de erro e resposta. Default: `pt-BR`. | `pt-BR`, `en` |

**Exemplo de requisicao completa:**

```
POST /api/v1/tenant/reservations HTTP/1.1
Host: api.condominiumevents.com.br
Authorization: Bearer eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...
Content-Type: application/json
Accept: application/json
X-Request-ID: 550e8400-e29b-41d4-a716-446655440000
Accept-Language: pt-BR
```

### 11.2 Headers de Resposta (Response)

| Header | Descricao | Exemplo |
|--------|-----------|---------|
| `X-Request-ID` | Mesmo ID enviado pelo cliente ou ID gerado pelo servidor. Utilizado para correlacao em logs. | `req_abc123def456` |
| `X-Correlation-ID` | ID de correlacao gerado pelo servidor. Agrupa requisicoes relacionadas em fluxos distribuidos. | `corr_xyz789ghi012` |
| `X-RateLimit-Limit` | Limite de requisicoes na janela atual. | `60` |
| `X-RateLimit-Remaining` | Requisicoes restantes na janela atual. | `45` |
| `X-RateLimit-Reset` | Timestamp Unix de reset da janela. | `1705312800` |
| `Location` | URI do recurso criado. Presente apenas em respostas `201 Created`. | `/api/v1/tenant/reservations/uuid` |
| `Sunset` | Data de remocao de endpoint depreciado (RFC 7231). | `Sat, 01 Jul 2027 00:00:00 GMT` |
| `Deprecation` | Indica que o endpoint esta depreciado. | `true` |

**Exemplo de resposta com headers:**

```
HTTP/1.1 201 Created
Content-Type: application/json
Location: /api/v1/tenant/reservations/01912c8e-7b3a-7d1f-a5c2-3e8f9b1d4a6e
X-Request-ID: 550e8400-e29b-41d4-a716-446655440000
X-Correlation-ID: corr_xyz789ghi012
X-RateLimit-Limit: 30
X-RateLimit-Remaining: 28
X-RateLimit-Reset: 1705312800
```

---

## 12. Datas e Horarios

### 12.1 Formato

Todas as datas e horarios seguem o padrao **ISO 8601**:

| Tipo | Formato | Exemplo |
|------|---------|---------|
| **Timestamp completo** | `YYYY-MM-DDThh:mm:ssTZD` | `2025-01-15T14:30:00-03:00` |
| **Data apenas** | `YYYY-MM-DD` | `2025-01-15` |
| **Hora apenas** | `hh:mm:ss` | `14:30:00` |

### 12.2 Regras de Timezone

| Regra | Descricao |
|-------|-----------|
| **Armazenamento** | Todos os timestamps sao armazenados em **UTC** no banco de dados. |
| **Entrada (request)** | O cliente pode enviar timestamps com qualquer timezone valido. O servidor converte para UTC antes de armazenar. |
| **Saida (response)** | Os timestamps nas respostas sao convertidos para o **timezone do tenant**, configurado nas configuracoes do condominio. |
| **Timezone do tenant** | Configurado em `tenant.settings.timezone` (ex: `America/Sao_Paulo`). Default: `America/Sao_Paulo`. |
| **Filtros de data** | Filtros `date_from` e `date_to` sao interpretados no timezone do tenant. |

### 12.3 Exemplos

```json
{
  "start_datetime": "2025-01-15T14:00:00-03:00",
  "end_datetime": "2025-01-15T18:00:00-03:00",
  "date": "2025-01-15",
  "time": "14:00:00",
  "created_at": "2025-01-10T09:30:00-03:00",
  "updated_at": "2025-01-10T09:30:00-03:00"
}
```

---

## 13. Identificadores (IDs)

### 13.1 Formato

| Regra | Descricao |
|-------|-----------|
| **Tipo** | **UUIDv7** (time-ordered). Garante unicidade global e ordenacao temporal. |
| **Formato no JSON** | Sempre representado como `string`. |
| **IDs sequenciais** | **Nunca** expostos na API. IDs sequenciais (auto-increment) sao utilizados internamente apenas no banco de dados para performance de joins/indexes. |
| **Exemplo** | `"01912c8e-7b3a-7d1f-a5c2-3e8f9b1d4a6e"` |

### 13.2 Justificativa do UUIDv7

- **Ordenacao temporal:** UUIDv7 codifica o timestamp de criacao, permitindo ordenacao natural sem campo adicional.
- **Performance em indice:** Por ser time-ordered, insercoes em indice B-tree sao sequenciais (ao contrario de UUIDv4 que causa fragmentacao).
- **Unicidade global:** Nao depende de sequencia centralizada, ideal para sistemas distribuidos e multi-tenant.
- **Seguranca:** Nao e possivel enumerar recursos ou inferir volume de dados a partir do ID.

---

## 14. Convencoes de Nomenclatura

### 14.1 Campos de Request e Response

Todos os campos em requisicoes e respostas utilizam **snake_case**:

```json
{
  "start_datetime": "2025-01-15T14:00:00-03:00",
  "guest_count": 15,
  "space_id": "uuid",
  "is_recurring": false,
  "max_capacity": 100,
  "created_at": "2025-01-10T09:30:00-03:00"
}
```

- Correto: `start_datetime`, `guest_count`, `space_id`
- Incorreto: `startDatetime`, `guestCount`, `spaceId`, `StartDatetime`

### 14.2 Valores de Enum

Valores de enum tambem utilizam **snake_case**:

```json
{
  "status": "pending_approval",
  "reservation_type": "single_use",
  "payment_status": "awaiting_payment"
}
```

### 14.3 Campos Booleanos

Campos booleanos **devem** ser prefixados com `is_` ou `has_`:

| Prefixo | Uso | Exemplos |
|---------|-----|----------|
| `is_` | Estado ou condicao do recurso. | `is_active`, `is_recurring`, `is_blocked`, `is_approved` |
| `has_` | Indica presenca ou posse de algo. | `has_mfa`, `has_parking`, `has_kitchen`, `has_penalty` |

- Correto: `is_active`, `has_mfa`
- Incorreto: `active`, `mfa_enabled`, `isActive`, `hasMfa`

### 14.4 Resumo de Convencoes

| Elemento | Convencao | Exemplo |
|----------|-----------|---------|
| URLs de recursos | kebab-case | `/common-areas`, `/support-requests` |
| Campos JSON | snake_case | `start_datetime`, `guest_count` |
| Enum values | snake_case | `pending_approval`, `single_use` |
| Booleanos | `is_` / `has_` prefix | `is_active`, `has_mfa` |
| Codigos de erro | UPPER_SNAKE_CASE | `RESERVATION_CONFLICT`, `TENANT_INACTIVE` |
| Query parameters | snake_case | `per_page`, `date_from`, `sort` |
| Headers customizados | X-PascalCase | `X-Request-ID`, `X-RateLimit-Limit` |

---

## 15. HATEOAS

### 15.1 Status na v1

Na versao `v1` da API, **HATEOAS nao e obrigatorio**. Os links de paginacao (`links`) ja sao fornecidos nas respostas de colecao, e o header `Location` e retornado em respostas `201 Created`.

### 15.2 Consideracoes Futuras

Em versoes futuras, links HATEOAS podem ser adicionados para melhorar a descoberta da API (discoverability). Um possivel formato seria:

```json
{
  "data": {
    "id": "01912c8e-7b3a-7d1f-a5c2-3e8f9b1d4a6e",
    "type": "reservation",
    "attributes": { "..." },
    "links": {
      "self": "/api/v1/tenant/reservations/01912c8e-7b3a-7d1f-a5c2-3e8f9b1d4a6e",
      "space": "/api/v1/tenant/spaces/01912c8e-6a2b-7c0e-b4d1-2f7e8a0c3b5d",
      "cancel": "/api/v1/tenant/reservations/01912c8e-7b3a-7d1f-a5c2-3e8f9b1d4a6e/cancel",
      "guests": "/api/v1/tenant/reservations/01912c8e-7b3a-7d1f-a5c2-3e8f9b1d4a6e/guests"
    }
  }
}
```

Esta funcionalidade sera avaliada com base na necessidade dos clientes e na complexidade da API.

---

## 16. Status

**Status do documento:** Ativo

Este documento e a referencia vigente para o design de todos os endpoints da API do Condominium Events Manager. Toda nova rota ou modificacao deve seguir as diretrizes aqui descritas.

| Informacao | Valor |
|------------|-------|
| Versao do documento | 2.0 |
| Ultima atualizacao | 2026-02-10 |
| Responsavel | Equipe de Engenharia |
| Validade | Enquanto o documento estiver com status "Ativo" |

---

## Apendice: Checklist para Novos Endpoints

Ao criar um novo endpoint, verificar:

- [ ] URL segue as convencoes de nomenclatura (kebab-case, plural, sem verbos)
- [ ] Metodo HTTP correto para a acao
- [ ] Resposta segue o formato padrao (envelope `data`)
- [ ] Erros seguem o formato padrao (envelope `error`)
- [ ] Codigos de status HTTP corretos
- [ ] Paginacao implementada para listagens
- [ ] Filtros e ordenacao documentados
- [ ] Includes permitidos definidos (whitelist)
- [ ] Rate limiting configurado
- [ ] Headers obrigatorios validados
- [ ] Datas no formato ISO 8601
- [ ] IDs em formato UUIDv7
- [ ] Campos em snake_case
- [ ] Booleanos com prefixo `is_` / `has_`
- [ ] Testes automatizados cobrindo sucesso e erro
- [ ] Documentacao OpenAPI atualizada
