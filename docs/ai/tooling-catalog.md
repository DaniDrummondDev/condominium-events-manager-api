# Tooling Catalog — Condominium Events Manager API

## Objetivo

Definir o catalogo formal de tools que podem ser utilizadas por sistemas de IA de forma segura, auditavel e alinhada ao dominio do negocio.

---

## Principios

1. Uma tool representa **um caso de uso de negocio**, nao uma operacao tecnica.
2. Nenhuma tool expoe SQL, filtros livres ou acesso generico a APIs.
3. Toda tool valida: tenant, permissoes, regras de negocio, auditoria.
4. Tools transacionais exigem confirmacao humana.
5. Parametros sao sempre tipados e enumerados.
6. Cada tool mapeia para um Use Case existente no Application Layer.

---

## Schema Padrao por Tool

Toda tool segue esta ficha:

```
Nome: nome_snake_case
Categoria: Contexto | Consulta | Validacao | Transacional | Comunicacao | Suporte | Admin
Descricao: O que faz em uma frase
Input: { parametros tipados }
Output: { formato da resposta }
Confirmacao humana: Sim | Nao
Use Case mapeado: NomeDoUseCase
Role minimo: sindico | administradora | condomino
Feature flag: nome_do_flag | null
Rate limit: X req/min por usuario
Efeitos colaterais: eventos disparados, notificacoes
```

---

## 1. Tools de Contexto

Retornam informacoes sobre o usuario e ambiente atual. Nao alteram estado.

### 1.1 obter_contexto_usuario

| Campo | Valor |
|-------|-------|
| **Categoria** | Contexto |
| **Descricao** | Retorna informacoes basicas do usuario autenticado |
| **Input** | Nenhum (inferido do token JWT) |
| **Output** | `{ user_id, name, role, condominium_id, condominium_name, unit_id, permissions[] }` |
| **Confirmacao humana** | Nao |
| **Use Case** | GetAuthenticatedUserContext |
| **Role minimo** | condomino |
| **Feature flag** | ai_assistant |
| **Rate limit** | 30 req/min |
| **Efeitos colaterais** | Nenhum |

### 1.2 listar_condominios_usuario

| Campo | Valor |
|-------|-------|
| **Categoria** | Contexto |
| **Descricao** | Lista condominios acessiveis pelo usuario |
| **Input** | Nenhum |
| **Output** | `[{ condominium_id, name, slug, role, status }]` |
| **Confirmacao humana** | Nao |
| **Use Case** | ListUserCondominiums |
| **Role minimo** | condomino |
| **Feature flag** | ai_assistant |
| **Rate limit** | 10 req/min |
| **Efeitos colaterais** | Nenhum |

---

## 2. Tools de Consulta (Read-only)

Retornam dados do dominio. Nunca alteram estado. Limites de volume e tempo obrigatorios.

### 2.1 listar_reservas

| Campo | Valor |
|-------|-------|
| **Categoria** | Consulta |
| **Descricao** | Lista reservas do condominio com filtros controlados |
| **Input** | `{ periodo_inicio: date, periodo_fim: date, status?: enum, espaco_id?: uuid }` |
| **Output** | `[{ reservation_id, space_name, date, start_time, end_time, status, created_by }]` |
| **Confirmacao humana** | Nao |
| **Use Case** | ListReservations |
| **Role minimo** | condomino (apenas proprias), sindico/administradora (todas) |
| **Feature flag** | ai_assistant |
| **Rate limit** | 20 req/min |
| **Efeitos colaterais** | Nenhum |
| **Restricoes** | Periodo maximo: 90 dias. Limite: 100 resultados. Condomino ve apenas suas reservas. |

### 2.2 buscar_reserva_por_id

| Campo | Valor |
|-------|-------|
| **Categoria** | Consulta |
| **Descricao** | Retorna dados completos de uma reserva especifica |
| **Input** | `{ reservation_id: uuid }` |
| **Output** | `{ reservation_id, space, date, times, status, guests_count, created_by, approval_status, history[] }` |
| **Confirmacao humana** | Nao |
| **Use Case** | GetReservationDetails |
| **Role minimo** | condomino (apenas proprias), sindico/administradora (todas) |
| **Feature flag** | ai_assistant |
| **Rate limit** | 30 req/min |
| **Efeitos colaterais** | Nenhum |

