# Agent Seller — Project Description

## Overview

**Agent Seller é uma plataforma AAS (Agent as a Service)** criada para o ensino de IA. O produto entrega, para cada **empresa** que se cadastra, um **agente de IA da própria plataforma** que conversa com os **clientes finais** dessa empresa. A empresa conecta seu **CRM** à plataforma — inicialmente apenas o **Pipedrive**, com outros provedores previstos para o futuro — e a plataforma faz uma **varredura (scan)** do CRM para armazenar como aquela empresa funciona: pipelines, estágios, campos customizados, contatos e negócios.

O sistema tem **dois públicos distintos**: a **empresa (tenant)**, que administra a conta e conecta o CRM; e o **cliente final**, que apenas conversa com o agente. O cliente final entra por uma tela pública onde informa o **email** e recebe um **magic link** de login — sem senha. Como o mesmo email pode existir no CRM de **mais de uma empresa**, se houver múltiplos matches o cliente **seleciona com qual empresa quer conversar** antes de iniciar o chat.

No **MVP**, o agente é deliberadamente simples: **um único system prompt global da plataforma, escrito direto em código, e nada além disso**. O agente **não** injeta os dados escaneados do CRM no contexto da conversa nesta fase — o scan é armazenado para uso futuro (personalização por empresa, RAG, tools). As **conversas são persistidas** por cliente/empresa para permitir histórico e continuidade.

O **limite do MVP** é: cadastro de empresa por email/senha, conexão Pipedrive via API token pessoal, scan e armazenamento do CRM, login do cliente final por magic link com resolução de multi-empresa, e chat persistido com agente de system prompt fixo usando **OpenAI** via `laravel/ai`.

### Key Concepts

- **Empresa (Tenant):** organização que se cadastra na plataforma (email/senha), conecta seu CRM e passa a ter um agente disponível para seus clientes. Unidade de isolamento multi-tenant.
- **Cliente Final:** pessoa que conversa com o agente de uma empresa. Não tem senha; autentica por **magic link**. É identificada por **email casado contra os Persons escaneados** do CRM.
- **Agente:** o assistente de IA da plataforma. No MVP é **um system prompt global fixo em código** (mesmo para todas as empresas), servido via `laravel/ai` com provider **OpenAI**. Sem tools, sem RAG, sem dados do CRM no contexto nesta fase.
- **Conexão CRM:** vínculo entre uma empresa e seu Pipedrive, autenticado por **API token pessoal** informado pela empresa. Arquitetura preparada para múltiplos provedores no futuro; MVP só Pipedrive.
- **Scan (Varredura):** processo que lê o Pipedrive da empresa e **armazena localmente**: pipelines + stages, campos customizados, persons/contatos e deals/negócios.
- **Person / Contato:** contato importado do CRM. Fonte de verdade para casar o email do cliente final com uma ou mais empresas.
- **Magic Link:** link de login de uso único enviado por email ao cliente final, sem senha.
- **Resolução de Multi-empresa:** quando o email do cliente final existe em Persons de **mais de uma empresa**, a plataforma lista as empresas e o cliente escolhe com qual conversar.
- **Conversa:** sessão de chat persistida entre um cliente final e o agente de uma empresa específica, com suas mensagens.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3+, Laravel 13 (`laravel/framework ^13.17`) |
| AI SDK | `laravel/ai ^0.9` — provider **OpenAI** |
| Frontend | Livewire 4 (`livewire/livewire ^4.1`), Blade, `livewire/blaze ^1.0` |
| CSS | Tailwind CSS v4 (`@tailwindcss/vite`), Vite 8 |
| Database | SQLite (default; `DB_CONNECTION=sqlite`) |
| Queue | Database driver (`QUEUE_CONNECTION=database`) — para jobs de scan |
| Mail | Mailer configurável (`MAIL_MAILER=log` em dev) — envio de magic link |
| Integração CRM | Pipedrive REST API (API token pessoal); arquitetura extensível a outros CRMs |
| Testing | Pest 4 (`pestphp/pest ^4.7`), `pest-plugin-laravel`, Mockery, Faker |
| Dev env | Laravel Sail (Docker), Pail (logs), Boost (MCP) |
| Tooling | Pint (formatação), Larastan 3 (análise estática) |
| Starter | `laravel/blank-livewire-starter-kit` |

## Core Workflows

### 1. Cadastro e login da empresa

1. A empresa acessa a plataforma e se registra com **email e senha** (fluxo padrão do starter kit Livewire).
2. Faz login e chega ao painel do tenant.
3. Ainda sem CRM conectado, o painel orienta a próxima etapa: conectar o Pipedrive.

### 2. Conexão do CRM (Pipedrive)

1. No painel, a empresa escolhe **conectar Pipedrive**.
2. Informa seu **API token pessoal** do Pipedrive.
3. A plataforma **valida o token** contra a API do Pipedrive (ex.: chamada de verificação da conta).
4. Persiste a **Conexão CRM** vinculada à empresa. Token armazenado de forma segura (criptografado).
5. Dispara o **scan** (workflow 3).

> A conexão é modelada de forma agnóstica ao provedor para permitir outros CRMs no futuro; no MVP apenas o driver Pipedrive é implementado.

### 3. Scan e armazenamento do CRM

1. Ao conectar (ou sob demanda), um **job em fila** varre o Pipedrive da empresa.
2. Recupera e **armazena localmente**, associado à empresa:
   - **Pipelines + stages** (funis e estágios);
   - **Campos customizados** (custom fields de deals/persons/orgs);
   - **Persons/contatos** (base para o match do cliente final);
   - **Deals/negócios**.
3. Registra o status/resultado do scan (sucesso, contagens, erros) para exibição no painel.

> No MVP os dados escaneados são **armazenados mas não injetados** no contexto do chat — servem de base para personalização futura.

### 4. Login do cliente final por magic link

1. O cliente final acessa a **tela pública de chat** e informa seu **email**.
2. A plataforma casa o email contra os **Persons escaneados** de todas as empresas.
   - **Nenhum match:** o cliente não pode entrar (não existe em nenhum CRM conectado); mensagem apropriada.
   - **Um match:** segue direto para aquela empresa.
   - **Múltiplos matches:** após autenticar, o cliente **seleciona a empresa** com quem quer conversar (workflow 5).
3. A plataforma envia um **magic link** de uso único ao email.
4. O cliente clica no link, é autenticado (sem senha) e entra na sessão de chat.

### 5. Seleção de empresa (multi-empresa)

1. Quando o email do cliente existe em Persons de **mais de uma empresa**, a plataforma lista as empresas correspondentes.
2. O cliente **escolhe** com qual empresa deseja conversar.
3. A escolha define o contexto (empresa/agente) da conversa que será iniciada.

### 6. Conversa com o agente

1. Com empresa selecionada, o cliente inicia/retoma uma **Conversa** persistida.
2. Cada mensagem do cliente é enviada ao agente via `laravel/ai` (**OpenAI**), usando o **system prompt global fixo** da plataforma.
3. A resposta do agente é exibida no chat e **persistida** (conversa + mensagens) por cliente/empresa.
4. O histórico fica disponível para continuidade em acessos futuros.

> No MVP o agente é **apenas o system prompt global** — sem tools, sem dados do CRM no contexto. Toda personalização por empresa é escopo futuro.
