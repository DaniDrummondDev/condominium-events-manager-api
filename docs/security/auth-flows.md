# Fluxos de Autenticacao — Condominium Events Manager API

## Status do Documento

**Status:** Ativo\
**Ultima atualizacao:** 2026-02-19\
**Versao da API:** v1

---

## 1. Visao Geral

O **Condominium Events Manager API** utiliza **OAuth 2.1 + JWT (JSON Web Tokens)** como estrategia principal de autenticacao. O sistema e **API-first**, sem frontend acoplado, e toda comunicacao e feita via tokens Bearer enviados nos headers HTTP.

### 1.1 Separacao entre Autenticacao e Autorizacao

| Conceito          | Responsabilidade                                                                 |
|-------------------|----------------------------------------------------------------------------------|
| **Autenticacao**  | Confirma **quem** e o usuario. Valida credenciais, emite tokens, gerencia sessoes. |
| **Autorizacao**   | Determina **o que** o usuario pode fazer. Avaliada por Roles + Policies.          |

A autenticacao e tratada neste documento. A autorizacao e tratada na [Matriz de Autorizacao](./authorization-matrix.md).

### 1.2 Estrategia OAuth 2.1

O sistema implementa os seguintes grant types do OAuth 2.1:

| Grant Type            | Uso                                                        |
|-----------------------|------------------------------------------------------------|
| **Resource Owner**    | Login de usuarios (plataforma e tenant) via email + senha  |
| **Client Credentials**| Comunicacao service-to-service (microservicos, webhooks)   |
| **Refresh Token**     | Renovacao de tokens de acesso sem re-autenticacao          |

> **Nota:** O grant type `Authorization Code` nao e utilizado, pois o sistema e API-only e nao possui fluxo de redirect para frontend.

### 1.3 Dois Contextos de Autenticacao

O sistema possui **dois contextos de autenticacao completamente separados**:

| Contexto       | Base de Usuarios        | Tabela               | Roles                                                  |
|----------------|-------------------------|----------------------|--------------------------------------------------------|
| **Plataforma** | Banco global (platform) | `platform_users`     | `platform_owner`, `platform_admin`, `platform_support` |
| **Tenant**     | Banco do tenant         | `tenant_users`       | `sindico`, `administradora`, `condomino`, `funcionario`|

Cada contexto possui seus proprios endpoints, tokens e ciclos de vida. Um token de plataforma **nunca** e aceito em rotas de tenant e vice-versa.

### 1.4 MFA Obrigatorio

A autenticacao multifator (MFA) via TOTP e **obrigatoria** para as seguintes roles:

| Role                | MFA Obrigatorio |
|---------------------|:---------------:|
| `platform_owner`    | Sim             |
| `platform_admin`    | Sim             |
| `platform_support`  | Nao             |
| `sindico`           | Sim             |
| `administradora`    | Sim             |
| `condomino`         | Nao             |
| `funcionario`       | Nao             |

Usuarios com MFA obrigatorio nao conseguem completar o login sem verificacao do segundo fator. O sistema retorna um token intermediario (`mfa_required`) ate que o codigo TOTP seja validado.

### 1.5 Resolucao de Tenant via Token

O tenant e resolvido exclusivamente a partir da claim `tenant_id` presente no JWT. **Nao existe resolucao de tenant por header, subdomain ou query parameter em rotas autenticadas.**

Regras:

- Um token pertence a **exatamente um** tenant.
- Para operar em outro tenant, o usuario deve realizar um novo login com o `tenant_slug` do tenant desejado.
- O `tenant_id` do token e validado contra o banco da plataforma em cada requisicao.
- Se o tenant estiver inativo, suspenso, cancelado ou arquivado, a requisicao e negada com `403 Forbidden`.

---

## 2. Fluxos de Autenticacao

### 2.1 Login de Usuario de Plataforma

**Endpoint:**

```
POST /api/v1/platform/auth/login
Content-Type: application/json
```

**Request Body:**

```json
{
  "email": "admin@condominium-events.com",
  "password": "s3cur3P@ssw0rd"
}
```

**Fluxo Detalhado:**

```
1. Receber email + password
2. Buscar usuario na tabela platform_users (banco platform) pelo email
3. Se usuario nao encontrado → 401 Unauthorized
4. Verificar se password confere (bcrypt)
   └── Se nao confere → 401 Unauthorized + incrementar contador de falhas
5. Verificar status do usuario
   ├── active    → continua
   ├── inactive  → 403 Forbidden ("Conta desativada")
   └── locked    → 403 Forbidden ("Conta bloqueada. Tente novamente em X minutos")
6. Verificar se conta esta bloqueada por excesso de tentativas
   └── Se bloqueada → 403 Forbidden ("Conta bloqueada temporariamente")
7. Resetar contador de falhas (login bem-sucedido ate aqui)
8. Verificar se MFA esta habilitado para o usuario
   ├── MFA habilitado:
   │   ├── Gerar mfa_required_token (JWT, TTL: 5 min)
   │   ├── Registrar evento auth.login.mfa_required
   │   └── Retornar resposta com mfa_required = true
   └── MFA nao habilitado:
       ├── Gerar access_token (JWT, TTL: 15 min)
       ├── Gerar refresh_token (opaco, TTL: 7 dias)
       ├── Armazenar hash do refresh_token no banco platform
       ├── Registrar evento auth.login.success
       └── Retornar tokens + perfil do usuario
```

**Response (sem MFA) — 200 OK:**

```json
{
  "data": {
    "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "dGhpcyBpcyBhIHJlZnJlc2ggdG9rZW4...",
    "token_type": "bearer",
    "expires_in": 900,
    "user": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Admin Principal",
      "email": "admin@condominium-events.com",
      "role": "platform_admin",
      "mfa_enabled": true,
      "created_at": "2025-01-15T10:30:00Z",
      "last_login_at": "2025-02-10T08:00:00Z"
    }
  }
}
```

**Response (com MFA pendente) — 200 OK:**

```json
{
  "data": {
    "mfa_required": true,
    "mfa_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "mfa_token_expires_in": 300,
    "mfa_methods": ["totp"]
  }
}
```

**Responses de Erro:**

| Codigo | Situacao                        | Body                                                                  |
|--------|---------------------------------|-----------------------------------------------------------------------|
| 401    | Credenciais invalidas           | `{ "error": "invalid_credentials", "message": "Email ou senha incorretos." }` |
| 403    | Conta desativada                | `{ "error": "account_disabled", "message": "Sua conta foi desativada." }` |
| 403    | Conta bloqueada                 | `{ "error": "account_locked", "message": "Conta bloqueada temporariamente. Tente novamente em 28 minutos." }` |
| 422    | Validacao                       | `{ "error": "validation_error", "message": "...", "errors": { "email": [...], "password": [...] } }` |
| 429    | Rate limit excedido             | `{ "error": "too_many_requests", "message": "Muitas tentativas. Tente novamente em 60 segundos.", "retry_after": 60 }` |

**Validacao do Request:**

| Campo      | Regras                                    |
|------------|-------------------------------------------|
| `email`    | obrigatorio, email valido, max 255 chars  |
| `password` | obrigatorio, string, min 8 chars          |

---

### 2.2 Login de Usuario de Tenant

**Endpoint:**

```
POST /api/v1/tenant/auth/login
Content-Type: application/json
```

**Request Body:**

```json
{
  "email": "joao.silva@email.com",
  "password": "m1nh@Senh@Segur@",
  "tenant_slug": "condominio-sol"
}
```

**Fluxo Detalhado:**

```
 1. Receber email + password + tenant_slug
 2. Resolver tenant pelo slug no banco da plataforma
    └── Se tenant nao encontrado → 404 Not Found ("Condominio nao encontrado")
 3. Verificar status do tenant
    ├── provisioning → 403 ("Condominio em configuracao. Aguarde a ativacao.")
    ├── active       → continua
    ├── trialing     → continua
    ├── past_due     → continua (modo somente leitura sera aplicado via policies)
    ├── suspended    → 403 ("Condominio suspenso. Entre em contato com o suporte.")
    ├── canceled     → 403 ("Condominio cancelado.")
    └── archived     → 403 ("Condominio arquivado.")
 4. Verificar status da assinatura do tenant
    ├── active       → continua
    ├── trialing     → continua
    ├── past_due     → continua (modo somente leitura)
    ├── canceled     → 403 ("Assinatura cancelada.")
    └── expired      → 403 ("Assinatura expirada.")
 5. Trocar conexao para o banco do tenant (dinamicamente)
 6. Buscar usuario na tabela tenant_users (banco do tenant) pelo email
    └── Se usuario nao encontrado → 401 Unauthorized
 7. Verificar se password confere (bcrypt)
    └── Se nao confere → 401 Unauthorized + incrementar contador de falhas
 8. Verificar status do usuario no tenant
    ├── active    → continua
    ├── inactive  → 403 ("Conta desativada neste condominio.")
    └── locked    → 403 ("Conta bloqueada temporariamente.")
 9. Verificar se conta esta bloqueada por excesso de tentativas
    └── Se bloqueada → 403 ("Conta bloqueada temporariamente.")
10. Resetar contador de falhas
11. Verificar se MFA esta habilitado para o usuario
    ├── MFA habilitado:
    │   ├── Gerar mfa_required_token (JWT, TTL: 5 min, inclui tenant_id)
    │   ├── Registrar evento auth.login.mfa_required
    │   └── Retornar resposta com mfa_required = true
    └── MFA nao habilitado:
        ├── Gerar access_token (JWT, TTL: 15 min, inclui tenant_id)
        ├── Gerar refresh_token (opaco, TTL: 7 dias)
        ├── Armazenar hash do refresh_token no banco do tenant
        ├── Registrar evento auth.login.success
        └── Retornar tokens + perfil do usuario + informacoes do tenant
```

**Response (sem MFA) — 200 OK:**

```json
{
  "data": {
    "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "dGhpcyBpcyBhIHJlZnJlc2ggdG9rZW4...",
    "token_type": "bearer",
    "expires_in": 900,
    "user": {
      "id": "660e8400-e29b-41d4-a716-446655440001",
      "name": "Joao Silva",
      "email": "joao.silva@email.com",
      "role": "sindico",
      "mfa_enabled": true,
      "unit": {
        "id": "770e8400-e29b-41d4-a716-446655440002",
        "block": "A",
        "number": "101"
      },
      "created_at": "2025-01-20T14:00:00Z",
      "last_login_at": "2025-02-10T09:15:00Z"
    },
    "tenant": {
      "id": "880e8400-e29b-41d4-a716-446655440003",
      "name": "Condominio Sol",
      "slug": "condominio-sol",
      "status": "active",
      "subscription_status": "active",
      "plan": "professional"
    }
  }
}
```

**Response (com MFA pendente) — 200 OK:**

```json
{
  "data": {
    "mfa_required": true,
    "mfa_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "mfa_token_expires_in": 300,
    "mfa_methods": ["totp"],
    "tenant": {
      "id": "880e8400-e29b-41d4-a716-446655440003",
      "name": "Condominio Sol",
      "slug": "condominio-sol"
    }
  }
}
```

**Responses de Erro:**

| Codigo | Situacao                         | Body                                                                  |
|--------|----------------------------------|-----------------------------------------------------------------------|
| 401    | Credenciais invalidas            | `{ "error": "invalid_credentials", "message": "Email ou senha incorretos." }` |
| 403    | Tenant suspenso                  | `{ "error": "tenant_suspended", "message": "Condominio suspenso. Entre em contato com o suporte." }` |
| 403    | Tenant cancelado                 | `{ "error": "tenant_canceled", "message": "Condominio cancelado." }` |
| 403    | Tenant em provisioning           | `{ "error": "tenant_provisioning", "message": "Condominio em configuracao. Aguarde a ativacao." }` |
| 403    | Assinatura invalida              | `{ "error": "subscription_invalid", "message": "Assinatura expirada ou cancelada." }` |
| 403    | Conta desativada                 | `{ "error": "account_disabled", "message": "Sua conta foi desativada neste condominio." }` |
| 403    | Conta bloqueada                  | `{ "error": "account_locked", "message": "Conta bloqueada temporariamente. Tente novamente em 28 minutos." }` |
| 404    | Tenant nao encontrado            | `{ "error": "tenant_not_found", "message": "Condominio nao encontrado." }` |
| 422    | Validacao                        | `{ "error": "validation_error", "message": "...", "errors": { ... } }` |
| 429    | Rate limit excedido              | `{ "error": "too_many_requests", "message": "Muitas tentativas. Tente novamente em 60 segundos.", "retry_after": 60 }` |

**Validacao do Request:**

| Campo         | Regras                                              |
|---------------|-----------------------------------------------------|
| `email`       | obrigatorio, email valido, max 255 chars            |
| `password`    | obrigatorio, string, min 8 chars                    |
| `tenant_slug` | obrigatorio, string, alpha_dash, max 100 chars      |

---

### 2.3 Verificacao MFA (TOTP)

**Endpoint:**

```
POST /api/v1/{context}/auth/mfa/verify
Content-Type: application/json
Authorization: Bearer {mfa_required_token}
```

