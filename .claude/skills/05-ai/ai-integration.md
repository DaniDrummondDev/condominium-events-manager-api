# ai-integration.md — Integração de Inteligência Artificial no SaaS

## Objetivo

Definir a arquitetura e as regras para integração de **Inteligência Artificial** no Condominium Events Manager API, garantindo:

* Isolamento total por tenant
* Uso seguro e auditável de dados
* Integração desacoplada do domínio
* Execução de tarefas via linguagem natural
* Confirmação humana para ações críticas
* Conformidade com LGPD e políticas internas
* Evolução gradual das capacidades de IA

A IA deve ser tratada como:

1. **Assistente conversacional**
2. **Orquestrador de tarefas**
3. **Camada de aplicação desacoplada do domínio**

O domínio nunca deve depender da IA para funcionar.

---

## Princípios Arquiteturais

1. IA nunca deve violar o isolamento entre tenants
2. Nenhum dado pessoal pode ser enviado a modelos externos sem base legal
3. IA deve ser **opcional e controlada por feature flag**
4. Toda interação com IA deve ser auditável
5. O domínio nunca depende diretamente de IA para funcionar
6. A IA nunca executa regras de negócio diretamente
7. Toda ação deve passar por use cases formais da aplicação
8. Ações críticas exigem confirmação humana explícita

---

## Papel da IA no Sistema

A IA atua como:

### 1. Interface Conversacional

Permite que o usuário interaja com o sistema em linguagem natural, como em um chat.

Exemplos:

* “Quero reservar o salão sábado à noite.”
* “Me ajuda a organizar um churrasco para 20 pessoas.”

### 2. Orquestrador de Ações

A IA:

1. Entende a intenção do usuário
2. Estrutura a ação
3. Solicita confirmação
4. Encaminha a execução para o sistema

A execução real sempre ocorre via **use cases da aplicação**.

---

## Camadas da Arquitetura de IA

```
Application
 ├── AI
 │   ├── ConversationalAssistant
 │   ├── ActionOrchestrator
 │   ├── ToolRegistry
 │   ├── Services
 │   ├── Prompts
 │   ├── DTOs
 │   └── UseCases
Domain
Infrastructure
```

---

## Componentes Principais

### 1. Conversational Assistant

Responsável por:

* Interpretar linguagem natural
* Identificar intenções
* Manter contexto da conversa
* Solicitar dados faltantes

---

### 2. Action Orchestrator

Responsável por:

* Converter intenções em ações estruturadas
* Validar permissões
* Solicitar confirmação do usuário
* Executar use cases após confirmação

---

### 3. Tool Registry

Catálogo de ações que a IA pode executar.

Cada tool:

* Representa um use case da aplicação
* Possui contrato de entrada e saída
* Pode exigir confirmação

Exemplos de tools:

| Tool                  | Descrição              |
| --------------------- | ---------------------- |
| create_event          | Cria evento ou reserva |
| cancel_event          | Cancela reserva        |
| suggest_vendors       | Sugere fornecedores    |
| request_vendor_quote  | Solicita orçamento     |
| confirm_vendor_order  | Confirma pedido        |
| send_whatsapp_message | Envia mensagem externa |

---

### 4. AI Service

Responsável por:

* Comunicação com o provedor de IA
* Envio de prompts
* Recebimento de respostas
* Tratamento de erros

Interface sugerida:

```
AIService
 ├── generateText(prompt, context)
 ├── generateEmbedding(text)
 └── classify(input, labels)
```

---

### 5. Prompt Builder

Responsável por:

* Construir prompts estruturados
* Inserir contexto do tenant
* Filtrar dados sensíveis

---

### 6. AI Use Cases

Casos de uso específicos.

Exemplos:

* CreateEventFromConversation
* SuggestVendors
* RequestVendorQuote
* ConfirmVendorOrder

---

## Padrão de Execução de Ações via IA

A IA nunca executa ações diretamente.
Ela apenas propõe ações estruturadas.

### Estrutura padrão de ação