### 2.3 listar_espacos

| Campo | Valor |
|-------|-------|
| **Categoria** | Consulta |
| **Descricao** | Lista espacos comuns disponiveis no condominio |
| **Input** | `{ status?: enum(active, inactive, maintenance) }` |
| **Output** | `[{ space_id, name, type, capacity, requires_approval, status }]` |
| **Confirmacao humana** | Nao |
| **Use Case** | ListSpaces |
| **Role minimo** | condomino |
| **Feature flag** | ai_assistant |
| **Rate limit** | 20 req/min |
| **Efeitos colaterais** | Nenhum |

### 2.4 estatisticas_uso_espacos

| Campo | Valor |
|-------|-------|
| **Categoria** | Consulta |
| **Descricao** | Retorna metricas agregadas de uso de espacos |
| **Input** | `{ periodo_inicio: date, periodo_fim: date, espaco_id?: uuid }` |
| **Output** | `{ total_reservations, occupancy_rate, peak_hours[], most_used_spaces[], cancellation_rate }` |
| **Confirmacao humana** | Nao |
| **Use Case** | GetSpaceUsageStatistics |
| **Role minimo** | sindico, administradora |
| **Feature flag** | ai_assistant |
| **Rate limit** | 10 req/min |
| **Efeitos colaterais** | Nenhum |
| **Restricoes** | Periodo maximo: 365 dias. Dados sempre agregados, nunca individuais. |

### 2.5 listar_avisos

| Campo | Valor |
|-------|-------|
| **Categoria** | Consulta |
| **Descricao** | Lista avisos/comunicados do condominio |
| **Input** | `{ periodo_inicio?: date, periodo_fim?: date, lido?: bool }` |
| **Output** | `[{ announcement_id, title, audience, published_at, read_status }]` |
| **Confirmacao humana** | Nao |
| **Use Case** | ListAnnouncements |
| **Role minimo** | condomino |
| **Feature flag** | ai_assistant |
| **Rate limit** | 20 req/min |
| **Efeitos colaterais** | Nenhum |

### 2.6 listar_solicitacoes_suporte

| Campo | Valor |
|-------|-------|
| **Categoria** | Consulta |
| **Descricao** | Lista solicitacoes de suporte |
| **Input** | `{ status?: enum(open, in_progress, resolved, closed) }` |
| **Output** | `[{ request_id, subject, status, created_at, last_message_at }]` |
| **Confirmacao humana** | Nao |
| **Use Case** | ListSupportRequests |
| **Role minimo** | condomino (proprias), sindico/administradora (todas) |
| **Feature flag** | ai_assistant |
| **Rate limit** | 20 req/min |
| **Efeitos colaterais** | Nenhum |

---

## 3. Tools de Validacao

Verificam viabilidade antes de executar acoes. Nao alteram estado.

### 3.1 validar_disponibilidade_espaco

| Campo | Valor |
|-------|-------|
| **Categoria** | Validacao |
| **Descricao** | Verifica conflitos de agenda para um espaco em data/horario especificos |
| **Input** | `{ espaco_id: uuid, data: date, hora_inicio: time, hora_fim: time }` |
| **Output** | `{ available: bool, conflicts?: [{ reservation_id, time_range }], suggested_slots?: [{ start, end }] }` |
| **Confirmacao humana** | Nao |
| **Use Case** | CheckSpaceAvailability |
| **Role minimo** | condomino |
| **Feature flag** | ai_assistant |
| **Rate limit** | 30 req/min |
| **Efeitos colaterais** | Nenhum |

### 3.2 validar_capacidade_evento

| Campo | Valor |
|-------|-------|
| **Categoria** | Validacao |
| **Descricao** | Valida se quantidade de convidados e compativel com o espaco |
| **Input** | `{ espaco_id: uuid, guests_count: int }` |
| **Output** | `{ valid: bool, space_capacity: int, exceeds_by?: int }` |
| **Confirmacao humana** | Nao |
| **Use Case** | ValidateEventCapacity |
| **Role minimo** | condomino |
| **Feature flag** | ai_assistant |
| **Rate limit** | 30 req/min |
| **Efeitos colaterais** | Nenhum |

### 3.3 avaliar_necessidade_aprovacao

