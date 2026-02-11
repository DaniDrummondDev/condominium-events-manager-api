# External Service Abstraction Strategy — Condominium Events Manager API

## Objetivo

Definir a estrategia obrigatoria de abstracao para **todo servico externo** integrado ao sistema, garantindo que nenhuma dependencia externa polua o dominio, que providers possam ser trocados sem impacto, e que resiliencia e observabilidade sejam padroes.

Este documento se aplica a **todos** os servicos externos: IA, pagamento, email, SMS, push notifications, WhatsApp, e qualquer integracao futura.

---

## 1. Principio Fundamental

> **Nenhum servico externo e confiavel. Todos falham. A arquitetura deve assumir isso.**

Corolarios:
1. O dominio **nunca** referencia um provider especifico
2. A aplicacao define **interfaces** (contracts)
3. A infraestrutura fornece **implementacoes**
4. Toda integracao tem **fallback ou degradacao graceful**
5. Toda chamada externa e **observavel e auditavel**
6. Credenciais **nunca** existem em codigo

---

## 2. Arquitetura de Camadas

```
┌─────────────────────────────────────────┐
│  Domain Layer                           │
│  (zero dependencias externas)           │
│  Entities, Value Objects, Events        │
├─────────────────────────────────────────┤
│  Application Layer                      │
│  Interfaces/Contracts                   │
│  PaymentGatewayInterface                │
│  NotificationSenderInterface            │
│  TextGenerationInterface                │
│  EmbeddingGenerationInterface           │
│  ... (1 interface por capability)       │
├─────────────────────────────────────────┤
│  Infrastructure Layer                   │
│  Implementacoes concretas               │
│  StripePaymentGateway                   │
│  SendGridEmailSender                    │
│  OpenAITextGeneration                   │
│  ... (1+ impl por interface)            │
├─────────────────────────────────────────┤
│  Service Providers (Binding)            │
│  Registra qual impl esta ativa          │
│  Controlado por config + env vars       │
└─────────────────────────────────────────┘
```

### Regra de Ouro

- **Application Layer**: define `o que` precisa ser feito (interface)
- **Infrastructure Layer**: define `como` fazer (implementacao)
- **Domain Layer**: nao sabe que servicos externos existem

---

## 3. Inventario de Servicos Externos

### 3.1 Servicos Planejados

| Categoria | Servico | Interface (Application) | Providers Possiveis | Status |
|-----------|---------|------------------------|--------------------:|--------|
| **Pagamento** | Gateway de pagamento | `PaymentGatewayInterface` | Stripe, Pagarme, MercadoPago | Fase 3 |
| **Email** | Envio de email transacional | `EmailSenderInterface` | SMTP, SendGrid, Mailgun, SES | Fase 6 |
| **SMS** | Envio de SMS | `SmsSenderInterface` | Twilio, Vonage, SNS | Futuro |
| **Push** | Push notifications | `PushNotificationInterface` | Firebase, OneSignal | Futuro |
| **WhatsApp** | Mensagens WhatsApp | `WhatsAppSenderInterface` | WhatsApp Business API, Twilio | Futuro |
| **IA Texto** | Geracao de texto | `TextGenerationInterface` | OpenAI, Azure OpenAI, Ollama | Fase 5 |
| **IA Embeddings** | Geracao de embeddings | `EmbeddingGenerationInterface` | OpenAI, Azure OpenAI | Fase 5 |
| **IA Classificacao** | Classificacao de input | `ClassificationInterface` | OpenAI, Azure OpenAI | Fase 5 |

### 3.2 Servicos Internos (nao precisam de abstracao externa)

| Servico | Implementacao | Motivo |
|---------|---------------|--------|
| PostgreSQL | Eloquent | Banco principal, nao trocavel |
| Redis | Predis | Cache/fila, baixa probabilidade de troca |
| pgvector | PostgreSQL extension | Parte do banco |

