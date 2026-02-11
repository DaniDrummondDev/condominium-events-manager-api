# Tooling Security Guidelines — Condominium Events Manager API

## Objetivo

Estabelecer regras obrigatorias para uso seguro de tooling por sistemas de IA em ambiente SaaS multi-tenant.

Estas guidelines complementam as definicoes de seguranca em `ai-data-governance` e `authorization-matrix`.

---

## 1. Isolamento de Tenant

- Nenhuma tool recebe `tenant_id` como parametro.
- Tenant e **sempre** inferido a partir da sessao/token JWT.
- Queries internas sempre incluem `WHERE tenant_id = ?` implicito.
- Embeddings, logs e memorias sao escopados por tenant.
- Testes devem validar que uma tool nunca retorna dados de outro tenant.

**Violacao de isolamento e falha critica e deve bloquear a execucao.**

---

## 2. Autorizacao no Backend

- A IA **nunca** decide permissoes — apenas propoe acoes.
- Toda tool executa politicas de acesso no backend (Policy layer).
- O token JWT do usuario e propagado para o Use Case.
- Role e permissoes sao verificados antes da execucao, nao apos.
- Condomino so acessa recursos da propria unidade/reservas.

Fluxo obrigatorio:

```
IA propoe tool → Backend extrai role do JWT → Policy verifica permissao
→ Regra de negocio valida → Executa (ou rejeita com motivo)
```

---

## 3. Superficie Minima de Entrada

- Parametros sempre **tipados**: uuid, date, time, int, enum.
- Enums definidos no catalogo — nunca aceitar valores arbitrarios.
- Strings livres limitadas a campos especificos (observacao, motivo) com:
  - Tamanho maximo (ex: 500 caracteres)
  - Sanitizacao contra injection (HTML, SQL, prompt injection)
- Nunca aceitar:
  - Filtros SQL ou expressoes regulares
  - JSON arbitrario como parametro
  - URLs ou paths de arquivo
  - Codigo executavel

---

## 4. Sanitizacao de Input da IA

Toda string originada da IA (gerada pelo modelo) deve passar por sanitizacao antes de ser usada:

| Tipo | Regra |
|------|-------|
| **Nomes/titulos** | Strip HTML, limitar a 255 chars, rejeitar caracteres de controle |
| **Descricoes/observacoes** | Strip HTML, limitar a 2000 chars, escapar para contexto de uso |
| **IDs** | Validar formato UUID estrito |
| **Datas/horarios** | Validar formato ISO 8601 |
| **Enums** | Validar contra lista permitida |

Regra geral: **tratar input da IA com a mesma desconfianca de input de usuario.**

---

## 5. Controle em Tools Read-only

Tools de consulta tambem precisam de controle:

| Controle | Regra |
|----------|-------|
| **Limite de volume** | Maximo de resultados por chamada (ex: 100 itens) |
| **Limite temporal** | Periodo maximo de consulta (ex: 90 dias para reservas, 365 para estatisticas) |
| **Rate limit por usuario** | Definido por tool no catalogo (10-30 req/min) |
| **Rate limit por tenant** | Limite global por tenant para evitar abuso (ex: 500 req/min total) |
| **Paginacao** | Tools de listagem devem suportar paginacao, nunca retornar tudo |

---

## 6. Controle em Tools Transacionais

| Controle | Regra |
|----------|-------|
| **Confirmacao humana** | Obrigatoria — sem excecoes |
| **Timeout** | Maximo de 30 segundos para execucao |
| **Idempotencia** | Tools transacionais devem ser idempotentes (mesmo input = mesmo resultado) |
| **Replay protection** | Token de confirmacao unico por acao (nonce), expira em 5 minutos |
| **Rate limit** | Mais restritivo que read-only (5-10 req/min) |
| **Atomicidade** | Nenhuma acao parcial e persistida — tudo ou nada |

---

## 7. Auditoria Obrigatoria

Toda execucao de tool deve registrar em `ai_action_logs`:

| Campo | Descricao |
|-------|-----------|
| `id` | UUID do registro |
| `tenant_id` | Tenant escopado |
| `user_id` | Usuario que solicitou |
| `tool_name` | Nome da tool executada |
| `tool_version` | Versao da tool |
| `parameters_hash` | Hash SHA-256 dos parametros (nunca parametros em texto puro) |
| `confirmed` | Se o usuario confirmou a acao |
| `executed` | Se a acao foi executada com sucesso |
| `error_code` | Codigo de erro se falhou |
| `ip_address` | IP do usuario |
| `trace_id` | Correlation ID para tracing distribuido |
| `created_at` | Timestamp |

