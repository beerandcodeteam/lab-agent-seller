# Phases: company-vector-stores

Gerado por /plan a partir de PLAN.md — view executável para `./ralph.sh .spec/features/company-vector-stores/PHASES.md`.

## Phase 1: Fundação de infra — migrations e limites de upload

Antes de implementar, leia:
1. `.spec/features/company-vector-stores/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/company-vector-stores/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T01 — Migrations: vector_stores, vector_store_files e lookup file_indexing_statuses
      Arquivos: `database/migrations/2026_07_12_000004_create_file_indexing_statuses_table.php`, `database/migrations/2026_07_12_000005_create_vector_stores_table.php`, `database/migrations/2026_07_12_000006_create_vector_store_files_table.php`
      Mudança: seguir o padrão de `2026_07_11_000007_create_crm_scans_table.php` e das lookups. `file_indexing_statuses`: `id`, `name`, `slug` (unique), timestamps. `vector_stores`: `id`, `user_id` (constrained cascadeOnDelete), `openai_vector_store_id` (string unique), `name` (string), `description` (text), timestamps. `vector_store_files`: `id`, `vector_store_id` (constrained cascadeOnDelete), `openai_document_id` (string, indexado), `openai_file_id` (string), `filename` (string), `file_indexing_status_id` (nullable constrained), timestamps. Sem soft delete.
      Cobre: RF-01, RF-04, RF-06, RF-07 (persistência)
      Acceptance criteria: migrar cria as 3 tabelas; `openai_vector_store_id` é unique; apagar um `User` remove seus `vector_stores` e, em cascata, seus `vector_store_files`.
      Testes: `tests/Feature/Ai/VectorStoreSchemaTest.php` — afirma colunas, unique de `openai_vector_store_id` e cascade em dois níveis.
- [ ] T06 — Infra: aceitar upload de 512 MB (PHP/Livewire/Sail)
      Arquivos: `config/livewire.php` (publicar se ausente), `docker/php/upload.ini` + referência em `compose.yaml` (montar php.ini custom no container Sail)
      Mudança: elevar `upload_max_filesize` e `post_max_size` (PHP), o limite de temporary-upload do Livewire e o `client_max_body_size` de qualquer nginx à frente para ≥512 MB, de modo que o arquivo chegue à validação da aplicação antes de qualquer corte de infra. Pode exigir rebuild do container Sail.
      Cobre: RNF-06 (viabiliza RNF-03)
      Acceptance criteria: um upload de arquivo próximo de 512 MB atravessa PHP/Livewire/proxy e chega à regra de validação da aplicação (não é cortado por `post_max_size`/temp-upload/body-size); os limites efetivos reportam ≥512 MB.
      Testes: verificação manual/documentada (limite de infra não é coberto por Pest); a regra de tamanho da aplicação vive em T04.

## Phase 2: Persistência — models, factories, relationship e seed

Antes de implementar, leia:
1. `.spec/features/company-vector-stores/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/company-vector-stores/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T02 — Models, factories, relationship em User e seed do lookup
      Arquivos: `app/Models/VectorStore.php`, `app/Models/VectorStoreFile.php`, `app/Models/FileIndexingStatus.php`, `app/Models/User.php`, `database/factories/VectorStoreFactory.php`, `database/factories/VectorStoreFileFactory.php`, `database/seeders/LookupSeeder.php`
      Mudança: `FileIndexingStatus` usa `IsLookup` (espelha `ScanStatus`), `hasMany VectorStoreFile`. `VectorStore` belongsTo User + hasMany VectorStoreFile, fillable + PHPDoc `@property` + HasFactory. `VectorStoreFile` belongsTo VectorStore + belongsTo FileIndexingStatus (relacionamento `indexingStatus`), fillable + HasFactory. `User::vectorStores(): HasMany` com PHPDoc. `LookupSeeder`: adicionar `file_indexing_statuses` com slugs `pending`/`in_progress`/`completed`/`failed` (nomes PT-BR), idempotente por slug. Factories com states úteis.
      Cobre: RF-03, RF-06, RF-09, RF-11 (relacionamentos), lookup slug-keyed §4
      Acceptance criteria: `auth-user->vectorStores`, `store->files` e `file->indexingStatus` resolvem; rodar `LookupSeeder` duas vezes mantém a contagem e cria os 4 slugs novos; `User.php` ganha só o método `vectorStores` sem alterar fillable/casts existentes.
      Testes: `tests/Feature/Ai/VectorStoreModelTest.php` — travessia de relacionamentos e cascade; `lookup_seeder_is_idempotent` estendido para os 4 slugs.

