# JOB ARCHITECTURE — Arquitetura de Jobs e Processamento Assíncrono
## FASE 6 — Operação & Confiabilidade
## Condominium Events Manager API

## 1. OBJETIVO

Definir a arquitetura oficial de jobs, filas, workers e processamento assíncrono, garantindo:

* Confiabilidade operacional
* Isolamento absoluto por tenant
* Idempotência
* Observabilidade completa
* Segurança
* Recuperação de falhas
* Escalabilidade horizontal

Jobs não contêm regras de negócio. Eles executam orquestrações técnicas ou invocam Use Cases já validados.

---

## 2. PRINCÍPIOS NÃO NEGOCIÁVEIS

* Jobs nunca implementam lógica de domínio
* Jobs apenas orquestram ou chamam Application Services
* Nenhum job é executado sem tenant context
* Jobs devem ser idempotentes
* Falhas são esperadas e tratadas
* Reexecução deve ser sempre segura
* Jobs são totalmente observáveis
* Jobs não acessam infraestrutura externa diretamente sem adapters

---

## 3. TIPOS DE JOBS

### 3.1 Jobs de Sistema

Responsáveis por tarefas técnicas:

* Limpeza de dados
* Expurgo por política de retenção
* Reindexação (ex: embeddings)
* Sincronização com provedores externos
* Monitoramento interno

**Exemplo:**

* CleanupExpiredEmbeddingsJob
* RotateAuditLogsJob

### 3.2 Jobs de Orquestração

Encadeiam processos longos:

* Provisionamento de tenant
* Cancelamento de tenant
* Ciclo de billing
* Regeneração em lote de embeddings
* Execuções pós-confirmação humana

### 3.3 Jobs Reativos (Event-Driven)

Disparados por eventos de domínio ou integração:

* TenantCreated
* SubscriptionCanceled
* InvoicePaid
* AIActionConfirmed

---

### 4. POSICIONAMENTO NA ARQUITETURA

Camadas:

* Presentation Layer → Nunca cria jobs diretamente
* Application Layer → Define contratos e casos de uso → Pode despachar jobs
* Infrastructure Layer → Implementa filas, workers e adapters

Regra:

Controllers → Use Cases → Dispatch Job

---

## 5. CONTEXTO DE TENANT

Todo job DEVE carregar:

* tenant_id
* correlation_id
* trace_id

**Regra absoluta:**

Se o tenant não puder ser resolvido, o job deve falhar imediatamente.

---

## 6. IDEMPOTÊNCIA

### 6.1 Estratégias

* Chaves idempotentes
* Locks lógicos por tenant
* Versionamento de estado
* Verificação de execução prévia

Exemplo:

* payment_id + job_type
* tenant_id + operation_type

---

## 7. RETRY & BACKOFF

### 7.1 Política Padrão

* Retry automático: SIM
* Backoff exponencial
* Número máximo de tentativas definido por tipo de job
* Falha final gera evento de erro auditável

### 7.2 Regras

* Retry nunca pode gerar efeito colateral duplicado
* Jobs não devem assumir sucesso parcial

---

## 8. DEAD LETTER QUEUE (DLQ)

Todos os jobs críticos devem ter DLQ.

Critérios de envio para DLQ:

* Excedeu tentativas máximas
* Erro não recuperável
* Violação de integridade

Jobs em DLQ:

* Nunca são reprocessados automaticamente
* Exigem ação humana
* São totalmente auditáveis

---

## 9. OBSERVABILIDADE DE JOBS

Integração direta com ai-observability.md e audit-logging.md.

Eventos mínimos:

* job.dispatched
* job.started
* job.completed
* job.failed
* job.retried
* job.sent_to_dlq

Métricas:

* Latência por job
* Taxa de falha
* Tempo em fila
* Jobs por tenant
* Custo operacional estimado

---

## 10. SEGURANÇA

* Jobs executam com identidade técnica
* Nunca com identidade de usuário
* Secrets acessados apenas via vault
* Nenhum segredo em payload
* Validação de payload obrigatória

---

## 11. ISOLAMENTO POR TENANT

* Filas podem ser:
  * Compartilhadas com segregação lógica
  * Dedicadas por tenant (casos críticos)
* Nunca misturar contexto de tenants
* Erros nunca vazam informações cross-tenant

---

## 12. DEPENDÊNCIA DE PROVIDERS EXTERNOS

* Sempre via adapters
* Timeout obrigatório
* Circuit breaker
* Fallback definido
* Falha não bloqueia fila inteira

---

## 13. JOBS DE IA (INTERAÇÃO COM FASE 5)

Regras específicas:

* Jobs de IA nunca tomam decisões
* Apenas executam:
  * Geração de embeddings
  * Coleta de métricas
  * Pré-processamento
* Confirmação humana sempre fora do job

---

## 14. ANTI-PADRÕES

**PROIBIDO:**

* Regra de negócio dentro de job
* Job sem tenant_id
* Job sem idempotência
* Retry sem controle
* Job silencioso (sem logs)
* Job chamando API externa direto
* Execução manual sem auditoria

---

## 15. CHECKLIST DE CONFORMIDADE

 * [ ] Jobs são idempotentes
 * [ ] Tenant context obrigatório
 * [ ] Retry controlado
 * [ ] DLQ configurada
 * [ ] Logs estruturados
 * [ ] Métricas coletadas
 * [ ] Segurança aplicada
 * [ ] Providers desacoplados
 * [ ] Observabilidade ativa

---

## 16. STATUS

Documento OBRIGATÓRIO para qualquer processamento assíncrono.

Qualquer job que viole esta arquitetura não pode ir para produção.