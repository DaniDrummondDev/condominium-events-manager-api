# Governance Rules — Governança, Regras e Penalidades
## FASE 8 — Core Domain
**Condominium Events Manager API**

---

## 1. Objetivo

Definir a arquitetura, regras e responsabilidades do **sistema de governança** do condomínio, garantindo:

- Regulamento interno configurável por condomínio
- Gestão de penalidades e bloqueios
- Aplicação automática de consequências
- Exceções documentadas e auditáveis
- Transparência e previsibilidade
- Isolamento total por tenant

Governança é o que transforma o sistema de **ferramenta de agendamento** em **plataforma de gestão**.

---

## 2. Dependências

Esta skill depende das seguintes skills:

- `spaces-management.md` — regras por espaço
- `reservation-system.md` — eventos que geram penalidades
- `access-control.md` — quem pode aplicar penalidades
- `audit-logging.md` — rastreabilidade obrigatória
- `event-driven-architecture.md` — eventos de domínio
- `notification-strategy.md` — comunicação de penalidades
- `lgpd-compliance.md` — dados pessoais envolvidos

---

## 3. Princípios Arquiteturais

### 3.1 Governança é domínio, não infraestrutura

Regras, penalidades e bloqueios são **entidades de domínio**.

- Vivem no Domain Layer
- Não dependem de framework
- São testáveis isoladamente

### 3.2 Regras são configuráveis, não hardcoded

Cada condomínio define suas próprias regras:

- O que gera penalidade
- Gravidade da penalidade
- Duração de bloqueios
- Critérios para exceções

### 3.3 Penalidade é consequência, não punição arbitrária

Toda penalidade deve:

- Ter origem rastreável (evento que a causou)
- Ter justificativa
- Ser comunicada ao afetado
- Ser passível de contestação

---

## 4. Documentos Legais (Condominium Documents)

### 4.1 Contexto

Na legislação brasileira, condomínios possuem documentos legais fundamentais:

- **Convenção do Condomínio** — Documento principal que rege o condomínio. Alteração requer quórum de 2/3 dos condôminos.
- **Regimento Interno** — Regras operacionais do dia a dia. Alteração por maioria simples em assembleia.
- **Atas de Assembleia** — Registro das decisões tomadas em assembleias.

Esses documentos são a **base legal** de todas as regras do condomínio.

### 4.2 Modelo: CondominiumDocument

Representa um documento legal completo com versionamento.

Campos conceituais:

- `id`
- `tenant_id`
- `type` (convencao, regimento_interno, ata_assembleia, other)
- `title`
- `version` (integer, incrementado a cada nova versão)
- `status` (draft, active, archived)
- `full_text` (conteúdo textual completo)
- `file_path` (PDF original, opcional)
- `file_hash` (SHA-256 para integridade)
- `approved_at` (data de aprovação em assembleia)
- `approved_in` (referência da assembleia)
- `created_by`
- `created_at`
- `updated_at`

### 4.3 Modelo: DocumentSection

Seção hierárquica de um documento (artigo, capítulo, parágrafo).

Campos conceituais:

- `id`
- `document_id`
- `parent_section_id` (auto-referência para hierarquia)
- `section_number` (ex: "Art. 15", "Cap. III", "§ 2º")
- `title`
- `content`
- `order_index`

### 4.4 Regras de Documentos

- Apenas um documento `active` por tipo no tenant
- Ativar novo documento arquiva automaticamente o anterior
- Documento deve ter seções parseadas antes de ser ativado
- Seções são imutáveis após ativação — nova versão = novo documento
- Apenas síndico pode ativar/arquivar documentos
- Condôminos podem consultar documentos ativos (somente leitura)

### 4.5 Integração com IA

- Cada `DocumentSection` gera embeddings via `ai_embeddings` (source_type = 'document_section')
- Permite busca semântica: "quais artigos falam sobre horário de silêncio?"
- IA pode referenciar artigos específicos ao responder perguntas de moradores

### 4.6 Integração com Regras

