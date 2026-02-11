# AI Provider Strategy — Condominium Events Manager API

## Objetivo

Definir a estrategia de abstracao, selecao, fallback e controle de custos para provedores de IA, garantindo que o sistema nunca dependa de um unico provider e que a troca seja transparente para o dominio.

---

## 1. Principios

1. **Desacoplamento total** — O dominio e a aplicacao nunca referenciam um provider especifico.
2. **Provider como infraestrutura** — Implementacoes de provider vivem na camada de Infrastructure.
3. **Fallback obrigatorio** — Nenhum provider e 100% disponivel; fallback deve existir.
4. **Custo e observavel** — Todo uso de IA gera metricas de custo por tenant.
5. **Modelo por caso de uso** — Nem toda tarefa precisa do modelo mais caro.
6. **Feature flag por capability** — Cada capability de IA pode ser ligada/desligada independentemente.

---

## 2. Arquitetura de Abstracao

### 2.1 Contratos (Application Layer)

```
src/Application/AI/Contracts/
├── TextGenerationInterface.php      → generateText(prompt, context): string
├── EmbeddingGenerationInterface.php → generateEmbedding(text): array<float>
├── ClassificationInterface.php      → classify(input, labels): ClassificationResult
└── ProviderHealthInterface.php      → isAvailable(): bool, latency(): int
```

Cada contrato define **uma capability**, nao um provider.

### 2.2 Implementacoes (Infrastructure Layer)

```
app/Infrastructure/AI/Providers/
├── OpenAI/
│   ├── OpenAITextGeneration.php
│   ├── OpenAIEmbeddingGeneration.php
│   └── OpenAIClassification.php
├── AzureOpenAI/
│   ├── AzureTextGeneration.php
│   ├── AzureEmbeddingGeneration.php
│   └── AzureClassification.php
└── Ollama/
    ├── OllamaTextGeneration.php
    ├── OllamaEmbeddingGeneration.php
    └── OllamaClassification.php
```

### 2.3 Resolucao via Provider Manager

```
app/Infrastructure/AI/
├── AIProviderManager.php              → Resolve provider ativo por capability
├── AIProviderFallbackDecorator.php    → Decorator: tenta primary, fallback em falha
└── AIProviderConfig.php               → Le config/ai.php e retorna provider por capability
```

O `AIProviderManager` implementa os contratos da Application e delega para o provider ativo.

---

## 3. Configuracao

### 3.1 Arquivo de Configuracao

```php
// config/ai.php
return [
    'enabled' => env('AI_ENABLED', false),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'organization' => env('OPENAI_ORGANIZATION'),
            'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            'timeout' => 30,
        ],
        'azure_openai' => [
            'api_key' => env('AZURE_OPENAI_API_KEY'),
            'endpoint' => env('AZURE_OPENAI_ENDPOINT'),
            'api_version' => env('AZURE_OPENAI_API_VERSION', '2024-02-01'),
            'timeout' => 30,
        ],
        'ollama' => [
            'base_url' => env('OLLAMA_BASE_URL', 'http://ollama:11434'),
            'timeout' => 60,  // modelos locais podem ser mais lentos
        ],
    ],

    'capabilities' => [
        'text_generation' => [
            'primary' => env('AI_TEXT_PRIMARY', 'openai'),
            'fallback' => env('AI_TEXT_FALLBACK', 'azure_openai'),
            'model' => env('AI_TEXT_MODEL', 'gpt-4o-mini'),
            'fallback_model' => env('AI_TEXT_FALLBACK_MODEL', 'gpt-4o-mini'),
            'max_tokens' => 2000,
            'temperature' => 0.3,
        ],
        'embedding' => [
            'primary' => env('AI_EMBEDDING_PRIMARY', 'openai'),
            'fallback' => env('AI_EMBEDDING_FALLBACK', 'azure_openai'),
            'model' => env('AI_EMBEDDING_MODEL', 'text-embedding-3-small'),
            'dimensions' => env('AI_EMBEDDING_DIMENSIONS', 1536),
        ],
        'classification' => [
            'primary' => env('AI_CLASSIFICATION_PRIMARY', 'openai'),
            'fallback' => env('AI_CLASSIFICATION_FALLBACK', null),
            'model' => env('AI_CLASSIFICATION_MODEL', 'gpt-4o-mini'),
        ],
    ],

    'rate_limits' => [
        'per_tenant_per_minute' => env('AI_RATE_LIMIT_TENANT', 60),
        'per_user_per_minute' => env('AI_RATE_LIMIT_USER', 20),
        'global_per_minute' => env('AI_RATE_LIMIT_GLOBAL', 500),
    ],

    'cost' => [
        'tracking_enabled' => env('AI_COST_TRACKING', true),
        'alert_threshold_daily_usd' => env('AI_COST_ALERT_DAILY', 50.00),
        'hard_limit_daily_usd' => env('AI_COST_HARD_LIMIT_DAILY', 100.00),
    ],
];
```

