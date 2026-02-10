# CI/CD Strategy — Estratégia de Integração e Entrega Contínua
## FASE 6 — Operação & Confiabilidade
**Condominium Events Manager API**

---

## 1. Objetivo

Definir a estratégia oficial de **CI/CD (Continuous Integration / Continuous Delivery)** do sistema, garantindo:

- Integração segura e automatizada de código
- Validação contínua de qualidade e arquitetura
- Deploy previsível e auditável
- Segregação de ambientes
- Rollback confiável
- Compatibilidade com arquitetura multi-tenant

CI/CD é **infraestrutura de qualidade**, não conveniência.

---

## 2. Princípios Não Negociáveis

- Todo código é integrado via pipeline automatizado
- Nenhum deploy manual em produção
- Testes devem passar antes de qualquer merge
- Testes arquiteturais fazem parte do pipeline
- Migrations são executadas automaticamente no deploy
- Segredos nunca estão no código ou no pipeline em texto claro
- Ambientes são segregados (dev, staging, production)
- Todo deploy é auditável

---

## 3. Pipeline de Integração Contínua (CI)

### 3.1 Etapas Obrigatórias

1. **Lint & Code Style**
   - Verificação de padrões de código
   - Análise estática

2. **Testes Unitários e de Domínio**
   - Executados com SQLite
   - Rápidos e determinísticos

3. **Testes de Application Layer**
   - Use cases com persistência real (SQLite)

4. **Testes Arquiteturais**
   - Validação de camadas
   - Isolamento multi-tenant
   - Dependências corretas

5. **Testes de Contrato de API**
   - Validação de endpoints
   - Backward compatibility

6. **Testes de Segurança**
   - SAST (Static Application Security Testing)
   - Verificação de dependências vulneráveis

7. **Build**
   - Compilação/empacotamento da aplicação

### 3.2 Regras

- Pipeline falha se qualquer etapa falhar
- Falha bloqueia merge
- Resultados são visíveis e acessíveis
- Tempo total do pipeline deve ser monitorado

---

## 4. Pipeline de Entrega Contínua (CD)

### 4.1 Ambientes

| Ambiente | Propósito | Deploy |
|----------|-----------|--------|
| Development | Desenvolvimento local | Manual |
| Staging | Validação pré-produção | Automático (branch main) |
| Production | Produção real | Manual com aprovação |

### 4.2 Fluxo de Deploy

1. Código aprovado e merged na branch principal
2. Pipeline CI executa e passa
3. Deploy automático em staging
4. Validação em staging (smoke tests)
5. Aprovação manual para produção
6. Deploy em produção
7. Execução de migrations (plataforma e tenants)
8. Smoke tests em produção
9. Monitoramento pós-deploy

### 4.3 Regras

- Deploy em produção requer aprovação explícita
- Rollback deve estar sempre disponível
- Zero-downtime deployment quando possível
- Deploy não deve afetar tenants ativos

---

## 5. Branching Strategy

### 5.1 Modelo

- Branch principal: `main`
- Feature branches: `feature/<nome>`
- Fix branches: `fix/<nome>`
- Release branches: opcionais, conforme necessidade

### 5.2 Regras

- Todo código entra via Pull Request
- PR requer aprovação e pipeline verde
- Nenhum commit direto em `main`
- Branches de curta duração (evitar long-lived branches)

---

## 6. Migrations no Deploy

### 6.1 Fluxo

1. Deploy da aplicação
2. Execução de migrations da plataforma
3. Execução de migrations de todos os tenants ativos
4. Verificação de sucesso

### 6.2 Regras

- Migrations são executadas automaticamente
- Falha de migration bloqueia ativação do deploy
- Tenants com migration falhada são marcados para reprocessamento
- Rollback de migration é manual e auditado

Integração direta com:
- `migration-strategy.md`

---

## 7. Gestão de Segredos

### 7.1 Regras

- Segredos são armazenados em secret manager
- Nunca em variáveis de ambiente em texto no pipeline
- Rotação periódica
- Acesso auditado
- Segregação por ambiente

### 7.2 Exemplos

- Credenciais de banco de dados
- Chaves de API (gateway de pagamento, IA)
- Chaves de assinatura JWT
- Webhooks secrets

---

## 8. Segurança do Pipeline

- Pipeline não expõe segredos em logs
- Imagens e dependências são verificadas
- Acesso ao pipeline é controlado
- Alterações no pipeline são auditadas
- Dependências são verificadas por vulnerabilidades (Dependabot, Snyk)

---

## 9. Observabilidade do Pipeline

Métricas obrigatórias:

- Tempo de execução do pipeline
- Taxa de falha por etapa
- Frequência de deploys
- Tempo entre commit e deploy (lead time)
- Taxa de rollback

---

## 10. Rollback

### 10.1 Estratégia

- Rollback da aplicação: reverter para versão anterior
- Rollback de migrations: manual e documentado
- Rollback de configuração: via secret manager

### 10.2 Regras

- Rollback deve ser testado periodicamente
- Rollback não deve causar perda de dados
- Rollback é auditado
- Tempo de rollback deve ser monitorado

---

## 11. Integração com Outras Skills

Esta skill integra-se com:

- `migration-strategy.md` — execução de migrations no deploy
- `testing-strategy.md` — testes no pipeline
- `architecture-tests.md` — validação arquitetural no CI
- `api-contract-strategy.md` — testes de contrato no CI
- `security-compliance.md` — segurança do pipeline
- `observability-strategy.md` — monitoramento pós-deploy

---

## 12. Anti-Padrões

- Deploy manual em produção
- Pipeline sem testes arquiteturais
- Segredos no código ou logs
- Long-lived branches
- Deploy sem migrations automáticas
- Ausência de rollback plan
- Pipeline lento sem otimização

---

## 13. Checklist de Conformidade

- [ ] Pipeline CI com todas as etapas
- [ ] Deploy automatizado para staging
- [ ] Deploy com aprovação para produção
- [ ] Migrations automáticas no deploy
- [ ] Segredos em secret manager
- [ ] Rollback documentado e testado
- [ ] Observabilidade do pipeline
- [ ] Segurança do pipeline garantida

---

## 14. Status

Documento **OBRIGATÓRIO** para operação profissional.

Sem CI/CD automatizado, a qualidade do sistema degrada com cada mudança.