Onde `{context}` pode ser `platform` ou `tenant`.

**Request Body:**

```json
{
  "code": "482915"
}
```

**Fluxo Detalhado:**

```
1. Extrair mfa_required_token do header Authorization
2. Validar o token JWT
   ├── Verificar assinatura (RS256)
   ├── Verificar expiracao (TTL: 5 min)
   ├── Verificar claim token_type === "mfa_required"
   ├── Verificar claim jti nao esta na revocation list
   └── Se invalido → 401 Unauthorized
3. Extrair user_id (sub) e tenant_id (se contexto tenant) do token
4. Se contexto tenant:
   ├── Resolver tenant pelo tenant_id
   ├── Verificar status do tenant (active/trialing)
   └── Trocar conexao para o banco do tenant
5. Buscar usuario e seu segredo TOTP
   └── Se usuario nao encontrado → 401 Unauthorized
6. Verificar codigo TOTP
   ├── Validar codigo contra o segredo TOTP do usuario
   ├── Considerar janela de tolerancia de +/- 1 periodo (30s)
   ├── Verificar se codigo ja foi usado (replay protection)
   │   └── Se ja usado → 401 ("Codigo ja utilizado. Aguarde o proximo.")
   ├── Se codigo valido:
   │   ├── Resetar contador de falhas MFA
   │   ├── Revogar mfa_required_token (adicionar jti a revocation list)
   │   ├── Gerar access_token (JWT, TTL: 15 min)
   │   ├── Gerar refresh_token (opaco, TTL: 7 dias)
   │   ├── Armazenar hash do refresh_token no banco apropriado
   │   ├── Registrar evento auth.mfa.verified
   │   └── Retornar tokens + perfil do usuario
   └── Se codigo invalido:
       ├── Incrementar contador de falhas MFA
       ├── Se falhas >= 5:
       │   ├── Bloquear conta temporariamente (30 min)
       │   ├── Invalidar mfa_required_token
       │   ├── Registrar evento auth.account.locked
       │   └── Retornar 403 ("Conta bloqueada por excesso de tentativas MFA")
       ├── Registrar evento auth.mfa.failed
       └── Retornar 401 ("Codigo MFA invalido. Tentativas restantes: X")
```

**Response (sucesso) — 200 OK:**

```json
{
  "data": {
    "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "dGhpcyBpcyBhIHJlZnJlc2ggdG9rZW4...",
    "token_type": "bearer",
    "expires_in": 900,
    "user": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "name": "Admin Principal",
      "email": "admin@condominium-events.com",
      "role": "platform_admin",
      "mfa_enabled": true
    }
  }
}
```

**Responses de Erro:**

| Codigo | Situacao                         | Body                                                                  |
|--------|----------------------------------|-----------------------------------------------------------------------|
| 401    | Token MFA invalido/expirado      | `{ "error": "invalid_mfa_token", "message": "Token MFA invalido ou expirado. Faca login novamente." }` |
| 401    | Codigo TOTP invalido             | `{ "error": "invalid_mfa_code", "message": "Codigo MFA invalido. Tentativas restantes: 3." }` |
| 401    | Codigo ja utilizado              | `{ "error": "mfa_code_reused", "message": "Codigo ja utilizado. Aguarde o proximo codigo." }` |
| 403    | Conta bloqueada (excesso MFA)    | `{ "error": "account_locked", "message": "Conta bloqueada por excesso de tentativas MFA. Tente novamente em 30 minutos." }` |
| 422    | Validacao                        | `{ "error": "validation_error", "errors": { "code": ["O campo codigo e obrigatorio."] } }` |

**Validacao do Request:**

| Campo  | Regras                                          |
|--------|-------------------------------------------------|
| `code` | obrigatorio, string, exatamente 6 digitos       |

---

### 2.4 Setup MFA (Configuracao Inicial)

**Endpoint — Iniciar Setup:**

```
POST /api/v1/{context}/auth/mfa/setup
Content-Type: application/json
Authorization: Bearer {access_token}
```

**Fluxo Detalhado — Etapa 1 (Gerar Segredo):**

```
1. Validar access_token (JWT valido, nao expirado, nao revogado)
2. Verificar se o usuario ja possui MFA habilitado
   └── Se ja habilitado → 409 Conflict ("MFA ja esta habilitado.")
3. Gerar segredo TOTP (base32, 160 bits)
4. Gerar URI otpauth para QR Code
   └── Formato: otpauth://totp/CondominiumEvents:{email}?secret={secret}&issuer=CondominiumEvents&algorithm=SHA1&digits=6&period=30
5. Armazenar segredo temporario (pendente de confirmacao) no banco
6. Registrar evento auth.mfa.setup_initiated
7. Retornar segredo + URI + QR code (base64)
```

**Response — 200 OK:**

```json
{
  "data": {
    "secret": "JBSWY3DPEHPK3PXP",
    "otpauth_uri": "otpauth://totp/CondominiumEvents:admin@condominium-events.com?secret=JBSWY3DPEHPK3PXP&issuer=CondominiumEvents&algorithm=SHA1&digits=6&period=30",
    "qr_code_base64": "data:image/png;base64,iVBORw0KGgo...",
    "recovery_codes": [
      "A1B2C3D4E5",
      "F6G7H8I9J0",
      "K1L2M3N4O5",
      "P6Q7R8S9T0",
      "U1V2W3X4Y5",
      "Z6A7B8C9D0",
      "E1F2G3H4I5",
      "J6K7L8M9N0"
    ]
  }
}
```

**Endpoint — Confirmar Setup:**

```
POST /api/v1/{context}/auth/mfa/setup/confirm
Content-Type: application/json
Authorization: Bearer {access_token}
```

**Request Body:**

```json
{
  "code": "384291"
}
```

**Fluxo Detalhado — Etapa 2 (Confirmar):**

```
1. Validar access_token
2. Verificar se existe setup pendente para o usuario
   └── Se nao existe → 400 Bad Request ("Nenhum setup MFA pendente.")
3. Validar codigo TOTP contra o segredo temporario
   ├── Se valido:
   │   ├── Ativar MFA na conta do usuario (mfa_enabled = true)
   │   ├── Persistir segredo TOTP definitivo (criptografado)
   │   ├── Persistir recovery codes (hash bcrypt cada um)
   │   ├── Remover segredo temporario
   │   ├── Registrar evento auth.mfa.enabled
   │   └── Retornar confirmacao
   └── Se invalido:
       └── Retornar 401 ("Codigo invalido. Tente novamente.")
```

**Response — 200 OK:**

```json
{
  "data": {
    "mfa_enabled": true,
    "message": "MFA habilitado com sucesso. Guarde os codigos de recuperacao em local seguro."
  }
}
```

**Endpoint — Desabilitar MFA:**

```
DELETE /api/v1/{context}/auth/mfa
Content-Type: application/json
Authorization: Bearer {access_token}
```

**Request Body:**

```json
{
  "code": "482915",
  "password": "m1nh@Senh@Segur@"
}
```

**Fluxo:**

```
1. Validar access_token
2. Verificar se MFA esta habilitado
   └── Se nao esta → 400 ("MFA nao esta habilitado.")
3. Verificar se a role do usuario permite desabilitar MFA
   └── Se role exige MFA obrigatorio → 403 ("MFA e obrigatorio para sua role.")
4. Validar password do usuario (confirmacao de identidade)
5. Validar codigo TOTP
6. Desabilitar MFA (mfa_enabled = false)
7. Remover segredo TOTP e recovery codes
8. Registrar evento auth.mfa.disabled
9. Retornar confirmacao
```

> **Importante:** Usuarios com roles que exigem MFA obrigatorio (`platform_owner`, `platform_admin`, `sindico`, `administradora`) **nao podem** desabilitar o MFA. Apenas usuarios com roles que nao exigem MFA (`platform_support`, `condomino`, `funcionario`) podem desabilitar, caso tenham habilitado voluntariamente.

---

### 2.5 Refresh Token

**Endpoint:**

```
POST /api/v1/{context}/auth/refresh
Content-Type: application/json
```

> **Nota:** Este endpoint NAO exige Authorization header. O refresh_token e enviado no body.

**Request Body:**

```json
{
  "refresh_token": "dGhpcyBpcyBhIHJlZnJlc2ggdG9rZW4..."
}
```

**Fluxo Detalhado:**

```
 1. Receber refresh_token do body
 2. Calcular hash do refresh_token recebido
 3. Buscar refresh_token no banco pelo hash
    └── Se nao encontrado → 401 ("Refresh token invalido.")
 4. Verificar se o refresh_token esta expirado (TTL: 7 dias)
    └── Se expirado → 401 ("Refresh token expirado. Faca login novamente.")
 5. Verificar se o refresh_token ja foi utilizado (rotacao)
    ├── Se ja foi utilizado (reuso detectado):
    │   ├── *** ALERTA DE SEGURANCA: possivel roubo de token ***
    │   ├── Invalidar TODA a cadeia de refresh tokens do usuario
    │   ├── Revogar todos os access tokens ativos (adicionar jtis a revocation list)
    │   ├── Registrar evento auth.token.chain_revoked com severity: critical
    │   ├── Enviar notificacao de seguranca ao usuario (email/push)
    │   └── Retornar 401 ("Sessao invalidada por motivos de seguranca.")
    └── Se nao foi utilizado (primeiro uso): continua
 6. Se contexto tenant:
    ├── Extrair tenant_id associado ao refresh_token
    ├── Resolver tenant no banco da plataforma
    ├── Verificar status do tenant (active/trialing/past_due)
    │   └── Se inativo → 403 ("Condominio suspenso ou cancelado.")
    └── Trocar conexao para o banco do tenant
 7. Buscar usuario associado ao refresh_token
    ├── Verificar status do usuario (active)
    │   └── Se inativo/bloqueado → 401 ("Conta desativada ou bloqueada.")
    └── Se usuario nao encontrado → 401
 8. Marcar refresh_token atual como "usado" (nao deletar, manter para deteccao de reuso)
 9. Gerar novo access_token (JWT, TTL: 15 min)
10. Gerar novo refresh_token (opaco, TTL: 7 dias)
11. Armazenar hash do novo refresh_token com referencia ao anterior (cadeia)
12. Registrar evento auth.token.refreshed
13. Retornar novos tokens
```

**Response — 200 OK:**

```json
{
  "data": {
    "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "bm92byByZWZyZXNoIHRva2VuIGFxdWk...",
    "token_type": "bearer",
    "expires_in": 900
  }
}
```

**Responses de Erro:**

| Codigo | Situacao                         | Body                                                                  |
|--------|----------------------------------|-----------------------------------------------------------------------|
| 401    | Refresh token invalido           | `{ "error": "invalid_refresh_token", "message": "Refresh token invalido." }` |
| 401    | Refresh token expirado           | `{ "error": "refresh_token_expired", "message": "Refresh token expirado. Faca login novamente." }` |
| 401    | Reuso detectado (roubo)          | `{ "error": "token_reuse_detected", "message": "Sessao invalidada por motivos de seguranca. Faca login novamente." }` |
| 401    | Usuario desativado               | `{ "error": "account_disabled", "message": "Conta desativada ou bloqueada." }` |
| 403    | Tenant inativo                   | `{ "error": "tenant_inactive", "message": "Condominio suspenso ou cancelado." }` |
| 422    | Validacao                        | `{ "error": "validation_error", "errors": { "refresh_token": [...] } }` |

**Validacao do Request:**

| Campo           | Regras                           |
|-----------------|----------------------------------|
| `refresh_token` | obrigatorio, string              |

---

### 2.6 Logout

**Endpoint:**

```
POST /api/v1/{context}/auth/logout
Authorization: Bearer {access_token}
```

> **Nota:** Este endpoint nao requer body.

**Fluxo Detalhado:**

```
1. Extrair access_token do header Authorization
2. Validar o token JWT
   └── Se invalido → 401 Unauthorized
3. Extrair jti (token ID) do access_token
4. Adicionar jti a revocation list (Redis/cache)
   └── TTL na revocation list = tempo restante de vida do access_token
5. Buscar todos os refresh_tokens da cadeia do usuario
6. Invalidar toda a cadeia de refresh_tokens
   ├── Marcar todos como "revoked"
   └── Registrar timestamp de revogacao
7. Se contexto tenant:
   └── Limpar contexto de tenant
8. Registrar evento auth.logout
9. Retornar 204 No Content
```

**Response — 204 No Content:**

(sem body)

**Responses de Erro:**

| Codigo | Situacao                         | Body                                                                  |
|--------|----------------------------------|-----------------------------------------------------------------------|
| 401    | Token invalido                   | `{ "error": "unauthenticated", "message": "Token invalido ou expirado." }` |

---

### 2.7 Client Credentials (Service-to-Service)

**Endpoint:**

```
POST /api/v1/auth/token
Content-Type: application/json
```

**Request Body:**

```json
{
  "grant_type": "client_credentials",
  "client_id": "service-webhook-receiver",
  "client_secret": "k8s-generated-secret-here",
  "scope": "webhooks:receive events:publish"
}
```

