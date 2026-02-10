# Condominium Events Manager API — Visão Geral do Projeto

## 1. Visão do Produto

O **Condominium Events Manager API** é uma plataforma SaaS **API-first** para gestão inteligente de espaços comuns em condomínios residenciais, com foco em **governança do uso**, **reservas**, **controle operacional** e **geração de dados para tomada de decisão**.

O sistema substitui processos manuais e descentralizados por uma **camada única de verdade**, segura, auditável e orientada a regras de negócio explícitas e configuráveis.

---

## 2. Problemas que o Produto Resolve

### 2.1 Operacionais

* Reservas feitas por canais informais (WhatsApp, papel, planilhas)
* Falta de sincronização entre portaria, síndico e administradora
* Ausência de histórico confiável e auditável

### 2.2 Conflitos e Governança

* Sobreposição de reservas por falta de regras centralizadas
* Aplicação inconsistente do regulamento interno
* Ausência de critérios claros para bloqueios, prioridades e penalidades

### 2.3 Controle e Segurança

* Prestadores de serviço sem cadastro, validação ou rastreabilidade
* Convidados sem vínculo formal com eventos ou reservas
* Dificuldade ou impossibilidade de investigação de ocorrências

### 2.4 Tomada de Decisão

* Falta de dados consolidados sobre uso dos espaços
* Decisões baseadas em percepção subjetiva
* Inexistência de inteligência sobre padrões de comportamento

---

## 3. Público-Alvo (Stakeholders)

* **Condôminos**: solicitam e utilizam espaços comuns
* **Síndico**: governa regras, resolve conflitos e analisa dados
* **Administradora**: supervisiona e opera múltiplos condomínios
* **Portaria / Segurança**: valida acessos, eventos e ocorrências

---

## 4. Escopo Funcional (Alto Nível)

### 4.1 Gestão de Espaços

* Cadastro e configuração de espaços comuns (salão, churrasqueira, quadra, etc.)
* Definição de capacidade, horários, restrições e regras específicas

### 4.2 Reservas

* Solicitação, aprovação, cancelamento e bloqueio de reservas
* Prevenção automática de conflitos de agenda
* Aplicação automática de regras e penalidades

### 4.3 Governança e Regras

* Regulamento interno configurável por condomínio
* Gestão de penalidades, bloqueios temporários e exceções
* Trilhas completas de auditoria e justificativas

### 4.4 Controle de Pessoas

* Registro e validação de convidados
* Cadastro e controle de prestadores de serviço
* Associação obrigatória com eventos e reservas

### 4.5 Auditoria e Logs

* Registro de ações críticas e decisões administrativas
* Histórico imutável e rastreável

### 4.6 Inteligência e Dados (IA)

* Identificação de padrões de uso e comportamento
* Detecção de situações de risco ou uso problemático
* Suporte ativo à tomada de decisão do síndico

---

## 5. Arquitetura do Produto (Visão Conceitual)

* Produto **API-first**, independente de canais de consumo
* Arquitetura **multi-tenant por database/schema (PostgreSQL)**
* Isolamento físico de dados por condomínio
* Arquitetura baseada em:

  * Domain-Driven Design (DDD)
  * Clean Architecture
  * SOLID

---

## 5.1 Módulos de Plataforma (SaaS)

Além do domínio principal de gestão e governança de espaços, o sistema possui módulos de **plataforma**, necessários para operação do SaaS, mas **fora do Core Domain**.

Esses módulos são tratados como **Supporting Domains / Generic Subdomains**.

### Responsabilidades

* Aquisição e onboarding de novos clientes (síndicos e administradoras)
* Cadastro de contas (Accounts)
* Criação e ativação de condomínios (Tenants)
* Provisionamento automático de infraestrutura por tenant:

  * Database ou schema PostgreSQL
  * Execução de migrações iniciais
* Associação inicial de usuários, papéis e permissões
* Ativação, suspensão e desativação de tenants
* Preparação para planos, billing e limites de uso (futuro)

### Restrições Arquiteturais

* Este módulo **não contém regras de governança de espaços**
* Não acessa dados de domínio de outros tenants
* Atua como camada de suporte ao produto, não como diferencial competitivo

---

## 6. IA como Componente do Produto

A IA é tratada como **componente estrutural e evolutivo**, não como funcionalidade acessória.

### Usos previstos

* Análise histórica de reservas e utilização dos espaços
* Recomendações de ajustes de regras e políticas internas
* Alertas proativos sobre padrões anômalos
* Comunicação em linguagem natural entre sistema e moradores (texto ou voz)

  * Preferencialmente via APIs de terceiros
  * Fallback para formulários tradicionais quando necessário
* Recomendações de serviços para eventos

  * Integração futura com marketplace de serviços
  * Consulta à API do marketplace para geolocalização e sugestão contextual

### Implementação técnica

* PostgreSQL com **pgvector**
* Estratégias de embedding documentadas previamente
* Embeddings sempre isolados por tenant

---

## 7. Princípios de Evolução

* Documentar antes de implementar
* Começar simples e evoluir com base em dados reais
* Nenhuma feature sem impacto claro e explícito no domínio
* Qualidade como requisito obrigatório, incluindo:

  * Testes Unitários
  * Testes de Feature
  * Testes Arquiteturais
  * Testes de Integração (Banco de Dados)
  * Testes de API / Contract
  * Testes de Validação de Request
  * Testes de Autenticação e Autorização
  * Testes de Jobs e Filas

---

## 8. Próximos Documentos a Serem Criados

Este projeto deverá conter, no mínimo:

* Domain Overview
* Bounded Contexts
* Linguagem Ubíqua
* Modelagem de Domínio
* Arquitetura Técnica
* Estratégia de Multi-tenancy
* Segurança e Autorização
* Roadmap Técnico

Cada documento será criado, validado e evoluído de forma iterativa e controlada.