## Phase 3: Serviço de negócio e wiring do agente

Antes de implementar, leia:
1. `.spec/features/company-vector-stores/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/company-vector-stores/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T03 — VectorStoreService: chamadas OpenAI, rollback e mapeamento de status
      Arquivos: `app/Services/Ai/VectorStoreService.php`, `app/Services/Ai/Exceptions/VectorStoreOperationException.php`
      Mudança: fat service com tipos explícitos. `createForCompany` chama `Laravel\Ai\Stores::create(name, description)` e persiste `VectorStore` SÓ após `Store->id` não-nulo; falha → não persiste (RNF-02) + `VectorStoreOperationException` PT-BR (RF-01/RF-02). `rename` = update local apenas, sem chamada OpenAI (RF-04). `addFile` = `Stores::get(...)->add(UploadedFile)` e persiste `openai_document_id = AddedDocumentResponse->id` + `openai_file_id = ->fileId` (RF-06). `removeFile` = `Store::remove(openai_document_id, deleteFile:true)` então apaga local (RF-08). `deleteStore` NESTA ORDEM: (1) itera `remove(documentId, deleteFile:true)` por arquivo, (2) `Stores::delete(store id)`, (3) apaga local (RF-05). `handleRemoteDeletion` privado: 404 → sucesso idempotente (apaga local); 5xx/rede → preserva local + exceção (RNF-05). `indexingState` lê `fileCounts` + `ready` e deriva estado agregado (RF-07), sem status por-arquivo.
      Cobre: RF-01, RF-02, RF-04, RF-05, RF-06, RF-07, RF-08, RNF-02, RNF-05, CT-03
      Acceptance criteria: com `Laravel\Ai\Stores::fake()` — criação persiste id retornado e falha simulada não deixa órfão; rename não emite chamada OpenAI e mantém o id; addFile persiste ambos os ids; removeFile e deleteStore chamam `remove(deleteFile:true)` na ordem correta e limpam o local; 404 apaga local (idempotente); 5xx preserva local e lança `VectorStoreOperationException`.
      Testes: `tests/Feature/Ai/VectorStoreServiceTest.php` — cobre os cenários acima via `FakeStoreGateway`.
- [ ] T05 — SellerAgent: wiring de FileSearch escopada + catálogo em instructions()
      Arquivos: `app/Ai/Agents/SellerAgent.php`
      Mudança: em `tools()`, montar o array das 9 tools Pipedrive, derivar `$storeIds = $this->conversation->user->vectorStores()->pluck('openai_vector_store_id')->all()`; se não-vazio, adicionar `new \Laravel\Ai\Providers\Tools\FileSearch($storeIds)` (RF-09/CT-01); se vazio, não adicionar (RF-10). Em `instructions()`, quando houver stores, anexar bloco PT-BR `<knowledge_bases>` com name+description de cada store (RF-11), preservando a renderização de `{company_name}`/`{company_playbook}`.
      Cobre: RF-09, RF-10, RF-11, CT-01
      Acceptance criteria: empresa com N (>0) stores → `tools()` contém exatamente 1 `FileSearch` cujo `ids()` é o conjunto dos N ids da empresa (nenhum de outra) e as 9 tools Pipedrive permanecem; empresa sem stores → nenhum `FileSearch`; `instructions()` contém nome+descrição de cada store quando há, e nenhum catálogo quando não há.
      Testes: `tests/Feature/Ai/SellerAgentFileSearchTest.php` — afirma presença/ausência de `FileSearch`, exatidão dos ids e o catálogo em `instructions()`.

## Phase 4: UI Livewire de gestão

Antes de implementar, leia:
1. `.spec/features/company-vector-stores/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/company-vector-stores/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T04 — Livewire Agent\VectorStores: UI CRUD + upload + polling + rota
      Arquivos: `app/Livewire/Agent/VectorStores.php`, `resources/views/livewire/agent/vector-stores.blade.php`, `routes/web.php`
      Mudança: thin component (valida/autoriza/delega ao `VectorStoreService` via method injection, como `Connect::connect`). Rota `Route::get('/agente/bases-de-conhecimento', VectorStores::class)->name('agent.vector-stores')` no grupo `middleware('auth')`. `render()` lista `auth()->user()->vectorStores()->with('files')->get()` (RF-03) com empty-state PT-BR `x-empty-state` (UI-01). Criar/editar: `#[Validate]` `name` (`required|string|max:255`) e `description` (`required|string`) com mensagens PT-BR (UI-02), delegando a `createForCompany`/`rename`; erro do serviço → banner PT-BR `x-alert` sem detalhe técnico (UI-04). `authorizeStore()` com `abort_unless($store->user_id === auth()->id(), 403)` em toda ação (RNF-01). Upload: `WithFileUploads`, `#[Validate]` `upload` com regra de tipo (extensões File Search OpenAI) e `max:524288` (RNF-03/UI-03), delegando a `addFile`. Lista de arquivos com nome, `x-badge` de status derivado de `indexingState` e botão remover (`removeFile`). `wire:poll` enquanto algum store `!ready` atualiza o badge (RF-07). Reuso de `x-card`/`x-badge`/`x-empty-state`/`x-alert`/`x-input`/`x-button`.
      Cobre: UI-01, UI-02, UI-03, UI-04, RF-03, RF-07, RNF-01, RNF-03, RNF-04
      Acceptance criteria: a lista mostra só stores do autenticado (nenhum de outra empresa); submeter nome/descrição vazios bloqueia e mostra msg PT-BR do campo; operar store de outra empresa retorna `403`; upload de tipo inválido ou >512 MB é rejeitado com msg PT-BR; erro do serviço vira banner PT-BR sem stack trace e a tela permanece consistente; o badge de status reflete `indexingState` e muda para "pronto" quando `ready`.
      Testes: `tests/Feature/Ai/VectorStoresComponentTest.php` — escopo, validação PT-BR, 403 cross-tenant, rejeição de upload, banner de erro e badge de status.

## Phase 5: Gate de qualidade

Antes de implementar, leia:
1. `.spec/features/company-vector-stores/SPEC.md` — requisitos RIGID que esta fase cobre
2. `.spec/features/company-vector-stores/PLAN.md` — decomposição completa, dependências e riscos

- [ ] T07 — Gate completo: Pint + PHPStan + Pest
      Arquivos: nenhum arquivo de produto novo (execução de ferramentas)
      Mudança: rodar `vendor/bin/sail bin pint --format agent`, `composer types:check` e `vendor/bin/sail artisan test --compact` (gate `composer test`); corrigir formatação e violações de PHPStan nível 7 introduzidas por T01–T05 até o gate ficar verde.
      Cobre: AGENTS.md §1/§3 (gate obrigatório), todos os ACs testáveis
      Acceptance criteria: `composer test` (config:clear + lint:check + types:check + test) passa integralmente; nenhuma violação de Pint ou PHPStan nível 7; toda a suíte `tests/Feature/Ai/*` da feature verde.
      Testes: a suíte criada em T01–T05.
