---
name: twinforge-setup
description: Configuração inicial guiada do twinforgecli, em passos, retomável e repetível sem efeito colateral - instala o CLI, valida a URL da instância, faz o login por aprovação no navegador, cria o perfil, ajuda a escolher company e projeto e termina mostrando os projetos do usuário. Use quando o usuário quiser configurar, conectar ou começar a usar o twinforgecli, ou quando não houver instância/login. Gatilhos - "configurar o twinforge", "conectar na instância", "primeiro acesso", "não consigo achar meus projetos", /twinforge:setup.
---

# twinforge-setup

Leva o usuário da máquina sem configuração até um `twinforgecli` logado, com perfil, company e projetos visíveis. Funciona em qualquer pasta. O objetivo final é a pessoa **encontrar os projetos dela**.

## Regras inegociáveis

- **Nunca** imprima, peça, grave ou repita token, senha ou chave de API, nem em resumos de saída. Se um token aparecer na saída de um comando, omita-o.
- **Nunca** rode `twinforgecli connect` (exige terminal interativo). O caminho scriptável de login é `twinforgecli auth login --no-browser`. Se um comando exigir TTY, peça ao usuário que o rode na sessão com o prefixo `!`.
- **Toda escrita na instância** (criar, atualizar, apagar, atribuir, mover, comentar, enviar) segue: mostrar a instância e a company em uso, o comando e o efeito esperado; esperar um "sim" explícito do usuário; só então executar. Recusa ou pedido de ajuste = nada é executado.
- Nenhum nome de cliente, URL ou perfil é fixo nesta skill: trabalhe com a instância que o usuário tem no `twinforgecli`.

## Instância da sessão

Antes do primeiro comando do `twinforgecli` em cada sessão, fixe a instância de trabalho. A escolha vale só para a sessão e fica na conversa.

1. **Candidatas.** Junte os perfis de duas fontes, ignorando perfis com `apiBase` nulo (o perfil `default` vazio é só um placeholder da CLI):
   - global: `twinforgecli context list --json --context "${TWINFORGE_HOME:-$HOME/.twinforge}/context.json"`
   - pasta: `twinforgecli context show --json`. Se o `contextPath` não for o arquivo global, a pasta atual tem contexto próprio (`.twinforge/context.json` em algum diretório acima); liste esses perfis com `twinforgecli context list --json` (sem `--context`).
2. **Decidir.**
   - Nenhuma candidata → encaminhe para a skill `twinforge-setup` (`/twinforge:setup`).
   - **Padrão salvo da pasta** (a pasta tem `.twinforge/context.json` com perfil válido) → use-o **sem perguntar**, mesmo havendo outras candidatas: `Usando <perfil> (<apiBase>, company <companyId>), padrão salvo desta pasta. Diga "trocar de instância" para mudar.`
   - Uma → diga em uma linha `Usando <perfil> (<apiBase>, company <companyId>)` e siga, sem perguntar.
   - Duas ou mais, sem padrão salvo → pergunte UMA vez qual usar, pelo nome do perfil e pela URL. **Depois da escolha, ofereça salvá-la como padrão desta pasta** ("Não perguntar mais aqui?"). Se aceitar, mostre o conteúdo e, após confirmação, grave `.twinforge/context.json` na raiz do repositório (`git rev-parse --show-toplevel`, ou a pasta atual) só com o perfil escolhido: `{"currentProfile":"<perfil>","profiles":{"<perfil>":{"apiBase":"<URL>","companyId":"<id>","persona":"board"}}}` (sem token), e confira que `.twinforge/` está no `.gitignore` (acrescente, com confirmação). Se recusar, vale só para a sessão.
3. **Fixar.** Guarde o par (arquivo de contexto, perfil). Em TODO comando do `twinforgecli` passe `--context "<arquivo>" --profile "<perfil>"`. Nunca use só `--profile` nem `context use`: sem o arquivo certo, um nome que a CLI não encontra **cai silenciosamente em `http://localhost:3100`**, e `context use` altera o perfil global da máquina e as outras sessões.
4. **Refazer** a seleção se o usuário pedir troca ou se você não souber mais qual instância está fixada (por exemplo, depois de a conversa ser resumida). Nunca assuma uma instância.

## Como este fluxo trabalha

- **Estado lido, nunca guardado.** A cada execução, rode a checagem de cada passo, pule o que já está pronto e comece no **primeiro passo pendente**. Não crie arquivo de progresso. Com tudo pronto, mostre só a conferência final (passo 8) e **não execute nenhuma escrita**.
- **O setup NÃO usa a regra "uma candidata → siga sem perguntar"** da seção "Instância da sessão": essa regra serve às outras skills. Aqui, a **primeira decisão é sempre perguntar qual instância configurar** (Passo 2), mesmo com um único perfil e login válido. Não conclua o setup, não rode os passos 3 a 8 e não mostre company ou projetos de uma instância antes de o usuário responder. Quem roda `/twinforge:setup` pode estar justamente tentando conectar uma instância **nova**.
- Anuncie o que encontrou antes de agir: "CLI ok, login ok, falta o perfil".
- A única coisa que o usuário precisa trazer é a **URL da instância**.
- Se o diretório de dados estiver isolado (`TWINFORGE_HOME` ou `--data-dir`), use-o em todos os comandos e não toque em `~/.twinforge`.

