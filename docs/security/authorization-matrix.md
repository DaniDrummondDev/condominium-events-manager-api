# Matriz de Autorização — Condominium Events Manager API

## 1. Visao Geral

O sistema de autorização do Condominium Events Manager API utiliza uma abordagem híbrida de **RBAC (Role-Based Access Control) + Policies** para garantir controle de acesso granular e contextual.

### Princípio Fundamental

> **Roles definem o perfil do usuário. Policies tomam a decisão final.**

As Roles determinam o conjunto base de permissões de um usuário, enquanto as Policies aplicam regras contextuais que refinam e restringem o acesso com base no estado atual do recurso, do tenant e do próprio usuário.

### Cadeia de Avaliação

Toda requisição protegida passa pela seguinte cadeia de verificação, nesta ordem:

```
Autenticado → Tenant Ativo → Assinatura Válida → Policy Permite → Contexto Válido
```

| Etapa                | Descrição                                                                 |
|----------------------|---------------------------------------------------------------------------|
| **Autenticado**      | O usuário possui um token válido (Sanctum/JWT).                           |
| **Tenant Ativo**     | O tenant ao qual o usuário pertence está com status ativo (não suspenso/cancelado). |
| **Assinatura Válida**| O tenant possui uma assinatura vigente e dentro do período de validade.    |
| **Policy Permite**   | A Policy do recurso autoriza a ação com base na role e no contexto.       |
| **Contexto Válido**  | Regras adicionais de negócio são satisfeitas (ex: feature flags, limites de uso). |

Se qualquer etapa falhar, a requisição é negada imediatamente e as etapas seguintes não são avaliadas.

---

## 2. Papéis (Roles)

### 2.1 Roles de Plataforma (Platform Roles)

Roles atribuídas a usuários que operam no nível da plataforma, fora do escopo de um tenant específico.

| Role           | Descrição                                                                                              |
|----------------|--------------------------------------------------------------------------------------------------------|
| `super_admin`  | Acesso total à plataforma. Gerencia tenants, planos, cobrança, usuários da plataforma e feature flags. |
| `admin`        | Gerencia tenants e cobrança. Não pode gerenciar usuários da plataforma nem criar/editar planos.        |
| `support`      | Acesso somente leitura aos dados dos tenants para suporte. Pode visualizar logs e métricas.            |

### 2.2 Roles de Tenant (Tenant Roles)

Roles atribuídas a usuários que operam dentro do escopo de um tenant (condomínio).

| Role             | Descrição                                                                                                                              |
|------------------|----------------------------------------------------------------------------------------------------------------------------------------|
| `sindico`        | Síndico do condomínio. Acesso total dentro do escopo do tenant. Gerencia unidades, espaços, reservas, governança, pessoas e comunicação.|
| `administradora` | Empresa administradora. Mesmas permissões do síndico, podendo gerenciar múltiplos condomínios vinculados.                              |
| `condomino`      | Condômino (morador). Pode fazer reservas, registrar convidados, visualizar dados próprios e criar chamados de suporte.                 |
| `funcionario`    | Funcionário (porteiro, segurança). Pode realizar check-in/check-out de convidados e prestadores. Visualiza reservas do dia.            |

---

## 3. Matriz de Autorização — Plataforma

Permissões para recursos gerenciados no nível da plataforma.

### 3.1 Tenants

| Ação                   | `super_admin` | `admin` | `support`        |
|------------------------|:-------------:|:-------:|:----------------:|
| Listar tenants         | ✅            | ✅      | ✅ (somente leitura) |
| Visualizar detalhes    | ✅            | ✅      | ✅               |
| Criar tenant           | ✅            | ✅      | ❌               |
| Suspender tenant       | ✅            | ✅      | ❌               |
| Cancelar tenant        | ✅            | ❌      | ❌               |
| Reativar tenant        | ✅            | ✅      | ❌               |
| Excluir tenant         | ✅            | ❌      | ❌               |

### 3.2 Planos (Plans)

| Ação                   | `super_admin` | `admin` | `support` |
|------------------------|:-------------:|:-------:|:---------:|
| Listar planos          | ✅            | ✅      | ✅        |
| Visualizar plano       | ✅            | ✅      | ✅        |
| Criar plano            | ✅            | ❌      | ❌        |
| Editar plano           | ✅            | ❌      | ❌        |
| Desativar plano        | ✅            | ❌      | ❌        |

### 3.3 Assinaturas e Cobrança (Subscriptions & Billing)

| Ação                      | `super_admin` | `admin` | `support` |
|---------------------------|:-------------:|:-------:|:---------:|
| Visualizar assinaturas    | ✅            | ✅      | ✅        |
| Alterar assinatura        | ✅            | ✅      | ❌        |
| Visualizar faturas        | ✅            | ✅      | ✅        |
| Emitir reembolso          | ✅            | ❌      | ❌        |