### 3.2 Variaveis de Ambiente

| Variavel | Descricao | Default |
|----------|-----------|---------|
| `AI_ENABLED` | Master switch da IA | `false` |
| `AI_TEXT_PRIMARY` | Provider primario para texto | `openai` |
| `AI_TEXT_FALLBACK` | Provider fallback para texto | `azure_openai` |
| `AI_TEXT_MODEL` | Modelo de texto | `gpt-4o-mini` |
| `AI_EMBEDDING_PRIMARY` | Provider primario para embeddings | `openai` |
| `AI_EMBEDDING_MODEL` | Modelo de embedding | `text-embedding-3-small` |
| `AI_EMBEDDING_DIMENSIONS` | Dimensao do vetor de embedding | `1536` |
| `OLLAMA_BASE_URL` | Endpoint do Ollama local | `http://ollama:11434` |
| `AI_RATE_LIMIT_TENANT` | Limite de requests por tenant/min | `60` |
| `AI_COST_TRACKING` | Habilita tracking de custo | `true` |
| `AI_COST_HARD_LIMIT_DAILY` | Limite diario em USD | `100.00` |

---

## 4. Estrategia de Fallback

### 4.1 Cadeia de Fallback

```
Provider Primario (pode ser local ou cloud, conforme roteamento)
  → [Falha: timeout, 5xx, rate limit]
  → Provider Fallback (cloud, se primario era local)
    → [Falha]
    → Resposta degradada (mensagem padrao)
```

**Exemplo com estrategia hibrida:**
```
Classificacao de intencao:
  Ollama (LLaMA 8B) → [falha] → OpenAI (gpt-4o-mini) → [falha] → fallback manual

Rascunho de aviso:
  OpenAI (gpt-4o) → [falha] → Azure OpenAI (gpt-4o) → [falha] → degradacao
```

### 4.2 Tipos de Falha e Acao

| Tipo de Falha | Acao |
|---------------|------|
| Timeout (>30s) | Fallback imediato |
| HTTP 429 (rate limit) | Fallback imediato |
| HTTP 5xx | Fallback imediato |
| HTTP 401/403 (auth) | Alerta critico, fallback |
| Resposta malformada | Log + fallback |
| Provider indisponivel | Fallback |
| Ambos indisponiveis | Resposta degradada |

### 4.3 Resposta Degradada

Quando nenhum provider esta disponivel:

```json
{
  "status": "ai_unavailable",
  "message": "Assistente indisponivel no momento. Utilize o menu principal.",
  "fallback_action": "redirect_to_ui"
}
```

O sistema core continua funcionando normalmente sem IA.

### 4.4 Circuit Breaker

| Parametro | Valor |
|-----------|-------|
| Falhas consecutivas para abrir circuito | 5 |
| Tempo em estado aberto | 60 segundos |
| Requisicoes de teste em half-open | 1 |
| Reset apos sucesso | Imediato |

Implementado no `AIProviderFallbackDecorator` com estado em Redis.

---

## 5. Estrategia Hibrida: Local + Cloud

### 5.1 Conceito

O sistema utiliza uma **estrategia hibrida** de providers: modelos locais (LLaMA via Ollama) para tarefas simples e de alto volume, modelos cloud (OpenAI/Azure) para tarefas que exigem maior qualidade. Isso otimiza custos sem sacrificar qualidade onde importa.

```
Tarefa Simples (classificacao, embedding, Q&A direto)
  → Modelo Local (LLaMA 8B / nomic-embed) → Custo zero por token

Tarefa Complexa (redacao, raciocinio, analise)
  → Modelo Cloud (GPT-4o-mini / GPT-4o) → Melhor qualidade

Fallback: Local falha → Cloud assume
```

### 5.2 Roteamento por Caso de Uso

