# Catalogo de Casos de Uso

## Condominium Events Manager API

**Tipo:** SaaS B2B Multi-Tenant
**Stack:** Laravel, PostgreSQL, pgvector
**Arquitetura:** DDD + Clean Architecture
**Camada:** Application Layer — todos os Use Cases possuem um unico metodo `execute()`

---

## Indice

1. [Platform Domain — Tenant Management](#1-platform-domain--tenant-management)
2. [Platform Domain — Billing](#2-platform-domain--billing)
3. [Platform Domain — Platform Admin](#3-platform-domain--platform-admin)
4. [Tenant Domain — Units & Residents](#4-tenant-domain--units--residents)
5. [Tenant Domain — Spaces Management](#5-tenant-domain--spaces-management)
6. [Tenant Domain — Reservations (CORE)](#6-tenant-domain--reservations-core)
7. [Tenant Domain — Governance](#7-tenant-domain--governance)
8. [Tenant Domain — People Control](#8-tenant-domain--people-control)
9. [Tenant Domain — Communication](#9-tenant-domain--communication)
10. [Transversal — AI](#10-transversal--ai)
11. [Matriz de Casos de Uso por Ator](#11-matriz-de-casos-de-uso-por-ator)
12. [Fluxos Criticos](#12-fluxos-criticos)

---

## 1. Platform Domain — Tenant Management

### UC-01: ProvisionTenant

| Campo | Descricao |
|-------|-----------|
| **Nome** | ProvisionTenant |
| **Contexto** | Tenant Management (Platform Domain) |
| **Ator** | PlatformAdmin |

**Pre-condicoes:**
- Ator autenticado como PlatformAdmin
- Dados do condominio validados (nome, CNPJ, tipo, plano selecionado)
- CNPJ nao duplicado na plataforma
- Plano selecionado ativo e com PlanVersion vigente

**Fluxo principal:**
1. PlatformAdmin submete dados do novo tenant (nome, CNPJ, tipo do condominio, plano, dados do admin inicial)
2. Sistema valida unicidade do CNPJ
3. Sistema cria registro do Tenant com status `provisioning`
4. Sistema despacha `ProvisionTenantJob` para fila assincrona
5. Job cria database/schema dedicado no PostgreSQL
6. Job executa migrations no novo schema
7. Job executa seeders iniciais (roles, permissions, configuracoes padrao)
8. Job cria usuario administrador inicial (TenantUser com role sindico)
9. Job atualiza status do Tenant para `active`
10. Sistema emite evento `TenantCreated`
11. Sistema emite evento `TenantProvisioned`
12. Sistema dispara criacao automatica de Subscription (via listener de `TenantProvisioned`)

**Pos-condicoes:**
- Tenant criado com status `active`
- Database/schema dedicado provisionado
- Usuario administrador inicial criado com status `invited`
- Email de convite enviado ao administrador
- Eventos emitidos: `TenantCreated`, `TenantProvisioned`

**Cenarios de erro:**
- CNPJ duplicado: retorna erro de validacao
- Falha na criacao do database: Job marca Tenant como `provisioning_failed`, emite `TenantProvisioningFailed`
- Falha nas migrations: rollback do database, marca como `provisioning_failed`
- Plano inexistente ou inativo: retorna erro de validacao
- Timeout no provisionamento: Job pode ser retentado (idempotente via idempotency_key)

**Regras de negocio aplicaveis:**
- RN-01: Cada tenant possui database/schema isolado (multi-tenancy por database)
- RN-02: CNPJ deve ser unico na plataforma
- RN-03: Provisionamento eh assincrono e idempotente
- RN-04: Tenant so eh considerado ativo apos provisionamento completo

---

### UC-02: SuspendTenant

| Campo | Descricao |
|-------|-----------|
| **Nome** | SuspendTenant |
| **Contexto** | Tenant Management (Platform Domain) |
| **Ator** | System / PlatformAdmin |

**Pre-condicoes:**
- Tenant existe e esta com status `active`
- Motivo da suspensao informado (inadimplencia ou violacao de politica)

**Fluxo principal:**
1. System (via dunning) ou PlatformAdmin solicita suspensao do tenant
2. Sistema valida que tenant esta ativo
3. Sistema altera status do Tenant para `suspended`
4. Sistema registra motivo e data da suspensao
5. Sistema cancela todas as sessoes ativas dos usuarios do tenant
6. Sistema emite evento `TenantSuspended`
7. Listeners notificam administradores do tenant sobre a suspensao

**Pos-condicoes:**
- Tenant com status `suspended`
- Todas as sessoes ativas invalidadas
- Usuarios do tenant nao conseguem acessar o sistema
- Dados preservados (nao deletados)
- Evento emitido: `TenantSuspended`

**Cenarios de erro:**
- Tenant ja suspenso: retorna erro (operacao idempotente se mesmo motivo)
- Tenant em status `canceled`: operacao nao permitida
- Tenant em status `provisioning`: operacao nao permitida

**Regras de negocio aplicaveis:**
- RN-05: Suspensao nao deleta dados, apenas bloqueia acesso
- RN-06: Sessoes ativas devem ser canceladas imediatamente
- RN-07: Suspensao automatica ocorre apos esgotamento do grace period de dunning

---

### UC-03: CancelTenant

| Campo | Descricao |
|-------|-----------|
| **Nome** | CancelTenant |
| **Contexto** | Tenant Management (Platform Domain) |
| **Ator** | PlatformAdmin / Tenant (Sindico) |

**Pre-condicoes:**
- Tenant existe e esta com status `active` ou `suspended`
- Ator autenticado com permissao para cancelamento

**Fluxo principal:**
1. PlatformAdmin ou Sindico solicita cancelamento do tenant
2. Sistema valida status atual do tenant
3. Sistema altera status para `canceled`
4. Sistema registra data de cancelamento e inicio do periodo de retencao
5. Sistema cancela assinatura ativa (se houver)
6. Sistema cancela todas as sessoes ativas
7. Sistema emite evento `TenantCanceled`
8. Listeners agendam exclusao de dados apos periodo de retencao (conforme LGPD)

**Pos-condicoes:**
- Tenant com status `canceled`
- Periodo de retencao iniciado (dados preservados temporariamente)
- Assinatura cancelada
- Evento emitido: `TenantCanceled`

**Cenarios de erro:**
- Tenant ja cancelado: retorna erro
- Periodo de retencao nao configurado: usa valor padrao da plataforma

**Regras de negocio aplicaveis:**
- RN-08: Cancelamento inicia periodo de retencao (configuravel, padrao 30 dias)
- RN-09: Dados sao preservados durante periodo de retencao para possivel reativacao
- RN-10: Apos periodo de retencao, dados sao anonimizados/excluidos conforme LGPD

---

### UC-04: ReactivateTenant

| Campo | Descricao |
|-------|-----------|
| **Nome** | ReactivateTenant |
| **Contexto** | Tenant Management (Platform Domain) |
| **Ator** | PlatformAdmin |

**Pre-condicoes:**
- Tenant existe e esta com status `suspended`
- Motivo da reativacao informado (ex: pagamento regularizado)
- Assinatura ativa ou nova assinatura sendo criada

**Fluxo principal:**
1. PlatformAdmin solicita reativacao do tenant
2. Sistema valida que tenant esta suspenso
3. Sistema verifica se existe assinatura ativa ou pendencia financeira resolvida
4. Sistema altera status para `active`
5. Sistema registra data e motivo da reativacao
6. Sistema emite evento `TenantReactivated`
7. Listeners notificam administradores do tenant

**Pos-condicoes:**
- Tenant com status `active`
- Usuarios podem acessar o sistema novamente
- Dados intactos (preservados durante suspensao)
- Evento emitido: `TenantReactivated`

**Cenarios de erro:**
- Tenant nao esta suspenso: retorna erro
- Tenant esta cancelado (fora do periodo de retencao): reativacao nao permitida
- Pendencia financeira nao resolvida: retorna erro

**Regras de negocio aplicaveis:**
- RN-11: Apenas tenants suspensos podem ser reativados
- RN-12: Reativacao requer resolucao da causa da suspensao
- RN-13: Tenants cancelados so podem ser reativados dentro do periodo de retencao

---

### UC-67: RegisterTenant (Self-Service)

| Campo | Descricao |
|-------|-----------|
| **Nome** | RegisterTenant |
| **Contexto** | Tenant Management (Platform Domain) |
| **Ator** | Visitante (futuro sindico/administradora) |

**Pre-condicoes:**
- Nenhuma autenticacao necessaria (endpoint publico)
- Dados do condominio e do administrador informados
- Plano selecionado ativo e disponivel

**Fluxo principal:**
1. Visitante submete dados de registro (nome do condominio, slug, tipo, dados do admin, plano)
2. Sistema valida unicidade do slug na tabela `tenants`
3. Sistema valida unicidade do slug na tabela `pending_registrations` (nao expirados)
4. Sistema valida tipo do condominio (horizontal, vertical, mixed)
5. Sistema valida existencia e disponibilidade do plano selecionado
6. Sistema gera token de verificacao (64 bytes aleatorios)
7. Sistema hasheia o token com SHA-256 para armazenamento
8. Sistema hasheia a senha do admin com bcrypt
9. Sistema cria registro em `pending_registrations` com TTL de 24 horas
10. Sistema envia email de verificacao ao admin com o token em texto plano
11. Sistema retorna `PendingRegistrationDTO` (sem dados sensiveis)

**Pos-condicoes:**
- Registro pendente criado com expiracao de 24 horas
- Email de verificacao enviado ao administrador
- Nenhum Tenant criado (aguarda verificacao)
- Senha armazenada como hash bcrypt
- Token armazenado como hash SHA-256

**Cenarios de erro:**
- Slug ja existe em `tenants`: retorna erro `TENANT_SLUG_ALREADY_EXISTS`
- Slug ja existe em `pending_registrations` ativo: retorna erro `REGISTRATION_SLUG_PENDING`
- Tipo de condominio invalido: retorna erro `INVALID_CONDOMINIUM_TYPE`
- Plano inexistente ou inativo: retorna erro `PLAN_NOT_AVAILABLE`
- Validacao de campos falha: retorna erro `VALIDATION_ERROR`

**Regras de negocio aplicaveis:**
- RN-175: Slug deve ser unico tanto em `tenants` quanto em `pending_registrations` ativos
- RN-176: Token de verificacao nunca eh armazenado em texto plano (somente hash SHA-256)
- RN-177: Senha do admin eh hasheada com bcrypt antes do armazenamento
- RN-178: PendingRegistration expira em 24 horas se nao verificado
- RN-179: PendingRegistration NAO eh entidade de dominio (conceito de workflow/infraestrutura)

---

### UC-68: VerifyRegistration

| Campo | Descricao |
|-------|-----------|
| **Nome** | VerifyRegistration |
| **Contexto** | Tenant Management (Platform Domain) |
| **Ator** | Visitante (via link do email) |

**Pre-condicoes:**
- Token de verificacao valido recebido via query parameter
- PendingRegistration associado ao token existente e nao expirado

**Fluxo principal:**
1. Visitante acessa link de verificacao com token
2. Sistema hasheia o token recebido com SHA-256
3. Sistema busca `pending_registration` pelo hash do token
4. Sistema valida que o registro nao esta expirado (`expires_at > now`)
5. Sistema valida que o registro nao foi verificado anteriormente (`verified_at IS NULL`)
6. Sistema re-valida unicidade do slug na tabela `tenants` (protecao contra race condition)
7. Sistema marca `pending_registration` como verificado (`verified_at = now`)
8. Sistema cria Tenant com status `provisioning`
9. Sistema salva configuracao do tenant (tipo, plano)
10. Sistema inicia provisionamento (startProvisioning)
11. Sistema despacha evento `TenantCreated`
12. Listener despacha `ProvisionTenantJob` para fila assincrona

**Pos-condicoes:**
- PendingRegistration marcado como verificado
- Tenant criado com status `provisioning`
- `ProvisionTenantJob` despachado
- Database do tenant sera provisionado assincronamente
- Apos provisionamento: Tenant fica `active`, admin (sindico) criado

**Cenarios de erro:**
- Token nao encontrado (hash nao corresponde): retorna erro `VERIFICATION_TOKEN_INVALID`
- Token expirado (apos 24 horas): retorna erro `VERIFICATION_TOKEN_EXPIRED`
- Token ja utilizado (verified_at preenchido): retorna erro `VERIFICATION_TOKEN_INVALID`
- Slug ja tomado por outro tenant (race condition): retorna erro `TENANT_SLUG_ALREADY_EXISTS`

**Regras de negocio aplicaveis:**
- RN-180: Token eh validado por hash SHA-256, nao por comparacao direta
- RN-181: Unicidade do slug eh re-validada no momento da verificacao (race condition)
- RN-182: Provisionamento eh assincrono e idempotente
- RN-183: Tenant so eh criado apos verificacao de email bem-sucedida

---

## 2. Platform Domain — Billing

### UC-05: CreateSubscription

| Campo | Descricao |
|-------|-----------|
| **Nome** | CreateSubscription |
| **Contexto** | Billing & Plans (Platform Domain) |
| **Ator** | System (apos provisionamento do tenant) |

**Pre-condicoes:**
- Tenant provisionado com sucesso (evento `TenantProvisioned` recebido)
- Plano selecionado ativo com PlanVersion vigente
- Nenhuma assinatura ativa para o tenant

**Fluxo principal:**
1. Listener de `TenantProvisioned` dispara criacao da assinatura
2. Sistema valida que tenant nao possui assinatura ativa
3. Sistema cria Subscription vinculada ao Tenant e PlanVersion
4. Sistema define periodo inicial (starts_at, current_period_end)
5. Sistema define status como `active`
6. Sistema gera primeira Invoice (se plano nao for trial)
7. Sistema emite evento `SubscriptionActivated`

**Pos-condicoes:**
- Subscription criada com status `active`
- Vinculada ao PlanVersion vigente
- Periodo de cobranca definido
- Evento emitido: `SubscriptionActivated`

**Cenarios de erro:**
- Tenant ja possui assinatura ativa: operacao ignorada (idempotente)
- PlanVersion nao encontrada: falha, evento enviado para DLQ
- Erro ao criar invoice: Subscription criada, invoice retry via job

**Regras de negocio aplicaveis:**
- RN-14: Cada tenant possui no maximo uma assinatura ativa
- RN-15: Assinatura sempre vinculada a uma PlanVersion especifica
- RN-16: Criacao de assinatura eh idempotente (protegida por idempotency_key)

---

### UC-06: RenewSubscription

| Campo | Descricao |
|-------|-----------|
| **Nome** | RenewSubscription |
| **Contexto** | Billing & Plans (Platform Domain) |
| **Ator** | System (cron/scheduler) |

**Pre-condicoes:**
- Subscription ativa com `current_period_end` atingido
- Tenant com status `active`

**Fluxo principal:**
1. Scheduler identifica assinaturas com periodo encerrado
2. Sistema valida que assinatura esta ativa e tenant esta ativo
3. Sistema atualiza `current_period_start` e `current_period_end` para novo ciclo
4. Sistema gera nova Invoice para o periodo
5. Sistema emite evento `SubscriptionRenewed`
6. Sistema emite evento `InvoiceIssued`

**Pos-condicoes:**
- Subscription com novo periodo de cobranca
- Nova Invoice gerada com status `pending`
- Eventos emitidos: `SubscriptionRenewed`, `InvoiceIssued`

**Cenarios de erro:**
- Assinatura ja renovada para o periodo: operacao ignorada (idempotente)
- Tenant suspenso: renovacao nao executada
- Falha ao gerar invoice: retry automatico via job

**Regras de negocio aplicaveis:**
- RN-17: Renovacao automatica no fim de cada periodo
- RN-18: Renovacao idempotente por periodo (nao duplica invoices)
- RN-19: Tenant suspenso nao renova assinatura

---

### UC-07: CancelSubscription

| Campo | Descricao |
|-------|-----------|
| **Nome** | CancelSubscription |
| **Contexto** | Billing & Plans (Platform Domain) |
| **Ator** | Tenant (Sindico) / PlatformAdmin |

**Pre-condicoes:**
- Subscription ativa existente
- Ator autenticado com permissao

**Fluxo principal:**
1. Ator solicita cancelamento da assinatura
2. Sistema valida que assinatura esta ativa
3. Sistema define `canceled_at` como data atual
4. Sistema define `ends_at` como fim do periodo corrente (cancelamento ao fim do ciclo)
5. Sistema altera status para `canceled`
6. Sistema emite evento `SubscriptionCanceled`

**Pos-condicoes:**
- Subscription com status `canceled`
- Acesso mantido ate fim do periodo corrente (`ends_at`)
- Evento emitido: `SubscriptionCanceled`

**Cenarios de erro:**
- Assinatura ja cancelada: retorna erro
- Assinatura inexistente: retorna erro

**Regras de negocio aplicaveis:**
- RN-20: Cancelamento efetivo apenas ao fim do periodo corrente
- RN-21: Acesso ao sistema mantido ate `ends_at`
- RN-22: Apos `ends_at`, tenant eh suspenso automaticamente

---

### UC-08: ChangeSubscriptionPlan

| Campo | Descricao |
|-------|-----------|
| **Nome** | ChangeSubscriptionPlan |
| **Contexto** | Billing & Plans (Platform Domain) |
| **Ator** | Tenant (Sindico) |

**Pre-condicoes:**
- Subscription ativa existente
- Novo plano diferente do atual
- Novo plano ativo com PlanVersion vigente

**Fluxo principal:**
1. Sindico seleciona novo plano
2. Sistema valida que novo plano esta ativo e possui PlanVersion vigente
3. Sistema verifica se eh upgrade ou downgrade
4. Se upgrade: aplica imediatamente, calcula pro-rata do periodo restante
5. Se downgrade: agenda para proximo ciclo de renovacao
6. Sistema atualiza Subscription com nova PlanVersion
7. Sistema gera credito ou cobranca adicional (se pro-rata aplicavel)
8. Sistema emite evento `SubscriptionPlanChanged`

**Pos-condicoes:**
- Subscription atualizada com nova PlanVersion
- Feature flags ajustados conforme novo plano
- Pro-rata calculado e refletido na proxima invoice (se aplicavel)
- Evento emitido: `SubscriptionPlanChanged`

**Cenarios de erro:**
- Plano igual ao atual: retorna erro
- Plano inexistente ou inativo: retorna erro
- Downgrade viola limites atuais (ex: mais unidades do que o novo plano permite): retorna erro com detalhes

**Regras de negocio aplicaveis:**
- RN-23: Upgrade eh imediato com pro-rata
- RN-24: Downgrade efetivo no proximo ciclo
- RN-25: Downgrade bloqueado se uso atual excede limites do novo plano

---

### UC-09: GenerateInvoice

| Campo | Descricao |
|-------|-----------|
| **Nome** | GenerateInvoice |
| **Contexto** | Billing & Plans (Platform Domain) |
| **Ator** | System |

**Pre-condicoes:**
- Subscription ativa vinculada a um tenant
- Periodo de cobranca definido

**Fluxo principal:**
1. Sistema identifica necessidade de geracao de invoice (renovacao, pro-rata, etc.)
2. Sistema cria Invoice com status `pending`
3. Sistema cria InvoiceItems detalhando os itens cobrados
4. Sistema calcula valor total
5. Sistema define `due_date` conforme politica de pagamento
6. Sistema emite evento `InvoiceIssued`

**Pos-condicoes:**
- Invoice criada com status `pending`
- InvoiceItems detalhados
- Evento emitido: `InvoiceIssued`

**Cenarios de erro:**
- Invoice ja existente para o periodo: operacao ignorada (idempotente)
- Subscription inativa: invoice nao gerada

**Regras de negocio aplicaveis:**
- RN-26: Cada periodo de assinatura gera no maximo uma invoice
- RN-27: Invoice eh imutavel apos emissao (correcoes via credito ou nova invoice)
- RN-28: Geracao idempotente por subscription_id + periodo

---

### UC-10: ProcessPayment

| Campo | Descricao |
|-------|-----------|
| **Nome** | ProcessPayment |
| **Contexto** | Billing & Plans (Platform Domain) |
| **Ator** | System |

**Pre-condicoes:**
- Invoice emitida com status `pending` ou `past_due`
- Metodo de pagamento configurado no gateway (Stripe)

**Fluxo principal:**
1. Sistema submete cobranca ao gateway de pagamento (Stripe)
2. Gateway processa transacao
3. Sistema recebe webhook de confirmacao
4. Sistema cria registro Payment com status `confirmed`
5. Sistema atualiza Invoice para status `paid`
6. Sistema emite evento `PaymentConfirmed`

**Pos-condicoes:**
- Payment registrado com status `confirmed`
- Invoice atualizada para `paid`
- Evento emitido: `PaymentConfirmed`

**Cenarios de erro:**
- Falha no gateway (cartao recusado, saldo insuficiente): Payment com status `failed`, Invoice permanece `pending` ou vai para `past_due`, emite `PaymentFailed`
- Timeout no gateway: retry automatico com backoff
- Webhook duplicado: processamento idempotente via gateway_event_id

**Regras de negocio aplicaveis:**
- RN-29: Processamento de pagamento eh idempotente (via gateway_event_id)
- RN-30: Falha de pagamento inicia processo de dunning
- RN-31: Webhooks do Stripe sao validados via assinatura

---

### UC-11: ProcessDunning

| Campo | Descricao |
|-------|-----------|
| **Nome** | ProcessDunning |
| **Contexto** | Billing & Plans (Platform Domain) |
| **Ator** | System (cron/scheduler) |

**Pre-condicoes:**
- Invoice(s) com status `past_due`
- DunningPolicy configurada

**Fluxo principal:**
1. Scheduler identifica invoices vencidas
2. Para cada invoice, sistema verifica etapa atual do dunning
3. **Etapa 1 (dia 1):** Envia email de lembrete ao tenant
4. **Etapa 2 (dia 3):** Retenta cobranca automatica no gateway
5. **Etapa 3 (dia 7):** Envia email de aviso de suspensao iminente
6. **Etapa 4 (dia 10):** Retenta cobranca novamente
7. **Etapa 5 (dia 14 — grace period):** Suspende tenant automaticamente
8. **Etapa 6 (dia 30+):** Inicia processo de cancelamento
9. Sistema registra cada etapa executada
10. Sistema emite eventos conforme acoes (InvoiceOverdue, TenantSuspended)

**Pos-condicoes:**
- Acoes de dunning executadas conforme politica
- Emails enviados nas etapas configuradas
- Tenant suspenso se grace period expirado
- Eventos emitidos: `InvoiceOverdue`, `TenantSuspended` (quando aplicavel)

**Cenarios de erro:**
- Falha no envio de email: retry automatico, nao bloqueia proxima etapa
- Falha na retentativa de cobranca: segue para proxima etapa
- DunningPolicy nao configurada: usa politica padrao da plataforma

**Regras de negocio aplicaveis:**
- RN-32: Dunning segue politica configuravel com etapas progressivas
- RN-33: Grace period padrao de 14 dias antes da suspensao
- RN-34: Cada etapa de dunning eh idempotente e registrada

---

### UC-12: IssueRefund

| Campo | Descricao |
|-------|-----------|
| **Nome** | IssueRefund |
| **Contexto** | Billing & Plans (Platform Domain) |
| **Ator** | PlatformAdmin |

**Pre-condicoes:**
- Payment existente com status `confirmed`
- Motivo do reembolso informado
- Valor do reembolso valido (total ou parcial, ate o valor do pagamento)

**Fluxo principal:**
1. PlatformAdmin solicita reembolso informando payment_id, valor e motivo
2. Sistema valida que pagamento existe e esta confirmado
3. Sistema valida que valor nao excede o pago
4. Sistema solicita reembolso ao gateway (Stripe)
5. Sistema cria registro de refund vinculado ao Payment
6. Sistema atualiza status do Payment conforme (parcial ou totalmente reembolsado)
7. Sistema emite evento `PaymentRefunded`

**Pos-condicoes:**
- Refund registrado
- Payment atualizado (status `refunded` ou `partially_refunded`)
- Reembolso processado no gateway
- Evento emitido: `PaymentRefunded`

**Cenarios de erro:**
- Payment nao encontrado: retorna erro
- Valor excede o pago: retorna erro de validacao
- Falha no gateway: retry ou registro manual necessario
- Payment ja totalmente reembolsado: retorna erro

**Regras de negocio aplicaveis:**
- RN-35: Reembolso pode ser total ou parcial
- RN-36: Valor total de reembolsos nao pode exceder o valor original do pagamento
- RN-37: Reembolso eh registrado no audit log

---

## 3. Platform Domain — Platform Admin

### UC-13: ManagePlans

| Campo | Descricao |
|-------|-----------|
| **Nome** | ManagePlans |
| **Contexto** | Platform Admin (Platform Domain) |
| **Ator** | PlatformAdmin |

**Pre-condicoes:**
- Ator autenticado como PlatformAdmin

**Fluxo principal:**
1. PlatformAdmin cria ou atualiza um plano
2. Sistema valida dados do plano (nome, descricao, features, limites)
3. Se criacao: Sistema cria Plan e PlanVersion inicial
4. Se atualizacao: Sistema cria nova PlanVersion (versionamento, nunca altera versao existente)
5. Nova PlanVersion recebe lista de PlanFeatures (max_units, max_spaces, max_users, etc.)
6. Assinaturas existentes permanecem na PlanVersion anterior ate renovacao

**Pos-condicoes:**
- Plan criado ou atualizado com nova PlanVersion
- PlanFeatures definidas para a versao
- Assinaturas existentes nao afetadas imediatamente

**Cenarios de erro:**
- Nome do plano duplicado: retorna erro de validacao
- Features invalidas: retorna erro de validacao
- Tentativa de alterar PlanVersion existente: bloqueada (imutavel)

**Regras de negocio aplicaveis:**
- RN-38: PlanVersion eh imutavel apos criacao
- RN-39: Alteracoes em planos sempre criam nova PlanVersion
- RN-40: Assinaturas migram para nova versao apenas na renovacao (ou via ChangeSubscriptionPlan)

---

### UC-14: ManageFeatureFlags

| Campo | Descricao |
|-------|-----------|
| **Nome** | ManageFeatureFlags |
| **Contexto** | Platform Admin (Platform Domain) |
| **Ator** | PlatformAdmin |

**Pre-condicoes:**
- Ator autenticado como PlatformAdmin

**Fluxo principal:**
1. PlatformAdmin cria, atualiza ou remove feature flag
2. Sistema valida dados (nome unico, tipo: boolean/numeric/enum)
3. Se criacao: Sistema cria Feature com valor padrao
4. Se override por tenant: Sistema cria TenantFeatureOverride vinculado ao tenant especifico
5. Override tem precedencia sobre valor do plano

**Pos-condicoes:**
- Feature flag criada/atualizada
- Overrides por tenant aplicados (se configurados)
- Tenants afetados recebem novos limites/configuracoes imediatamente

**Cenarios de erro:**
- Nome duplicado: retorna erro de validacao
- Tenant inexistente para override: retorna erro
- Tipo invalido: retorna erro de validacao

**Regras de negocio aplicaveis:**
- RN-41: Feature flags possuem 3 niveis: padrao global, por plano (PlanFeature), por tenant (override)
- RN-42: Precedencia: Override > PlanFeature > Padrao global
- RN-43: Alteracoes em feature flags tem efeito imediato

---

### UC-15: ViewTenantDashboard

| Campo | Descricao |
|-------|-----------|
| **Nome** | ViewTenantDashboard |
| **Contexto** | Platform Admin (Platform Domain) |
| **Ator** | PlatformAdmin |

**Pre-condicoes:**
- Ator autenticado como PlatformAdmin

**Fluxo principal:**
1. PlatformAdmin acessa dashboard de um tenant especifico ou visao geral
2. Sistema agrega metricas: status do tenant, plano atual, status da assinatura
3. Sistema apresenta: uso de recursos (unidades, espacos, usuarios), faturas recentes, status de pagamento
4. Sistema apresenta: historico de acoes administrativas (suspensoes, reativacoes)

**Pos-condicoes:**
- Dados apresentados em tempo real (ou near real-time)
- Nenhuma alteracao de estado

**Cenarios de erro:**
- Tenant inexistente: retorna erro 404
- Dados de metricas indisponiveis: exibe dados parciais com indicador

**Regras de negocio aplicaveis:**
- RN-44: Dashboard nao acessa dados de dominio do tenant (reservas, penalidades, etc.)
- RN-45: Metricas limitadas a dados de plataforma (billing, subscription, usage)

---

## 4. Tenant Domain — Units & Residents

### UC-16: CreateBlock

| Campo | Descricao |
|-------|-----------|
| **Nome** | CreateBlock |
| **Contexto** | Units & Residents (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Tenant ativo com assinatura valida
- Ator autenticado com role sindico ou administradora
- Condominio eh do tipo vertical ou misto

**Fluxo principal:**
1. Sindico/Administradora submete dados do bloco (identificador, nome/descricao opcional)
2. Sistema valida que condominio eh vertical ou misto (blocos nao se aplicam a condominios horizontais)
3. Sistema valida unicidade do identificador dentro do tenant
4. Sistema cria registro Block
5. Sistema emite evento `BlockCreated`

**Pos-condicoes:**
- Block criado e vinculado ao tenant
- Evento emitido: `BlockCreated`

**Cenarios de erro:**
- Condominio horizontal: retorna erro (blocos nao permitidos)
- Identificador duplicado no tenant: retorna erro de validacao
- Tenant suspenso ou cancelado: operacao bloqueada

**Regras de negocio aplicaveis:**
- RN-46: Blocos sao exclusivos de condominios verticais e mistos
- RN-47: Identificador do bloco deve ser unico dentro do tenant

---

### UC-17: CreateUnit

| Campo | Descricao |
|-------|-----------|
| **Nome** | CreateUnit |
| **Contexto** | Units & Residents (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Tenant ativo com assinatura valida
- Ator autenticado com role sindico ou administradora
- Se condominio vertical: block_id informado e bloco existente
- Limite de unidades (max_units) nao atingido

**Fluxo principal:**
1. Sindico/Administradora submete dados da unidade (numero, tipo, block_id se vertical)
2. Sistema valida tipo do condominio e obrigatoriedade de block_id
3. Sistema valida unicidade do numero dentro do bloco (vertical) ou do tenant (horizontal)
4. Sistema verifica limite max_units do plano (via feature flag)
5. Sistema cria registro Unit com status `active`
6. Sistema emite evento `UnitCreated`

**Pos-condicoes:**
- Unit criada com status `active`
- Vinculada ao bloco (se vertical) ou diretamente ao tenant (se horizontal)
- Evento emitido: `UnitCreated`

**Cenarios de erro:**
- Numero duplicado no escopo (bloco ou tenant): retorna erro de validacao
- Limite max_units atingido: retorna erro com informacao do limite
- Block_id nao informado para condominio vertical: retorna erro de validacao
- Block_id inexistente: retorna erro

**Regras de negocio aplicaveis:**
- RN-48: Numero da unidade unico dentro do bloco (vertical) ou tenant (horizontal)
- RN-49: Condominio vertical exige block_id
- RN-50: Limite de unidades controlado por feature flag max_units do plano

---

### UC-18: DeactivateUnit

| Campo | Descricao |
|-------|-----------|
| **Nome** | DeactivateUnit |
| **Contexto** | Units & Residents (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Unit existe e esta ativa
- Ator autenticado com role sindico ou administradora

**Fluxo principal:**
1. Sindico/Administradora solicita desativacao da unidade
2. Sistema valida que unidade esta ativa
3. Sistema altera status da unidade para `inactive`
4. Sistema cancela todas as reservas futuras da unidade
5. Sistema desativa moradores vinculados (opcionalmente)
6. Sistema emite evento `UnitDeactivated`

**Pos-condicoes:**
- Unit com status `inactive`
- Reservas futuras canceladas
- Evento emitido: `UnitDeactivated`

**Cenarios de erro:**
- Unidade ja inativa: retorna erro
- Unidade inexistente: retorna erro 404

**Regras de negocio aplicaveis:**
- RN-51: Desativacao cancela automaticamente reservas futuras
- RN-52: Unidade desativada nao pode ser usada para novas reservas
- RN-53: Historico da unidade eh preservado

---

### UC-19: InviteResident

| Campo | Descricao |
|-------|-----------|
| **Nome** | InviteResident |
| **Contexto** | Units & Residents (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Tenant ativo com assinatura valida
- Unit ativa existente
- Ator autenticado com role sindico ou administradora
- Limite de usuarios (max_users) nao atingido
- Limite de moradores por unidade (max_residents_per_unit) nao atingido

**Fluxo principal:**
1. Sindico/Administradora submete dados do morador (nome, email, CPF, role no condominio, unit_id)
2. Sistema valida unicidade do email dentro do tenant
3. Sistema verifica limite max_users do plano
4. Sistema verifica limite max_residents_per_unit para a unidade
5. Sistema cria TenantUser com status `invited`
6. Sistema cria Resident vinculado ao TenantUser e Unit
7. Sistema envia email de convite com link de ativacao (token temporario)
8. Sistema emite evento `ResidentInvited`

**Pos-condicoes:**
- TenantUser criado com status `invited`
- Resident criado e vinculado a unidade
- Email de convite enviado
- Evento emitido: `ResidentInvited`

**Cenarios de erro:**
- Email duplicado no tenant: retorna erro de validacao
- Limite max_users atingido: retorna erro com informacao do limite e sugestao de upgrade
- Limite max_residents_per_unit atingido: retorna erro
- Unit inexistente ou inativa: retorna erro
- Falha no envio de email: convite criado, email reagendado para retry

**Regras de negocio aplicaveis:**
- RN-54: Email unico por tenant
- RN-55: Limite de usuarios controlado por feature flag max_users
- RN-56: Limite de moradores por unidade controlado por feature flag max_residents_per_unit
- RN-57: Token de convite tem validade configuravel (padrao 72h)

---

### UC-20: ActivateResident

| Campo | Descricao |
|-------|-----------|
| **Nome** | ActivateResident |
| **Contexto** | Units & Residents (Tenant Domain) |
| **Ator** | Resident (via link de convite) |

**Pre-condicoes:**
- Token de convite valido e nao expirado
- TenantUser com status `invited`

**Fluxo principal:**
1. Morador acessa link de convite
2. Sistema valida token (existencia, validade, nao utilizado)
3. Morador define senha
4. Sistema atualiza TenantUser para status `active`
5. Sistema registra data de ativacao
6. Sistema invalida token utilizado
7. Sistema emite evento `ResidentActivated`

**Pos-condicoes:**
- TenantUser com status `active`
- Morador pode acessar o sistema
- Token invalidado
- Evento emitido: `ResidentActivated`

**Cenarios de erro:**
- Token expirado: retorna erro com opcao de reenvio
- Token ja utilizado: retorna erro
- Token inexistente: retorna erro 404
- Senha nao atende criterios de seguranca: retorna erro de validacao

**Regras de negocio aplicaveis:**
- RN-58: Token de uso unico (one-time use)
- RN-59: Senha deve atender politica de seguranca (minimo 8 caracteres, complexidade)
- RN-60: Ativacao eh idempotente se usuario ja esta ativo

---

### UC-21: DeactivateResident

| Campo | Descricao |
|-------|-----------|
| **Nome** | DeactivateResident |
| **Contexto** | Units & Residents (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Resident existe e esta ativo
- Ator autenticado com role sindico ou administradora

**Fluxo principal:**
1. Sindico/Administradora solicita desativacao do morador
2. Sistema valida que morador esta ativo
3. Sistema define `moved_out_at` com data informada (ou data atual)
4. Sistema altera status do TenantUser para `inactive`
5. Sistema altera status do Resident para `inactive`
6. Sistema cancela sessoes ativas do morador
7. Sistema cancela reservas futuras do morador
8. Sistema emite evento `ResidentDeactivated`
9. Sistema emite evento `ResidentMovedOut`

**Pos-condicoes:**
- Resident e TenantUser com status `inactive`
- Data de mudanca registrada (`moved_out_at`)
- Reservas futuras canceladas
- Sessoes invalidadas
- Eventos emitidos: `ResidentDeactivated`, `ResidentMovedOut`

**Cenarios de erro:**
- Morador ja inativo: retorna erro
- Morador inexistente: retorna erro 404

**Regras de negocio aplicaveis:**
- RN-61: Desativacao cancela reservas futuras automaticamente
- RN-62: Historico do morador eh preservado (soft delete)
- RN-63: Data de mudanca (moved_out_at) obrigatoria

---

### UC-22: TransferUnit

| Campo | Descricao |
|-------|-----------|
| **Nome** | TransferUnit |
| **Contexto** | Units & Residents (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Unit ativa existente
- Dados do novo proprietario/inquilino informados
- Ator autenticado com role sindico ou administradora

**Fluxo principal:**
1. Sindico/Administradora solicita transferencia da unidade
2. Sistema desativa morador(es) anterior(es) da unidade (executa fluxo de DeactivateResident)
3. Sistema registra historico de transferencia
4. Sistema convida novo proprietario/inquilino (executa fluxo de InviteResident)
5. Sistema preserva historico completo da unidade (moradores anteriores, reservas, violacoes)

**Pos-condicoes:**
- Morador(es) anterior(es) desativado(s) com moved_out_at
- Novo morador convidado
- Historico da unidade preservado integralmente
- Reservas futuras dos moradores anteriores canceladas

**Cenarios de erro:**
- Unit inexistente ou inativa: retorna erro
- Dados do novo morador invalidos: retorna erro de validacao
- Limites de usuarios atingidos: retorna erro

**Regras de negocio aplicaveis:**
- RN-64: Transferencia eh operacao composta (desativacao + convite)
- RN-65: Historico completo da unidade deve ser preservado
- RN-66: Penalidades anteriores permanecem vinculadas aos moradores antigos, nao a unidade

---

## 5. Tenant Domain — Spaces Management

### UC-23: CreateSpace

| Campo | Descricao |
|-------|-----------|
| **Nome** | CreateSpace |
| **Contexto** | Spaces Management (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Tenant ativo com assinatura valida
- Ator autenticado com role sindico ou administradora
- Limite de espacos (max_spaces) nao atingido

**Fluxo principal:**
1. Sindico/Administradora submete dados do espaco (nome, tipo, capacidade, descricao, configuracoes)
2. Sistema valida dados obrigatorios
3. Sistema verifica limite max_spaces do plano (via feature flag)
4. Sistema cria Space com status `active`
5. Sistema configura valores padrao (requires_approval, max_duration_hours, advance_booking_days, etc.)
6. Sistema emite evento `SpaceCreated`

**Pos-condicoes:**
- Space criado com status `active`
- Configuracoes padrao aplicadas
- Evento emitido: `SpaceCreated`

**Cenarios de erro:**
- Limite max_spaces atingido: retorna erro com informacao do limite
- Dados obrigatorios ausentes: retorna erro de validacao
- Tipo de espaco invalido: retorna erro de validacao

**Regras de negocio aplicaveis:**
- RN-67: Limite de espacos controlado por feature flag max_spaces
- RN-68: Espaco criado com configuracoes padrao que podem ser ajustadas posteriormente

---

### UC-24: UpdateSpace

| Campo | Descricao |
|-------|-----------|
| **Nome** | UpdateSpace |
| **Contexto** | Spaces Management (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Space existe e esta ativo
- Ator autenticado com role sindico ou administradora

**Fluxo principal:**
1. Sindico/Administradora submete dados atualizados do espaco
2. Sistema valida dados
3. Sistema atualiza configuracoes (capacidade, requires_approval, max_duration_hours, advance_booking_days, etc.)
4. Alteracoes nao afetam reservas ja confirmadas

**Pos-condicoes:**
- Space atualizado com novas configuracoes
- Reservas existentes nao afetadas

**Cenarios de erro:**
- Space inexistente: retorna erro 404
- Space inativo: retorna erro
- Dados invalidos: retorna erro de validacao

**Regras de negocio aplicaveis:**
- RN-69: Alteracoes de configuracao nao afetam reservas ja confirmadas
- RN-70: Novas reservas devem respeitar as configuracoes atualizadas

---

### UC-25: SetSpaceAvailability

| Campo | Descricao |
|-------|-----------|
| **Nome** | SetSpaceAvailability |
| **Contexto** | Spaces Management (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Space existe e esta ativo
- Ator autenticado com role sindico ou administradora

**Fluxo principal:**
1. Sindico/Administradora define disponibilidade por dia da semana
2. Para cada dia: horario de inicio, horario de fim, se esta disponivel
3. Sistema valida que horario de inicio eh anterior ao de fim
4. Sistema valida consistencia dos horarios (sem sobreposicao)
5. Sistema cria ou atualiza registros SpaceAvailability

**Pos-condicoes:**
- SpaceAvailability definida para os dias configurados
- Novas reservas devem respeitar a disponibilidade

**Cenarios de erro:**
- Horario de inicio posterior ao fim: retorna erro de validacao
- Space inexistente ou inativo: retorna erro
- Formato de horario invalido: retorna erro de validacao

**Regras de negocio aplicaveis:**
- RN-71: Disponibilidade definida por dia da semana (segunda a domingo)
- RN-72: Alteracoes de disponibilidade nao afetam reservas ja confirmadas
- RN-73: Dias sem disponibilidade configurada sao considerados indisponiveis

---

### UC-26: BlockSpace

| Campo | Descricao |
|-------|-----------|
| **Nome** | BlockSpace |
| **Contexto** | Spaces Management (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Space existe e esta ativo
- Ator autenticado com role sindico ou administradora
- Periodo de bloqueio definido (data inicio, data fim)

**Fluxo principal:**
1. Sindico/Administradora solicita bloqueio do espaco para periodo (manutencao, evento privado, etc.)
2. Sistema valida periodo (data inicio anterior a data fim, datas futuras)
3. Sistema cria registro SpaceBlock com motivo
4. Sistema identifica reservas confirmadas no periodo afetado
5. Sistema cancela reservas afetadas (emitindo ReservationCanceled para cada)
6. Sistema notifica moradores afetados
7. Sistema emite evento `SpaceBlocked`

**Pos-condicoes:**
- SpaceBlock criado para o periodo
- Reservas conflitantes canceladas
- Moradores notificados
- Evento emitido: `SpaceBlocked`

**Cenarios de erro:**
- Data inicio posterior a data fim: retorna erro de validacao
- Datas no passado: retorna erro de validacao
- Space inexistente ou inativo: retorna erro

**Regras de negocio aplicaveis:**
- RN-74: Bloqueio cancela automaticamente reservas no periodo
- RN-75: Motivo do bloqueio eh obrigatorio
- RN-76: Moradores afetados devem ser notificados

---

### UC-27: DeactivateSpace

| Campo | Descricao |
|-------|-----------|
| **Nome** | DeactivateSpace |
| **Contexto** | Spaces Management (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Space existe e esta ativo
- Ator autenticado com role sindico ou administradora

**Fluxo principal:**
1. Sindico/Administradora solicita desativacao do espaco
2. Sistema valida que espaco esta ativo
3. Sistema altera status para `inactive`
4. Sistema cancela todas as reservas futuras do espaco
5. Sistema notifica moradores com reservas canceladas
6. Sistema emite evento `SpaceDeactivated`

**Pos-condicoes:**
- Space com status `inactive`
- Todas as reservas futuras canceladas
- Moradores notificados
- Evento emitido: `SpaceDeactivated`

**Cenarios de erro:**
- Space ja inativo: retorna erro
- Space inexistente: retorna erro 404

**Regras de negocio aplicaveis:**
- RN-77: Desativacao cancela automaticamente todas as reservas futuras
- RN-78: Espaco desativado nao aparece em buscas de disponibilidade
- RN-79: Historico do espaco eh preservado

---

### UC-28: ConfigureSpaceRules

| Campo | Descricao |
|-------|-----------|
| **Nome** | ConfigureSpaceRules |
| **Contexto** | Spaces Management (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Space existe e esta ativo
- Ator autenticado com role sindico ou administradora

**Fluxo principal:**
1. Sindico/Administradora define regras especificas do espaco
2. Regras possiveis: max_guests, noise_curfew_time, music_allowed, alcohol_allowed, requires_deposit, deposit_amount, cleaning_fee, min_advance_hours, max_advance_days
3. Sistema valida consistencia das regras
4. Sistema cria ou atualiza registros SpaceRule

**Pos-condicoes:**
- SpaceRules configuradas para o espaco
- Novas reservas devem respeitar as regras

**Cenarios de erro:**
- Valores invalidos (negativos, inconsistentes): retorna erro de validacao
- Space inexistente ou inativo: retorna erro

**Regras de negocio aplicaveis:**
- RN-80: Regras sao por espaco e independentes entre si
- RN-81: Alteracoes de regras nao afetam reservas ja confirmadas
- RN-82: Regras sao consultadas durante criacao e aprovacao de reservas

---

## 6. Tenant Domain — Reservations (CORE)

### UC-29: CreateReservation

| Campo | Descricao |
|-------|-----------|
| **Nome** | CreateReservation |
| **Contexto** | Reservations (Tenant Domain) — **CORE USE CASE** |
| **Ator** | Condomino |

**Pre-condicoes:**
- Tenant ativo com assinatura valida
- Ator autenticado como condomino com status ativo
- Unidade do condomino ativa

**Fluxo principal:**
1. Condomino submete solicitacao de reserva (space_id, date, start_time, end_time, guest_count, observacoes)
2. **Validacao de espaco:** Sistema verifica que espaco esta ativo
3. **Validacao de unidade/morador:** Sistema verifica que unidade esta ativa e morador esta ativo
4. **Validacao de disponibilidade:** Sistema verifica que data/horario esta dentro da disponibilidade configurada (SpaceAvailability) do espaco
5. **Validacao de antecedencia:** Sistema verifica que a reserva respeita advance_booking_days (dias minimos e maximos de antecedencia)
6. **Validacao de duracao:** Sistema verifica que duracao esta dentro de max_duration_hours do espaco
7. **Verificacao de conflitos:** ConflictChecker (domain service) verifica sobreposicao com reservas existentes — utiliza **lock pessimista no banco** para prevenir race conditions
8. **Validacao de capacidade:** Sistema verifica que guest_count nao excede capacidade do espaco
9. **Validacao de limite mensal:** Sistema verifica limite de reservas mensais para a unidade (via feature flag, configuravel)
10. **Verificacao de penalidades:** Sistema verifica se unidade ou morador possui penalidades ativas (temporary_block impede reserva; reservation_limit_reduction reduz limite mensal)
11. **Determinacao de status:**
    - Se espaco `requires_approval = true`: status = `pending_approval`
    - Se espaco `requires_approval = false`: status = `confirmed`
12. Sistema cria registro Reservation
13. Sistema emite evento `ReservationRequested` (se pending) ou `ReservationConfirmed` (se confirmada)
14. Listeners enviam notificacao ao sindico (se pending) ou ao condomino (se confirmada)

**Pos-condicoes:**
- Reservation criada com status `pending_approval` ou `confirmed`
- Slot de tempo reservado (bloqueado para conflitos)
- Evento emitido: `ReservationRequested` ou `ReservationConfirmed`

**Cenarios de erro:**
- Espaco inativo ou inexistente: retorna erro
- Unidade ou morador inativo: retorna erro
- Data/horario fora da disponibilidade: retorna erro com horarios disponiveis
- Antecedencia insuficiente ou excessiva: retorna erro com limites
- Duracao excede maximo: retorna erro com limite
- Conflito de horario: retorna erro com informacao do conflito
- Capacidade excedida: retorna erro com capacidade maxima
- Limite mensal atingido: retorna erro com informacao do limite
- Penalidade de bloqueio temporario ativa: retorna erro com data de fim do bloqueio
- Penalidade de reducao de limite ativa: aplica limite reduzido, pode resultar em erro de limite

**Regras de negocio aplicaveis:**
- RN-83: Verificacao de conflitos usa lock pessimista para concorrencia
- RN-84: Espaco com requires_approval gera reserva com status pending_approval
- RN-85: Limite mensal de reservas por unidade eh configuravel via feature flag
- RN-86: Penalidades ativas afetam capacidade de reservar
- RN-87: Validacao completa ocorre atomicamente (tudo ou nada)
- RN-88: Antecedencia minima e maxima configuravel por espaco

---

### UC-30: ApproveReservation

| Campo | Descricao |
|-------|-----------|
| **Nome** | ApproveReservation |
| **Contexto** | Reservations (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Reservation existe com status `pending_approval`
- Ator autenticado com role sindico ou administradora

**Fluxo principal:**
1. Sindico/Administradora solicita aprovacao da reserva
2. Sistema valida status `pending_approval`
3. **Re-validacao de conflitos:** Sistema executa ConflictChecker novamente (para cobrir race conditions entre a criacao e a aprovacao)
4. Se nao houver conflitos: Sistema altera status para `confirmed`
5. Sistema registra quem aprovou e quando
6. Sistema emite evento `ReservationConfirmed`
7. Listener notifica o condomino sobre a confirmacao

**Pos-condicoes:**
- Reservation com status `confirmed`
- Condomino notificado
- Evento emitido: `ReservationConfirmed`

**Cenarios de erro:**
- Reserva nao esta em pending_approval: retorna erro
- Conflito detectado na re-validacao: retorna erro ao sindico informando o conflito, reserva permanece em pending_approval
- Reserva inexistente: retorna erro 404

**Regras de negocio aplicaveis:**
- RN-89: Re-validacao de conflitos obrigatoria na aprovacao
- RN-90: Apenas reservas em pending_approval podem ser aprovadas
- RN-91: Registro de auditoria de quem aprovou

---

### UC-31: RejectReservation

| Campo | Descricao |
|-------|-----------|
| **Nome** | RejectReservation |
| **Contexto** | Reservations (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Reservation existe com status `pending_approval`
- Motivo da rejeicao informado
- Ator autenticado com role sindico ou administradora

**Fluxo principal:**
1. Sindico/Administradora rejeita a reserva informando motivo
2. Sistema valida status `pending_approval`
3. Sistema altera status para `rejected`
4. Sistema registra motivo, quem rejeitou e quando
5. Sistema emite evento `ReservationRejected`
6. Listener notifica o condomino sobre a rejeicao com motivo

**Pos-condicoes:**
- Reservation com status `rejected`
- Motivo registrado
- Condomino notificado
- Evento emitido: `ReservationRejected`

**Cenarios de erro:**
- Reserva nao esta em pending_approval: retorna erro
- Motivo nao informado: retorna erro de validacao

**Regras de negocio aplicaveis:**
- RN-92: Motivo de rejeicao obrigatorio
- RN-93: Apenas reservas em pending_approval podem ser rejeitadas

---

### UC-32: CancelReservation

| Campo | Descricao |
|-------|-----------|
| **Nome** | CancelReservation |
| **Contexto** | Reservations (Tenant Domain) |
| **Ator** | Condomino / Sindico |

**Pre-condicoes:**
- Reservation existe com status `confirmed` ou `pending_approval`
- Ator autenticado como condomino (dono da reserva) ou sindico

**Fluxo principal:**
1. Ator solicita cancelamento da reserva (motivo opcional)
2. Sistema valida que reserva esta em status cancelavel (confirmed ou pending_approval)
3. Sistema verifica se cancelamento eh tardio (dentro da janela configuravel de cancelamento, ex: menos de 24h antes do inicio)
4. Sistema altera status para `canceled`
5. Sistema registra quem cancelou, quando e motivo
6. Se cancelamento tardio por condomino: Sistema dispara registro de violacao automatica (late_cancellation)
7. Sistema emite evento `ReservationCanceled`
8. Listeners notificam partes interessadas

**Pos-condicoes:**
- Reservation com status `canceled`
- Slot de tempo liberado para novas reservas
- Se cancelamento tardio: violacao registrada automaticamente
- Evento emitido: `ReservationCanceled`

**Cenarios de erro:**
- Reserva ja cancelada/completed/no_show: retorna erro
- Reserva inexistente: retorna erro 404
- Condomino tentando cancelar reserva de outro: retorna erro de autorizacao

**Regras de negocio aplicaveis:**
- RN-94: Cancelamento tardio (dentro da janela configuravel) gera violacao automatica
- RN-95: Janela de cancelamento tardio configuravel por espaco
- RN-96: Sindico pode cancelar qualquer reserva sem gerar violacao para si

---

### UC-33: CompleteReservation

| Campo | Descricao |
|-------|-----------|
| **Nome** | CompleteReservation |
| **Contexto** | Reservations (Tenant Domain) |
| **Ator** | System / Sindico |

**Pre-condicoes:**
- Reservation existe com status `confirmed`
- Horario de fim da reserva atingido

**Fluxo principal:**
1. System (via scheduler) ou Sindico marca reserva como concluida
2. Sistema valida que reserva esta confirmada
3. Sistema valida que horario de fim ja passou
4. Sistema altera status para `completed`
5. Sistema emite evento `ReservationCompleted`

**Pos-condicoes:**
- Reservation com status `completed`
- Evento emitido: `ReservationCompleted`

**Cenarios de erro:**
- Reserva nao esta confirmada: retorna erro
- Horario de fim ainda nao atingido (se acionado manualmente): retorna erro

**Regras de negocio aplicaveis:**
- RN-97: Conclusao automatica via scheduler apos horario de fim
- RN-98: Sindico pode concluir manualmente apos horario de fim

---

### UC-34: MarkAsNoShow

| Campo | Descricao |
|-------|-----------|
| **Nome** | MarkAsNoShow |
| **Contexto** | Reservations (Tenant Domain) |
| **Ator** | Sindico / Funcionario |

**Pre-condicoes:**
- Reservation existe com status `confirmed`
- Janela de confirmacao de presenca expirada (horario de inicio + margem configuravel)
- Ator autenticado com role sindico ou funcionario

**Fluxo principal:**
1. Sindico/Funcionario marca reserva como no-show
2. Sistema valida que reserva esta confirmada
3. Sistema valida que janela de confirmacao expirou
4. Sistema altera status para `no_show`
5. Sistema emite evento `ReservationNoShow`
6. **Listener automatico:** Evento ReservationNoShow dispara RegisterViolation automaticamente (tipo: no_show)
7. Condomino eh notificado

**Pos-condicoes:**
- Reservation com status `no_show`
- Violacao registrada automaticamente
- Evento emitido: `ReservationNoShow`

**Cenarios de erro:**
- Reserva nao esta confirmada: retorna erro
- Janela de confirmacao ainda nao expirou: retorna erro
- Reserva inexistente: retorna erro 404

**Regras de negocio aplicaveis:**
- RN-99: No-show gera violacao automatica
- RN-100: Janela de confirmacao de presenca configuravel por espaco
- RN-101: Apenas sindico ou funcionario podem marcar no-show

---

### UC-35: ListAvailableSlots

| Campo | Descricao |
|-------|-----------|
| **Nome** | ListAvailableSlots |
| **Contexto** | Reservations (Tenant Domain) |
| **Ator** | Condomino |

**Pre-condicoes:**
- Tenant ativo
- Ator autenticado como condomino
- Space informado e ativo

**Fluxo principal:**
1. Condomino consulta slots disponiveis para um espaco em uma data especifica
2. Sistema busca SpaceAvailability para o dia da semana correspondente
3. Sistema busca reservas existentes (confirmed e pending_approval) para a data
4. Sistema busca SpaceBlocks que cobrem a data
5. Sistema calcula slots livres (disponibilidade - reservas - bloqueios)
6. Sistema retorna lista de slots disponiveis com horarios

**Pos-condicoes:**
- Lista de slots disponiveis retornada
- Nenhuma alteracao de estado

**Cenarios de erro:**
- Space inexistente ou inativo: retorna erro
- Data no passado: retorna erro de validacao
- Data alem do limite de antecedencia: retorna erro
- Dia sem disponibilidade configurada: retorna lista vazia

**Regras de negocio aplicaveis:**
- RN-102: Slots consideram reservas confirmadas E pendentes de aprovacao
- RN-103: Bloqueios de espaco removem slots da disponibilidade
- RN-104: Consulta eh read-only, nao reserva slots

---

## 7. Tenant Domain — Governance

### UC-36: RegisterViolation

| Campo | Descricao |
|-------|-----------|
| **Nome** | RegisterViolation |
| **Contexto** | Governance (Tenant Domain) |
| **Ator** | Sindico / Administradora / System |

**Pre-condicoes:**
- Unidade e/ou morador identificados
- Tipo de violacao informado
- Se automatica: evento gatilho recebido (ReservationNoShow, ReservationCanceled com late_cancellation)

**Fluxo principal:**
1. **Se automatica:** Listener de evento (NoShow ou LateCancellation) dispara registro
2. **Se manual:** Sindico/Administradora registra violacao com descricao e evidencias
3. Sistema cria Violation com tipo, gravidade, descricao
4. Se automatica: status = `confirmed` (nao requer revisao)
5. Se manual: status = `pending` (aguarda confirmacao do sindico)
6. Sistema vincula violacao a unidade e morador
7. Sistema emite evento `ViolationRegistered`

**Pos-condicoes:**
- Violation criada com status `confirmed` (automatica) ou `pending` (manual)
- Vinculada a unidade e morador
- Evento emitido: `ViolationRegistered`
- Se automatica e confirmada: dispara calculo de penalidade

**Cenarios de erro:**
- Unidade inexistente: retorna erro
- Morador inexistente: retorna erro
- Dados insuficientes para registro: retorna erro de validacao

**Regras de negocio aplicaveis:**
- RN-105: Violacoes automaticas (no_show, late_cancellation) sao confirmadas automaticamente
- RN-106: Violacoes manuais requerem confirmacao posterior do sindico
- RN-107: Toda violacao deve ser vinculada a uma unidade

---

### UC-37: ConfirmViolation

| Campo | Descricao |
|-------|-----------|
| **Nome** | ConfirmViolation |
| **Contexto** | Governance (Tenant Domain) |
| **Ator** | Sindico |

**Pre-condicoes:**
- Violation existe com status `pending`
- Ator autenticado como sindico

**Fluxo principal:**
1. Sindico revisa violacao e confirma
2. Sistema valida status `pending`
3. Sistema altera status para `confirmed`
4. Sistema registra quem confirmou e quando
5. Sistema dispara calculo de penalidade via PenaltyPolicy (domain service)
6. PenaltyPolicy avalia historico de violacoes da unidade/morador e determina penalidade adequada
7. Sistema emite evento `ViolationConfirmed`
8. Sistema emite evento `PenaltyApplied` (se penalidade gerada)

**Pos-condicoes:**
- Violation com status `confirmed`
- Penalidade calculada e aplicada conforme PenaltyPolicy
- Eventos emitidos: `ViolationConfirmed`, `PenaltyApplied`

**Cenarios de erro:**
- Violacao nao esta em status pending: retorna erro
- Violacao inexistente: retorna erro 404
- PenaltyPolicy nao configurada: aplica penalidade padrao (advertencia)

**Regras de negocio aplicaveis:**
- RN-108: Confirmacao dispara calculo automatico de penalidade
- RN-109: PenaltyPolicy considera historico de violacoes para escalonamento
- RN-110: Penalidade padrao eh advertencia (warning) se nenhuma politica configurada

---

### UC-38: DismissViolation

| Campo | Descricao |
|-------|-----------|
| **Nome** | DismissViolation |
| **Contexto** | Governance (Tenant Domain) |
| **Ator** | Sindico |

**Pre-condicoes:**
- Violation existe com status `pending`
- Motivo do arquivamento informado
- Ator autenticado como sindico

**Fluxo principal:**
1. Sindico arquiva (dismiss) a violacao informando motivo
2. Sistema valida status `pending`
3. Sistema altera status para `dismissed`
4. Sistema registra motivo, quem arquivou e quando
5. Sistema emite evento `ViolationDismissed`

**Pos-condicoes:**
- Violation com status `dismissed`
- Nenhuma penalidade aplicada
- Evento emitido: `ViolationDismissed`

**Cenarios de erro:**
- Violacao nao esta em status pending: retorna erro
- Motivo nao informado: retorna erro de validacao

**Regras de negocio aplicaveis:**
- RN-111: Motivo de arquivamento obrigatorio
- RN-112: Violacao arquivada nao gera penalidade
- RN-113: Registro de auditoria de quem arquivou

---

### UC-39: ContestViolation

| Campo | Descricao |
|-------|-----------|
| **Nome** | ContestViolation |
| **Contexto** | Governance (Tenant Domain) |
| **Ator** | Condomino |

**Pre-condicoes:**
- Violation existe com status `confirmed` e vinculada ao condomino/unidade do ator
- Prazo de contestacao nao expirado (configuravel)
- Ator autenticado como condomino

**Fluxo principal:**
1. Condomino submete contestacao com motivo e evidencias (texto, referencia a documentos)
2. Sistema valida que violacao esta confirmada
3. Sistema valida prazo de contestacao
4. Sistema cria ViolationContestation vinculada a Violation
5. Sistema altera status da Violation para `contested`
6. Sistema emite evento `ViolationContested`
7. Listener notifica sindico sobre nova contestacao

**Pos-condicoes:**
- ViolationContestation criada
- Violation com status `contested`
- Sindico notificado
- Evento emitido: `ViolationContested`

**Cenarios de erro:**
- Violacao nao esta confirmada: retorna erro
- Prazo de contestacao expirado: retorna erro
- Condomino tentando contestar violacao de outro: retorna erro de autorizacao
- Ja existe contestacao pendente para esta violacao: retorna erro

**Regras de negocio aplicaveis:**
- RN-114: Prazo de contestacao configuravel (padrao 7 dias apos confirmacao)
- RN-115: Apenas uma contestacao ativa por violacao
- RN-116: Condomino so pode contestar violacoes de sua propria unidade

---

### UC-40: ReviewContestation

| Campo | Descricao |
|-------|-----------|
| **Nome** | ReviewContestation |
| **Contexto** | Governance (Tenant Domain) |
| **Ator** | Sindico |

**Pre-condicoes:**
- ViolationContestation existe com status pendente
- Ator autenticado como sindico

**Fluxo principal:**
1. Sindico revisa contestacao e decide: aceitar ou rejeitar
2. **Se aceitar:**
   a. Sistema altera status da contestacao para `accepted`
   b. Sistema revoga penalidade ativa vinculada a violacao (executa RevokePenalty)
   c. Sistema altera status da violacao para `dismissed`
   d. Sistema emite evento `ContestationAccepted`
3. **Se rejeitar:**
   a. Sistema altera status da contestacao para `rejected`
   b. Sistema registra motivo da rejeicao
   c. Sistema altera status da violacao de volta para `confirmed`
   d. Sistema emite evento `ContestationRejected`
4. Sistema registra quem decidiu e quando

**Pos-condicoes:**
- Contestacao com status `accepted` ou `rejected`
- Se aceita: penalidade revogada, violacao arquivada
- Se rejeitada: violacao retorna a `confirmed`, penalidade mantida
- Evento emitido: `ContestationAccepted` ou `ContestationRejected`

**Cenarios de erro:**
- Contestacao inexistente: retorna erro 404
- Contestacao ja revisada: retorna erro

**Regras de negocio aplicaveis:**
- RN-117: Aceitacao de contestacao revoga penalidade automaticamente
- RN-118: Rejeicao de contestacao mantem penalidade ativa
- RN-119: Registro de auditoria completo da decisao

---

### UC-41: ApplyPenalty

| Campo | Descricao |
|-------|-----------|
| **Nome** | ApplyPenalty |
| **Contexto** | Governance (Tenant Domain) |
| **Ator** | System |

**Pre-condicoes:**
- Violacao confirmada
- PenaltyPolicy configurada (ou padrao)

**Fluxo principal:**
1. Sistema recebe evento `ViolationConfirmed`
2. Sistema consulta PenaltyPolicy vigente
3. Sistema conta violacoes anteriores da unidade/morador (historico)
4. Sistema determina penalidade baseada no escalonamento:
   - 1a ocorrencia: advertencia (warning)
   - 2a ocorrencia: advertencia formal
   - 3a ocorrencia: bloqueio temporario (temporary_block) por periodo configuravel
   - 4a+ ocorrencias: bloqueio temporario com duracao crescente, reducao de limite de reservas
5. Sistema cria registro Penalty vinculado a Violation
6. Sistema emite evento `PenaltyApplied`
7. Listener notifica condomino sobre penalidade

**Pos-condicoes:**
- Penalty criada e vinculada a violacao
- Se bloqueio temporario: morador impedido de reservar ate fim do periodo
- Evento emitido: `PenaltyApplied`

**Cenarios de erro:**
- PenaltyPolicy nao encontrada: usa escalonamento padrao
- Violacao nao confirmada: penalidade nao aplicada

**Regras de negocio aplicaveis:**
- RN-120: Escalonamento progressivo baseado em historico de violacoes
- RN-121: PenaltyPolicy configuravel pelo sindico
- RN-122: Tipos de penalidade: warning, formal_warning, temporary_block, reservation_limit_reduction

---

### UC-42: RevokePenalty

| Campo | Descricao |
|-------|-----------|
| **Nome** | RevokePenalty |
| **Contexto** | Governance (Tenant Domain) |
| **Ator** | Sindico |

**Pre-condicoes:**
- Penalty ativa existente
- Motivo da revogacao informado
- Ator autenticado como sindico

**Fluxo principal:**
1. Sindico solicita revogacao da penalidade informando motivo
2. Sistema valida que penalidade esta ativa
3. Sistema altera status da penalidade para `revoked`
4. Sistema registra motivo, quem revogou e quando
5. Se bloqueio temporario: morador pode reservar novamente
6. Sistema emite evento `PenaltyRevoked`

**Pos-condicoes:**
- Penalty com status `revoked`
- Efeitos da penalidade removidos
- Evento emitido: `PenaltyRevoked`

**Cenarios de erro:**
- Penalidade inexistente: retorna erro 404
- Penalidade ja revogada ou expirada: retorna erro
- Motivo nao informado: retorna erro de validacao

**Regras de negocio aplicaveis:**
- RN-123: Motivo de revogacao obrigatorio
- RN-124: Revogacao remove efeitos imediatamente
- RN-125: Registro de auditoria completo

---

### UC-43: ConfigurePenaltyPolicy

| Campo | Descricao |
|-------|-----------|
| **Nome** | ConfigurePenaltyPolicy |
| **Contexto** | Governance (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Tenant ativo
- Ator autenticado com role sindico ou administradora

**Fluxo principal:**
1. Sindico/Administradora define regras de escalonamento automatico
2. Para cada nivel de ocorrencia: define tipo de penalidade, duracao (se bloqueio), descricao
3. Sistema valida consistencia das regras (escalonamento progressivo)
4. Sistema cria ou atualiza PenaltyPolicy

**Pos-condicoes:**
- PenaltyPolicy configurada
- Proximas violacoes confirmadas usam a nova politica

**Cenarios de erro:**
- Regras inconsistentes (ex: bloqueio antes de advertencia): retorna aviso (nao bloqueante)
- Dados invalidos: retorna erro de validacao

**Regras de negocio aplicaveis:**
- RN-126: Politica de penalidade configuravel por tenant
- RN-127: Escalonamento deve ser progressivo (recomendacao, nao obrigatorio)
- RN-128: Alteracoes na politica nao afetam penalidades ja aplicadas

---

### UC-62: UploadCondominiumDocument

| Campo | Descricao |
|-------|-----------|
| **Nome** | UploadCondominiumDocument |
| **Contexto** | Governance (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Tenant ativo
- Ator autenticado com role sindico ou administradora
- Tipo de documento informado (convencao, regimento_interno, ata_assembleia, other)

**Fluxo principal:**
1. Sindico/Administradora submete documento com tipo, titulo, texto completo e opcionalmente arquivo PDF
2. Sistema valida tipo e campos obrigatorios
3. Se arquivo PDF fornecido: calcula hash SHA-256 para integridade
4. Sistema cria CondominiumDocument com status `draft`
5. Sistema emite evento `DocumentUploaded`

**Pos-condicoes:**
- CondominiumDocument criado com status `draft`
- Evento emitido: `DocumentUploaded`

**Cenarios de erro:**
- Tipo invalido: retorna erro de validacao
- Texto vazio: retorna erro de validacao
- Tenant inativo: retorna erro de autorizacao

**Regras de negocio aplicaveis:**
- RN-129: Documento criado sempre com status `draft`
- RN-130: Hash SHA-256 calculado para arquivos PDF
- RN-131: Apenas sindico e administradora podem criar documentos

---

### UC-63: ParseDocumentSections

| Campo | Descricao |
|-------|-----------|
| **Nome** | ParseDocumentSections |
| **Contexto** | Governance (Tenant Domain) |
| **Ator** | Sindico / Administradora / System |

**Pre-condicoes:**
- CondominiumDocument existe com status `draft`
- Secoes informadas (manualmente ou via processo automatizado de IA)

**Fluxo principal:**
1. Sistema recebe lista de secoes com numeracao, titulo, conteudo e ordem
2. Sistema valida unicidade de section_number dentro do documento
3. Sistema cria DocumentSections com hierarquia (parent_section_id)
4. Sistema emite evento `DocumentSectionsParsed`
5. Se IA habilitada: sistema gera embeddings para cada secao via GenerateEmbedding (UC-60)

**Pos-condicoes:**
- DocumentSections criadas vinculadas ao documento
- Se IA habilitada: embeddings gerados para busca semantica
- Evento emitido: `DocumentSectionsParsed`

**Cenarios de erro:**
- Documento nao esta em status `draft`: retorna erro
- Section_number duplicado dentro do documento: retorna erro de validacao
- Conteudo vazio em secao: retorna erro de validacao

**Regras de negocio aplicaveis:**
- RN-132: Secoes so podem ser criadas para documentos em `draft`
- RN-133: Section_number unico por documento
- RN-134: Hierarquia de secoes preservada (capitulo > artigo > paragrafo)
- RN-135: Embeddings gerados automaticamente se IA habilitada no tenant

---

### UC-64: ActivateCondominiumDocument

| Campo | Descricao |
|-------|-----------|
| **Nome** | ActivateCondominiumDocument |
| **Contexto** | Governance (Tenant Domain) |
| **Ator** | Sindico |

**Pre-condicoes:**
- CondominiumDocument existe com status `draft`
- Documento possui pelo menos uma secao parseada
- Ator autenticado como sindico

**Fluxo principal:**
1. Sindico ativa o documento
2. Sistema valida status `draft` e existencia de secoes
3. Sistema verifica se existe outro documento `active` do mesmo tipo
4. Se sim: arquiva o documento anterior (status → `archived`)
5. Sistema altera status do documento para `active`
6. Sistema emite evento `DocumentActivated`
7. Se documento anterior foi arquivado: emite `DocumentArchived`

**Pos-condicoes:**
- Documento ativo (apenas um por tipo no tenant)
- Documento anterior do mesmo tipo arquivado
- Eventos emitidos: `DocumentActivated`, opcionalmente `DocumentArchived`

**Cenarios de erro:**
- Documento sem secoes: retorna erro
- Documento ja ativo ou arquivado: retorna erro
- Ator nao e sindico: retorna erro de autorizacao

**Regras de negocio aplicaveis:**
- RN-136: Apenas um documento `active` por tipo no tenant
- RN-137: Ativacao arquiva automaticamente versao anterior
- RN-138: Documento deve ter secoes parseadas antes de ativar
- RN-139: Apenas sindico pode ativar documentos

---

### UC-65: SearchDocumentSections

| Campo | Descricao |
|-------|-----------|
| **Nome** | SearchDocumentSections |
| **Contexto** | Governance (Tenant Domain) |
| **Ator** | Condomino / Sindico / Administradora / System (IA) |

**Pre-condicoes:**
- Tenant ativo
- Ator autenticado

**Fluxo principal:**
1. Ator submete busca textual ou semantica
2. **Busca textual:** Sistema pesquisa por termo em `content` das secoes de documentos ativos
3. **Busca semantica (IA):** Sistema usa SemanticSearch (UC-61) com source_type='document_section'
4. Sistema retorna secoes relevantes com contexto (section_number, titulo, trecho, documento de origem)
5. Sistema registra busca em ai_usage_logs (se busca semantica)

**Pos-condicoes:**
- Resultados retornados com referencia a documento e secao
- Se busca semantica: registro em ai_usage_logs

**Cenarios de erro:**
- Nenhum documento ativo do tipo buscado: retorna lista vazia
- Termo de busca vazio: retorna erro de validacao

**Regras de negocio aplicaveis:**
- RN-140: Busca retorna apenas secoes de documentos ativos
- RN-141: Busca semantica requer embeddings gerados previamente
- RN-142: Resultados incluem referencia ao artigo/secao para consulta direta

---

### UC-66: ViewDocumentVersionHistory

| Campo | Descricao |
|-------|-----------|
| **Nome** | ViewDocumentVersionHistory |
| **Contexto** | Governance (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Tenant ativo
- Ator autenticado com role sindico ou administradora

**Fluxo principal:**
1. Ator solicita historico de versoes de um tipo de documento (ex: convencao)
2. Sistema lista todos os documentos daquele tipo, ordenados por versao (desc)
3. Sistema retorna: versao, status, data de aprovacao, assembleia, criado_por

**Pos-condicoes:**
- Historico de versoes retornado

**Cenarios de erro:**
- Nenhum documento do tipo solicitado: retorna lista vazia

**Regras de negocio aplicaveis:**
- RN-143: Historico acessivel apenas por sindico e administradora
- RN-144: Documentos arquivados permanecem no historico para consulta

---

## 8. Tenant Domain — People Control

### UC-44: RegisterGuest

| Campo | Descricao |
|-------|-----------|
| **Nome** | RegisterGuest |
| **Contexto** | People Control (Tenant Domain) |
| **Ator** | Condomino |

**Pre-condicoes:**
- Reservation existente com status `confirmed` ou `pending_approval`
- Reservation pertence ao condomino
- Ator autenticado como condomino

**Fluxo principal:**
1. Condomino registra convidado vinculado a uma reserva (nome, documento, telefone)
2. Sistema valida que reserva pertence ao condomino
3. Sistema verifica que guest_count total (convidados ja registrados + novo) nao excede capacidade do espaco
4. Sistema cria registro Guest vinculado a Reservation
5. Sistema emite evento `GuestRegistered`

**Pos-condicoes:**
- Guest registrado e vinculado a reserva
- Evento emitido: `GuestRegistered`

**Cenarios de erro:**
- Reserva inexistente ou nao pertence ao condomino: retorna erro de autorizacao
- Capacidade do espaco seria excedida: retorna erro com capacidade disponivel
- Dados obrigatorios do convidado ausentes: retorna erro de validacao
- Reserva cancelada/completed/no_show: retorna erro

**Regras de negocio aplicaveis:**
- RN-129: Convidados sempre vinculados a uma reserva
- RN-130: Total de convidados nao pode exceder capacidade do espaco
- RN-131: Registro de convidados possivel ate o inicio da reserva

---

### UC-45: CheckInGuest

| Campo | Descricao |
|-------|-----------|
| **Nome** | CheckInGuest |
| **Contexto** | People Control (Tenant Domain) |
| **Ator** | Funcionario / Portaria |

**Pre-condicoes:**
- Guest registrado vinculado a reserva
- Reserva confirmada
- Dentro da janela de tempo da reserva (com margem configuravel)
- Ator autenticado com role funcionario

**Fluxo principal:**
1. Funcionario/Portaria busca convidado (por nome, documento ou reserva)
2. Sistema valida que convidado esta esperado (Guest registrado)
3. Sistema valida que reserva esta confirmada
4. Sistema valida que horario atual esta dentro da janela da reserva
5. Sistema registra check-in (data/hora de entrada)
6. Sistema emite evento `GuestCheckedIn`

**Pos-condicoes:**
- Check-in registrado com timestamp
- Evento emitido: `GuestCheckedIn`

**Cenarios de erro:**
- Convidado nao encontrado: retorna erro (portaria deve negar acesso)
- Reserva nao confirmada: retorna erro
- Fora da janela de tempo da reserva: retorna erro
- Check-in ja realizado: retorna erro (duplicado)

**Regras de negocio aplicaveis:**
- RN-132: Check-in apenas para convidados pre-registrados
- RN-133: Reserva deve estar confirmada no momento do check-in
- RN-134: Janela de check-in com margem configuravel (ex: 30min antes do inicio)

---

### UC-46: CheckOutGuest

| Campo | Descricao |
|-------|-----------|
| **Nome** | CheckOutGuest |
| **Contexto** | People Control (Tenant Domain) |
| **Ator** | Funcionario / Portaria |

**Pre-condicoes:**
- Guest com check-in registrado
- Ator autenticado com role funcionario

**Fluxo principal:**
1. Funcionario/Portaria registra saida do convidado
2. Sistema valida que check-in foi realizado
3. Sistema registra check-out (data/hora de saida)
4. Sistema emite evento `GuestCheckedOut`

**Pos-condicoes:**
- Check-out registrado com timestamp
- Evento emitido: `GuestCheckedOut`

**Cenarios de erro:**
- Check-in nao realizado: retorna erro
- Check-out ja registrado: retorna erro (duplicado)

**Regras de negocio aplicaveis:**
- RN-135: Check-out requer check-in previo
- RN-136: Registro de permanencia (duracao) calculado automaticamente

---

### UC-47: DenyGuestAccess

| Campo | Descricao |
|-------|-----------|
| **Nome** | DenyGuestAccess |
| **Contexto** | People Control (Tenant Domain) |
| **Ator** | Funcionario / Portaria |

**Pre-condicoes:**
- Ator autenticado com role funcionario

**Fluxo principal:**
1. Funcionario/Portaria registra negativa de acesso com motivo
2. Motivos possiveis: sem reserva, reserva cancelada, convidado nao registrado, documento invalido, fora do horario
3. Sistema registra ocorrencia (pessoa, motivo, data/hora)
4. Sistema emite evento `GuestDenied`

**Pos-condicoes:**
- Registro de negativa de acesso criado
- Evento emitido: `GuestDenied`

**Cenarios de erro:**
- Motivo nao informado: retorna erro de validacao

**Regras de negocio aplicaveis:**
- RN-137: Toda negativa de acesso deve ser registrada com motivo
- RN-138: Registro disponivel para auditoria e relatorios

---

### UC-48: RegisterServiceProvider

| Campo | Descricao |
|-------|-----------|
| **Nome** | RegisterServiceProvider |
| **Contexto** | People Control (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Tenant ativo
- Ator autenticado com role sindico ou administradora

**Fluxo principal:**
1. Sindico/Administradora registra prestador de servico (nome, empresa, CPF/CNPJ, tipo de servico, telefone)
2. Sistema valida dados obrigatorios
3. Sistema cria ServiceProvider com status `approved`
4. Sistema emite evento `ServiceProviderRegistered`

**Pos-condicoes:**
- ServiceProvider criado com status `approved`
- Disponivel para agendamento de visitas
- Evento emitido: `ServiceProviderRegistered`

**Cenarios de erro:**
- Dados obrigatorios ausentes: retorna erro de validacao
- CPF/CNPJ duplicado no tenant: retorna erro

**Regras de negocio aplicaveis:**
- RN-139: Prestadores devem ser pre-aprovados antes de acessar o condominio
- RN-140: Cadastro centralizado de prestadores por tenant

---

### UC-49: ScheduleServiceProviderVisit

| Campo | Descricao |
|-------|-----------|
| **Nome** | ScheduleServiceProviderVisit |
| **Contexto** | People Control (Tenant Domain) |
| **Ator** | Condomino / Sindico |

**Pre-condicoes:**
- ServiceProvider registrado e aprovado
- Ator autenticado como condomino ou sindico

**Fluxo principal:**
1. Ator agenda visita do prestador (service_provider_id, data, horario, unit_id ou reservation_id, motivo)
2. Sistema valida que prestador esta aprovado
3. Sistema cria registro de visita agendada (ReservationServiceProvider se vinculado a reserva)
4. Sistema vincula visita a unidade ou reserva

**Pos-condicoes:**
- Visita agendada
- Portaria pode validar entrada do prestador na data

**Cenarios de erro:**
- Prestador nao aprovado: retorna erro
- Prestador inexistente: retorna erro 404
- Data no passado: retorna erro de validacao

**Regras de negocio aplicaveis:**
- RN-141: Visita de prestador pode ser vinculada a unidade ou reserva
- RN-142: Apenas prestadores aprovados podem ter visitas agendadas

---

### UC-50: CheckInServiceProvider

| Campo | Descricao |
|-------|-----------|
| **Nome** | CheckInServiceProvider |
| **Contexto** | People Control (Tenant Domain) |
| **Ator** | Funcionario / Portaria |

**Pre-condicoes:**
- ServiceProvider registrado e aprovado
- Visita agendada para a data
- Ator autenticado com role funcionario

**Fluxo principal:**
1. Funcionario/Portaria busca prestador (por nome, documento ou agendamento)
2. Sistema valida que prestador esta aprovado
3. Sistema valida que existe visita agendada para a data
4. Sistema registra check-in (data/hora de entrada)
5. Sistema emite evento `ServiceProviderCheckedIn`

**Pos-condicoes:**
- Check-in registrado com timestamp
- Evento emitido: `ServiceProviderCheckedIn`

**Cenarios de erro:**
- Prestador nao aprovado: retorna erro, negar acesso
- Sem visita agendada: retorna erro, negar acesso
- Check-in ja realizado: retorna erro

**Regras de negocio aplicaveis:**
- RN-143: Check-in requer aprovacao previa e agendamento
- RN-144: Portaria valida identidade do prestador

---

### UC-51: CheckOutServiceProvider

| Campo | Descricao |
|-------|-----------|
| **Nome** | CheckOutServiceProvider |
| **Contexto** | People Control (Tenant Domain) |
| **Ator** | Funcionario / Portaria |

**Pre-condicoes:**
- ServiceProvider com check-in registrado
- Ator autenticado com role funcionario

**Fluxo principal:**
1. Funcionario/Portaria registra saida do prestador
2. Sistema valida que check-in foi realizado
3. Sistema registra check-out (data/hora de saida)
4. Sistema emite evento `ServiceProviderCheckedOut`

**Pos-condicoes:**
- Check-out registrado com timestamp
- Evento emitido: `ServiceProviderCheckedOut`

**Cenarios de erro:**
- Check-in nao realizado: retorna erro
- Check-out ja registrado: retorna erro

**Regras de negocio aplicaveis:**
- RN-145: Check-out requer check-in previo
- RN-146: Duracao da visita registrada automaticamente

---

## 9. Tenant Domain — Communication

### UC-52: PublishAnnouncement

| Campo | Descricao |
|-------|-----------|
| **Nome** | PublishAnnouncement |
| **Contexto** | Communication (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Tenant ativo
- Ator autenticado com role sindico ou administradora

**Fluxo principal:**
1. Sindico/Administradora cria aviso (titulo, conteudo, prioridade, audiencia)
2. Audiencia pode ser: todos os moradores, moradores de um bloco, moradores de unidades especificas
3. Sistema valida dados obrigatorios
4. Sistema cria Announcement com status `published`
5. Sistema identifica destinatarios conforme audiencia
6. Sistema emite evento `AnnouncementPublished`
7. Listeners disparam notificacoes para destinatarios (email, push futuro)

**Pos-condicoes:**
- Announcement publicado
- Notificacoes enviadas aos destinatarios
- Evento emitido: `AnnouncementPublished`

**Cenarios de erro:**
- Dados obrigatorios ausentes: retorna erro de validacao
- Audiencia invalida (bloco inexistente, unidades inexistentes): retorna erro

**Regras de negocio aplicaveis:**
- RN-147: Avisos podem ser direcionados a audiencias especificas
- RN-148: Publicacao dispara notificacoes automaticamente
- RN-149: Avisos sao imutaveis apos publicacao (correcoes via novo aviso)

---

### UC-53: MarkAnnouncementAsRead

| Campo | Descricao |
|-------|-----------|
| **Nome** | MarkAnnouncementAsRead |
| **Contexto** | Communication (Tenant Domain) |
| **Ator** | Condomino |

**Pre-condicoes:**
- Announcement existente e publicado
- Condomino eh destinatario do aviso
- Ator autenticado como condomino

**Fluxo principal:**
1. Condomino acessa/visualiza aviso
2. Sistema cria registro AnnouncementRead (announcement_id, tenant_user_id, read_at)
3. Operacao idempotente (marcar como lido novamente nao cria duplicata)

**Pos-condicoes:**
- AnnouncementRead registrado
- Sindico pode consultar taxa de leitura

**Cenarios de erro:**
- Aviso inexistente: retorna erro 404
- Condomino nao eh destinatario: retorna erro de autorizacao

**Regras de negocio aplicaveis:**
- RN-150: Leitura registrada uma unica vez por usuario por aviso
- RN-151: Operacao idempotente

---

### UC-54: ArchiveAnnouncement

| Campo | Descricao |
|-------|-----------|
| **Nome** | ArchiveAnnouncement |
| **Contexto** | Communication (Tenant Domain) |
| **Ator** | Sindico / Administradora |

**Pre-condicoes:**
- Announcement existente e publicado
- Ator autenticado com role sindico ou administradora

**Fluxo principal:**
1. Sindico/Administradora solicita arquivamento do aviso
2. Sistema valida que aviso esta publicado
3. Sistema altera status para `archived`
4. Sistema emite evento `AnnouncementArchived`

**Pos-condicoes:**
- Announcement com status `archived`
- Aviso nao aparece mais em listagens ativas
- Historico preservado
- Evento emitido: `AnnouncementArchived`

**Cenarios de erro:**
- Aviso ja arquivado: retorna erro
- Aviso inexistente: retorna erro 404

**Regras de negocio aplicaveis:**
- RN-152: Aviso arquivado nao aparece em listagens padrao
- RN-153: Historico e registros de leitura preservados

---

### UC-55: CreateSupportRequest

| Campo | Descricao |
|-------|-----------|
| **Nome** | CreateSupportRequest |
| **Contexto** | Communication (Tenant Domain) |
| **Ator** | Condomino |

**Pre-condicoes:**
- Tenant ativo
- Ator autenticado como condomino

**Fluxo principal:**
1. Condomino cria solicitacao (titulo, categoria, descricao, prioridade)
2. Categorias possiveis: manutencao, reclamacao, duvida, sugestao, outro
3. Sistema cria SupportRequest com status `open`
4. Sistema cria primeira SupportMessage com conteudo da solicitacao
5. Sistema emite evento `SupportRequestCreated`
6. Listener notifica sindico/administradora sobre nova solicitacao

**Pos-condicoes:**
- SupportRequest criado com status `open`
- Primeira mensagem registrada
- Sindico notificado
- Evento emitido: `SupportRequestCreated`

**Cenarios de erro:**
- Dados obrigatorios ausentes: retorna erro de validacao
- Categoria invalida: retorna erro de validacao

**Regras de negocio aplicaveis:**
- RN-154: Solicitacao sempre inicia com status `open`
- RN-155: Primeira mensagem eh obrigatoria (descricao do problema)
- RN-156: Notificacao automatica ao sindico

---

### UC-56: ReplySupportRequest

| Campo | Descricao |
|-------|-----------|
| **Nome** | ReplySupportRequest |
| **Contexto** | Communication (Tenant Domain) |
| **Ator** | Condomino / Sindico |

**Pre-condicoes:**
- SupportRequest existente com status `open`
- Ator eh o condomino que criou ou sindico/administradora
- Ator autenticado

**Fluxo principal:**
1. Ator submete mensagem de resposta
2. Sistema valida que solicitacao esta aberta
3. Sistema cria SupportMessage vinculada ao SupportRequest
4. Se mensagem do sindico: pode ser marcada como `internal` (visivel apenas para staff)
5. Sistema notifica a outra parte (se condomino respondeu, notifica sindico e vice-versa)

**Pos-condicoes:**
- SupportMessage criada e vinculada
- Notificacao enviada a outra parte

**Cenarios de erro:**
- Solicitacao fechada ou resolvida: retorna erro
- Solicitacao inexistente: retorna erro 404
- Condomino tentando responder solicitacao de outro: retorna erro de autorizacao

**Regras de negocio aplicaveis:**
- RN-157: Mensagens internas visiveis apenas para staff (sindico, administradora)
- RN-158: Thread cronologica de mensagens
- RN-159: Notificacao automatica para a outra parte

---

### UC-57: ResolveSupportRequest

| Campo | Descricao |
|-------|-----------|
| **Nome** | ResolveSupportRequest |
| **Contexto** | Communication (Tenant Domain) |
| **Ator** | Sindico |

**Pre-condicoes:**
- SupportRequest existente com status `open`
- Ator autenticado como sindico

**Fluxo principal:**
1. Sindico marca solicitacao como resolvida (com mensagem opcional de resolucao)
2. Sistema altera status para `resolved`
3. Sistema registra quem resolveu e quando
4. Sistema emite evento `SupportRequestResolved`
5. Listener notifica condomino sobre resolucao

**Pos-condicoes:**
- SupportRequest com status `resolved`
- Condomino notificado
- Evento emitido: `SupportRequestResolved`

**Cenarios de erro:**
- Solicitacao nao esta aberta: retorna erro
- Solicitacao inexistente: retorna erro 404

**Regras de negocio aplicaveis:**
- RN-160: Apenas sindico/administradora pode resolver
- RN-161: Condomino pode reabrir se nao satisfeito (via nova resposta que reabre)

---

### UC-58: CloseSupportRequest

| Campo | Descricao |
|-------|-----------|
| **Nome** | CloseSupportRequest |
| **Contexto** | Communication (Tenant Domain) |
| **Ator** | Sindico / System |

**Pre-condicoes:**
- SupportRequest existente com status `resolved`
- Se automatico: periodo de inatividade atingido (configuravel)

**Fluxo principal:**
1. **Se manual:** Sindico fecha a solicitacao
2. **Se automatico:** Scheduler identifica solicitacoes resolvidas sem atividade apos periodo configuravel (ex: 7 dias)
3. Sistema altera status para `closed`
4. Sistema emite evento `SupportRequestClosed`

**Pos-condicoes:**
- SupportRequest com status `closed`
- Solicitacao nao pode mais receber mensagens
- Evento emitido: `SupportRequestClosed`

**Cenarios de erro:**
- Solicitacao nao esta resolvida (para fechamento): retorna erro
- Solicitacao ja fechada: retorna erro

**Regras de negocio aplicaveis:**
- RN-162: Fechamento automatico apos periodo de inatividade pos-resolucao
- RN-163: Periodo de auto-fechamento configuravel (padrao 7 dias)
- RN-164: Solicitacao fechada eh imutavel

---

## 10. Transversal — AI

### UC-59: ProcessConversation

| Campo | Descricao |
|-------|-----------|
| **Nome** | ProcessConversation |
| **Contexto** | AI Assistant (Transversal) |
| **Ator** | Condomino / Sindico |

**Pre-condicoes:**
- Tenant ativo com feature de AI habilitada
- Ator autenticado
- Limites de uso de AI nao atingidos (rate limiting)

**Fluxo principal:**
1. Ator envia mensagem em linguagem natural ao assistente de IA
2. Sistema registra mensagem na conversa
3. AI analisa intencao usando contexto da conversa e dados do tenant
4. AI consulta ToolRegistry para identificar acoes possiveis
5. AI determina acao necessaria (ou responde diretamente com informacao)
6. Se acao identificada: ActionOrchestrator prepara execucao
7. **Se mutacao (escrita):** Sistema solicita confirmacao humana do ator antes de executar
8. Ator confirma ou rejeita acao proposta
9. Se confirmada: Sistema executa Use Case correspondente via ActionOrchestrator
10. Sistema retorna resultado ao ator em linguagem natural
11. Sistema registra uso (AIUsageLog, AIActionLog)

**Pos-condicoes:**
- Mensagem processada e resposta gerada
- Se acao executada: estado do sistema alterado conforme Use Case
- Logs de uso registrados (AIUsageLog, AIActionLog)

**Cenarios de erro:**
- Feature de AI desabilitada para o tenant: retorna erro
- Rate limit atingido: retorna erro com tempo de espera
- IA nao identifica intencao: responde pedindo mais informacoes
- Acao nao autorizada para o role do ator: retorna erro de autorizacao
- Ator rejeita acao proposta: nenhuma alteracao, conversa continua

**Regras de negocio aplicaveis:**
- RN-165: Mutacoes sempre requerem confirmacao humana (human-in-the-loop)
- RN-166: IA respeita autorizacoes do ator (nao pode executar acoes alem do role)
- RN-167: Uso registrado para auditoria e controle de custos
- RN-168: Dados de conversa isolados por tenant

---

### UC-60: GenerateEmbedding

| Campo | Descricao |
|-------|-----------|
| **Nome** | GenerateEmbedding |
| **Contexto** | AI Assistant (Transversal) |
| **Ator** | System |

**Pre-condicoes:**
- Conteudo indexavel criado ou atualizado (aviso, regra do condominio, FAQ)
- Feature de AI habilitada para o tenant

**Fluxo principal:**
1. Listener detecta evento de criacao/atualizacao de conteudo indexavel
2. Sistema extrai texto do conteudo
3. Sistema gera embedding vetorial via API de embedding (ex: OpenAI)
4. Sistema armazena embedding no pgvector, vinculado ao tenant e ao conteudo original
5. Embedding disponivel para busca semantica

**Pos-condicoes:**
- Embedding gerado e armazenado (AIEmbedding)
- Conteudo disponivel para busca semantica

**Cenarios de erro:**
- API de embedding indisponivel: retry automatico com backoff
- Conteudo vazio ou muito curto: embedding nao gerado
- Feature de AI desabilitada: embedding nao gerado

**Regras de negocio aplicaveis:**
- RN-169: Embeddings isolados por tenant (nunca compartilhados)
- RN-170: Geracao assincrona via fila
- RN-171: Re-geracao automatica quando conteudo eh atualizado

---

### UC-61: SemanticSearch

| Campo | Descricao |
|-------|-----------|
| **Nome** | SemanticSearch |
| **Contexto** | AI Assistant (Transversal) |
| **Ator** | AI / System |

**Pre-condicoes:**
- Embeddings existentes para o tenant
- Query de busca informada

**Fluxo principal:**
1. AI ou sistema interno submete query de busca semantica
2. Sistema gera embedding da query
3. Sistema busca embeddings mais similares no pgvector (cosine similarity)
4. Sistema filtra resultados por tenant (isolamento)
5. Sistema retorna conteudos mais relevantes com score de similaridade

**Pos-condicoes:**
- Resultados de busca retornados ordenados por relevancia
- Nenhuma alteracao de estado

**Cenarios de erro:**
- Nenhum embedding encontrado: retorna lista vazia
- API de embedding indisponivel para gerar embedding da query: retorna erro

**Regras de negocio aplicaveis:**
- RN-172: Busca sempre filtrada por tenant (isolamento obrigatorio)
- RN-173: Resultados limitados por threshold de similaridade configuravel
- RN-174: Busca eh read-only

---

## 11. Matriz de Casos de Uso por Ator

### Legenda

- **PA** = PlatformAdmin
- **SY** = System (automatico/cron)
- **SI** = Sindico
- **AD** = Administradora
- **CO** = Condomino
- **FU** = Funcionario/Portaria
- **RE** = Resident (via convite)
- **AI** = AI Assistant
- **VI** = Visitante (nao autenticado)

| # | Caso de Uso | PA | SY | SI | AD | CO | FU | RE | AI | VI |
|---|-------------|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|:--:|
| **Tenant Management** | | | | | | | | | | |
| 01 | ProvisionTenant | X | | | | | | | | |
| 02 | SuspendTenant | X | X | | | | | | | |
| 03 | CancelTenant | X | | X | | | | | | |
| 04 | ReactivateTenant | X | | | | | | | | |
| 67 | RegisterTenant (Self-Service) | | | | | | | | | X |
| 68 | VerifyRegistration | | | | | | | | | X |
| **Billing** | | | | | | | | | | |
| 05 | CreateSubscription | | X | | | | | | | |
| 06 | RenewSubscription | | X | | | | | | | |
| 07 | CancelSubscription | X | | X | | | | | | |
| 08 | ChangeSubscriptionPlan | | | X | | | | | | |
| 09 | GenerateInvoice | | X | | | | | | | |
| 10 | ProcessPayment | | X | | | | | | | |
| 11 | ProcessDunning | | X | | | | | | | |
| 12 | IssueRefund | X | | | | | | | | |
| **Platform Admin** | | | | | | | | | | |
| 13 | ManagePlans | X | | | | | | | | |
| 14 | ManageFeatureFlags | X | | | | | | | | |
| 15 | ViewTenantDashboard | X | | | | | | | | |
| **Units & Residents** | | | | | | | | | | |
| 16 | CreateBlock | | | X | X | | | | | |
| 17 | CreateUnit | | | X | X | | | | | |
| 18 | DeactivateUnit | | | X | X | | | | | |
| 19 | InviteResident | | | X | X | | | | | |
| 20 | ActivateResident | | | | | | | X | | |
| 21 | DeactivateResident | | | X | X | | | | | |
| 22 | TransferUnit | | | X | X | | | | | |
| **Spaces Management** | | | | | | | | | | |
| 23 | CreateSpace | | | X | X | | | | | |
| 24 | UpdateSpace | | | X | X | | | | | |
| 25 | SetSpaceAvailability | | | X | X | | | | | |
| 26 | BlockSpace | | | X | X | | | | | |
| 27 | DeactivateSpace | | | X | X | | | | | |
| 28 | ConfigureSpaceRules | | | X | X | | | | | |
| **Reservations (CORE)** | | | | | | | | | | |
| 29 | CreateReservation | | | | | X | | | | |
| 30 | ApproveReservation | | | X | X | | | | | |
| 31 | RejectReservation | | | X | X | | | | | |
| 32 | CancelReservation | | | X | | X | | | | |
| 33 | CompleteReservation | | X | X | | | | | | |
| 34 | MarkAsNoShow | | | X | | | X | | | |
| 35 | ListAvailableSlots | | | | | X | | | | |
| **Governance** | | | | | | | | | | |
| 36 | RegisterViolation | | X | X | X | | | | | |
| 37 | ConfirmViolation | | | X | | | | | | |
| 38 | DismissViolation | | | X | | | | | | |
| 39 | ContestViolation | | | | | X | | | | |
| 40 | ReviewContestation | | | X | | | | | | |
| 41 | ApplyPenalty | | X | | | | | | | |
| 42 | RevokePenalty | | | X | | | | | | |
| 43 | ConfigurePenaltyPolicy | | | X | X | | | | | |
| 62 | UploadCondominiumDocument | | | X | X | | | | | |
| 63 | ParseDocumentSections | | X | X | X | | | | | |
| 64 | ActivateCondominiumDocument | | | X | | | | | | |
| 65 | SearchDocumentSections | | | X | X | X | | | X | |
| 66 | ViewDocumentVersionHistory | | | X | X | | | | | |
| **People Control** | | | | | | | | | | |
| 44 | RegisterGuest | | | | | X | | | | |
| 45 | CheckInGuest | | | | | | X | | | |
| 46 | CheckOutGuest | | | | | | X | | | |
| 47 | DenyGuestAccess | | | | | | X | | | |
| 48 | RegisterServiceProvider | | | X | X | | | | | |
| 49 | ScheduleServiceProviderVisit | | | X | | X | | | | |
| 50 | CheckInServiceProvider | | | | | | X | | | |
| 51 | CheckOutServiceProvider | | | | | | X | | | |
| **Communication** | | | | | | | | | | |
| 52 | PublishAnnouncement | | | X | X | | | | | |
| 53 | MarkAnnouncementAsRead | | | | | X | | | | |
| 54 | ArchiveAnnouncement | | | X | X | | | | | |
| 55 | CreateSupportRequest | | | | | X | | | | |
| 56 | ReplySupportRequest | | | X | | X | | | | |
| 57 | ResolveSupportRequest | | | X | | | | | | |
| 58 | CloseSupportRequest | | X | X | | | | | | |
| **AI** | | | | | | | | | | |
| 59 | ProcessConversation | | | X | | X | | | | |
| 60 | GenerateEmbedding | | X | | | | | | | |
| 61 | SemanticSearch | | X | | | | | | X | |

### Resumo por Ator

| Ator | Total de Casos de Uso |
|------|:---------------------:|
| PlatformAdmin (PA) | 10 |
| System (SY) | 14 |
| Sindico (SI) | 30 |
| Administradora (AD) | 18 |
| Condomino (CO) | 12 |
| Funcionario (FU) | 6 |
| Resident (RE) | 1 |
| AI (AI) | 1 |
| Visitante (VI) | 2 |

---

## 12. Fluxos Criticos

### 12.1 Fluxo de Reserva (Criacao ate Conclusao/No-Show)

```
                         Condomino solicita reserva
                                   |
                                   v
                    +-----------------------------+
                    |     CreateReservation        |
                    |  (UC-29 -- 10 validacoes)    |
                    +-----------------------------+
                         |                   |
                   [requires_approval]  [auto-confirm]
                         |                   |
                         v                   v
              +------------------+  +------------------+
              | pending_approval |  |    confirmed     |
              +------------------+  +------------------+
                    |       |                |
                    v       v                |
            +--------+  +--------+           |
            |Approve |  |Reject  |           |
            |(UC-30) |  |(UC-31) |           |
            +--------+  +--------+           |
                |            |               |
                v            v               |
          +-----------+  +----------+        |
          | confirmed |  | rejected |        |
          +-----------+  +----------+        |
                |                            |
                +----------------------------+
                |               |            |
                v               v            v
         +-----------+  +------------+  +----------+
         |  Cancel   |  |  Complete  |  | No-Show  |
         |  (UC-32)  |  |  (UC-33)  |  | (UC-34)  |
         +-----------+  +------------+  +----------+
              |               |              |
              v               v              v
         +-----------+  +------------+  +----------+
         | canceled  |  | completed  |  | no_show  |
         +-----------+  +------------+  +----------+
              |                              |
              v                              v
     [late_cancellation?]          [automatico: RegisterViolation]
              |                              |
              v                              v
     RegisterViolation              ViolationRegistered
         (UC-36)                    + ApplyPenalty (UC-41)
```

**Observacoes do fluxo:**
- CreateReservation executa 10 validacoes em sequencia (atomicamente)
- ConflictChecker usa lock pessimista para prevenir race conditions
- ApproveReservation re-valida conflitos para cobrir intervalo entre criacao e aprovacao
- No-show e cancelamento tardio disparam governanca automaticamente

---

### 12.2 Fluxo de Governanca (Violacao -> Penalidade -> Contestacao)

```
     [Evento: NoShow/LateCancellation]        [Sindico registra manualmente]
                    |                                      |
                    v                                      v
          +-------------------+                 +-------------------+
          | RegisterViolation |                 | RegisterViolation |
          |  (UC-36) AUTO     |                 |  (UC-36) MANUAL   |
          +-------------------+                 +-------------------+
                    |                                      |
                    v                                      v
          status: confirmed                       status: pending
                    |                                      |
                    |                            +---------+---------+
                    |                            |                   |
                    |                            v                   v
                    |                   +--------------+    +--------------+
                    |                   | ConfirmViol. |    | DismissViol. |
                    |                   |   (UC-37)    |    |   (UC-38)    |
                    |                   +--------------+    +--------------+
                    |                            |                   |
                    +----------------------------+                   v
                    |                                          [encerrado,
                    v                                        sem penalidade]
          +-------------------+
          |   ApplyPenalty    |
          |     (UC-41)       |
          +-------------------+
                    |
                    v
          +-------------------+
          | PenaltyPolicy     |
          | avalia historico   |
          +-------------------+
                    |
        +-----------+-----------+-----------+
        |           |           |           |
        v           v           v           v
    warning    formal_w.   temp_block   limit_red.
   (1a vez)   (2a vez)    (3a vez)     (4a+ vez)
                    |
                    v
          +-------------------+
          | Condomino recebe  |
          | notificacao       |
          +-------------------+
                    |
          +---------+---------+
          |                   |
          v                   v
   [aceita penalidade]  [contesta]
                              |
                              v
                    +-------------------+
                    | ContestViolation  |
                    |     (UC-39)       |
                    +-------------------+
                              |
                              v
                    +-------------------+
                    | ReviewContestation|
                    |     (UC-40)       |
                    +-------------------+
                         |          |
                         v          v
                    [aceita]    [rejeita]
                         |          |
                         v          v
                  RevokePenalty   Penalidade
                    (UC-42)      mantida
                         |
                         v
                  Efeitos removidos
                  (pode reservar)
```

**Observacoes do fluxo:**
- Violacoes automaticas (no_show, late_cancellation) sao confirmadas imediatamente
- Violacoes manuais passam por revisao antes de gerar penalidade
- Escalonamento progressivo: advertencia -> advertencia formal -> bloqueio temporario -> reducao de limite
- Contestacao pode revogar penalidade se aceita pelo sindico

---

### 12.3 Fluxo de Registro Self-Service (Cadastro ate Provisioning)

```
                    Visitante submete formulario de registro
                                   |
                                   v
                    +-----------------------------+
                    |      RegisterTenant          |
                    |  (UC-67 -- 5 validacoes)     |
                    +-----------------------------+
                                   |
                                   v
                    +-----------------------------+
                    | PendingRegistration criado   |
                    | Email de verificacao enviado |
                    +-----------------------------+
                                   |
                          [24h para verificar]
                                   |
                     +-------------+-------------+
                     |                           |
                     v                           v
            [Clica no link]             [Nao verifica]
                     |                           |
                     v                           v
          +-------------------+        +------------------+
          | VerifyRegistration|        | Registro expira  |
          |     (UC-68)       |        | (cleanup job)    |
          +-------------------+        +------------------+
                     |
                     v
          +-------------------+
          | Tenant criado     |
          | status:provisioning|
          +-------------------+
                     |
                     v
          +-------------------+
          | ProvisionTenantJob |
          | (UC-01 parcial)   |
          +-------------------+
                     |
                     v
          +-------------------+
          | Tenant ativo      |
          | Admin (sindico)   |
          | criado            |
          +-------------------+
```

**Observacoes do fluxo:**
- RegisterTenant (UC-67) valida slug em 2 tabelas (tenants + pending_registrations)
- Token de verificacao tem TTL de 24 horas
- VerifyRegistration (UC-68) re-valida unicidade do slug para proteger contra race conditions
- Provisionamento reutiliza a infraestrutura existente (ProvisionTenantJob)
- Registros nao verificados sao limpos periodicamente

---

### 12.4 Fluxo de Onboarding (Provisionamento -> Unidade -> Morador -> Ativacao)

```
     PlatformAdmin cria tenant
               |
               v
     +-------------------+
     | ProvisionTenant   |
     |     (UC-01)       |
     +-------------------+
               |
               v
     +-------------------+      +-------------------+
     | ProvisionTenantJob|----->| CreateSubscription|
     | (async)           |      |     (UC-05)       |
     +-------------------+      +-------------------+
         |    |    |    |
         v    v    v    v
      create  run  seed  create
       DB   migr. data  admin
               |
               v
     TenantProvisioned
     + admin user (invited)
               |
               v
     +-------------------+
     | Admin recebe      |
     | email de convite  |
     +-------------------+
               |
               v
     +-------------------+
     | ActivateResident  |
     |     (UC-20)       |
     | (define senha)    |
     +-------------------+
               |
               v
     Admin (Sindico) ativo
               |
     +---------+---------+---------+
     |         |         |         |
     v         v         v         v
  CreateBlock CreateUnit CreateSpace ConfigureSpaceRules
   (UC-16)    (UC-17)    (UC-23)     (UC-28)
     |         |
     |    +----+
     |    |
     v    v
  Estrutura do condominio pronta
               |
               v
     +-------------------+
     | InviteResident    |
     |     (UC-19)       |
     | (para cada morador|
     |  de cada unidade) |
     +-------------------+
               |
               v
     +-------------------+
     | Morador recebe    |
     | email de convite  |
     +-------------------+
               |
               v
     +-------------------+
     | ActivateResident  |
     |     (UC-20)       |
     | (define senha)    |
     +-------------------+
               |
               v
     +-------------------+
     | SetSpaceAvail.    |
     |     (UC-25)       |
     +-------------------+
               |
               v
     +-------------------------------------------+
     | Condominio operacional!                    |
     | Moradores podem:                           |
     |   - Consultar slots (UC-35)                |
     |   - Criar reservas (UC-29)                 |
     |   - Registrar convidados (UC-44)           |
     |   - Abrir solicitacoes (UC-55)             |
     |   - Conversar com IA (UC-59)               |
     +-------------------------------------------+
```

**Observacoes do fluxo:**
- Provisionamento eh totalmente assincrono (ProvisionTenantJob)
- Subscription eh criada automaticamente via listener de TenantProvisioned
- Admin inicial recebe convite e ativa conta antes de configurar o condominio
- Ordem recomendada: blocos -> unidades -> espacos -> regras -> disponibilidade -> convites
- Condominio esta operacional quando tem pelo menos: 1 espaco configurado + 1 morador ativo

---

## Status

Documento **ATIVO**. Define o catalogo completo de casos de uso do sistema.

Novos casos de uso devem ser adicionados a este documento seguindo o mesmo formato antes de serem implementados.

**Documentos relacionados:**
- `bounded-contexts.md` -- Fronteiras dos contextos
- `ubiquitous-language.md` -- Glossario de termos
- `domain-model.md` -- Modelo de dominio completo
