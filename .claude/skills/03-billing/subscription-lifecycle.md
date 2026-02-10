# Skill — Subscription Lifecycle (SaaS)

## 1. Objetivo da Skill

Definir, de forma clara e inequívoca, o **ciclo de vida completo de uma assinatura SaaS**, garantindo:

- Previsibilidade de estados
- Governança financeira
- Automação de inadimplência
- Segurança operacional
- Coerência entre billing, acesso ao sistema e tenants

Esta skill orienta **API, domínio, jobs, webhooks e decisões de segurança** relacionadas a assinaturas.

---

## 2. Princípios Fundamentais

- Assinaturas são **entidades de domínio**, não apenas registros financeiros
- Acesso ao sistema é **derivado do estado da assinatura**
- Nenhuma ação crítica depende apenas de eventos externos (ex: gateway)
- Todo estado deve ser **auditável**
- Mudanças de estado devem ser **idempotentes**

---

## 3. Entidade Central: Subscription

A entidade `Subscription` representa o **contrato ativo entre a plataforma e um tenant**.

### Atributos essenciais
- `id`
- `tenant_id`
- `plan_id`
- `status`
- `billing_cycle`
- `current_period_start`
- `current_period_end`
- `grace_period_end`
- `canceled_at`
- `created_at`
- `updated_at`

---

## 4. Estados da Assinatura

### 4.1 Estados Principais

| Estado | Descrição |
|------|----------|
| `trialing` | Período de teste ativo |
| `active` | Assinatura paga e válida |
| `past_due` | Pagamento falhou, dentro da tolerância |
| `grace_period` | Acesso limitado, cobrança em retry |
| `suspended` | Acesso bloqueado (inadimplência) |
| `canceled` | Cancelada pelo cliente ou sistema |
| `expired` | Encerrada definitivamente |

---

## 5. Transições Permitidas

### Fluxo normal

trialing → active → canceled → expired

### Fluxo de inadimplência

active → past_due → grace_period → suspended → expired

### Reativação

past_due / grace_period → active
suspended → active (somente após pagamento confirmado)


> ⚠️ Transições fora desses fluxos são **proibidas**.

---

## 6. Trial (Período de Teste)

Regras:
- Pode ter limitação de features
- Pode exigir ou não cartão
- Deve ter data de expiração explícita
- Nunca se renova automaticamente sem consentimento

Eventos:
- `trial_started`
- `trial_ending`
- `trial_expired`

---

## 7. Billing Cycle

- Mensal ou anual
- Cobrança sempre **antecipada**
- Alterações de plano seguem regra de:
  - Upgrade: imediato
  - Downgrade: próximo ciclo

---

## 8. Inadimplência e Retry Logic

### Retry Strategy (exemplo)
- D+1 → tentativa 1
- D+3 → tentativa 2
- D+7 → tentativa final

### Regras
- Durante `past_due`: acesso normal
- Durante `grace_period`: acesso restrito
- Em `suspended`: acesso bloqueado

---

## 9. Impacto no Acesso ao Sistema

| Estado | Acesso |
|-----|-------|
| `trialing` | Parcial |
| `active` | Total |
| `past_due` | Total |
| `grace_period` | Parcial |
| `suspended` | Nenhum |
| `canceled` | Nenhum |

A API **deve validar o estado da assinatura em toda request sensível**.

---

## 10. Cancelamento

Tipos:
- Imediato
- No fim do ciclo

Regras:
- Cancelamento não remove dados
- Tenant entra em estado `canceled`
- Dados permanecem conforme política de retenção

---

## 11. Webhooks e Eventos

Eventos externos (ex: Stripe) **nunca são fonte única de verdade**.

Todo webhook deve:
- Ser validado
- Ser idempotente
- Disparar um evento interno

Eventos internos:
- `subscription_activated`
- `subscription_payment_failed`
- `subscription_suspended`
- `subscription_canceled`

---

## 12. Jobs e Automação

Jobs obrigatórios:
- Verificação de expiração de trial
- Processamento de retries
- Suspensão automática
- Expiração definitiva

Nenhuma lógica crítica deve depender de execução manual.

---

## 13. Auditoria e Compliance

- Toda transição gera log imutável
- Histórico completo por tenant
- Integração com LGPD (retenção e anonimização)

---

## 14. Testes Obrigatórios

Esta skill exige:

- Unit tests de máquina de estados
- Feature tests de transições
- Tests de retry e suspensão
- Tests de impacto no acesso à API
- Contract tests com gateway de pagamento

---

## 15. Anti-Padrões (Proibidos)

- Checar pagamento apenas no frontend
- Alterar estado manualmente sem evento
- Depender só do status do gateway
- Misturar billing com autorização de API

---

## 16. Resultado Esperado

Um sistema de assinaturas:
- Previsível
- Automatizado
- Seguro
- Auditável
- Escalável para múltiplos tenants

Sem ambiguidades, sem exceções implícitas.
