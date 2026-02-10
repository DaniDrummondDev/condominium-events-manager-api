# Spaces Management — Gestão de Espaços Comuns
## FASE 8 — Core Domain
**Condominium Events Manager API**

---

## 1. Objetivo

Definir a arquitetura, regras e responsabilidades da **gestão de espaços comuns** do condomínio, garantindo:

- Cadastro completo e configurável de espaços
- Definição de capacidade, horários e restrições
- Regras de uso por espaço
- Configuração flexível pelo síndico/administradora
- Isolamento total por tenant
- Auditoria de alterações

Espaços são **entidades centrais do domínio**, pois toda reserva, evento e regra de governança gira em torno deles.

---

## 2. Dependências

Esta skill depende das seguintes skills:

- `saas-architecture.md` — isolamento multi-tenant
- `tenant-lifecycle.md` — tenant ativo como pré-requisito
- `access-control.md` — quem pode gerenciar espaços
- `audit-logging.md` — rastreabilidade de alterações
- `feature-flag-strategy.md` — limites por plano
- `api-contract-strategy.md` — contratos de API

---

## 3. Princípios Arquiteturais

### 3.1 Espaço é entidade de domínio

O espaço pertence ao **Domain Layer**.

- Contém regras de negócio próprias
- Não depende de infraestrutura
- É a unidade central para reservas e eventos

### 3.2 Configuração é responsabilidade do tenant

Cada condomínio configura seus próprios espaços.

- A plataforma não define espaços
- A plataforma pode limitar a quantidade (via plano)
- Cada tenant é independente na configuração

### 3.3 Espaço não é apenas um registro

Um espaço define:

- Capacidade
- Disponibilidade
- Regras de uso
- Restrições
- Custos (quando aplicável)

---

## 4. Entidade Central: Space

### 4.1 Campos Conceituais

- `id`
- `tenant_id`
- `name`
- `slug` (único por tenant)
- `description`
- `type` (salão de festas, churrasqueira, quadra, piscina, etc.)
- `capacity` (número máximo de pessoas)
- `status` (active, inactive, maintenance)
- `requires_approval` (boolean — reserva precisa de aprovação do síndico)
- `advance_booking_days` (antecedência mínima para reserva)
- `max_booking_days_ahead` (antecedência máxima)
- `min_duration_minutes`
- `max_duration_minutes`
- `allow_recurring` (boolean — permite reservas recorrentes)
- `created_at`
- `updated_at`

### 4.2 Regras de Validação

- `name` obrigatório e único por tenant
- `capacity` deve ser > 0
- `advance_booking_days` >= 0
- `max_booking_days_ahead` > `advance_booking_days`
- `min_duration_minutes` > 0
- `max_duration_minutes` >= `min_duration_minutes`

---

## 5. Disponibilidade (Availability)

### 5.1 Modelo: SpaceAvailability

Define os períodos em que o espaço pode ser reservado.

Campos conceituais:

- `id`
- `space_id`
- `day_of_week` (0-6)
- `start_time`
- `end_time`
- `is_available` (boolean)

### 5.2 Regras

- Horários são definidos por dia da semana
- Períodos não podem se sobrepor
- Feriados e exceções podem ser tratados via bloqueios

---

## 6. Bloqueios (Space Blocks)

### 6.1 Modelo: SpaceBlock

Representa períodos em que o espaço está indisponível.

Campos conceituais:

- `id`
- `space_id`
- `reason` (maintenance, holiday, event, administrative)
- `start_datetime`
- `end_datetime`
- `blocked_by` (user_id)
- `notes`
- `created_at`

### 6.2 Regras

- Bloqueio sobrepõe disponibilidade regular
- Reservas existentes no período devem ser tratadas (cancelamento ou notificação)
- Bloqueios são auditados

---

## 7. Tipos de Espaço

O sistema deve suportar tipos configuráveis:

Exemplos:

- Salão de festas
- Churrasqueira
- Quadra esportiva
- Piscina
- Playground
- Sala de reuniões
- Espaço gourmet
- Academia

Tipos podem ter **regras padrão** diferentes (ex: churrasqueira pode ter regra de limpeza obrigatória).

---

## 8. Estados do Espaço

| Estado | Descrição |
|--------|-----------|
| `active` | Disponível para reservas |
| `inactive` | Desativado temporariamente, sem reservas |
| `maintenance` | Em manutenção, bloqueado automaticamente |

Transições:

- `active` → `inactive` / `maintenance`
- `inactive` → `active`
- `maintenance` → `active`

---

## 9. Regras de Uso por Espaço (Space Rules)

### 9.1 Modelo: SpaceRule

Permite configuração granular de regras por espaço.

Campos conceituais:

- `id`
- `space_id`
- `rule_type` (max_guests, cleaning_fee, deposit_required, noise_curfew, etc.)
- `value` (string — interpretado conforme tipo)
- `description`
- `is_active` (boolean)

### 9.2 Exemplos de Regras

| Tipo | Valor | Descrição |
|------|-------|-----------|
| `max_guests` | 50 | Máximo de convidados |
| `cleaning_fee` | 150.00 | Taxa de limpeza |
| `deposit_required` | true | Exige caução |
| `noise_curfew` | 22:00 | Horário limite de barulho |
| `min_interval_hours` | 4 | Intervalo mínimo entre reservas |
| `max_reservations_per_month` | 2 | Limite por unidade/mês |

---

## 10. Limites por Plano

O número de espaços cadastrados pode ser limitado pelo plano do tenant.

Integração:

- `feature-flag-strategy.md` → `max_spaces`
- Verificação via `FeatureResolver`

Regra:

- Ao criar espaço, verificar se limite foi atingido
- Limite nunca é verificado apenas no front-end

---

## 11. Isolamento por Tenant

Regras obrigatórias:

- Todo espaço pertence a um tenant
- Nenhum espaço é compartilhado entre tenants
- Queries sempre escopadas por tenant_id
- Slug é único **dentro do tenant**, não globalmente

---

## 12. Auditoria

Eventos auditáveis:

- `space.created`
- `space.updated`
- `space.deactivated`
- `space.reactivated`
- `space.blocked`
- `space.unblocked`
- `space.rule_created`
- `space.rule_updated`

---

## 13. Permissões

| Ação | Papéis permitidos |
|------|-------------------|
| Criar espaço | Síndico, Administradora |
| Editar espaço | Síndico, Administradora |
| Desativar espaço | Síndico, Administradora |
| Visualizar espaço | Todos os usuários do tenant |
| Bloquear espaço | Síndico, Administradora |

---

## 14. Testes

### Testes de Domínio

- Validações de entidade
- Transições de estado
- Regras de disponibilidade
- Limites por plano

### Testes de Integração

- Persistência correta
- Isolamento por tenant
- Bloqueios sobrepondo disponibilidade

### Testes de API

- Contratos de criação/edição
- Validação de permissões
- Limites de plano respeitados

---

## 15. Anti-Padrões

- Espaço sem regras de validação
- Espaço acessível entre tenants
- Lógica de espaço no controller
- Configuração hardcoded no código
- Bloqueio sem auditoria

---

## 16. O que esta skill NÃO cobre

- Criação de reservas (→ `reservation-system.md`)
- Regras de penalidade (→ `governance-rules.md`)
- Controle de convidados (→ `people-control.md`)

---

## 17. Status

Documento **OBRIGATÓRIO** para implementação do domínio de espaços.

Espaços são o **alicerce funcional** sobre o qual reservas, governança e inteligência operam.
