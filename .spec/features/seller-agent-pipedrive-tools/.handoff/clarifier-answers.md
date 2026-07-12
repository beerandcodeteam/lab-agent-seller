# Clarifier answers — seller-agent-pipedrive-tools

Developer decisions for Q-01…Q-10 (all confirmed). Apply in-place, increment version, remove the marker at line 145.

**Q-01 (marker) — deal selection:** Resolve to the most-recently-updated OPEN deal (`deal_status = open`, order by `updated_at` desc, first). If the person has no open deal → reads return RF-10 "sem deal". Mutations MUST never silently target a closed deal — refuse (ties to Q-10).

**Q-02 — client→person match:** Match `conversation->client` to `crm_person` case-insensitively (normalize `Str::lower(trim())`, consistent with domain_rules magic-link normalization), scoped to `conversation->user`'s `crm_connection` (tenant isolation, RNF-02). On multiple matched persons, UNION all their deals into the Q-01 selection.

**Q-03 — stage id kind:** RF-05 list-pipelines exposes the **Pipedrive external stage id** (+ name); RF-06 move accepts that same external id; driver PUTs it live. No local↔external translation. Document explicitly to avoid id mismatch.

**Q-04 — cross-pipeline move:** SAME-PIPELINE ONLY. RF-06 validates the target stage belongs to the deal's current pipeline; refuse otherwise.

**Q-05 — lost reason:** `lost_reason` is a free-text string passed through as-is. No configured-reason validation, no extra tool. Stays 8 tools.

**Q-06 — null stage/status output:** RF-01 returns live Pipedrive fields as-provided; when stage/status absent, emit explicit null / "desconhecido" markers. Never fabricate. "sem estágio" is distinct from RF-10 "sem deal".

**Q-07 — mirror freshness boundary:** State that deal-id resolution relies on the local mirror (`deals.external_id`, refreshed on scan) and is bounded by scan freshness. A live 404 on a mirrored/resolved id → RF-11 "não consegui confirmar" (falha), NOT RF-10 "sem deal".

**Q-08 — notes scope:** Fetch BOTH deal notes and person notes; return a merged, source-tagged list. Person notes remain available under RF-10 when there is no deal.

**Q-09 — history filter:** RF-02 filters the Pipedrive `/deals/{id}/flow` stream to stage-change events only (origin, destination, timestamp); drop other flow types.

**Q-10 — closed-deal mutations:** If the resolved deal is already won/lost, move/won/lost mutations REFUSE with an explicit marker (never silently reopen/re-close). Consistent with Q-01.
