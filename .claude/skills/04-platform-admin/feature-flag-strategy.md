# feature-flags.md — Feature Flags e Controle de Recursos por Plano

## Objetivo

Definir a arquitetura e as regras de **feature flags** do SaaS, permitindo:

* Controle de funcionalidades por plano
* Ativação/desativação de recursos por tenant
* Liberação gradual de features
* Testes controlados (beta, rollout, etc.)

Este sistema deve ser:

* Simples
* Auditável
* Determinístico
* Seguro para ambiente multi-tenant

---

## Princípios Arquiteturais

1. **Feature flags são parte do domínio de billing**
2. **Todo recurso do sistema deve ser controlado por feature**
3. **Planos determinam features padrão**
4. **Tenants podem ter overrides controlados**
5. **Nunca confiar em validação apenas no front-end**

---

## Tipos de Feature Flags

### 1. Booleanas

Ativadas ou desativadas.

Exemplo:

* `ai_assistant`
* `priority_support`
* `advanced_reports`

### 2. Numéricas (limites)

Definem cotas.

Exemplo:

* `max_units`
* `max_reservations_per_month`
* `max_admin_users`

### 3. Enum (níveis de recurso)

Definem níveis de funcionalidade.

Exemplo:

* `ai_level: none | basic | advanced`
* `support_level: email | priority | dedicated`

---

## Modelo de Dados

### Tabela: features

Catálogo global de features.

Campos:

* id
* code (string única)
* name
* type (boolean, integer, enum)
* description
* created_at
* updated_at

Exemplos:

* ai_assistant
* max_units
* priority_support

---

### Tabela: plans

Já existente.

---

### Tabela: plan_features

Define features padrão de cada plano.

Campos:

* id
* plan_id
* feature_id
* value (string)
* created_at
* updated_at

Exemplos:

Plano Basic:

* ai_assistant = false
* max_units = 5

Plano Pro:

* ai_assistant = true
* max_units = 50

---

### Tabela: tenant_feature_overrides

Overrides específicos por tenant.

Campos:

* id
* tenant_id
* feature_id
* value
* reason (string)
* expires_at (nullable)
* created_by (admin id)
* created_at
* updated_at

Uso típico:

* upgrade manual
* benefício promocional
* liberação de beta

---

## Hierarquia de Resolução de Feature

A ordem de prioridade deve ser:

1. **Override do tenant**
2. **Feature do plano**
3. **Valor padrão da feature**

Fluxo:

resolveFeature(tenant, feature_code):

1. verificar override do tenant
2. se existir, retornar valor
3. senão, buscar valor do plano
4. se existir, retornar valor
5. senão, usar default da feature

---

## Serviço de Domínio

Criar um serviço:

FeatureResolver

Responsabilidades:

* Resolver features por tenant
* Cache de resultados
* Tipagem correta dos valores

Interface sugerida:

interface FeatureResolver
{
public function isEnabled(string $featureCode): bool;

```
public function getInt(string $featureCode): int;

public function getString(string $featureCode): string;
```

}

---

## Uso na Aplicação

### Exemplo: limitar unidades

$maxUnits = $featureResolver->getInt('max_units');

if ($currentUnits >= $maxUnits) {
throw new FeatureLimitExceededException('Unit limit reached');
}

---

### Exemplo: recurso de IA

if (! $featureResolver->isEnabled('ai_assistant')) {
throw new FeatureNotAvailableException();
}

---

## Cache de Features

Para performance:

* Cache por tenant
* TTL curto (ex: 5 minutos)
* Invalidar cache quando:

  * Plano muda
  * Override é criado/removido
  * Tenant é atualizado

Chave sugerida:

tenant:{tenant_id}:features

---

## API para Consulta de Features

Endpoint:

GET /api/v1/features

Retorna:

* Todas as features resolvidas do tenant

Exemplo de resposta:

{
"features": {
"ai_assistant": true,
"max_units": 50,
"priority_support": true
}
}

Uso:

* Front-end
* Aplicativos móveis
* Painéis administrativos

---

## Admin Platform: Gerenciamento de Overrides

Somente o **platform admin** pode:

* Criar override
* Editar override
* Remover override
* Definir expiração

Toda alteração deve:

* Ser auditada
* Registrar usuário responsável
* Registrar motivo

---

## Auditoria

Eventos auditáveis:

* tenant_feature_override_created
* tenant_feature_override_updated
* tenant_feature_override_removed
* plan_feature_updated

---

## Boas Práticas

1. Nunca hardcodar limites no código
2. Toda regra de negócio dependente de plano deve usar feature flag
3. Overrides devem ser exceção, não regra
4. Evitar features órfãs (sem uso)
5. Documentar cada feature no catálogo

---

## Anti-patterns Proibidos

❌ Verificar plano diretamente no código
if ($tenant->plan === 'pro') { ... }

❌ Lógica de limites no controller

❌ Duplicação de regras de plano em múltiplos serviços

---

## Testes

Deve haver:

### Testes unitários

* Resolução correta de features
* Prioridade de override

### Testes de integração

* Limites sendo respeitados
* Mudança de plano alterando features
