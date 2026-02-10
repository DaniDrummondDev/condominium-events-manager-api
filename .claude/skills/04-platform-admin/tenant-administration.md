# tenant-administration.md — Administração de Tenants na Plataforma

## Objetivo

Definir as regras e a arquitetura para **administração de tenants pelo dono da plataforma (SaaS owner)**, garantindo:

* Controle operacional sobre qualquer tenant
* Ações seguras e auditáveis
* Isolamento entre domínios
* Governança centralizada sem violar limites de segurança

Esta skill define **o que o platform admin pode fazer** e **como essas ações devem ser tratadas no sistema**.

---

## Princípios Arquiteturais

1. O **platform admin atua no nível da plataforma**, não no domínio do tenant
2. Toda ação sobre um tenant deve ser:

   * Auditada
   * Reversível quando possível
   * Segura e rastreável
3. Nenhuma ação administrativa pode violar:

   * Regras de billing
   * Regras de segurança
   * Regras de compliance
4. Ações destrutivas devem exigir **confirmação explícita**

---

## Tipos de Administração de Tenant

### 1. Administração Operacional

Ações comuns de suporte e operação.

Exemplos:

* Visualizar dados do tenant
* Alterar plano manualmente
* Criar override de feature
* Reativar tenant suspenso
* Forçar sincronização de billing

---

### 2. Administração de Segurança

Ações relacionadas à proteção da plataforma.

Exemplos:

* Suspender tenant por violação
* Bloquear acesso temporário
* Forçar logout global
* Resetar tokens e sessões

---

### 3. Administração de Ciclo de Vida

Ações estruturais no tenant.

Exemplos:

* Ativar tenant
* Suspender tenant
* Marcar tenant para exclusão
* Executar exclusão definitiva

---

## Estados Administrativos do Tenant

O tenant possui um **estado administrativo**, separado do estado de billing.

Estados:

* `active`
* `suspended`
* `blocked`
* `pending_deletion`
* `deleted`

### Definições

#### active

* Tenant operando normalmente

#### suspended

* Acesso temporariamente suspenso
* Geralmente por:

  * Inadimplência
  * Manutenção
  * Solicitação do cliente

#### blocked

* Bloqueio por violação de segurança ou termos
* Requer ação manual do platform admin

#### pending_deletion

* Tenant marcado para exclusão
* Dentro do período de retenção

#### deleted

* Tenant removido logicamente
* Dados anonimizados ou apagados conforme LGPD

---

## Tabela: tenant_admin_actions

Registro de todas as ações administrativas.

Campos:

* id
* tenant_id
* action (string)
* reason (string)
* performed_by (platform_admin_id)
* metadata (json)
* created_at

Exemplos de action:

* tenant_suspended
* tenant_reactivated
* tenant_blocked
* tenant_marked_for_deletion
* tenant_deleted
* tenant_plan_changed
* tenant_feature_override_created

---

## Ações Administrativas Permitidas

### Suspender tenant

Ação:

```
suspendTenant(tenant_id, reason)
```

Efeitos:

* Estado administrativo: `suspended`
* Bloqueio de login
* Manter dados intactos
* Registrar auditoria

---

### Reativar tenant

Ação:

```
reactivateTenant(tenant_id, reason)
```

Efeitos:

* Estado administrativo: `active`
* Restaurar acesso
* Registrar auditoria

---

### Bloquear tenant por segurança

Ação:

```
blockTenant(tenant_id, reason)
```

Efeitos:

* Estado administrativo: `blocked`
* Bloqueio total de acesso
* Revogação de sessões
* Registrar auditoria crítica

---

### Marcar para exclusão

Ação:

```
markTenantForDeletion(tenant_id, reason)
```

Efeitos:

* Estado: `pending_deletion`
* Início do período de retenção
* Disparar processo de anonimização programada
* Registrar auditoria

---

### Exclusão definitiva

Ação:

```
deleteTenant(tenant_id)
```

Efeitos:

* Estado: `deleted`
* Execução de:

  * Anonimização
  * Exclusão de dados
  * Remoção de recursos
* Registrar auditoria

---

## Separação de Responsabilidades

### Platform Admin

Pode:

* Suspender tenant
* Bloquear tenant
* Alterar plano
* Criar overrides
* Marcar para exclusão

Não pode:

* Alterar dados de domínio diretamente
* Criar reservas
* Editar unidades
* Manipular dados internos do condomínio

---

### Tenant Admin

Pode:

* Gerenciar recursos do condomínio
* Usuários internos
* Reservas
* Configurações

Não pode:

* Alterar plano diretamente
* Acessar painel da plataforma
* Alterar features globais

---

## Integração com Billing

Regras obrigatórias:

1. Suspensão por billing:

   * Deve alterar estado administrativo para `suspended`
2. Reativação após pagamento:

   * Retorna para `active`
3. Bloqueio por segurança:

   * Independe do estado de billing

Billing nunca deve:

* Alterar para `blocked`
* Executar exclusão definitiva

---

## Auditoria Obrigatória

Toda ação administrativa deve:

* Registrar evento em audit log
* Incluir:

  * Quem executou
  * Quando
  * Motivo
  * Tenant afetado
  * Estado anterior e novo estado

Eventos auditáveis:

* tenant_suspended
* tenant_reactivated
* tenant_blocked
* tenant_marked_for_deletion
* tenant_deleted
* tenant_plan_changed

---

## API de Administração (Plataforma)

Endpoints protegidos:

```
GET   /platform/tenants
GET   /platform/tenants/{id}
POST  /platform/tenants/{id}/suspend
POST  /platform/tenants/{id}/reactivate
POST  /platform/tenants/{id}/block
POST  /platform/tenants/{id}/mark-for-deletion
DELETE /platform/tenants/{id}
POST  /platform/tenants/{id}/change-plan
```

Todos os endpoints devem:

* Exigir autenticação de platform admin
* Registrar auditoria
* Validar permissões

---

## Regras de Segurança

1. Ações destrutivas devem exigir:

   * Confirmação explícita
   * Motivo obrigatório
2. Exclusão definitiva deve:

   * Ser assíncrona
   * Seguir política de retenção
3. Bloqueio de tenant deve:

   * Revogar todas as sessões
   * Invalidar tokens

---

## Testes

### Testes unitários

* Transições de estado administrativo
* Regras de bloqueio e reativação

### Testes de integração

* Suspensão impedindo login
* Reativação restaurando acesso
* Exclusão seguindo política de retenção

---

## Anti-patterns Proibidos

❌ Platform admin manipulando dados de domínio
❌ Exclusão de tenant sem período de retenção
❌ Ações administrativas sem auditoria
❌ Billing alterando estados de segurança