Redis e PostgreSQL sao infra core, nao servicos externos substituiveis.

---

## 4. Padrao de Interface (Contract)

### 4.1 Regras para Definicao de Interfaces

1. **Uma interface por capability**, nao por provider
2. **Parametros tipados** — Value Objects do dominio, nunca strings livres
3. **Retorno tipado** — DTOs da Application, nunca respostas raw do provider
4. **Excecoes do dominio** — nunca propagar excecoes do SDK do provider
5. **Sem dependencia de framework** — interfaces nao usam Request, Response, Collection do Laravel

### 4.2 Exemplo: Payment Gateway

```php
// src/Application/Billing/Contracts/PaymentGatewayInterface.php
namespace Application\Billing\Contracts;

use Application\Billing\DTOs\ChargeRequestDTO;
use Application\Billing\DTOs\ChargeResultDTO;
use Application\Billing\DTOs\RefundRequestDTO;
use Application\Billing\DTOs\RefundResultDTO;
use Application\Billing\DTOs\SubscriptionDTO;

interface PaymentGatewayInterface
{
    public function charge(ChargeRequestDTO $request): ChargeResultDTO;
    public function refund(RefundRequestDTO $request): RefundResultDTO;
    public function createSubscription(SubscriptionDTO $subscription): SubscriptionDTO;
    public function cancelSubscription(string $subscriptionId): void;
    public function verifyWebhookSignature(string $payload, string $signature): bool;
}
```

### 4.3 Exemplo: Notification Sender

```php
// src/Application/Communication/Contracts/EmailSenderInterface.php
namespace Application\Communication\Contracts;

use Application\Communication\DTOs\EmailMessageDTO;
use Application\Communication\DTOs\SendResultDTO;

interface EmailSenderInterface
{
    public function send(EmailMessageDTO $message): SendResultDTO;
    public function sendBatch(array $messages): array;
}
```

### 4.4 Exemplo: AI Text Generation

```php
// src/Application/AI/Contracts/TextGenerationInterface.php
namespace Application\AI\Contracts;

use Application\AI\DTOs\TextPromptDTO;
use Application\AI\DTOs\TextResponseDTO;

interface TextGenerationInterface
{
    public function generate(TextPromptDTO $prompt): TextResponseDTO;
}
```

---

## 5. Padrao de Implementacao

### 5.1 Estrutura de Diretorio

```
app/Infrastructure/
├── Payment/
│   ├── Stripe/
│   │   ├── StripePaymentGateway.php
│   │   └── StripeWebhookHandler.php
│   ├── Pagarme/
│   │   └── PagarmePaymentGateway.php
│   └── PaymentGatewayFactory.php
├── Notification/
│   ├── Email/
│   │   ├── SmtpEmailSender.php
│   │   ├── SendGridEmailSender.php
│   │   └── MailgunEmailSender.php
│   ├── Sms/
│   │   └── TwilioSmsSender.php
│   └── Push/
│       └── FirebasePushNotification.php
├── AI/
│   ├── Providers/
│   │   ├── OpenAI/
│   │   │   ├── OpenAITextGeneration.php
│   │   │   └── OpenAIEmbeddingGeneration.php
│   │   └── AzureOpenAI/
│   │       ├── AzureTextGeneration.php
│   │       └── AzureEmbeddingGeneration.php
│   ├── AIProviderManager.php
│   └── AIProviderFallbackDecorator.php
└── Testing/
    ├── FakePaymentGateway.php
    ├── FakeEmailSender.php
    └── FakeTextGeneration.php
```

### 5.2 Regras para Implementacoes

1. **Isolar SDK do provider** — Classes do SDK nunca vazam para fora da implementacao
2. **Mapear excecoes** — Converter exceptions do SDK para exceptions da Application
3. **Log de chamada** — Toda chamada externa registra: provider, latencia, sucesso/falha
4. **Timeout** — Toda chamada tem timeout configuravel
5. **Retry** — Retries sao responsabilidade do decorator, nao da implementacao