| Campo | Valor |
|-------|-------|
| **Categoria** | Validacao |
| **Descricao** | Avalia se uma reserva exige aprovacao formal com base nas regras do condominio |
| **Input** | `{ espaco_id: uuid, guests_count: int, data: date }` |
| **Output** | `{ requires_approval: bool, reason?: string, approver_role?: string }` |
| **Confirmacao humana** | Nao |
| **Use Case** | EvaluateApprovalRequirement |
| **Role minimo** | condomino |
| **Feature flag** | ai_assistant |
| **Rate limit** | 30 req/min |
| **Efeitos colaterais** | Nenhum |

### 3.4 verificar_penalidades_ativas

| Campo | Valor |
|-------|-------|
| **Categoria** | Validacao |
| **Descricao** | Verifica se o usuario tem penalidades ativas que bloqueiam reservas |
| **Input** | `{ user_id?: uuid }` (se vazio, usa usuario autenticado) |
| **Output** | `{ has_active_penalties: bool, penalties?: [{ type, reason, expires_at }] }` |
| **Confirmacao humana** | Nao |
| **Use Case** | CheckActivePenalties |
| **Role minimo** | condomino (proprias), sindico/administradora (qualquer) |
| **Feature flag** | ai_assistant |
| **Rate limit** | 20 req/min |
| **Efeitos colaterais** | Nenhum |

---

## 4. Tools Transacionais

Alteram estado do sistema. Confirmacao humana obrigatoria.

### 4.1 criar_reserva

| Campo | Valor |
|-------|-------|
| **Categoria** | Transacional |
| **Descricao** | Cria uma reserva aplicando todas as validacoes e regras |
| **Input** | `{ espaco_id: uuid, data: date, hora_inicio: time, hora_fim: time, guests_count: int, observacao?: string }` |
| **Output** | `{ reservation_id: uuid, status: string, requires_approval: bool }` |
| **Confirmacao humana** | **Sim** |
| **Use Case** | CreateReservation |
| **Role minimo** | condomino |
| **Feature flag** | ai_assistant |
| **Rate limit** | 5 req/min |
| **Efeitos colaterais** | Evento `ReservationCreated`, notificacao ao aprovador (se aplicavel) |

### 4.2 cancelar_reserva

| Campo | Valor |
|-------|-------|
| **Categoria** | Transacional |
| **Descricao** | Cancela uma reserva respeitando prazos e permissoes |
| **Input** | `{ reservation_id: uuid, motivo: string }` |
| **Output** | `{ canceled: bool, penalty_applied?: bool, penalty_type?: string }` |
| **Confirmacao humana** | **Sim** |
| **Use Case** | CancelReservation |
| **Role minimo** | condomino (proprias), sindico/administradora (todas) |
| **Feature flag** | ai_assistant |
| **Rate limit** | 5 req/min |
| **Efeitos colaterais** | Evento `ReservationCanceled`, possivel infracao automatica por cancelamento tardio |

### 4.3 aprovar_reserva

| Campo | Valor |
|-------|-------|
| **Categoria** | Transacional |
| **Descricao** | Aprova uma reserva pendente de aprovacao |
| **Input** | `{ reservation_id: uuid, observacao?: string }` |
| **Output** | `{ approved: bool, reservation_status: string }` |
| **Confirmacao humana** | **Sim** |
| **Use Case** | ApproveReservation |
| **Role minimo** | sindico, administradora |
| **Feature flag** | ai_assistant |
| **Rate limit** | 10 req/min |
| **Efeitos colaterais** | Evento `ReservationApproved`, notificacao ao solicitante |

### 4.4 registrar_convidados

| Campo | Valor |
|-------|-------|
| **Categoria** | Transacional |
| **Descricao** | Registra convidados vinculados a uma reserva |
| **Input** | `{ reservation_id: uuid, convidados: [{ nome: string, documento?: string }] }` |
| **Output** | `{ registered_count: int, guest_ids: uuid[] }` |
| **Confirmacao humana** | **Sim** |
| **Use Case** | RegisterGuests |
| **Role minimo** | condomino (propria reserva), sindico/administradora |
| **Feature flag** | ai_assistant |
| **Rate limit** | 10 req/min |
| **Efeitos colaterais** | Dados pessoais — LGPD aplicavel |