| Caso de Uso | Provider | Modelo | Justificativa |
|-------------|----------|--------|---------------|
| Classificacao de intencao | **Local** | LLaMA 3.1 8B | Alto volume, simples, zero custo |
| Embeddings | **Local** | nomic-embed-text | Volume alto, custo de re-indexacao unico |
| Conversacao simples (RAG) | **Local** | LLaMA 3.1 8B | Perguntas diretas sobre regras, RAG fornece contexto |
| Sumarizacao de sessao | **Local** | LLaMA 3.1 8B | Tarefa estruturada, nao criativa |
| Proposta de tool | **Cloud** | gpt-4o-mini | Precisa de aderencia precisa ao schema |
| Explicacao detalhada de regra | **Cloud** | gpt-4o-mini | Qualidade de explicacao importa |
| Rascunho de aviso | **Cloud** | gpt-4o | Requer redacao formal de alta qualidade |
| Sugestao de resposta de suporte | **Cloud** | gpt-4o-mini | Empatia e tom profissional |
| Analise de conflitos complexos | **Cloud** | gpt-4o | Raciocinio sofisticado |

### 5.3 Configuracao de Roteamento

```php
// config/ai.php
'routing' => [
    // Tarefas roteadas para provider local quando disponivel
    'prefer_local' => [
        'intent_classification',
        'embedding_generation',
        'simple_conversation',
        'session_summarization',
    ],

    // Tarefas que sempre usam cloud
    'require_cloud' => [
        'tool_proposal',
        'announcement_draft',
        'complex_analysis',
    ],

    // Tarefas que usam local com fallback para cloud
    'local_with_cloud_fallback' => [
        'rule_explanation',
        'support_response_suggestion',
    ],
],
```

### 5.4 Modelos Locais Recomendados

#### Texto (via Ollama)

| Modelo | Tamanho | VRAM | Qualidade PT-BR | Uso Recomendado |
|--------|---------|------|-----------------|-----------------|
| LLaMA 3.1 8B (Q4) | ~5 GB | 6 GB | Boa | Classificacao, Q&A simples, sumarizacao |
| LLaMA 3.1 8B (Q8) | ~8 GB | 10 GB | Melhor | Conversacao, explicacoes |
| Mistral 7B (Q4) | ~4 GB | 5 GB | Boa | Alternativa ao LLaMA |

**Nota:** Modelos 8B sao suficientes para as tarefas locais definidas. Modelos maiores (70B+) nao sao necessarios e requerem hardware caro.

#### Embeddings (via Ollama)

| Modelo | Dimensao | Tamanho | Qualidade |
|--------|----------|---------|-----------|
| nomic-embed-text | 768 | ~275 MB | Boa para PT-BR |
| mxbai-embed-large | 1024 | ~670 MB | Melhor qualidade |
| all-MiniLM-L6-v2 | 384 | ~90 MB | Leve, qualidade media |

**Recomendacao:** `nomic-embed-text` (768 dims) — bom equilibrio qualidade/tamanho.

### 5.5 Override por Plano

| Plano | Texto (simples) | Texto (complexo) | Embedding |
|-------|-----------------|-------------------|-----------|
| Basic | Local (LLaMA 8B) | gpt-4o-mini | Local (nomic-embed) |
| Professional | Local (LLaMA 8B) | gpt-4o-mini / gpt-4o | Local (nomic-embed) |
| Enterprise | Local (LLaMA 8B) | gpt-4o | Cloud (text-embedding-3-large) |

Controlado via feature flags no plano do tenant.

### 5.6 Quando Ativar o Modelo Local

O modelo local e **opcional**. O sistema funciona 100% com cloud. Ativar local quando:

| Criterio | Threshold |
|----------|-----------|
| Volume mensal de requests de IA | > 10.000 |
| Custo mensal cloud | > $100/mes |
| Latencia e critica | < 200ms desejado |
| Privacidade maxima | Dados nao podem sair do servidor |

### 5.7 Docker: Servico Ollama

```yaml
# docker-compose.yml (quando ativado)
ollama:
  image: ollama/ollama:latest
  ports:
    - "11434:11434"
  volumes:
    - ollama_data:/root/.ollama
  deploy:
    resources:
      reservations:
        devices:
          - driver: nvidia
            count: 1
            capabilities: [gpu]
  # Sem GPU (CPU only, mais lento):
  # Remover bloco 'deploy' acima
```

**Inicializacao de modelos (setup.sh):**

```bash
# Baixar modelos na primeira execucao
docker exec ollama ollama pull llama3.1:8b-q4
docker exec ollama ollama pull nomic-embed-text
```

---

## 6. Controle de Custos

### 6.1 Metricas de Custo

Cada chamada ao provider registra:

