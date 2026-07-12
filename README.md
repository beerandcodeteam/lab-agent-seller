# Lab Agent Seller

Aplicação **Laravel 13 + Livewire 4** que demonstra um agente de vendas com IA.
Empresas conectam um CRM (driver Pipedrive), um job em fila (`ScanCrmConnection`)
espelha pipelines/stages/campos/pessoas/deals para modelos locais, clientes
entram via magic link (guard `auth:client`) e conversam com o agente de vendas
streaming `SellerAgent` (`laravel/ai`).

## Repositórios e ferramentas usados

| Projeto | Descrição | Link |
|---------|-----------|------|
| Beer and Code Harness | Harness de agentes (skills, hooks, comandos e guidelines) usado no projeto | https://github.com/beerandcodeteam/beer-and-code-harness |
| Langfuse | Observabilidade de LLM (traces, custos, avaliações) | https://github.com/langfuse/langfuse |
| Mem0 | Camada de memória de longo prazo para o agente | https://github.com/mem0ai/mem0 |

Pacotes de integração:

- **Langfuse** — via [`axyr/laravel-langfuse`](https://packagist.org/packages/axyr/laravel-langfuse) (região US, projeto `ai-lab`).
- **Mem0** — via API HTTP (`MEM0_API_KEY`, `MEM0_BASE_URL`, `MEM0_TIMEOUT` no `.env`).

## Aulas

Slides gravados neste repositório:

- [Aula 1](slides-da-aula/aula-dia-1/index.html) — `slides-da-aula/aula-dia-1/index.html`
- [Aula 2](slides-da-aula/aula-dia-2/aula.html) — `slides-da-aula/aula-dia-2/aula.html`

Abra os arquivos `.html` direto no navegador.

## Stack

- PHP 8.5 · Laravel 13 · Livewire 4
- `laravel/ai` (agente streaming)
- Laravel Sail (Docker) · PostgreSQL 18 · Redis · Mailpit
- Pest 4 · Larastan (PHPStan nível 7) · Pint
- Tailwind CSS v4 · Vite

## Setup do ambiente

Pré-requisitos: **Docker**, **Composer 2**, **Node 22 + npm**.

```sh
# 1. Clonar
git clone git@github.com:beerandcodeteam/lab-agent-seller.git
cd lab-agent-seller

# 2. Instalar deps PHP (necessário pra ter o binário do Sail)
composer install

# 3. Subir os containers (laravel.test PHP 8.5, pgsql 18, redis, mailpit)
vendor/bin/sail up -d

# 4. Setup completo: copia .env, key:generate, migrate --force, npm install, npm run build
vendor/bin/sail composer setup

# 5. Configurar chaves de API no .env
#    MEM0_API_KEY=...          (Mem0)
#    LANGFUSE_PUBLIC_KEY=...    (Langfuse - região US)
#    LANGFUSE_SECRET_KEY=...
```

## Levantar o projeto

```sh
# Sobe os containers (se ainda não estiverem no ar)
vendor/bin/sail up -d

# Inicia os processos de desenvolvimento (servidor, queue, vite, logs)
vendor/bin/sail composer dev

# Abrir no navegador
vendor/bin/sail open
```

> `QUEUE_CONNECTION=database` local: o job `ScanCrmConnection` precisa de um
> worker de fila rodando. `composer dev` já sobe o worker junto.

Mailpit captura os e-mails de saída (incluindo os magic links) — acesse via
porta do Mailpit exposta pelo Sail.

## Comandos úteis

| Comando | Função |
|---------|--------|
| `vendor/bin/sail composer dev` | Sobe servidor + queue + vite + logs |
| `vendor/bin/sail artisan test` | Roda a suíte Pest |
| `vendor/bin/sail composer test` | Gate completo: lint + types + testes |
| `vendor/bin/sail composer lint` | Formata PHP (Pint) |
| `vendor/bin/sail composer types:check` | Análise estática (PHPStan nível 7) |
| `vendor/bin/sail npm run build` | Build dos assets frontend |
