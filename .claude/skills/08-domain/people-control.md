# People Control — Controle de Convidados e Prestadores de Serviço
## FASE 8 — Core Domain
**Condominium Events Manager API**

---

## 1. Objetivo

Definir a arquitetura, regras e responsabilidades do **controle de pessoas** (convidados e prestadores de serviço) no condomínio, garantindo:

- Registro e validação de convidados por reserva
- Cadastro e controle de prestadores de serviço
- Associação obrigatória com eventos e reservas
- Rastreabilidade completa para segurança
- Conformidade com LGPD
- Isolamento total por tenant

O controle de pessoas é essencial para **segurança, rastreabilidade e governança** do condomínio.

---

## 2. Dependências

Esta skill depende das seguintes skills:

- `reservation-system.md` — reservas vinculam convidados e prestadores
- `spaces-management.md` — regras de capacidade
- `governance-rules.md` — infrações relacionadas a pessoas
- `access-control.md` — permissões por papel
- `audit-logging.md` — rastreabilidade
- `lgpd-compliance.md` — dados pessoais
- `data-retention-policy.md` — retenção de dados pessoais
- `notification-strategy.md` — notificações de acesso

---

## 3. Princípios Arquiteturais

### 3.1 Pessoa é vinculada a contexto

Convidados e prestadores **sempre** estão vinculados a:

- Uma reserva
- Um evento
- Uma autorização de acesso

Não existem convidados ou prestadores "soltos" no sistema.

### 3.2 Dados pessoais são tratados com LGPD

- Minimização de coleta
- Finalidade explícita
- Retenção controlada
- Anonimização após período

### 3.3 Controle é do domínio, não da portaria

O sistema registra e valida. A portaria **consulta e confirma**.

---

## 4. Convidados (Guests)

### 4.1 Entidade: Guest

Campos conceituais:

- `id`
- `tenant_id`
- `reservation_id`
- `name`
- `document` (CPF ou RG — opcional, configurável)
- `phone` (opcional)
- `vehicle_plate` (opcional)
- `relationship` (amigo, familiar, outro)
- `status` (expected, checked_in, checked_out, no_show)
- `checked_in_at`
- `checked_out_at`
- `registered_by` (user_id)
- `created_at`

### 4.2 Estados do Convidado

| Estado | Descrição |
|--------|-----------|
| `expected` | Registrado, aguardando chegada |
| `checked_in` | Presente no condomínio |
| `checked_out` | Saiu do condomínio |
| `no_show` | Não compareceu |

### 4.3 Regras

- Convidado deve estar vinculado a uma reserva ativa
- Número de convidados limitado pela capacidade do espaço
- Registro pode ser obrigatório ou opcional (configurável por espaço)
- Condômino registra convidados antes do evento
- Portaria faz check-in/check-out

### 4.4 Verificação de Capacidade

Ao registrar convidado:

1. Verificar reserva ativa
2. Verificar `max_guests` do espaço
3. Verificar `expected_guests_count` da reserva
4. Se limite atingido → rejeitar registro

---

## 5. Prestadores de Serviço (Service Providers)

### 5.1 Entidade: ServiceProvider

Representa um prestador de serviço cadastrado.

Campos conceituais:

- `id`
- `tenant_id`
- `name`
- `company_name` (nullable)
- `document` (CPF/CNPJ)
- `phone`
- `email` (nullable)
- `service_type` (buffet, limpeza, decoração, DJ, segurança, outro)
- `status` (active, inactive, blocked)
- `notes`
- `created_by`
- `created_at`
- `updated_at`

### 5.2 Cadastro Prévio

Prestadores podem ser **pré-cadastrados** no condomínio para reuso.

- Cadastro feito por síndico ou condômino
- Aprovação do síndico pode ser requerida
- Prestador bloqueado não pode ser vinculado a reservas

### 5.3 Vinculação com Reserva: ReservationServiceProvider

Campos conceituais:

- `id`
- `reservation_id`
- `service_provider_id`
- `service_description`
- `arrival_time`
- `departure_time`
- `status` (expected, checked_in, checked_out)
- `checked_in_at`
- `checked_out_at`
- `created_at`

### 5.4 Regras

- Prestador deve estar ativo no cadastro
- Prestador vinculado a reserva ativa
- Portaria verifica prestador no check-in
- Prestador sem vínculo com reserva = acesso negado

---

## 6. Portaria e Validação de Acesso

### 6.1 Fluxo de Validação — Convidado

1. Convidado chega ao condomínio
2. Portaria busca por nome/documento no sistema
3. Sistema verifica:
   - Reserva ativa para o período atual
   - Convidado na lista da reserva
   - Status `expected`