**Fluxo Detalhado:**

```
1. Receber client_id, client_secret e scope
2. Validar grant_type === "client_credentials"
   └── Se diferente → 400 ("Grant type nao suportado.")
3. Buscar client na tabela oauth_clients (banco platform)
   └── Se nao encontrado → 401 ("Client nao encontrado.")
4. Verificar client_secret (hash bcrypt)
   └── Se invalido → 401 ("Credenciais do client invalidas.")
5. Verificar status do client
   ├── active   → continua
   └── revoked  → 401 ("Client revogado.")
6. Validar scopes solicitados
   ├── Verificar se cada scope solicitado esta permitido para o client
   └── Se algum scope nao permitido → 403 ("Scope nao autorizado: {scope}")
7. Gerar access_token (JWT, TTL: 1 hora)
   ├── Claims especiais:
   │   ├── sub: client_id
   │   ├── token_type: "client_credentials"
   │   ├── scopes: ["webhooks:receive", "events:publish"]
   │   └── tenant_id: null (ou tenant_id especifico se client for tenant-scoped)
   └── SEM refresh_token (client credentials nao usa refresh)
8. Registrar evento auth.client.token_issued
9. Retornar token
```

**Response — 200 OK:**

```json
{
  "data": {
    "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "token_type": "bearer",
    "expires_in": 3600,
    "scope": "webhooks:receive events:publish"
  }
}
```

**Responses de Erro:**

| Codigo | Situacao                         | Body                                                                  |
|--------|----------------------------------|-----------------------------------------------------------------------|
| 400    | Grant type invalido              | `{ "error": "unsupported_grant_type", "message": "Grant type nao suportado." }` |
| 401    | Client nao encontrado            | `{ "error": "invalid_client", "message": "Client nao encontrado." }` |
| 401    | Secret invalido                  | `{ "error": "invalid_client", "message": "Credenciais do client invalidas." }` |
| 403    | Scope nao autorizado             | `{ "error": "invalid_scope", "message": "Scope nao autorizado: admin:write" }` |

**Casos de Uso:**

| Servico                   | Scopes Tipicos                            |
|---------------------------|-------------------------------------------|
| Webhook Receiver          | `webhooks:receive`                        |
| Job Scheduler (Cron)      | `jobs:execute tenants:read`               |
| Notification Service      | `notifications:send users:read`           |
| Metrics Collector         | `metrics:write tenants:read`              |
| Billing Integration       | `billing:process subscriptions:manage`    |

**Validacao do Request:**

| Campo           | Regras                                              |
|-----------------|-----------------------------------------------------|
| `grant_type`    | obrigatorio, deve ser "client_credentials"          |
| `client_id`     | obrigatorio, string, max 255 chars                  |
| `client_secret` | obrigatorio, string, min 32 chars                   |
| `scope`         | opcional, string (scopes separados por espaco)      |

---

### 2.8 Onboarding / Registro de Morador (via Convite)

**Endpoint:**

```
POST /api/v1/tenant/auth/register
Content-Type: application/json
```

**Request Body:**

```json
{
  "invitation_token": "inv_a1b2c3d4e5f6g7h8i9j0...",
  "name": "Maria Santos",
  "email": "maria.santos@email.com",
  "password": "m1nh@Senh@Segur@",
  "password_confirmation": "m1nh@Senh@Segur@",
  "phone": "+5511999998888"
}
```

**Fluxo Detalhado:**

```
 1. Receber dados de registro + invitation_token
 2. Buscar convite na tabela invitations (banco platform)
    └── Se nao encontrado → 404 ("Convite nao encontrado.")
 3. Validar convite:
    ├── Verificar se nao esta expirado (TTL configuravel, padrao: 7 dias)
    │   └── Se expirado → 410 Gone ("Convite expirado. Solicite um novo convite.")
    ├── Verificar se nao foi utilizado
    │   └── Se ja utilizado → 409 Conflict ("Convite ja utilizado.")
    ├── Verificar se nao foi revogado
    │   └── Se revogado → 410 Gone ("Convite revogado.")
    └── Verificar se o email do registro corresponde ao email do convite
        └── Se diferente → 422 ("Email nao corresponde ao convite.")
 4. Resolver tenant a partir do convite (tenant_id no convite)
 5. Verificar status do tenant (active/trialing)
    └── Se inativo → 403 ("Condominio indisponivel.")
 6. Trocar conexao para o banco do tenant
 7. Verificar se ja existe usuario com este email no tenant
    └── Se ja existe → 409 Conflict ("Ja existe uma conta com este email neste condominio.")
 8. Criar tenant_user no banco do tenant
    ├── name, email, password (bcrypt hash)
    ├── phone
    ├── role: extraida do convite (geralmente "condomino")
    ├── status: active
    ├── mfa_enabled: false (primeiro acesso, MFA sera configurado depois se necessario)
    └── email_verified_at: now() (email verificado via token do convite)
 9. Criar registro em residents (vincular usuario a unidade)
    ├── tenant_user_id: id do usuario criado
    ├── unit_id: extraido do convite
    ├── type: extraido do convite (owner/resident/dependent)
    └── moved_in_at: now()
10. Marcar convite como utilizado
    ├── used_at: now()
    └── used_by: id do usuario criado
11. Gerar access_token (JWT, TTL: 15 min, inclui tenant_id)
12. Gerar refresh_token (opaco, TTL: 7 dias)
13. Armazenar hash do refresh_token no banco do tenant
14. Registrar evento auth.register.success
15. Disparar evento domain: ResidentRegistered (para notificacoes, auditoria)
16. Retornar tokens + perfil do usuario + info do tenant
```

**Response — 201 Created:**

```json
{
  "data": {
    "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
    "refresh_token": "dGhpcyBpcyBhIHJlZnJlc2ggdG9rZW4...",
    "token_type": "bearer",
    "expires_in": 900,
    "user": {
      "id": "990e8400-e29b-41d4-a716-446655440004",
      "name": "Maria Santos",
      "email": "maria.santos@email.com",
      "role": "condomino",
      "mfa_enabled": false,
      "unit": {
        "id": "770e8400-e29b-41d4-a716-446655440002",
        "block": "A",
        "number": "101"
      }
    },
    "tenant": {
      "id": "880e8400-e29b-41d4-a716-446655440003",
      "name": "Condominio Sol",
      "slug": "condominio-sol"
    }
  }
}
```

**Responses de Erro:**

| Codigo | Situacao                         | Body                                                                  |
|--------|----------------------------------|-----------------------------------------------------------------------|
| 404    | Convite nao encontrado           | `{ "error": "invitation_not_found", "message": "Convite nao encontrado." }` |
| 409    | Convite ja utilizado             | `{ "error": "invitation_used", "message": "Convite ja utilizado." }` |
| 409    | Email ja registrado no tenant    | `{ "error": "email_already_registered", "message": "Ja existe uma conta com este email neste condominio." }` |
| 410    | Convite expirado                 | `{ "error": "invitation_expired", "message": "Convite expirado. Solicite um novo convite." }` |
| 410    | Convite revogado                 | `{ "error": "invitation_revoked", "message": "Convite revogado." }` |
| 422    | Email nao corresponde            | `{ "error": "email_mismatch", "message": "Email nao corresponde ao convite." }` |
| 422    | Validacao                        | `{ "error": "validation_error", "errors": { ... } }` |
| 403    | Tenant indisponivel              | `{ "error": "tenant_unavailable", "message": "Condominio indisponivel." }` |

**Validacao do Request:**

| Campo                    | Regras                                                     |
|--------------------------|------------------------------------------------------------|
| `invitation_token`       | obrigatorio, string                                        |
| `name`                   | obrigatorio, string, min 3, max 255 chars                  |
| `email`                  | obrigatorio, email valido, max 255 chars                   |
| `password`               | obrigatorio, string, min 8, confirmado, mixed case + numeros |
| `password_confirmation`  | obrigatorio, deve ser igual a password                     |
| `phone`                  | opcional, string, formato E.164 (+55XXXXXXXXXXX)           |

---

### 2.9 Recuperacao de Senha

#### 2.9.1 Solicitar Reset de Senha

**Endpoint:**

```
POST /api/v1/{context}/auth/forgot-password
Content-Type: application/json
```

**Request Body (Plataforma):**

```json
{
  "email": "admin@condominium-events.com"
}
```

**Request Body (Tenant):**

```json
{
  "email": "joao.silva@email.com",
  "tenant_slug": "condominio-sol"
}
```

**Fluxo Detalhado:**

```
1. Receber email (e tenant_slug se contexto tenant)
2. Se contexto tenant:
   ├── Resolver tenant pelo slug
   ├── Verificar status do tenant (active/trialing/past_due)
   │   └── Se inativo → 403
   └── Trocar conexao para o banco do tenant
3. Buscar usuario pelo email no banco apropriado
   └── Se nao encontrado → retornar 200 OK (NUNCA revelar se email existe)
4. Se usuario encontrado:
   ├── Verificar se usuario esta ativo
   │   └── Se inativo/bloqueado → retornar 200 OK (nao revelar)
   ├── Verificar se ja existe token de reset valido
   │   └── Se existe e nao expirou → invalidar o anterior
   ├── Gerar token de reset (64 bytes, criptograficamente seguro)
   ├── Armazenar hash do token com TTL de 1 hora
   ├── Enviar email com link de reset
   │   └── Link: {frontend_url}/reset-password?token={token}&email={email}
   ├── Registrar evento auth.password.reset_requested
   └── Rate limit: maximo 3 solicitacoes por email a cada 15 minutos
5. Retornar 200 OK (sempre, independente de o email existir ou nao)
```

**Response — 200 OK:**

```json
{
  "data": {
    "message": "Se o email estiver cadastrado, voce recebera as instrucoes de recuperacao de senha."
  }
}
```

> **Importante:** A resposta e sempre 200 OK com a mesma mensagem, independente de o email existir ou nao no sistema. Isso impede enumeracao de usuarios.

#### 2.9.2 Executar Reset de Senha

**Endpoint:**

```
POST /api/v1/{context}/auth/reset-password
Content-Type: application/json
```

**Request Body (Plataforma):**

```json
{
  "token": "a1b2c3d4e5f6...",
  "email": "admin@condominium-events.com",
  "password": "n0v@Senh@Segur@",
  "password_confirmation": "n0v@Senh@Segur@"
}
```

**Request Body (Tenant):**

```json
{
  "token": "a1b2c3d4e5f6...",
  "email": "joao.silva@email.com",
  "password": "n0v@Senh@Segur@",
  "password_confirmation": "n0v@Senh@Segur@",
  "tenant_slug": "condominio-sol"
}
```

**Fluxo Detalhado:**

```
1. Receber token, email, password (e tenant_slug se contexto tenant)
2. Se contexto tenant:
   ├── Resolver tenant pelo slug
   └── Trocar conexao para o banco do tenant
3. Buscar token de reset pelo hash
   └── Se nao encontrado → 400 ("Token de reset invalido.")
4. Verificar se o token nao expirou (TTL: 1 hora)
   └── Se expirado → 400 ("Token de reset expirado. Solicite um novo.")
5. Verificar se o email corresponde ao token
   └── Se nao corresponde → 400 ("Token de reset invalido.")
6. Validar nova senha
   ├── Minimo 8 caracteres
   ├── Pelo menos 1 maiuscula, 1 minuscula, 1 numero
   ├── Nao pode ser igual as ultimas 5 senhas
   └── Se invalida → 422 com erros especificos
7. Atualizar senha do usuario (bcrypt hash)
8. Invalidar token de reset (marcar como usado)
9. Invalidar TODOS os refresh tokens do usuario (forcar re-login)
10. Revogar TODOS os access tokens ativos (adicionar jtis a revocation list)
11. Se conta estava bloqueada → desbloquear
12. Resetar contador de falhas de login
13. Registrar evento auth.password.reset
14. Enviar email de confirmacao de alteracao de senha
15. Retornar confirmacao
```

**Response — 200 OK:**

```json
{
  "data": {
    "message": "Senha alterada com sucesso. Faca login com sua nova senha."
  }
}
```

**Responses de Erro:**

| Codigo | Situacao                         | Body                                                                  |
|--------|----------------------------------|-----------------------------------------------------------------------|
| 400    | Token invalido                   | `{ "error": "invalid_reset_token", "message": "Token de reset invalido." }` |
| 400    | Token expirado                   | `{ "error": "reset_token_expired", "message": "Token de reset expirado. Solicite um novo." }` |
| 422    | Senha fraca                      | `{ "error": "validation_error", "errors": { "password": ["A senha deve conter pelo menos 8 caracteres, incluindo maiusculas, minusculas e numeros."] } }` |
| 422    | Senha ja utilizada               | `{ "error": "validation_error", "errors": { "password": ["Esta senha ja foi utilizada recentemente. Escolha uma senha diferente."] } }` |
| 422    | Validacao                        | `{ "error": "validation_error", "errors": { ... } }` |

**Validacao do Request:**

