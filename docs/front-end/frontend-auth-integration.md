# Especificacao Front-End — Autenticacao e Integracao com a API

## Status do Documento

**Status:** Ativo\
**Ultima atualizacao:** 2026-02-10\
**Versao da API:** v1\
**Referencia:** [auth-flows.md](../security/auth-flows.md), [authorization-matrix.md](../security/authorization-matrix.md), [api-design-guidelines.md](../api/api-design-guidelines.md)

---

## 1. Visao Geral

Este documento define **todas as regras, fluxos e requisitos** que o front-end deve implementar para se integrar corretamente com a API do Condominium Events Manager.

A API e **API-first**, sem frontend acoplado. Toda comunicacao ocorre via tokens Bearer enviados nos headers HTTP. O front-end e um **cliente consumidor** da API e deve respeitar integralmente os contratos aqui descritos.

### 1.1 Principios Fundamentais

| Principio | Descricao |
|-----------|-----------|
| **Stateless** | A API nao mantem sessao. Todo estado de autenticacao e mantido pelo front-end via tokens. |
| **JSON only** | Todas as requisicoes e respostas usam `application/json`. |
| **Bearer Token** | Autenticacao via header `Authorization: Bearer {access_token}`. |
| **Tenant via Token** | O tenant e resolvido pela claim `tenant_id` no JWT. O front-end **nao envia** tenant por header em rotas autenticadas. |
| **MFA obrigatorio** | Algumas roles exigem MFA — o front-end deve implementar o fluxo completo. |

### 1.2 Base URLs

```
Platform API:  /api/v1/platform/{resource}
Tenant API:    /api/v1/tenant/{resource}
Auth:          /api/v1/{context}/auth/{action}
```

| Grupo | Quem usa | Autenticacao |
|-------|----------|--------------|
| **Platform** | Admins da plataforma | JWT com role `platform_*` |
| **Tenant** | Sindico, administradora, condomino, funcionario | JWT com `tenant_id` valido |

---

## 2. Dois Contextos de Autenticacao

O sistema possui **dois contextos completamente separados**. O front-end deve tratar cada um de forma independente.

| Contexto | Base URL Auth | Quem | Roles |
|----------|---------------|------|-------|
| **Plataforma** | `/api/v1/platform/auth/` | Admins SaaS | `platform_owner`, `platform_admin`, `platform_support` |
| **Tenant** | `/api/v1/tenant/auth/` | Usuarios do condominio | `sindico`, `administradora`, `condomino`, `funcionario` |

**Regra critica:** Um token de plataforma **nunca** e aceito em rotas de tenant e vice-versa. O front-end deve manter esses contextos separados.

---

## 3. Fluxos de Autenticacao

### 3.1 Login de Usuario de Plataforma

**Endpoint:** `POST /api/v1/platform/auth/login`

**Request:**

```json
{
  "email": "admin@condominium-events.com",
  "password": "s3cur3P@ssw0rd"
}
```

**Validacao no front-end (antes de enviar):**

| Campo | Regras |
|-------|--------|
| `email` | obrigatorio, email valido, max 255 chars |
| `password` | obrigatorio, min 8 chars |

**Fluxo de decisao apos resposta:**

```
Response 200:
├── mfa_required === true?
│   ├── SIM → Redirecionar para tela de MFA
│   │         Armazenar mfa_token temporariamente (memoria, NAO localStorage)
│   │         mfa_token expira em 5 minutos — exibir countdown
│   └── NAO → Login completo
│             Armazenar access_token e refresh_token
│             Redirecionar para dashboard
│
Response 401: "Email ou senha incorretos" → exibir mensagem generica
Response 403: Verificar error code:
├── account_disabled → "Sua conta foi desativada"
├── account_locked → "Conta bloqueada. Tente em X minutos" (usar retry_after se disponivel)
Response 422: Erros de validacao → exibir por campo
Response 429: Rate limit → "Muitas tentativas. Aguarde X segundos" (usar retry_after)
```

**Response (sem MFA):**