### 5.3 Mapeamento de Excecoes

Cada categoria de servico define suas excecoes na Application:

```
src/Application/Billing/Exceptions/
├── PaymentFailedException.php
├── PaymentGatewayUnavailableException.php
├── InvalidWebhookSignatureException.php
└── SubscriptionNotFoundException.php

src/Application/Communication/Exceptions/
├── NotificationFailedException.php
├── ProviderUnavailableException.php
└── InvalidRecipientException.php

src/Application/AI/Exceptions/
├── AIProviderUnavailableException.php
├── AIRateLimitException.php
├── AIResponseMalformedException.php
└── AIContentFilteredException.php
```

A implementacao (Infrastructure) **nunca** propaga `StripeException`, `GuzzleException`, etc.

---

## 6. Resiliencia

### 6.1 Padrao Obrigatorio para Toda Integracao

```
Chamada ao Provider
  → Timeout (configuravel, default 30s)
  → Retry com backoff exponencial (max 3 tentativas)
  → Circuit Breaker (5 falhas → aberto por 60s)
  → Fallback provider (se existir)
  → Degradacao graceful (se nenhum provider disponivel)
```

### 6.2 Decorator de Resiliencia

Cada integracao usa um decorator que implementa:

| Componente | Responsabilidade |
|-----------|------------------|
| **Timeout** | Aborta chamada apos tempo limite |
| **Retry** | Exponential backoff: 1s, 2s, 4s |
| **Circuit Breaker** | Evita chamadas a provider sabidamente indisponivel |
| **Fallback** | Tenta provider alternativo |
| **Logging** | Registra toda tentativa com latencia e resultado |

### 6.3 Circuit Breaker

| Parametro | Valor Padrao | Configuravel |
|-----------|-------------|-------------|
| Falhas para abrir | 5 | Sim |
| Tempo aberto | 60 segundos | Sim |
| Requests em half-open | 1 | Sim |
| Reset apos sucesso | Imediato | Nao |
| Storage | Redis | Nao |

### 6.4 Fallback por Categoria

| Categoria | Primary | Fallback | Degradacao |
|-----------|---------|----------|-----------|
| Pagamento | Stripe | Pagarme | Retry later, notifica admin |
| Email | SendGrid | SMTP | Queue com retry |
| SMS | Twilio | Vonage | Notifica via email |
| IA Texto | OpenAI | Azure OpenAI | "Assistente indisponivel" |
| IA Embedding | OpenAI | Azure OpenAI | Desabilita RAG temporariamente |

### 6.5 Degradacao Graceful

Quando nenhum provider esta disponivel:

| Categoria | Comportamento |
|-----------|--------------|
| Pagamento | Rejeita operacao, notifica admin |
| Email | Enfileira para retry posterior (max 24h) |
| SMS | Fallback para email |
| Push | Fallback para email |
| IA | Sistema funciona sem IA, interface tradicional |

---

## 7. Configuracao

### 7.1 Padrao de Configuracao

Cada categoria de servico tem um arquivo de config dedicado:

```
config/
├── payment.php       → Gateway de pagamento
├── notification.php  → Email, SMS, Push, WhatsApp
├── ai.php            → Providers de IA (ja documentado)
```

### 7.2 Estrutura Padrao

```php
// config/payment.php
return [
    'default' => env('PAYMENT_GATEWAY', 'stripe'),
    'fallback' => env('PAYMENT_GATEWAY_FALLBACK', null),

    'gateways' => [
        'stripe' => [
            'api_key' => env('STRIPE_API_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'timeout' => 30,
        ],
        'pagarme' => [
            'api_key' => env('PAGARME_API_KEY'),
            'timeout' => 30,
        ],
    ],

    'resilience' => [
        'retry_attempts' => 3,
        'retry_delay_ms' => 1000,
        'circuit_breaker_threshold' => 5,
        'circuit_breaker_timeout' => 60,
    ],
];
```

