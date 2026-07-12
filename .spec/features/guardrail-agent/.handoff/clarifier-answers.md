# Clarifier answers — guardrail-agent

- **Q-01** (blocked messages in history): Blocked messages STAY in the database, marked as blocked, and are NEVER sent to the SellerAgent under any circumstance (excluded from its history, along with their redirect replies). Requires a flag/marker on the `messages` table. Blocked turns DO remain visible inside the guardrail's own history window.
- **Q-02** (company restriction category): Add new 6th verdict category `company_restriction`. When the restrictions field is empty, that check is disabled (mirror RF-05 empty-field rule).
- **Q-03** (failure mode): **Fail-closed.** On guardrail error/timeout the message is blocked and the SellerAgent is not invoked. (Developer explicitly chose this over the fail-open recommendation — security posture prioritized.)
- **Q-04** (history window): N = 10 messages (last 5 user/assistant pairs) before the current message; fewer if the conversation is shorter.
- **Q-05** (redirect text): Single fixed PT-BR string in code. Verdict category never revealed to the customer.
- **Q-06** (failure observability): Yes — on guardrail failure emit the activity-panel event AND the structured log with `verdict: "error"`, `category: null`. Extend CT-02/CT-03 to explicitly include `error` in the verdict enumeration.
- **Q-07** (pii definition): Block attempts to elicit/extract third-party PII or internal data. Customer volunteering their OWN contact info (name, address, phone) for a purchase is allowed. Encode distinction in RF-06 prompt criteria.
- **Q-08** (intent_change vs off_topic): `intent_change` = attempts to repurpose the assistant's role/persona ("agora você é...", "aja como..."), active regardless of configured alignments. `off_topic` = subject outside configured alignments only. No overlap.
