# Units Management — Gestão de Unidades e Moradores
## FASE 8 — Core Domain
**Condominium Events Manager API**

---

## 1. Objetivo

Definir a arquitetura, regras e responsabilidades da **gestão de unidades residenciais e moradores** do condomínio, garantindo:

- Suporte a condomínios de casas (unidades simples) e prédios (blocos + apartamentos)
- Cadastro de unidades com associação de moradores
- Onboarding de moradores via convite do síndico
- Controle de papéis por unidade
- Isolamento total por tenant
- Base para reservas, governança e controle de pessoas

Unidades são **entidades estruturais do domínio**. Toda reserva, penalidade e controle de acesso depende da unidade.

---

## 2. Dependências

Esta skill depende das seguintes skills:

- `saas-architecture.md` — isolamento multi-tenant
- `tenant-lifecycle.md` — tenant ativo como pré-requisito
- `auth-architecture.md` — autenticação de moradores
- `access-control.md` — papéis e permissões
- `audit-logging.md` — rastreabilidade
- `notification-strategy.md` — convites e comunicações
- `lgpd-compliance.md` — dados pessoais de moradores
- `feature-flag-strategy.md` — limites por plano

---

## 3. Princípios Arquiteturais

### 3.1 Unidade é entidade de domínio, não de infraestrutura

A unidade pertence ao **Domain Layer**.

- Representa o vínculo entre pessoa e condomínio
- É referência obrigatória para reservas e penalidades
- Não depende de framework ou infraestrutura

### 3.2 Suporte a múltiplos tipos de condomínio

O sistema deve suportar:

- **Condomínio horizontal (casas)**: unidades diretas sem bloco
- **Condomínio vertical (prédios)**: blocos com múltiplos apartamentos

A arquitetura deve ser flexível para ambos sem lógica condicional complexa.

### 3.3 Morador é usuário do tenant

Moradores são **usuários do tenant**, não usuários da plataforma.

- Cada morador pertence a um tenant
- Cada morador está vinculado a pelo menos uma unidade
- Um morador pode ter papéis diferentes

---

## 4. Modelo de Condomínio

### 4.1 Tipo de Condomínio

Cada tenant define seu tipo:

| Tipo | Estrutura | Exemplo |
|------|-----------|---------|
| `horizontal` | Unidades diretas | Casa 1, Casa 2, Casa 3 |
| `vertical` | Blocos + Apartamentos | Bloco A - Apto 101, 102 |
| `mixed` | Ambos | Casas + Torres |

O tipo é definido na configuração do tenant.

---

## 5. Entidades

### 5.1 Block (Bloco)

Aplicável apenas para condomínios verticais ou mistos.

Campos conceituais:

- `id`
- `tenant_id`
- `name` (ex: "Bloco A", "Torre 1")
- `identifier` (ex: "A", "1")
- `floors` (número de andares, opcional)
- `status` (active, inactive)
- `created_at`
- `updated_at`

Regras:

- Bloco é opcional (condomínios horizontais não têm)
- `identifier` é único por tenant
- Bloco pode ser desativado sem excluir unidades

---

### 5.2 Unit (Unidade)

Campos conceituais:

- `id`
- `tenant_id`
- `block_id` (nullable — null para casas)
- `number` (ex: "101", "Casa 3")
- `floor` (nullable — andar do apartamento)
- `type` (apartment, house)
- `status` (active, inactive)
- `created_at`
- `updated_at`

Regras:

- `number` é único dentro do bloco (ou do tenant se sem bloco)
- Unidade pode ter múltiplos moradores
- Unidade desativada não pode fazer reservas

---

### 5.3 Resident (Morador)

Representa um morador vinculado a uma unidade.

Campos conceituais:

- `id`
- `tenant_id`
- `user_id` (referência ao usuário autenticado)
- `unit_id`
- `role_in_unit` (owner, tenant_resident, dependent)
- `is_primary` (boolean — morador principal da unidade)
- `moved_in_at`
- `moved_out_at` (nullable)
- `status` (active, inactive)
- `created_at`
- `updated_at`

Regras:

- Um usuário pode ser morador de múltiplas unidades (ex: proprietário de 2 aptos)
- Cada unidade deve ter pelo menos um morador primário
- `role_in_unit`:
  - `owner` — proprietário
  - `tenant_resident` — inquilino
  - `dependent` — familiar/dependente