### 7.3 Variaveis de Ambiente

| Variavel | Descricao |
|----------|-----------|
| `PAYMENT_GATEWAY` | Provider ativo de pagamento |
| `PAYMENT_GATEWAY_FALLBACK` | Provider fallback |
| `STRIPE_API_KEY` | Chave API do Stripe |
| `STRIPE_WEBHOOK_SECRET` | Secret para validacao de webhook |
| `NOTIFICATION_EMAIL_DRIVER` | Provider de email ativo |
| `NOTIFICATION_SMS_DRIVER` | Provider de SMS ativo |
| `AI_TEXT_PRIMARY` | Provider primario de IA (texto) |
| `AI_TEXT_FALLBACK` | Provider fallback de IA |

**Regra:** credenciais **nunca** em codigo, **nunca** em logs, **somente** via `.env`.

---

## 8. Binding no Container (Service Provider)

### 8.1 Padrao de Registro

```php
// app/Providers/ExternalServicesProvider.php

// Payment
$this->app->bind(
    PaymentGatewayInterface::class,
    fn () => PaymentGatewayFactory::create(config('payment'))
);

// Email
$this->app->bind(
    EmailSenderInterface::class,
    fn () => EmailSenderFactory::create(config('notification.email'))
);

// AI (ja em AuthServiceProvider)
$this->app->bind(
    TextGenerationInterface::class,
    fn () => $this->app->make(AIProviderManager::class)
);
```

### 8.2 Factory Pattern

Cada categoria usa uma Factory que:
1. Le a configuracao
2. Instancia o provider correto
3. Envolve com decorator de resiliencia
4. Retorna a interface

```php
class PaymentGatewayFactory
{
    public static function create(array $config): PaymentGatewayInterface
    {
        $primary = self::buildProvider($config['default'], $config['gateways']);
        $fallback = $config['fallback']
            ? self::buildProvider($config['fallback'], $config['gateways'])
            : null;

        return new ResilientServiceDecorator(
            primary: $primary,
            fallback: $fallback,
            config: $config['resilience'],
        );
    }
}
```

---

## 9. Observabilidade

### 9.1 Metricas Obrigatorias por Servico Externo

| Metrica | Descricao |
|---------|-----------|
| `external.{category}.request.count` | Total de chamadas |
| `external.{category}.request.latency` | Latencia P50/P95/P99 |
| `external.{category}.request.error_rate` | Taxa de erro |
| `external.{category}.circuit_breaker.state` | Estado do circuit breaker |
| `external.{category}.fallback.triggered` | Quantas vezes fallback foi usado |
| `external.{category}.cost.total` | Custo total (quando aplicavel) |

### 9.2 Log Padrao por Chamada

```json
{
  "event": "external.service.call",
  "category": "payment",
  "provider": "stripe",
  "operation": "charge",
  "tenant_id": "uuid",
  "latency_ms": 234,
  "status": "success",
  "trace_id": "uuid",
  "timestamp": "ISO8601"
}
```

### 9.3 Alertas

| Alerta | Condicao |
|--------|----------|
| Provider indisponivel | Circuit breaker aberto |
| Latencia alta | P95 > 2x normal |
| Taxa de erro alta | > 5% em janela de 5min |
| Custo anomalo | 3x acima da media diaria |
| Todas falhas (primary + fallback) | Degradacao ativa |

---

## 10. Webhooks

### 10.1 Padrao para Recebimento de Webhooks

Servicos como Stripe e WhatsApp enviam webhooks.

Padrao obrigatorio:

