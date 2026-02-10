# Features Futuras — Condominium Events Manager API

**Status:** Backlog (para implementação futura)
**Última atualização:** 2026-02-10

---

## 1. Extração Automática de Regras via IA

### Problema
O síndico precisa cadastrar manualmente cada regra do regulamento interno. Isso é trabalhoso e propenso a erros, especialmente quando o condomínio já possui Convenção e Regimento Interno documentados.

### Solução Proposta
Ao fazer upload de um documento legal (Convenção, Regimento Interno), a IA lê o documento e propõe automaticamente as regras para o banco de dados.

### Fluxo
1. Síndico faz upload do documento (UC-62: UploadCondominiumDocument)
2. IA parseia as seções automaticamente (UC-63: ParseDocumentSections)
3. IA analisa cada seção e propõe `CondominiumRule` com:
   - `title` extraído do artigo
   - `description` resumindo a regra
   - `category` classificada automaticamente (noise, reservations, guests, etc.)
   - `document_section_id` vinculando ao artigo de origem
4. Síndico revisa a lista de regras propostas (princípio: IA propõe, humano confirma)
5. Síndico aprova, ajusta ou rejeita cada regra individualmente
6. Regras aprovadas são criadas via `ai_action_logs` (proposed → confirmed → executed)

### Arquitetura já preparada
- `ai_action_logs` com padrão `proposed → confirmed → executed`
- `condominium_rules.document_section_id` (FK) para vínculo automático
- `ai_embeddings` com `source_type = 'document_section'` para busca semântica
- AI orchestration skill define tool-based AI com confirmação humana

### Dependências
- UC-62, UC-63, UC-64 implementados
- Integração com LLM configurada (ai-integration.md)
- ai_action_logs funcional

---

## 2. Importação em Massa de Moradores via CSV/Excel

### Problema
Um condomínio pode ter dezenas ou centenas de unidades e moradores. Cadastrar individualmente via convite (UC-19: InviteResident) é extremamente trabalhoso e pode ser um fator que penaliza a adoção pelo síndico no onboarding.

### Solução Proposta
Permitir que o síndico faça upload de um arquivo CSV ou Excel com os dados dos moradores, e o sistema processe em lote.

### Fluxo
1. Síndico faz upload de CSV/Excel com colunas:
   - Bloco (opcional — não existe em condomínios horizontais)
   - Unidade (identificador)
   - Nome completo
   - Email
   - CPF (opcional, mas recomendado para identificação única)
   - Tipo: proprietário / inquilino / dependente
2. Sistema valida o arquivo:
   - Formato e encoding corretos
   - Campos obrigatórios preenchidos
   - Emails válidos e sem duplicatas
   - CPFs válidos (se informados)
3. Sistema exibe preview: "150 moradores encontrados. 3 erros nas linhas X, Y, Z"
4. Síndico revisa e confirma
5. Job assíncrono processa em batch:
   - Cria blocos (se não existem e se informados)
   - Cria unidades (se não existem)
   - Cria residents vinculados às unidades
   - Dispara convites por email para cada morador
6. Síndico acompanha progresso em tempo real
7. Relatório final: X criados, Y convites enviados, Z erros

### Variação: Importação de Estrutura do Condomínio
Mesmo fluxo pode ser usado para importar apenas a estrutura:
- Blocos + Unidades (sem moradores)
- Útil para setup inicial do condomínio

### Arquitetura já preparada
- `job-architecture.md` — jobs assíncronos com progresso e observabilidade
- `idempotency-strategy.md` — re-upload não duplica (chave: email ou CPF)
- `notification-strategy.md` — convite por email
- UC-19 (InviteResident) — lógica individual reutilizada internamente
- LGPD: arquivo deletado após processamento (dados pessoais temporários)

### Considerações técnicas
- Limite de linhas por arquivo (ex: 1000) para evitar abuso
- Rate limiting nos convites por email (evitar spam)
- Arquivo processado em chunks para resiliência
- Falha parcial: linhas com erro não bloqueiam as demais
- Log detalhado por linha para troubleshooting

### Dependências
- UC-16 (CreateBlock), UC-17 (CreateUnit), UC-19 (InviteResident) implementados
- Job infrastructure configurada
- Sistema de email funcional

### Endpoint sugerido
```
POST /api/v1/tenant/residents/import
Content-Type: multipart/form-data
Body: { file: <arquivo.csv> }
```
**Roles:** sindico, administradora

**Response 202 (Accepted):**
```json
{
  "job_id": "uuid",
  "status": "processing",
  "total_rows": 150,
  "message": "Importação iniciada. Acompanhe o progresso via GET /api/v1/tenant/jobs/{job_id}"
}
```

---

## 3. Prioridade

| Feature | Prioridade | Motivo |
|---------|-----------|--------|
| Importação em massa de moradores | **Alta** | Crítico para onboarding — síndico não vai cadastrar 200 moradores manualmente |
| Extração automática de regras via IA | Média | Diferencial competitivo, mas síndico pode cadastrar regras manualmente |

---

## 4. Status

Documento de **backlog**. Features serão incorporadas ao roadmap de implementação quando priorizadas.
