# Agent Seller — Project Phases

<!-- inputs: project-description.md@sha256:76f9e62697bf user-stories.md@sha256:5e971be1f269 database-schema.md@sha256:0261e38c1cb2 -->

## Overview

Plano de construção **fundação-primeiro**: as três primeiras fases erguem a base (banco → models relationship-complete → design system), e só então vêm os fluxos de produto (auth da empresa → conexão do CRM → varredura → login do cliente → seleção de empresa → chat com streaming). São **9 fases top-level**, cada uma dimensionada para uma sessão de agente. Todo o escopo está no **primeiro release (MVP)**: streaming + painel de atividade e as stories Medium (re-scan, status, resumo) foram confirmados dentro do MVP. **A linha de corte do MVP é o fim da Phase 9.**

Cada tela e componente aponta para seu artefato em `.spec/init/design/` e deve ser fiel ao design proposto (design system em `Tokens e Componentes.dc.html`, Superfície A em `Painel da Empresa.dc.html`, Superfície B em `Cliente Final - standalone.html`, chat em `tela do chat.html`).

**Conventions:**
- `[ ]` pending · `[x]` done in the codebase.
- Phases and sub-phases are numbered (`Phase 1`, `Phase 5.3`) for reference by AI agents.
- Business-logic tasks list the **feature tests** to generate; frontend-only tasks list validatable **acceptance criteria** and a **Design ref**.

---

## Phase 1: Fundação de dados — migrations e seeders

**Goal:** criar todo o schema (17 tabelas) e semear as lookups. · **Depends on:** none · **Covers:** todas as tabelas de `database-schema.md`

### Phase 1.1: Base do starter (auth) e lookups

- [x] **Task:** Migration base de `users`, `cache`, `jobs` do starter kit
  - **Acceptance criteria:**
    - `users` tem `id`, `name`, `email` (unique), `password`, timestamps — serve à empresa (tenant); `name` = nome da empresa.
    - `jobs` existe (fila database, usada pela varredura).
  - **Traces:** table users · workflow 1

- [ ] **Task:** Migrations das lookup tables `crm_providers`, `scan_statuses`, `custom_field_entities`, `deal_statuses`, `message_roles`
  - **Acceptance criteria:**
    - Cada lookup tem `id`, `name`, `slug` (unique), timestamps; `crm_providers` também tem `is_active` (default true).
    - Nenhuma coluna enum — todo categórico é lookup com FK.
  - **Feature tests:** `lookup_tables_have_unique_slug` → inserir slug duplicado viola unique em cada lookup.
  - **Traces:** tables crm_providers, scan_statuses, custom_field_entities, deal_statuses, message_roles

### Phase 1.2: Tabelas de conexão e dados do CRM

- [ ] **Task:** Migrations `crm_connections` e `crm_scans`
  - **Acceptance criteria:**
    - `crm_connections`: FK `user_id`, `crm_provider_id`, `api_token` (text), `last_validated_at` (nullable), timestamps; índice **único em `user_id`** (uma conexão ativa por empresa).
    - `crm_scans`: FK `crm_connection_id`, `scan_status_id`, contadores `pipelines_count/custom_fields_count/persons_count/deals_count` (int default 0), `error_message` (nullable), `started_at`/`finished_at` (nullable), timestamps.
  - **Feature tests:** `one_active_connection_per_company` → segunda `crm_connections` para o mesmo `user_id` viola o índice único.
  - **Traces:** tables crm_connections, crm_scans · US-2.1, US-3.1

- [ ] **Task:** Migrations `pipelines`, `pipeline_stages`, `custom_fields`, `crm_persons`, `deals`
  - **Acceptance criteria:**
    - Cada tabela tem `external_id` e FKs conforme schema; `custom_fields.custom_field_entity_id`, `deals.deal_status_id/pipeline_id/pipeline_stage_id/crm_person_id` (nullable onde o schema define).
    - Índice único `(crm_connection_id, external_id)` em `pipelines`, `custom_fields`, `crm_persons`, `deals`; e `(pipeline_id, external_id)` em `pipeline_stages`.
    - `crm_persons.email` indexado; `deals.value` decimal nullable.
  - **Feature tests:** `scanned_rows_unique_by_external_id` → upsert do mesmo `external_id` na mesma conexão não duplica linha.
  - **Traces:** tables pipelines, pipeline_stages, custom_fields, crm_persons, deals · US-3.1

