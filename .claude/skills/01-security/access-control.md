# Access Control — Condominium Events Manager API

## 1. Objetivo

Definir a **estratégia de autorização** da plataforma, garantindo controle fino de acesso dentro do contexto de cada tenant.

---

## 2. Princípios

* Autorização nunca baseada apenas em role
* Sempre contextual ao tenant
* Baseada em políticas explícitas

---

## 3. Modelo Adotado

### 3.1 RBAC + Policies

* Roles: identificação de perfil
* Policies: decisão final de acesso

---

## 4. Papéis Base

* Condômino
* Síndico
* Administradora
* Funcionário do Condomínio
* Admin da Plataforma

---

## 5. Avaliação de Permissão

Uma ação é autorizada somente se:

1. Usuário autenticado
2. Tenant ativo
3. Policy permite a ação
4. Contexto válido

---

## 6. Policies

* Escritas como código
* Sem lógica de UI
* Testáveis isoladamente

---

## 7. Multi-Tenancy

* Policies sempre recebem `tenant_id`
* Nenhuma policy pode acessar dados fora do tenant

---

## 8. Integração com Billing

* Tenant suspenso → todas as policies negam

---

## 9. Auditoria

* Toda decisão negativa relevante deve ser logada

---

## 10. Fora de Escopo

* Autenticação
* Auditoria detalhada
