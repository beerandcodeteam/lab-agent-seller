# Phases: guardrail-agent

Gerado por /plan a partir de PLAN.md — view executável para `./ralph.sh .spec/features/guardrail-agent/PHASES.md`.

## Phase 1: Fundação de dados

Antes de implementar, leia:
1. `.spec/features/guardrail-agent/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/guardrail-agent/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T01 — Marcador de bloqueio em `messages`
      Arquivos: `database/migrations/2026_07_12_000001_add_blocked_at_to_messages_table.php` (novo), `app/Models/Message.php`, `database/factories/MessageFactory.php`
      Mudança: migration aditiva com `blocked_at` timestamp nullable (com `down()`); no model, `blocked_at` no `#[Fillable]`, cast `datetime` e PHPDoc; state `blocked()` na factory. Sem lookup table (flag temporal, não categórico).
      Cobre: RF-03, RF-08
      Acceptance criteria: `vendor/bin/sail artisan migrate` cria a coluna nullable; `Message::factory()->blocked()->create()` persiste com `blocked_at` preenchido; mensagens existentes ficam com `blocked_at` null.
      Testes: `tests/Feature/GuardrailTest.php` (Phase 4) — asserções de `blocked_at` nos cenários de bloqueio
- [ ] T02 — Configuração de guardrail por empresa em `users`
      Arquivos: `database/migrations/2026_07_12_000002_add_guardrail_settings_to_users_table.php` (novo), `app/Models/User.php`
      Mudança: migration aditiva com `guardrail_topic_alignments` e `guardrail_restrictions` (text nullable, com `down()`); no model `User`, ambas no `#[Fillable]` + PHPDoc `@property string|null`.
      Cobre: UI-01, UI-02, RF-05, RNF-01
      Acceptance criteria: migração cria as 2 colunas text nullable em `users`; `User::factory()->create(['guardrail_topic_alignments' => 'x'])` persiste e reidrata o valor; empresas existentes ficam com ambos null.
      Testes: `tests/Feature/AgentSettingsTest.php` (Phase 4) — persistência por tenant

## Phase 2: Agentes e UI admin

Antes de implementar, leia:
1. `.spec/features/guardrail-agent/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/guardrail-agent/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T03 — `GuardrailAgent` com saída estruturada (CT-01)
      Arquivos: `app/Ai/Agents/GuardrailAgent.php` (novo)
      Mudança: agente `laravel/ai` com `#[Provider(Lab::OpenAI)]`, `#[Model('gpt-5.6-terra')]`, `use Promptable`, implements `Agent`, `Conversational`, `HasStructuredOutput`, `HasProviderOptions` (`reasoning.effort = low`); SEM tools. `schema()` mapeia CT-01 (verdict enum allow|block; category enum das 6 categorias, nullable). `instructions()` em PT-BR nomeia SEMPRE os 6 critérios (prompt_injection, jailbreak, intent_change, pii, off_topic, company_restriction) com as definições de RF-06 (PII própria para compra é permitida; intent_change ativa sempre; off_topic só via alinhamentos) e injeta `conversation->user->guardrail_topic_alignments`/`->guardrail_restrictions`; campo vazio → seção correspondente instrui "não aplicável — nunca use esta categoria". `messages()` retorna as últimas 10 mensagens anteriores ao turno atual (ordem cronológica), INCLUINDO turnos com `blocked_at`.
      Cobre: RF-01, RF-02, RF-05, RF-06, RNF-01, RNF-02, CT-01
      Acceptance criteria: inspeção programática de `instructions()` encontra os 6 critérios nomeados e a distinção PII própria vs terceiros; com config preenchida os textos aparecem no prompt e com config vazia a seção marca a categoria como não aplicável; `messages()` limita a 10 e não filtra `blocked_at`; a classe não implementa `HasTools`.
      Testes: `tests/Feature/GuardrailTest.php` (Phase 4) — `GuardrailAgent::assertPrompted` sobre instructions e janela