```json
{
  "data": {
    "access_token": "eyJhbGci...",
    "refresh_token": "dGhpcyBp...",
    "token_type": "bearer",
    "expires_in": 900,
    "user": {
      "id": "uuid",
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

**Response (MFA pendente):**

```json
{
  "data": {
    "mfa_required": true,
    "mfa_token": "eyJhbGci...",
    "mfa_token_expires_in": 300,
    "mfa_methods": ["totp"]
  }
}
```

---

### 3.2 Login de Usuario de Tenant

**Endpoint:** `POST /api/v1/tenant/auth/login`

**Request:**

```json
{
  "email": "joao.silva@email.com",
  "password": "m1nh@Senh@Segur@",
  "tenant_slug": "condominio-sol"
}
```

**Validacao no front-end:**

| Campo | Regras |
|-------|--------|
| `email` | obrigatorio, email valido, max 255 chars |
| `password` | obrigatorio, min 8 chars |
| `tenant_slug` | obrigatorio, alfanumerico + hifens, max 100 chars |

**Campo `tenant_slug`:**
- O front-end deve coletar o slug do condominio do usuario.
- Opcoes de UX:
  - Campo de texto onde o usuario digita o slug
  - Subdominio na URL (ex: `condominio-sol.app.com` → extrair slug)
  - Selecao de condominio em lista (requer endpoint publico de busca)

**Fluxo de decisao apos resposta:**

Mesmo fluxo do login de plataforma, com erros adicionais:

| Codigo | Error Code | Acao no Front-End |
|--------|------------|-------------------|
| 404 | `tenant_not_found` | "Condominio nao encontrado. Verifique o nome." |
| 403 | `tenant_suspended` | "Condominio suspenso. Entre em contato com o suporte." |
| 403 | `tenant_canceled` | "Condominio cancelado." |
| 403 | `tenant_provisioning` | "Condominio em configuracao. Aguarde a ativacao." |
| 403 | `subscription_invalid` | "Assinatura expirada ou cancelada." |
| 403 | `account_disabled` | "Sua conta foi desativada neste condominio." |

**Response (sem MFA) inclui dados do tenant:**

```json
{
  "data": {
    "access_token": "eyJhbGci...",
    "refresh_token": "dGhpcyBp...",
    "token_type": "bearer",
    "expires_in": 900,
    "user": {
      "id": "uuid",
      "name": "Joao Silva",
      "email": "joao.silva@email.com",
      "role": "sindico",
      "mfa_enabled": true,
      "unit": {
        "id": "uuid",
        "block": "A",
        "number": "101"
      }
    },
    "tenant": {
      "id": "uuid",
      "name": "Condominio Sol",
      "slug": "condominio-sol",
      "status": "active",
      "subscription_status": "active",
      "plan": "professional"
    }
  }
}
```

**O front-end deve armazenar:**
- `access_token` e `refresh_token` (ver secao 4)
- Dados do `user` (role, unit, id)
- Dados do `tenant` (id, slug, name, status, subscription_status, plan)

---

### 3.3 Verificacao MFA (TOTP)

**Endpoint:** `POST /api/v1/{context}/auth/mfa/verify`

Onde `{context}` e `platform` ou `tenant`, conforme o login original.

**Headers:**
```
Authorization: Bearer {mfa_token}
Content-Type: application/json
```

**Request:**

```json
{
  "code": "482915"
}
```

**Validacao no front-end:**

| Campo | Regras |
|-------|--------|
| `code` | obrigatorio, exatamente 6 digitos numericos |

**Fluxo de UX:**

```
1. Exibir campo para codigo de 6 digitos
2. Exibir countdown do tempo restante (mfa_token expira em 5 min)
3. Ao expirar → redirecionar para login com mensagem "Tempo esgotado"
4. Permitir colar codigo (clipboard)
5. Auto-submit quando 6 digitos forem preenchidos (opcional, boa UX)
```

**Respostas:**

| Codigo | Error Code | Acao no Front-End |
|--------|------------|-------------------|
| 200 | — | Login completo. Armazenar tokens. Redirecionar para dashboard. |
| 401 | `invalid_mfa_token` | "Sessao expirada. Faca login novamente." → redirecionar para login |
| 401 | `invalid_mfa_code` | "Codigo invalido. Tentativas restantes: X" → exibir tentativas |
| 401 | `mfa_code_reused` | "Codigo ja utilizado. Aguarde o proximo codigo." |
| 403 | `account_locked` | "Conta bloqueada por excesso de tentativas. Tente em 30 minutos." |

---

### 3.4 Setup MFA (Primeira Configuracao)

Obrigatorio para: `platform_owner`, `platform_admin`, `sindico`, `administradora`.
Opcional para: `platform_support`, `condomino`, `funcionario`.

**Etapa 1 — Gerar Segredo:**

```
POST /api/v1/{context}/auth/mfa/setup
Authorization: Bearer {access_token}
```

**Response:**

```json
{
  "data": {
    "secret": "JBSWY3DPEHPK3PXP",
    "otpauth_uri": "otpauth://totp/CondominiumEvents:admin@email.com?secret=JBSWY3DPEHPK3PXP&issuer=CondominiumEvents&algorithm=SHA1&digits=6&period=30",
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

**Requisitos de UX:**

1. Exibir QR Code (usar `qr_code_base64` como `src` de uma `<img>`)
2. Exibir `secret` como texto (para entrada manual no app autenticador)
3. Exibir `recovery_codes` com destaque visual
4. **Obrigar o usuario a confirmar que anotou os recovery codes** (checkbox ou botao "Anotei os codigos")
5. Botao "Copiar codigos" (copiar para clipboard)
6. **Nunca armazenar recovery codes no front-end apos a tela ser fechada**
7. Campo para inserir codigo TOTP de confirmacao

**Etapa 2 — Confirmar Setup:**

```
POST /api/v1/{context}/auth/mfa/setup/confirm
Authorization: Bearer {access_token}
Body: { "code": "384291" }
```

**Response (sucesso):**

```json
{
  "data": {
    "mfa_enabled": true,
    "message": "MFA habilitado com sucesso."
  }
}
```

Se o usuario tem role que exige MFA e ainda nao configurou, o front-end deve:
- Redirecionar para tela de setup MFA imediatamente apos primeiro login
- Nao permitir navegacao ate que MFA seja configurado

---

### 3.5 Desabilitar MFA

**Endpoint:** `DELETE /api/v1/{context}/auth/mfa`

**Regra critica:** Usuarios com roles que exigem MFA obrigatorio (`platform_owner`, `platform_admin`, `sindico`, `administradora`) **NAO podem** desabilitar MFA. O front-end **nao deve exibir** a opcao de desabilitar MFA para essas roles.

**Request:**

```json
{
  "code": "482915",
  "password": "m1nh@Senh@Segur@"
}
```

| Codigo | Error Code | Acao |
|--------|------------|------|
| 200 | — | MFA desabilitado. Atualizar estado do usuario. |
| 403 | — | "MFA e obrigatorio para sua role." |

---

### 3.6 Registro de Morador (via Convite)

**Endpoint:** `POST /api/v1/tenant/auth/register`

O morador recebe um convite por email contendo um link com `invitation_token`. O front-end deve:

1. Extrair `invitation_token` da URL (query param ou path param)
2. Exibir formulario de registro

**Request:**

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

**Validacao no front-end:**

| Campo | Regras |
|-------|--------|
| `invitation_token` | obrigatorio (extraido da URL, nao editavel pelo usuario) |
| `name` | obrigatorio, min 3, max 255 chars |
| `email` | obrigatorio, email valido (pre-preenchido se possivel, nao editavel) |
| `password` | obrigatorio, min 8, ao menos 1 maiuscula, 1 minuscula, 1 numero |
| `password_confirmation` | obrigatorio, deve ser igual a password |
| `phone` | opcional, formato E.164 (+55XXXXXXXXXXX) |

**Indicador de forca de senha:** recomendado exibir visualmente.

**Respostas:**

| Codigo | Error Code | Acao no Front-End |
|--------|------------|-------------------|
| 201 | — | Registro completo. Armazenar tokens. Redirecionar para dashboard. |
| 404 | `invitation_not_found` | "Convite invalido ou nao encontrado." |
| 409 | `invitation_used` | "Este convite ja foi utilizado." |
| 409 | `email_already_registered` | "Ja existe uma conta com este email neste condominio." |
| 410 | `invitation_expired` | "Convite expirado. Solicite um novo convite ao sindico." |
| 410 | `invitation_revoked` | "Convite revogado." |
| 422 | `email_mismatch` | "O email informado nao corresponde ao convite." |

**Apos registro bem-sucedido:**
- O front-end recebe `access_token`, `refresh_token`, `user` e `tenant`
- Redirecionar para dashboard do condominio
- Se role exige MFA, redirecionar para setup MFA

---

### 3.7 Recuperacao de Senha

#### 3.7.1 Solicitar Reset

**Endpoint (Plataforma):** `POST /api/v1/platform/auth/forgot-password`
**Endpoint (Tenant):** `POST /api/v1/tenant/auth/forgot-password`

**Request (Plataforma):**

```json
{
  "email": "admin@condominium-events.com"
}
```

**Request (Tenant):**

```json
{
  "email": "joao.silva@email.com",
  "tenant_slug": "condominio-sol"
}
```

**Regra critica de UX:** A API **sempre retorna 200 OK** com a mesma mensagem, independente de o email existir ou nao. Isso previne enumeracao de usuarios.

**O front-end deve:**
- Exibir mensagem: "Se o email estiver cadastrado, voce recebera as instrucoes de recuperacao de senha."
- **Nunca** indicar se o email existe ou nao
- **Nunca** alterar a mensagem baseado na resposta (sempre a mesma)
- Redirecionar para tela de login apos exibir a mensagem

#### 3.7.2 Executar Reset

**Endpoint (Plataforma):** `POST /api/v1/platform/auth/reset-password`
**Endpoint (Tenant):** `POST /api/v1/tenant/auth/reset-password`

O usuario recebe um email com link contendo o token. O front-end deve:

1. Extrair `token` e `email` da URL
2. Exibir formulario com campos de nova senha

**Request (Plataforma):**

```json
{
  "token": "a1b2c3d4e5f6...",
  "email": "admin@condominium-events.com",
  "password": "n0v@Senh@Segur@",
  "password_confirmation": "n0v@Senh@Segur@"
}
```

**Request (Tenant):**

```json
{
  "token": "a1b2c3d4e5f6...",
  "email": "joao.silva@email.com",
  "password": "n0v@Senh@Segur@",
  "password_confirmation": "n0v@Senh@Segur@",
  "tenant_slug": "condominio-sol"
}
```

**Validacao no front-end:**

| Campo | Regras |
|-------|--------|
| `password` | obrigatorio, min 8, ao menos 1 maiuscula, 1 minuscula, 1 numero |
| `password_confirmation` | obrigatorio, deve ser igual a password |

**Respostas:**

| Codigo | Error Code | Acao no Front-End |
|--------|------------|-------------------|
| 200 | — | "Senha alterada com sucesso. Faca login com sua nova senha." → redirecionar para login |
| 400 | `invalid_reset_token` | "Link invalido. Solicite um novo reset de senha." |
| 400 | `reset_token_expired` | "Link expirado. Solicite um novo reset de senha." (token expira em 1 hora) |
| 422 | — | "A senha deve conter pelo menos 8 caracteres..." ou "Esta senha ja foi utilizada recentemente." |

**Apos reset:**
- Todos os tokens do usuario sao invalidados pela API
- O front-end deve limpar todos os tokens armazenados
- Redirecionar para tela de login

---

### 3.8 Logout

**Endpoint:** `POST /api/v1/{context}/auth/logout`

```
Authorization: Bearer {access_token}
```

Sem body.

**Acoes do front-end apos logout:**

1. Chamar o endpoint de logout
2. Independente da resposta (mesmo se falhar):
   - Limpar `access_token` do armazenamento
   - Limpar `refresh_token` do armazenamento
   - Limpar dados do usuario e tenant da memoria/store
   - Redirecionar para tela de login
3. Se a resposta for 401 (token ja expirado/invalido), tratar como logout bem-sucedido

---

## 4. Gerenciamento de Tokens

### 4.1 Tipos de Token

| Token | Formato | Duracao (TTL) | Uso |
|-------|---------|---------------|-----|
| `access_token` | JWT (RS256) | **15 minutos** | Autenticar requisicoes a API |
| `refresh_token` | Opaco (string) | **7 dias** | Renovar access_token sem re-login |
| `mfa_token` | JWT | **5 minutos** | Temporario durante fluxo MFA |

### 4.2 Armazenamento Seguro de Tokens

**Recomendacao de seguranca:**

| Armazenamento | Recomendado | Motivo |
|---------------|:-----------:|--------|
| `httpOnly cookie` | Sim (preferido) | Protegido contra XSS. Nao acessivel via JavaScript. |
| Memoria (variavel JS) | Sim (alternativa) | Perdido ao fechar aba. Mais seguro que localStorage. |
| `localStorage` | Nao | Vulneravel a XSS. Acessivel por qualquer script na pagina. |
| `sessionStorage` | Aceitavel | Perdido ao fechar aba. Menos seguro que httpOnly cookie. |

**Estrategia recomendada:**

```
access_token  → Memoria (variavel em state management / closure)
refresh_token → httpOnly cookie (se possivel via BFF) OU sessionStorage
mfa_token     → Memoria apenas (NUNCA persistir)
```

**Se nao for possivel usar httpOnly cookies (SPA puro):**

```
access_token  → Memoria (state/store)
refresh_token → sessionStorage (com ciencia do risco)
```

### 4.3 Refresh Automatico de Token

O front-end **deve** implementar refresh automatico de tokens. O access_token expira a cada 15 minutos.

**Estrategia recomendada — interceptor HTTP:**

```
Para cada requisicao autenticada:
1. Verificar se access_token esta proximo de expirar (< 2 min restantes)
   └── Se sim → fazer refresh antes de enviar a requisicao
2. Enviar requisicao com access_token atual
3. Se receber 401:
   ├── Tentar refresh token
   │   ├── Se refresh OK → repetir a requisicao original com novo access_token
   │   └── Se refresh falhar → redirecionar para login
   └── Se nao tem refresh_token → redirecionar para login
```

**Controle de concorrencia:**

Quando multiplas requisicoes simultaneas recebem 401:
- O front-end deve fazer **apenas um** refresh
- As demais requisicoes devem esperar o refresh completar
- Apos o refresh, repetir todas as requisicoes pendentes com o novo token

**Implementacao sugerida (pseudo-codigo):**

```
let isRefreshing = false
let pendingRequests = []

function onRequestFail(error) {
  if (error.status !== 401) return reject(error)

  if (isRefreshing) {
    // Enfileirar requisicao para retry
    return new Promise((resolve, reject) => {
      pendingRequests.push({ resolve, reject })
    })
  }

  isRefreshing = true

  try {
    const tokens = await refreshToken()
    storeTokens(tokens)
    isRefreshing = false

    // Repetir requisicoes enfileiradas
    pendingRequests.forEach(p => p.resolve())
    pendingRequests = []

    // Repetir requisicao original
    return retryRequest(error.config)
  } catch {
    isRefreshing = false
    pendingRequests.forEach(p => p.reject())
    pendingRequests = []
    redirectToLogin()
  }
}
```

### 4.4 Endpoint de Refresh

**Endpoint:** `POST /api/v1/{context}/auth/refresh`

**NAO exige Authorization header.**

**Request:**

```json
{
  "refresh_token": "dGhpcyBpcyBhIHJlZnJlc2ggdG9rZW4..."
}
```

**Response (sucesso):**

```json
{
  "data": {
    "access_token": "eyJhbGci...",
    "refresh_token": "bm92byByZWZyZXNo...",
    "token_type": "bearer",
    "expires_in": 900
  }
}
```

**Regra critica — Rotacao de Refresh Token:**
- A cada refresh, a API emite um **novo** refresh_token e invalida o anterior
- O front-end **deve substituir** o refresh_token armazenado pelo novo
- Se o front-end usar o refresh_token antigo → a API detecta como **possivel roubo** e invalida **toda a cadeia**
- Nesse caso, o usuario precisa fazer login novamente

**Erros de refresh:**

| Codigo | Error Code | Acao no Front-End |
|--------|------------|-------------------|
| 401 | `invalid_refresh_token` | Redirecionar para login |
| 401 | `refresh_token_expired` | "Sessao expirada. Faca login novamente." |
| 401 | `token_reuse_detected` | "Sessao invalidada por seguranca. Faca login novamente." (possivel roubo) |
| 401 | `account_disabled` | "Conta desativada." → limpar tudo, login |
| 403 | `tenant_inactive` | "Condominio suspenso ou cancelado." |

### 4.5 Leitura de Claims do JWT

O front-end pode decodificar o access_token (JWT) para ler claims sem verificar a assinatura (verificacao e responsabilidade do backend).

**Claims uteis para o front-end:**

| Claim | Tipo | Uso no Front-End |
|-------|------|------------------|
| `sub` | string (UUID) | ID do usuario |
| `tenant_id` | string? | ID do tenant (null para plataforma) |
| `roles` | string[] | Roles do usuario (para controle de UI) |
| `exp` | integer (Unix timestamp) | Calcular tempo restante do token |
| `iat` | integer (Unix timestamp) | Momento de emissao |
| `token_type` | string | Tipo: `access`, `mfa_required` |

**Calcular expiracao:**

```
const payload = JSON.parse(atob(token.split('.')[1]))
const expiresAt = new Date(payload.exp * 1000)
const remainingMs = expiresAt - Date.now()
const isExpired = remainingMs <= 0
const shouldRefresh = remainingMs < 120000  // < 2 minutos
```

---

## 5. Headers HTTP Obrigatorios

### 5.1 Headers de Requisicao

| Header | Obrigatorio | Valor | Descricao |
|--------|:-----------:|-------|-----------|
| `Authorization` | Sim* | `Bearer {access_token}` | Autenticacao. *Nao exigido em endpoints publicos. |
| `Content-Type` | Sim | `application/json` | Para requisicoes com body (POST, PUT, PATCH). |
| `Accept` | Sim | `application/json` | Formato esperado da resposta. |
| `X-Request-ID` | Recomendado | UUID v4 | Correlacao para debug. Se ausente, o servidor gera um. |

**Endpoints publicos (sem Authorization):**
- `POST /*/auth/login`
- `POST /*/auth/register`
- `POST /*/auth/forgot-password`
- `POST /*/auth/reset-password`
- `POST /*/auth/refresh`

### 5.2 Headers de Resposta (uteis para o front-end)

| Header | Descricao | Acao do Front-End |
|--------|-----------|-------------------|
| `X-Request-ID` | ID de correlacao | Logar para debug. Exibir em telas de erro para suporte. |
| `X-RateLimit-Limit` | Limite de requisicoes na janela | Informativo |
| `X-RateLimit-Remaining` | Requisicoes restantes | Exibir aviso se proximo de 0 |
| `X-RateLimit-Reset` | Timestamp Unix do reset | Calcular tempo de espera |
| `Retry-After` | Segundos para esperar (em respostas 429) | Exibir countdown |

---

## 6. Formato de Requisicoes e Respostas

### 6.1 Formato de Resposta — Recurso Unico

```json
{
  "data": {
    "id": "uuid",
    "type": "reservation",
    "attributes": {
      "space_id": "uuid",
      "start_datetime": "2025-01-15T14:00:00-03:00",
      "status": "confirmed"
    }
  }
}
```

### 6.2 Formato de Resposta — Colecao (Lista Paginada)

```json
{
  "data": [
    {
      "id": "uuid",
      "type": "space",
      "attributes": { ... }
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

### 6.3 Formato de Erro

```json
{
  "error": "error_code",
  "message": "Mensagem legivel para o usuario.",
  "errors": {
    "campo": ["Mensagem de validacao do campo."]
  }
}
```

**Tratamento no front-end:**

| Codigo HTTP | Acao |
|-------------|------|
| 200 / 201 / 204 | Sucesso |
| 400 | Erro de logica (ex: token invalido) |
| 401 | Nao autenticado → tentar refresh ou redirecionar para login |
| 403 | Nao autorizado → exibir mensagem de permissao negada |
| 404 | Recurso nao encontrado |
| 409 | Conflito (ex: reserva duplicada) |
| 422 | Validacao → exibir erros por campo |
| 429 | Rate limit → exibir countdown com `Retry-After` |
| 500 | Erro interno → "Erro no servidor. Tente novamente." + exibir `X-Request-ID` |

---

## 7. Rate Limiting

A API aplica rate limiting em todos os endpoints. O front-end deve estar preparado.

### 7.1 Limites por Endpoint

| Endpoint | Limite | Janela |
|----------|--------|--------|
| Login | 5 tentativas | 1 minuto |
| MFA Verify | 5 tentativas | 1 minuto |
| Forgot Password | 3 por email | 15 minutos |
| Reset Password | 5 tentativas | 15 minutos |
| Refresh Token | 10 tentativas | 1 minuto |
| Registro | 3 tentativas | 15 minutos |
| API Leitura (tenant) | 60/minuto | por usuario |
| API Escrita (tenant) | 30/minuto | por usuario |
| API Plataforma | 120/minuto | por usuario |

### 7.2 Tratamento de 429 (Too Many Requests)

```json
{
  "error": "too_many_requests",
  "message": "Muitas tentativas. Tente novamente em 60 segundos.",
  "retry_after": 60
}
```

**O front-end deve:**

1. Capturar resposta 429
2. Ler `retry_after` do body ou header `Retry-After`
3. Desabilitar botao de acao
4. Exibir countdown: "Tente novamente em X segundos"
5. Reativar apos countdown
6. **Nunca** enviar requisicoes durante o periodo de espera

---

## 8. Autorizacao e Controle de UI por Role

### 8.1 Roles e Permissoes

O front-end deve adaptar a interface baseado na role do usuario. A role vem no JWT (claim `roles`) e na resposta do login (`user.role`).

### 8.2 Roles de Plataforma

| Role | Pode |
|------|------|
| `platform_owner` | Tudo na plataforma (tenants, planos, billing, usuarios, flags) |
| `platform_admin` | Gerenciar tenants e billing. Nao pode gerenciar planos nem usuarios da plataforma. |
| `platform_support` | Somente leitura. Visualizar dados para suporte. |

### 8.3 Roles de Tenant

| Role | Pode |
|------|------|
| `sindico` | Tudo dentro do condominio |
| `administradora` | Mesmo que sindico, pode gerenciar multiplos condominios |
| `condomino` | Reservas proprias, convidados proprios, visualizar dados, criar chamados |
| `funcionario` | Check-in/check-out de convidados/prestadores, visualizar reservas do dia |

### 8.4 Matriz de Visibilidade de UI

| Funcionalidade | sindico | administradora | condomino | funcionario |
|----------------|:-------:|:--------------:|:---------:|:-----------:|
| Dashboard Admin | Sim | Sim | Nao | Nao |
| Gerenciar Blocos/Unidades | Sim | Sim | Nao | Nao |
| Convidar Moradores | Sim | Sim | Nao | Nao |
| Gerenciar Espacos | Sim | Sim | Nao | Nao |
| Fazer Reservas | Sim | Sim | Sim | Nao |
| Ver Todas Reservas | Sim | Sim | Nao | Sim |
| Ver Proprias Reservas | Sim | Sim | Sim | Nao |
| Aprovar/Rejeitar Reservas | Sim | Sim | Nao | Nao |
| Registrar Convidados | Sim | Sim | Sim* | Nao |
| Check-in/Check-out | Sim | Sim | Nao | Sim |
| Registrar Infraccoes | Sim | Sim | Nao | Nao |
| Contestar Infraccoes | Nao | Nao | Sim* | Nao |
| Configurar Penalidades | Sim | Sim | Nao | Nao |
| Publicar Comunicados | Sim | Sim | Nao | Nao |
| Ver Comunicados | Sim | Sim | Sim* | Sim |
| Criar Chamado Suporte | Sim | Sim | Sim | Sim |
| Gerenciar Documentos Legais | Sim | Sim | Nao | Nao |
| Consultar Documentos | Sim | Sim | Sim | Sim |
| Chat com IA | Sim | Sim | Sim | Nao |

`*` = com restricao de escopo (proprios recursos apenas)

### 8.5 Implementacao no Front-End

**Nao confie apenas no front-end para autorizar.** A API valida tudo no backend. O controle de UI e para **experiencia do usuario** (nao mostrar opcoes que ele nao pode usar).

```
// Exemplo de verificacao de permissao
function canUser(action) {
  const role = currentUser.role

  switch (action) {
    case 'manage_spaces':
      return ['sindico', 'administradora'].includes(role)
    case 'create_reservation':
      return ['sindico', 'administradora', 'condomino'].includes(role)
    case 'check_in_guest':
      return ['sindico', 'administradora', 'funcionario'].includes(role)
    case 'contest_violation':
      return role === 'condomino'
    default:
      return false
  }
}
```

---

## 9. Multi-Tenancy no Front-End

### 9.1 Contexto do Tenant

Apos login de tenant, o front-end possui os dados do tenant:

```json
{
  "id": "uuid",
  "name": "Condominio Sol",
  "slug": "condominio-sol",
  "status": "active",
  "subscription_status": "active",
  "plan": "professional"
}
```

### 9.2 Status do Tenant e Impacto na UI

| Status do Tenant | Login | Navegacao | Escrita | Acao do Front-End |
|------------------|:-----:|:---------:|:-------:|-------------------|
| `active` | Sim | Total | Total | Normal |
| `trialing` | Sim | Total | Total | Exibir badge "Periodo de teste" |
| `past_due` | Sim | Total | **Bloqueada** | Modo somente leitura (ver secao 9.3) |
| `suspended` | Nao | — | — | Tela de bloqueio |
| `canceled` | Nao | — | — | Tela de bloqueio |
| `provisioning` | Nao | — | — | Tela "Aguarde configuracao" |

### 9.3 Modo Somente Leitura (past_due)

Quando `subscription_status === "past_due"`:

1. Exibir banner persistente: "Pagamento pendente. Algumas funcionalidades estao limitadas."
2. **Desabilitar** todos os botoes de criacao/edicao/exclusao
3. **Manter** navegacao e visualizacao funcionando
4. Se o usuario tentar uma acao de escrita → exibir mensagem antes de enviar
5. A API retornara 403 com `error: "subscription_past_due"` se alguma escrita passar

### 9.4 Troca de Condominio (Administradora)

A role `administradora` pode gerenciar multiplos condominios. Para trocar:

1. O front-end deve fazer **logout** do tenant atual
2. Fazer novo login com o `tenant_slug` do condominio desejado
3. Um novo access_token com o `tenant_id` correto sera emitido

**Nao existe** endpoint de troca de tenant sem re-login.

---

## 10. Feature Flags e Limites do Plano

### 10.1 Feature Flags que Afetam a UI

| Feature Flag | Tipo | Impacto na UI |
|-------------|------|---------------|
| `can_use_ai` | boolean | Esconder/exibir modulo de IA |
| `max_reservations_per_month` | integer | Exibir contador "X de Y reservas usadas" |
| `can_use_support` | boolean | Esconder/exibir modulo de suporte |

### 10.2 Tratamento de Limite Atingido

Quando o usuario tenta uma acao e o limite do plano foi atingido:

```json
{
  "error": "usage_limit_reached",
  "message": "Limite de reservas do plano atingido (30/30)."
}
```

**O front-end deve:**
- Exibir mensagem clara
- Sugerir upgrade de plano (se aplicavel)
- Desabilitar botao de criacao quando o contador mostrar que esta no limite

---

## 11. Seguranca no Front-End

### 11.1 Prevencao de XSS

- **Nunca** usar `innerHTML` com dados da API
- Sanitizar todos os dados renderizados
- Usar frameworks que escapam automaticamente (React, Vue, Angular)
- Content Security Policy (CSP) headers recomendados

### 11.2 Protecao de Tokens

- **Nunca** logar tokens no console
- **Nunca** enviar tokens para servicos de terceiros (analytics, error tracking)
- **Nunca** expor tokens em URLs (query parameters)
- Limpar tokens ao fechar sessao
- Implementar deteccao de inatividade (ex: logout apos 30 min sem uso)

### 11.3 HTTPS Obrigatorio

- Toda comunicacao com a API deve ser via **HTTPS**
- O front-end nao deve funcionar em HTTP (exceto localhost em desenvolvimento)
- Verificar se a API retorna `Strict-Transport-Security` header

### 11.4 Protecao contra Enumeracao de Usuarios

- Na tela de login, exibir mensagem generica: "Email ou senha incorretos" (nao dizer qual esta errado)
- Na tela de forgot-password, exibir sempre a mesma mensagem (nunca indicar se email existe)
- Na tela de registro, o email vem do convite (nao permite escolha livre)

### 11.5 Timeout de Inatividade

Recomendacao:

| Role | Timeout de Inatividade |
|------|----------------------|
| `platform_owner`, `platform_admin` | 15 minutos |
| `sindico`, `administradora` | 30 minutos |
| `condomino` | 60 minutos |
| `funcionario` | 15 minutos (terminal de portaria) |

Apos timeout:
1. Fazer logout automatico
2. Exibir mensagem: "Sua sessao expirou por inatividade."
3. Redirecionar para login

---

## 12. Bloqueio de Conta

### 12.1 Parametros

| Parametro | Valor |
|-----------|-------|
| Tentativas antes do lockout | 10 falhas consecutivas |
| Duracao do lockout | 30 minutos |
| Tentativas MFA antes do lockout | 5 falhas |
| Duracao do lockout MFA | 30 minutos |

### 12.2 Tratamento no Front-End

Quando `error: "account_locked"`:

1. Extrair tempo restante da mensagem (ou calcular se `locked_until` disponivel)
2. Exibir: "Conta bloqueada por excesso de tentativas. Tente novamente em X minutos."
3. Desabilitar formulario de login
4. Exibir link: "Esqueceu sua senha?" (reset de senha desbloqueia a conta)
5. **Nao** exibir contador de tentativas restantes na tela de login (facilitaria ataque)

---

## 13. Paginacao, Filtros e Ordenacao

### 13.1 Paginacao

A API usa paginacao baseada em cursor ou offset. Parametros:

| Parametro | Tipo | Default | Descricao |
|-----------|------|---------|-----------|
| `page` | integer | 1 | Pagina atual |
| `per_page` | integer | 20 | Itens por pagina (max: 100) |
| `cursor` | string | — | Cursor para paginacao baseada em cursor |

### 13.2 Filtros

Enviados como query parameters:

```
GET /api/v1/tenant/reservations?status=confirmed&space_id=uuid&date_from=2025-01-01&date_to=2025-01-31
```

### 13.3 Ordenacao

```
GET /api/v1/tenant/reservations?sort=-created_at
```

- Prefixo `-` = descendente
- Sem prefixo = ascendente
- Multiplos campos: `sort=-created_at,name`

### 13.4 Includes (Eager Loading)

```
GET /api/v1/tenant/reservations?include=space,unit,guests
```

Reduz o numero de requisicoes (N+1).

---

## 14. Datas e Horarios

### 14.1 Formato

| Contexto | Formato | Exemplo |
|----------|---------|---------|
| Request/Response | ISO 8601 com timezone | `2025-01-15T14:00:00-03:00` |
| Apenas data | ISO 8601 | `2025-01-15` |
| Apenas hora | HH:mm | `14:00` |

### 14.2 Timezone

- A API retorna datas com timezone
- O front-end deve exibir datas no timezone local do usuario
- Para criacao de reservas, enviar sempre com timezone explicito

---

## 15. Identificadores

Todos os IDs sao **UUID v7** (ordenados por tempo). Nunca usar IDs sequenciais.

- Correto: `01912c8e-7b3a-7d1f-a5c2-3e8f9b1d4a6e`
- Incorreto: `42`

---

## 16. Checklist de Implementacao

### Autenticacao

- [ ] Tela de login (plataforma)
- [ ] Tela de login (tenant) com campo tenant_slug
- [ ] Tela de verificacao MFA (TOTP 6 digitos)
- [ ] Tela de setup MFA (QR Code + recovery codes + confirmacao)
- [ ] Tela de registro de morador (via convite)
- [ ] Tela de forgot password
- [ ] Tela de reset password
- [ ] Botao de logout
- [ ] Opcao de desabilitar MFA (somente roles opcionais)

### Gerenciamento de Tokens

- [ ] Armazenamento seguro de access_token e refresh_token
- [ ] Interceptor HTTP com Authorization header automatico
- [ ] Refresh automatico de token (antes de expirar ou apos 401)
- [ ] Controle de concorrencia no refresh (uma requisicao por vez)
- [ ] Substituicao do refresh_token apos cada refresh (rotacao)
- [ ] Limpeza completa de tokens no logout
- [ ] Redirect para login quando refresh falha

### Tratamento de Erros

- [ ] Tratamento de 401 (nao autenticado)
- [ ] Tratamento de 403 (nao autorizado)
- [ ] Tratamento de 404 (nao encontrado)
- [ ] Tratamento de 422 (validacao — erros por campo)
- [ ] Tratamento de 429 (rate limit — countdown)
- [ ] Tratamento de 500 (erro interno — exibir X-Request-ID)
- [ ] Tratamento de erros de tenant (suspenso, cancelado, etc.)

### Controle de UI

- [ ] Renderizacao condicional baseada em role
- [ ] Modo somente leitura (subscription past_due)
- [ ] Feature flags (esconder modulos indisponiveis)
- [ ] Limites de uso (contador de reservas, etc.)
- [ ] Timeout de inatividade

### Seguranca

- [ ] Prevencao de XSS (sanitizacao de dados)
- [ ] Tokens nao expostos em URLs ou logs
- [ ] HTTPS obrigatorio
- [ ] Mensagens genericas em login e forgot-password
- [ ] Deteccao de inatividade e logout automatico

---

## 17. Status

**Documento ATIVO** — Referencia definitiva para implementacao do front-end.

| Campo | Valor |
|-------|-------|
| Ultima atualizacao | 2026-02-10 |
| Versao | 1.0.0 |
| Documentos de referencia | auth-flows.md, authorization-matrix.md, api-design-guidelines.md, tenant-api.md, platform-api.md |
