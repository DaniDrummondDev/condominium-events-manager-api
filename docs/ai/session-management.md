# Conversational Session Management — Condominium Events Manager API

## Objetivo

Definir a estrategia completa de gerenciamento de sessoes conversacionais da IA, incluindo tempos, renovacao, limpeza, context window e configurabilidade por tenant.

---

## 1. Conceito

Uma sessao conversacional representa **uma conversa continua** entre um usuario e o assistente de IA. Ela mantem:

- Historico de mensagens recentes
- Resumo de mensagens anteriores (quando aplicavel)
- Tools propostas e pendentes de confirmacao
- Tools ja executadas na sessao
- Fontes RAG consultadas

A sessao e **efemera** — expira e nao persiste permanentemente.

---

## 2. Tempos e Limites

### 2.1 Valores Padrao

| Parametro | Valor Padrao | Configuravel | Onde Configurar |
|-----------|-------------|-------------|-----------------|
| **Inactivity TTL** | 10 minutos | Sim | Admin do tenant |
| **Absolute max duration** | 2 horas | Sim | Admin do tenant |
| **Confirmation pending TTL** | 5 minutos | Nao | Fixo (seguranca) |
| **Max concurrent sessions** | 3 por usuario | Sim | Admin do tenant |
| **Summarization threshold** | 10 mensagens | Nao | Fixo |

### 2.2 Limites de Configuracao

O admin pode ajustar os valores dentro destes limites:

| Parametro | Minimo | Maximo | Justificativa |
|-----------|--------|--------|---------------|
| Inactivity TTL | 5 min | 30 min | < 5 min e impraticavel; > 30 min desperdia recursos |
| Absolute max duration | 30 min | 4 horas | < 30 min e restritivo; > 4h acumula dados demais |
| Max concurrent sessions | 1 | 5 | > 5 indica abuso |

### 2.3 Valores por Plano (Sugestao)

| Plano | Inactivity TTL | Max Duration | Concurrent Sessions |
|-------|---------------|--------------|---------------------|
| Basic | 10 min | 1 hora | 2 |
| Professional | 10 min | 2 horas | 3 |
| Enterprise | 15 min | 4 horas | 5 |

O tenant admin pode ajustar dentro dos limites do seu plano.

---

## 3. Armazenamento

### 3.1 Redis

Sessoes sao armazenadas em Redis com TTL automatico.

**Chave:**
```
ai_session:{tenant_id}:{user_id}:{session_id}
```

**TTL:** Definido pelo inactivity TTL do tenant (padrao: 10 min). Renovado a cada atividade.

### 3.2 Schema do Valor (JSON)

```json
{
  "session_id": "uuid",
  "tenant_id": "uuid",
  "user_id": "uuid",
  "started_at": "ISO8601",
  "last_activity": "ISO8601",
  "absolute_expiry": "ISO8601",
  "config": {
    "inactivity_ttl_seconds": 600,
    "max_duration_seconds": 7200
  },
  "messages": [
    {
      "id": "uuid",
      "role": "user|assistant",
      "content": "mensagem",
      "timestamp": "ISO8601",
      "tools_proposed": [],
      "tools_executed": []
    }
  ],
  "summary": "resumo das mensagens anteriores ao window ativo",
  "pending_confirmation": {
    "tool": "criar_reserva",
    "parameters": {},
    "nonce": "uuid",
    "proposed_at": "ISO8601",
    "expires_at": "ISO8601"
  },
  "tools_executed_in_session": [
    {
      "tool": "verificar_disponibilidade",
      "result_status": "success",
      "executed_at": "ISO8601"
    }
  ],
  "rag_sources_used": ["source_id_1", "source_id_2"],
  "message_count": 15
}
```

---

## 4. Ciclo de Vida da Sessao

### 4.1 Criacao

```
Usuario envia primeira mensagem para IA
  → Verificar se existe sessao ativa para o usuario
    → Se sim: reutilizar sessao existente
    → Se nao: criar nova sessao
      → Gerar session_id (UUID)
      → Calcular absolute_expiry (now + max_duration)
      → Carregar config do tenant (inactivity_ttl, max_duration)
      → Salvar em Redis com TTL = inactivity_ttl
```

### 4.2 Renovacao (atividade)

```
Usuario envia mensagem
  → Verificar absolute_expiry
    → Se expirou: destruir sessao, criar nova
    → Se nao: renovar TTL no Redis (EXPIRE key ttl)
  → Atualizar last_activity
  → Adicionar mensagem ao historico
  → Verificar message_count para summarization
```