Eventos auditaveis:
- `ai.tool.proposed` — IA sugeriu tool
- `ai.tool.confirmed` — usuario confirmou
- `ai.tool.rejected` — usuario rejeitou
- `ai.tool.executed` — execucao bem-sucedida
- `ai.tool.failed` — execucao falhou
- `ai.tool.rate_limited` — bloqueado por rate limit

---

## 8. Fail-safe por Padrao

- Falha ou ambiguidade **bloqueia execucao**.
- Nenhuma acao parcial e persistida.
- Se o provider de IA falhar, o sistema funciona normalmente via interface tradicional.
- Mensagem padrao: "Assistente indisponivel no momento. Utilize o menu principal."
- Erro na IA nunca propaga para o dominio — catch boundary obrigatorio.

Principio: **na duvida, nao executa.**

---

## 9. Segregacao por Agentes

Cada agente de IA possui acesso apenas ao conjunto minimo de tools necessario:

| Agente | Tools Permitidas | Justificativa |
|--------|-----------------|---------------|
| **Assistente Conversacional** | Contexto + Consulta + Validacao + Suporte | Interacao basica com usuario |
| **Orquestrador de Acoes** | Transacional + Comunicacao | Execucao de acoes confirmadas |
| **Agente RAG** | Suporte (explicar_regra) + Consulta | Busca semantica em documentos |
| **Agente Admin** | Admin + Consulta | Gestao e simulacao |

Nenhum agente tem acesso a todas as tools. O `ToolRegistry` aplica essa segregacao.

---

## 10. Sandboxing de Execucao

- Uma tool **nunca** pode escalar privilegios (ex: condomino executar tool de sindico).
- Tools executam dentro do contexto de permissao do usuario, nunca do sistema.
- Tools nao podem chamar outras tools diretamente — apenas via orquestrador.
- Tools nao podem modificar configuracoes do sistema, feature flags ou planos.
- Tools nao podem acessar outros tenants, mesmo que o provider de IA sugira.

---

## 11. Versionamento de Tools

- Alteracoes de comportamento exigem nova versao.
- Nunca quebrar contratos existentes silenciosamente.
- Depreciacao minima: 30 dias de aviso antes de remocao.
- Versao registrada em cada log de auditoria.
- Rollback de versao deve ser possivel sem perda de dados.

---

## 12. Proibicao de Tools Genericas

Estritamente proibido expor:

| Proibido | Motivo |
|----------|--------|
| `executar_sql` | Acesso direto ao banco |
| `http_request` | Chamadas HTTP arbitrarias |
| `executar_comando` | Execucao de shell |
| `acessar_api_interna` | Bypass de autorizacao |
| `manipular_arquivo` | Acesso ao filesystem |
| `alterar_configuracao` | Modificacao de infra |

Toda interacao da IA com o sistema passa por tools tipadas e catalogadas.

---

## 13. Protecao contra Prompt Injection

- Input do usuario e tratado como **nao-confiavel** antes de chegar ao modelo.
- System prompts contem guardrails (ver [prompt-registry.md](prompt-registry.md)).
- Output do modelo e tratado como **nao-confiavel** antes de executar tools.
- Delimitadores claros entre instrucoes do sistema e input do usuario.
- Monitoramento de tentativas de injection (patterns conhecidos).

---

## 14. Conformidade LGPD

| Requisito | Implementacao |
|-----------|---------------|
| **Minimizacao** | Tools retornam apenas dados necessarios para a operacao |
| **Anonimizacao** | CPF, email, telefone nunca enviados a providers externos |
| **Expiracao** | Logs de IA: 12 meses, depois anonimizacao |
| **Direito de auditoria** | Usuario pode consultar historico de interacoes com IA |
| **Direito de exclusao** | Delete de embeddings pessoais, anonimizacao de logs |
| **Consentimento** | Obrigatorio para comunicacoes com terceiros via IA |
| **Base legal** | Registrada em todo acesso a dados pessoais |

---

## 15. Checklist de Seguranca por Tool

Antes de publicar uma nova tool, verificar:

- [ ] Tenant inferido do JWT, nunca do parametro
- [ ] Role e permissoes verificados no backend
- [ ] Parametros tipados e validados
- [ ] Strings sanitizadas contra injection
- [ ] Rate limit definido no catalogo
- [ ] Limite de volume/tempo para consultas
- [ ] Auditoria registrada em ai_action_logs
- [ ] Confirmacao humana para tools transacionais
- [ ] Idempotencia implementada para tools transacionais
- [ ] Timeout definido
- [ ] Teste de isolamento cross-tenant
- [ ] LGPD compliance verificado
- [ ] Documentacao no catalogo atualizada
