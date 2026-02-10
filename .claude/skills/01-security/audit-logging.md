# Audit Logging — Condominium Events Manager API

## 1. Objetivo

Definir a **estratégia de auditoria e logs de segurança**, garantindo rastreabilidade completa de ações críticas na plataforma e nos tenants.

---

## 2. Princípios

* Logs são imutáveis
* Logs não são alterados nem removidos
* Logs não contêm dados sensíveis

---

## 3. O que Deve Ser Auditado

### 3.1 Autenticação

* Login
* Falhas de login
* Refresh token

---

### 3.2 Autorização

* Acesso negado
* Tentativas suspeitas

---

### 3.3 Ações Críticas

* Criação/remoção de usuários
* Alterações de regras
* Mudanças de estado do tenant
* Operações administrativas

---

## 4. Estrutura de Log

Cada evento deve conter:

* `event_type`
* `actor_id`
* `tenant_id`
* `timestamp`
* `origin` (IP, user-agent)
* `metadata`

---

## 5. Armazenamento

* Banco dedicado ou append-only
* Retenção configurável
* Acesso restrito

---

## 6. LGPD e Privacidade

* Dados pessoais minimizados
* Logs não usados para profiling

---

## 7. Integração com Billing

* Mudanças de estado do tenant auditadas

---

## 8. Monitoramento

* Alertas para padrões anômalos
* Integração com SIEM

---

## 9. Fora de Escopo

* Logs de aplicação comuns
* Observabilidade de performance