4. Portaria realiza check-in
5. Status muda para `checked_in`

### 6.2 Fluxo de Validação — Prestador

1. Prestador chega ao condomínio
2. Portaria busca por nome/documento/empresa
3. Sistema verifica:
   - Prestador ativo
   - Vinculação com reserva ativa no período
4. Portaria realiza check-in
5. Status muda para `checked_in`

### 6.3 Regras

- Portaria **não pode** criar reservas
- Portaria **pode** fazer check-in/check-out
- Acesso negado é registrado (tentativas de acesso sem vínculo)

---

## 7. Dados Pessoais e LGPD

### 7.1 Classificação

| Dado | Classificação | Obrigatório |
|------|---------------|-------------|
| Nome | Pessoal | Sim |
| Documento (CPF/RG) | Pessoal | Configurável |
| Telefone | Pessoal | Não |
| Placa do veículo | Pessoal | Não |
| E-mail | Pessoal | Não |

### 7.2 Regras de Retenção

- Dados de convidados: retidos enquanto reserva ativa + período legal
- Após período: anonimização automática
- Dados de prestadores: retidos enquanto cadastro ativo
- Após inativação: anonimização conforme política

### 7.3 Base Legal

- **Convidados**: legítimo interesse (segurança do condomínio)
- **Prestadores**: execução de contrato + legítimo interesse

### 7.4 Minimização

- Coletar apenas o necessário para identificação e segurança
- Não coletar dados que não serão usados
- Dados opcionais são genuinamente opcionais

---

## 8. Eventos de Domínio

- `GuestRegistered`
- `GuestCheckedIn`
- `GuestCheckedOut`
- `GuestNoShow`
- `ServiceProviderRegistered`
- `ServiceProviderBlocked`
- `ServiceProviderCheckedIn`
- `ServiceProviderCheckedOut`
- `AccessDenied` (tentativa de acesso sem vínculo)

---

## 9. Isolamento por Tenant

Regras obrigatórias:

- Convidados pertencem a um tenant
- Prestadores pertencem a um tenant
- Nenhum compartilhamento entre tenants
- Queries sempre escopadas por tenant_id
- Prestador de um tenant não é visível em outro

---

## 10. Auditoria

Eventos auditáveis:

- Registro de convidado
- Check-in / check-out
- Registro de prestador
- Bloqueio de prestador
- Tentativa de acesso negada
- Alteração de dados pessoais

---

## 11. Permissões

| Ação | Papéis permitidos |
|------|-------------------|
| Registrar convidados na reserva | Condômino (própria reserva) |
| Fazer check-in/check-out | Funcionário (Portaria) |
| Cadastrar prestador | Condômino, Síndico |
| Aprovar prestador | Síndico |
| Bloquear prestador | Síndico |
| Visualizar convidados de reserva | Condômino (própria), Síndico, Portaria |
| Visualizar todos os prestadores | Síndico, Administradora |

---

## 12. Integração com IA

A IA pode:

- Auxiliar condômino a registrar convidados via conversa
- Sugerir prestadores previamente cadastrados
- Facilitar o fluxo de preparação do evento

A IA **não pode**:

- Fazer check-in/check-out automaticamente
- Aprovar prestadores sem confirmação humana
- Acessar dados pessoais para outros fins

---

## 13. Testes

### Testes de Domínio

- Registro de convidado com verificação de capacidade
- Vinculação de prestador com reserva
- Check-in/check-out
- Bloqueio de prestador impedindo vinculação

### Testes de Integração

- Fluxo completo: reserva → convidados → check-in → check-out
- Isolamento por tenant
- Retenção e anonimização

### Testes de API

- Contratos de registro e consulta
- Permissões por papel
- Busca pela portaria

---

## 14. Anti-Padrões

- Convidado sem vínculo com reserva
- Prestador sem cadastro prévio acessando condomínio
- Dados pessoais coletados sem finalidade
- Check-in sem auditoria
- Portaria com acesso a funções administrativas
- Dados pessoais retidos indefinidamente

---

## 15. O que esta skill NÃO cobre

- Fluxo de reservas (→ `reservation-system.md`)
- Configuração de espaços (→ `spaces-management.md`)
- Penalidades por excesso de convidados (→ `governance-rules.md`)
- Marketplace de prestadores (escopo futuro)

---

## 16. Status

Documento **OBRIGATÓRIO** para implementação do controle de pessoas.

Sem controle de pessoas, o condomínio não tem **segurança nem rastreabilidade** sobre quem acessa os espaços.
