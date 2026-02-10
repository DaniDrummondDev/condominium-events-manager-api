# Platform Architecture — Condominium Events Manager API

## 1. Objetivo deste Documento

Definir a **arquitetura da plataforma SaaS** como um todo, separando claramente:

* Plataforma (SaaS Owner)
* Tenants (Condomínios / Administradoras)
* Usuários finais

Este documento **não descreve regras de domínio** dos condomínios, apenas a **estrutura da plataforma que os suporta**.

---

## 2. Visão Geral da Plataforma

O Condominium Events Manager é uma plataforma **API-first**, multi-tenant, projetada para operar como:

* Um **produto único**
* Servindo **múltiplos tenants isolados**
* Com governança centralizada pelo owner da plataforma

A plataforma é responsável por:

* Provisionamento de tenants
* Billing e assinaturas
* Segurança global
* Observabilidade
* Governança técnica

---

## 3. Separação Conceitual (Obrigatória)

### 3.1 Plataforma (Global)

Responsável por:

* Planos e preços
* Assinaturas
* Pagamentos e faturas
* Estado do tenant (ativo, suspenso, cancelado)
* Administração global
* Feature flags
* Políticas globais de segurança

A plataforma **não conhece** o domínio interno do condomínio.

---

### 3.2 Tenant (Condomínio / Administradora)

Cada tenant representa uma **unidade de negócio isolada**, com:

* Base de dados ou schema exclusivo
* Configurações próprias
* Regras internas próprias

O tenant **não tem acesso a dados da plataforma**, apenas ao que lhe é delegado.

---

### 3.3 Usuários

Usuários **sempre pertencem a um tenant**.

Exemplos:

* Condôminos
* Síndicos
* Administradoras
* Funcionários do condomínio

Não existem usuários "globais" fora do contexto da plataforma admin.

---

## 4. Arquitetura Multi-Tenant

### Estratégia adotada

* **PostgreSQL**
* Isolamento por **database ou schema**
* Um tenant = um contexto físico isolado

Benefícios:

* Segurança
* Facilidade de compliance
* Base sólida para IA isolada

---

## 5. Componentes da Plataforma

### 5.1 API Gateway

* Entrada única para clientes
* Autenticação inicial
* Rate limiting
* Versionamento

---

### 5.2 Core Platform Services

* Tenant Provisioning Service
* Subscription & Billing Service
* Platform Admin API
* Feature Flag Service

---

### 5.3 Tenant Services

* APIs de domínio
* Operam **dentro do contexto do tenant**
* Nunca acessam dados de outros tenants

---

## 6. Comunicação entre Camadas

* Plataforma → Tenant: **somente via eventos ou estados**
* Tenant → Plataforma: **somente para billing, métricas ou auditoria**

Comunicação direta com banco de outro tenant é **proibida**.

---

## 7. Princípios Arquiteturais

* API-first
* Stateless APIs
* Isolamento forte
* Observabilidade por tenant
* Segurança como padrão

---

## 8. Fora de Escopo

Este documento **não cobre**:

* Regras de reservas
* Domínio de eventos
* IA aplicada ao uso dos espaços

Esses temas pertencem ao **domínio do tenant**.
