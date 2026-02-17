# Skill: NFSe Integration (Nota Fiscal de Serviço Eletrônica)

## Contexto

A plataforma SaaS emite NFSe automaticamente para condomínios clientes após pagamento de faturas de assinatura. A emissão é 100% assíncrona via queue.

## Provider

- **Focus NFe** — API REST/JSON para emissão de NFSe
- Ambiente de homologação (sandbox) gratuito
- Produção requer contrato ativo
- Configuração via `config/fiscal.php` e variáveis de ambiente

## Arquitetura

### Fluxo Assíncrono

```
Invoice.paid → InvoicePaid Event
  → GenerateNFSeOnInvoicePaid (Listener)
    → GenerateNFSeJob (Queue: fiscal)
      → GenerateNFSe (UseCase)
        → NFSeProviderInterface.emit()
        → NFSeDocumentRepository.save()
      → Se authorized: SendNFSeByEmailJob (Queue: notifications)

Focus NFe Webhook → FiscalWebhookController
  → HandleNFSeWebhook (UseCase)
    → Atualiza status (authorized/denied/cancelled)
    → Se authorized: SendNFSeByEmailJob
```

### State Machine

```
draft → processing → authorized → cancelled
                  → denied → draft (retry)
```

## Camadas

### Domain (src/Domain/Billing/)
- `Entities/NFSeDocument.php` — Aggregate com state machine e domain events
- `Enums/NFSeStatus.php` — draft, processing, authorized, denied, cancelled
- `Events/NFSe*.php` — NFSeRequested, NFSeAuthorized, NFSeDenied, NFSeCancelled
- `ValueObjects/Cnpj.php` — CNPJ com validação de dígitos verificadores

### Application (src/Application/Billing/)
- `Contracts/NFSeProviderInterface.php` — Interface do serviço externo
- `Contracts/NFSeDocumentRepositoryInterface.php` — Persistência
- `DTOs/NFSe*.php` — NFSeRequestDTO, NFSeResultDTO, NFSeWebhookDTO, NFSeDocumentDTO
- `UseCases/GenerateNFSe.php` — Gera e envia NFSe (idempotente)
- `UseCases/HandleNFSeWebhook.php` — Processa callback
- `UseCases/CancelNFSe.php` — Cancela NFSe autorizada

### Infrastructure (app/Infrastructure/)
- `Gateways/Fiscal/FocusNFeProvider.php` — Implementação Focus NFe
- `Gateways/Fiscal/FakeNFSeProvider.php` — Fake para testes
- `Jobs/Billing/GenerateNFSeJob.php` — Job idempotente (queue: fiscal)
- `Jobs/Billing/SendNFSeByEmailJob.php` — Envio por e-mail (queue: notifications)
- `Events/Handlers/Billing/GenerateNFSeOnInvoicePaid.php` — Listener

### Interface (app/Interface/Http/)
- `Controllers/Platform/NFSeController.php` — CRUD endpoints
- `Controllers/Webhook/FiscalWebhookController.php` — Webhook
- `Resources/Platform/NFSeDocumentResource.php` — API response

## API Endpoints

```
GET    /platform/nfse                    — Listar NFSe (filtro: tenant_id)
GET    /platform/nfse/{id}               — Detalhes
POST   /platform/nfse/{id}/cancel        — Cancelar (body: reason)
POST   /platform/nfse/{id}/retry         — Retentar (denied → draft → queue)
GET    /platform/nfse/{id}/pdf           — URL do PDF
POST   /platform/webhooks/fiscal         — Webhook Focus NFe
```

## Banco de Dados

- Tabela `nfse_documents` (Platform DB)
- Colunas fiscais em `tenants`: cnpj (UNIQUE), razao_social, endereco, email_fiscal

## Regras de Negócio

1. NFSe é gerada automaticamente após pagamento (configurável via `FISCAL_AUTO_EMIT`)
2. Idempotência: uma NFSe por invoice (`idempotency_key = nfse:{invoice_id}`)
3. CNPJ do tenant deve ser válido (dígitos verificadores) e único
4. NFSe autorizada pode ser cancelada (com justificativa obrigatória, min 10 chars)
5. NFSe negada pode ser retentada (reset → draft → reprocessamento)
6. Jobs com retry + backoff exponencial
7. E-mail automático após autorização

## Configuração

```env
FISCAL_DRIVER=fake                    # fake | focus_nfe
FISCAL_AUTO_EMIT=true
FISCAL_EMITTER_CNPJ=11222333000181
FISCAL_EMITTER_RAZAO_SOCIAL="Empresa SaaS Ltda"
FISCAL_EMITTER_IM=12345
FISCAL_EMITTER_COD_MUNICIPIO=3550308
FISCAL_EMITTER_UF=SP
FISCAL_EMITTER_CNAE=6311900
FISCAL_EMITTER_COD_SERVICO=0107
FISCAL_EMITTER_ISS_RATE=5.00
FISCAL_EMITTER_REGIME=simples_nacional
FOCUS_NFE_TOKEN=token_sandbox
FOCUS_NFE_ENV=homologation
FOCUS_NFE_WEBHOOK_SECRET=secret
FOCUS_NFE_BASE_URL=https://homologacao.focusnfe.com.br
```

## Testes

- `tests/Unit/Domain/Billing/CnpjTest.php` — 16 testes (dígitos verificadores)
- `tests/Unit/Domain/Billing/NFSeStatusTest.php` — 13 testes (state machine)
- `tests/Unit/Domain/Billing/NFSeDocumentTest.php` — 22 testes (entity, lifecycle)
