# Notification Strategy — Estratégia de Notificações
## FASE 6 — Operação & Confiabilidade
**Condominium Events Manager API**

---

## 1. Objetivo

Definir a estratégia oficial de **notificações** do sistema, garantindo:

- Comunicação centralizada e desacoplada
- Suporte a múltiplos canais (e-mail, push, SMS, WhatsApp)
- Isolamento por tenant
- Auditoria completa de envios
- Templates configuráveis
- Priorização e throttling
- Resiliência a falhas de provedores

Notificações são **infraestrutura transversal**, não funcionalidade de domínio.

---

## 2. Princípios Não Negociáveis

- Notificações nunca contêm lógica de negócio
- Notificações são disparadas por eventos ou jobs, nunca por controllers
- Toda notificação pertence a um tenant
- Toda notificação é auditável
- Falha de notificação nunca bloqueia operação principal
- Dados pessoais em notificações seguem LGPD
- Provedores de envio são abstraídos por interfaces

---

## 3. Tipos de Notificação

### 3.1 Transacionais

Disparadas por eventos do sistema.

Exemplos:

- Confirmação de reserva
- Cancelamento de reserva
- Pagamento confirmado
- Fatura gerada
- Conta ativada

Regras:

- Envio imediato ou quase imediato
- Alta prioridade
- Obrigatórias (não podem ser desativadas pelo usuário)

---

### 3.2 Operacionais

Relacionadas à operação da plataforma.

Exemplos:

- Aviso de inadimplência (dunning)
- Expiração de trial
- Suspensão de tenant
- Mudança de plano

Regras:

- Podem ter delay controlado
- Prioridade média/alta

---

### 3.3 Informativas

Comunicações não críticas.

Exemplos:

- Dicas de uso
- Novidades do sistema
- Relatórios periódicos

Regras:

- Podem ser desativadas pelo usuário
- Baixa prioridade
- Throttling aplicável

---

## 4. Canais de Envio

### 4.1 Canais Suportados

1. **E-mail** — canal padrão
2. **Push notification** — aplicações mobile/web (futuro)
3. **SMS** — alertas críticos (futuro)
4. **WhatsApp** — comunicação via IA (futuro, requer consentimento)

### 4.2 Regras

- Canal padrão: e-mail
- Canais adicionais controlados por feature flag
- Cada canal possui adapter próprio
- Preferência do usuário respeitada quando aplicável

---

## 5. Arquitetura

### 5.1 Posicionamento

```
Evento de Domínio → Event Handler → NotificationService → Queue → Provider Adapter → Envio
```

### 5.2 Componentes

#### NotificationService

- Recebe solicitação de envio
- Resolve template
- Resolve canal
- Enfileira para envio

#### NotificationQueue

- Fila dedicada para notificações
- Priorização por tipo
- Retry automático
- DLQ para falhas definitivas

#### ProviderAdapter

- Interface abstrata para provedores
- Implementações concretas (ex: SES, Mailgun, Twilio)
- Substituível sem impacto no domínio

#### TemplateResolver

- Resolve template por tipo de notificação
- Suporte a variáveis dinâmicas
- Isolamento por tenant (templates customizáveis no futuro)

---

## 6. Templates

### 6.1 Regras

- Templates são versionados
- Templates não contêm lógica de negócio
- Variáveis são injetadas pelo serviço
- Templates são validados antes do envio

### 6.2 Variáveis Padrão

Toda notificação deve ter acesso a:

- `tenant_name`
- `recipient_name`
- `recipient_email`
- Variáveis específicas do evento

---

## 7. Isolamento por Tenant

Regras obrigatórias:

- Toda notificação carrega `tenant_id`
- Nenhuma notificação cruza tenants
- Templates podem ser customizados por tenant (futuro)
- Logs de envio são escopados por tenant
- Rate limiting por tenant

---

## 8. Priorização e Throttling

### 8.1 Prioridades

| Prioridade | Tipo | Exemplo |
|-----------|------|---------|
| Alta | Transacional | Confirmação de pagamento |
| Média | Operacional | Aviso de inadimplência |
| Baixa | Informativa | Dicas de uso |

### 8.2 Throttling

- Limite de envio por tenant por hora
- Limite de envio por usuário por dia
- Notificações informativas são as primeiras a serem limitadas

---

## 9. Retry e Falhas

- Retry com backoff exponencial
- Máximo de tentativas configurável por canal
- Falha definitiva registrada em DLQ
- Falha de notificação nunca bloqueia operação de domínio
- Falhas são auditadas

---

## 10. Consentimento e LGPD

Regras obrigatórias:

- Notificações transacionais: base legal de execução de contrato
- Notificações informativas: requerem consentimento
- WhatsApp e SMS: sempre requerem consentimento explícito
- Opt-out deve ser respeitado
- Dados pessoais em notificações seguem política de retenção

---

## 11. Auditoria

Eventos auditáveis:

- `notification.queued`
- `notification.sent`
- `notification.delivered` (quando disponível)
- `notification.failed`
- `notification.retried`

Campos obrigatórios:

- `tenant_id`
- `recipient_id`
- `channel`
- `notification_type`
- `timestamp`

---

## 12. Observabilidade

Métricas obrigatórias:

- Total de envios por canal
- Taxa de falha por canal
- Latência de envio
- Notificações por tenant
- DLQ size

---

## 13. Integração com Outras Skills

Esta skill integra-se com:

- `event-driven-architecture.md` — eventos disparam notificações
- `job-architecture.md` — envio via jobs
- `dunning-strategy.md` — notificações de inadimplência
- `subscription-lifecycle.md` — notificações de ciclo de assinatura
- `tenant-lifecycle.md` — notificações de provisionamento
- `ai-integration.md` — comunicação via IA/WhatsApp
- `lgpd-compliance.md` — consentimento e dados pessoais
- `data-retention-policy.md` — retenção de logs de envio

---

## 14. Anti-Padrões

- Enviar notificação diretamente do controller
- Lógica de negócio dentro de templates
- Notificação sem tenant_id
- Envio síncrono bloqueante
- Provider sem abstração
- Notificação sem auditoria
- Ignorar opt-out do usuário

---

## 15. Checklist de Conformidade

- [ ] Notificações desacopladas do domínio
- [ ] Envio via fila assíncrona
- [ ] Templates versionados
- [ ] Isolamento por tenant
- [ ] Retry configurado
- [ ] DLQ ativa
- [ ] Auditoria habilitada
- [ ] LGPD respeitada
- [ ] Provedores abstraídos

---

## 16. Status

Documento **OBRIGATÓRIO** para qualquer comunicação com usuários.

Sem estratégia de notificações, o sistema não pode operar comunicações de forma confiável.