### Phase 1.3: Tabelas de cliente e chat + seeders

- [ ] **Task:** Migrations `clients`, `magic_links`, `conversations`, `messages`
  - **Acceptance criteria:**
    - `clients.email` unique (identidade global).
    - `magic_links`: `email` (indexado), `token` (unique), `expires_at` (not null), `used_at` (nullable), timestamps.
    - `conversations`: FK `client_id`, `user_id`; índice **único `(client_id, user_id)`** (uma conversa por par).
    - `messages`: FK `conversation_id`, `message_role_id`, `content` (text not null), timestamps.
  - **Feature tests:** `one_conversation_per_client_company_pair` → segunda `conversations` com o mesmo par viola o índice único; `client_email_is_unique` → email duplicado em `clients` falha.
  - **Traces:** tables clients, magic_links, conversations, messages · US-4.1, US-6.1, US-6.2

- [ ] **Task:** Seeders das lookups com os valores canônicos
  - **Acceptance criteria:**
    - `crm_providers`: `pipedrive`. `scan_statuses`: `pending`, `running`, `success`, `failed`. `custom_field_entities`: `deal`, `person`, `organization`. `deal_statuses`: `open`, `won`, `lost`. `message_roles`: `user`, `assistant`.
    - Seeder é idempotente (rodar 2× não duplica) via `updateOrCreate` por slug.
  - **Feature tests:** `lookup_seeder_is_idempotent` → rodar o seeder duas vezes mantém a contagem; slugs esperados presentes.
  - **Traces:** tables scan_statuses, deal_statuses, message_roles, custom_field_entities, crm_providers

---

## Phase 2: Models e relacionamentos (relationship-complete)

**Goal:** todos os models Eloquent com relacionamentos, casts, fillables e factories. · **Depends on:** Phase 1 · **Covers:** todas as tabelas

### Phase 2.1: Models de lookup e empresa

- [ ] **Task:** Models das lookups (`CrmProvider`, `ScanStatus`, `CustomFieldEntity`, `DealStatus`, `MessageRole`)
  - **Acceptance criteria:**
    - Cada model tem `hasMany` para quem o referencia (ex.: `ScanStatus hasMany CrmScan`, `MessageRole hasMany Message`).
    - Helper de resolução por slug (ex.: `ScanStatus::slug('running')`) para uso nos jobs/serviços.
  - **Feature tests:** `lookup_relationships_resolve` → `ScanStatus::slug('failed')->scans` retorna os scans corretos.
  - **Traces:** tables crm_providers, scan_statuses, custom_field_entities, deal_statuses, message_roles

- [ ] **Task:** Model `User` (empresa) relationship-complete
  - **Acceptance criteria:**
    - `hasOne CrmConnection`, `hasMany Conversation`.
    - Fillable/hidden coerentes com auth do starter; `name` documentado como nome da empresa.
  - **Feature tests:** `company_has_one_connection_and_many_conversations` → relacionamentos retornam os registros esperados.
  - **Traces:** table users · US-1.1

### Phase 2.2: Models do CRM

- [ ] **Task:** Models `CrmConnection` e `CrmScan`
  - **Acceptance criteria:**
    - `CrmConnection`: `belongsTo User`, `belongsTo CrmProvider`, `hasMany CrmScan/pipelines/customFields/crmPersons/deals`; **cast `api_token` → `encrypted`**.
    - `CrmScan`: `belongsTo CrmConnection`, `belongsTo ScanStatus`; casts de datas em `started_at/finished_at`.
  - **Feature tests:** `api_token_is_encrypted_at_rest` → valor cru no banco difere do texto e o accessor descriptografa; `connection_scan_relationships` ok.
  - **Traces:** tables crm_connections, crm_scans · US-2.1

