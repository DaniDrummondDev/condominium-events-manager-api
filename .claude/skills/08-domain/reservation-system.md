# Reservation System — Sistema de Reservas
## FASE 8 — Core Domain
**Condominium Events Manager API**

---

## 1. Objetivo

Definir a arquitetura, regras e responsabilidades do **sistema de reservas** de espaços comuns, garantindo:

- Solicitação, aprovação, cancelamento e bloqueio de reservas
- Prevenção automática de conflitos de agenda
- Aplicação automática de regras e penalidades
- Fluxo previsível e auditável
- Isolamento total por tenant
- Governança configurável pelo síndico

O sistema de reservas é o **coração funcional** do produto.

---

## 2. Dependências

Esta skill depende das seguintes skills:

- `spaces-management.md` — espaços, disponibilidade e regras
- `governance-rules.md` — penalidades e bloqueios
- `people-control.md` — convidados e prestadores
- `access-control.md` — permissões por papel
- `audit-logging.md` — rastreabilidade
- `event-driven-architecture.md` — eventos de domínio
- `notification-strategy.md` — notificações de reserva
- `feature-flag-strategy.md` — limites por plano
- `idempotency-strategy.md` — operações seguras

---

## 3. Princípios Arquiteturais

### 3.1 Reserva é agregado raiz

A reserva é um **Aggregate Root** no DDD.

- Contém todas as regras de criação, alteração e cancelamento
- Controla convidados e prestadores associados
- Emite eventos de domínio

### 3.2 Conflito é prevenido, não resolvido

O sistema deve **impedir** sobreposição de reservas, não resolver após ocorrência.

### 3.3 Regras são do domínio, não da API

Validações de negócio vivem no Domain Layer, não nos controllers.

---

## 4. Entidade Central: Reservation

### 4.1 Campos Conceituais

- `id`
- `tenant_id`
- `space_id`
- `unit_id` (unidade do condômino)
- `requested_by` (user_id)
- `approved_by` (user_id, nullable)
- `status`
- `date`
- `start_time`
- `end_time`
- `expected_guests_count`
- `purpose` (descrição do evento)
- `notes`
- `canceled_at`
- `canceled_by`
- `cancellation_reason`
- `created_at`
- `updated_at`

---

## 5. Estados da Reserva

| Estado | Descrição |
|--------|-----------|
| `pending_approval` | Aguardando aprovação do síndico |
| `confirmed` | Aprovada e confirmada |
| `rejected` | Rejeitada pelo síndico |
| `canceled` | Cancelada pelo solicitante ou síndico |
| `completed` | Evento realizado |
| `no_show` | Reserva não utilizada |

### 5.1 Transições Permitidas

- `pending_approval` → `confirmed` / `rejected` / `canceled`
- `confirmed` → `canceled` / `completed` / `no_show`

Transições fora desse fluxo são **proibidas**.

### 5.2 Fluxo com Aprovação

```
Solicitação → pending_approval → confirmed → completed
                               → rejected
                               → canceled
```

### 5.3 Fluxo sem Aprovação (espaço com `requires_approval = false`)

```
Solicitação → confirmed → completed
                        → canceled
                        → no_show
```

---

## 6. Prevenção de Conflitos

### 6.1 Regra Central

Para um dado espaço, data e período:

- Só pode existir **uma reserva ativa** (confirmed ou pending_approval)
- Reservas canceladas e rejeitadas não bloqueiam

### 6.2 Verificação

Antes de criar reserva:

1. Verificar disponibilidade do espaço (horários configurados)
2. Verificar bloqueios ativos no período
3. Verificar conflito com outras reservas ativas
4. Verificar regras do espaço (antecedência, duração)
5. Verificar limites do condômino (penalidades, cota mensal)
6. Verificar limites do plano (feature flags)

Se qualquer verificação falhar → rejeitar com motivo explícito.

### 6.3 Lock de Concorrência

- Reservas concorrentes para o mesmo slot devem ser tratadas
- Estratégia: lock pessimista ou otimista no banco
- Apenas uma reserva pode ser criada por slot

---

## 7. Regras de Negócio

### 7.1 Antecedência

- Respeitar `advance_booking_days` do espaço
- Respeitar `max_booking_days_ahead` do espaço

### 7.2 Duração

- Respeitar `min_duration_minutes` e `max_duration_minutes`
- Reserva deve caber dentro do horário de disponibilidade

### 7.3 Limites por Unidade

