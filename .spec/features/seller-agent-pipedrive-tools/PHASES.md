# Phases: seller-agent-pipedrive-tools

Gerado por /plan a partir de PLAN.md — view executável para `./ralph.sh .spec/features/seller-agent-pipedrive-tools/PHASES.md`.

## Phase 1: Fundação — leituras do driver e resolução de identidade

Antes de implementar, leia:
1. `.spec/features/seller-agent-pipedrive-tools/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/seller-agent-pipedrive-tools/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T01 — Métodos de LEITURA no contrato CrmDriver + PipedriveDriver
      Arquivos: `app/Services/Crm/Contracts/CrmDriver.php`, `app/Services/Crm/Drivers/PipedriveDriver.php`
      Mudança: adicionar ao contrato e implementar no driver, cada método recebendo `string $token` + ids externos já resolvidos (nenhuma identidade além do token): `fetchDeal($token, $dealExternalId)` (`GET /deals/{id}` → title/value/stage_external_id/status; campos ausentes ficam null, sem fabricação); `fetchDealStageChanges($token, $dealExternalId)` (`GET /deals/{id}/flow` filtrando SÓ mudanças de estágio → origem/destino/momento, descartando os demais eventos); `fetchDealComments($token, $dealExternalId)`; `fetchNotes($token, ?$dealExternalId, ?$personExternalId)` retornando lista mesclada com cada item rotulado por origem deal/person (pessoa disponível mesmo sem deal); `fetchPipelinesWithStages($token)` (pipelines com stages contendo `id` externo Pipedrive + `name`). Reutilizar `Http::` + `config('services.pipedrive.base_url')` + `api_token` query param; falha de rede/não-2xx lança `CrmApiException`; 404 ao vivo LANÇA `CrmApiException` (não retorna vazio). Confirmar shapes/endpoints na doc oficial (Open Question Q-1) antes de congelar.
      Cobre: RF-01, RF-02, RF-03, RF-04, RF-05, CT-02, RNF-01
      Acceptance criteria: `Http::fake` — fetchDeal retorna title/value/estágio/status e marcador null quando Pipedrive omite estágio/status; flow filtrado só a mudanças de estágio; comentários retornados; notas mescladas e rotuladas deal/person; pipelines com stages id+name; não-2xx, `ConnectionException` e 404 em fetchDeal lançam `CrmApiException`.
      Testes: `tests/Feature/Crm/PipedriveDriverReadTest.php` — casos acima com Pipedrive fakeado.
- [ ] T03 — Serviço app-side ConversationDealResolver
      Arquivos: `app/Services/Crm/ConversationDealResolver.php` (novo)
      Mudança: serviço que recebe `Conversation` e resolve sem expor nada ao LLM: token = `conversation->user->crmConnection->api_token`; driver = `CrmDriverManager::driver($connection->crmProvider->slug)`; `crm_persons` casadas por `Str::lower(trim($conversation->client->email))` case-insensitive ESCOPADO ao `crm_connection` de `conversation->user` (tenant, RNF-02); UNION dos deals das pessoas casadas; deal resolvido = `deal_status` slug `open` mais recentemente atualizado (`whereHas('dealStatus', slug=open)->orderByDesc('updated_at')->first()`). Retorna estrutura com token, driver, personExternalIds (para notas mesmo sem deal) e deal resolvido (external_id, pipeline_external_id, status) ou null. Sem empresa/conexão, pessoa casada ou deal aberto → deal=null (estado "sem deal"). Nenhuma chamada HTTP aqui; o mirror só resolve ids, nunca serve como resposta de tool.
      Cobre: RF-09, RF-10, RF-12, RNF-02
      Acceptance criteria: casamento case-insensitive; escopo por tenant (empresa A não casa pessoa/deal da empresa B); UNION de múltiplas pessoas; seleciona o deal aberto com maior `updated_at`; só deals fechados → deal=null mas personExternalIds presente; sem match → deal=null e personExternalIds vazio; empresa sem `crmConnection` → resolução vazia sem erro.
      Testes: `tests/Feature/Crm/ConversationDealResolverTest.php` — casos acima com factories.

## Phase 2: Ações do driver

Antes de implementar, leia:
1. `.spec/features/seller-agent-pipedrive-tools/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/seller-agent-pipedrive-tools/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T02 — Métodos de AÇÃO no contrato CrmDriver + PipedriveDriver
      Arquivos: `app/Services/Crm/Contracts/CrmDriver.php`, `app/Services/Crm/Drivers/PipedriveDriver.php`
      Mudança: adicionar/implementar recebendo `string $token` + deal externo resolvido + parâmetro de negócio: `moveDealStage($token, $dealExternalId, $stageExternalId)` (`PUT /deals/{id}` com `stage_id` = id externo, sem tradução local↔externo); `markDealWon($token, $dealExternalId)` (`PUT status=won`); `markDealLost($token, $dealExternalId, ?$lostReason=null)` (`PUT status=lost` + `lost_reason` texto livre repassado como está quando presente). Falha de provider lança `CrmApiException`. Validação same-pipeline (RF-06) e recusa de deal fechado (RF-12) NÃO ficam aqui — são app-side na Phase 3. Confirmar payload do PUT na doc oficial (Q-1).
      Cobre: RF-06, RF-07, RF-08, CT-03
      Acceptance criteria: `Http::fake` — PUT de move envia `stage_id`; won envia `status=won`; lost envia `status=lost` e, com motivo, `lost_reason` idêntico ao informado; não-2xx e `ConnectionException` lançam `CrmApiException`.
      Testes: `tests/Feature/Crm/PipedriveDriverActionTest.php` — casos acima com Pipedrive fakeado.

## Phase 3: Tools laravel/ai (leitura e ação)