**Regra critica:** Somente mensagens do **usuario** renovam o TTL. Respostas da IA e eventos do sistema nao renovam.

### 4.3 Expiracao por Inatividade

```
Redis TTL expira automaticamente apos inactivity_ttl
  → Sessao destruida sem acao adicional
  → Nenhum dado persiste
  → Proxima mensagem do usuario cria nova sessao
```

### 4.4 Expiracao Absoluta

```
A cada mensagem do usuario:
  → Verificar if now > absolute_expiry
  → Se sim:
    → Salvar resumo final (opcional, para memoria)
    → Destruir sessao
    → Criar nova sessao
    → Informar usuario: "Sessao renovada para melhor experiencia."
```

### 4.5 Destruicao Explicita

| Evento | Acao |
|--------|------|
| Logout | Destruir todas as sessoes do usuario |
| Troca de tenant | Destruir sessao (contexto invalido) |
| Usuario solicita "nova conversa" | Destruir sessao atual, criar nova |
| Admin desativa IA | Destruir todas sessoes do tenant |

---

## 5. Context Window Management

### 5.1 Estrategia por Volume

| Situacao | Estrategia | Mensagens no Prompt |
|----------|-----------|---------------------|
| < 10 mensagens | Historico completo | Todas |
| 10-30 mensagens | Window + resumo | Ultimas 10 + summary |
| > 30 mensagens | Window reduzido + resumo | Ultimas 5 + summary progressivo |

### 5.2 Sumarizacao

Quando `message_count` atinge o threshold (10):

```
1. Pegar mensagens fora do window ativo (msgs 1 a N-10)
2. Enviar para o modelo com prompt de sumarizacao
3. Salvar resumo no campo "summary"
4. Remover mensagens sumarizadas do array "messages"
5. Manter apenas as ultimas 10 no array
```

### 5.3 Prompt de Sumarizacao

```
ID: session_summarize_v1

[INSTRUCAO]
Resuma a conversa abaixo preservando:
- Intencoes expressas pelo usuario
- Acoes ja executadas (tools)
- Decisoes tomadas
- Informacoes relevantes para continuidade

Remova:
- Saudacoes e small talk
- Mensagens de confirmacao simples
- Detalhes tecnicos internos

Formato: paragrafo conciso, maximo 200 palavras.

[CONVERSA]
{messages_to_summarize}
```

### 5.4 Token Budget

| Componente do Prompt | Budget Estimado |
|---------------------|-----------------|
| System prompt (guardrails) | ~500 tokens |
| Memoria do usuario | ~200 tokens |
| Resumo de sessao | ~300 tokens |
| RAG results (Top-5 chunks) | ~2500 tokens |
| Historico recente (10 msgs) | ~1500 tokens |
| Input do usuario | ~200 tokens |
| **Total input estimado** | **~5200 tokens** |
| Reserva para output | ~2000 tokens |

Dentro do limite de gpt-4o-mini (128K context), com margem confortavel.

---

## 6. Sessoes Concorrentes

### 6.1 Regras

| Regra | Valor |
|-------|-------|
| Max sessoes por usuario por tenant | 3 (configuravel) |
| Quando excede limite | Sessao mais antiga e destruida |
| Sessoes sao independentes | Cada uma tem seu contexto |

### 6.2 Implementacao

```
Ao criar nova sessao:
  → Listar sessoes ativas do usuario (SCAN ai_session:{tenant_id}:{user_id}:*)
  → Se count >= max_concurrent:
    → Ordenar por last_activity
    → Destruir a mais antiga
  → Criar nova sessao
```

### 6.3 Index de Sessoes

Para listar sessoes ativas sem SCAN (que e lento), manter um set:

```
Chave: ai_sessions_index:{tenant_id}:{user_id}
Tipo: Redis Sorted Set
Score: timestamp de last_activity
Member: session_id
TTL: mesmo do absolute_expiry mais longo
```

---

## 7. Confirmacao Pendente

### 7.1 Fluxo

```
IA propoe acao (tool)
  → Gerar nonce (UUID)
  → Salvar em pending_confirmation:
    {
      "tool": "criar_reserva",
      "parameters": { ... },
      "nonce": "uuid",
      "proposed_at": "now",
      "expires_at": "now + 5min"
    }
  → Apresentar ao usuario com descricao

Usuario confirma
  → Validar nonce
  → Verificar expires_at > now
  → Executar tool via backend
  → Limpar pending_confirmation
  → Registrar em tools_executed_in_session

Usuario nao responde em 5min
  → pending_confirmation expira
  → IA informa: "A proposta de acao expirou. Deseja que eu refaca?"
```

