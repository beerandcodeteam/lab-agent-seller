# Agent Seller — Database Schema

<!-- inputs: project-description.md@sha256:76f9e62697bf user-stories.md@sha256:5e971be1f269 -->

## Overview

O modelo de dados gira em torno de dois atores autenticáveis distintos: a **empresa** (tenant), persistida na tabela `users` do starter Laravel (login por email/senha), e o **cliente final** (`clients`), identidade global chaveada por email único e autenticada por **magic link** (`magic_links`). Cada empresa tem uma **conexão de CRM** (`crm_connections`, uma ativa por empresa) que aponta para um provedor (`crm_providers`, lookup) e cujo **scan** (`crm_scans`) importa e armazena localmente os dados do Pipedrive: **pipelines** + **pipeline_stages**, **custom_fields**, **crm_persons** e **deals**.

A associação entre cliente e empresa **não é uma tabela** — é derivada em runtime pelo match do email do cliente contra `crm_persons`. Quando o cliente escolhe uma empresa e inicia o chat, isso materializa uma **conversation** (par único `client` + `user`) com suas **messages**.

Convenções em vigor: **Laravel 13 / Eloquent** — tabelas plural snake_case, PK `id bigint increment`, FKs `<singular>_id`, `created_at`/`updated_at`. Todo campo categórico (status, tipo, provedor, papel) é uma **lookup table** com FK (sem colunas enum). **Sem soft deletes** no MVP (decisão do desenvolvedor); re-scan substitui dados via chave única `(crm_connection_id, external_id)`.

## Schema (DBML)

```dbml
// ── Lookup tables ───────────────────────────────────────────────

Table crm_providers {
  id bigint [pk, increment]
  name varchar [not null]
  slug varchar [unique, not null]
  is_active boolean [not null, default: true]
  created_at timestamp
  updated_at timestamp
}

Table scan_statuses {
  id bigint [pk, increment]
  name varchar [not null]
  slug varchar [unique, not null]
  created_at timestamp
  updated_at timestamp
}

Table custom_field_entities {
  id bigint [pk, increment]
  name varchar [not null]
  slug varchar [unique, not null]
  created_at timestamp
  updated_at timestamp
}

Table deal_statuses {
  id bigint [pk, increment]
  name varchar [not null]
  slug varchar [unique, not null]
  created_at timestamp
  updated_at timestamp
}

Table message_roles {
  id bigint [pk, increment]
  name varchar [not null]
  slug varchar [unique, not null]
  created_at timestamp
  updated_at timestamp
}

// ── Empresa (tenant) ────────────────────────────────────────────

Table users {
  id bigint [pk, increment]
  name varchar [not null]                 // nome da empresa
  email varchar [unique, not null]
  email_verified_at timestamp [null]
  password varchar [not null]
  remember_token varchar [null]
  created_at timestamp
  updated_at timestamp
}

// ── Conexão de CRM e scan ───────────────────────────────────────

Table crm_connections {
  id bigint [pk, increment]
  user_id bigint [ref: > users.id, not null]         // uma conexão ativa por empresa (unique em app)
  crm_provider_id bigint [ref: > crm_providers.id, not null]
  api_token text [not null]                           // criptografado
  last_validated_at timestamp [null]
  created_at timestamp
  updated_at timestamp
}

Table crm_scans {
  id bigint [pk, increment]
  crm_connection_id bigint [ref: > crm_connections.id, not null]
  scan_status_id bigint [ref: > scan_statuses.id, not null]
  pipelines_count integer [not null, default: 0]
  custom_fields_count integer [not null, default: 0]
  persons_count integer [not null, default: 0]
  deals_count integer [not null, default: 0]
  error_message text [null]
  started_at timestamp [null]
  finished_at timestamp [null]
  created_at timestamp
  updated_at timestamp
}

// ── Dados escaneados do CRM ─────────────────────────────────────

Table pipelines {
  id bigint [pk, increment]
  crm_connection_id bigint [ref: > crm_connections.id, not null]
  external_id varchar [not null]           // id no Pipedrive; unique(crm_connection_id, external_id)
  name varchar [not null]
  created_at timestamp
  updated_at timestamp
}

Table pipeline_stages {
  id bigint [pk, increment]
  pipeline_id bigint [ref: > pipelines.id, not null]
  external_id varchar [not null]
  name varchar [not null]
  order_index integer [not null, default: 0]
  created_at timestamp
  updated_at timestamp
}

Table custom_fields {
  id bigint [pk, increment]
  crm_connection_id bigint [ref: > crm_connections.id, not null]
  custom_field_entity_id bigint [ref: > custom_field_entities.id, not null]
  external_id varchar [not null]           // unique(crm_connection_id, external_id)
  name varchar [not null]
  field_key varchar [null]
  field_type varchar [null]
  created_at timestamp
  updated_at timestamp
}

Table crm_persons {
  id bigint [pk, increment]
  crm_connection_id bigint [ref: > crm_connections.id, not null]
  external_id varchar [not null]           // unique(crm_connection_id, external_id)
  name varchar [null]
  email varchar [null]                     // indexado; base do match do cliente final
  phone varchar [null]
  created_at timestamp
  updated_at timestamp
}

Table deals {
  id bigint [pk, increment]
  crm_connection_id bigint [ref: > crm_connections.id, not null]
  pipeline_id bigint [ref: > pipelines.id, null]
  pipeline_stage_id bigint [ref: > pipeline_stages.id, null]
  crm_person_id bigint [ref: > crm_persons.id, null]
  deal_status_id bigint [ref: > deal_statuses.id, null]
  external_id varchar [not null]           // unique(crm_connection_id, external_id)
  title varchar [not null]
  value decimal [null]
  created_at timestamp
  updated_at timestamp
}

// ── Cliente final, login e chat ─────────────────────────────────

Table clients {
  id bigint [pk, increment]
  email varchar [unique, not null]         // identidade global
  name varchar [null]
  created_at timestamp
  updated_at timestamp
}

Table magic_links {
  id bigint [pk, increment]
  email varchar [not null]                 // indexado
  token varchar [unique, not null]         // hash do token de uso único
  expires_at timestamp [not null]          // criado_em + 15 min
  used_at timestamp [null]
  created_at timestamp
  updated_at timestamp
}

Table conversations {
  id bigint [pk, increment]
  client_id bigint [ref: > clients.id, not null]
  user_id bigint [ref: > users.id, not null]   // empresa escolhida; unique(client_id, user_id)
  created_at timestamp
  updated_at timestamp
}

Table messages {
  id bigint [pk, increment]
  conversation_id bigint [ref: > conversations.id, not null]
  message_role_id bigint [ref: > message_roles.id, not null]
  content text [not null]
  created_at timestamp
  updated_at timestamp
}
```