### 3.4 Feature Flags

| Ação                           | `super_admin` | `admin` | `support` |
|--------------------------------|:-------------:|:-------:|:---------:|
| Gerenciar flags globais        | ✅            | ❌      | ❌        |
| Override por tenant            | ✅            | ✅      | ❌        |
| Visualizar flags               | ✅            | ✅      | ✅        |

### 3.5 Usuários da Plataforma (Platform Users)

| Ação                              | `super_admin` | `admin` | `support` |
|-----------------------------------|:-------------:|:-------:|:---------:|
| Gerenciar usuários da plataforma  | ✅            | ❌      | ❌        |
| Visualizar usuários da plataforma | ✅            | ✅      | ❌        |

---

## 4. Matriz de Autorização — Tenant

Permissões para recursos gerenciados dentro do escopo de um tenant (condomínio).

### 4.1 Blocos (Blocks)

| Ação              | `sindico` | `administradora` | `condomino`          | `funcionario` |
|-------------------|:---------:|:-----------------:|:--------------------:|:-------------:|
| Criar bloco       | ✅        | ✅                | ❌                   | ❌            |
| Editar bloco      | ✅        | ✅                | ❌                   | ❌            |
| Visualizar blocos | ✅        | ✅                | ✅ (próprio bloco)   | ✅            |
| Desativar bloco   | ✅        | ✅                | ❌                   | ❌            |

### 4.2 Unidades (Units)

| Ação                     | `sindico` | `administradora` | `condomino`      | `funcionario` |
|--------------------------|:---------:|:-----------------:|:----------------:|:-------------:|
| Criar unidade            | ✅        | ✅                | ❌               | ❌            |
| Editar unidade           | ✅        | ✅                | ❌               | ❌            |
| Visualizar todas         | ✅        | ✅                | ❌               | ✅            |
| Visualizar própria       | ✅        | ✅                | ✅               | ❌            |
| Desativar unidade        | ✅        | ✅                | ❌               | ❌            |

### 4.3 Moradores (Residents)

| Ação                     | `sindico` | `administradora` | `condomino`      | `funcionario` |
|--------------------------|:---------:|:-----------------:|:----------------:|:-------------:|
| Convidar morador         | ✅        | ✅                | ❌               | ❌            |
| Visualizar todos         | ✅        | ✅                | ❌               | ✅            |
| Visualizar próprio perfil| ✅        | ✅                | ✅               | ❌            |
| Desativar morador        | ✅        | ✅                | ❌               | ❌            |
| Alterar role do morador  | ✅        | ✅                | ❌               | ❌            |

### 4.4 Espaços (Spaces)

| Ação                         | `sindico` | `administradora` | `condomino` | `funcionario` |
|------------------------------|:---------:|:-----------------:|:-----------:|:-------------:|
| Criar espaço                 | ✅        | ✅                | ❌          | ❌            |
| Editar espaço                | ✅        | ✅                | ❌          | ❌            |
| Visualizar espaços           | ✅        | ✅                | ✅          | ✅            |
| Bloquear espaço              | ✅        | ✅                | ❌          | ❌            |
| Desativar espaço             | ✅        | ✅                | ❌          | ❌            |
| Configurar regras de uso     | ✅        | ✅                | ❌          | ❌            |
| Definir disponibilidade      | ✅        | ✅                | ❌          | ❌            |

### 4.5 Reservas (Reservations)

| Ação                         | `sindico` | `administradora` | `condomino`              | `funcionario` |
|------------------------------|:---------:|:-----------------:|:------------------------:|:-------------:|
| Criar reserva               | ✅        | ✅                | ✅ (própria unidade)     | ❌            |
| Visualizar todas             | ✅        | ✅                | ❌                       | ✅            |
| Visualizar próprias          | ✅        | ✅                | ✅                       | ❌            |
| Aprovar reserva              | ✅        | ✅                | ❌                       | ❌            |
| Rejeitar reserva             | ✅        | ✅                | ❌                       | ❌            |
| Cancelar reserva             | ✅        | ✅                | ✅ (somente próprias)    | ❌            |
| Marcar como no-show          | ✅        | ✅                | ❌                       | ✅            |
| Concluir reserva             | ✅        | ✅                | ❌                       | ✅            |
| Visualizar horários disponíveis | ✅     | ✅                | ✅                       | ✅            |

### 4.6 Governança (Governance)

