# Ubiquitous Language — Linguagem Ubíqua

## 1. Objetivo

Este glossário define os **termos oficiais** usados no projeto. Toda comunicação — código, documentação, API, testes — deve usar exclusivamente estes termos.

Termos em inglês são usados **no código**. Termos em português são usados na **documentação de negócio e comunicação com stakeholders**.

---

## 2. Platform Domain

| Termo (EN) | Termo (PT) | Definição |
|------------|------------|-----------|
| **Platform** | Plataforma | O sistema SaaS como um todo, gerenciado pelo owner |
| **Platform Owner** | Dono da Plataforma | Empresa/pessoa que opera o SaaS |
| **Platform Admin** | Administrador da Plataforma | Usuário com acesso ao painel de gestão do SaaS |
| **Tenant** | Tenant / Inquilino | Um condomínio ou administradora cadastrada na plataforma. Unidade de isolamento |
| **Tenant Provisioning** | Provisionamento | Processo de criação de banco/schema e configuração inicial de um tenant |
| **Plan** | Plano | Contrato comercial que define recursos, limites e preço |
| **Plan Version** | Versão do Plano | Versão específica de um plano (preço, features) |
| **Subscription** | Assinatura | Vínculo ativo entre tenant e plano. Controla acesso ao sistema |
| **Invoice** | Fatura | Documento de cobrança gerado por ciclo de assinatura |
| **Payment** | Pagamento | Transação financeira vinculada a uma fatura |
| **Dunning** | Inadimplência | Processo de gestão de faturas vencidas e recuperação de pagamento |
| **Grace Period** | Período de Carência | Tempo entre falha de pagamento e suspensão do tenant |
| **Feature Flag** | Feature Flag | Recurso controlável por plano ou override (boolean, numérico ou enum) |
| **Feature Override** | Override de Feature | Exceção de feature aplicada a um tenant específico |

---

## 3. Tenant Domain — Unidades e Moradores

| Termo (EN) | Termo (PT) | Definição |
|------------|------------|-----------|
| **Block** | Bloco | Edificação dentro do condomínio (ex: "Bloco A", "Torre 1"). Apenas para condomínios verticais |
| **Unit** | Unidade | Unidade residencial (apartamento ou casa). Identificada por número dentro do bloco ou do condomínio |
| **Resident** | Morador | Pessoa vinculada a uma unidade. Possui papel: proprietário, inquilino ou dependente |
| **Owner** | Proprietário | Morador que é dono da unidade |
| **Tenant Resident** | Inquilino | Morador que aluga a unidade (não confundir com Tenant do SaaS) |
| **Dependent** | Dependente | Familiar ou pessoa autorizada vinculada à unidade |
| **Tenant User** | Usuário do Tenant | Conta de acesso ao sistema dentro de um tenant |
| **Invitation** | Convite | Processo de onboarding de morador via e-mail |

---

## 4. Tenant Domain — Espaços

| Termo (EN) | Termo (PT) | Definição |
|------------|------------|-----------|
| **Space** | Espaço Comum | Área compartilhada do condomínio (salão, churrasqueira, quadra, etc.) |
| **Space Type** | Tipo de Espaço | Classificação do espaço (party_hall, bbq_area, court, pool, gym, etc.) |
| **Space Availability** | Disponibilidade | Horários em que o espaço pode ser reservado (por dia da semana) |
| **Space Block** | Bloqueio de Espaço | Período em que o espaço está indisponível (manutenção, feriado, etc.) |
| **Space Rule** | Regra do Espaço | Configuração específica (max convidados, taxa de limpeza, horário de silêncio, etc.) |

---

## 5. Tenant Domain — Reservas

| Termo (EN) | Termo (PT) | Definição |
|------------|------------|-----------|
| **Reservation** | Reserva | Solicitação de uso de espaço comum por um morador em data/horário específico. **Aggregate Root** |
| **Pending Approval** | Aguardando Aprovação | Reserva que requer aprovação do síndico antes de confirmar |
| **Confirmed** | Confirmada | Reserva aprovada e ativa |
| **Rejected** | Rejeitada | Reserva negada pelo síndico |
| **Canceled** | Cancelada | Reserva cancelada pelo morador ou síndico |
| **Completed** | Concluída | Evento realizado com sucesso |
| **No-Show** | Não Comparecimento | Reserva confirmada mas não utilizada pelo morador |
| **Conflict** | Conflito | Tentativa de reservar espaço já ocupado no mesmo período |
| **Approval Flow** | Fluxo de Aprovação | Processo onde reserva aguarda decisão do síndico |

---

## 6. Tenant Domain — Governança

