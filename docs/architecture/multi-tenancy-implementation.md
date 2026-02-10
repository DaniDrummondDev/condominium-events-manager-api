# Multi-Tenancy Implementation — Implementação de Multi-Tenancy

## 1. Visão Geral

O sistema utiliza **isolamento por database/schema** no PostgreSQL, onde cada tenant possui seu próprio espaço de dados completamente separado.

---

## 2. Arquitetura de Bancos de Dados

### 2.1 Banco da Plataforma (Global)

Um único banco que contém:

- Tenants e configurações
- Planos e versões
- Assinaturas e faturas
- Pagamentos
- Feature flags e overrides
- Usuários da plataforma
- Audit logs da plataforma
- Dunning policies
- Gateway events

**Conexão:** Configurada estaticamente no `.env` principal.

### 2.2 Banco do Tenant (Isolado)

Um banco (ou schema) por tenant, contendo:

- Blocos e unidades
- Moradores (TenantUsers, Residents)
- Espaços e disponibilidade
- Reservas
- Convidados e prestadores
- Governança (regras, infrações, penalidades)
- Comunicação (avisos, solicitações)
- Audit logs do tenant
- Embeddings de IA
- AI usage/action logs

**Conexão:** Resolvida dinamicamente por requisição.

---

## 3. Resolução de Tenant

### 3.1 Fluxo

```
Request HTTP
    │
    ▼
[Middleware: ResolvesTenant]
    │
    ├── Extrai tenant_id do JWT token
    │
    ├── Busca tenant no banco da plataforma
    │
    ├── Verifica:
    │   ├── Tenant existe?
    │   ├── Tenant está ativo?
    │   └── Assinatura válida?
    │
    ├── Configura conexão para o banco do tenant
    │
    └── Injeta TenantContext no container
    │
    ▼
[Controller → Use Case → Repository (usando conexão do tenant)]
```

### 3.2 TenantContext

Objeto disponível em toda a aplicação durante a request:

```
TenantContext
├── tenant_id
├── tenant_slug
├── tenant_name
├── tenant_type (horizontal/vertical/mixed)
├── tenant_status
├── subscription_status
├── database_name / schema_name
└── resolved_at
```

### 3.3 Regras

- Tenant é resolvido **antes** de qualquer acesso a dados
- Se o tenant não for resolvido → 403 Forbidden
- Se o tenant não estiver ativo → 403 com mensagem específica
- TenantContext é imutável durante a request
- Jobs e eventos recebem `tenant_id` no payload

---

## 4. Troca de Conexão

### 4.1 Estratégia

- Laravel suporta múltiplas conexões de banco
- A conexão `tenant` é configurada dinamicamente via middleware
- Todas as queries de domínio usam a conexão `tenant`
- Queries de plataforma usam a conexão `platform`

### 4.2 Configuração

```
config/database.php
├── connections
│   ├── platform   (conexão fixa, banco global)
│   └── tenant     (conexão dinâmica, banco do tenant)
```

### 4.3 Eloquent Models

- Models de plataforma: `protected $connection = 'platform';`
- Models de tenant: `protected $connection = 'tenant';` (padrão dinâmico)

---

## 5. Provisionamento de Tenant

### 5.1 Fluxo (Job Assíncrono)

```
1. CreateTenant (API da plataforma)
   └── Cria registro na tabela `tenants` (status: provisioning)

2. ProvisionTenantJob (assíncrono)
   ├── Cria database/schema no PostgreSQL
   ├── Executa todas as migrations do tenant
   ├── Executa seeds iniciais (config padrão)
   ├── Cria usuário admin do tenant (síndico)
   └── Atualiza status para `active`

3. Envia notificação de boas-vindas
```

### 5.2 Nomenclatura de Banco

Convenção:

- Database: `tenant_{tenant_slug}` (ex: `tenant_condominio_sol`)
- Schema: `tenant_{tenant_slug}` (se schema por tenant)

### 5.3 Rollback de Provisionamento

Se qualquer etapa falhar:

- Tenant permanece em `provisioning`
- Erro é registrado em audit log
- Job pode ser reprocessado (idempotente)
- Admin da plataforma é notificado

---

## 6. Migrations

### 6.1 Duas Categorias

| Tipo | Onde roda | Quando |
|------|-----------|--------|
| Platform migrations | Banco global | No deploy |
| Tenant migrations | Cada banco de tenant | No deploy + no provisioning |

### 6.2 Deploy

```
1. Executar platform migrations
2. Para cada tenant ativo:
   ├── Conectar ao banco do tenant
   ├── Executar migrations pendentes
   └── Registrar sucesso/falha
3. Tenants com falha → marcados para reprocessamento
```

### 6.3 Paralelismo

- Migrations de tenants podem rodar em paralelo (controlado)
- Falha em um tenant não bloqueia outros
- Observabilidade por tenant

---

## 7. Jobs e Eventos em Contexto Multi-Tenant

### 7.1 Payload Obrigatório

Todo job e evento deve conter:

```json
{
  "tenant_id": "uuid",
  "correlation_id": "uuid",
  "trace_id": "uuid",
  ...
}
```

### 7.2 Resolução no Worker

```
Job recebido
    │
    ├── Extrai tenant_id do payload
    ├── Resolve conexão do tenant
    ├── Configura TenantContext
    ├── Executa lógica
    └── Limpa contexto ao finalizar
```

### 7.3 Regra

- Job sem `tenant_id` deve falhar imediatamente
- Contexto de tenant é limpo após cada job
- Nenhum estado compartilhado entre execuções

---

## 8. Queries e Segurança

### 8.1 Regras

- Toda query de domínio usa a conexão `tenant` (já isolada)
- Queries de plataforma usam a conexão `platform`
- Nenhuma query cruza conexões
- Repositórios nunca recebem `tenant_id` como parâmetro de filtro (a conexão já isola)

### 8.2 Verificação Adicional

Mesmo com isolamento por banco, o sistema deve:

- Validar que o token pertence ao tenant da conexão ativa
- Impedir acesso via manipulação de IDs
- Logar tentativas suspeitas

---

## 9. Backup e Restauração

### 9.1 Estratégia

- Backup independente por tenant
- Restauração sem afetar outros tenants
- Compliance com LGPD (retenção e exclusão)

### 9.2 Exclusão de Tenant

```
1. Tenant marcado para exclusão (pending_deletion)
2. Período de retenção (configurável)
3. Anonimização de dados pessoais
4. Drop do database/schema
5. Registro em audit log da plataforma
```

---

## 10. Performance

### 10.1 Connection Pooling

- Pool de conexões para o banco da plataforma
- Conexões dinâmicas para tenants (com pool quando possível)
- Timeout configurável por conexão

### 10.2 Cache

- Cache por tenant (chave: `tenant:{id}:...`)
- Cache de feature flags por tenant (TTL curto)
- Invalidação de cache ao mudar plano ou overrides

---

## 11. Checklist de Validação

- [ ] Tenant resolvido antes de qualquer acesso a dados
- [ ] Conexão trocada dinamicamente por request
- [ ] Jobs e eventos carregam tenant_id
- [ ] Migrations separadas (platform vs tenant)
- [ ] Provisionamento automatizado e idempotente
- [ ] Backup independente por tenant
- [ ] Cache isolado por tenant
- [ ] Nenhuma query cross-tenant possível

---

## 12. Status

Documento **ATIVO**. Define como o multi-tenancy funciona na prática.