| Ação                         | `sindico` | `administradora` | `condomino`          | `funcionario` |
|------------------------------|:---------:|:-----------------:|:--------------------:|:-------------:|
| Registrar infração           | ✅        | ✅                | ❌                   | ❌            |
| Confirmar infração           | ✅        | ✅                | ❌                   | ❌            |
| Indeferir infração           | ✅        | ✅                | ❌                   | ❌            |
| Contestar infração           | ❌        | ❌                | ✅ (próprias)        | ❌            |
| Analisar contestação         | ✅        | ✅                | ❌                   | ❌            |
| Visualizar todas as infrações| ✅        | ✅                | ❌                   | ❌            |
| Visualizar próprias infrações| ❌        | ❌                | ✅                   | ❌            |
| Configurar penalidades       | ✅        | ✅                | ❌                   | ❌            |
| Revogar penalidade           | ✅        | ✅                | ❌                   | ❌            |

### 4.7 Pessoas (People)

| Ação                               | `sindico` | `administradora` | `condomino`                   | `funcionario` |
|------------------------------------|:---------:|:-----------------:|:-----------------------------:|:-------------:|
| Registrar convidado                | ✅        | ✅                | ✅ (própria reserva)          | ❌            |
| Check-in de convidado              | ✅        | ✅                | ❌                            | ✅            |
| Check-out de convidado             | ✅        | ✅                | ❌                            | ✅            |
| Negar acesso a convidado           | ✅        | ✅                | ❌                            | ✅            |
| Registrar prestador de serviço     | ✅        | ✅                | ❌                            | ❌            |
| Agendar visita de prestador        | ✅        | ✅                | ✅ (própria unidade)          | ❌            |
| Check-in/out de prestador          | ✅        | ✅                | ❌                            | ✅            |

### 4.8 Comunicação (Communication)

| Ação                         | `sindico` | `administradora` | `condomino`                    | `funcionario` |
|------------------------------|:---------:|:-----------------:|:------------------------------:|:-------------:|
| Publicar comunicado          | ✅        | ✅                | ❌                             | ❌            |
| Visualizar comunicados       | ✅        | ✅                | ✅ (direcionados a ele)        | ✅            |
| Marcar como lido             | ✅        | ✅                | ✅                             | ✅            |
| Arquivar comunicado          | ✅        | ✅                | ❌                             | ❌            |
| Criar chamado de suporte     | ✅        | ✅                | ✅                             | ✅            |
| Visualizar todos os chamados | ✅        | ✅                | ❌                             | ❌            |
| Visualizar próprios chamados | ✅        | ✅                | ✅                             | ✅            |
| Responder chamado            | ✅        | ✅                | ✅ (próprios)                  | ❌            |
| Resolver chamado             | ✅        | ✅                | ❌                             | ❌            |
| Mensagens internas           | ✅        | ✅                | ❌                             | ❌            |

### 4.9 IA (AI)

| Ação                              | `sindico`        | `administradora` | `condomino`                      | `funcionario` |
|-----------------------------------|:----------------:|:----------------:|:--------------------------------:|:-------------:|
| Chat com IA                       | ✅               | ✅               | ✅                               | ❌            |
| Ações da IA (mutações)            | ✅ (com confirmação) | ✅ (com confirmação) | ✅ (com confirmação, escopo próprio) | ❌        |

---

## 5. Policies — Regras Contextuais

As Policies vão além da verificação simples de role. Elas aplicam regras de negócio contextuais que determinam se uma ação é permitida com base no estado atual dos dados.

### 5.1 Regras do Condômino

| Regra                                                                                          | Contexto Avaliado                              |
|------------------------------------------------------------------------------------------------|------------------------------------------------|
| Condômino só pode criar reservas para unidades às quais pertence.                              | `$user->units->contains($unit)`                |
| Condômino só pode cancelar suas próprias reservas.                                             | `$reservation->user_id === $user->id`          |
| Condômino só pode contestar infrações vinculadas à sua unidade.                                | `$violation->unit_id === $user->unit_id`        |
| Condômino só pode registrar convidados para suas reservas confirmadas.                         | `$reservation->user_id === $user->id && $reservation->status === 'confirmed'` |
| Condômino só pode visualizar comunicados direcionados a ele (geral, seu bloco ou sua unidade). | `$announcement->target ∈ {all, user.block, user.unit}` |

### 5.2 Regras do Funcionário

| Regra                                                                                   | Contexto Avaliado                                   |
|-----------------------------------------------------------------------------------------|-----------------------------------------------------|
| Funcionário só pode realizar check-in/check-out de convidados em reservas do dia atual. | `$reservation->date === today()`                    |

### 5.3 Regras da IA

| Regra                                                                     | Contexto Avaliado                                          |
|---------------------------------------------------------------------------|------------------------------------------------------------|
| Ações da IA (mutações) requerem confirmação humana antes da execução.     | `$aiAction->confirmed === true`                            |
| Condômino só pode confirmar ações da IA dentro do seu próprio escopo.     | `$aiAction->scope ⊆ $user->scope`                         |

### 5.4 Regras de Escopo do Tenant