## Passo 1. CLI

- Checagem: `node --version` (precisa de 20+), `twinforgecli --version`.
- Node ausente ou < 20: explique o requisito e pare aqui; não tente instalar o CLI antes.
- CLI ausente: mostre `npm install -g twinforgecli` e instale **somente com a confirmação do usuário** (ou deixe-o rodar). Oriente apenas esse caminho; **não** oriente o `install.sh` do app completo (é para quem hospeda a instância).
- `E404` e registry privado configurado no `~/.npmrc`: proponha repetir com `--registry https://registry.npmjs.org`.
- Versão antiga (confira com `npm view twinforgecli version`): avise e sugira `/twinforge:update`, sem bloquear.

## Passo 2. Qual instância configurar? (sempre pergunta)

1. **Pergunte primeiro, sempre.** Reúna as candidatas (seção "Instância da sessão") e mostre-as: perfil, URL, company e se o login responde (`auth whoami`). Depois pergunte, em uma única pergunta, com estas opções: **usar uma das existentes** (diga qual) ou **conectar uma instância nova** (peça a URL). Isso vale mesmo com uma só candidata e login válido: **nunca** assuma que a existente é a que o usuário quer configurar.
   - **Dica de padrão:** se `twinforge/roles.yaml` da pasta tiver `scope.project_id` ou ids em placeholder (`{{...}}`), este repositório ainda não pertence a nenhuma instância: sugira **conectar uma instância nova** como a opção provável, sem decidir pelo usuário.
   - Conectar outra **não remove nem altera** as conexões existentes: o login é guardado por endereço, então no final há várias instâncias e dá para alternar entre elas (`/twinforge:instances`). Escolhendo uma existente, siga para o Passo 3 só para conferir o login.
2. Sem URL: se o usuário não a tem, diga o que pedir aos responsáveis pela instância: **a URL** e **o convite ou usuário na instância**. Encerre o passo sem erro.
3. Com URL: exija `https://`. Valide com `curl -fsS -m 10 "<URL>/api/health"` (público, sem login). Sem https ou sem resposta: explique e peça outra URL; **não** tente o login.

## Passo 3. Login

- Checagem: `twinforgecli auth whoami --api-base "<URL>"`. Se retornar a identidade, pule.
- Senão, rode **em segundo plano** (Bash com `run_in_background`): `twinforgecli auth login --no-browser --api-base "<URL>" --json > "<arquivo>.out" 2> "<arquivo>.err"` (use arquivos temporários no scratchpad). Ele cria um desafio, imprime a URL de aprovação em **stderr** (o stdout fica vazio até a aprovação, mesmo com `--json`) e **aguarda** até o usuário aprovar no navegador; grava a credencial por endereço no diretório de dados da CLI.
- Leia a saída do processo, capture a URL de aprovação (um link https) e entregue ao usuário: "Abra este link, entre na instância se pedir e aprove." Se não achar um link na saída, mostre a saída **sem qualquer token** e peça ajuda ao usuário. (Verificado na CLI 0.6.0: a URL tem a forma `<URL>/cli-auth/<uuid>?token=...`; o `token` dessa URL é de aprovação e só vai para o usuário.)
- **Opcional, com o plugin Claude in Chrome:** abrir a URL e acionar o botão de aprovar. **Nunca** digite senha ou credenciais. Se a página pedir login na instância, **pare** e peça ao usuário que entre e avise.
- Quando o processo terminar, confirme com `twinforgecli auth whoami --api-base "<URL>"` e informe a identidade. Nunca exiba token.
- Demora sem aprovação: avise que o desafio expira, ofereça gerar outro.

## Passo 4. Perfil

- Checagem: existe perfil (global) com `apiBase` igual à URL (ignore barra final)?
- Se não: proponha nome, URL, company e persona e, **após confirmação**, rode
  `twinforgecli context set --profile "<nome>" --api-base "<URL>" --company-id "<id>" --persona board`
  (adicione `--use` só se o usuário quiser torná-lo o ativo global). A company vem do passo 5: se ainda não a tem, faça o passo 5 antes de gravar.
- **Nome do perfil:** com outras instâncias já configuradas, peça um nome curto e único (por exemplo, o do projeto) e nunca reutilize o de outro perfil, para não sobrescrevê-lo. Não use `--use`: o perfil ativo global **não muda** (outras sessões continuam como estão).
- **Padrão desta pasta:** depois de criar o perfil, se a pasta atual é um repositório, ofereça salvá-lo como padrão dela (`.twinforge/context.json`, como descrito na seção "Instância da sessão"), para que comandos e skills nesta pasta usem sempre essa instância sem perguntar.
- Perfil de mesmo nome já existe com valores diferentes: **mostre a diferença** (URL, company, persona) e não sobrescreva sem confirmação explícita.