| Passo | Acao |
|-------|------|
| 1. Receber | Controller recebe payload raw |
| 2. Validar assinatura | Verificar que o webhook e autentico |
| 3. Idempotencia | Verificar se o evento ja foi processado (`gateway_events` table) |
| 4. Enfileirar | Despachar job para processamento assincrono |
| 5. Responder 200 | Responder rapidamente ao provider |
| 6. Processar | Job processa o evento com retry e auditoria |

### 10.2 Tabela de Idempotencia

```sql
CREATE TABLE gateway_events (
    id UUID PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    external_event_id VARCHAR(255) NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload_hash VARCHAR(64) NOT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL,

    CONSTRAINT uq_provider_event UNIQUE (provider, external_event_id)
);
```

---

## 11. Testes

### 11.1 Fake Implementations

Todo servico externo deve ter um Fake para testes:

```
app/Infrastructure/Testing/
├── FakePaymentGateway.php       → Simula charges, refunds
├── FakeEmailSender.php          → Captura emails enviados
├── FakeSmsSender.php            → Captura SMS enviados
├── FakeTextGeneration.php       → Retorna respostas predefinidas
├── FakeEmbeddingGeneration.php  → Retorna vetores fixos
└── FakeClassification.php       → Retorna labels fixos
```

### 11.2 Regras de Teste

| Regra | Motivo |
|-------|--------|
| **Nunca** chamar provider real em testes automatizados | Custo, flakiness, velocidade |
| Fakes registrados via `TestServiceProvider` | Binding automatico em test env |
| Contract tests validam que Fakes seguem interface | Garantia de compatibilidade |
| Integration tests com sandbox (opcional) | Para validacao manual |

### 11.3 Contract Tests

```php
// Cada interface tem um contract test que roda contra TODAS as implementacoes (incluindo Fakes):
// tests/Contract/PaymentGatewayContractTest.php
// tests/Contract/EmailSenderContractTest.php
// tests/Contract/TextGenerationContractTest.php
```

---

## 12. Seguranca

| Regra | Implementacao |
|-------|---------------|
| Credenciais somente em .env | Nunca em codigo, config files, ou logs |
| Rotacao de chaves | Hot-reload via config:clear |
| PII nunca enviado a providers sem anonimizacao | Scrubbing pipeline obrigatorio |
| Webhooks validados por assinatura | Provider-specific validation |
| Rate limiting por tenant | Previne abuso |
| Audit trail | Toda chamada externa logada |

---

## 13. Checklist para Nova Integracao

Antes de adicionar qualquer novo servico externo:

- [ ] Interface definida na Application Layer
- [ ] Implementacao na Infrastructure Layer
- [ ] Excecoes mapeadas (sem propagar SDK exceptions)
- [ ] Config file com env vars
- [ ] Fake para testes criado
- [ ] Contract test cobrindo interface
- [ ] Decorator de resiliencia (timeout, retry, circuit breaker)
- [ ] Fallback definido (ou degradacao graceful)
- [ ] Metricas de observabilidade
- [ ] Log estruturado por chamada
- [ ] Alertas configurados
- [ ] Credenciais somente em .env
- [ ] Webhook handler (se aplicavel) com idempotencia
- [ ] Rate limiting por tenant
- [ ] LGPD compliance verificado (PII, dados sensiveis)
- [ ] Binding registrado no Service Provider
- [ ] Documentacao atualizada

---

## 14. Anti-padroes

| Anti-padrao | Alternativa |
|-------------|-------------|
| Chamar SDK do provider no Use Case | Usar interface da Application |
| Propagar StripeException, GuzzleException | Mapear para exception da Application |
| Credenciais hardcoded | Usar .env + config() |
| Sem fallback | Sempre ter degradacao graceful |
| Sem timeout | Configurar timeout para toda chamada |
| Testar com provider real | Usar Fakes |
| Webhook sem validacao de assinatura | Sempre validar |
| Webhook sem idempotencia | Usar gateway_events table |
| Retry infinito | Circuit breaker + max attempts |
| Log com credenciais | Sanitizacao automatica |
