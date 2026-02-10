# Communication — Comunicação Interna do Condomínio
## FASE 8 — Core Domain
**Condominium Events Manager API**

---

## 1. Objetivo

Definir a arquitetura, regras e responsabilidades do **módulo de comunicação interna** do condomínio, garantindo:

- Avisos e comunicados do síndico para moradores
- Canal de atendimento entre moradores e administração
- Comunicação centralizada e rastreável
- Integração com notificações (e-mail, push)
- Isolamento total por tenant
- Conformidade com LGPD

A comunicação interna é **ferramenta de governança**, complementando reservas e regras.

---

## 2. Dependências

Esta skill depende das seguintes skills:

- `units-management.md` — moradores como destinatários
- `access-control.md` — quem pode publicar
- `audit-logging.md` — rastreabilidade
- `notification-strategy.md` — entrega de comunicações
- `lgpd-compliance.md` — dados pessoais em mensagens
- `feature-flag-strategy.md` — funcionalidade controlada por plano
- `event-driven-architecture.md` — eventos de comunicação

---

## 3. Princípios Arquiteturais

### 3.1 Comunicação é domínio do tenant

O módulo de comunicação pertence ao **contexto do tenant**.

- Não é funcionalidade de plataforma
- Cada condomínio tem sua comunicação isolada
- A plataforma não tem acesso ao conteúdo

### 3.2 Comunicação não é chat

O módulo **não é** um sistema de mensagens instantâneas.

É um sistema de:

- Avisos oficiais (um para muitos)
- Comunicados formais
- Solicitações e atendimento (canal estruturado)

### 3.3 Toda comunicação é rastreável

- Quem enviou
- Quando
- Para quem
- Se foi lida (quando aplicável)

---

## 4. Tipos de Comunicação

### 4.1 Avisos (Announcements)

Comunicação **um para muitos** do síndico/administração para moradores.

Exemplos:

- Aviso de manutenção
- Comunicado sobre regras
- Informativo de assembleia
- Alerta de segurança

Características:

- Autor: síndico ou administradora
- Destinatários: todos os moradores, bloco específico, ou unidades específicas
- Pode ter prioridade (normal, urgent)
- Pode ter data de expiração

### 4.2 Solicitações (Support Requests)

Comunicação **um para um** do morador para a administração.

Exemplos:

- Reportar problema em espaço comum
- Solicitar manutenção
- Reclamar de barulho
- Pedir informação

Características:

- Autor: condômino
- Destinatário: síndico/administração
- Possui status (open, in_progress, resolved, closed)
- Permite respostas (thread)

---

## 5. Entidades

### 5.1 Announcement (Aviso)

Campos conceituais:

- `id`
- `tenant_id`
- `title`
- `content` (texto do aviso)
- `priority` (normal, urgent)
- `audience` (all, block, units)
- `audience_filter` (nullable — block_ids ou unit_ids)
- `published_at`
- `expires_at` (nullable)
- `status` (draft, published, archived)
- `created_by` (user_id)
- `created_at`
- `updated_at`

---

### 5.2 AnnouncementRead (Confirmação de Leitura)

Campos conceituais:

- `id`
- `announcement_id`
- `user_id`
- `read_at`

---

### 5.3 SupportRequest (Solicitação)

Campos conceituais:

- `id`
- `tenant_id`
- `unit_id`
- `subject`
- `category` (maintenance, noise, security, general, other)
- `status` (open, in_progress, resolved, closed)
- `priority` (low, normal, high)
- `created_by` (user_id)
- `assigned_to` (user_id, nullable)
- `resolved_at`
- `closed_at`
- `created_at`
- `updated_at`

---

### 5.4 SupportMessage (Mensagem na Thread)

Campos conceituais:

- `id`
- `support_request_id`
- `sender_id` (user_id)
- `content`
- `is_internal` (boolean — visível apenas para administração)
- `created_at`

---

## 6. Estados da Solicitação

| Estado | Descrição |
|--------|-----------|
| `open` | Criada pelo morador, aguardando atendimento |
| `in_progress` | Em análise pela administração |
| `resolved` | Resolvida pela administração |
| `closed` | Fechada (pelo morador ou por timeout) |

Transições:

