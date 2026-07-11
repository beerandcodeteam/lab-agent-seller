# Agent Seller — User Stories

<!-- inputs: project-description.md@sha256:76f9e62697bf -->

## Overview

Agent Seller é uma plataforma AAS (Agent as a Service) para ensino de IA. Cada **empresa** conecta seu CRM (Pipedrive no MVP), a plataforma **escaneia e armazena** pipelines, campos customizados, contatos e negócios, e disponibiliza um **agente de IA** (system prompt global fixo) para os **clientes finais** dessa empresa conversarem via chat. O cliente final autentica por **magic link** e, se seu email existir em mais de uma empresa, escolhe com qual conversar.

**User Types:**
- **Empresa (Tenant)** - organização que se cadastra por email/senha, conecta o CRM e administra a conexão/dados escaneados.
- **Cliente Final** - pessoa que conversa com o agente; autentica por magic link, sem senha; identificada por email casado contra os Persons do CRM.
- **Sistema/Agente** - processos automáticos da plataforma: scan do CRM, envio de magic link e o agente de IA que responde no chat.

---

## 1. Cadastro e Autenticação da Empresa

### US-1.1: Cadastro da empresa
**As a** empresa
**I want to** me cadastrar na plataforma com email e senha
**So that** eu tenha uma conta para conectar meu CRM e disponibilizar o agente

**Acceptance Criteria:**
- [ ] Formulário exige nome da empresa, email válido e senha.
- [ ] Email deve ser único; email já cadastrado retorna erro de validação.
- [ ] Senha respeita a regra mínima do framework (mín. 8 caracteres) e é armazenada com hash.
- [ ] Após cadastro bem-sucedido, a empresa é autenticada e redirecionada ao painel.

**Expected Result:** Conta da empresa criada e autenticada, com painel acessível e ainda sem CRM conectado.

---

### US-1.2: Login e logout da empresa
**As a** empresa
**I want to** entrar e sair da minha conta com email e senha
**So that** eu acesse com segurança o painel do meu tenant

**Acceptance Criteria:**
- [ ] Login com credenciais corretas leva ao painel do tenant.
- [ ] Credenciais inválidas exibem mensagem de erro sem revelar qual campo falhou.
- [ ] Logout encerra a sessão e redireciona à tela de login.
- [ ] Rotas do painel exigem autenticação da empresa.

**Expected Result:** A empresa acessa apenas seu próprio painel quando autenticada e é bloqueada quando não.

---

## 2. Conexão do CRM

### US-2.1: Conectar Pipedrive via API token
**As a** empresa
**I want to** informar meu API token pessoal do Pipedrive
**So that** a plataforma possa acessar meu CRM e escaneá-lo

**Acceptance Criteria:**
- [ ] O painel oferece a ação "Conectar Pipedrive" quando não há conexão ativa.
- [ ] A empresa informa o API token; o campo é obrigatório.
- [ ] A plataforma valida o token com uma chamada à API do Pipedrive antes de salvar.
- [ ] Token inválido/expirado exibe erro claro e não cria a conexão.
- [ ] Token válido é persistido criptografado, vinculado à empresa, e dispara o scan (US-3.1).
- [ ] A conexão é modelada de forma agnóstica ao provedor (driver Pipedrive no MVP).

**Expected Result:** Conexão CRM criada e válida para a empresa, com scan disparado.

---

### US-2.2: Ver status da conexão do CRM
**As a** empresa
**I want to** ver se meu CRM está conectado e o estado da conexão
**So that** eu saiba se o agente está pronto e se preciso reconectar

**Acceptance Criteria:**
- [ ] O painel indica se há conexão ativa e o provedor (Pipedrive).
- [ ] Mostra a data da última conexão/validação do token.
- [ ] Permite desconectar/atualizar o token da conexão.

**Expected Result:** A empresa entende o estado atual da integração do CRM a qualquer momento.

---

## 3. Scan e Dados do CRM

### US-3.1: Escanear o CRM ao conectar
**As a** sistema
**I want to** varrer o Pipedrive da empresa em background ao conectar
**So that** os dados do CRM fiquem armazenados na plataforma

**Acceptance Criteria:**
- [ ] O scan roda em um job de fila (não bloqueia a requisição de conexão).
- [ ] Armazena, vinculado à empresa: pipelines + stages, campos customizados, persons/contatos e deals/negócios.
- [ ] Registra o resultado do scan: status (em andamento/sucesso/erro), contagens e timestamp.
- [ ] Erro de API do Pipedrive é capturado e registrado no status sem quebrar a plataforma.
- [ ] Re-scan da mesma empresa atualiza os dados sem duplicar registros.

**Expected Result:** Dados do CRM da empresa armazenados localmente e status do scan registrado.

---

### US-3.2: Re-escanear o CRM sob demanda
**As a** empresa
**I want to** disparar um novo scan manualmente pelo painel
**So that** meus dados na plataforma reflitam mudanças recentes no Pipedrive

**Acceptance Criteria:**
- [ ] O painel oferece um botão de "Re-scan" quando há conexão ativa.
- [ ] O botão dispara o mesmo job de scan (US-3.1) em fila.
- [ ] Enquanto um scan está em andamento, o botão fica desabilitado/indica progresso.
- [ ] Ao concluir, o painel reflete as novas contagens e timestamp.

