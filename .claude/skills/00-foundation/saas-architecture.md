# saas-architecture.md

**Skill: Arquitetura SaaS**
**Fase:** 0 — Fundação
**Domínio:** Plataforma
**Tipo:** Foundational Architecture Skill

---

## Objetivo

Definir os princípios arquiteturais fundamentais de um **SaaS multi-tenant**, garantindo:

* Separação clara entre plataforma e tenants
* Escalabilidade estrutural
* Isolamento de dados
* Segurança entre tenants
* Evolução controlada do sistema

Esta skill estabelece as **regras estruturais do SaaS** e deve ser considerada base para todas as demais.

---

## Dependências

Esta skill é **fundacional** e não depende de outras skills.

Todas as outras skills devem respeitar as decisões aqui definidas.

---

## Princípios Fundamentais

### 1. O sistema é uma plataforma multi-tenant

O sistema deve ser projetado como uma **plataforma SaaS**, e não como um sistema single-tenant replicado.

Características obrigatórias:

* Vários tenants na mesma aplicação
* Isolamento lógico e/ou físico de dados
* Gerenciamento centralizado da plataforma
* Billing centralizado

---

### 2. Separação entre Plataforma e Tenant

O sistema deve possuir **dois contextos principais**:

#### Contexto da Plataforma

Responsável por:

* Gestão de tenants
* Planos e billing
* Administração global
* Segurança da plataforma
* Monitoramento

#### Contexto do Tenant

Responsável por:

* Usuários do condomínio
* Espaços comuns
* Reservas
* Regras internas
* Dados operacionais

Esses contextos devem ser **isolados em nível de arquitetura**.

---

### 3. Tenant como unidade de isolamento

O tenant representa:

* Um cliente do SaaS
* Um condomínio
* Uma organização independente

O tenant é a **unidade principal de isolamento de dados, segurança e billing**.

---

## Estratégia de Multi-Tenancy

O sistema deve adotar **isolamento por banco ou schema**.

Estratégias permitidas:

1. Database por tenant
2. Schema por tenant (PostgreSQL)

Estratégias não permitidas:

* Tabelas compartilhadas com `tenant_id` como único isolamento para dados sensíveis

Motivo:

* Segurança
* Compliance
* Backup e restauração independentes
* Redução de risco de vazamento de dados

---

## Identidade do Tenant

Cada tenant deve possuir:

Campos conceituais:

* `id`
* `slug` (identificador público)
* `name`
* `status`
* `created_at`
* `activated_at`
* `suspended_at`
* `canceled_at`

O `slug` deve ser:

* Único globalmente
* Usado para identificação externa
* Não mutável após criação

---

## Estados do Tenant

Estados principais:

1. `prospect`
2. `trial` (opcional)
3. `provisioning`
4. `active`
5. `past_due`
6. `suspended`
7. `canceled`
8. `archived`

A transição de estados e suas regras completas estão definidas em:

`tenant-lifecycle.md`

---

## Domínios do Sistema

O sistema deve ser organizado em domínios principais:

### 1. Platform Domain

Responsável por:

* Tenants
* Planos
* Billing
* Administração global

### 2. Tenant Domain

Responsável por:

* Espaços
* Reservas
* Usuários
* Regras do condomínio

### 3. Shared Infrastructure

Responsável por:

* Autenticação
* Segurança
* Logs
* Jobs
* Integrações externas

---

## Arquitetura em Camadas

O sistema deve seguir princípios de:

* DDD
* Clean Architecture
* SOLID

Camadas principais:

1. **Domain**

   * Entidades
   * Value Objects
   * Regras de negócio

2. **Application**

   * Casos de uso
   * Orquestração

3. **Infrastructure**

   * Banco de dados
   * Gateways
   * APIs externas

4. **Interface**

   * Controllers
   * Endpoints de API

---

## Identificação de Contexto em Requisições

Toda requisição deve:

1. Identificar o tenant
2. Validar o acesso
3. Carregar o contexto correto

Estratégias possíveis:

* Subdomínio por tenant
  Ex: `condominio-a.app.com`
* Header com tenant
* Token com escopo de tenant

A identificação deve ocorrer **antes de qualquer acesso a dados**.

---

## Provisionamento de Tenant

A criação de um tenant deve:

1. Criar registro na plataforma
2. Criar database ou schema
3. Executar migrations
4. Criar usuário inicial
5. Ativar assinatura
6. Alterar estado para `active`

O fluxo completo deve seguir:

`tenant-lifecycle.md`

---

## Isolamento de Código

O código do sistema deve:

* Ser único para todos os tenants
* Não conter lógica específica por cliente
* Usar configurações baseadas em plano ou feature flags

Customizações devem ocorrer via:

* Configurações
* Planos
* Feature flags

Nunca via:

* Branch por cliente
* Código específico por tenant

---

## Observabilidade por Tenant

O sistema deve permitir:

* Logs por tenant
* Métricas por tenant
* Rastreamento de erros por tenant

Isso é necessário para:

* Suporte
* Auditoria
* Análise de uso
* Cobrança

---

## Regras Não Negociáveis

1. O sistema deve ser multi-tenant desde a base.
2. Plataforma e tenant são contextos separados.
3. Tenant é a unidade principal de isolamento.
4. Dados sensíveis não podem compartilhar tabelas entre tenants.
5. Código não pode ser customizado por cliente.
6. Toda requisição deve identificar o tenant antes de acessar dados.

---

## O que esta skill NÃO cobre

Esta skill não deve tratar:

* Ciclo de vida detalhado do tenant
* Regras de billing
* Segurança detalhada
* Autenticação
* Feature flags

Esses pontos pertencem às skills:

* `tenant-lifecycle.md`
* `billing-subscription.md`
* `security-architecture.md`
* `auth-architecture.md`
* `feature-flag-strategy.md`