| Campo                    | Regras                                                     |
|--------------------------|------------------------------------------------------------|
| `token`                  | obrigatorio, string                                        |
| `email`                  | obrigatorio, email valido                                  |
| `password`               | obrigatorio, string, min 8, confirmado, mixed case + numeros |
| `password_confirmation`  | obrigatorio, deve ser igual a password                     |
| `tenant_slug`            | obrigatorio (somente contexto tenant), string, alpha_dash  |

---

### 2.10 Registro de Condominio (Self-Service com Verificacao de Email)

O registro de novos condominios eh um fluxo publico (sem autenticacao) que exige verificacao de email antes de criar o Tenant e iniciar o provisionamento.

#### 2.10.1 Submeter Registro

**Endpoint:**

```
POST /api/v1/platform/public/register
Content-Type: application/json
```

**Request Body:**

```json
{
  "condominium": {
    "name": "Condominio Solar",
    "slug": "condominio-solar",
    "type": "vertical"
  },
  "admin": {
    "name": "Joao Silva",
    "email": "joao@email.com",
    "password": "s3cur3P@ssw0rd",
    "password_confirmation": "s3cur3P@ssw0rd"
  },
  "plan_slug": "basico"
}
```

**Fluxo Detalhado:**

```
1. Receber dados de registro (condominium + admin + plan_slug)
2. Validar campos obrigatorios e formatos
3. Verificar unicidade do slug:
   ├── Buscar em tenants pelo slug
   │   └── Se encontrado → 422 ("Slug ja esta em uso")
   └── Buscar em pending_registrations ativos (nao expirados, nao verificados) pelo slug
       └── Se encontrado → 422 ("Registro com este slug ja esta pendente de verificacao")
4. Validar tipo do condominio (horizontal, vertical, mixed)
   └── Se invalido → 422
5. Validar plano:
   ├── Buscar plano pelo slug
   └── Verificar se plano esta ativo e disponivel
       └── Se inexistente ou inativo → 422
6. Gerar token de verificacao:
   ├── Token: 64 bytes aleatorios (Str::random(64))
   ├── Hash para armazenamento: hash('sha256', $plainToken)
   └── Token plain enviado no email (NUNCA armazenado)
7. Hashear senha do admin:
   └── bcrypt($password) — armazenado no PendingRegistration
8. Criar PendingRegistration:
   ├── id: UUIDv7
   ├── slug, name, type, admin_name, admin_email
   ├── admin_password_hash: hash bcrypt
   ├── plan_slug
   ├── verification_token_hash: hash SHA-256 do token
   └── expires_at: now() + 24 horas
9. Enviar email de verificacao:
   ├── Template: tenant-verification
   ├── Destinatario: admin_email
   ├── Dados: admin_name, condominium_name, verification_token (plain)
   ├── Queue: notifications (assincrono)
   └── Link: {app_url}/api/v1/platform/public/register/verify?token={plain_token}
10. Retornar 202 Accepted com slug e mensagem de verificacao
```

**Resposta (202 Accepted):**

```json
{
  "data": {
    "slug": "condominio-solar",
    "message": "Verifique seu email para continuar o cadastro."
  }
}
```

**Cenarios de Erro:**

| Codigo | Erro | Descricao |
|--------|------|-----------|
| 422 | `VALIDATION_ERROR` | Campos invalidos |
| 429 | `TOO_MANY_REQUESTS` | Rate limit excedido |

**Seguranca:**
- Senha hasheada com bcrypt antes de armazenar
- Token de verificacao armazenado como hash SHA-256 (token plain nunca persiste)
- PendingRegistration expira em 24 horas
- Rate limit aplicado por IP para prevenir abuso
- Nenhum Tenant ou database criado ate verificacao do email

---

#### 2.10.2 Verificar Email e Criar Tenant

**Endpoint:**

```
GET /api/v1/platform/public/register/verify?token={verification_token}
```

**Fluxo Detalhado:**

```
1. Receber token via query parameter
   └── Se ausente → 400 ("Token de verificacao obrigatorio")
2. Hashear token recebido: hash('sha256', $token)
3. Buscar pending_registration pelo verification_token_hash:
   └── Filtro: verified_at IS NULL (nao permite reuso)
       └── Se nao encontrado → 404 ("Token invalido")
4. Validar expiracao:
   └── Se expires_at < now() → 410 ("Token expirado")
5. Re-validar unicidade do slug na tabela tenants:
   └── Se slug ja existe → 409 ("Slug ja registrado") — protecao contra race condition
6. Marcar pending_registration como verificado:
   └── UPDATE verified_at = NOW()
7. Criar Tenant:
   ├── id: UUIDv7
   ├── slug, name, type (do PendingRegistration)
   ├── status: provisioning
   └── config: { plan_slug, admin_name, admin_email, admin_password_hash, admin_phone }
8. Iniciar provisionamento:
   └── Despachar evento TenantCreated → Listener → ProvisionTenantJob
9. Retornar 200 OK com tenant_slug e status
```

**Resposta (200 OK):**

```json
{
  "data": {
    "tenant_slug": "condominio-solar",
    "status": "provisioning",
    "message": "Email verificado com sucesso. Seu condominio esta sendo provisionado."
  }
}
```

**Cenarios de Erro:**

| Codigo | Erro | Descricao |
|--------|------|-----------|
| 400 | `VERIFICATION_TOKEN_REQUIRED` | Token ausente na query string |
| 404 | `VERIFICATION_TOKEN_INVALID` | Token nao encontrado ou ja utilizado |
| 409 | `TENANT_SLUG_ALREADY_EXISTS` | Slug ja registrado (race condition) |
| 410 | `VERIFICATION_TOKEN_EXPIRED` | Token expirado (apos 24 horas) |

**Seguranca:**
- Token validado por hash SHA-256, nao por comparacao direta
- Token nao pode ser reutilizado (verified_at marca uso)
- Unicidade do slug re-validada no momento da criacao (unique constraint + check)
- Provisionamento eh assincrono (nao bloqueia a resposta)

---

#### 2.10.3 Diagrama de Sequencia — Registro Self-Service

```
Visitante          API                    DB (Platform)         Email Service
    |                |                         |                      |
    |--POST /register-->                       |                      |
    |                |--validate slug---------->|                      |
    |                |<--slug available---------|                      |
    |                |--validate plan---------->|                      |
    |                |<--plan active------------|                      |
    |                |--hash password           |                      |
    |                |--generate token          |                      |
    |                |--hash token (SHA-256)    |                      |
    |                |--save pending----------->|                      |
    |                |<--saved------------------|                      |
    |                |--send verification email-|--------------------->|
    |<--202 Accepted-|                         |                      |
    |                |                         |                      |
    |  (Clica no link do email)                |                      |
    |                |                         |                      |
    |--GET /verify?token=xxx-->                |                      |
    |                |--hash token (SHA-256)    |                      |
    |                |--find by token hash----->|                      |
    |                |<--pending found----------|                      |
    |                |--check expiration        |                      |
    |                |--re-check slug---------->|                      |
    |                |<--slug still available---|                      |
    |                |--mark verified---------->|                      |
    |                |--create tenant---------->|                      |
    |                |<--tenant created---------|                      |
    |                |--dispatch TenantCreated  |                      |
    |<--200 OK-------|                         |                      |
    |                |                         |                      |
    |                |  (Async) ProvisionTenantJob                    |
    |                |--create database-------->|                      |
    |                |--run migrations--------->|                      |
    |                |--create admin (sindico)->|                      |
    |                |--update status: active-->|                      |
```

---

## 3. Estrutura do JWT Token

### 3.1 Access Token (Plataforma)

```json
{
  "sub": "550e8400-e29b-41d4-a716-446655440000",
  "tenant_id": null,
  "roles": ["platform_admin"],
  "token_type": "access",
  "iss": "condominium-events-api",
  "aud": "condominium-events-client",
  "exp": 1707555600,
  "iat": 1707554700,
  "jti": "tok_a1b2c3d4-e5f6-7890-abcd-ef1234567890"
}
```

### 3.2 Access Token (Tenant)

```json
{
  "sub": "660e8400-e29b-41d4-a716-446655440001",
  "tenant_id": "880e8400-e29b-41d4-a716-446655440003",
  "roles": ["sindico"],
  "token_type": "access",
  "iss": "condominium-events-api",
  "aud": "condominium-events-client",
  "exp": 1707555600,
  "iat": 1707554700,
  "jti": "tok_f1e2d3c4-b5a6-7890-1234-567890abcdef"
}
```

### 3.3 MFA Required Token

```json
{
  "sub": "550e8400-e29b-41d4-a716-446655440000",
  "tenant_id": null,
  "roles": ["platform_admin"],
  "token_type": "mfa_required",
  "iss": "condominium-events-api",
  "aud": "condominium-events-client",
  "exp": 1707555000,
  "iat": 1707554700,
  "jti": "mfa_a1b2c3d4-e5f6-7890-abcd-ef1234567890"
}
```

### 3.4 Client Credentials Token

```json
{
  "sub": "service-webhook-receiver",
  "tenant_id": null,
  "roles": [],
  "scopes": ["webhooks:receive", "events:publish"],
  "token_type": "client_credentials",
  "iss": "condominium-events-api",
  "aud": "condominium-events-service",
  "exp": 1707558300,
  "iat": 1707554700,
  "jti": "cc_a1b2c3d4-e5f6-7890-abcd-ef1234567890"
}
```

### 3.5 Descricao das Claims

| Claim        | Tipo     | Descricao                                                                         |
|--------------|----------|-----------------------------------------------------------------------------------|
| `sub`        | string   | Identificador unico do sujeito (UUID do usuario ou client_id do servico)          |
| `tenant_id`  | string?  | UUID do tenant. `null` para tokens de plataforma e client_credentials sem tenant  |
| `roles`      | string[] | Array de roles do usuario. Vazio para client_credentials                          |
| `scopes`     | string[] | Array de scopes autorizados. Presente apenas em tokens client_credentials         |
| `token_type` | string   | Tipo do token: `access`, `mfa_required`, `client_credentials`                     |
| `iss`        | string   | Emissor do token. Sempre `condominium-events-api`                                 |
| `aud`        | string   | Audiencia do token. `condominium-events-client` ou `condominium-events-service`   |
| `exp`        | integer  | Timestamp Unix de expiracao do token                                              |
| `iat`        | integer  | Timestamp Unix de emissao do token                                                |
| `jti`        | string   | Identificador unico do token (JWT ID). Usado para revogacao                       |

### 3.6 Formato do JTI

O JTI segue um formato prefixado para facilitar identificacao e debug:

| Token Type           | Prefixo JTI | Exemplo                                          |
|----------------------|-------------|--------------------------------------------------|
| Access Token         | `tok_`      | `tok_a1b2c3d4-e5f6-7890-abcd-ef1234567890`      |
| MFA Required Token   | `mfa_`      | `mfa_a1b2c3d4-e5f6-7890-abcd-ef1234567890`      |
| Client Credentials   | `cc_`       | `cc_a1b2c3d4-e5f6-7890-abcd-ef1234567890`       |

---

## 4. Seguranca de Tokens

### 4.1 Tempos de Vida (TTL)

| Token                 | TTL        | Justificativa                                                      |
|-----------------------|------------|--------------------------------------------------------------------|
| Access Token          | 15 minutos | Curto para limitar janela de exposicao em caso de vazamento        |
| Refresh Token         | 7 dias     | Longo o suficiente para UX, curto o suficiente para seguranca      |
| MFA Required Token    | 5 minutos  | Tempo suficiente para o usuario abrir o app autenticador           |
| Client Credentials    | 1 hora     | Service-to-service nao tem interacao humana, renovacao automatica  |
| Password Reset Token  | 1 hora     | Tempo suficiente para o usuario acessar o email e redefinir        |
| Invitation Token      | 7 dias     | Tempo para o morador receber e aceitar o convite                   |

### 4.2 Rotacao de Refresh Token

A rotacao de refresh token e a pratica de emitir um **novo** refresh token a cada uso, invalidando o anterior:

```
Login:    access_token_1 + refresh_token_1
Refresh:  access_token_2 + refresh_token_2  (refresh_token_1 marcado como "usado")
Refresh:  access_token_3 + refresh_token_3  (refresh_token_2 marcado como "usado")
```

**Deteccao de Reuso (Token Theft Detection):**

Se um refresh_token ja marcado como "usado" for apresentado novamente, o sistema interpreta como possivel roubo de token:

```
Atacante rouba refresh_token_1

Usuario usa refresh_token_1 → recebe refresh_token_2 (token_1 marcado como "usado")
Atacante tenta usar refresh_token_1 → REUSO DETECTADO

Acao:
├── Invalida toda a cadeia: refresh_token_1, refresh_token_2, ...
├── Revoga todos os access_tokens ativos do usuario
├── Registra evento de seguranca (severity: critical)
├── Envia notificacao ao usuario
└── Usuario precisa fazer login novamente
```

### 4.3 Revogacao via JTI

O sistema mantém uma **revocation list** baseada no `jti` (JWT ID) de cada token:

**Armazenamento:**

```
Redis/Cache:
├── Key: "revoked_token:{jti}"
├── Value: timestamp de revogacao
└── TTL: tempo restante de vida do token (auto-cleanup)
```

