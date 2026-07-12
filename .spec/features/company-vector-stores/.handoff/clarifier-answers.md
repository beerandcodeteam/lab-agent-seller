# Clarifier answers — company-vector-stores

Apply these resolutions in-place to SPEC.md, remove both `[NEEDS CLARIFICATION]` markers, and bump the version. Fold the SDK-verified facts into RIGID (contract) requirements so the planner cannot re-open them.

## Developer decisions

**Q-01 — Remote delete/remove failure semantics (RNF-05, RF-05/RF-08): PRESERVE + IDEMPOTENT-404.**
- OpenAI returns 404 / "already deleted" → treat as idempotent SUCCESS, delete the local record.
- Real failure (5xx / network / timeout) → PRESERVE the local record intact, surface a PT-BR error, allow manual retry. The local record is never removed without remote confirmation (or a confirmed not-found). No orphan-reconciliation job.

**Q-04 — Indexing status granularity: AGGREGATE ONLY (RF-07 downgraded).**
- The `laravel/ai` SDK exposes only store-level `StoreFileCounts{completed, pending, failed}` + `Store->ready` (verified in `vendor/laravel/ai/src/Store.php` / `Responses/Data/StoreFileCounts.php`). There is NO per-file status API.
- RF-07 acceptance is store-level: UI shows the store's aggregate state (processing / ready / N failed). An individual file row inherits "processing" until the store reports `ready`. Rewrite the RF-07 AC to aggregate terms; drop any per-file status claim.

**Q-05a — Allowed upload file types: PROVIDER-ALLOWED SET.**
- Accept only the file types the OpenAI File Search provider supports for indexing (the provider's supported-document set), not an arbitrary product whitelist. Validation rejects anything outside that set with a PT-BR message. State the source of truth as "tipos suportados pelo File Search da OpenAI" rather than a frozen hardcoded list.

**Q-05b — Max file size: 512 MB (OpenAI Files API limit).**
- Per-file cap = 512 MB, the OpenAI Files API ceiling. This is above default PHP/Livewire limits, so it becomes an infra RNF: `upload_max_filesize` / `post_max_size` (PHP), Livewire temporary-upload max, and any Sail/nginx `client_max_body_size` must permit 512 MB. Add this as an explicit RNF the plan must satisfy.

## SDK-verified resolutions (fold into RIGID; do NOT re-ask)

Verified against `vendor/laravel/ai/src/{Stores,Store}.php`, `Responses/AddedDocumentResponse.php`, `Providers/Tools/FileSearch.php`.

**Q-02 / Q-03 — File identifiers + removal.**
- `Store::add(UploadedFile)` returns `AddedDocumentResponse` exposing BOTH `id` (the vector-store document id) and `fileId` (the Files API object id). Persist BOTH on the local file record (e.g. `openai_document_id` + `openai_file_id`).
- `Store::remove($documentId, deleteFile: true)` removes the document from the store AND deletes the underlying File object. RF-08 uses `deleteFile: true` so no orphan File/storage cost remains. `$documentId` = the persisted document id.

**Q-06 — Deleting a store does NOT auto-delete Files.**
- `Store::delete()` / `Stores::delete($id)` deletes only the vector store, leaving File objects orphaned on OpenAI. To satisfy RF-05 ("arquivos não existem na OpenAI"), the delete flow first iterates the store's tracked documents calling `remove($documentId, deleteFile: true)`, THEN deletes the store. Apply the same PRESERVE + idempotent-404 rule (Q-01) to each step.

**Q-07 — Name/description edit is LOCAL-ONLY.**
- The `Stores` facade has only `create` / `get` / `delete` — no update. The `Store` object does not even expose `description`. Name + description are a LOCAL catalog used to build the SellerAgent's tool context (`instructions()` / FileSearch labelling). RF-04 edit persists locally and keeps the OpenAI `store id`; it performs NO OpenAI call and cannot fail remotely.

**Q-08 — Indexing status polling.**
- Poll `Store->refresh()` while `!$store->ready`; persist the aggregate (`completed/pending/failed`, `ready`) to the local store record; stop polling when `ready` (pending == 0) or a failure count is reported. Local aggregate is the UI source of truth (cache of remote), mirroring the existing `ScanCard` polling pattern.

**Q-09 — TO BE diagram legend numbering.**
- Fix the legend: the name+description catalog is RF-11, not RF-08 (RF-08 = remove file). Mechanical traceability fix.

## Native FileSearch wiring (confirm in RIGID)

- `new \Laravel\Ai\Providers\Tools\FileSearch($storeIds)` takes an array of vector-store ids (or `Store` objects). SellerAgent (RF-09/RF-10) builds it from the calling company's (User's) persisted store ids. When the company has zero stores, the tool is omitted entirely (no empty FileSearch). Store name+description are injected into the agent instructions so it knows what each store contains.
