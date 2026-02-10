# data-retention-policy.md

## Objetivo

Definir regras claras de retenção, arquivamento e descarte de dados, em conformidade com a LGPD e requisitos operacionais do SaaS multi-tenant.

## Princípios

* **Finalidade**: dados mantidos apenas para fins legítimos.
* **Necessidade**: mínimo necessário.
* **Transparência**: políticas claras aos titulares.
* **Segurança**: proteção durante todo o ciclo de vida.

## Classificação de Dados

* **Pessoais**: identificação, contato, credenciais.
* **Sensíveis**: quando aplicável (tratamento restrito).
* **Operacionais**: logs, métricas.
* **Financeiros**: faturamento, pagamentos.

## Prazos de Retenção (Referência)

* **Conta do Usuário**: enquanto ativa + período legal.
* **Dados de Autenticação**: enquanto a conta existir.
* **Logs de Acesso**: 6–12 meses.
* **Logs de Auditoria**: 12–24 meses.
* **Financeiros/Fiscais**: conforme legislação aplicável.
* **Backups**: política de retenção escalonada.

## Arquivamento

* Dados inativos podem ser anonimizados ou pseudonimizados.
* Storage segregado e criptografado.

## Exclusão e Descarte

* Exclusão segura (soft delete + hard delete conforme prazo).
* Destruição criptográfica quando aplicável.
* Propagação da exclusão para terceiros.

## Direitos dos Titulares

* Acesso, correção, portabilidade e exclusão.
* Fluxos documentados e prazos.

## Multi-Tenant

* Isolamento lógico por tenant.
* Políticas aplicáveis por contrato.

## Terceiros e Processadores

* Contratos com cláusulas de retenção/descarte.
* Auditoria de conformidade.

## Revisão da Política

* Revisão periódica ou por mudança regulatória.
* Versionamento e histórico.

## Exceções

* Exceções documentadas e aprovadas.
