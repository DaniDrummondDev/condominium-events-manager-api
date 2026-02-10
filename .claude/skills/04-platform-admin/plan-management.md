# plan-management.md

**Skill: Gestão de Planos (Plan Management)**
**Fase:** 4 — Plataforma Admin
**Domínio:** Platform / Billing
**Tipo:** Platform Governance Skill

---

## Objetivo

Definir a arquitetura, regras e responsabilidades relacionadas à **gestão de planos do SaaS**, permitindo:

* Criação e evolução de planos
* Controle de recursos disponíveis por plano
* Versionamento de planos
* Alterações sem necessidade de deploy

Os planos são o **contrato comercial e técnico** entre o SaaS e o tenant.

---

## Dependências

Esta skill depende das seguintes skills:

* `saas-architecture.md`
* `platform-admin.md`
* `billing-subscription.md`
* `subscription-lifecycle.md`
* `feature-flag-strategy.md`
* `audit-logging.md`

Nenhuma implementação de planos deve ignorar essas dependências.

---

## Princípios Arquiteturais

### 1. Plano é um contrato, não apenas preço

Um plano define:

* Recursos disponíveis
* Limites de uso
* Funcionalidades liberadas
* Regras de billing

O plano não deve ser tratado apenas como:

* Nome
* Preço

---

### 2. Planos devem ser versionados

Planos evoluem ao longo do tempo:

* Mudanças de preço
* Alteração de limites
* Novos recursos

Mudanças não devem quebrar assinaturas existentes.

Portanto:

* Planos devem ter versões
* Assinaturas devem apontar para uma versão específica

---

### 3. Planos não devem alterar código

Mudanças de plano devem ocorrer via:

* Configuração
* Banco de dados
* Feature flags

Nunca via:

* Alteração de código
* Deploy
* Branch específica

---

## Entidades Conceituais

### Plan

Representa um plano comercial.

Campos conceituais:

* `id`
* `name`
* `slug`
* `status` (active, archived)
* `created_at`
* `updated_at`

Regras:

* `slug` deve ser único
* `slug` não deve mudar após criação

---

### PlanVersion

Representa uma versão específica do plano.

Campos conceituais:

* `id`
* `plan_id`
* `version`
* `price`
* `currency`
* `billing_cycle` (monthly, yearly)
* `trial_days`
* `status` (active, deprecated)
* `created_at`

Regras:

* Cada plano pode ter múltiplas versões
* Apenas uma versão pode estar ativa para novas assinaturas

---

### PlanFeature

Representa recursos liberados no plano.

Campos conceituais:

* `id`
* `plan_version_id`
* `feature_key`
* `value`
* `type` (boolean, integer, string)

Exemplos:

| feature_key         | value | type    |
| ------------------- | ----- | ------- |
| reservations_limit  | 100   | integer |
| ai_assistant        | true  | boolean |
| analytics_dashboard | true  | boolean |

---

## Tipos de Recursos de Plano

Os planos devem controlar:

### 1. Limites quantitativos

Exemplos:

* Número de reservas por mês
* Número de usuários
* Número de espaços

---

### 2. Recursos funcionais

Exemplos:

* Assistente de IA
* Relatórios avançados
* Integrações externas

---

### 3. Regras comerciais

Exemplos:

* Período de trial
* Frequência de cobrança
* Descontos

---

## Relação com Assinatura

A assinatura deve apontar para:

```
subscription → plan_version
```

Nunca para:

```
subscription → plan
```

Motivo:

* Garantir estabilidade de contrato
* Evitar mudanças inesperadas

---

## Alteração de Planos

### Atualização de preço

Fluxo correto:

1. Criar nova `PlanVersion`
2. Marcar versão anterior como `deprecated`
3. Novas assinaturas usam a nova versão
4. Assinaturas existentes mantêm versão antiga

---

### Upgrade de plano

Fluxo:

1. Alterar assinatura para nova `plan_version`
2. Gerar fatura proporcional (se aplicável)
3. Atualizar recursos do tenant

---

### Downgrade de plano

Fluxo:

1. Solicitar downgrade
2. Aplicar no próximo ciclo
3. Garantir que limites não sejam violados

Exemplo:

* Plano atual: 10 espaços
* Novo plano: 5 espaços
* Sistema deve exigir ajuste antes do downgrade

---

## Estados do Plano

Estados possíveis:

### Plan

* `active`
* `archived`

### PlanVersion

* `active`
* `deprecated`

---

## Regras de Segurança

* Apenas admins da plataforma podem:

  * Criar planos
  * Alterar versões
  * Arquivar planos

* Toda alteração deve ser auditada.

---

## Auditoria Obrigatória

Devem ser auditadas:

* Criação de plano
* Criação de versão
* Alteração de preço
* Ativação ou desativação de versão
* Arquivamento de plano

---

## Regras Não Negociáveis

1. Plano não é apenas preço; é contrato de recursos.
2. Planos devem ser versionados.
3. Assinaturas devem apontar para `plan_version`.
4. Alterações de plano não podem exigir deploy.
5. Toda alteração deve ser auditada.

---

## O que esta skill NÃO cobre

Esta skill não deve tratar:

* Implementação técnica de feature flags
* Estratégias de inadimplência
* Integração com gateway
* Lógica interna da assinatura

Esses pontos pertencem às skills:

* `feature-flag-strategy.md`
* `dunning-strategy.md`
* `payment-gateway-integration.md`
* `subscription-lifecycle.md`