| Regra                                                                                          | Contexto Avaliado                                 |
|------------------------------------------------------------------------------------------------|---------------------------------------------------|
| Síndico só pode operar dentro do escopo do seu próprio tenant.                                 | `$resource->tenant_id === $user->tenant_id`       |
| Administradora pode operar em múltiplos condomínios, desde que vinculados à sua conta.         | `$user->condominiums->contains($condominium)`     |

---

## 6. Integração com Feature Flags

Algumas permissões dependem também de feature flags configuráveis por tenant. As feature flags são avaliadas **após** a verificação de role e **antes** da execução da Policy.

| Feature Flag                   | Tipo      | Descrição                                                             | Impacto na Autorização                                              |
|--------------------------------|-----------|-----------------------------------------------------------------------|---------------------------------------------------------------------|
| `can_use_ai`                   | `boolean` | Controla o acesso às funcionalidades de IA por tenant.                | Se desabilitada, nenhum usuário do tenant pode acessar chat ou ações de IA. |
| `max_reservations_per_month`   | `integer` | Limita a quantidade de reservas que podem ser criadas por mês.        | Bloqueia criação de novas reservas quando o limite é atingido.       |
| `can_use_support`              | `boolean` | Controla o acesso ao módulo de suporte (chamados).                    | Se desabilitada, nenhum usuário do tenant pode criar ou visualizar chamados. |

### Fluxo de Avaliação com Feature Flags

```
Role permite a ação?
  └─ NÃO → Acesso negado
  └─ SIM → Feature flag está habilitada?
              └─ NÃO → Acesso negado (403 com mensagem específica)
              └─ SIM → Limite de uso foi atingido?
                          └─ SIM → Acesso negado (429 com mensagem de limite)
                          └─ NÃO → Policy permite? → Acesso concedido/negado
```

---

## 7. Implementacao Tecnica (Laravel)

### 7.1 Estrutura de Policies

Cada recurso possui uma classe Policy dedicada no Laravel, organizada por escopo:

```
app/Interface/Http/Policies/
├── Platform/
│   ├── TenantPolicy.php
│   ├── PlanPolicy.php
│   ├── SubscriptionPolicy.php
│   ├── FeatureFlagPolicy.php
│   └── PlatformUserPolicy.php
└── Tenant/
    ├── BlockPolicy.php
    ├── UnitPolicy.php
    ├── ResidentPolicy.php
    ├── SpacePolicy.php
    ├── ReservationPolicy.php
    ├── GovernancePolicy.php
    ├── PersonPolicy.php
    ├── CommunicationPolicy.php
    └── AiPolicy.php
```

### 7.2 Assinatura das Policies

As Policies recebem o usuário autenticado e o recurso (quando aplicável):

```php
class ReservationPolicy
{
    public function viewAny(User $user): bool
    {
        // Verifica se a role permite listar reservas
    }

    public function view(User $user, Reservation $reservation): bool
    {
        // Verifica se o usuário pode ver esta reserva específica
    }

    public function create(User $user): bool
    {
        // Verifica se a role permite criar reservas
    }

    public function update(User $user, Reservation $reservation): bool
    {
        // Verifica se o usuário pode editar esta reserva
    }

    public function delete(User $user, Reservation $reservation): bool
    {
        // Verifica se o usuário pode cancelar esta reserva
    }
}
```

### 7.3 Cadeia de Middleware

Os middlewares são aplicados na seguinte ordem nas rotas protegidas:

```php
Route::middleware([
    'auth:sanctum',       // 1. Autenticação
    'tenant.active',      // 2. Tenant ativo
    'subscription.valid', // 3. Assinatura válida
    'feature.check',      // 4. Feature flags
    // 5. Policy é verificada no Controller via $this->authorize()
])->group(function () {
    // Rotas protegidas
});
```

### 7.4 Convenção de Métodos

Os métodos das Policies seguem a convenção padrão do Laravel:

| Método        | Ação                                      |
|---------------|-------------------------------------------|
| `viewAny`     | Listar recursos                           |
| `view`        | Visualizar um recurso específico          |
| `create`      | Criar um novo recurso                     |
| `update`      | Editar um recurso existente               |
| `delete`      | Excluir/desativar um recurso              |
| `restore`     | Restaurar um recurso desativado           |
| `forceDelete` | Exclusão permanente (quando aplicável)    |

Métodos customizados são adicionados conforme a necessidade do domínio (ex: `approve`, `reject`, `checkIn`, `checkOut`).

---

## 8. Status

**Documento ativo** — Atualizado conforme evolução das funcionalidades e regras de negócio do sistema.

| Campo              | Valor          |
|--------------------|----------------|
| Última atualização | 2025-02-10     |
| Versão             | 1.0.0          |
| Responsável        | Equipe Backend |
