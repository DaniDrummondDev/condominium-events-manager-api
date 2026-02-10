# Observability Strategy — Estratégia de Observabilidade Geral
## FASE 6 — Operação & Confiabilidade
**Condominium Events Manager API**

---

## 1. Objetivo

Definir a estratégia oficial de **observabilidade geral** do sistema, cobrindo:

- Monitoramento de saúde da aplicação
- Métricas de performance
- Logging centralizado
- Tracing distribuído
- Alertas operacionais
- Health checks
- SLIs/SLOs

Esta skill complementa `ai-observability.md` (específica para IA) e `audit-logging.md` (específica para auditoria de segurança), cobrindo a **observabilidade técnica e operacional** do sistema como um todo.

---

## 2. Princípios Não Negociáveis

- Toda operação crítica deve ser observável
- Observabilidade é por tenant quando aplicável
- Logs são estruturados (JSON)
- Métricas são coletadas continuamente
- Alertas são acionáveis, não ruidosos
- Dados sensíveis nunca aparecem em logs ou métricas
- Observabilidade não deve degradar performance significativamente

---

## 3. Pilares da Observabilidade

### 3.1 Logs

Logs estruturados em formato JSON.

Campos obrigatórios:

- `timestamp`
- `level` (debug, info, warning, error, critical)
- `message`
- `tenant_id` (quando aplicável)
- `correlation_id`
- `trace_id`
- `context` (módulo, operação)

Regras:

- Logs nunca contêm dados pessoais em claro
- Logs nunca contêm credenciais ou tokens
- Logs são centralizados
- Retenção conforme `data-retention-policy.md`

---

### 3.2 Métricas

Métricas coletadas continuamente.

#### Métricas de Aplicação

- Request rate (por endpoint)
- Response time (p50, p95, p99)
- Error rate (por tipo)
- Queue depth
- Job execution time
- Active connections

#### Métricas de Negócio

- Tenants ativos
- Reservas por período
- Taxa de falha de pagamento
- Uso de IA por tenant

#### Métricas de Infraestrutura

- CPU, memória, disco
- Conexões de banco
- Latência de rede
- Tamanho das filas

---

### 3.3 Tracing Distribuído

Toda requisição deve carregar:

- `trace_id` — identificador único da requisição
- `correlation_id` — identifica operações relacionadas
- `tenant_id`

O trace deve percorrer:

- API Gateway → Controller → Use Case → Repository → External Service

Regras:

- Traces não contêm dados sensíveis
- Traces são amostrados em produção (sampling)
- Traces são completos em ambiente de staging

---

## 4. Health Checks

### 4.1 Tipos

#### Liveness

Verifica se a aplicação está rodando.

- Endpoint: `GET /health/live`
- Frequência: a cada 10s
- Não depende de serviços externos

#### Readiness

Verifica se a aplicação está pronta para receber tráfego.

- Endpoint: `GET /health/ready`
- Verifica: banco de dados, filas, cache
- Frequência: a cada 30s

#### Startup

Verifica se a aplicação inicializou corretamente.

- Endpoint: `GET /health/startup`
- Executado uma vez na inicialização

### 4.2 Regras

- Health checks não requerem autenticação
- Health checks não expõem detalhes internos
- Falha de readiness impede roteamento de tráfego

---

## 5. SLIs e SLOs

### 5.1 SLIs (Service Level Indicators)

Indicadores obrigatórios:

- **Disponibilidade**: % de requests bem-sucedidas
- **Latência**: tempo de resposta p95
- **Taxa de erro**: % de respostas 5xx
- **Throughput**: requests por segundo

### 5.2 SLOs (Service Level Objectives)

Objetivos de referência:

| SLI | SLO |
|-----|-----|
| Disponibilidade | >= 99.5% |
| Latência (p95) | <= 500ms |
| Taxa de erro | <= 0.5% |
| Uptime mensal | >= 99.5% |

SLOs devem ser revisados periodicamente com base em dados reais.

---

## 6. Alertas

### 6.1 Princípios

- Alertas devem ser acionáveis
- Evitar alert fatigue
- Severidade clara (critical, warning, info)
- Runbook associado a cada alerta crítico

### 6.2 Alertas Obrigatórios

- Aplicação down (health check failing)
- Taxa de erro acima do SLO
- Latência acima do SLO
- Fila crescendo sem consumo
- Falha de conexão com banco
- Certificado TLS próximo da expiração
- Disco próximo da capacidade
- Falhas de pagamento em massa
- Tenant provisioning failing

---

## 7. Observabilidade por Tenant

Regras obrigatórias:

- Logs, métricas e traces devem conter `tenant_id`
- Dashboards por tenant quando necessário (suporte)
- Nenhum dado de um tenant vaza para outro
- Métricas agregadas disponíveis para plataforma admin

---

## 8. Logging Centralizado

### 8.1 Estratégia

- Logs são enviados para sistema centralizado
- Busca e filtro por:
  - tenant_id
  - correlation_id
  - trace_id
  - level
  - timestamp
- Retenção conforme política

### 8.2 Níveis de Log

| Nível | Uso |
|-------|-----|
| DEBUG | Desenvolvimento apenas |
| INFO | Operações normais |
| WARNING | Situações inesperadas não críticas |
| ERROR | Falhas que requerem atenção |
| CRITICAL | Falhas que requerem ação imediata |

Produção deve usar **INFO** como nível mínimo.

---

## 9. Segurança

- Logs não contêm PII
- Métricas não expõem dados sensíveis
- Acesso a dashboards é controlado
- Dados de observabilidade seguem política de retenção
- Traces não contêm payloads completos

---

## 10. Integração com Outras Skills

Esta skill integra-se com:

- `ai-observability.md` — observabilidade específica de IA
- `audit-logging.md` — auditoria de segurança
- `job-architecture.md` — observabilidade de jobs
- `event-driven-architecture.md` — observabilidade de eventos
- `failure-handling.md` — detecção de falhas
- `data-retention-policy.md` — retenção de logs

---

## 11. Anti-Padrões

- Logs não estruturados
- Métricas sem tenant context
- Alertas não acionáveis
- Health checks que dependem de serviços externos no liveness
- Dados sensíveis em logs
- Observabilidade como afterthought
- Alert fatigue por excesso de alertas

---

## 12. Checklist de Conformidade

- [ ] Logs estruturados em JSON
- [ ] Métricas de aplicação coletadas
- [ ] Tracing distribuído ativo
- [ ] Health checks implementados
- [ ] SLIs/SLOs definidos
- [ ] Alertas configurados
- [ ] Observabilidade por tenant
- [ ] Segurança de logs garantida

---

## 13. Status

Documento **OBRIGATÓRIO** para operação em produção.

Sem observabilidade, o sistema é operado às cegas.