```json
{
  "action": "create_event",
  "tenant_id": "t1",
  "user_id": "u1",
  "parameters": {
    "space_id": "salon_1",
    "date": "2026-03-15",
    "start_time": "18:00",
    "end_time": "23:00"
  },
  "requires_confirmation": true
}
```

---

## Fluxo de Execução Segura

1. Usuário envia mensagem em linguagem natural
2. IA interpreta a intenção
3. IA monta ação estruturada
4. Sistema apresenta proposta ao usuário
5. Usuário confirma
6. Application executa use case real
7. Sistema retorna resultado
8. Auditoria é registrada

---

## Confirmação Obrigatória

As seguintes ações exigem confirmação explícita:

* Criar reserva ou evento
* Cancelar evento
* Confirmar pedido
* Efetuar pagamento
* Enviar mensagens a terceiros
* Alterar dados importantes

---

## Isolamento por Tenant

Regras obrigatórias:

1. Todo contexto enviado à IA deve conter:

   * tenant_id
2. Nunca misturar dados de tenants
3. Embeddings devem ser:

   * Separados por tenant
   * Filtrados por tenant_id nas consultas

---

## Integração com pgvector

Uso:

* PostgreSQL
* Extensão pgvector
* Embeddings por tenant

Tabela sugerida:

### ai_embeddings

Campos:

* id
* tenant_id
* entity_type
* entity_id
* embedding (vector)
* content_hash
* created_at

---

## Fluxo de Consulta com IA

1. Usuário envia pergunta
2. Sistema valida feature de IA
3. Sistema identifica tenant
4. Sistema busca contexto no banco:

   * Filtrado por tenant_id
5. Gera prompt com contexto
6. Envia para o modelo de IA
7. Retorna resposta
8. Registra auditoria

---

## Auditoria de Uso de IA

Toda interação com IA deve gerar log.

Tabela sugerida: ai_usage_logs

Campos:

* id
* tenant_id
* user_id
* action
* prompt_hash
* model
* tokens_used
* created_at

Eventos auditáveis:

* ai_prompt_executed
* ai_action_proposed
* ai_action_confirmed
* ai_summary_generated
* ai_vendor_quote_requested

---

## Proteção de Dados

Antes de enviar dados para IA externa:

1. Remover:

   * CPF
   * E-mail
   * Telefone
2. Anonimizar identificadores pessoais
3. Enviar apenas:

   * Dados agregados
   * Dados operacionais

---

## Provedores de IA Desacoplados

A arquitetura deve permitir troca de provedor.

Interface:

```
AIProvider
 ├── generateText()
 ├── generateEmbedding()
 └── classify()
```

Implementações possíveis:

* OpenAI
* Azure OpenAI
* Provedor local (futuro)
* Modelo on-premise

---

## Estratégia de Falha

A IA deve ser **não crítica** para o sistema.

Se a IA falhar:

* O sistema continua funcionando
* O usuário pode usar a interface tradicional
* Retornar mensagem padrão:

```
"Assistente indisponível no momento."
```

---

## Segurança

Regras obrigatórias:

1. Nunca enviar dados de um tenant para outro
2. Nunca enviar dados pessoais sensíveis
3. Toda ação via IA deve:

   * Registrar usuário humano
   * Registrar que foi iniciada por IA
4. Logs devem ser mantidos
5. Integrações externas devem passar por serviços internos

---

## Testes

### Testes unitários

* Interpretação de intenções
* Construção de ações
* Resolução de features
* Isolamento por tenant

### Testes de integração

* Fluxo completo de conversa até execução
* Confirmação obrigatória
* Falha de provedor de IA
* Auditoria de ações

---

## Anti-patterns Proibidos

❌ IA executando regras de negócio diretamente
❌ IA criando reservas sem confirmação
❌ Mistura de dados entre tenants
❌ Envio de dados pessoais para IA externa
❌ Chamadas diretas à IA dentro do domínio
❌ IA sendo requisito para funcionamento do sistema