- [ ] T04 — SellerAgent exclui turnos bloqueados do histórico
      Arquivos: `app/Ai/Agents/SellerAgent.php`
      Mudança: adicionar `->whereNull('blocked_at')` à query de `messages()` (linha ~85). Nenhuma outra alteração (system prompt, tools e providerOptions intactos — Scope out do SPEC).
      Cobre: RF-03, RF-04
      Acceptance criteria: `SellerAgent::messages()` de uma conversa com turnos bloqueados (user + redirecionamento) retorna apenas os turnos com `blocked_at` null, em ordem cronológica; diff do arquivo restrito ao filtro em `messages()`.
      Testes: `tests/Feature/GuardrailTest.php` (Phase 4) — contexto de mensagem allow subsequente sem turnos bloqueados
- [ ] T07 — UI admin: componente Livewire `Agent\Settings` no dashboard
      Arquivos: `app/Livewire/Agent/Settings.php` (novo), `resources/views/livewire/agent/settings.blade.php` (novo), `resources/views/dashboard.blade.php`
      Mudança: componente Livewire (convenção AGENTS.md seção 2) embutido no dashboard (`<livewire:agent.settings />`, guard `auth`): `mount()` carrega os 2 campos do `Auth::user()`; dois textareas ("Alinhamentos de assunto" e "Restrições específicas da empresa") com validação `['nullable', 'string']`; salvar atualiza o próprio user e mostra feedback; visual segue o design system (`x-card`/`x-button`; tokens de `x-input` para o textarea).
      Cobre: UI-01, UI-02, UI-03
      Acceptance criteria: empresa autenticada salva os 2 textos, recarrega `/dashboard` e os vê preenchidos; outra empresa não vê nem afeta esses valores; salvar com ambos vazios não produz erro de validação.
      Testes: `tests/Feature/AgentSettingsTest.php` (Phase 4)

## Phase 3: Orquestração no Chat