- `CondominiumRule.document_section_id` (FK opcional) vincula regra ao artigo de origem
- Permite rastrear a base legal de cada regra do regulamento
- Busca bidirecional: do artigo para regras derivadas e da regra para o artigo de origem

---

## 5. Regulamento Interno (Condominium Rules)

### 5.1 Modelo: CondominiumRule

Representa uma regra operacional do regulamento interno.

Campos conceituais:

- `id`
- `tenant_id`
- `category` (reservas, convidados, barulho, limpeza, etc.)
- `title`
- `description`
- `is_active` (boolean)
- `applies_to` (all_spaces, specific_space_ids)
- `document_section_id` (FK opcional para DocumentSection — origem legal da regra)
- `created_by`
- `created_at`
- `updated_at`

### 5.2 Regras

- Regulamento é definido pelo síndico/administradora
- Alterações são versionadas e auditadas
- Regras inativas não são aplicadas
- Condôminos podem consultar o regulamento vigente
- Regra pode referenciar a seção do documento legal de onde foi derivada

---

## 6. Infrações (Violations)

### 5.1 Modelo: Violation

Representa uma infração registrada contra uma unidade/condômino.

Campos conceituais:

- `id`
- `tenant_id`
- `unit_id`
- `user_id` (condômino)
- `reservation_id` (nullable — pode ser infração sem reserva)
- `rule_id` (nullable — regra violada)
- `type` (no_show, late_cancellation, damage, noise, overcrowding, other)
- `description`
- `severity` (minor, moderate, severe)
- `status` (open, acknowledged, contested, resolved)
- `registered_by` (user_id — quem registrou)
- `created_at`
- `resolved_at`

### 5.2 Tipos de Infração

| Tipo | Origem | Severidade típica |
|------|--------|-------------------|
| `no_show` | Automática (reserva não utilizada) | minor/moderate |
| `late_cancellation` | Automática (cancelamento tardio) | minor |
| `damage` | Manual (registro pelo síndico) | severe |
| `noise` | Manual | moderate |
| `overcrowding` | Manual | moderate |
| `rule_breach` | Manual (qualquer regra violada) | variável |

### 5.3 Infrações Automáticas

Infrações geradas automaticamente por eventos:

- `ReservationNoShow` → infração `no_show`
- Cancelamento após prazo → infração `late_cancellation`

Infrações automáticas seguem configuração do espaço e do regulamento.

---

## 7. Penalidades (Penalties)

### 6.1 Modelo: Penalty

Representa uma penalidade aplicada a uma unidade/condômino.

Campos conceituais:

- `id`
- `tenant_id`
- `unit_id`
- `user_id`
- `violation_id` (infração que originou)
- `type` (warning, temporary_block, reservation_limit_reduction, fine)
- `description`
- `starts_at`
- `ends_at` (nullable — bloqueios temporários)
- `status` (active, expired, revoked)
- `applied_by` (user_id ou system)
- `revoked_by` (nullable)
- `revocation_reason` (nullable)
- `created_at`

### 6.2 Tipos de Penalidade

| Tipo | Efeito |
|------|--------|
| `warning` | Aviso formal, sem bloqueio |
| `temporary_block` | Bloqueio de reservas por período |
| `reservation_limit_reduction` | Redução da cota mensal |
| `fine` | Multa financeira (informativa, não cobrada pelo sistema) |

### 6.3 Aplicação de Penalidades

#### Automática

Baseada em configuração:

- X no-shows → bloqueio de Y dias
- X cancelamentos tardios → redução de cota
- Configurável por espaço e por regulamento

#### Manual

Pelo síndico:

- Registra infração
- Aplica penalidade
- Justifica a decisão
- Condômino é notificado

---

## 8. Bloqueios (User Blocks)

### 7.1 Modelo Conceitual

Bloqueio impede que uma unidade/condômino faça reservas.

- `temporary_block` → tem data de início e fim
- Bloqueio é verificado na criação de reserva
- Bloqueio pode ser revogado pelo síndico

### 7.2 Verificação

No fluxo de criação de reserva:

1. Verificar se unidade tem penalidade ativa do tipo `temporary_block`
2. Se sim → negar reserva com motivo explícito