- [ ] **Task:** Models `Pipeline`, `PipelineStage`, `CustomField`, `CrmPerson`, `Deal`
  - **Acceptance criteria:**
    - Relacionamentos: `Pipeline hasMany PipelineStage`; `Deal belongsTo Pipeline/PipelineStage/CrmPerson/DealStatus`; `CustomField belongsTo CustomFieldEntity`; todos `belongsTo CrmConnection`.
    - Fillables incluem `external_id` para upsert.
  - **Feature tests:** `crm_model_graph_wired` → travessia `pipeline->stages`, `deal->person`, `customField->entity` retorna corretamente.
  - **Traces:** tables pipelines, pipeline_stages, custom_fields, crm_persons, deals

### Phase 2.3: Models de cliente e chat + factories

- [ ] **Task:** Models `Client`, `MagicLink`, `Conversation`, `Message`
  - **Acceptance criteria:**
    - `Client hasMany Conversation`; `Conversation belongsTo Client/User (empresa)`, `hasMany Message`; `Message belongsTo Conversation/MessageRole`.
    - `MagicLink` com helpers `isExpired()` e `isUsed()`.
  - **Feature tests:** `chat_model_graph_wired` → `conversation->messages`, `message->role`, `client->conversations` ok; `magic_link_state_helpers` → `isExpired`/`isUsed` refletem `expires_at`/`used_at`.
  - **Traces:** tables clients, magic_links, conversations, messages · US-6.1, US-6.2

- [ ] **Task:** Factories dos models testáveis (`User`, `CrmConnection`, `CrmScan`, `CrmPerson`, `Client`, `MagicLink`, `Conversation`, `Message`)
  - **Acceptance criteria:**
    - Cada factory produz registro válido; states úteis (ex.: `CrmScan` states `pending/running/success/failed`; `MagicLink` states `expired/used`).
  - **Feature tests:** `factories_persist_valid_models` → cada factory salva sem violar constraints.
  - **Traces:** tables users, crm_connections, crm_scans, crm_persons, clients, magic_links, conversations, messages

---

## Phase 3: Fundação de frontend — design system

**Goal:** tokens Tailwind v4, layout base e componentes Blade compartilhados. · **Depends on:** none (paralelizável com P1–P2) · **Covers:** design system

### Phase 3.1: Tokens e tema

- [ ] **Task:** Configurar `@theme` Tailwind v4 com tokens do design + fontes + dark mode
  - **Acceptance criteria:**
    - Bloco `@theme` com cores (`--color-bg/surface/ink/ink-2/ink-3/border/accent/accent-strong/accent-soft/success/warn/danger/tool` e soft variants), `--font-sans` (Space Grotesk), `--font-mono` (IBM Plex Mono), `--radius-field/card`, sombras `sm/md/lg`.
    - Dark mode sobrescreve variáveis sob `.dark` (sem segunda paleta).
    - Fontes Space Grotesk e IBM Plex Mono carregadas.
  - **Design ref:** `Tokens e Componentes.dc.html` (seções 01–04)
  - **Traces:** design system

### Phase 3.2: Componentes base

- [ ] **Task:** Componentes `x-button` (default/hover/focus/disabled/loading, secondary/ghost/danger), `x-input` (default/focus/disabled/error/sensitive-masked/select)
  - **Acceptance criteria:**
    - Estados visuais batem com o design; `x-button` loading mostra spinner; `x-input` sensitive mascara e nunca reexibe valor.
  - **Design ref:** `Tokens e Componentes.dc.html` (seções 05–06)
  - **Traces:** design system

- [ ] **Task:** Componentes `x-badge` (pending/syncing/completed/failed/neutro/em-breve), `x-alert` (info/success/warn/danger), `x-card`, `x-empty-state`
  - **Acceptance criteria:**
    - `x-badge` syncing pulsa; cada estado usa a cor correta. `x-alert` tem os 4 tons. `x-card` com slots header/footer opcionais. `x-empty-state` com ícone + título + descrição + CTA.
  - **Design ref:** `Tokens e Componentes.dc.html` (seções 07–09)
  - **Traces:** design system