- `max_reservations_per_month` por unidade (configurável no espaço)
- Penalidades podem reduzir o limite

### 7.4 Cancelamento

Regras de cancelamento:

- Cancelamento até X horas antes: sem penalidade
- Cancelamento tardio: pode gerar penalidade
- No-show: penalidade automática
- Prazo de cancelamento configurável por espaço

### 7.5 Reservas Recorrentes

Se `allow_recurring = true` no espaço:

- Condômino pode solicitar reserva semanal/mensal
- Cada ocorrência é uma reserva individual
- Conflito em uma ocorrência não cancela todas

---

## 8. Eventos de Domínio

Eventos emitidos:

- `ReservationRequested`
- `ReservationConfirmed`
- `ReservationRejected`
- `ReservationCanceled`
- `ReservationCompleted`
- `ReservationNoShow`

Eventos disparam:

- Notificações
- Atualização de métricas
- Verificação de penalidades
- Logs de auditoria

---

## 9. Associação com Pessoas

### 9.1 Convidados

Uma reserva pode ter convidados associados.

- Registro obrigatório quando configurado pelo espaço
- Lista vinculada à reserva
- Verificação de capacidade
- Detalhes: → `people-control.md`

### 9.2 Prestadores de Serviço

Uma reserva pode ter prestadores associados.

- Registro obrigatório quando configurado
- Validação de cadastro prévio
- Detalhes: → `people-control.md`

---

## 10. Aprovação

### 10.1 Fluxo de Aprovação

Quando `requires_approval = true`:

1. Condômino solicita reserva
2. Reserva fica em `pending_approval`
3. Síndico recebe notificação
4. Síndico aprova ou rejeita
5. Condômino recebe notificação do resultado

### 10.2 Regras

- Aprovação deve ter prazo (timeout configurável)
- Timeout sem resposta → ação configurável (aprovar automaticamente ou cancelar)
- Rejeição exige justificativa
- Aprovação/rejeição é auditada

---

## 11. Isolamento por Tenant

Regras obrigatórias:

- Toda reserva pertence a um tenant
- Nenhuma reserva cruza tenants
- Queries sempre escopadas por tenant_id
- Conflitos verificados apenas dentro do tenant

---

## 12. Limites por Plano

Limites controlados via feature flags:

- `max_reservations_per_month` (por tenant)
- `max_guests_per_reservation`
- `allow_recurring_reservations`

Integração: `feature-flag-strategy.md`

---

## 13. Auditoria

Eventos auditáveis:

- `reservation.requested`
- `reservation.confirmed`
- `reservation.rejected`
- `reservation.canceled`
- `reservation.completed`
- `reservation.no_show`
- `reservation.guest_added`
- `reservation.guest_removed`

---

## 14. Permissões

| Ação | Papéis permitidos |
|------|-------------------|
| Solicitar reserva | Condômino |
| Aprovar/rejeitar reserva | Síndico, Administradora |
| Cancelar própria reserva | Condômino |
| Cancelar qualquer reserva | Síndico, Administradora |
| Marcar como completed/no_show | Síndico, Funcionário |
| Visualizar próprias reservas | Condômino |
| Visualizar todas as reservas | Síndico, Administradora, Funcionário |

---

## 15. Testes

### Testes de Domínio

- Criação de reserva com todas as validações
- Prevenção de conflitos
- Transições de estado
- Regras de antecedência e duração
- Limites por unidade

### Testes de Integração

- Lock de concorrência
- Isolamento por tenant
- Persistência e consulta

### Testes de API

- Contratos de criação/cancelamento
- Permissões por papel
- Fluxo de aprovação

---

## 16. Anti-Padrões

- Verificação de conflito apenas no front-end
- Reserva sem vínculo com espaço
- Estado da reserva alterado sem evento de domínio
- Regras de negócio no controller
- Reserva acessível entre tenants
- Aprovação sem auditoria

---

## 17. O que esta skill NÃO cobre

- Cadastro e configuração de espaços (→ `spaces-management.md`)
- Regras de penalidade e bloqueio (→ `governance-rules.md`)
- Detalhes de convidados e prestadores (→ `people-control.md`)
- IA conversacional para reservas (→ `ai-integration.md`)

---

## 18. Status

Documento **OBRIGATÓRIO** para implementação do sistema de reservas.

Reservas são o **principal caso de uso** do produto e devem ser implementadas com máximo rigor.
