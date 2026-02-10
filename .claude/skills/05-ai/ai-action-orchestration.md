# ai-action-orchestration.md — Orquestração de Ações via IA Conversacional

## Objetivo

Definir a arquitetura e as regras para execução de ações no sistema através de **conversas com IA**, garantindo:

* Execução segura de tarefas
* Confirmação humana obrigatória
* Isolamento por tenant
* Auditoria completa
* Desacoplamento do domínio

A IA atua como **interface conversacional e orquestradora**, nunca como executora direta de regras de negócio.

---

## Princípios Arquiteturais

1. A IA nunca executa ações diretamente no domínio
2. Toda ação deve passar por um use case formal
3. Ações críticas exigem confirmação humana
4. Toda ação iniciada por IA deve ser auditada
5. A IA só pode usar ferramentas registradas (tools)

---

## Conceito de Tools

Tools são ações que a IA pode solicitar ao sistema.

Cada tool:

* Representa um use case da aplicação
* Possui contrato de entrada e saída
* Pode exigir confirmação

---

## Estrutura de uma Tool

Exemplo conceitual:

```
Tool: create_event

Input:
- space_id
- date
- start_time
- end_time

Use case associado:
CreateReservationUseCase

Requires confirmation:
true
```

---

## Catálogo Inicial de Tools

### Gestão de eventos

* create_event
* cancel_event
* update_event

### Fornecedores

* suggest_vendors
* request_vendor_quote
* confirm_vendor_order

### Comunicação externa

* send_whatsapp_message

---

## Estrutura Padrão de Ação

Toda ação proposta pela IA deve seguir o formato:

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

## Fluxo de Execução de Ações

1. Usuário envia mensagem em linguagem natural
2. IA interpreta intenção
3. IA monta ação estruturada
4. Sistema apresenta proposta ao usuário
5. Usuário confirma
6. Application executa use case real
7. Resultado retornado ao usuário
8. Auditoria registrada

---

## Confirmação Humana Obrigatória

As seguintes ações exigem confirmação:

* Criar evento ou reserva
* Cancelar evento
* Confirmar pedido com fornecedor
* Enviar mensagens a terceiros
* Efetuar pagamentos
* Alterar dados relevantes

---

## Action Orchestrator

Componente responsável por:

* Receber ação da IA
* Validar permissões do usuário
* Verificar feature flags
* Solicitar confirmação
* Executar use case correspondente

---

## Estrutura da Camada de Orquestração

```
Application
 ├── AI
 │   ├── ConversationalAssistant
 │   ├── ActionOrchestrator
 │   ├── ToolRegistry
 │   ├── ToolHandlers
 │   └── ConfirmationService
```

---

## Tool Registry

Responsável por:

* Registrar todas as tools disponíveis
* Mapear tool → use case
* Definir se requer confirmação

Exemplo:

```
create_event → CreateReservationUseCase
cancel_event → CancelReservationUseCase
```

---

## Auditoria de Ações da IA

Tabela: ai_action_logs

Campos:

* id
* tenant_id
* user_id
* action
* parameters_hash
* confirmed (boolean)
* executed (boolean)
* created_at

Eventos auditáveis:

* ai_action_proposed
* ai_action_confirmed
* ai_action_executed
* ai_action_rejected

---

## Segurança

Regras obrigatórias:

1. A IA nunca pode:

   * Acessar o domínio diretamente
   * Executar queries diretas
2. Toda ação deve:

   * Passar por autenticação
   * Passar por autorização
3. Toda ação deve:

   * Registrar auditoria
   * Registrar usuário humano

---

## Integração com Feature Flags

Cada tool pode depender de uma feature.

Exemplo:

| Tool                 | Feature necessária    |
| -------------------- | --------------------- |
| create_event         | basic_reservations    |
| suggest_vendors      | vendor_integration    |
| request_vendor_quote | ai_vendor_integration |

Se a feature não estiver ativa:

* Tool não deve ser exposta à IA
* Ação deve ser negada

---

## Estratégia de Falha

Se a execução de uma ação falhar:

1. Sistema registra erro
2. Não executa ação parcialmente
3. Informa usuário:

```
Não foi possível concluir a ação.
Deseja tentar novamente?
```

---

## Testes

### Testes unitários

* Interpretação de intenções
* Montagem de ações
* Resolução de tools
* Validação de confirmação

### Testes de integração

* Fluxo completo da conversa até execução
* Auditoria das ações
* Falhas de execução

---

## Anti-patterns Proibidos

❌ IA executando use cases diretamente
❌ Ações sem confirmação humana
❌ Tools sem auditoria
❌ Execução fora da camada de aplicação
❌ IA manipulando dados de múltiplos tenants