- [ ] **Task:** Componente `x-agent-event` (item polimórfico de atividade)
  - **Acceptance criteria:**
    - Anatomia fixa: ícone tipado + nome do evento (mono) + timestamp + resumo humano + payload JSON colapsável.
    - Suporta tipos `request.started`, `stream.delta`, `response.completed`, `error` (e visual reservado para `tool.*` futuro), sem layout hardcoded por tipo.
  - **Design ref:** `Tokens e Componentes.dc.html` (seção 10)
  - **Traces:** design system · US-6.1

### Phase 3.3: Layouts

- [ ] **Task:** Layout autenticado (topbar do painel) e layout público (cliente final)
  - **Acceptance criteria:**
    - Topbar do painel: logo `agentseller`, nome da empresa, email, botão Sair.
    - Layout público minimalista para as telas do cliente final. Ambos responsivos.
  - **Design ref:** `Painel da Empresa.dc.html` (topbar telas 03/05), `Cliente Final - standalone.html`
  - **Traces:** design system

---

## Phase 4: Autenticação da empresa

**Goal:** registro, login e logout do tenant. · **Depends on:** Phase 2, Phase 3 · **Covers:** US-1.1, US-1.2

### Phase 4.1: Registro

- [ ] **Task:** Tela + lógica de registro da empresa (Livewire)
  - **Acceptance criteria:**
    - Campos: nome da empresa, seu nome, email, senha, confirmar senha.
    - Email único; email repetido retorna erro de validação; senha mín. 8 chars; senha com hash.
    - Sucesso autentica e redireciona ao painel.
  - **Feature tests:** `company_can_register` → cria user e autentica; `registration_rejects_duplicate_email`; `registration_enforces_min_password_length`.
  - **Design ref:** `Painel da Empresa.dc.html` (tela 01 Registro)
  - **Traces:** US-1.1 · table users

### Phase 4.2: Login / logout / guarda

- [ ] **Task:** Tela + lógica de login/logout e proteção das rotas do painel
  - **Acceptance criteria:**
    - Login válido leva ao painel; credenciais inválidas mostram **mensagem genérica única** (nunca revela qual campo/se email existe).
    - Logout encerra sessão e volta ao login; rotas do painel exigem auth.
  - **Feature tests:** `company_can_login_and_logout`; `login_shows_generic_error_on_bad_credentials`; `panel_routes_require_authentication`.
  - **Design ref:** `Painel da Empresa.dc.html` (tela 02 Login — erro genérico)
  - **Traces:** US-1.2 · table users

---

## Phase 5: Conexão do CRM (Pipedrive)

**Goal:** driver Pipedrive, validação de token e persistência da conexão. · **Depends on:** Phase 4 · **Covers:** US-2.1, US-2.2

### Phase 5.1: Driver Pipedrive e validação

- [ ] **Task:** Cliente/driver Pipedrive agnóstico + validação de token
  - **Acceptance criteria:**
    - Interface de driver de CRM (contrato) com implementação Pipedrive; resolvido por `crm_providers.slug`.
    - `validateToken()` faz chamada de verificação à API do Pipedrive: 401 → inválido; falha de rede → retryable (não descarta token); vazio → erro de validação inline.
  - **Feature tests:** `pipedrive_token_validation_states` (API do Pipedrive fakeada) → 401 marca inválido, timeout marca retryable, string vazia falha validação antes de chamar a API.
  - **Traces:** US-2.1 · table crm_providers

### Phase 5.2: Formulário de conexão e persistência

- [ ] **Task:** Tela "Conectar Pipedrive" + persistência da conexão
  - **Acceptance criteria:**
    - Form com provider (Pipedrive selecionado; HubSpot/RD Station "em breve" desabilitados) e API token (campo sensível, mascarado).
    - Token válido → cria `crm_connections` (uma por empresa, `api_token` criptografado) e **enfileira a varredura**.
    - Três estados de erro visualmente distintos: 401 (banner vermelho, campo limpo/nunca reecoado), falha de rede (âmbar, retryable, token mascarado preservado), validação (erro inline, sem banner).
  - **Feature tests:** `connecting_valid_token_creates_encrypted_connection_and_queues_scan`; `invalid_token_does_not_persist_connection`; `token_never_returned_to_client_after_save`.
  - **Design ref:** `Painel da Empresa.dc.html` (tela 03 CRM não conectado, tela 04 + estados 4a/4b/4c)
  - **Traces:** US-2.1 · tables crm_connections, crm_providers