### 4.5 solicitar_aprovacao_evento

| Campo | Valor |
|-------|-------|
| **Categoria** | Transacional |
| **Descricao** | Inicia workflow de aprovacao para reserva |
| **Input** | `{ reservation_id: uuid }` |
| **Output** | `{ approval_request_id: uuid, status: string }` |
| **Confirmacao humana** | **Sim** |
| **Use Case** | RequestReservationApproval |
| **Role minimo** | condomino |
| **Feature flag** | ai_assistant |
| **Rate limit** | 5 req/min |
| **Efeitos colaterais** | Notificacao ao aprovador |

---

## 5. Tools de Comunicacao

Enviam mensagens e notificacoes usando templates aprovados.

### 5.1 rascunhar_aviso

| Campo | Valor |
|-------|-------|
| **Categoria** | Comunicacao |
| **Descricao** | Gera rascunho de aviso/comunicado para revisao |
| **Input** | `{ titulo: string, contexto: string, audiencia: enum(todos, bloco, unidades) }` |
| **Output** | `{ draft_title: string, draft_body: string, target_audience: string }` |
| **Confirmacao humana** | **Sim** (antes de publicar) |
| **Use Case** | DraftAnnouncement |
| **Role minimo** | sindico, administradora |
| **Feature flag** | ai_assistant |
| **Rate limit** | 5 req/min |
| **Efeitos colaterais** | Nenhum (rascunho apenas) |

### 5.2 sugerir_resposta_suporte

| Campo | Valor |
|-------|-------|
| **Categoria** | Comunicacao |
| **Descricao** | Sugere resposta para solicitacao de suporte com base no historico e regras |
| **Input** | `{ support_request_id: uuid }` |
| **Output** | `{ suggested_response: string, referenced_rules?: string[], confidence: float }` |
| **Confirmacao humana** | **Sim** (antes de enviar) |
| **Use Case** | SuggestSupportResponse |
| **Role minimo** | sindico, administradora |
| **Feature flag** | ai_assistant |
| **Rate limit** | 10 req/min |
| **Efeitos colaterais** | Usa RAG para buscar regras e historico similar |

### 5.3 notificar_responsaveis

| Campo | Valor |
|-------|-------|
| **Categoria** | Comunicacao |
| **Descricao** | Dispara notificacoes usando templates aprovados |
| **Input** | `{ template_id: string, destinatarios: enum(sindico, aprovadores, unidade), contexto: object }` |
| **Output** | `{ sent_count: int, channel: string }` |
| **Confirmacao humana** | **Sim** |
| **Use Case** | NotifyStakeholders |
| **Role minimo** | sindico, administradora |
| **Feature flag** | ai_assistant |
| **Rate limit** | 5 req/min |
| **Efeitos colaterais** | Notificacoes enviadas via fila assincrona |

---

## 6. Tools de Suporte e Explicacao

Explicam decisoes do sistema e fornecem contexto. Read-only.

### 6.1 obter_log_decisao

| Campo | Valor |
|-------|-------|
| **Categoria** | Suporte |
| **Descricao** | Retorna justificativas e regras aplicadas em decisoes do sistema |
| **Input** | `{ resource_type: enum(reservation, penalty, approval), resource_id: uuid }` |
| **Output** | `{ decision: string, rules_applied: string[], timestamp: datetime, decided_by: string }` |
| **Confirmacao humana** | Nao |
| **Use Case** | GetDecisionLog |
| **Role minimo** | condomino (proprias), sindico/administradora (todas) |
| **Feature flag** | ai_assistant |
| **Rate limit** | 20 req/min |
| **Efeitos colaterais** | Nenhum |

### 6.2 explicar_regra

| Campo | Valor |
|-------|-------|
| **Categoria** | Suporte |
| **Descricao** | Explica uma regra do condominio em linguagem acessivel usando RAG |
| **Input** | `{ pergunta: string }` |
| **Output** | `{ explicacao: string, regras_referenciadas: string[], fontes: string[] }` |
| **Confirmacao humana** | Nao |
| **Use Case** | ExplainRule (RAG) |
| **Role minimo** | condomino |
| **Feature flag** | ai_assistant |
| **Rate limit** | 10 req/min |
| **Efeitos colaterais** | Consulta embeddings de regulamentos e politicas |