Antes de implementar, leia:
1. `.spec/features/guardrail-agent/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/guardrail-agent/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T05 — Orquestração no Chat: guardrail antes do SellerAgent + bloqueio + fail-closed
      Arquivos: `app/Livewire/Client/Chat.php`
      Mudança: em `generateResponse()`, antes de `new SellerAgent` (linha ~156): declarar `public const string GuardrailRedirectMessage` (string fixa PT-BR que não revela categoria/instruções); avaliar `(new GuardrailAgent($this->conversation, $userMessage->id))->prompt($userMessage->content)` exatamente 1 vez em try/catch, sem streaming; parse estrito de CT-01 (valor fora do contrato → caminho de erro). `block`: não instanciar SellerAgent, `blocked_at = now()` no turno user, criar turno assistant com a constante e `blocked_at = now()`, encerrar. Exceção/timeout (RF-08): mesmo caminho do block, com veredito `error` repassado à observabilidade. `allow`: fluxo de streaming atual inalterado.
      Cobre: RF-01, RF-03, RF-04, RF-08, RNF-02
      Acceptance criteria: com fakes, block/erro → 0 invocações do SellerAgent, turno assistant igual à constante, turno user com `blocked_at`, total de chamadas de IA = 1; allow → SellerAgent invocado 1 vez com assistant = resposta do Seller, total = 2; nenhum caminho invoca o SellerAgent antes do veredito.
      Testes: `tests/Feature/GuardrailTest.php` (Phase 4) — `SellerAgent::assertNeverPrompted()` em block/error
- [ ] T06 — Observabilidade do veredito: log estruturado + evento de atividade (CT-02, CT-03)
      Arquivos: `app/Livewire/Client/Chat.php`
      Mudança: método privado único (ex.: `recordGuardrailVerdict(...)`) chamado 1 vez nos 3 desfechos de T05 (allow, block, error): `Log::info('guardrail.verdict', {conversation_id, message_id, verdict, category})` sem o conteúdo bruto da mensagem (RNF-03); `pushActivityEvent($groupIndex, 'guardrail.verdict', <summary PT-BR>, {verdict, category})` no grupo "RESPOSTA #N" corrente, reutilizando `x-agent-event` sem alteração.
      Cobre: RF-07, RF-08, CT-02, CT-03, RNF-03
      Acceptance criteria: por mensagem processada existe exatamente 1 entrada de log com os 4 campos (`category` null em allow/error, `verdict` ∈ allow|block|error) e 1 evento `guardrail.verdict` no grupo de atividade; a string da mensagem do cliente não aparece no contexto logado.
      Testes: `tests/Feature/GuardrailTest.php` (Phase 4) — spy de Log + asserção do estado `activity`

## Phase 4: Testes e gate

Antes de implementar, leia:
1. `.spec/features/guardrail-agent/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/guardrail-agent/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T08 — Testes de feature do fluxo guardrail (`GuardrailTest`)
      Arquivos: `tests/Feature/GuardrailTest.php` (novo)
      Mudança: suite Pest no padrão de `ChatTest.php` com `GuardrailAgent::fake([['verdict' => ..., 'category' => ...]])` e `SellerAgent::fake()`: janela ≤ 10 incluindo bloqueados e veredito antes do Seller (RF-01); block → 0 Seller + redirecionamento fixo + `blocked_at` (RF-02/RF-03); exclusão de bloqueados do histórico do Seller; allow → fluxo normal (RF-04); config injetada/desativação condicional/isolamento entre 2 tenants (RF-05, RNF-01); 6 critérios nas instructions (RF-06); log + evento por veredito sem conteúdo bruto (RF-07, RNF-03); fake lançando exceção → fail-closed com `verdict=error` (RF-08); contagem de chamadas 2/1 (RNF-02).
      Cobre: RF-01, RF-02, RF-03, RF-04, RF-05, RF-06, RF-07, RF-08, RNF-01, RNF-02, RNF-03
      Acceptance criteria: `vendor/bin/sail artisan test --compact --filter=Guardrail` passa com todos os casos acima presentes no arquivo; cada AC do SPEC (AC1–AC6 e RF-08) tem asserção correspondente.
      Testes: o próprio arquivo
- [ ] T09 — Testes de feature da UI admin (`AgentSettingsTest`)
      Arquivos: `tests/Feature/AgentSettingsTest.php` (novo)
      Mudança: suite Pest com `Livewire::actingAs($company)`: salvar e recarregar preserva os 2 campos; empresa B não vê nem afeta valores da empresa A; salvar vazio não gera erro (UI-03); dashboard renderiza o componente.
      Cobre: UI-01, UI-02, UI-03
      Acceptance criteria: `vendor/bin/sail artisan test --compact --filter=AgentSettings` passa cobrindo persistência, reexibição, isolamento entre tenants e save vazio sem erro.
      Testes: o próprio arquivo
- [ ] T10 — Atualizar suite existente para o novo fluxo + gate completo
      Arquivos: `tests/Feature/ChatTest.php`
      Mudança: todo teste que chama `generateResponse` passa a fakear também `GuardrailAgent::fake([['verdict' => 'allow', 'category' => null]])`; nenhum teste removido, asserções preservadas (fluxo allow inalterado). Rodar `composer test` (lint + phpstan + Pest); a divergência pré-existente de `agent_exposes_web_search_tool` (espera WebSearch, código retorna vazio) NÃO deve ser corrigida silenciosamente — apenas reportada.
      Cobre: RF-04
      Acceptance criteria: todos os testes de `ChatTest.php` que invocam `generateResponse` fakeiam o GuardrailAgent e passam; `composer test` verde exceto, no máximo, a falha pré-existente documentada no PLAN (Open Questions).
      Testes: `tests/Feature/ChatTest.php` atualizado + gate `composer test`
