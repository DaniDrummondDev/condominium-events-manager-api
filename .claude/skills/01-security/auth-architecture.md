# Auth Architecture — Condominium Events Manager API

## 1. Objetivo

Definir a **arquitetura de autenticação** da plataforma SaaS, garantindo segurança, escalabilidade e compatibilidade com múltiplos tenants.

Este documento trata **exclusivamente de autenticação** (quem é o usuário). Autorização é tratada separadamente.

---

## 2. Princípios Fundamentais

* Autenticação é **centralizada**
* Autorização é **contextual ao tenant**
* Tokens são **curta duração**
* Nenhuma sessão stateful no backend

---

## 3. Estratégia de Autenticação

### 3.1 Padrão Adotado

* OAuth 2.1
* JWT assinado
* Refresh Tokens rotacionados

---

### 3.2 Tipos de Autenticação

* Usuários humanos (email/senha, MFA)
* APIs / Integrações (Client Credentials)
* Serviços internos (service-to-service)

---

## 4. Escopo do Token

Todo token deve conter:

* `sub` (identidade do usuário)
* `tenant_id`
* `roles` (referência, não decisão final)
* `token_type`
* `exp`, `iat`, `jti`

Tokens **nunca** carregam permissões finais.

---

## 5. Multi-Tenancy

* Um token pertence a **um único tenant**
* Troca de tenant exige novo token
* Tokens cross-tenant são proibidos

---

## 6. MFA (Obrigatório para Perfis Sensíveis)

* Síndico
* Administradora
* Admin da Plataforma

---

## 7. Revogação e Segurança

* Lista de revogação baseada em `jti`
* Rotação de refresh tokens
* Logout invalida cadeia de tokens

---

## 8. Integração com Billing

* Tenant inativo → autenticação negada
* Billing não altera identidade

---

## 9. Auditoria

* Login bem-sucedido
* Falhas de autenticação
* Uso de refresh token

---

## 10. Fora de Escopo

* Controle de acesso
* Regras de domínio