### Phase 5.3: Status da conexão

- [ ] **Task:** Bloco de status da conexão no painel (Medium)
  - **Acceptance criteria:**
    - Mostra provider conectado (Pipedrive) e data de conexão/validação; permite desconectar/atualizar token.
  - **Feature tests:** `connection_status_reflects_state`; `company_can_disconnect_connection`.
  - **Design ref:** `Painel da Empresa.dc.html` (tela 05 — "CRM conectado: Pipedrive · conectado em …")
  - **Traces:** US-2.2 · table crm_connections

---

## Phase 6: Varredura (scan) do CRM

**Goal:** job de varredura, máquina de estados e telas de resumo/re-scan. · **Depends on:** Phase 5 · **Covers:** US-3.1, US-3.2, US-3.3

### Phase 6.1: Job de varredura e importação

- [ ] **Task:** Job em fila que varre o Pipedrive e armazena os dados
  - **Acceptance criteria:**
    - Importa e **upserta por `external_id`**: `pipelines` + `pipeline_stages`, `custom_fields` (com `custom_field_entity`), `crm_persons`, `deals`.
    - Atualiza `crm_scans`: `pending`→`running`→`success`; grava contadores por entidade e `finished_at`.
    - Não bloqueia a requisição de conexão (roda na fila database).
  - **Feature tests:** `scan_imports_all_entities` (Pipedrive fakeado) → contagens corretas em cada tabela; `scan_upsert_does_not_duplicate` → rodar 2× mantém contagem; `scan_status_transitions_to_success`.
  - **Traces:** US-3.1 · tables pipelines, pipeline_stages, custom_fields, crm_persons, deals, crm_scans

- [ ] **Task:** Tratamento de falha da varredura
  - **Acceptance criteria:**
    - Erro da API do Pipedrive (ex.: 429 ao paginar) é capturado: `crm_scans` vira `failed` com `error_message` legível/copiável e `finished_at`.
    - **Dados já importados permanecem** no banco (não há rollback dos registros anteriores).
  - **Feature tests:** `scan_failure_sets_failed_status_and_keeps_partial_data` → after 429 na página 3, status `failed`, persons das páginas 1–2 continuam.
  - **Traces:** US-3.1 · table crm_scans

### Phase 6.2: Card de varredura (4 estados) e re-scan

- [ ] **Task:** Card de varredura com os 4 estados + polling Livewire
  - **Acceptance criteria:**
    - `pending`: enfileirada, botão Ressincronizar desabilitado. `syncing`: barra indeterminada + spinner, botão desabilitado, tela atualiza sozinha (polling). `completed`: timestamp + contagens por entidade. `failed`: erro copiável, dados mantidos.
    - Badge de estado usa `x-badge` com a cor correta.
  - **Design ref:** `Painel da Empresa.dc.html` (tela 05 estados 5a–5d)
  - **Traces:** US-3.1, US-3.3 · table crm_scans

- [ ] **Task:** Re-scan sob demanda (Medium)
  - **Acceptance criteria:**
    - Botão "Ressincronizar" (habilitado só quando não há scan em andamento) enfileira o mesmo job.
    - Durante `pending`/`syncing` o botão fica desabilitado; ao concluir, contagens e timestamp atualizam.
  - **Feature tests:** `rescan_enqueues_scan_job`; `rescan_disabled_while_scan_running`.
  - **Design ref:** `Painel da Empresa.dc.html` (tela 05 — botão Ressincronizar)
  - **Traces:** US-3.2 · table crm_scans

### Phase 6.3: Resumo dos dados e volume de conversas

