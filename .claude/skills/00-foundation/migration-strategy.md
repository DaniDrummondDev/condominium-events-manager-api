# Migration Strategy — Estratégia de Migrations e Provisionamento de Schema
## FASE 0 — Fundação
**Condominium Events Manager API**

---

## 1. Objetivo

Definir a estratégia oficial de **migrations, provisionamento de banco de dados e gerenciamento de schema** em ambiente multi-tenant, garantindo:

- Provisionamento automatizado por tenant
- Versionamento confiável de schema
- Rollback seguro
- Isolamento absoluto entre tenants
- Compatibilidade com a estratégia de database/schema por tenant
- Execução previsível em todos os ambientes

Migrations são **infraestrutura crítica**, não detalhe técnico adiável.

---

## 2. Princípios Não Negociáveis

- Toda alteração de schema é feita via migration versionada
- Nenhuma alteração manual de schema em produção
- Migrations são idempotentes quando possível
- Provisionamento de tenant é automatizado
- Rollback deve ser sempre possível
- Migrations são testadas antes de aplicação
- Isolamento por tenant é preservado em todas as operações

---

## 3. Estratégia Multi-Tenant

### 3.1 Separação de Contextos

O sistema possui **dois contextos de banco de dados**:

#### Banco da Plataforma (Global)

Contém:

- Tenants
- Planos
- Assinaturas
- Faturas
- Usuários da plataforma
- Feature flags
- Audit logs da plataforma

Migrations do banco da plataforma são executadas **uma vez**, no deploy.

#### Banco do Tenant (Isolado)

Contém:

- Dados de domínio (espaços, reservas, usuários)
- Configurações do condomínio
- Audit logs do tenant
- Embeddings de IA

Cada tenant possui seu próprio **database ou schema**.

### 3.2 Estratégia de Isolamento

Estratégias permitidas:

- Database por tenant (PostgreSQL)
- Schema por tenant (PostgreSQL)

Toda migration de tenant deve:

- Ser aplicável a qualquer tenant individualmente
- Ser aplicável em lote a todos os tenants
- Nunca acessar dados de outro tenant

---

## 4. Provisionamento de Tenant

### 4.1 Fluxo de Criação

1. Registro do tenant na plataforma
2. Criação do database ou schema
3. Execução de todas as migrations do tenant
4. Criação de dados iniciais (seeds controlados)
5. Criação do usuário administrador do tenant
6. Ativação do tenant

### 4.2 Regras

- Provisionamento é executado via **job assíncrono**
- Todo passo é idempotente
- Falha em qualquer etapa deve ser recuperável
- Estado do tenant permanece em `provisioning` até conclusão
- Provisionamento é auditado

### 4.3 Seeds Iniciais

Seeds de tenant incluem apenas:

- Configurações padrão
- Dados estruturais mínimos

Seeds nunca incluem:

- Dados de teste
- Dados de outros tenants

---

## 5. Versionamento de Migrations

### 5.1 Convenção

- Migrations seguem ordem cronológica
- Cada migration possui timestamp único
- Nome descritivo da operação

### 5.2 Tipos de Migration

#### Migrations de Plataforma

- Aplicadas no banco global
- Executadas no deploy
- Não dependem de tenants

#### Migrations de Tenant

- Aplicadas em cada banco de tenant
- Executadas:
  - No provisionamento (todas as migrations)
  - No deploy (migrations pendentes para todos os tenants)

### 5.3 Regras de Versionamento

- Migrations nunca são alteradas após aplicadas
- Correções são feitas via nova migration
- Cada migration é atômica (uma operação lógica)

---

## 6. Execução de Migrations

### 6.1 Deploy (Migrations Pendentes)

Fluxo:

1. Executar migrations da plataforma
2. Identificar todos os tenants ativos
3. Para cada tenant, executar migrations pendentes
4. Registrar sucesso ou falha por tenant

### 6.2 Execução em Lote

Regras:

- Paralelismo controlado
- Falha em um tenant não bloqueia outros
- Tenants com falha são marcados para reprocessamento
- Observabilidade por tenant

### 6.3 Timeout e Limites

- Migrations longas devem ter timeout explícito
- Operações pesadas (índices, alteração de coluna) devem ser planejadas
- Migrations de dados devem ser feitas em batches

---

## 7. Rollback

### 7.1 Estratégia

- Toda migration deve ter método `down` quando viável
- Rollback é executado por tenant individual ou em lote
- Rollback nunca deve causar perda de dados sem aviso

### 7.2 Regras

- Rollback é sempre uma decisão manual
- Rollback é auditado
- Rollback de migration destrutiva pode ser irreversível (documentar)

---

## 8. Testes de Migrations

### 8.1 Regras

- Migrations são testadas em SQLite (ambiente de teste)
- Migrations destrutivas são validadas manualmente antes do deploy
- Pipeline CI deve:
  - Executar todas as migrations
  - Verificar schema resultante
  - Rodar testes de integração após migration

---

## 9. Segurança

- Credenciais de banco nunca em migrations
- Migrations não executam operações de dados sensíveis em claro
- Acesso ao executor de migrations é restrito
- Logs de migration não contêm dados pessoais

---

## 10. Observabilidade

Eventos mínimos:

- `migration.started`
- `migration.completed`
- `migration.failed`
- `migration.rollback`
- `tenant.provisioned`
- `tenant.provisioning_failed`

Métricas:

- Tempo de execução por migration
- Migrations pendentes por tenant
- Taxa de falha de provisionamento

---

## 11. Integração com Outras Skills

Esta skill integra-se diretamente com:

- `saas-architecture.md` — estratégia de isolamento
- `tenant-lifecycle.md` — fluxo de provisionamento
- `job-architecture.md` — execução assíncrona
- `idempotency-strategy.md` — provisionamento idempotente
- `audit-logging.md` — rastreabilidade

---

## 12. Anti-Padrões

- Alteração manual de schema em produção
- Migrations que acessam múltiplos tenants
- Migrations sem método de rollback documentado
- Seeds com dados reais ou de teste
- Provisionamento síncrono
- Migrations que dependem de ordem de execução entre tenants

---

## 13. Checklist de Conformidade

- [ ] Provisionamento automatizado
- [ ] Migrations versionadas
- [ ] Rollback documentado
- [ ] Isolamento por tenant garantido
- [ ] Testes de migration no CI
- [ ] Observabilidade ativa
- [ ] Auditoria habilitada

---

## 14. Status

Documento **OBRIGATÓRIO** para qualquer operação de banco de dados.

Sem estratégia de migrations, o sistema multi-tenant não pode operar de forma confiável.
