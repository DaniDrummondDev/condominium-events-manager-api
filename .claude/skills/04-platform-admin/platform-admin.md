# platform-admin.md

**Skill: Administração da Plataforma (SaaS Owner)**
**Fase:** 4 — Plataforma Admin
**Domínio:** Platform
**Tipo:** Platform Governance Skill

---

## Objetivo

Definir a arquitetura, regras e responsabilidades da **administração da plataforma SaaS**, permitindo que o operador do sistema (SaaS Owner):

* Gerencie tenants
* Controle planos e recursos
* Aplique suspensões
* Visualize dados operacionais
* Governe a plataforma sem necessidade de deploy

Esta skill representa o **domínio da plataforma**, separado do domínio dos tenants.

---

## Dependências

Esta skill depende das seguintes skills:

* `saas-architecture.md`
* `platform-architecture.md`
* `tenant-lifecycle.md`
* `billing-subscription.md`
* `subscription-lifecycle.md`
* `access-control.md`
* `audit-logging.md`

Nenhuma funcionalidade de administração da plataforma deve ignorar essas dependências.

---

## Princípios Arquiteturais

### 1. Plataforma é separada dos tenants

O sistema deve ter dois contextos claros:

#### Contexto da plataforma

* Administradores do SaaS
* Gestão de tenants
* Gestão de planos
* Métricas globais

#### Contexto do tenant

* Usuários do condomínio
* Reservas
* Espaços
* Regras internas

O **admin da plataforma nunca atua como usuário do tenant**.

---

### 2. Ações do admin devem ser auditáveis

Toda ação de administrador deve:

* Ser registrada em audit log
* Conter ator, ação e alvo
* Conter timestamp
* Ser rastreável

---

### 3. Admin da plataforma não altera domínio do tenant

O admin pode:

* Suspender tenant
* Reativar tenant
* Alterar plano
* Bloquear acesso

O admin não pode:

* Criar reservas
* Alterar dados de moradores
* Operar funcionalidades internas do tenant

---

## Papéis de Administração da Plataforma

Papéis conceituais:

### 1. Platform Owner

* Controle total da plataforma
* Gestão de planos
* Gestão de billing
* Acesso a métricas globais

### 2. Platform Admin

* Gestão de tenants
* Suspensão e reativação
* Suporte operacional

### 3. Platform Support (opcional)

* Visualização de dados
* Ações limitadas
* Sem poder de suspensão global

---

## Entidade Conceitual: Platform User

Campos conceituais:

* `id`
* `email`
* `password_hash`
* `role`
* `status`
* `last_login_at`
* `created_at`
* `updated_at`

Esses usuários:

* Não pertencem a tenants
* Vivem no contexto da plataforma
* Não compartilham autenticação com usuários de tenant

---

## Gestão de Tenants

O admin da plataforma deve poder:

### Ações permitidas

* Criar tenant manualmente
* Suspender tenant
* Reativar tenant
* Cancelar tenant
* Alterar plano
* Visualizar status de cobrança
* Forçar renovação de assinatura (opcional)

---

### Estados do tenant (resumo)

Estados principais:

* `provisioning`
* `active`
* `past_due`
* `suspended`
* `canceled`

A mudança de estado deve:

* Seguir `tenant-lifecycle.md`
* Ser auditada

---

## Suspensão Manual

O admin pode suspender um tenant:

Motivos:

* Inadimplência manual
* Violação de termos
* Solicitação do cliente
* Segurança

Efeitos:

* Tenant → `suspended`
* Acesso bloqueado
* Dados preservados

---

## Reativação Manual

O admin pode:

* Reativar tenant suspenso
* Ajustar plano
* Resolver problemas de billing

A reativação deve:

* Atualizar o estado do tenant
* Registrar auditoria

---

## Dashboard de Plataforma (Conceitual)

O painel administrativo deve expor:

### Métricas globais

* Total de tenants
* Tenants ativos
* Tenants suspensos
* Receita mensal estimada
* Invoices em atraso

### Métricas operacionais

* Falhas de pagamento
* Eventos de dunning
* Crescimento de tenants

---

## Isolamento de Dados

O admin da plataforma:

* Não acessa diretamente dados internos do tenant
* Só vê dados agregados ou metadados

Exemplos permitidos:

* Nome do tenant
* Plano
* Status de cobrança
* Data de criação

Exemplos proibidos:

* Reservas específicas
* Dados pessoais de moradores
* Conversas ou conteúdos internos

---

## Segurança

### Regras obrigatórias

1. Acesso ao admin da plataforma deve:

   * Usar autenticação forte
   * Preferencialmente com MFA

2. Sessões administrativas devem:

   * Ter timeout reduzido
   * Ser auditadas

3. Endpoints de plataforma devem:

   * Ser separados dos endpoints de tenant
   * Usar escopo de autorização próprio

---

## Auditoria Obrigatória

Devem ser auditadas:

* Criação de tenant
* Suspensão
* Reativação
* Cancelamento
* Alteração de plano
* Alteração de papéis administrativos
* Login de admin

---

## Regras Não Negociáveis

1. Plataforma e tenant são contextos separados.
2. Admin da plataforma não atua como usuário do tenant.
3. Toda ação administrativa deve ser auditada.
4. Suspensão nunca apaga dados automaticamente.
5. Endpoints de plataforma devem ser isolados.

---

## O que esta skill NÃO cobre

Esta skill não deve tratar:

* Estrutura de planos
* Feature flags
* Configuração detalhada de planos
* Estratégias de preço

Esses pontos pertencem às skills:

* `plan-management.md`
* `feature-flag-strategy.md`
* `tenant-administration.md`
