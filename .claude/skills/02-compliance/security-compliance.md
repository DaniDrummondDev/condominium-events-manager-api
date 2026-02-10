# security-compliance.md

## Objetivo

Definir os controles de segurança técnicos e organizacionais do SaaS, alinhados a boas práticas (OWASP ASVS, ISO 27001/27701 como referência) e à LGPD, garantindo confidencialidade, integridade, disponibilidade e rastreabilidade.

## Escopo

Aplica-se a toda a plataforma multi-tenant (API, web, mobile, workers, infraestrutura, pipelines CI/CD e fornecedores).

## Princípios

* **Security by Design & Default**
* **Menor Privilégio (PoLP)**
* **Defesa em Profundidade**
* **Zero Trust (quando aplicável)**
* **Auditoria e Evidências**

## Governança e Responsabilidades

* **Responsável Técnico**: define padrões e aprova exceções.
* **Time de Desenvolvimento**: implementa controles e corrige vulnerabilidades.
* **Operações/SRE**: monitora, responde a incidentes e mantém SLAs.
* **Fornecedores**: cumprem requisitos contratuais de segurança.

## Identidade, Autenticação e Autorização

* Autenticação forte (hash seguro de senhas, MFA opcional por tenant).
* OAuth2/OpenID Connect para integrações.
* RBAC/ABAC por tenant, com escopos explícitos.
* Rotação e revogação de tokens.

## Gestão de Segredos

* Segredos fora do código (vault/secret manager).
* Rotação periódica.
* Acesso auditável.

## Criptografia

* **Em trânsito**: TLS 1.2+.
* **Em repouso**: criptografia em banco, backups e storage.
* Chaves gerenciadas (KMS), rotação e segregação por ambiente.

## Segurança de Aplicação

* Validação de entrada/saída (server-side).
* Proteção contra OWASP Top 10 (XSS, CSRF, SQLi, SSRF, etc.).
* Rate limiting, WAF e proteção anti-bot.
* Headers de segurança.

## Segurança de API

* Versionamento e contratos.
* Autorização por escopo.
* Throttling e quotas por tenant.
* Logs estruturados e correlação.

## Infraestrutura e Rede

* Ambientes segregados (dev/stg/prod).
* Princípio do menor privilégio em IAM.
* Hardening de containers/VMs.
* Backups testados e isolados.

## Monitoramento e Logs

* Logs imutáveis e centralizados.
* Alertas de segurança (SIEM).
* Retenção conforme política.

## Gestão de Vulnerabilidades

* SAST/DAST/Dependabot.
* Patching contínuo.
* Pentests periódicos.

## Incidentes de Segurança

* Plano de resposta (IRP).
* Comunicação e escalonamento.
* Registro de evidências.
* Notificação conforme LGPD quando aplicável.

## Continuidade e Recuperação

* DRP com RPO/RTO definidos.
* Testes regulares.

## Conformidade e Auditoria

* Evidências versionadas.
* Revisões periódicas.
* Treinamento de segurança.

## Exceções

* Documentadas, aprovadas e com prazo.