---

### 5.4 TenantUser (Usuário do Tenant)

Campos conceituais:

- `id`
- `tenant_id`
- `name`
- `email`
- `phone` (nullable)
- `document` (CPF, nullable)
- `role` (condomino, sindico, administradora, funcionario)
- `status` (invited, active, inactive)
- `invited_by` (user_id)
- `invited_at`
- `activated_at`
- `created_at`
- `updated_at`

---

## 6. Onboarding de Moradores

### 6.1 Fluxo de Convite

1. Síndico cadastra a unidade
2. Síndico cadastra o morador (nome, e-mail, unidade, papel)
3. Sistema cria TenantUser com status `invited`
4. Sistema envia e-mail de convite com link de ativação
5. Morador acessa link e cria senha
6. Status muda para `active`
7. Morador pode acessar o sistema

### 6.2 Regras

- Apenas síndico e administradora podem convidar
- Convite tem prazo de expiração (configurável)
- Convite expirado pode ser reenviado
- E-mail deve ser único por tenant
- Convite é auditado

---

## 7. Gestão de Mudanças

### 7.1 Mudança de Morador

Quando um morador sai da unidade:

1. Síndico marca morador como `inactive` / define `moved_out_at`
2. Reservas futuras do morador são canceladas (ou transferidas)
3. Penalidades ativas permanecem no histórico
4. Novo morador é convidado

### 7.2 Transferência de Unidade

Quando a unidade muda de proprietário:

1. Morador anterior é desativado
2. Novo morador é convidado
3. Histórico da unidade é preservado
4. Penalidades são da unidade, não do morador (decisão configurável)

---

## 8. Limites por Plano

Limites controlados via feature flags:

- `max_units` — número máximo de unidades por tenant
- `max_users` — número máximo de usuários por tenant
- `max_residents_per_unit` — moradores por unidade

---

## 9. Isolamento por Tenant

Regras obrigatórias:

- Todas as unidades pertencem a um tenant
- Nenhuma unidade cruza tenants
- Moradores existem apenas no contexto do tenant
- Queries sempre escopadas por tenant_id

---

## 10. Eventos de Domínio

- `UnitCreated`
- `UnitDeactivated`
- `ResidentInvited`
- `ResidentActivated`
- `ResidentDeactivated`
- `ResidentMovedOut`
- `BlockCreated`

---

## 11. Auditoria

Eventos auditáveis:

- Criação de unidade/bloco
- Convite de morador
- Ativação de morador
- Desativação de morador
- Mudança de papel
- Transferência de unidade

---

## 12. Permissões

| Ação | Papéis permitidos |
|------|-------------------|
| Criar bloco/unidade | Síndico, Administradora |
| Editar unidade | Síndico, Administradora |
| Convidar morador | Síndico, Administradora |
| Desativar morador | Síndico, Administradora |
| Visualizar própria unidade | Condômino |
| Visualizar todas as unidades | Síndico, Administradora |

---

## 13. LGPD

- Nome, e-mail, telefone, CPF são dados pessoais
- Finalidade: gestão do condomínio
- Base legal: execução de contrato
- Retenção: enquanto morador ativo + período legal
- Anonimização após saída definitiva

---

## 14. Testes

### Testes de Domínio

- Criação de unidade em condomínio horizontal e vertical
- Vinculação de morador a unidade
- Limites de plano respeitados
- Fluxo de convite e ativação

### Testes de Integração

- Isolamento por tenant
- Persistência e consulta
- Fluxo completo de onboarding

### Testes de API

- Contratos de criação de unidade
- Fluxo de convite
- Permissões por papel

---

## 15. Anti-Padrões

- Morador sem vínculo com unidade
- Unidade sem tenant_id
- Convite sem expiração
- Dados pessoais retidos indefinidamente
- Lógica de onboarding no controller

---

## 16. O que esta skill NÃO cobre

- Reservas (→ `reservation-system.md`)
- Penalidades (→ `governance-rules.md`)
- Comunicação interna (→ `communication.md`)

---

## 17. Status

Documento **OBRIGATÓRIO** para implementação da gestão de unidades.

Sem unidades, não há como vincular reservas, penalidades e controle de acesso a moradores.