| Termo (EN) | Termo (PT) | Definição |
|------------|------------|-----------|
| **Condominium Rule** | Regra do Regulamento | Regra definida no regulamento interno do condomínio |
| **Violation** | Infração | Registro de descumprimento de regra por uma unidade/morador |
| **Penalty** | Penalidade | Consequência aplicada por infração (aviso, bloqueio, multa informativa) |
| **Penalty Policy** | Política de Penalidade | Configuração automática: X infrações → penalidade Y |
| **Temporary Block** | Bloqueio Temporário | Impedimento de fazer reservas por período determinado |
| **Warning** | Advertência | Penalidade leve, sem bloqueio |
| **Contestation** | Contestação | Recurso do morador contra infração ou penalidade |
| **Severity** | Gravidade | Nível da infração: minor (leve), moderate (moderada), severe (grave) |

---

## 7. Tenant Domain — Controle de Pessoas

| Termo (EN) | Termo (PT) | Definição |
|------------|------------|-----------|
| **Guest** | Convidado | Pessoa externa convidada para um evento/reserva |
| **Service Provider** | Prestador de Serviço | Profissional ou empresa contratada para atuar no condomínio |
| **Check-in** | Entrada | Registro de chegada no condomínio (feito pela portaria) |
| **Check-out** | Saída | Registro de saída do condomínio |
| **Access Validation** | Validação de Acesso | Verificação pela portaria se pessoa tem autorização de entrada |

---

## 8. Tenant Domain — Comunicação

| Termo (EN) | Termo (PT) | Definição |
|------------|------------|-----------|
| **Announcement** | Aviso / Comunicado | Mensagem oficial do síndico para moradores (um para muitos) |
| **Support Request** | Solicitação | Chamado do morador para a administração (um para um, com thread) |
| **Support Message** | Mensagem | Resposta dentro de uma solicitação |
| **Audience** | Audiência | Destinatários de um aviso (todos, bloco, unidades específicas) |

---

## 9. Papéis (Roles)

| Papel (EN) | Papel (PT) | Escopo | Descrição |
|------------|------------|--------|-----------|
| **Platform Owner** | Dono da Plataforma | Plataforma | Controle total do SaaS |
| **Platform Admin** | Admin da Plataforma | Plataforma | Gestão de tenants e billing |
| **Síndico** | Síndico | Tenant | Governa o condomínio, aprova reservas, aplica penalidades |
| **Administradora** | Administradora | Tenant | Gestão administrativa, pode supervisionar múltiplos condomínios |
| **Condômino** | Condômino | Tenant | Morador, solicita reservas, registra convidados |
| **Funcionário** | Funcionário | Tenant | Portaria, zeladoria — check-in/check-out, registros operacionais |

---

## 10. Termos Técnicos

| Termo | Definição |
|-------|-----------|
| **Aggregate Root** | Entidade principal que controla consistência do agregado (ex: Reservation) |
| **Value Object** | Objeto imutável sem identidade própria (ex: DateRange, Money) |
| **Domain Event** | Fato ocorrido no domínio, representado no passado (ex: ReservationConfirmed) |
| **Use Case** | Operação de aplicação que orquestra uma ação (ex: CreateReservation) |
| **Policy** | Regra de autorização codificada (ex: CanCreateReservationPolicy) |
| **Idempotency Key** | Chave que garante que operação repetida não gera efeito duplicado |
| **DLQ (Dead Letter Queue)** | Fila para mensagens/eventos que falharam após todas as tentativas |
| **Tool (AI)** | Ação que a IA pode propor ao sistema (mapeada para um Use Case) |
| **Embedding** | Representação vetorial de texto para busca semântica (pgvector) |

---

## 11. Convenções de Nomenclatura no Código

| Contexto | Convenção | Exemplo |
|----------|-----------|---------|
| Entidades | PascalCase, singular | `Reservation`, `Space`, `Unit` |
| Value Objects | PascalCase, descritivo | `DateRange`, `Money`, `Address` |
| Use Cases | Verbo + Substantivo | `CreateReservation`, `CancelReservation` |
| Eventos | Substantivo + Particípio | `ReservationConfirmed`, `PenaltyApplied` |
| Policies | Can + Ação + Policy | `CanCreateReservationPolicy` |
| Jobs | Verbo + Contexto + Job | `ProcessPastDueInvoicesJob` |
| Tabelas (DB) | snake_case, plural | `reservations`, `spaces`, `units` |
| Colunas (DB) | snake_case | `tenant_id`, `created_at`, `space_id` |

---

## 12. Status

Documento **ATIVO**. Toda comunicação no projeto deve usar estes termos.

Novos termos devem ser adicionados aqui antes de serem usados em código ou documentação.