**Verificacao a cada requisicao:**

```
1. Extrair jti do access_token
2. Buscar "revoked_token:{jti}" no Redis/cache
3. Se encontrado → token revogado → 401 Unauthorized
4. Se nao encontrado → token valido → continua
```

**Eventos que adicionam JTI a revocation list:**

| Evento                          | JTIs Revogados                      |
|---------------------------------|-------------------------------------|
| Logout                          | JTI do access_token atual           |
| Refresh Token (token antigo)    | Nenhum (access antigo expira naturalmente) |
| Deteccao de reuso               | Todos os JTIs da cadeia do usuario  |
| Reset de senha                  | Todos os JTIs ativos do usuario     |
| Desativacao de conta            | Todos os JTIs ativos do usuario     |
| Suspensao de tenant             | Todos os JTIs de todos os usuarios do tenant |

### 4.4 Assinatura do Token (RS256)

Os tokens JWT sao assinados com **RS256 (RSA Signature with SHA-256)**, um algoritmo assimetrico:

| Aspecto          | Detalhe                                                         |
|------------------|-----------------------------------------------------------------|
| Algoritmo        | RS256 (RSA-SHA256)                                              |
| Chave Privada    | Usada para **assinar** tokens (servidor de autenticacao)        |
| Chave Publica    | Usada para **verificar** tokens (qualquer servico)              |
| Tamanho da Chave | 2048 bits (minimo), 4096 bits (recomendado para producao)       |
| Formato          | PEM (PKCS#8 para privada, SPKI para publica)                   |

**Vantagens do RS256 sobre HS256:**

- A chave publica pode ser distribuida para outros servicos sem comprometer a seguranca.
- Apenas o servidor de autenticacao precisa da chave privada.
- Permite verificacao descentralizada de tokens.
- JWKS endpoint para distribuicao automatica de chaves publicas.

### 4.5 Estrategia de Rotacao de Chaves

```
1. Gerar novo par de chaves (kid: "key-2025-02")
2. Publicar chave publica no JWKS endpoint com ambas as chaves (antiga + nova)
3. Comecar a assinar tokens com a nova chave
4. Manter chave publica antiga no JWKS ate todos os tokens antigos expirarem (15 min)
5. Remover chave publica antiga do JWKS
```

**JWKS Endpoint:**

```
GET /api/v1/.well-known/jwks.json

Response:
{
  "keys": [
    {
      "kty": "RSA",
      "kid": "key-2025-02",
      "use": "sig",
      "alg": "RS256",
      "n": "...",
      "e": "AQAB"
    }
  ]
}
```

### 4.6 Armazenamento de Refresh Tokens

Os refresh tokens sao opacos (nao sao JWT) e armazenados como hash no banco:

**Tabela: `refresh_tokens` (platform ou tenant)**

| Coluna             | Tipo        | Descricao                                              |
|--------------------|-------------|--------------------------------------------------------|
| `id`               | uuid (PK)   | Identificador unico do registro                       |
| `user_id`          | uuid (FK)   | Usuario dono do token                                  |
| `token_hash`       | string(128)  | SHA-256 hash do refresh token                         |
| `parent_id`        | uuid? (FK)  | ID do refresh token anterior na cadeia (para rotacao) |
| `expires_at`       | timestamp   | Data de expiracao                                      |
| `used_at`          | timestamp?  | Data em que foi usado (null se ainda nao usado)        |
| `revoked_at`       | timestamp?  | Data de revogacao (null se ativo)                      |
| `ip_address`       | string(45)  | IP que gerou o token                                   |
| `user_agent`       | string(500) | User-Agent que gerou o token                           |
| `created_at`       | timestamp   | Data de criacao                                        |

---

## 5. Protecao contra Ataques

### 5.1 Forca Bruta (Brute Force)

**Rate Limiting por Endpoint:**

| Endpoint                          | Limite                         | Janela    |
|-----------------------------------|--------------------------------|-----------|
| `POST /*/auth/login`             | 5 tentativas                   | 1 minuto  |
| `POST /*/auth/mfa/verify`        | 5 tentativas                   | 1 minuto  |
| `POST /*/auth/forgot-password`   | 3 solicitacoes por email       | 15 minutos|
| `POST /*/auth/reset-password`    | 5 tentativas                   | 15 minutos|
| `POST /*/auth/refresh`           | 10 tentativas                  | 1 minuto  |
| `POST /auth/token` (client cred) | 10 tentativas                  | 1 minuto  |
| `POST /*/auth/register`          | 3 tentativas                   | 15 minutos|

**Implementacao:**

```
Rate Limiter: Laravel RateLimiter (backed by Redis)
Chave: "auth:{ip}:{endpoint}" ou "auth:{email}:{endpoint}"
Headers de resposta:
  X-RateLimit-Limit: 5
  X-RateLimit-Remaining: 3
  X-RateLimit-Reset: 1707555600
  Retry-After: 45 (quando excedido)
```

### 5.2 Bloqueio de Conta (Account Lockout)

| Parametro                    | Valor                                         |
|------------------------------|-----------------------------------------------|
| Tentativas antes do lockout  | 10 tentativas falhas consecutivas             |
| Duracao do lockout           | 30 minutos                                    |
| Escopo do contador           | Por usuario (nao por IP)                      |
| Reset do contador            | Apos login bem-sucedido                       |
| Desbloqueio automatico       | Apos 30 minutos ou reset de senha             |
| Notificacao                  | Email ao usuario apos lockout                 |

**Fluxo de Lockout:**

```
Tentativa 1-9: login falha, contador incrementa, retorna 401
Tentativa 10:  login falha, conta bloqueada
               ├── Status do usuario → locked
               ├── locked_until = now() + 30 minutos
               ├── Registra evento auth.account.locked
               ├── Envia email ao usuario
               └── Retorna 403 ("Conta bloqueada por 30 minutos")

Tentativa 11+: (dentro dos 30 min)
               └── Retorna 403 ("Conta bloqueada. Tente em X minutos")

Apos 30 min:   Conta desbloqueada automaticamente
               ├── Status → active
               ├── Contador resetado
               └── Login normal permitido
```

### 5.3 CSRF (Cross-Site Request Forgery)

**Nao aplicavel.** O sistema e API-only e utiliza JWT Bearer tokens. CSRF e uma vulnerabilidade de cookies de sessao, que nao sao utilizados nesta arquitetura.

### 5.4 Roubo de Token (Token Theft)

**Protecoes implementadas:**

| Mecanismo                         | Descricao                                                         |
|-----------------------------------|-------------------------------------------------------------------|
| Refresh Token Rotation            | Novo refresh token a cada uso, anterior invalidado                |
| Reuse Detection                   | Reuso de token ja usado invalida toda a cadeia                    |
| Short-Lived Access Tokens         | 15 min de vida limita a janela de uso de token roubado            |
| JTI Revocation                    | Revogacao imediata por identificador unico do token               |
| IP + User-Agent Logging           | Registro de contexto para auditoria e deteccao de anomalias       |
| Notificacao de Seguranca          | Email ao usuario quando reuso e detectado                         |

### 5.5 Replay Attacks

| Mecanismo                         | Descricao                                                         |
|-----------------------------------|-------------------------------------------------------------------|
| JTI (JWT ID)                      | Cada token tem um identificador unico, verificado na revocation list |
| Short TTL                         | Access tokens expiram em 15 minutos                               |
| TOTP Replay Protection            | Codigo TOTP so pode ser usado uma vez por periodo de 30s          |
| Token Binding                     | Refresh tokens vinculados a user_id + cadeia                      |

### 5.6 Enumeracao de Usuarios

| Endpoint                     | Protecao                                                            |
|------------------------------|---------------------------------------------------------------------|
| Login                        | Mensagem generica "Email ou senha incorretos" (nao revela se email existe) |
| Forgot Password              | Sempre retorna 200 OK com mesma mensagem                           |
| Register (via convite)       | Convite ja vincula ao email, 404 generico se token invalido        |

### 5.7 Injecao e Manipulacao de JWT

| Ameaca                            | Protecao                                                          |
|-----------------------------------|-------------------------------------------------------------------|
| Alteracao de claims               | Assinatura RS256 invalida se token for alterado                   |
| Algoritmo "none"                  | Validacao rigorosa: aceitar SOMENTE RS256                         |
| Key confusion (RS256 → HS256)     | Rejeitar qualquer algoritmo diferente de RS256 no header do JWT   |
| Token forjado                     | Verificacao de assinatura com chave publica                       |
| Claim tenant_id manipulado        | Tenant_id do token e validado contra banco da plataforma          |

---

## 6. Integracao com Tenant Lifecycle

O estado do tenant impacta diretamente a capacidade de autenticacao dos seus usuarios.

### 6.1 Matriz de Status do Tenant vs Autenticacao

| Status do Tenant   | Login Permitido | Refresh Token | Acesso a Dados       | Observacao                                    |
|--------------------|:---------------:|:-------------:|:--------------------:|-----------------------------------------------|
| `provisioning`     | Nao             | Nao           | Nenhum               | Tenant em configuracao inicial                |
| `active`           | Sim             | Sim           | Total                | Operacao normal                               |
| `trialing`         | Sim             | Sim           | Total                | Periodo de teste                              |
| `past_due`         | Sim             | Sim           | Somente leitura*     | Pagamento atrasado, modo restrito             |
| `suspended`        | Nao             | Nao           | Nenhum               | Suspensao administrativa                      |
| `canceled`         | Nao             | Nao           | Nenhum               | Assinatura cancelada                          |
| `archived`         | Nao             | Nao           | Nenhum               | Dados retidos para compliance                 |
| `pending_deletion` | Nao             | Nao           | Nenhum               | Aguardando exclusao definitiva                |

> *Somente leitura: o login e permitido, mas acoes de escrita sao bloqueadas pelas Policies de autorizacao, nao pela camada de autenticacao. O token e emitido normalmente, e as restricoes sao aplicadas no nivel de autorizacao.

### 6.2 Transicoes de Estado e Impacto nos Tokens

```
Tenant ativo → suspenso:
├── Todos os refresh tokens do tenant sao invalidados
├── Todos os access tokens sao adicionados a revocation list
├── Proxima requisicao de qualquer usuario → 403
└── Evento: tenant.suspended (dispara invalidacao em massa)

Tenant ativo → past_due:
├── Tokens continuam validos
├── Login continua funcionando
├── Policies passam a restringir escrita
└── Evento: tenant.past_due (dispara notificacoes)

Tenant suspenso → ativo (reativacao):
├── Nenhum token e restaurado
├── Usuarios precisam fazer login novamente
└── Evento: tenant.reactivated
```

### 6.3 Verificacao em Cada Requisicao

O status do tenant e verificado em **cada requisicao autenticada**, nao apenas no login:

```
Middleware ResolveTenant:
1. Extrair tenant_id do JWT
2. Buscar tenant no banco da plataforma (com cache: 60s)
3. Verificar status:
   ├── active/trialing/past_due → continua
   └── qualquer outro → 403 Forbidden
4. Configurar conexao do tenant
5. Injetar TenantContext
```

---

## 7. Diagramas de Sequencia

### 7.1 Login Padrao de Tenant (sem MFA)

```
Cliente                    API Gateway              Auth Service           Platform DB            Tenant DB
  |                            |                        |                      |                     |
  |  POST /tenant/auth/login   |                        |                      |                     |
  |  {email, pass, slug}       |                        |                      |                     |
  |--------------------------->|                        |                      |                     |
  |                            |  Rate limit check      |                      |                     |
  |                            |----------------------->|                      |                     |
  |                            |                        |                      |                     |
  |                            |                        |  SELECT tenant       |                     |
  |                            |                        |  WHERE slug = ?      |                     |
  |                            |                        |--------------------->|                     |
  |                            |                        |  tenant {id, status} |                     |
  |                            |                        |<---------------------|                     |
  |                            |                        |                      |                     |
  |                            |                        |  Verify tenant       |                     |
  |                            |                        |  status = active     |                     |
  |                            |                        |                      |                     |
  |                            |                        |  Switch connection   |                     |
  |                            |                        |  to tenant DB        |                     |
  |                            |                        |                      |                     |
  |                            |                        |  SELECT user         |                     |
  |                            |                        |  WHERE email = ?     |                     |
  |                            |                        |--------------------------------------------->|
  |                            |                        |  user {id, hash,     |                     |
  |                            |                        |   status, role}      |                     |
  |                            |                        |<---------------------------------------------|
  |                            |                        |                      |                     |
  |                            |                        |  Verify password     |                     |
  |                            |                        |  (bcrypt)            |                     |
  |                            |                        |                      |                     |
  |                            |                        |  Generate JWT        |                     |
  |                            |                        |  (RS256 sign)        |                     |
  |                            |                        |                      |                     |
  |                            |                        |  Generate refresh    |                     |
  |                            |                        |  token (opaque)      |                     |
  |                            |                        |                      |                     |
  |                            |                        |  Store refresh hash  |                     |
  |                            |                        |--------------------------------------------->|
  |                            |                        |  OK                  |                     |
  |                            |                        |<---------------------------------------------|
  |                            |                        |                      |                     |
  |                            |                        |  Log: auth.login     |                     |
  |                            |                        |  .success            |                     |
  |                            |                        |                      |                     |
  |                            |  200 OK                |                      |                     |
  |                            |  {access, refresh,     |                      |                     |
  |                            |   user, tenant}        |                      |                     |
  |<---------------------------|                        |                      |                     |
  |                            |                        |                      |                     |
```

### 7.2 Login com MFA (TOTP)

```
Cliente                    API Gateway              Auth Service           Database
  |                            |                        |                      |
  |  POST /tenant/auth/login   |                        |                      |
  |  {email, pass, slug}       |                        |                      |
  |--------------------------->|                        |                      |
  |                            |  Forward               |                      |
  |                            |----------------------->|                      |
  |                            |                        |                      |
  |                            |                        |  [Resolve tenant]    |
  |                            |                        |  [Validate creds]    |
  |                            |                        |  [User has MFA]      |
  |                            |                        |                      |
  |                            |                        |  Generate            |
  |                            |                        |  mfa_required_token  |
  |                            |                        |  (JWT, 5min TTL)     |
  |                            |                        |                      |
  |  200 OK                    |                        |                      |
  |  {mfa_required: true,      |                        |                      |
  |   mfa_token: "..."}        |                        |                      |
  |<---------------------------|                        |                      |
  |                            |                        |                      |
  |  [Usuario abre app         |                        |                      |
  |   autenticador e           |                        |                      |
  |   obtem codigo TOTP]       |                        |                      |
  |                            |                        |                      |
  |  POST /tenant/auth/        |                        |                      |
  |       mfa/verify           |                        |                      |
  |  Authorization: Bearer     |                        |                      |
  |    {mfa_token}             |                        |                      |
  |  {code: "482915"}          |                        |                      |
  |--------------------------->|                        |                      |
  |                            |  Forward               |                      |
  |                            |----------------------->|                      |
  |                            |                        |                      |
  |                            |                        |  Validate            |
  |                            |                        |  mfa_token JWT       |
  |                            |                        |                      |
  |                            |                        |  Verify TOTP code    |
  |                            |                        |  against secret      |
  |                            |                        |--------------------->|
  |                            |                        |  user.totp_secret    |
  |                            |                        |<---------------------|
  |                            |                        |                      |
  |                            |                        |  Code valid!         |
  |                            |                        |                      |
  |                            |                        |  Revoke mfa_token    |
  |                            |                        |  Generate access +   |
  |                            |                        |  refresh tokens      |
  |                            |                        |                      |
  |                            |                        |  Store refresh hash  |
  |                            |                        |--------------------->|
  |                            |                        |  OK                  |
  |                            |                        |<---------------------|
  |                            |                        |                      |
  |  200 OK                    |                        |                      |
  |  {access_token,            |                        |                      |
  |   refresh_token,           |                        |                      |
  |   user, tenant}            |                        |                      |
  |<---------------------------|                        |                      |
  |                            |                        |                      |
```

### 7.3 Refresh Token Flow

```
Cliente                    API Gateway              Auth Service           Database            Redis/Cache
  |                            |                        |                      |                     |
  |  POST /tenant/auth/refresh |                        |                      |                     |
  |  {refresh_token: "old"}    |                        |                      |                     |
  |--------------------------->|                        |                      |                     |
  |                            |  Forward               |                      |                     |
  |                            |----------------------->|                      |                     |
  |                            |                        |                      |                     |
  |                            |                        |  Hash("old")         |                     |
  |                            |                        |                      |                     |
  |                            |                        |  SELECT refresh      |                     |
  |                            |                        |  WHERE hash = ?      |                     |
  |                            |                        |--------------------->|                     |
  |                            |                        |  {id, user_id,       |                     |
  |                            |                        |   used_at: null,     |                     |
  |                            |                        |   expires_at, ...}   |                     |
  |                            |                        |<---------------------|                     |
  |                            |                        |                      |                     |
  |                            |                        |  used_at == null?    |                     |
  |                            |                        |  YES → first use     |                     |
  |                            |                        |                      |                     |
  |                            |                        |  Verify tenant       |                     |
  |                            |                        |  status (cached)     |                     |
  |                            |                        |                      |                     |
  |                            |                        |  Mark "old" as used  |                     |
  |                            |                        |  (used_at = now())   |                     |
  |                            |                        |--------------------->|                     |
  |                            |                        |                      |                     |
  |                            |                        |  Generate new        |                     |
  |                            |                        |  access_token (JWT)  |                     |
  |                            |                        |                      |                     |
  |                            |                        |  Generate new        |                     |
  |                            |                        |  refresh_token       |                     |
  |                            |                        |                      |                     |
  |                            |                        |  Store new refresh   |                     |
  |                            |                        |  (parent = old.id)   |                     |
  |                            |                        |--------------------->|                     |
  |                            |                        |                      |                     |
  |                            |                        |  Log: auth.token     |                     |
  |                            |                        |  .refreshed          |                     |
  |                            |                        |                      |                     |
  |  200 OK                    |                        |                      |                     |
  |  {access_token: "new_a",   |                        |                      |                     |
  |   refresh_token: "new_r"}  |                        |                      |                     |
  |<---------------------------|                        |                      |                     |
  |                            |                        |                      |                     |
```

### 7.4 Deteccao de Roubo de Token (Reuse Detection)

```
                 Cenario: Atacante roubou refresh_token_1 antes do usuario usa-lo

Usuario                    Atacante                   Auth Service           Database            Redis/Cache
  |                            |                        |                      |                     |
  |                            |  POST /auth/refresh     |                      |                     |
  |                            |  {refresh: "token_1"}   |                      |                     |
  |                            |----------------------->|                      |                     |
  |                            |                        |  Lookup token_1      |                     |
  |                            |                        |--------------------->|                     |
  |                            |                        |  used_at: null ✓     |                     |
  |                            |                        |<---------------------|                     |
  |                            |                        |                      |                     |
  |                            |                        |  Mark token_1 used   |                     |
  |                            |                        |  Generate token_2    |                     |
  |                            |                        |--------------------->|                     |
  |                            |                        |                      |                     |
  |                            |  200 OK                |                      |                     |
  |                            |  {access, token_2}     |                      |                     |
  |                            |<-----------------------|                      |                     |
  |                            |                        |                      |                     |
  |                            |         ... tempo passa ...                   |                     |
  |                            |                        |                      |                     |
  |  POST /auth/refresh        |                        |                      |                     |
  |  {refresh: "token_1"}      |                        |                      |                     |
  |--------------------------->|                        |                      |                     |
  |                            |                        |  Lookup token_1      |                     |
  |                            |                        |--------------------->|                     |
  |                            |                        |  used_at: NOT NULL ✗ |                     |
  |                            |                        |<---------------------|                     |
  |                            |                        |                      |                     |
  |                            |                        |  *** REUSO ***       |                     |
  |                            |                        |  *** DETECTADO ***   |                     |
  |                            |                        |                      |                     |
  |                            |                        |  Invalidate ALL      |                     |
  |                            |                        |  refresh tokens      |                     |
  |                            |                        |  in chain            |                     |
  |                            |                        |--------------------->|                     |
  |                            |                        |  UPDATE SET          |                     |
  |                            |                        |  revoked_at=now()    |                     |
  |                            |                        |<---------------------|                     |
  |                            |                        |                      |                     |
  |                            |                        |  Revoke ALL active   |                     |
  |                            |                        |  access tokens       |                     |
  |                            |                        |  (add jtis to        |                     |
  |                            |                        |   revocation list)   |                     |
  |                            |                        |------------------------------------------->|
  |                            |                        |  SET revoked:{jti}   |                     |
  |                            |                        |<-------------------------------------------|
  |                            |                        |                      |                     |
  |                            |                        |  Log: auth.token     |                     |
  |                            |                        |  .chain_revoked      |                     |
  |                            |                        |  severity: critical  |                     |
  |                            |                        |                      |                     |
  |                            |                        |  Send security       |                     |
  |                            |                        |  notification email  |                     |
  |                            |                        |                      |                     |
  |  401 Unauthorized          |                        |                      |                     |
  |  {"error":                 |                        |                      |                     |
  |   "token_reuse_detected"}  |                        |                      |                     |
  |<---------------------------|                        |                      |                     |
  |                            |                        |                      |                     |
  |  [Usuario precisa fazer    |                        |                      |                     |
  |   login novamente]         |                        |                      |                     |
  |                            |                        |                      |                     |
  |                            |  [Atacante tenta usar  |                      |                     |
  |                            |   token_2 → REVOGADO]  |                      |                     |
  |                            |  401 Unauthorized      |                      |                     |
  |                            |                        |                      |                     |
```

### 7.5 Fluxo Completo de Requisicao Autenticada

```
Cliente                  Middleware Pipeline                                                 Controller
  |                          |                                                                  |
  |  GET /api/v1/tenant/     |                                                                  |
  |      reservations        |                                                                  |
  |  Authorization:          |                                                                  |
  |    Bearer {token}        |                                                                  |
  |------------------------->|                                                                  |
  |                          |                                                                  |
  |                    [1. RateLimiter]                                                          |
  |                          |  Check: request count < limit                                    |
  |                          |  If exceeded → 429 Too Many Requests                             |
  |                          |                                                                  |
  |                    [2. AuthenticateToken]                                                    |
  |                          |  Extract Bearer token                                            |
  |                          |  Verify JWT signature (RS256)                                    |
  |                          |  Check token_type = "access"                                     |
  |                          |  Check exp > now()                                               |
  |                          |  Check jti not in revocation list (Redis)                        |
  |                          |  If invalid → 401 Unauthorized                                   |
  |                          |                                                                  |
  |                    [3. ResolveTenant]                                                        |
  |                          |  Extract tenant_id from JWT claims                               |
  |                          |  Lookup tenant in platform DB (cached 60s)                       |
  |                          |  Verify tenant exists                                            |
  |                          |  Verify tenant status in (active, trialing, past_due)            |
  |                          |  Switch DB connection to tenant database                         |
  |                          |  Inject TenantContext into container                             |
  |                          |  If invalid → 403 Forbidden                                     |
  |                          |                                                                  |
  |                    [4. CheckSubscription]                                                    |
  |                          |  Verify subscription status (active, trialing, past_due)         |
  |                          |  If past_due → set read_only flag in context                     |
  |                          |  If invalid → 403 Forbidden                                     |
  |                          |                                                                  |
  |                    [5. AuthorizeAction]                                                      |
  |                          |  Policy evaluation (via $this->authorize() no controller)        |
  |                          |  Check role-based permission                                     |
  |                          |  Check contextual rules (owner, unit, etc.)                      |
  |                          |  Check feature flags                                             |
  |                          |  If denied → 403 Forbidden                                      |
  |                          |                                                                  |
  |                          |  All checks passed!                                              |
  |                          |-------------------------------------------------------->         |
  |                          |                                                        |         |
  |                          |                                                  [Execute]       |
  |                          |                                                  [Business]      |
  |                          |                                                  [Logic]         |
  |                          |                                                        |         |
  |  200 OK                  |                                                        |         |
  |  {data: [...]}           |<-------------------------------------------------------|         |
  |<-------------------------|                                                                  |
  |                          |                                                                  |
```

---

## 8. Auditoria de Autenticacao

### 8.1 Eventos de Auditoria

Todos os eventos de autenticacao sao registrados de forma imutavel para fins de seguranca, compliance e investigacao.

| Evento                        | Descricao                                                   | Severity   |
|-------------------------------|-------------------------------------------------------------|------------|
| `auth.login.success`          | Login realizado com sucesso                                 | info       |
| `auth.login.failed`           | Tentativa de login com credenciais invalidas                | warning    |
| `auth.login.mfa_required`     | Login parcial, aguardando verificacao MFA                   | info       |
| `auth.mfa.verified`           | Codigo MFA verificado com sucesso                           | info       |
| `auth.mfa.failed`             | Codigo MFA invalido                                         | warning    |
| `auth.mfa.enabled`            | MFA habilitado na conta                                     | info       |
| `auth.mfa.disabled`           | MFA desabilitado na conta                                   | warning    |
| `auth.mfa.setup_initiated`    | Setup de MFA iniciado                                       | info       |
| `auth.token.refreshed`        | Token de acesso renovado via refresh token                  | info       |
| `auth.token.revoked`          | Token revogado (logout ou acao administrativa)              | info       |
| `auth.token.chain_revoked`    | Cadeia inteira de tokens invalidada (deteccao de reuso)     | critical   |
| `auth.client.token_issued`    | Token emitido para client credentials                       | info       |
| `auth.logout`                 | Logout realizado                                            | info       |
| `auth.password.reset_requested`| Solicitacao de reset de senha                              | info       |
| `auth.password.reset`         | Senha alterada via reset                                    | warning    |
| `auth.register.success`       | Registro de novo usuario via convite                        | info       |
| `auth.account.locked`         | Conta bloqueada por excesso de tentativas                   | warning    |
| `auth.account.unlocked`       | Conta desbloqueada (automatico ou manual)                   | info       |

### 8.2 Estrutura do Log de Auditoria

Cada evento de autenticacao registra os seguintes campos:

```json
{
  "id": "log_a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "event": "auth.login.success",
  "severity": "info",
  "actor_id": "550e8400-e29b-41d4-a716-446655440000",
  "actor_type": "platform_user",
  "actor_email": "admin@condominium-events.com",
  "actor_role": "platform_admin",
  "tenant_id": null,
  "ip_address": "203.0.113.42",
  "user_agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
  "correlation_id": "req_f1e2d3c4-b5a6-7890-1234-567890abcdef",
  "request_id": "req_a1b2c3d4-e5f6-7890-abcd-ef1234567890",
  "metadata": {
    "mfa_used": true,
    "token_jti": "tok_a1b2c3d4-e5f6-7890-abcd-ef1234567890",
    "login_method": "email_password"
  },
  "timestamp": "2025-02-10T10:30:00.000Z",
  "created_at": "2025-02-10T10:30:00.000Z"
}
```

### 8.3 Campos do Log de Auditoria

| Campo            | Tipo     | Descricao                                                              |
|------------------|----------|------------------------------------------------------------------------|
| `id`             | uuid     | Identificador unico do registro de auditoria                          |
| `event`          | string   | Nome do evento (padrao: `{domain}.{action}.{result}`)                 |
| `severity`       | string   | Nivel de severidade: `info`, `warning`, `critical`                    |
| `actor_id`       | uuid?    | UUID do usuario que realizou a acao (null se anonimo)                 |
| `actor_type`     | string   | Tipo do ator: `platform_user`, `tenant_user`, `service`, `anonymous`  |
| `actor_email`    | string?  | Email do ator (para facilitar investigacao)                           |
| `actor_role`     | string?  | Role do ator no momento da acao                                       |
| `tenant_id`      | uuid?    | UUID do tenant (null para acoes de plataforma)                        |
| `ip_address`     | string   | Endereco IP do cliente (IPv4 ou IPv6)                                 |
| `user_agent`     | string   | Header User-Agent da requisicao                                       |
| `correlation_id` | uuid     | ID de correlacao para rastrear fluxos entre servicos                  |
| `request_id`     | uuid     | ID unico da requisicao HTTP                                          |
| `metadata`       | json     | Dados adicionais especificos do evento                                |
| `timestamp`      | datetime | Momento exato do evento (UTC, ISO 8601)                               |

### 8.4 Armazenamento

| Contexto         | Tabela                  | Banco                                       |
|------------------|-------------------------|---------------------------------------------|
| Plataforma       | `platform_audit_logs`   | Banco platform (global)                     |
| Tenant           | `tenant_audit_logs`     | Banco do tenant (isolado)                   |

> **Nota:** Eventos de autenticacao de tenant sao armazenados no banco do tenant para manter o isolamento de dados. Eventos criticos (como `auth.token.chain_revoked`) sao tambem registrados no banco da plataforma para visibilidade da equipe de suporte.

### 8.5 Retencao e Compliance

| Tipo de Log                    | Retencao Minima | Justificativa                               |
|--------------------------------|-----------------|---------------------------------------------|
| Login success/failed           | 1 ano           | Auditoria de seguranca                      |
| MFA events                     | 1 ano           | Auditoria de seguranca                      |
| Token events                   | 90 dias         | Investigacao de incidentes                   |
| Password events                | 2 anos          | Compliance e recuperacao                     |
| Account lock/unlock            | 1 ano           | Auditoria de seguranca                      |
| Security incidents (critical)  | 5 anos          | Compliance LGPD / investigacao forense      |

---

## 9. Headers de Seguranca

### 9.1 Headers de Requisicao

| Header                | Obrigatorio | Descricao                                                        |
|-----------------------|:-----------:|------------------------------------------------------------------|
| `Authorization`       | Sim*        | Bearer token para endpoints autenticados. Formato: `Bearer {token}` |
| `Content-Type`        | Sim         | Sempre `application/json` para requests com body                 |
| `Accept`              | Sim         | Sempre `application/json`                                        |
| `X-Request-ID`       | Nao         | UUID de correlacao gerado pelo cliente. Se ausente, o servidor gera um |
| `X-Forwarded-For`    | Nao         | IP real do cliente quando atras de proxy/load balancer           |
| `X-Forwarded-Proto`  | Nao         | Protocolo original (https) quando atras de proxy                 |

> *O header `Authorization` e obrigatorio para todos os endpoints protegidos. Endpoints publicos (login, register, forgot-password, reset-password, refresh) nao exigem este header.

### 9.2 Headers de Resposta

| Header                        | Valor                              | Descricao                                                |
|-------------------------------|-------------------------------------|----------------------------------------------------------|
| `X-Request-ID`               | UUID                                | ID de correlacao da requisicao (eco do request ou gerado)|
| `X-RateLimit-Limit`          | Numero                              | Limite de requisicoes na janela atual                    |
| `X-RateLimit-Remaining`      | Numero                              | Requisicoes restantes na janela                          |
| `X-RateLimit-Reset`          | Timestamp Unix                      | Quando a janela de rate limit reseta                     |
| `Retry-After`                | Segundos                            | Presente apenas em respostas 429                         |
| `X-Content-Type-Options`     | `nosniff`                           | Previne MIME type sniffing                               |
| `X-Frame-Options`            | `DENY`                              | Previne clickjacking (mesmo sendo API)                   |
| `Strict-Transport-Security`  | `max-age=31536000; includeSubDomains` | Forca HTTPS                                            |
| `Cache-Control`              | `no-store, no-cache, must-revalidate` | Previne cache de dados sensiveis                       |
| `Pragma`                     | `no-cache`                          | Compatibilidade HTTP/1.0                                 |

### 9.3 Tratamento de X-Forwarded-For

Quando a API esta atras de um proxy reverso ou load balancer, o IP real do cliente e extraido do header `X-Forwarded-For`:

```
Regras:
1. Confiar apenas em proxies conhecidos (lista de IPs confiaveis)
2. Extrair o IP mais a esquerda da cadeia X-Forwarded-For
3. Se X-Forwarded-For ausente ou proxy nao confiavel → usar REMOTE_ADDR
4. IP extraido e usado para:
   ├── Rate limiting
   ├── Audit logs
   ├── Deteccao de anomalias
   └── Geo-blocking (se implementado)
```

**Configuracao de Proxies Confiaveis (Laravel):**

```php
// config/trustedproxies.php ou middleware TrustProxies
'proxies' => [
    '10.0.0.0/8',       // Rede interna
    '172.16.0.0/12',     // Docker
    '192.168.0.0/16',    // Rede local
],
'headers' => [
    'X-Forwarded-For',
    'X-Forwarded-Proto',
    'X-Forwarded-Port',
],
```

### 9.4 Resolucao do Tenant (Token vs Header)

**Regra fundamental:** O tenant e resolvido **exclusivamente** a partir da claim `tenant_id` do JWT token.

```
Header X-Tenant-Slug:
├── NAO e usado para resolucao de tenant em rotas autenticadas
├── Pode ser usado opcionalmente para contexto em logs
├── Se presente, e validado contra o tenant_id do token
│   └── Se diferente → 403 ("Tenant do token nao corresponde ao header")
└── Nunca substitui a claim tenant_id do token
```

Essa abordagem elimina possibilidades de manipulacao de tenant via headers.

---

## 10. Middleware Pipeline

### 10.1 Visao Geral do Pipeline

```
Request HTTP
    │
    ▼
[RateLimiter] → [AuthenticateToken] → [ResolveTenant] → [CheckSubscription] → [AuthorizeAction] → [Controller]
    │                  │                     │                   │                    │                   │
    │                  │                     │                   │                    │                   │
   429               401                   403                 403                  403              200/201/204
 Too Many         Unauthenticated       Forbidden           Forbidden            Forbidden          Success
 Requests
```

### 10.2 Descricao Detalhada de Cada Middleware

#### 10.2.1 RateLimiter

**Responsabilidade:** Proteger contra abuso e ataques de forca bruta.

```
Execucao:
1. Identificar chave de rate limit
   ├── Para login: "auth:{ip}:{endpoint}" E "auth:{email}:{endpoint}"
   ├── Para refresh: "refresh:{ip}"
   └── Para demais: "api:{ip}" ou "api:{user_id}"
2. Buscar contador no Redis
3. Se contador >= limite → 429 Too Many Requests
4. Incrementar contador
5. Adicionar headers de rate limit na resposta
6. Passar para proximo middleware
```

**Configuracao por grupo de rotas:**

| Grupo              | Limite | Janela     | Chave                       |
|--------------------|--------|------------|-----------------------------|
| Auth (login)       | 5/min  | 1 minuto   | `auth:{ip}:{endpoint}`      |
| Auth (MFA)         | 5/min  | 1 minuto   | `mfa:{ip}:{user_id}`        |
| Auth (refresh)     | 10/min | 1 minuto   | `refresh:{ip}`              |
| Auth (password)    | 3/15min| 15 minutos | `password:{email}`          |
| API (tenant read)  | 60/min | 1 minuto   | `api:{user_id}`             |
| API (tenant write) | 30/min | 1 minuto   | `api:{user_id}:write`       |
| API (platform)     | 120/min| 1 minuto   | `api:{user_id}:platform`    |

#### 10.2.2 AuthenticateToken

**Responsabilidade:** Validar o JWT e identificar o usuario.

```
Execucao:
1. Extrair token do header Authorization
   ├── Formato esperado: "Bearer {token}"
   ├── Se header ausente → 401 ("Token nao fornecido")
   └── Se formato invalido → 401 ("Formato de token invalido")
2. Decodificar JWT (sem validar assinatura ainda)
   └── Extrair header "alg" e "kid"
3. Verificar algoritmo
   ├── Se alg != "RS256" → 401 ("Algoritmo nao suportado")
   └── Se alg == "none" → 401 (rejeitar imediatamente)
4. Buscar chave publica pelo kid (JWKS)
5. Verificar assinatura do JWT com a chave publica
   └── Se invalida → 401 ("Token invalido")
6. Validar claims padrao:
   ├── exp: token nao expirado (exp > now())
   │   └── Se expirado → 401 ("Token expirado")
   ├── iat: emitido no passado (iat <= now())
   ├── iss: emissor correto (iss == "condominium-events-api")
   │   └── Se diferente → 401 ("Emissor invalido")
   ├── aud: audiencia correta
   └── token_type: tipo esperado para a rota
       └── Se mfa_required em rota normal → 401 ("Token MFA nao aceito nesta rota")
7. Verificar JTI na revocation list (Redis)
   ├── Buscar "revoked_token:{jti}" no Redis
   └── Se encontrado → 401 ("Token revogado")
8. Carregar usuario a partir do sub (user_id)
   └── Se nao encontrado → 401 ("Usuario nao encontrado")
9. Verificar status do usuario
   ├── active → continua
   └── inactive/locked → 401 ("Conta desativada ou bloqueada")
10. Injetar usuario autenticado no request/container
11. Passar para proximo middleware
```

#### 10.2.3 ResolveTenant

**Responsabilidade:** Resolver o tenant a partir do token e configurar a conexao do banco de dados.

```
Execucao:
1. Extrair tenant_id da claim do JWT
   ├── Se null (token de plataforma) → pular middleware (rota de plataforma)
   └── Se presente → continua
2. Buscar tenant no banco da plataforma (com cache de 60s)
   ├── Cache key: "tenant:{tenant_id}"
   └── Se nao encontrado → 403 ("Tenant nao encontrado")
3. Verificar status do tenant
   ├── active    → continua
   ├── trialing  → continua
   ├── past_due  → continua (flag read_only)
   ├── suspended → 403 ("Condominio suspenso")
   ├── canceled  → 403 ("Condominio cancelado")
   ├── archived  → 403 ("Condominio arquivado")
   └── provisioning / pending_deletion → 403 ("Condominio indisponivel")
4. Configurar conexao dinamica para o banco do tenant
   ├── database_name: "tenant_{tenant_slug}"
   └── Testar conexao (fail fast se banco inacessivel)
5. Construir e injetar TenantContext no container
   ├── tenant_id
   ├── tenant_slug
   ├── tenant_name
   ├── tenant_status
   ├── subscription_status
   ├── database_name
   ├── is_read_only (true se past_due)
   └── resolved_at
6. Passar para proximo middleware
```

#### 10.2.4 CheckSubscription

**Responsabilidade:** Verificar se a assinatura do tenant esta em dia e aplicar restricoes.

```
Execucao:
1. Obter TenantContext do container
   └── Se nao existe (rota de plataforma) → pular
2. Buscar assinatura ativa do tenant (com cache)
   └── Se nenhuma assinatura → 403 ("Sem assinatura ativa")
3. Verificar status da assinatura
   ├── active    → continua (acesso total)
   ├── trialing  → continua (acesso total)
   ├── past_due  → continua (restricoes aplicadas)
   │   ├── Se metodo HTTP e POST/PUT/PATCH/DELETE:
   │   │   └── Verificar se acao e permitida em modo read_only
   │   │       ├── Refresh token → permitido (nao e acao de dominio)
   │   │       ├── Logout → permitido
   │   │       └── Demais acoes de escrita → 403 ("Assinatura em atraso. Acoes de escrita bloqueadas.")
   │   └── Se metodo HTTP e GET → continua
   ├── canceled → 403 ("Assinatura cancelada")
   └── expired  → 403 ("Assinatura expirada")
4. Verificar limites do plano (se aplicavel)
   ├── Quota de usuarios
   ├── Quota de reservas por mes
   └── Feature flags do plano
5. Passar para proximo middleware
```

#### 10.2.5 AuthorizeAction

**Responsabilidade:** Verificar se o usuario tem permissao para realizar a acao solicitada no recurso.

```
Execucao:
1. Este middleware e executado DENTRO do controller via $this->authorize()
2. Laravel resolve a Policy correspondente ao recurso
3. A Policy avalia:
   ├── Role do usuario permite a acao?
   │   └── Se nao → 403 ("Acao nao autorizada")
   ├── Feature flag habilitada para o tenant?
   │   └── Se nao → 403 ("Funcionalidade nao disponivel no seu plano")
   ├── Limite de uso atingido?
   │   └── Se sim → 429 ("Limite de uso atingido")
   └── Regras contextuais satisfeitas?
       ├── Propriedade do recurso (ex: propria reserva)
       ├── Escopo do tenant
       ├── Estado do recurso
       └── Se nao → 403 ("Acao nao permitida neste contexto")
4. Se autorizado → executar acao
5. Se negado → retornar erro com detalhes
```

### 10.3 Aplicacao nas Rotas (Laravel)

```php
// Rotas de autenticacao (publicas, apenas rate limit)
Route::middleware(['throttle:auth'])->group(function () {
    Route::post('/platform/auth/login', [PlatformAuthController::class, 'login']);
    Route::post('/tenant/auth/login', [TenantAuthController::class, 'login']);
    Route::post('/tenant/auth/register', [TenantAuthController::class, 'register']);
    Route::post('/{context}/auth/forgot-password', [PasswordResetController::class, 'forgot']);
    Route::post('/{context}/auth/reset-password', [PasswordResetController::class, 'reset']);
    Route::post('/{context}/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/auth/token', [ClientCredentialsController::class, 'token']);
});

// Rotas de plataforma (autenticadas, sem tenant)
Route::middleware([
    'auth:jwt',          // AuthenticateToken
    'throttle:platform', // RateLimiter
])->prefix('platform')->group(function () {
    Route::post('/auth/logout', [PlatformAuthController::class, 'logout']);
    Route::post('/auth/mfa/verify', [MfaController::class, 'verify']);
    Route::post('/auth/mfa/setup', [MfaController::class, 'setup']);
    Route::post('/auth/mfa/setup/confirm', [MfaController::class, 'confirm']);
    // ... demais rotas de plataforma
});

// Rotas de tenant (autenticadas, com resolucao de tenant)
Route::middleware([
    'auth:jwt',            // AuthenticateToken
    'tenant.resolve',      // ResolveTenant
    'subscription.check',  // CheckSubscription
    'throttle:tenant',     // RateLimiter
])->prefix('tenant')->group(function () {
    Route::post('/auth/logout', [TenantAuthController::class, 'logout']);
    Route::post('/auth/mfa/verify', [MfaController::class, 'verify']);
    Route::post('/auth/mfa/setup', [MfaController::class, 'setup']);
    Route::post('/auth/mfa/setup/confirm', [MfaController::class, 'confirm']);
    // ... demais rotas de tenant (reservas, espacos, etc.)
});
```

---

## 11. Regras de Senha

### 11.1 Politica de Senha

| Requisito                              | Valor                                |
|----------------------------------------|--------------------------------------|
| Comprimento minimo                     | 8 caracteres                         |
| Comprimento maximo                     | 128 caracteres                       |
| Caractere maiusculo obrigatorio        | Sim (pelo menos 1)                   |
| Caractere minusculo obrigatorio        | Sim (pelo menos 1)                   |
| Digito numerico obrigatorio            | Sim (pelo menos 1)                   |
| Caractere especial obrigatorio         | Nao (recomendado, nao obrigatorio)   |
| Historico de senhas                    | Ultimas 5 senhas nao podem ser reusadas |
| Verificacao de senhas comprometidas    | Sim (via Have I Been Pwned API, opcional) |

### 11.2 Hashing

| Parametro        | Valor                                          |
|------------------|-------------------------------------------------|
| Algoritmo        | bcrypt                                          |
| Custo (rounds)   | 12 (padrao Laravel, ajustavel conforme hardware)|

---

## 12. Consideracoes de Implementacao

### 12.1 Estrutura de Pastas (Clean Architecture)

```
src/
├── Domain/
│   └── Auth/
│       ├── Entities/
│       │   ├── User.php
│       │   └── RefreshToken.php
│       ├── ValueObjects/
│       │   ├── Email.php
│       │   ├── Password.php
│       │   ├── TokenJti.php
│       │   └── TotpSecret.php
│       ├── Events/
│       │   ├── UserLoggedIn.php
│       │   ├── UserLoggedOut.php
│       │   ├── MfaVerified.php
│       │   ├── TokenRefreshed.php
│       │   ├── TokenChainRevoked.php
│       │   ├── PasswordReset.php
│       │   └── AccountLocked.php
│       └── Exceptions/
│           ├── InvalidCredentialsException.php
│           ├── AccountLockedException.php
│           ├── AccountDisabledException.php
│           ├── MfaRequiredException.php
│           ├── InvalidMfaCodeException.php
│           ├── TokenReusedException.php
│           └── TenantInactiveException.php
│
├── Application/
│   └── Auth/
│       ├── UseCases/
│       │   ├── LoginPlatformUser/
│       │   │   ├── LoginPlatformUserUseCase.php
│       │   │   ├── LoginPlatformUserInput.php
│       │   │   └── LoginPlatformUserOutput.php
│       │   ├── LoginTenantUser/
│       │   │   ├── LoginTenantUserUseCase.php
│       │   │   ├── LoginTenantUserInput.php
│       │   │   └── LoginTenantUserOutput.php
│       │   ├── VerifyMfa/
│       │   │   ├── VerifyMfaUseCase.php
│       │   │   ├── VerifyMfaInput.php
│       │   │   └── VerifyMfaOutput.php
│       │   ├── RefreshToken/
│       │   │   ├── RefreshTokenUseCase.php
│       │   │   ├── RefreshTokenInput.php
│       │   │   └── RefreshTokenOutput.php
│       │   ├── Logout/
│       │   │   └── LogoutUseCase.php
│       │   ├── RegisterResident/
│       │   │   ├── RegisterResidentUseCase.php
│       │   │   ├── RegisterResidentInput.php
│       │   │   └── RegisterResidentOutput.php
│       │   ├── ForgotPassword/
│       │   │   └── ForgotPasswordUseCase.php
│       │   ├── ResetPassword/
│       │   │   ├── ResetPasswordUseCase.php
│       │   │   └── ResetPasswordInput.php
│       │   └── SetupMfa/
│       │       ├── InitiateMfaSetupUseCase.php
│       │       └── ConfirmMfaSetupUseCase.php
│       ├── Contracts/
│       │   ├── TokenServiceInterface.php
│       │   ├── RefreshTokenRepositoryInterface.php
│       │   ├── MfaServiceInterface.php
│       │   ├── PasswordResetRepositoryInterface.php
│       │   └── RevocationListInterface.php
│       └── DTOs/
│           ├── AuthTokensDTO.php
│           ├── UserProfileDTO.php
│           └── TenantInfoDTO.php
│
├── Infrastructure/
│   └── Auth/
│       ├── Services/
│       │   ├── JwtTokenService.php
│       │   ├── TotpMfaService.php
│       │   └── PasswordHashService.php
│       ├── Repositories/
│       │   ├── EloquentRefreshTokenRepository.php
│       │   └── EloquentPasswordResetRepository.php
│       ├── Cache/
│       │   └── RedisRevocationList.php
│       └── Providers/
│           └── AuthServiceProvider.php
│
└── Interface/
    └── Http/
        ├── Controllers/
        │   └── Auth/
        │       ├── PlatformAuthController.php
        │       ├── TenantAuthController.php
        │       ├── MfaController.php
        │       ├── PasswordResetController.php
        │       └── ClientCredentialsController.php
        ├── Middleware/
        │   ├── AuthenticateToken.php
        │   ├── ResolveTenant.php
        │   ├── CheckSubscription.php
        │   └── RateLimiterMiddleware.php
        └── Requests/
            └── Auth/
                ├── LoginRequest.php
                ├── TenantLoginRequest.php
                ├── MfaVerifyRequest.php
                ├── RefreshTokenRequest.php
                ├── RegisterResidentRequest.php
                ├── ForgotPasswordRequest.php
                └── ResetPasswordRequest.php
```

### 12.2 Dependencias Sugeridas

| Pacote                        | Uso                                              |
|-------------------------------|--------------------------------------------------|
| `lcobucci/jwt`                | Geracao e validacao de JWT (RS256)                |
| `pragmarx/google2fa-laravel`  | Implementacao TOTP (Google Authenticator)         |
| `bacon/bacon-qr-code`         | Geracao de QR Codes para setup MFA               |
| `predis/predis` ou `phpredis` | Redis client para revocation list e rate limiting |

### 12.3 Variaveis de Ambiente

```env
# JWT
JWT_PRIVATE_KEY_PATH=/path/to/private.pem
JWT_PUBLIC_KEY_PATH=/path/to/public.pem
JWT_ALGORITHM=RS256
JWT_ACCESS_TTL=900         # 15 minutos em segundos
JWT_REFRESH_TTL=604800     # 7 dias em segundos
JWT_MFA_TTL=300            # 5 minutos em segundos
JWT_CLIENT_TTL=3600        # 1 hora em segundos
JWT_ISSUER=condominium-events-api
JWT_AUDIENCE=condominium-events-client

# MFA
MFA_ISSUER=CondominiumEvents
MFA_DIGITS=6
MFA_PERIOD=30
MFA_ALGORITHM=SHA1
MFA_WINDOW=1               # Tolerancia de +/- 1 periodo

# Lockout
AUTH_MAX_ATTEMPTS=10
AUTH_LOCKOUT_MINUTES=30
AUTH_MFA_MAX_ATTEMPTS=5

# Rate Limiting
AUTH_RATE_LIMIT_LOGIN=5
AUTH_RATE_LIMIT_WINDOW=60
AUTH_RATE_LIMIT_PASSWORD=3
AUTH_RATE_LIMIT_PASSWORD_WINDOW=900

# Password Reset
PASSWORD_RESET_TTL=3600    # 1 hora em segundos
PASSWORD_HISTORY_COUNT=5

# Invitation
INVITATION_TTL=604800      # 7 dias em segundos
```

---

## 13. Glossario

| Termo                    | Definicao                                                                    |
|--------------------------|------------------------------------------------------------------------------|
| **Access Token**         | JWT de curta duracao usado para autenticar requisicoes a API                 |
| **Refresh Token**        | Token opaco de longa duracao usado para obter novos access tokens            |
| **MFA Required Token**   | JWT intermediario emitido quando MFA e necessario, antes da verificacao      |
| **TOTP**                 | Time-based One-Time Password. Codigo de 6 digitos que muda a cada 30 segundos |
| **JTI**                  | JWT ID. Identificador unico de cada token, usado para revogacao             |
| **Revocation List**      | Lista de JTIs de tokens revogados, mantida em Redis com TTL                 |
| **Token Rotation**       | Pratica de emitir novo refresh token a cada uso, invalidando o anterior     |
| **Reuse Detection**      | Mecanismo que detecta quando um refresh token ja utilizado e apresentado novamente |
| **Token Chain**          | Sequencia de refresh tokens vinculados (cada um aponta para o anterior)     |
| **RS256**                | RSA Signature with SHA-256. Algoritmo assimetrico para assinatura de JWT    |
| **JWKS**                 | JSON Web Key Set. Endpoint que publica chaves publicas para verificacao de JWT |
| **Tenant Context**       | Objeto que contem informacoes do tenant resolvido para a requisicao atual   |
| **Grant Type**           | Tipo de concessao OAuth que define como o cliente obtem tokens              |
| **Client Credentials**   | Grant type usado para autenticacao service-to-service sem usuario humano    |
| **PendingRegistration** | Registro temporario criado durante o self-service. Armazena dados do futuro tenant ate verificacao do email |
| **Verification Token**  | Token de 64 bytes usado para verificar o email do administrador durante o registro self-service |
| **Bcrypt**               | Algoritmo de hash de senha adaptativo com custo configuravel                |

---

## 14. Status

**Documento ATIVO** — Referencia definitiva para implementacao dos fluxos de autenticacao.

| Campo              | Valor          |
|--------------------|----------------|
| Ultima atualizacao | 2025-02-10     |
| Versao             | 1.0.0          |
| Responsavel        | Equipe Backend |