| Campo | Descricao |
|-------|-----------|
| `tenant_id` | Tenant que gerou o custo |
| `capability` | text_generation, embedding, classification |
| `provider` | ollama, openai, azure_openai |
| `model` | Modelo usado |
| `input_tokens` | Tokens de entrada |
| `output_tokens` | Tokens de saida |
| `estimated_cost_usd` | Custo estimado baseado na tabela de precos |
| `timestamp` | Quando ocorreu |

### 6.2 Tabela de Precos (Referencia)

Armazenada em `config/ai.php` e atualizada manualmente:

```php
'pricing' => [
    // Cloud providers (custo por token)
    'gpt-4o-mini' => [
        'input_per_1m' => 0.15,   // USD por 1M tokens
        'output_per_1m' => 0.60,
    ],
    'gpt-4o' => [
        'input_per_1m' => 2.50,
        'output_per_1m' => 10.00,
    ],
    'text-embedding-3-small' => [
        'input_per_1m' => 0.02,
    ],
    'text-embedding-3-large' => [
        'input_per_1m' => 0.13,
    ],
    // Modelos locais (custo zero por token, custo de infra fixo)
    'llama3.1:8b' => [
        'input_per_1m' => 0.00,
        'output_per_1m' => 0.00,
    ],
    'nomic-embed-text' => [
        'input_per_1m' => 0.00,
    ],
],
```

### 6.3 Limites e Alertas

| Controle | Acao |
|----------|------|
| Alerta diario (ex: $50) | Notifica platform_admin |
| Hard limit diario (ex: $100) | Bloqueia novas chamadas de IA |
| Limite por tenant | Proporcional ao plano |
| Anomalia de custo | Alerta se custo 3x acima da media |

### 6.4 Armazenamento

```sql
CREATE TABLE ai_cost_tracking (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL,
    user_id UUID NOT NULL,
    capability VARCHAR(50) NOT NULL,
    provider VARCHAR(50) NOT NULL,
    model VARCHAR(100) NOT NULL,
    input_tokens INT NOT NULL DEFAULT 0,
    output_tokens INT NOT NULL DEFAULT 0,
    estimated_cost_usd DECIMAL(10,6) NOT NULL,
    trace_id UUID NULL,
    created_at TIMESTAMP NOT NULL,

    CONSTRAINT fk_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id)
);

CREATE INDEX idx_cost_tenant_date ON ai_cost_tracking (tenant_id, created_at);
CREATE INDEX idx_cost_daily ON ai_cost_tracking (created_at);
```

---

## 7. Integracao com Laravel Prism

O projeto usa o pacote **Prism** como abstraction layer para providers de IA.

### 7.1 Como Prism se Encaixa

```
Application Contracts (TextGenerationInterface)
  → Infrastructure (PrismTextGeneration)
    → Prism (abstrai providers)
      → OpenAI / Azure / Ollama
```

Prism facilita a troca de provider, mas os contracts da Application **nao dependem de Prism** — dependem dos nossos interfaces.

### 7.2 Beneficios

- Prism gerencia HTTP clients, retries e parsing de respostas
- Suporte nativo a OpenAI, Azure, Anthropic, Ollama
- Nosso `AIProviderFallbackDecorator` adiciona circuit breaker e fallback

---

## 8. Embedding Migration Strategy

### 8.1 O Problema

Trocar o modelo de embedding (ex: de `text-embedding-3-small` para `nomic-embed-text`) altera a **dimensao do vetor**. Vetores de dimensoes diferentes sao incompativeis — nao podem ser comparados via similaridade.

| Cenario | Impacto |
|---------|---------|
| OpenAI → nomic-embed (local) | 1536 → 768 dims |
| OpenAI small → large | 1536 → 3072 dims |
| Qualquer troca de modelo | Re-indexacao obrigatoria |

### 8.2 Schema Flexivel

A dimensao do vetor e definida pela **configuracao ativa**, nao hardcoded:

```sql
-- A dimensao e configuravel via AI_EMBEDDING_DIMENSIONS env var
-- Valor definido na criacao da tabela do tenant
embedding VECTOR({dimensions})  -- 1536, 768, 1024, etc.
```

O campo `model_version` na tabela `ai_embeddings` rastreia qual modelo gerou cada embedding.

### 8.3 Processo de Migracao (Zero-Downtime)

```
1. Deploy novo modelo de embedding (configuracao + container Ollama)
2. Criar coluna temporaria: embedding_new VECTOR({nova_dimensao})
3. Job batch: re-gerar embeddings do conteudo original
   → Para cada embedding existente:
     → Ler content_text
     → Gerar novo embedding com novo modelo
     → Salvar em embedding_new com novo model_version
4. Verificar integridade (count, nulls)
5. Swap atomico:
   → Renomear embedding → embedding_old
   → Renomear embedding_new → embedding
   → Recriar indice IVFFlat na nova coluna
6. Periodo de grace (7 dias): manter embedding_old
7. Apos validacao: DROP embedding_old
```

