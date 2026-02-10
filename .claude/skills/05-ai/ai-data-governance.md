# ai-data-governance.md — Governança de Dados para Inteligência Artificial

## Objetivo

Definir as regras legais, técnicas e operacionais para o uso de dados em recursos de Inteligência Artificial no Condominium Events Manager API, garantindo:

* Conformidade com LGPD
* Isolamento total por tenant
* Uso mínimo e necessário de dados
* Auditoria completa das interações com IA
* Controle de consentimento
* Possibilidade de anonimização e exclusão

Esta skill regula **como os dados podem ser usados pela IA**, não como a IA funciona tecnicamente.

---

## Princípios Fundamentais

1. A IA só pode usar dados com **base legal válida**
2. O uso de dados deve seguir o princípio da **minimização**
3. Nenhum dado pode sair do tenant sem controle
4. Todo uso de dados pela IA deve ser auditável
5. O usuário deve ter transparência sobre o uso da IA
6. Dados pessoais nunca devem ser enviados a provedores externos sem necessidade real

---

## Base Legal para Uso de Dados

O uso de dados pela IA deve se apoiar em uma das seguintes bases legais:

### 1. Execução de contrato

Aplicável quando:

* A IA está executando uma função essencial do sistema
* Exemplo:

  * Criar reservas
  * Organizar eventos
  * Responder perguntas sobre uso do condomínio

### 2. Legítimo interesse

Aplicável quando:

* A IA está oferecendo melhorias operacionais
* Exemplo:

  * Sugestões de horários
  * Classificação de ocorrências

Deve haver:

* Avaliação de impacto
* Registro da finalidade

### 3. Consentimento explícito

Necessário quando:

* A IA usa dados pessoais para finalidades não essenciais
* A IA se comunica com terceiros em nome do usuário
* A IA envia dados a fornecedores externos

Exemplos:

* Solicitar orçamento a fornecedor
* Enviar mensagem via WhatsApp
* Compartilhar dados do evento com terceiros

---

## Classificação de Dados para IA

Os dados devem ser classificados antes do uso em IA.

### 1. Dados operacionais (permitidos)

Exemplos:

* Datas de eventos
* Horários
* Espaços reservados
* Quantidade de convidados

Podem ser usados pela IA sem consentimento adicional.

---

### 2. Dados pessoais identificáveis (restritos)

Exemplos:

* Nome do morador
* Telefone
* E-mail
* CPF

Regras:

* Nunca enviar diretamente para IA externa
* Devem ser:

  * Removidos
  * Anonimizados
  * Substituídos por identificadores internos

---

### 3. Dados sensíveis (proibidos)

Exemplos:

* Informações médicas
* Religião
* Orientação sexual
* Dados financeiros pessoais

Regra:

* Nunca podem ser usados pela IA
* Nem interna, nem externa

---

## Política de Anonimização

Antes de enviar dados para provedores externos de IA:

1. Remover:

   * Nome completo
   * CPF
   * Telefone
   * E-mail
2. Substituir por:

   * user_id interno
   * Identificadores genéricos

Exemplo:

Entrada original:

```
João Silva quer reservar o salão para 30 pessoas.
Telefone: 21 99999-0000
```

Texto enviado à IA:

```
Usuário deseja reservar o salão para 30 pessoas.
```

---

## Consentimento para Ações com Terceiros

Sempre exigir consentimento explícito quando:

* A IA enviar mensagem para fornecedor
* A IA compartilhar dados do evento
* A IA solicitar orçamento externo
* A IA iniciar qualquer comunicação fora da plataforma

Fluxo obrigatório:

1. IA propõe ação
2. Sistema informa:

   * Qual dado será enviado
   * Para quem
3. Usuário confirma
4. Ação é executada

---

## Auditoria de Uso de Dados em IA

Toda ação envolvendo dados deve ser auditada.

Tabela: ai_data_access_logs

Campos:

* id
* tenant_id
* user_id
* data_category
* action
* legal_basis
* external_provider (nullable)
* created_at

Exemplos de action:

* ai_prompt_executed
* ai_embedding_created
* ai_data_shared_with_vendor

---

## Retenção de Dados de IA

### Logs de uso da IA

Tabela: ai_usage_logs

Retenção:

* 12 meses

Após o período:

* Anonimizar usuário
* Manter apenas métricas técnicas

---

### Embeddings

Tabela: ai_embeddings

Regras:

* Devem ser isolados por tenant
* Devem ser excluídos quando:

  * O tenant for excluído
  * O dado original for removido

---

## Exclusão e Direito ao Esquecimento

Quando um usuário solicitar exclusão:

O sistema deve:

1. Remover dados pessoais do banco
2. Remover ou reprocessar embeddings associados
3. Anonimizar logs de IA
4. Registrar auditoria da exclusão

---

## Transparência para o Usuário

O sistema deve:

1. Informar que a IA está sendo usada
2. Explicar:

   * Para que serve
   * Que dados podem ser utilizados
3. Permitir:

   * Desativar IA (se permitido pelo plano)
   * Revogar consentimentos

---

## Integração com Feature Flags

A IA deve ser controlada por features:

Exemplos:

* ai_assistant
* ai_vendor_integration
* ai_advanced_features

Se a feature não estiver ativa:

* A IA não deve acessar dados
* A IA não deve executar ações

---

## Segurança de Dados

Regras obrigatórias:

1. Todo acesso a dados para IA deve:

   * Ser autenticado
   * Ser autorizado
2. Dados enviados a provedores externos:

   * Devem ser minimizados
   * Devem ser anonimizados
3. Nunca armazenar:

   * Prompts com dados pessoais em texto puro

---

## Anti-patterns Proibidos

❌ Enviar dados pessoais diretamente para IA externa
❌ Usar dados sem base legal
❌ Compartilhar dados com fornecedores sem consentimento
❌ Embeddings sem isolamento por tenant
❌ Logs de IA sem anonimização após retenção