### 7.2 Regras de Seguranca

- **Um nonce por acao** — nunca reutilizar
- **Apenas uma confirmacao pendente por vez** — nova proposta cancela a anterior
- **Nonce validado no backend** — nunca confiar no frontend
- **Timeout fixo de 5 min** — nao configuravel (seguranca)

---

## 8. Eventos de Sessao

| Evento | Quando | Dados |
|--------|--------|-------|
| `ai.session.created` | Nova sessao iniciada | session_id, tenant_id, user_id |
| `ai.session.renewed` | TTL renovado por atividade | session_id, new_ttl |
| `ai.session.summarized` | Sumarizacao executada | session_id, message_count_before |
| `ai.session.expired_inactivity` | Expirou por inatividade | session_id, duration |
| `ai.session.expired_absolute` | Expirou por duracao maxima | session_id, duration |
| `ai.session.destroyed` | Destruida explicitamente | session_id, reason |
| `ai.session.concurrent_evicted` | Destruida por limite de concorrentes | session_id, user_id |
| `ai.confirmation.proposed` | Acao proposta ao usuario | session_id, tool, nonce |
| `ai.confirmation.accepted` | Usuario confirmou | session_id, tool, nonce |
| `ai.confirmation.expired` | Confirmacao expirou | session_id, tool, nonce |

---

## 9. Configuracao no Sistema

### 9.1 Config Padrao (config/ai.php)

```php
'session' => [
    'default_inactivity_ttl' => env('AI_SESSION_INACTIVITY_TTL', 600),     // 10 min
    'default_max_duration' => env('AI_SESSION_MAX_DURATION', 7200),         // 2 horas
    'default_max_concurrent' => env('AI_SESSION_MAX_CONCURRENT', 3),
    'confirmation_ttl' => 300,                                              // 5 min (fixo)
    'summarization_threshold' => 10,                                        // msgs (fixo)
    'limits' => [
        'inactivity_ttl_min' => 300,    // 5 min
        'inactivity_ttl_max' => 1800,   // 30 min
        'max_duration_min' => 1800,     // 30 min
        'max_duration_max' => 14400,    // 4 horas
        'max_concurrent_min' => 1,
        'max_concurrent_max' => 5,
    ],
],
```

### 9.2 Override por Tenant (banco de dados)

```sql
-- Na tabela tenant_settings (ou equivalente):
-- Tenant admin pode ajustar dentro dos limites do plano

{
  "ai_session_inactivity_ttl": 600,    -- 10 min (customizavel)
  "ai_session_max_duration": 7200,     -- 2 horas (customizavel)
  "ai_session_max_concurrent": 3       -- (customizavel)
}
```

### 9.3 Hierarquia de Configuracao

```
1. Configuracao do Tenant (banco) → tem prioridade
2. Configuracao do Plano (limites)  → define limites
3. Config padrao (config/ai.php)    → fallback
```

---

## 10. Protecao de Dados (LGPD)

| Regra | Implementacao |
|-------|---------------|
| **Nenhum PII** na sessao | Nunca armazenar CPF, telefone, email no contexto |
| **Efemero** | Dados desaparecem com a expiracao, sem backup |
| **Isolamento** | Chave Redis inclui tenant_id + user_id |
| **Sem persistencia** | Sessoes nunca sao salvas em banco permanente |
| **Resumo anonimizado** | Sumarizacao remove referencias pessoais |

---

## 11. Falha do Redis

| Cenario | Comportamento |
|---------|--------------|
| Redis indisponivel | Sessao nao pode ser criada; IA retorna "Assistente indisponivel" |
| Redis lento (> 500ms) | Log de alerta, continuar com timeout |
| Perda de dados Redis (restart) | Todas as sessoes perdidas, usuarios iniciam novas |
| Sessao corrompida | Destruir e criar nova, log de erro |

Sessoes sao **descartaveis**. Perda de sessao nao causa perda de dados do dominio.

---

## 12. Anti-padroes

| Anti-padrao | Alternativa |
|-------------|-------------|
| Sessao sem TTL | Sempre ter inactivity TTL |
| Sessao sem expiracao absoluta | Max duration obrigatorio |
| Armazenar PII na sessao | Apenas IDs e dados operacionais |
| Persistir sessao em banco | Redis com TTL, efemero |
| Renovar TTL com resposta da IA | Somente input do usuario renova |
| Confirmacao sem nonce | Nonce unico por acao |
| Multiplas confirmacoes pendentes | Uma por vez |
| SCAN para listar sessoes | Sorted Set como indice |