- `open` → `in_progress` → `resolved` → `closed`
- `open` → `closed` (morador cancela)
- `resolved` → `open` (morador reabre)

---

## 7. Fluxos

### 7.1 Fluxo de Aviso

1. Síndico cria aviso (rascunho ou publicação direta)
2. Define audiência (todos, bloco, unidades)
3. Publica o aviso
4. Sistema dispara notificações para destinatários
5. Moradores visualizam e confirmam leitura

### 7.2 Fluxo de Solicitação

1. Morador cria solicitação com categoria e descrição
2. Síndico/administração recebe notificação
3. Administração responde (pode ser mensagem interna ou visível)
4. Thread de respostas até resolução
5. Administração marca como resolvida
6. Morador confirma ou reabre

---

## 8. Notificações Integradas

Eventos que geram notificação:

- Novo aviso publicado → moradores destinatários
- Nova solicitação criada → síndico
- Resposta em solicitação → autor ou administração
- Solicitação resolvida → morador

Canal: conforme `notification-strategy.md` (e-mail como padrão).

---

## 9. Isolamento por Tenant

Regras obrigatórias:

- Toda comunicação pertence a um tenant
- Nenhum aviso ou solicitação cruza tenants
- Queries sempre escopadas por tenant_id
- Conteúdo de comunicação nunca acessível pela plataforma

---

## 10. LGPD

- Conteúdo de mensagens pode conter dados pessoais
- Retenção conforme política do tenant
- Anonimização de mensagens quando morador solicita exclusão
- Mensagens não usadas para IA sem base legal

---

## 11. Limites por Plano

Features controladas:

- `communication_module` (boolean — ativo ou não)
- `max_announcements_per_month` (limite de avisos)
- `support_requests` (boolean — canal de atendimento ativo)

---

## 12. Eventos de Domínio

- `AnnouncementPublished`
- `AnnouncementArchived`
- `SupportRequestCreated`
- `SupportRequestUpdated`
- `SupportRequestResolved`
- `SupportRequestClosed`
- `SupportMessageSent`

---

## 13. Auditoria

Eventos auditáveis:

- Publicação de aviso
- Criação de solicitação
- Mudança de status de solicitação
- Mensagens em thread (sem conteúdo, apenas metadata)

---

## 14. Permissões

| Ação | Papéis permitidos |
|------|-------------------|
| Criar aviso | Síndico, Administradora |
| Arquivar aviso | Síndico, Administradora |
| Visualizar avisos | Todos os moradores |
| Criar solicitação | Condômino |
| Responder solicitação | Síndico, Administradora, Funcionário |
| Visualizar próprias solicitações | Condômino |
| Visualizar todas as solicitações | Síndico, Administradora |
| Fechar solicitação | Condômino (própria), Síndico |

---

## 15. Integração com IA (Futuro)

A IA poderá:

- Classificar solicitações automaticamente
- Sugerir respostas padrão
- Resumir avisos para moradores

A IA **não pode**:

- Responder solicitações sem confirmação humana
- Publicar avisos automaticamente
- Acessar conteúdo de outros tenants

---

## 16. Testes

### Testes de Domínio

- Criação e publicação de aviso
- Filtro de audiência (todos, bloco, unidades)
- Fluxo de solicitação (open → resolved → closed)
- Thread de mensagens

### Testes de Integração

- Notificações disparadas corretamente
- Isolamento por tenant
- Confirmação de leitura

### Testes de API

- Contratos de avisos e solicitações
- Permissões por papel
- Filtros de audiência

---

## 17. Anti-Padrões

- Comunicação sem vínculo com tenant
- Aviso sem autor identificável
- Solicitação sem categoria
- Mensagens sem rastreabilidade
- Conteúdo acessível pela plataforma admin
- Módulo de comunicação como chat em tempo real

---

## 18. O que esta skill NÃO cobre

- Notificações transacionais do sistema (→ `notification-strategy.md`)
- Comunicação com fornecedores externos (→ `ai-integration.md`, futuro marketplace)
- Mensageria em tempo real (WebSocket — fora do escopo atual)

---

## 19. Status

Documento **OBRIGATÓRIO** para implementação da comunicação interna.

Sem comunicação, o condomínio depende de canais informais (WhatsApp, papel) — exatamente o problema que este produto resolve.