---

## 7. Tools Administrativas

Acesso restrito a gestores. Permitem simulacao e configuracao.

### 7.1 listar_regras_condominio

| Campo | Valor |
|-------|-------|
| **Categoria** | Admin |
| **Descricao** | Lista regras vigentes do condominio |
| **Input** | `{ categoria?: string }` |
| **Output** | `[{ rule_id, category, description, active, version }]` |
| **Confirmacao humana** | Nao |
| **Use Case** | ListCondominiumRules |
| **Role minimo** | sindico, administradora |
| **Feature flag** | ai_assistant |
| **Rate limit** | 10 req/min |
| **Efeitos colaterais** | Nenhum |

### 7.2 simular_regra_evento

| Campo | Valor |
|-------|-------|
| **Categoria** | Admin |
| **Descricao** | Simula o comportamento de regras sem persistir dados |
| **Input** | `{ espaco_id: uuid, data: date, hora_inicio: time, hora_fim: time, guests_count: int }` |
| **Output** | `{ would_require_approval: bool, would_apply_penalty: bool, rules_triggered: string[], conflicts: object[] }` |
| **Confirmacao humana** | Nao |
| **Use Case** | SimulateEventRules |
| **Role minimo** | sindico, administradora |
| **Feature flag** | ai_assistant |
| **Rate limit** | 10 req/min |
| **Efeitos colaterais** | Nenhum (simulacao apenas) |

### 7.3 resumo_operacional

| Campo | Valor |
|-------|-------|
| **Categoria** | Admin |
| **Descricao** | Gera resumo operacional do condominio para periodo |
| **Input** | `{ periodo_inicio: date, periodo_fim: date }` |
| **Output** | `{ total_reservations, cancellations, pending_approvals, active_penalties, open_support_requests, occupancy_summary }` |
| **Confirmacao humana** | Nao |
| **Use Case** | GenerateOperationalSummary |
| **Role minimo** | sindico, administradora |
| **Feature flag** | ai_assistant |
| **Rate limit** | 5 req/min |
| **Efeitos colaterais** | Nenhum |

---

## 8. Tools Futuras (Marketplace / Fornecedores)

Estas tools dependem de integracao com fornecedores externos (marketplace separado).

### 8.1 sugerir_fornecedores

| Campo | Valor |
|-------|-------|
| **Categoria** | Consulta |
| **Input** | `{ tipo_servico: string, contexto?: string }` |
| **Feature flag** | ai_vendor_integration |
| **Status** | Planejada |

### 8.2 solicitar_orcamento

| Campo | Valor |
|-------|-------|
| **Categoria** | Transacional |
| **Input** | `{ vendor_id: uuid, escopo_servico: string }` |
| **Confirmacao humana** | **Sim** |
| **Feature flag** | ai_vendor_integration |
| **Status** | Planejada |

### 8.3 confirmar_pedido_fornecedor

| Campo | Valor |
|-------|-------|
| **Categoria** | Transacional |
| **Input** | `{ quote_id: uuid }` |
| **Confirmacao humana** | **Sim** |
| **Feature flag** | ai_vendor_integration |
| **Status** | Planejada |

---

## 9. Resumo por Categoria

| Categoria | Quantidade | Altera Estado | Confirmacao | Exemplo |
|-----------|:----------:|:-------------:|:-----------:|---------|
| Contexto | 2 | Nao | Nao | obter_contexto_usuario |
| Consulta | 6 | Nao | Nao | listar_reservas |
| Validacao | 4 | Nao | Nao | validar_disponibilidade_espaco |
| Transacional | 5 | **Sim** | **Sim** | criar_reserva |
| Comunicacao | 3 | Sim/Parcial | **Sim** | rascunhar_aviso |
| Suporte | 2 | Nao | Nao | explicar_regra |
| Admin | 3 | Nao | Nao | simular_regra_evento |
| **Futuras** | 3 | Sim | Sim | sugerir_fornecedores |
| **Total** | **28** | | | |

---

## 10. Versionamento

- Toda alteracao de comportamento de uma tool exige nova versao.
- Tools removidas sao depreciadas antes de exclusao (min. 30 dias).
- Contratos de input/output nunca sao quebrados silenciosamente.
- Formato: `v1`, `v2`, etc. no registro da tool.
