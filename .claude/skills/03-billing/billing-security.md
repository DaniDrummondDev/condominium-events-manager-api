# billing-security.md — Segurança de Cobrança e Pagamentos

## Objetivo

Estabelecer controles técnicos, operacionais e de conformidade para **proteção do fluxo de cobrança**, **dados financeiros** e **processamento de pagamentos** em aplicações SaaS. Este documento complementa `api-security.md`, `security-architecture.md` e `lgpd-compliance.md`.

## Escopo

* Assinaturas recorrentes
* Faturas, boletos, PIX e cartões
* Integração com gateways de pagamento
* Webhooks financeiros
* Reembolsos, estornos e chargebacks

## Princípios de Segurança

1. **Nunca armazenar dados sensíveis de cartão** (PAN, CVV, trilha magnética)
2. **Tokenização obrigatória** para meios de pagamento
3. **Menor privilégio** em acessos financeiros
4. **Imutabilidade de registros financeiros**
5. **Auditabilidade completa** de eventos de billing

## Conformidade e Normas

* **PCI DSS** (quando aplicável via gateway)
* **LGPD** — dados pessoais vinculados a pagamentos
* **BACEN / FEBRABAN** (PIX e boletos, quando aplicável)
* **SOX-like controls** para trilhas de auditoria

## Arquitetura Recomendada

* Gateways externos certificados (ex: Stripe, Adyen, Mercado Pago)
* Backend **nunca** toca dados brutos de cartão
* Comunicação via HTTPS + TLS 1.2+
* Webhooks em endpoint dedicado e isolado

```
Cliente → Gateway de Pagamento
         ↘ Token
Backend ← Webhook Assinado
```

## Integração com Gateways

### Regras

* Usar **SDK oficial** do gateway
* Validar **assinatura de webhook** (HMAC ou chave pública)
* Verificar **idempotência** por `event_id`
* Rejeitar webhooks sem assinatura válida

### Webhooks

* Endpoint exclusivo (ex: `/webhooks/billing`)
* IP allowlist quando suportado
* Processamento assíncrono (fila)
* Logs imutáveis

## Proteção de Dados Financeiros

### Armazenamento

* Apenas:

  * Token de pagamento
  * Últimos 4 dígitos do cartão
  * Bandeira
  * Status da assinatura

### Criptografia

* **Em repouso:** AES-256
* **Em trânsito:** TLS
* Segredos via **Secrets Manager**

## Controle de Acesso

* Billing isolado por **bounded context**
* RBAC:

  * `billing.read`
  * `billing.manage`
  * `billing.refund`
* MFA obrigatório para ações críticas

## Prevenção de Fraudes

* Rate limit em tentativas de pagamento
* Detecção de padrões anômalos
* Integração antifraude do gateway
* Bloqueio progressivo por falhas consecutivas

## Reembolsos e Estornos

* Fluxo separado de cobrança
* Confirmação em dois fatores
* Registro imutável do motivo
* Notificação automática ao cliente

## Logs e Auditoria

* Eventos obrigatórios:

  * Criação de cobrança
  * Pagamento aprovado/negado
  * Webhook recebido
  * Reembolso / estorno
* Logs **append-only**
* Retenção mínima: 5 anos

## Testes de Segurança

* Testes de webhook inválido
* Simulação de replay attack
* Testes de concorrência
* Auditoria de permissões

## Monitoramento e Alertas

* Falhas de pagamento em massa
* Webhooks rejeitados
* Acesso administrativo fora do padrão
* Divergência entre gateway e banco de dados

## Checklist de Implementação

* [ ] Gateway certificado
* [ ] Webhooks assinados e validados
* [ ] Tokenização ativa
* [ ] RBAC aplicado
* [ ] Logs imutáveis
* [ ] Monitoramento configurado

## Anti‑Padrões (Proibidos)

* ❌ Armazenar dados completos de cartão
* ❌ Processar pagamento no frontend sem gateway
* ❌ Webhooks sem validação
* ❌ Logs financeiros editáveis

---

**Status:** Obrigatório
**Aplicação:** Backend / API / Billing Service
