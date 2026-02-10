# Decision Records — Registros de Decisões Arquiteturais
## FASE 7 — Qualidade & Governança Técnica  
**Condominium Events Manager API**

---

## 1. Objetivo

Definir a estratégia oficial de **registro, manutenção e governança de decisões arquiteturais**, garantindo que:

- Decisões técnicas não se percam com o tempo
- O racional por trás das escolhas seja preservado
- Mudanças sejam conscientes e justificadas
- Novos colaboradores entendam o “porquê”, não só o “como”
- A arquitetura permaneça coerente durante a evolução do sistema

Decision Records são **parte da arquitetura**, não documentação opcional.

---

## 2. Princípios Não Negociáveis

- Toda decisão relevante deve ser registrada
- Decisões têm contexto e validade temporal
- Decisões não são reescritas silenciosamente
- Mudanças devem ser rastreáveis
- Registros são imutáveis após aprovados
- O histórico é tão importante quanto o estado atual

---

## 3. O Que Deve Ser Registrado

Devem gerar Decision Records:

- Escolhas arquiteturais estruturais
- Adoção ou rejeição de tecnologias
- Mudanças de padrão (ex: sync → async)
- Decisões de segurança
- Decisões de multi-tenancy
- Estratégias de billing
- Uso de IA e suas limitações
- Estratégias de testes e qualidade
- Trade-offs relevantes

Decisões triviais **não** precisam ser registradas.

---

## 4. Estrutura de um Decision Record

Todo Decision Record deve conter:

- Identificador único
- Título claro
- Data
- Status (proposto, aceito, depreciado)
- Contexto
- Decisão
- Justificativa
- Consequências
- Dependências
- Impactos futuros

A estrutura deve ser consistente em todos os registros.

---

## 5. Status de Decisão

Estados permitidos:

- Proposto  
- Aceito  
- Rejeitado  
- Depreciado  
- Substituído  

Decisões depreciadas **não são apagadas**.

---

## 6. Relação com as Skills do Projeto

As skills definem **o estado atual da arquitetura**.  
Os Decision Records explicam **como e por que chegamos lá**.

Toda skill relevante deve ter decisões associadas.

Exemplos de decisões já consolidadas no projeto:

- Arquitetura SaaS multi-tenant com isolamento forte
- API-first sem front-end inicial
- DDD + Clean Architecture
- SQLite como banco de testes
- PostgreSQL em produção
- IA apenas como proponente, nunca executora
- Confirmação humana obrigatória
- Event-driven para desacoplamento
- Jobs idempotentes e observáveis

---

## 7. Governança de Mudanças

Para alterar uma decisão existente:

1. Criar novo Decision Record
2. Referenciar a decisão anterior
3. Explicar o novo contexto
4. Justificar a mudança
5. Avaliar impactos
6. Atualizar skills afetadas
7. Atualizar testes arquiteturais

Mudanças sem registro são **proibidas**.

---

## 8. Integração com Testes

Decisões arquiteturais devem ser:

- Refletidas em testes arquiteturais
- Protegidas contra regressão
- Verificáveis automaticamente

Se uma decisão não pode ser testada, ela deve ser questionada.

Integração direta com:
- architecture-tests
- testing-strategy

---

## 9. Decisões e Onboarding

Decision Records são o principal material para:

- Onboarding técnico
- Revisões arquiteturais
- Auditorias
- Evolução do sistema

Eles reduzem dependência de conhecimento tácito.

---

## 10. Anti-Padrões

❌ Decisões tomadas apenas em conversa  
❌ Alterações sem registro  
❌ Reescrever histórico  
❌ Decisões implícitas  
❌ Falta de justificativa  
❌ Registros vagos ou genéricos  

---

## 11. Checklist de Conformidade

- [ ] Decisões relevantes registradas
- [ ] Estrutura padronizada
- [ ] Histórico preservado
- [ ] Mudanças rastreáveis
- [ ] Skills alinhadas
- [ ] Testes refletindo decisões
- [ ] Governança ativa

---

## 12. Status

Documento **OBRIGATÓRIO**.

Sem registros de decisão, a arquitetura se torna **frágil, subjetiva e difícil de evoluir**.