- [ ] **Task:** Resumo do que foi escaneado no painel (Medium)
  - **Acceptance criteria:**
    - Mostra pipelines/stages, custom fields (por entidade person/deal), nº de persons e deals, status e timestamp do último scan.
  - **Feature tests:** `dashboard_shows_scanned_counts` → contagens batem com o banco.
  - **Design ref:** `Painel da Empresa.dc.html` (tela 05 grid de contagens)
  - **Traces:** US-3.3 · tables pipelines, custom_fields, crm_persons, deals

- [ ] **Task:** Cards de volume de conversas/mensagens (zero honesto)
  - **Acceptance criteria:**
    - Mostra contagem de `conversations` e `messages` da empresa; zero é estado honesto (não erro), com texto explicativo; conteúdo das conversas nunca é exibido (só volumes).
  - **Feature tests:** `conversation_volume_counts_are_scoped_to_company` → contagens só da empresa logada.
  - **Design ref:** `Painel da Empresa.dc.html` (tela 06 volume zero)
  - **Traces:** US-3.3 · tables conversations, messages

---

## Phase 7: Login do cliente final (magic link)

**Goal:** acesso por email com match no CRM e magic link de 15 min uso único. · **Depends on:** Phase 6 · **Covers:** US-4.1, US-4.2

### Phase 7.1: Solicitação do magic link

- [ ] **Task:** Tela de acesso + geração/envio do magic link com match no CRM
  - **Acceptance criteria:**
    - Cliente informa email; casa contra `crm_persons.email` de todas as empresas.
    - Sem match: não envia link. Com ≥1 match: gera `magic_links` (token hash, `expires_at` = agora + **15 min**, uso único) e envia por email.
    - Resposta na tela é **enumeration-safe** (não revela em quais/quantas empresas o email existe); tela de confirmação "Verifique seu e-mail".
  - **Feature tests:** `no_crm_match_sends_no_link`; `match_generates_link_expiring_in_15_min`; `magic_link_token_is_hashed`; `access_response_is_enumeration_safe` (resposta idêntica com e sem match).
  - **Design ref:** `Cliente Final - standalone.html` (08 Acesso, 08b Confirmação, 09 Email magic link)
  - **Traces:** US-4.1 · tables magic_links, crm_persons, clients

### Phase 7.2: Autenticação pelo link

- [ ] **Task:** Endpoint de autenticação do magic link
  - **Acceptance criteria:**
    - Link válido e não usado autentica o cliente (cria/acha `clients` por email), marca `used_at`.
    - Link expirado (>15 min) ou já usado → tela "Este link não é mais válido" com opção de novo link.
    - Após auth: 1 match → chat direto; múltiplos → seleção de empresa.
  - **Feature tests:** `valid_link_authenticates_and_marks_used`; `expired_link_is_rejected`; `used_link_cannot_be_reused`; `single_match_goes_to_chat_multiple_goes_to_selection`.
  - **Design ref:** `Cliente Final - standalone.html` (10 Link inválido)
  - **Traces:** US-4.2 · tables magic_links, clients

---

## Phase 8: Seleção de empresa (multi-empresa)

**Goal:** cliente escolhe a empresa quando há múltiplos matches. · **Depends on:** Phase 7 · **Covers:** US-5.1

- [ ] **Task:** Tela de seleção de empresa
  - **Acceptance criteria:**
    - Lista apenas empresas em que o email do cliente consta em `crm_persons`.
    - Selecionar define o contexto (empresa) e leva ao chat; com um único match a tela é pulada.
  - **Feature tests:** `multiple_matches_list_all_matched_companies`; `only_matched_companies_are_listed`; `single_match_skips_selection`.
  - **Design ref:** `Cliente Final - standalone.html` (11 Seleção de empresa — "Com quem você quer conversar?")
  - **Traces:** US-5.1 · tables crm_persons, users

---

## Phase 9: Chat com o agente (streaming + atividade)

**Goal:** chat persistido com streaming do agente e painel de atividade didático. · **Depends on:** Phase 8, Phase 3 · **Covers:** US-6.1, US-6.2

### Phase 9.1: Conversa persistida e resposta do agente