Antes de implementar, leia:
1. `.spec/features/seller-agent-pipedrive-tools/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/seller-agent-pipedrive-tools/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T04 — 5 tools de leitura sob app/Ai/Tools
      Arquivos: `app/Ai/Tools/GetDealDataTool.php`, `app/Ai/Tools/GetDealStageHistoryTool.php`, `app/Ai/Tools/GetDealCommentsTool.php`, `app/Ai/Tools/GetDealNotesTool.php`, `app/Ai/Tools/ListPipelinesTool.php` (novos)
      Mudança: cada tool implementa `Laravel\Ai\Contracts\Tool`, recebe `Conversation` por construtor, `schema()` retorna `[]` (nenhum parâmetro — RF-09/CT-04), e `handle()` resolve via `ConversationDealResolver` (T03) e chama o método de leitura correspondente (T01). Campos ausentes de estágio/status → marcador nulo/"desconhecido" explícito, distinto do "sem deal" (RF-01); sem deal resolvível → marcador "sem deal encontrado" para DealData/StageHistory/Comments (RF-10); Notes ainda retorna notas da pessoa quando há pessoa mas não deal (RF-04); ListPipelines independe de deal e sempre retorna pipelines+stages (RF-05); `CrmApiException`/falha capturada vira marcador "não consegui confirmar" SEM nome de tool/id/stacktrace (RF-11/RNF-03). Descrições PT-BR orientando o modelo.
      Cobre: RF-01, RF-02, RF-03, RF-04, RF-05, RF-09, RF-10, RF-11, CT-01, CT-04, RNF-01, RNF-03
      Acceptance criteria: `schema()` de cada tool vazio (nenhum campo deal_id/person_id/client_id/user_id/company_id/email/token); GetDealData retorna dados ao vivo e marcador null quando estágio/status ausentes; StageHistory só mudanças de estágio; Notes mescladas rotuladas e ainda retorna notas da pessoa sem deal; ListPipelines disponível sem deal; sem deal → marcador "sem deal"; falha do Pipedrive → marcador de falha sem vazar tool/id/erro.
      Testes: `tests/Feature/Ai/Tools/ReadToolsTest.php` — `Http::fake` do Pipedrive + factories.
- [ ] T05 — 3 tools de ação sob app/Ai/Tools
      Arquivos: `app/Ai/Tools/MoveDealStageTool.php`, `app/Ai/Tools/MarkDealWonTool.php`, `app/Ai/Tools/MarkDealLostTool.php` (novos)
      Mudança: cada tool implementa `Tool`, recebe `Conversation` por construtor. Schemas (CT-04): MoveDealStageTool → `stage_id` string required (id externo de stage do Pipedrive de RF-05); MarkDealLostTool → `lost_reason` string opcional (texto livre); MarkDealWonTool → `[]`. `handle()` resolve via `ConversationDealResolver`; sem deal → marcador "sem deal" (RF-10). Antes de qualquer mutação: deal resolvido `won`/`lost` (fechado) → RECUSA com marcador explícito, sem chamar o driver (RF-12). MoveDealStageTool valida same-pipeline app-side: pipeline do deal (via fetchDeal/mirror) vs pipeline do `stage_id` alvo (via fetchPipelinesWithStages, T01); estágio de outro pipeline → RECUSA (RF-06). Só então chama moveDealStage/markDealWon/markDealLost (T02). Falha → marcador "não consegui confirmar" sem vazamento (RF-11/RNF-03); `lost_reason` repassado como está (RF-08).
      Cobre: RF-06, RF-07, RF-08, RF-09, RF-10, RF-11, RF-12, CT-01, CT-03, CT-04, RNF-03
      Acceptance criteria: MoveDealStageTool schema só `stage_id`; move para estágio do mesmo pipeline chama PUT correto; `stage_id` de outro pipeline → recusa sem PUT; MarkDealWonTool schema vazio, marca `won`; MarkDealLostTool schema só `lost_reason`, marca `lost` e repassa o motivo; deal já fechado → todas recusam com marcador e não chamam o driver; sem deal → marcador "sem deal"; falha → marcador sem vazar tool/id.
      Testes: `tests/Feature/Ai/Tools/ActionToolsTest.php` — `Http::fake` + factories.

## Phase 4: Wiring no SellerAgent

Antes de implementar, leia:
1. `.spec/features/seller-agent-pipedrive-tools/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/seller-agent-pipedrive-tools/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T06 — Wiring das 8 tools em SellerAgent::tools() + broadcasting
      Arquivos: `app/Ai/Agents/SellerAgent.php`, `tests/Feature/ChatTest.php`
      Mudança: `tools()` passa a retornar `WebSearch(maxSearches: 3)` MAIS as 8 tools da feature, cada uma instanciada com `$this->conversation` (CT-01). Avaliar `#[WithoutBroadcasting(ToolResult::class)]` (import `Laravel\Ai\Streaming\Events\ToolResult`) se payloads de leitura excederem o frame WebSocket do painel. Atualizar o teste existente `agent_exposes_web_search_tool` em `tests/Feature/ChatTest.php:58` (hoje espera `toHaveCount(1)`) para 9 tools, com `WebSearch(maxSearches:3)` presente e as 8 tools instanciáveis. Não alterar `instructions()`/prompt — o bloco `<tools>` já cobre RF-11/RNF-03.
      Cobre: CT-01
      Acceptance criteria: `tools()` retorna 9 itens; contém `WebSearch` com `maxSearches = 3`; as 8 novas tools presentes e instâncias de `Laravel\Ai\Contracts\Tool`; o teste `agent_exposes_web_search_tool` atualizado passa.
      Testes: `tests/Feature/ChatTest.php` — `agent_exposes_web_search_tool` atualizado para 9 tools.
