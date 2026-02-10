# Skill — API Security (OWASP + SaaS API-first)

## Objetivo da Skill

Esta skill define **como o Claude deve projetar, documentar e validar a segurança da API** do projeto *Condominium Events Manager API*.

Ela complementa a skill **Security Architecture**, detalhando **controles específicos de API**, alinhados ao modelo **API-first**, multi-tenant e orientado a domínio.

---

## Princípios de Segurança de API

O Claude deve sempre assumir que:

* A API é o **produto principal**
* Todo endpoint é **potencialmente público**
* Clientes podem ser comprometidos

Princípios obrigatórios:

* **Explicit is safer than implicit**
* **Fail closed** (negar por padrão)
* **Contrato antes de implementação**

---

## OWASP API Security Top 10

O Claude deve considerar explicitamente os riscos do **OWASP API Security Top 10** ao projetar endpoints:

1. Broken Object Level Authorization (BOLA)
2. Broken Authentication
3. Broken Object Property Level Authorization (BOPLA)
4. Unrestricted Resource Consumption
5. Broken Function Level Authorization
6. Unrestricted Access to Sensitive Business Flows
7. Server Side Request Forgery (SSRF)
8. Security Misconfiguration
9. Improper Inventory Management
10. Unsafe Consumption of APIs

Cada novo endpoint **deve indicar quais riscos são mitigados**.

---

## Autenticação

### Diretrizes

* Autenticação **obrigatória** para qualquer endpoint não público
* Tokens de curta duração
* Refresh tokens protegidos
* Revogação possível

### Tipos de Autenticação

* JWT ou tokens opacos
* Separação clara entre:

  * Usuários finais
  * Funcionários do condomínio
  * Admins da plataforma
  * Serviços internos

---

## Autorização

### Estratégia

* Autorização **não é apenas RBAC**
* Combinação de:

  * Papéis (role)
  * Regras de domínio
  * Contexto do tenant

### Regras obrigatórias

* Toda autorização ocorre **antes** da execução do caso de uso
* Autorizações críticas podem viver no domínio
* Nunca confiar em IDs enviados pelo cliente

---

## Multi-tenancy na API

Regras estritas:

* Tenant nunca vem apenas no payload
* Tenant é resolvido a partir de:

  * Token
  * Subdomínio
  * Header assinado

### Proibições

* Endpoints globais sem validação de tenant
* Queries sem escopo explícito de tenant

---

## Design de Endpoints

Diretrizes obrigatórias:

* Endpoints orientados a **casos de uso**, não CRUD puro
* Verbos HTTP corretos
* Validação de request sempre presente

Exemplo correto:

* POST /spaces/{id}/reservations

Exemplo proibido:

* POST /reservations (sem contexto)

---

## Rate Limiting e Proteção de Abuso

O Claude deve sempre considerar:

* Rate limit por:

  * IP
  * Token
  * Tenant

* Limites mais restritos para:

  * Portaria
  * Endpoints de autenticação

---

## Validação de Entrada

Regras obrigatórias:

* Validação sintática
* Validação semântica
* Validação de domínio

Nunca confiar em:

* Campos opcionais
* Defaults implícitos

---

## Respostas e Erros

Diretrizes:

* Mensagens de erro **não devem vazar informação sensível**

* Diferenciar erros:

  * Autenticação
  * Autorização
  * Regra de negócio

* Códigos HTTP corretos

---

## Logs e Monitoramento

A API deve registrar:

* Tentativas de acesso negadas
* Ações sensíveis
* Padrões anômalos

Logs devem conter:

* Tenant
* Endpoint
* Ator
* Timestamp

---

## Testes de Segurança de API

O Claude **deve sempre gerar**:

* Testes de autorização (BOLA / BFLA)
* Testes de validação de request
* Testes de rate limit
* Testes de escopo de tenant
* Testes de contratos da API

Nenhum endpoint é considerado pronto sem testes.

---

## Integração com Outras Skills

Esta skill **herda**:

* `security-architecture.md`

E complementa:

* `lgpd-compliance.md`
* `billing-security.md`

---

## Resultado Esperado

Seguindo esta skill, o Claude atuará como:

* Arquiteto de API segura
* Revisor de contratos e fluxos sensíveis
* Guardião da superfície de ataque do produto

A API deve ser **segura por padrão**, previsível e auditável.