### 8.4 Job de Re-indexacao

```
Job: ReindexTenantEmbeddingsJob
  - Processado por tenant (isolamento)
  - Idempotente (content_hash evita duplicatas)
  - Batch de 100 embeddings por execucao
  - Retry com backoff exponencial
  - Metricas: progresso %, tempo estimado, erros
```

### 8.5 Custos de Migracao

| Modelo | Custo por 10K embeddings |
|--------|------------------------|
| text-embedding-3-small (cloud) | ~$0.30 |
| nomic-embed-text (local) | $0.00 |

Migracao para modelo local e financeiramente atrativa apos a primeira re-indexacao.

---

## 9. Rate Limiting

### 8.1 Camadas de Rate Limit

| Camada | Limite | Implementacao |
|--------|--------|---------------|
| Global | 500 req/min | Redis counter |
| Por tenant | 60 req/min | Redis counter com tenant_id |
| Por usuario | 20 req/min | Redis counter com user_id |
| Por capability | Configuravel | Redis counter com capability |

### 8.2 Resposta ao Rate Limit

```json
{
  "error": "ai_rate_limited",
  "message": "Limite de requisicoes atingido. Tente novamente em instantes.",
  "retry_after": 30
}
```

HTTP Status: `429 Too Many Requests`

---

## 10. Health Checks

### 9.1 Provider Health

Verificacao periodica (a cada 60s) via scheduled job:

```
Para cada provider configurado:
  → Ping de health (chamada leve)
  → Registrar latencia
  → Atualizar status no Redis
```

### 9.2 Endpoints de Health

| Endpoint | Descricao |
|----------|-----------|
| `GET /platform/health/ai` | Status geral da IA |
| `GET /platform/health/ai/providers` | Status de cada provider |

Resposta:

```json
{
  "ai_enabled": true,
  "strategy": "hybrid",
  "providers": {
    "ollama": {
      "status": "healthy",
      "latency_ms": 45,
      "models_loaded": ["llama3.1:8b-q4", "nomic-embed-text"],
      "last_check": "2026-02-11T10:00:00Z"
    },
    "openai": {
      "status": "healthy",
      "latency_ms": 120,
      "last_check": "2026-02-11T10:00:00Z"
    },
    "azure_openai": {
      "status": "healthy",
      "latency_ms": 180,
      "last_check": "2026-02-11T10:00:00Z"
    }
  },
  "circuit_breaker": {
    "ollama": "closed",
    "openai": "closed",
    "azure_openai": "closed"
  }
}
```

---

## 11. Seguranca de Credenciais

| Regra | Implementacao |
|-------|---------------|
| API keys nunca em codigo | Somente via .env e config() |
| API keys nunca em logs | Sanitizacao automatica |
| API keys nunca em prompts | Validacao no PromptBuilder |
| Rotacao de chaves | Suporte a hot-reload via config:clear |
| Acesso ao config | Apenas AIProviderManager |

---

## 12. Testes

### 11.1 Estrategia de Testes

| Tipo | O que testar |
|------|-------------|
| Unit | Fallback logic, circuit breaker, cost calculation |
| Integration | Provider real (sandbox), rate limiting |
| Contract | Nossos interfaces retornam tipos esperados |
| Mock | Provider mockado para testes de fluxo completo |

### 11.2 Fake Provider para Testes

```
app/Infrastructure/AI/Testing/
├── FakeTextGeneration.php      → Retorna respostas predefinidas
├── FakeEmbeddingGeneration.php → Retorna vetores fixos
└── FakeClassification.php      → Retorna labels fixos
```

Registrados no container durante testes via `AITestServiceProvider`.

---

## 13. Anti-padroes

| Anti-padrao | Alternativa |
|-------------|-------------|
| Hardcode de provider no use case | Usar interface da Application |
| API key em codigo | Usar .env |
| Sem fallback | Sempre ter provider secundario |
| Sem tracking de custo | Registrar todo uso |
| Modelo unico para tudo | Roteamento hibrido: local para simples, cloud para complexo |
| Sem circuit breaker | Implementar para evitar cascading failure |
| Retry infinito | Circuit breaker + resposta degradada |
| Depender do Prism diretamente na Application | Manter contracts proprios |
