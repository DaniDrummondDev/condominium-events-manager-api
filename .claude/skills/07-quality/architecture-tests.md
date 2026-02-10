# Architecture Tests — Testes Arquiteturais
## FASE 7 — Qualidade & Governança Técnica  
**Condominium Events Manager API**

---

## 1. Objetivo

Definir a estratégia oficial de **testes arquiteturais automatizados**, garantindo que:

- Decisões arquiteturais não sejam violadas ao longo do tempo
- O código continue aderente a DDD e Clean Architecture
- O isolamento multi-tenant seja preservado
- Limites entre camadas sejam respeitados
- O crescimento do sistema não introduza acoplamentos indevidos
- A arquitetura seja validada continuamente, não apenas documentada

Testes arquiteturais são **mecanismo de governança**, não testes funcionais.

---

## 2. Princípios Não Negociáveis

- Arquitetura deve ser testável
- Regras arquiteturais são executáveis
- Violação arquitetural é falha crítica
- Testes arquiteturais não dependem de UI
- Testes arquiteturais não dependem de providers externos
- Testes arquiteturais são parte do pipeline de CI
- Código que viola arquitetura não pode ser integrado

---

## 3. Escopo dos Testes Arquiteturais

Os testes arquiteturais cobrem:

- Dependências entre camadas
- Direção correta de dependências
- Limites de contexto
- Isolamento multi-tenant
- Separação entre domínio e infraestrutura
- Uso correto de eventos, jobs e IA
- Aderência às decisões documentadas nas skills

---

## 4. Camadas e Regras de Dependência

### 4.1 Domain Layer

Regras obrigatórias:

- Não depende de Application Layer
- Não depende de Infrastructure Layer
- Não depende de frameworks
- Não acessa banco, filas ou APIs externas
- Contém apenas regras de negócio puras

Violação dessas regras é **falha arquitetural crítica**.

---

### 4.2 Application Layer

Regras obrigatórias:

- Pode depender do Domain Layer
- Não depende diretamente de Infrastructure
- Orquestra casos de uso
- Define interfaces para dependências externas
- Não contém lógica de infraestrutura

---

### 4.3 Infrastructure Layer

Regras obrigatórias:

- Implementa interfaces definidas na Application Layer
- Pode depender de frameworks e bibliotecas
- Não contém regras de negócio
- Não expõe detalhes técnicos para o domínio

---

### 4.4 Presentation Layer

Regras obrigatórias:

- Nunca contém lógica de negócio
- Apenas traduz entrada e saída
- Não acessa diretamente o domínio
- Sempre passa por use cases

---

## 5. Testes de Isolamento Multi-Tenant

Regras obrigatórias:

- Nenhuma classe ignora o contexto de tenant
- Repositórios exigem tenant_id
- Queries sem tenant_id são proibidas
- Eventos, jobs e logs sempre carregam tenant_id
- Falha de isolamento é falha arquitetural

O isolamento por tenant é tratado como **regra estrutural testável**.

---

## 6. Testes de Eventos e Jobs

### 6.1 Eventos

Regras testáveis:

- Eventos representam fatos passados
- Eventos são imutáveis
- Eventos não executam lógica
- Eventos carregam tenant_id
- Eventos não dependem de infraestrutura

Integração direta com:
- event-driven-architecture

---

### 6.2 Jobs

Regras testáveis:

- Jobs não contêm regras de negócio
- Jobs são idempotentes por contrato
- Jobs carregam tenant context
- Jobs não chamam providers diretamente
- Jobs são observáveis

Integração direta com:
- job-architecture
- idempotency-strategy

---

## 7. Testes Arquiteturais de Segurança

Regras obrigatórias:

- Domínio não conhece autenticação
- Autorização ocorre fora do domínio
- Camadas não expõem dados sensíveis
- Logs não vazam PII
- Segurança não depende de UI

Integração direta com:
- security-architecture
- api-security
- access-control

---

## 8. Testes Arquiteturais de Billing

Regras obrigatórias:

- Billing é desacoplado do domínio
- Billing não executa regras de negócio
- Estados de assinatura controlam acesso
- Domínio não conhece planos ou preços
- Billing não viola isolamento de tenant

Integração direta com:
- billing-subscription
- subscription-lifecycle

---

## 9. Testes Arquiteturais de IA

Regras obrigatórias:

- IA nunca executa regras de negócio
- IA apenas propõe ações
- Toda ação exige confirmação humana
- IA é isolada por tenant
- IA é observável
- IA pode ser desligada sem afetar o core

Integração direta com:
- ai-integration
- ai-data-governance
- ai-observability

---

## 10. Testes Arquiteturais de Persistência

Regras obrigatórias:

- Domínio não depende de ORM
- Repositórios pertencem à infraestrutura
- SQLite é usado apenas em testes
- PostgreSQL é abstraído por contratos
- Persistência não vaza para camadas superiores

Integração direta com:
- testing-strategy

---

## 11. Testes como Documentação Viva

Testes arquiteturais devem refletir:

- As skills do projeto
- As decisões explícitas
- Os limites intencionais do sistema

Se a documentação mudar, os testes devem mudar.  
Se os testes falharem, a arquitetura foi violada.

---

## 12. Anti-Padrões

❌ Confiar apenas em revisão manual  
❌ Arquitetura validada só por convenção  
❌ Testes arquiteturais opcionais  
❌ Ignorar isolamento multi-tenant  
❌ Misturar camadas por conveniência  
❌ Framework ditar arquitetura  

---

## 13. Checklist de Conformidade

- [ ] Dependências entre camadas validadas
- [ ] Domínio isolado de infraestrutura
- [ ] Application Layer corretamente posicionada
- [ ] Jobs e eventos validados
- [ ] Isolamento por tenant testado
- [ ] Segurança respeitada
- [ ] Billing desacoplado
- [ ] IA governada

---

## 14. Status

Documento **OBRIGATÓRIO**.

Sem testes arquiteturais automatizados, a arquitetura se degrada inevitavelmente com o tempo.