- [ ] **Task:** Componente de chat + integração `laravel/ai` (OpenAI, system prompt global)
  - **Acceptance criteria:**
    - Ao abrir, materializa/retoma `conversations` do par cliente+empresa (único).
    - Mensagem do cliente é persistida (`message_role` = `user`), enviada ao agente via `laravel/ai` (OpenAI) com o **system prompt global fixo em código**; resposta persistida (`message_role` = `assistant`).
    - Agente **não** usa dados do CRM nem tools no MVP — apenas o system prompt.
  - **Feature tests:** `client_message_and_agent_reply_are_persisted` (provider fakeado) → 2 messages com roles corretos; `agent_uses_global_system_prompt`; `conversation_is_reused_per_client_company_pair`.
  - **Design ref:** `tela do chat.html` (12 Chat desktop — bolhas cliente/AI)
  - **Traces:** US-6.1 · tables conversations, messages, message_roles

- [ ] **Task:** Retomar histórico da conversa
  - **Acceptance criteria:**
    - Reabrir o chat carrega as mensagens anteriores do par, em ordem; conversas de empresas diferentes ficam isoladas; histórico persiste entre sessões (após novo magic link).
  - **Feature tests:** `history_reloads_in_order`; `conversations_are_isolated_between_companies`; `history_persists_across_sessions`.
  - **Design ref:** `Cliente Final - standalone.html` (12 Chat)
  - **Traces:** US-6.2 · tables conversations, messages

### Phase 9.2: Streaming e painel de atividade

- [ ] **Task:** Resposta em streaming no chat
  - **Acceptance criteria:**
    - A resposta do agente aparece em streaming (cursor piscando, "o agent está escrevendo…"); input e botão enviar ficam desabilitados enquanto a resposta não termina.
    - Ao concluir, a mensagem final fica persistida.
  - **Feature tests:** `streamed_response_is_persisted_on_completion` → após stream, `messages` tem a resposta completa.
  - **Design ref:** `tela do chat.html` (12 — streaming, input travado)
  - **Traces:** US-6.1 · tables messages

- [ ] **Task:** Painel de atividade do agente (eventos efêmeros, didático)
  - **Acceptance criteria:**
    - Emite eventos por resposta: `request.started` → `stream.delta`(s) → `response.completed` (ou `error`), renderizados com `x-agent-event` (ícone/nome mono/timestamp/resumo/payload colapsável).
    - Eventos são **efêmeros** (somem ao recarregar; não persistidos); agrupados por "RESPOSTA #N".
    - Painel colapsável; versão mobile (drawer de atividade).
  - **Design ref:** `tela do chat.html` (12 painel de atividade), `Cliente Final - standalone.html` (12b Atividade/Chat mobile), `Tokens e Componentes.dc.html` (seção 10 x-agent-event)
  - **Traces:** US-6.1 · table messages

### Phase 9.3: Erros do provider e troca de empresa

- [ ] **Task:** Tratamento de erro do provider de IA no chat
  - **Acceptance criteria:**
    - Falha na chamada de IA emite evento `error` e mostra erro amigável ("RESPOSTA #N — FALHOU"); **a mensagem do cliente não é perdida** e pode reenviar.
  - **Feature tests:** `provider_error_shows_friendly_message_and_preserves_client_message` → após erro do provider, a message do cliente persiste e nenhuma resposta parcial corrompe o histórico.
  - **Design ref:** `Cliente Final - standalone.html` (13 Erro do provider)
  - **Traces:** US-6.1 · tables messages

- [ ] **Task:** Trocar empresa a partir do chat
  - **Acceptance criteria:**
    - Botão "⇄ Trocar empresa" volta à seleção de empresa (só quando o cliente tem múltiplos matches); ao trocar, abre/retoma a conversa da outra empresa.
  - **Feature tests:** `switch_company_opens_other_conversation`; `switch_hidden_for_single_match_client`.
  - **Design ref:** `tela do chat.html` (12 — "Trocar empresa"), `Cliente Final - standalone.html` (11 Seleção)
  - **Traces:** US-5.1, US-6.2 · tables conversations, users
