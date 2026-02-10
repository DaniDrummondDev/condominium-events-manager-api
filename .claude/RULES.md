# RULES.md — Regras Não Negociáveis do Projeto

## 1. Princípio Central
Este projeto deve ser desenvolvido **como se fosse operar em produção desde o primeiro commit**, com foco em:

- Segurança
- Confiabilidade
- Escalabilidade
- Compliance legal
- Auditabilidade

Nenhuma decisão técnica pode violar este princípio.

---

## 2. Segurança da Informação (Obrigatória)

### 2.1 LGPD — Lei Geral de Proteção de Dados
O sistema deve estar em conformidade com a LGPD desde o design.

Regras obrigatórias:
- Coletar apenas dados estritamente necessários (minimização)
- Finalidade explícita para cada dado pessoal
- Consentimento quando aplicável
- Possibilidade de:
  - Acesso aos dados
  - Correção
  - Exclusão
  - Portabilidade
- Registro de base legal para tratamento
- Logs de acesso a dados pessoais
- Dados de convidados e prestadores seguem política de retenção

⚠️ Dados pessoais **nunca** podem ser usados para IA sem base legal explícita.

---

### 2.2 OWASP API Security Top 10
Todas as APIs **devem** seguir as recomendações do OWASP API Security Top 10.

Regras obrigatórias:
- Autenticação forte (OAuth2 / JWT)
- Autorização baseada em escopo e papel
- Validação rigorosa de input (request validation)
- Rate limiting e proteção contra abuso
- Proteção contra:
  - Broken Object Level Authorization
  - Excessive Data Exposure
  - Mass Assignment
  - Injection (SQL, NoSQL, Command)
- Erros nunca devem expor detalhes internos

---

### 2.3 ISO 27001 (Baseline de Segurança)
O projeto deve seguir os princípios da ISO 27001 como referência mínima.

Requisitos:
- Controle de acesso baseado em menor privilégio
- Segregação de ambientes (dev / staging / prod)
- Gestão de segredos segura (secret manager, nunca no código)
- Registro e monitoramento de eventos de segurança
- Plano de resposta a incidentes

---

## 3. Multi-Tenancy (Isolamento Obrigatório)

- Isolamento de dados **por tenant** (database/schema PostgreSQL)
- Nenhuma query pode acessar dados fora do contexto do tenant
- Embeddings de IA **devem ser isolados por tenant**
- Notificações, logs e métricas escopados por tenant
- Migrations executadas por tenant de forma independente
- Falhas de isolamento são consideradas **incidentes críticos**

---

## 4. Autenticação e Autorização

- Autenticação centralizada
- RBAC + Policies (decisão final via policies)
- Permissões explícitas por ação
- Acesso administrativo auditado
- Tokens com expiração e revogação
- MFA obrigatório para perfis sensíveis (síndico, admin)

---

## 5. Auditoria e Logs

- Todas as ações administrativas devem ser auditadas
- Logs devem conter:
  - Quem
  - O quê
  - Quando
  - Contexto (tenant, recurso)
- Logs não podem conter dados sensíveis em claro
- Logs devem ser imutáveis e estruturados (JSON)
- Logs centralizados com busca por tenant_id e correlation_id

---

## 6. Billing e Financeiro

- Operações financeiras devem ser idempotentes
- Webhooks devem ser verificados e validados
- Estados financeiros devem ser explícitos e imutáveis
- Falhas de pagamento devem seguir fluxo automático documentado (dunning)
- Nenhuma cobrança pode ocorrer sem rastreabilidade completa
- Gateway é infraestrutura, abstrato por interface

---

## 7. IA e Dados Sensíveis

- IA não pode tomar decisões finais sem intervenção humana
- Resultados de IA devem ser explicáveis
- IA executa ações apenas via Tool Registry e Use Cases formais
- Dados usados para embeddings devem:
  - Ter base legal
  - Ser anonimizados quando possível
  - Ser isolados por tenant
- IA não pode violar regras do domínio
- IA pode ser desligada sem afetar o core

---

## 8. Core Domain (Domínio Principal)

### 8.1 Unidades e Moradores
- Suporte a condomínios horizontais (casas), verticais (blocos + apartamentos) e mistos
- Bloco é opcional (condomínios horizontais não possuem)
- Morador é sempre vinculado a pelo menos uma unidade
- Onboarding exclusivamente via convite do síndico
- Limites por plano: max_units, max_users, max_residents_per_unit
- Unidade desativada não pode fazer reservas

### 8.2 Espaços Comuns
- Configuráveis por condomínio (capacidade, horários, regras)
- Limitados por plano via feature flags
- Bloqueios sobrepõem disponibilidade regular

### 8.3 Reservas
- Prevenção automática de conflitos (lock de concorrência)
- Fluxo de aprovação quando configurado pelo espaço
- Estados explícitos com transições controladas
- Reserva é Aggregate Root

### 8.4 Governança
- Regulamento interno configurável por condomínio
- Infrações automáticas e manuais
- Penalidades com prazo, contestação e revogação
- Toda penalidade tem origem rastreável

### 8.5 Controle de Pessoas
- Convidados e prestadores vinculados obrigatoriamente a reservas
- Check-in/check-out pela portaria
- Dados pessoais com retenção controlada (LGPD)
- Prestador sem vínculo com reserva = acesso negado

### 8.6 Comunicação
- Avisos publicados pelo síndico para audiência selecionável (todos, bloco, unidades)
- Solicitações de suporte com thread de mensagens
- Mensagens internas visíveis apenas para staff (síndico, administradora)
- Confirmação de leitura obrigatória para avisos
- Auto-fechamento de solicitações por inatividade

---

## 9. Qualidade de Código

- Clean Code é obrigatório
- SOLID é obrigatório
- Nenhuma feature sem testes
- Nenhum código sem revisão
- Débito técnico deve ser documentado
- Testes usam SQLite como banco
- Testes arquiteturais validam camadas e isolamento

---

## 10. Observabilidade (Obrigatória)

- Logs estruturados (JSON) com tenant_id, correlation_id, trace_id
- Health checks: liveness, readiness, startup
- SLIs/SLOs definidos e monitorados
- Alertas acionáveis com severidade clara
- Métricas de aplicação, negócio e infraestrutura
- Dados sensíveis nunca em logs ou métricas

---

## 11. CI/CD e Deploy

- Todo código passa por pipeline CI automatizado
- Testes unitários, de integração, arquiteturais e de contrato no pipeline
- Deploy em produção requer aprovação manual
- Migrations executadas automaticamente no deploy
- Segregação de ambientes: dev / staging / production
- Segredos em secret manager, nunca no código
- Rollback sempre disponível e testado

---

## 12. Notificações

- Notificações são desacopladas do domínio (via eventos e filas)
- Nunca enviadas por controllers
- Templates versionados e sem lógica de negócio
- Consentimento respeitado (LGPD)
- Priorização: transacionais > operacionais > informativas
- Provedores abstraídos por interface

---

## 13. Documentação Obrigatória

Nada é implementado sem:
- Skill ou documento de domínio correspondente
- Decisão arquitetural registrada (ADR) quando relevante
- Impacto de segurança avaliado

---

## 14. Regra Final
Se houver conflito entre:
- Velocidade vs Segurança
➡️ **Segurança vence**

- Simplicidade vs Isolamento
➡️ **Isolamento vence**

- Conveniência vs Auditabilidade
➡️ **Auditabilidade vence**

Essas regras não são negociáveis.