## Relationships

- Uma **empresa** (`users`) tem uma **conexão de CRM** (`crm_connections`) ativa (1:1 em app; FK 1:N no schema).
- Uma **conexão de CRM** pertence a um **provedor** (`crm_providers`).
- Uma **conexão de CRM** tem muitos **scans** (`crm_scans`); cada scan tem um **status** (`scan_statuses`).
- Uma **conexão de CRM** tem muitos **pipelines**, **custom_fields**, **crm_persons** e **deals**.
- Um **pipeline** tem muitos **pipeline_stages**.
- Um **custom_field** pertence a uma **entidade** (`custom_field_entities`: deal/person/org).
- Um **deal** pertence a um **pipeline**, um **pipeline_stage**, um **crm_person** e um **deal_status** (todos opcionais).
- Um **cliente** (`clients`) tem muitas **conversations**; uma **conversation** pertence a um **cliente** e a uma **empresa** (`users`), par único.
- Uma **conversation** tem muitas **messages**; cada **message** tem um **papel** (`message_roles`: user/assistant).
- **magic_links** referencia o cliente por **email** (não por FK), pois o registro do cliente pode ser criado só na autenticação.

## Lookup Table Seeds

- **crm_providers:** `pipedrive` (Pipedrive). *(único ativo no MVP; outros provedores no futuro)*
- **scan_statuses:** `pending` (Pendente), `running` (Em andamento), `success` (Sucesso), `failed` (Erro).
- **custom_field_entities:** `deal` (Negócio), `person` (Pessoa), `organization` (Organização).
- **deal_statuses:** `open` (Aberto), `won` (Ganho), `lost` (Perdido).
- **message_roles:** `user` (Cliente), `assistant` (Agente).

## Notes & Conventions

- **Stack:** Laravel 13 / Eloquent. Tabelas plural snake_case, PK `id bigint increment`, FKs `<singular>_id`, timestamps `created_at`/`updated_at`.
- **Sem enum columns:** todo categórico é lookup com FK (decisão de convenção da harness).
- **Sem soft deletes** no MVP (decisão do desenvolvedor). Re-scan (US-3.1/US-3.2) faz upsert por `external_id` — garantir índice único `(crm_connection_id, external_id)` em `pipelines`, `custom_fields`, `crm_persons`, `deals`, e `(pipeline_id, external_id)` em `pipeline_stages`.
- **Match do cliente final** (US-4.1/US-5.1): índice em `crm_persons.email` e em `magic_links.email` para casar o email rápido across empresas.
- **Magic link** (US-4.1/US-4.2): `expires_at` = criação + 15 min; `used_at` marca uso único. `token` armazenado como hash.
- **Uma conexão ativa por empresa:** enforce via índice único em `crm_connections.user_id` na migration (modelado como FK 1:N para permitir histórico/reconexão futura sem quebrar o schema).
- **Conversa = associação cliente↔empresa:** índice único `(client_id, user_id)` em `conversations` garante uma conversa por par (US-6.2 retoma histórico).
- **Não persistidos:** o **Agente** vive como system prompt global em código (não é tabela); a **Resolução de Multi-empresa** é derivada em runtime do match `crm_persons.email` (sem tabela própria).
- **`api_token`** em `crm_connections` deve ser criptografado (cast `encrypted` no Eloquent).
