# Tenant Lifecycle — Condominium Events Manager API

## 1. Objetivo deste Documento

Definir o **ciclo de vida completo de um tenant**, desde sua criação até o encerramento definitivo, garantindo:

* Segurança
* Previsibilidade
* Automação
* Governança

---

## 2. O que é um Tenant

Um tenant representa:

* Um condomínio individual
* Ou uma administradora responsável por múltiplos condomínios

Cada tenant possui:

* Isolamento físico de dados
* Configuração própria
* Usuários próprios

---

## 3. Estados do Tenant

### 3.1 Prospect

* Tenant ainda não ativo
* Cadastro inicial iniciado
* Nenhum acesso ao sistema

---

### 3.2 Trial (Opcional)

* Tenant provisionado
* Acesso limitado
* Sem cobrança

---

### 3.3 Active

* Assinatura válida
* Acesso completo conforme plano
* Operação normal

---

### 3.4 Past Due

* Pagamento pendente
* Avisos automáticos
* Acesso degradado

---

### 3.5 Suspended

* Inadimplência prolongada ou violação de regras
* Acesso bloqueado
* Dados preservados

---

### 3.6 Cancelled

* Assinatura encerrada
* Acesso bloqueado
* Dados em retenção temporária

---

### 3.7 Archived

* Tenant definitivamente encerrado
* Dados anonimizados ou removidos

---

## 4. Transições de Estado

* Prospect → Trial / Active
* Trial → Active / Cancelled
* Active → Past Due → Suspended
* Suspended → Active / Cancelled
* Cancelled → Archived

Transições **nunca** devem ser manuais sem auditoria.

---

## 5. Provisionamento Técnico

Ao ativar um tenant:

* Criar database ou schema
* Aplicar migrations
* Criar usuário administrador do tenant
* Registrar configurações iniciais

---

## 6. Integração com Billing

* Estado do tenant depende da assinatura
* Billing **nunca altera dados do domínio**
* Apenas controla acesso

---

## 7. Encerramento e Retenção

* Retenção conforme LGPD
* Período configurável
* Exclusão definitiva após prazo legal

---

## 8. Auditoria

Todas as mudanças de estado devem:

* Ser registradas
* Ter origem identificável
* Ser imutáveis

---

## 9. Princípios Importantes

* Nenhum acesso sem tenant ativo
* Nenhuma exclusão imediata
* Nenhuma ação sem rastreabilidade

---

## 10. Fora de Escopo

* Regras de assinatura
* Cobrança detalhada
* Domínio de eventos
