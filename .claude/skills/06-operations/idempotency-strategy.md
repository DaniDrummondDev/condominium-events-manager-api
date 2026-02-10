# Idempotency Strategy — Estratégia de Idempotência
## FASE 6 — Operação & Confiabilidade  
**Condominium Events Manager API**

---

## 1. Objetivo

Definir a estratégia oficial de **idempotência** para todas as operações críticas do sistema, garantindo que:

- Reexecuções não causem efeitos colaterais
- Falhas, retries e duplicidades sejam seguras
- Jobs, eventos e APIs possam ser reprocessados
- A consistência do sistema seja preservada
- Operações sejam confiáveis em ambientes distribuídos

Idempotência é **obrigatória**, não opcional.

---

## 2. Princípios Não Negociáveis

- Toda operação crítica deve ser idempotente
- Reexecução nunca pode gerar efeito duplicado
- Idempotência não é responsabilidade do client
- Idempotência é validada no backend
- Falhas são esperadas
- Retry é parte do fluxo normal
- Idempotência é auditável

---

## 3. Onde a Idempotência é Obrigatória

Idempotência **DEVE** ser aplicada em:

- APIs públicas e privadas
- Jobs assíncronos
- Event handlers
- Integrações com provedores externos
- Webhooks
- Processos de billing
- Operações financeiras
- Provisionamento e desprovisionamento de tenant
- Execuções pós-confirmação humana

---

## 4. Tipos de Idempotência

### 4.1 Idempotência por Chave (Idempotency Key)

Baseada em uma chave única fornecida ou gerada.

**Exemplos de chave:**

- tenant_id + operation_type + external_id  
- tenant_id + payment_id  
- tenant_id + job_type + correlation_id  

**Regras:**

- Chave deve ser única por operação lógica
- Chave é persistida
- Reexecução com a mesma chave retorna o mesmo resultado

---

### 4.2 Idempotência por Estado

Baseada no estado atual da entidade.

**Exemplos:**

- Se a assinatura já está cancelada → não cancelar novamente  
- Se a fatura já foi paga → não processar pagamento  

**Regras:**

- Verificação de estado antes da execução
- Nenhuma transição inválida é permitida

---

### 4.3 Idempotência por Lock Lógico

Baseada em bloqueio lógico temporário.

**Exemplos:**

- Lock por tenant + operação
- Lock por agregado

**Regras:**

- Locks têm timeout
- Locks não podem gerar deadlock
- Locks não substituem idempotência por chave

---

## 5. Posicionamento na Arquitetura

### Camadas

**Presentation Layer**
- Nunca implementa idempotência

**Application Layer**
- Aplica validação idempotente
- Controla chaves e estado
- Decide se executa ou retorna resultado anterior

**Domain Layer**
- Protege invariantes
- Garante transições válidas

**Infrastructure Layer**
- Persiste chaves
- Implementa locks
- Suporta mecanismos técnicos

---

## 6. Modelo de Persistência de Idempotência

### 6.1 Estrutura Base

```sql
CREATE TABLE idempotency_keys (
  id UUID PRIMARY KEY,
  tenant_id UUID NOT NULL,
  key VARCHAR(255) NOT NULL,
  operation_type VARCHAR(100) NOT NULL,
  status VARCHAR(50) NOT NULL,
  response_snapshot JSONB,
  created_at TIMESTAMP NOT NULL,
  expires_at TIMESTAMP
);
```

### 6.2 Regras

- Chave + tenant_id devem ser únicas
- Chaves expiram conforme política
- Resultado pode ser reaproveitado
- Nunca apagar sem política definida

---

## 7. Fluxo Padrão de Execução

1. Recebe requisição, job ou evento
2. Resolve tenant context
3. Gera ou valida idempotency key
4. Verifica existência da chave
5. Se existir:
   - Retorna resultado anterior
6. Se não existir:
   - Executa operação
   - Persiste resultado
   - Marca como concluída
7. Em falha:
   - Registra erro
   - Permite retry seguro

---

## 8. Idempotência em Jobs

**Regras específicas:**

- Todo job deve ser idempotente
- Job deve verificar execução prévia
- Retry não pode gerar efeito colateral
- DLQ não invalida idempotência
- Reprocessamento manual deve respeitar chave

---

## 9. Idempotência em Eventos

Regras específicas:

- Eventos podem ser entregues mais de uma vez
- Handlers devem ser idempotentes
- event_id pode ser usado como chave
- Nunca assumir consumo único

---

## 10. Idempotência em Integrações Externas

- Sempre que possível, usar idempotency key do provider
- Se o provider não suportar:
  - Implementar controle interno
- Nunca confiar apenas no provider
- Falhas de timeout são tratadas como indeterminadas

---

## 11.  Observabilidade e Auditoria

Integração com:

- audit-logging
- job-architecture
- event-driven-architecture

Eventos mínimos:

- idempotency.key.created
- idempotency.key.reused
- idempotency.execution.skipped
- idempotency.execution.completed
- idempotency.execution.failed

---

## 12. Segurança

- Chaves não devem conter dados sensíveis
- Payloads validados
- Acesso restrito
- Isolamento por tenant obrigatório
- Logs não podem expor dados críticos

---

## 13. Anti-Padrões

❌ Confiar no client para idempotência
❌ Reexecutar sem verificação
❌ Usar apenas retry sem idempotência
❌ Locks sem timeout
❌ Apagar chaves manualmente
❌ Misturar tenants na mesma chave

14. Checklist de Conformidade

 - [ ] Operações críticas idempotentes
 - [ ] Chaves persistidas
 - [ ] Reexecução segura
 - [ ] Jobs idempotentes
 - [ ] Handlers idempotentes
 - [ ] Integrações protegidas
 - [ ] Observabilidade ativa
 - [ ] Auditoria habilitada

---

## 15. Status

Documento OBRIGATÓRIO para produção.

Sem idempotência, o sistema é considerado não confiável.