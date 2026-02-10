# Roadmap de Skills — Condominium Events Manager API

Este documento define o **passo a passo oficial** para criação, validação e evolução das *skills do Claude* utilizadas no projeto **Condominium Events Manager API**.

O roadmap é **orientado a dependências reais de um SaaS B2B multi-tenant**, evitando sobreposição conceitual, lacunas de segurança e decisões técnicas prematuras.

---

## 🎯 Objetivos do Roadmap

* Garantir **coerência arquitetural** entre todas as skills
* Evitar duplicação de responsabilidades
* Assegurar **segurança, compliance e monetização desde o início**
* Criar uma base sólida para evolução da IA

---

## 🔹 FASE 0 — Fundação (Obrigatória) - ✅

> Base conceitual do SaaS. Nenhuma outra skill deve ser criada sem esta fase concluída.

### Skills

* `saas-architecture.md`
* `platform-architecture.md`
* `tenant-lifecycle.md`
* `migration-strategy.md`

### Critérios de Conclusão

* Definição clara de **plataforma vs tenant vs usuário**
* Ciclo de vida completo do tenant documentado
* Estratégia de migrations e provisionamento definida
* Nenhuma regra de billing ou segurança misturada ao domínio

---

## 🔹 FASE 1 — Segurança Estrutural - ✅

> Segurança como requisito de arquitetura, não como feature.

### Skills

* `security-architecture.md`
* `api-security.md`
* `auth-architecture.md`
* `access-control.md`
* `audit-logging.md`

### Critérios de Conclusão

* Autenticação e autorização explícitas
* Cobertura do OWASP API Security Top 10
* Todas as ações críticas auditáveis

---

## 🔹 FASE 2 — Compliance & LGPD - ✅

> Viabiliza contratos B2B e reduz risco jurídico.

### Skills

* `security-compliance.md`
* `lgpd-compliance.md`
* `data-retention-policy.md`

### Critérios de Conclusão

* Mapeamento de dados pessoais
* Definição de controlador vs operador
* Processos claros de exclusão, anonimização e auditoria

---

## 🔹 FASE 3 — Billing & Assinaturas (Core SaaS) - ✅

> Monetização desacoplada do domínio.

### Skills

* `billing-subscription.md`
* `billing-security.md`
* `subscription-lifecycle.md`
* `invoice-management.md`
* `payment-gateway-integration.md`
* `dunning-strategy.md`

### Critérios de Conclusão

* Estados claros da assinatura
* Automação de inadimplência
* Billing controla acesso apenas por estado

---

## 🔹 FASE 4 — Plataforma Admin (SaaS Owner) - ✅

> Governança completa da plataforma.

### Skills

* `platform-admin.md`
* `plan-management.md`
* `feature-flag-strategy.md`
* `tenant-administration.md`

### Critérios de Conclusão

* Gestão de planos e features
* Suspensão e controle de tenants
* Alterações sem necessidade de deploy

---

## 🔹 FASE 5 — IA & Dados Inteligentes - ✅

> Diferenciação do produto com responsabilidade.

### Skills

* `ai-integration.md`
* `ai-data-governance.md`
* `ai-action-orchestration.md`
* `embedding-strategy.md`
* `ai-observability.md`

### Critérios de Conclusão

* Isolamento total por tenant
* Base legal para uso de dados
* Observabilidade e explicabilidade

---

## 🔹 FASE 6 — Operação & Confiabilidade - ✅

> Estabilidade e resiliência do SaaS.

### Skills

* `job-architecture.md`
* `event-driven-architecture.md`
* `idempotency-strategy.md`
* `failure-handling.md`
* `notification-strategy.md`
* `observability-strategy.md`
* `cicd-strategy.md`

### Critérios de Conclusão

* Jobs idempotentes e observáveis
* Eventos desacoplados e auditáveis
* Falhas tratadas explicitamente
* Notificações centralizadas e desacopladas
* Observabilidade geral com health checks e SLOs
* Pipeline CI/CD automatizado

---

## 🔹 FASE 7 — Qualidade & Governança Técnica - ✅

> Sustentabilidade do projeto no longo prazo.

### Skills

* `testing-strategy.md`
* `architecture-tests.md`
* `api-contract-strategy.md`
* `decision-records.md`

---

## 🔹 FASE 8 — Core Domain - ✅

> Domínio principal do produto: gestão de espaços, reservas, governança e controle de pessoas.

### Skills

* `units-management.md`
* `communication.md`
* `spaces-management.md`
* `reservation-system.md`
* `governance-rules.md`
* `people-control.md`

### Critérios de Conclusão

* Unidades e moradores com suporte a condomínios horizontais e verticais
* Onboarding de moradores via convite do síndico
* Comunicação interna com avisos e solicitações de suporte
* Espaços configuráveis com regras por condomínio
* Sistema de reservas com prevenção de conflitos
* Governança com penalidades automáticas e contestação
* Controle de convidados e prestadores com rastreabilidade
* Integração documentada com IA, billing e segurança

---

## 📌 Observações Importantes

* Nenhuma skill deve violar decisões já documentadas
* O `CLAUDE.md` **só deve ser ajustado após todas as skills-base estarem prontas**
* Toda nova skill deve indicar **dependências explícitas**

---

**Este roadmap é um documento vivo**, porém qualquer alteração deve ser consciente, justificada e registrada.
