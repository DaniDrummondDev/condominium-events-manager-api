# Skill — LGPD & Data Governance Compliance

## Objetivo da Skill

Esta skill define **como o Claude deve projetar, documentar e validar conformidade com a LGPD** no projeto *Condominium Events Manager API*.

Ela trata **governança de dados pessoais** como parte do **design do domínio e da arquitetura**, e não como um requisito legal adicionado ao final.

Esta skill **não substitui** a `security-architecture.md`, mas a complementa sob a ótica legal, organizacional e de ciclo de vida dos dados.

---

## Princípios Fundamentais de LGPD

O Claude deve sempre assumir que o sistema processa **dados pessoais e dados potencialmente sensíveis**.

Princípios obrigatórios:

* **Finalidade**: todo dado deve ter propósito explícito
* **Adequação**: uso compatível com a finalidade declarada
* **Necessidade (minimização)**: coletar o mínimo possível
* **Transparência**: uso compreensível e rastreável
* **Segurança**: proteção técnica e organizacional
* **Prevenção**: riscos avaliados antes da implementação
* **Responsabilização**: decisões devem ser auditáveis

---

## Classificação de Dados

O Claude deve classificar dados ao projetar qualquer entidade:

### Tipos de Dados

* **Dados pessoais**: nome, documento, telefone, e-mail
* **Dados de identificação indireta**: unidade, horários, padrões de uso
* **Dados operacionais sensíveis**: logs de acesso, histórico de eventos

### Regra

Toda entidade do domínio deve indicar:

* Se contém dado pessoal
* Qual a finalidade do dado
* Tempo de retenção

---

## Bases Legais de Tratamento

O Claude deve sempre documentar **a base legal** para o tratamento dos dados:

Bases comuns no projeto:

* Execução de contrato (condomínio ↔ plataforma)
* Cumprimento de obrigação legal
* Legítimo interesse (com avaliação de impacto)

Nunca assumir consentimento como padrão.

---

## Direitos do Titular

O sistema deve permitir, direta ou indiretamente:

* Confirmação da existência de tratamento
* Acesso aos dados
* Correção de dados
* Anonimização ou exclusão (quando aplicável)
* Portabilidade (quando aplicável)

### Diretriz importante

Direitos do titular **não podem quebrar integridade do domínio**.

Quando exclusão não for possível:

* Usar anonimização
* Preservar trilhas de auditoria

---

## Retenção e Ciclo de Vida dos Dados

O Claude deve projetar dados considerando:

* Início do tratamento
* Uso ativo
* Arquivamento
* Exclusão ou anonimização

Regras:

* Retenção mínima necessária
* Prazos documentados
* Jobs automáticos para limpeza

---

## Multi-tenancy e LGPD

Diretrizes obrigatórias:

* Dados pessoais **nunca cruzam tenants**
* Cada tenant é controlador independente
* A plataforma atua como operadora

Logs, embeddings e backups devem respeitar isolamento por tenant.

---

## IA, Embeddings e LGPD

Regras específicas:

* Não gerar embeddings sem finalidade clara
* Embeddings são considerados dados pessoais se reidentificáveis
* Embeddings devem:

  * Ser isolados por tenant
  * Ter política de retenção
  * Ser excluíveis ou regeneráveis

Nunca usar dados de um tenant para treinar modelos globais.

---

## Auditoria e Evidências

O Claude deve sempre prever:

* Registro da finalidade do tratamento
* Registro de acessos a dados pessoais
* Registro de exclusões e anonimizações

Esses registros são **provas de conformidade**, não apenas logs técnicos.

---

## Incidentes de Segurança

O design deve permitir:

* Identificação rápida de incidentes
* Avaliação de impacto
* Notificação ao controlador

Processos devem estar documentados, mesmo que automatizados no futuro.

---

## Testes de Conformidade

O Claude deve sempre sugerir e/ou gerar:

* Testes de retenção de dados
* Testes de anonimização
* Testes de escopo de tenant
* Testes de acesso a dados pessoais

Conformidade não é assumida, é verificada.

---

## Integração com Outras Skills

Esta skill:

* Herda princípios de `security-architecture.md`
* Complementa `api-security.md`
* Influencia `ai-integration.md`
* Impacta diretamente `billing-subscription.md`

---

## Resultado Esperado

Ao seguir esta skill, o Claude atuará como:

* Arquiteto orientado à privacidade
* Guardião do ciclo de vida dos dados
* Facilitador de conformidade contínua

LGPD é tratada como **parte do design do sistema**, não como obrigação externa.