**Expected Result:** A empresa atualiza os dados escaneados quando quiser, sem reconectar o token.

---

### US-3.3: Ver resumo dos dados escaneados
**As a** empresa
**I want to** ver um resumo do que foi escaneado do meu CRM
**So that** eu confirme que a plataforma leu meu Pipedrive corretamente

**Acceptance Criteria:**
- [ ] O painel exibe pipelines e seus stages.
- [ ] Exibe a lista/quantidade de campos customizados.
- [ ] Exibe o número de persons/contatos e de deals/negócios armazenados.
- [ ] Exibe o status e o timestamp do último scan.

**Expected Result:** A empresa vê um resumo claro dos dados do CRM importados para a plataforma.

---

## 4. Acesso do Cliente Final

### US-4.1: Solicitar magic link por email
**As a** cliente final
**I want to** informar meu email na tela pública de chat e receber um link de login
**So that** eu entre para conversar com o agente sem precisar de senha

**Acceptance Criteria:**
- [ ] Tela pública com campo de email e ação de enviar.
- [ ] O email é casado contra os Persons escaneados de todas as empresas.
- [ ] Se não houver nenhum match, o cliente é informado de que não há acesso (não existe em nenhum CRM conectado).
- [ ] Se houver ao menos um match, um magic link de uso único é enviado ao email.
- [ ] O magic link expira em **15 minutos** e é **invalidado após o primeiro uso**.
- [ ] A resposta na tela não revela em quais/quantas empresas o email existe (evita enumeração).

**Expected Result:** Cliente final com email presente no CRM de alguma empresa recebe um link de login válido por 15 minutos.

---

### US-4.2: Autenticar pelo magic link
**As a** cliente final
**I want to** clicar no magic link recebido
**So that** eu seja autenticado e entre no chat

**Acceptance Criteria:**
- [ ] Link válido e não usado autentica o cliente e cria a sessão.
- [ ] Link expirado (>15 min) ou já usado exibe mensagem de erro e oferece solicitar novo link.
- [ ] Após autenticar, se houver um único match, vai direto ao chat daquela empresa (US-6.1).
- [ ] Após autenticar, se houver múltiplos matches, vai à seleção de empresa (US-5.1).

**Expected Result:** Cliente final autenticado e encaminhado ao chat ou à seleção de empresa.

---

## 5. Seleção de Empresa

### US-5.1: Escolher a empresa para conversar
**As a** cliente final
**I want to** escolher com qual empresa quero conversar quando meu email existe em mais de uma
**So that** eu fale com o agente no contexto certo

**Acceptance Criteria:**
- [ ] Quando o email tem match em mais de uma empresa, a plataforma lista essas empresas.
- [ ] O cliente só vê empresas em que seu email consta como Person.
- [ ] Selecionar uma empresa define o contexto da conversa e leva ao chat (US-6.1).
- [ ] Com um único match, esta tela é ignorada (vai direto ao chat).

**Expected Result:** Cliente final inicia a conversa vinculada à empresa que ele escolheu.

---

## 6. Chat com o Agente

### US-6.1: Conversar com o agente
**As a** cliente final
**I want to** enviar mensagens e receber respostas do agente da empresa
**So that** eu tire dúvidas/converse pelo chat da plataforma

**Acceptance Criteria:**
- [ ] O chat mostra as mensagens do cliente e do agente em ordem.
- [ ] Cada mensagem do cliente é respondida pelo agente via `laravel/ai` (OpenAI) usando o system prompt global fixo.
- [ ] A conversa é vinculada ao par cliente + empresa selecionada.
- [ ] Cada mensagem (cliente e agente) é persistida no banco.
- [ ] Falha na chamada de IA exibe erro amigável e não perde a mensagem do cliente.
- [ ] O agente não usa dados do CRM nem tools no MVP — apenas o system prompt.

**Expected Result:** Cliente final troca mensagens com o agente e a conversa fica gravada.

---

### US-6.2: Retomar histórico da conversa
**As a** cliente final
**I want to** ver o histórico da minha conversa com uma empresa ao voltar
**So that** eu continue de onde parei

**Acceptance Criteria:**
- [ ] Ao reabrir o chat de uma empresa, as mensagens anteriores daquele par cliente+empresa são carregadas.
- [ ] Conversas de empresas diferentes ficam isoladas entre si.
- [ ] O histórico persiste entre sessões (após novo magic link).

**Expected Result:** Cliente final retoma a conversa persistida com a empresa escolhida.

---

## Appendix: User Story Status

| ID | Story | Priority | Status |
|----|-------|----------|--------|
| US-1.1 | Cadastro da empresa | High | Pending |
| US-1.2 | Login e logout da empresa | High | Pending |
| US-2.1 | Conectar Pipedrive via API token | High | Pending |
| US-3.1 | Escanear o CRM ao conectar | High | Pending |
| US-4.1 | Solicitar magic link por email | High | Pending |
| US-4.2 | Autenticar pelo magic link | High | Pending |
| US-5.1 | Escolher a empresa para conversar | High | Pending |
| US-6.1 | Conversar com o agente | High | Pending |
| US-6.2 | Retomar histórico da conversa | High | Pending |
| US-2.2 | Ver status da conexão do CRM | Medium | Pending |
| US-3.3 | Ver resumo dos dados escaneados | Medium | Pending |
| US-3.2 | Re-escanear o CRM sob demanda | Medium | Pending |