---

## 9. Contestação

### 8.1 Fluxo

1. Condômino recebe notificação de infração/penalidade
2. Condômino pode contestar
3. Síndico avalia a contestação
4. Síndico mantém, reduz ou revoga
5. Decisão é auditada e comunicada

### 8.2 Modelo: ViolationContestation

Campos conceituais:

- `id`
- `violation_id`
- `contested_by` (user_id)
- `reason`
- `status` (pending, accepted, rejected)
- `decided_by` (user_id)
- `decision_notes`
- `created_at`
- `decided_at`

---

## 10. Configuração de Penalidades Automáticas

### 9.1 Modelo: PenaltyPolicy

Permite configurar quando penalidades são aplicadas automaticamente.

Campos conceituais:

- `id`
- `tenant_id`
- `space_id` (nullable — se null, aplica a todos)
- `violation_type`
- `threshold` (quantas infrações para disparar)
- `penalty_type`
- `penalty_duration_days` (para bloqueios)
- `is_active`
- `created_at`

Exemplos:

| Tipo de infração | Threshold | Penalidade | Duração |
|-----------------|-----------|------------|---------|
| no_show | 2 em 30 dias | temporary_block | 15 dias |
| late_cancellation | 3 em 30 dias | warning | — |
| noise | 1 | temporary_block | 30 dias |

---

## 11. Eventos de Domínio

- `DocumentUploaded`
- `DocumentActivated`
- `DocumentArchived`
- `DocumentSectionsParsed`
- `ViolationRegistered`
- `ViolationContested`
- `ViolationResolved`
- `PenaltyApplied`
- `PenaltyExpired`
- `PenaltyRevoked`
- `UserBlocked`
- `UserUnblocked`

---

## 12. Isolamento por Tenant

Regras obrigatórias:

- Documentos legais são por tenant
- Regulamento é por tenant
- Infrações são por tenant
- Penalidades são por tenant
- Nenhuma política cruza tenants

---

## 13. Auditoria

Todas as seguintes ações são auditadas:

- Upload/ativação/arquivamento de documento legal
- Criação/alteração de regra do regulamento
- Registro de infração
- Aplicação de penalidade
- Revogação de penalidade
- Contestação e decisão
- Bloqueio e desbloqueio de condômino

---

## 14. Permissões

| Ação | Papéis permitidos |
|------|-------------------|
| Upload documento legal | Síndico, Administradora |
| Ativar/arquivar documento | Síndico |
| Consultar documentos ativos | Todas as roles |
| Buscar em documentos | Todas as roles |
| Definir regulamento | Síndico, Administradora |
| Registrar infração | Síndico, Funcionário |
| Aplicar penalidade | Síndico |
| Revogar penalidade | Síndico |
| Contestar infração | Condômino |
| Visualizar próprias infrações | Condômino |
| Visualizar todas as infrações | Síndico, Administradora |

---

## 15. Testes

### Testes de Domínio

- Registro automático de infração (no_show, late_cancellation)
- Aplicação automática de penalidade (threshold atingido)
- Bloqueio impedindo reserva
- Expiração de penalidade
- Contestação e revogação

### Testes de Integração

- Fluxo completo: reserva → no_show → infração → penalidade → bloqueio
- Isolamento por tenant
- Persistência e consulta

### Testes de API

- Contratos de infração e penalidade
- Permissões por papel
- Contestação

---

## 16. Anti-Padrões

- Penalidade sem justificativa
- Bloqueio sem data de expiração (exceto quando explicitamente permanente)
- Infração sem origem rastreável
- Regras hardcoded no código
- Penalidade aplicada sem notificação
- Governança acessível entre tenants

---

## 17. O que esta skill NÃO cobre

- Cadastro e configuração de espaços (→ `spaces-management.md`)
- Fluxo de reservas (→ `reservation-system.md`)
- Controle de convidados e prestadores (→ `people-control.md`)

---

## 18. Status

Documento **OBRIGATÓRIO** para implementação da governança.

Governança é o que **diferencia** esta plataforma de um simples sistema de agendamento.