## Passo 5. Company e projetos

1. `twinforgecli company list --json --api-base "<URL>"` (ou com o perfil). Mostre as companies **por nome**; o usuário escolhe, você usa o id. Uma única: proponha como padrão e peça confirmação. Na 0.6.0 as chaves são `id` e `name` (company) e `id`, `name` (projeto); mesmo assim inspecione o JSON em vez de supor.
2. `twinforgecli project list --json -C "<companyId>" --api-base "<URL>"`. Mostre os projetos por nome. **Nunca peça uuid.**
3. Várias companies e o usuário não sabe onde está o projeto: ofereça listar os projetos de cada company antes de escolher.
4. **Lista vazia não é sucesso.** Explique as causas prováveis (company errada, convite incompleto, permissões), ofereça olhar as outras companies e, se persistir, diga o que pedir aos responsáveis. Não conclua o passo como concluído.
5. Ao final, **exiba os projetos encontrados**.

## Passo 6. Repositório de configuração dos agentes (opcional)

Nunca bloqueia o setup. É uma pasta com `agents/`, `skills/` e `twinforge/roles.yaml`. Pergunte se o usuário quer (checagem: existe `twinforge/roles.yaml` na pasta atual ou em um caminho já informado?). Opções:

1. **Pular.** Diga o que se perde: análise do fluxo dos agentes, aba Engenharia, comparação agente × instância e commits novos do repositório. Diga que dá para retomar depois (rodando o setup de novo). Conclua.
2. **Apontar um clone existente:** peça o caminho e valide a presença de `twinforge/roles.yaml`, `agents/` e `skills/`.
3. **Criar a partir do repositório base da organização (fork):** o repositório base é o do primeiro projeto configurado na organização, e os projetos novos nascem de um **fork** dele. Esta skill não grava qual é: peça a URL ao usuário (ou use a que ele já informou na conversa). Após confirmação, rode `gh repo fork <URL-base> --clone --fork-name <nome-novo>` (ou, se o base estiver marcado como template no GitHub, `gh repo create <nome-novo> --template <URL-base> --private --clone`). Sem `gh` instalado ou autenticado, mostre o comando para o usuário rodar. Depois valide a estrutura (`twinforge/roles.yaml`, `agents/`, `skills/`).
   **Rotule como base a adaptar, não como padrão:** o fork traz dados do projeto de origem (ids de agentes e de projeto em `twinforge/roles.yaml`, nomes de repositório e de cliente). Liste o que precisa ser trocado e **não aplique nada na instância** antes de o usuário revisar.
4. **Puxar o que existe na instância:** use o catálogo (`twinforgecli commands`, termos "agent" e "agent-config") para ler os agentes; mostre o que foi encontrado e só grave arquivos após confirmação.

Sem repositório, uma card pode ser criada sem responsável (`assigneeAgentId` é opcional): sem responsável ou em `backlog`, ninguém é acordado, e o usuário direciona depois para o agente que quiser.

## Passo 7. Auditoria (só com repositório)

Se `scope.project_id` ou os ids de `roles.yaml` ainda forem placeholders (`{{...}}`), **não rode a comparação**: diga que o repositório ainda não foi adaptado à instância (e liste o que falta trocar: `scope.project_id`, os ids dos papéis e o repositório do projeto), e ofereça a adaptação como próximo passo. Caso contrário, compare os agentes da instância (`twinforgecli agent list --json`) com `twinforge/roles.yaml`. No formato do repositório base, `roles:` mapeia uma chave de papel para `{kind, id}` (com `kind: agent`, o `id` é o uuid do agente), e há um bloco `scope` com o `project_id`. Compare por **`id`** e mostre o `name` da instância ao lado. Papéis com `kind` diferente de `agent` não entram na comparação. Se o arquivo não seguir esse formato, **mostre ao usuário como o interpretou** antes de comparar, ou diga que não foi possível, em vez de adivinhar. Um `scope.project_id` que não existe na instância indica um fork ainda não adaptado: avise. Relate agentes que existem de um lado e não do outro. **Somente leitura**: ofereça, mas não execute, uma correção.

## Passo 8. Conferência final

**Não diga "setup concluído" se algo ficou pendente.** Se o repositório ainda tem placeholders em `roles.yaml`, diga "instância configurada; repositório ainda não adaptado" e liste a pendência. Mostre um resumo: versão do CLI, instância (URL), identidade logada, perfil, company, **projetos encontrados** e situação do repositório de agentes. Sugira os próximos passos: `/twinforge:projects`, `/twinforge:status` ou `/twinforge:explore`.

