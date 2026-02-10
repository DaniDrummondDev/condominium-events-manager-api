# Testing Strategy — Estratégia de Testes
## FASE 7 — Qualidade & Governança Técnica  
**Condominium Events Manager API**

---

## 1. Objetivo

Definir a estratégia oficial de **testes automatizados** do sistema, garantindo que:

- A arquitetura definida seja continuamente validada
- Regressões sejam detectadas precocemente
- Regras de negócio permaneçam corretas
- Isolamento por tenant nunca seja violado
- Segurança, billing e IA não sejam quebrados por evolução do código
- Testes reflitam o comportamento real do sistema
- O ambiente de testes seja rápido, previsível e reprodutível

Todos os testes **utilizam banco de dados real para testes**, baseado em **SQLite**, evitando falsos positivos causados por mocks excessivos.

---

## 2. Princípios Não Negociáveis

- Testes devem usar banco de dados
- SQLite é o banco oficial de testes
- Testes não dependem de serviços externos reais
- Testes devem ser determinísticos
- Testes não podem compartilhar estado entre si
- Testes não podem vazar contexto entre tenants
- Falha de teste bloqueia evolução
- Código sem teste é considerado incompleto

---

## 3. Estratégia de Banco de Dados para Testes

### 3.1 Banco de Dados de Testes

- SQLite é usado como banco de dados de testes
- Cada suíte de testes inicia com banco limpo
- Migrations reais são executadas
- Seeds de teste são controlados e mínimos
- Nenhum teste depende de dados persistidos previamente

### 3.2 Justificativa

- Valida mapeamentos reais de ORM
- Detecta problemas de schema cedo
- Evita mocks frágeis de repositórios
- Acelera execução comparado a PostgreSQL
- Mantém testes próximos do comportamento real

SQLite é **instrumento de validação**, não substituto do banco de produção.

---

## 4. Pirâmide de Testes Adotada

A estratégia segue uma pirâmide adaptada para **DDD + banco real**:

1. Testes de Domínio (com persistência quando necessário)
2. Testes de Application Layer com SQLite
3. Testes de Integração Controlada
4. Testes de Contrato de API
5. Testes End-to-End mínimos

A maior parte da cobertura deve estar nos níveis **1 e 2**.

---

## 5. Tipos de Testes

### 5.1 Testes de Domínio

Escopo:
- Entidades
- Value Objects
- Agregados
- Regras de negócio

Características:
- Podem persistir dados no SQLite
- Sem chamadas externas
- Alta velocidade
- Estado totalmente controlado

Objetivo:
- Garantir invariantes
- Validar transições
- Detectar regressões conceituais

---

### 5.2 Testes de Application Layer

Escopo:
- Use Cases
- Application Services
- Orquestrações

Características:
- SQLite obrigatório
- Repositórios reais
- Mocks apenas para:
  - Provedores externos
  - Serviços de terceiros
- Validação de idempotência e estados

Objetivo:
- Garantir fluxo correto
- Garantir persistência correta
- Validar efeitos colaterais controlados

---

### 5.3 Testes de Integração Controlada

Escopo:
- Repositórios
- Adapters
- Mappers
- Event handlers
- Jobs

Características:
- SQLite
- Ambiente isolado
- Execução mais lenta
- Menor volume

Objetivo:
- Validar integração técnica
- Garantir compatibilidade entre camadas

---

### 5.4 Testes de Contrato (API)

Escopo:
- Endpoints
- Payloads
- Headers
- Autenticação
- Autorização

Características:
- API-first
- Banco SQLite
- Versionados
- Automatizados

Objetivo:
- Garantir estabilidade da API
- Evitar breaking changes
- Validar regras de acesso

Integração direta com:
- api-contract-strategy

---

### 5.5 Testes End-to-End

Escopo:
- Fluxos críticos completos

Características:
- SQLite
- Poucos
- Lentos
- Executados com parcimônia

Objetivo:
- Validar comportamento sistêmico
- Detectar falhas de integração geral

---

## 6. Testes e Multi-Tenant

Regras obrigatórias:

- Todo teste declara explicitamente o tenant
- Múltiplos tenants devem ser testados
- Queries devem ser escopadas
- Isolamento deve ser validado por teste
- Falha de isolamento é falha crítica

Isolamento por tenant é **requisito verificável**.

---

## 7. Testes de Segurança

Cobertura mínima:

- Autenticação
- Autorização
- Escopo de acesso
- Proteção contra acesso cross-tenant
- Validação de input malicioso

SQLite não reduz exigência de segurança.

Integração direta com:
- security-architecture
- api-security
- access-control

---

## 8. Testes de Billing e Assinaturas

Cobertura mínima:

- Estados da assinatura
- Transições válidas e inválidas
- Idempotência financeira
- Retry seguro
- Inadimplência e dunning

SQLite deve validar:
- Persistência correta
- Consistência de estados

Integração direta com:
- billing-subscription
- subscription-lifecycle
- idempotency-strategy

---

## 9. Testes de IA

Regras específicas:

- Testes validam fluxo, não “qualidade da resposta”
- SQLite armazena:
  - Logs
  - Embeddings de teste
  - Auditoria
- IA nunca executa ações nos testes
- Confirmação humana sempre simulada

Integração direta com:
- ai-integration
- ai-observability
- embedding-strategy

---

## 10. Testes de Falhas

Cobertura mínima:

- Timeout
- Retry
- DLQ
- Fallback
- Degradação controlada

Falhas devem ser **simuladas com persistência real**.

Integração direta com:
- failure-handling
- job-architecture
- event-driven-architecture

---

## 11. Testes como Governança Arquitetural

Testes devem falhar quando:

- Camadas são violadas
- Domínio acessa infraestrutura indevida
- Segurança é ignorada
- Billing é burlado
- Isolamento por tenant é quebrado
- Decisões arquiteturais são violadas

Testes são **contratos vivos da arquitetura**.

---

## 12. Anti-Padrões

❌ Testes sem banco de dados  
❌ Mockar repositórios por padrão  
❌ Compartilhar estado entre testes  
❌ Ignorar multi-tenant  
❌ Testes frágeis  
❌ Cobertura sem relevância  
❌ Testes que passam por acaso  

---

## 13. Checklist de Conformidade

- [ ] SQLite configurado para testes
- [ ] Migrations executadas
- [ ] Banco limpo por teste
- [ ] Multi-tenant validado
- [ ] Segurança testada
- [ ] Billing protegido
- [ ] IA governada
- [ ] Falhas simuladas

---

## 14. Status

Documento **OBRIGATÓRIO**.

Sem testes com persistência real, o sistema é considerado **não confiável**.
