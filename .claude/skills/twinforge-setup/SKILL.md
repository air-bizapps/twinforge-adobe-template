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

- **Estado lido, nunca guardado.** A cada execução, rode a checagem de cada passo; não crie arquivo de progresso. Quem decide o que fazer com esse estado é o usuário, no menu do Passo 2.
- **A primeira decisão é SEMPRE o menu do Passo 2, sem exceção:** nem com um único perfil, nem com login válido, nem com um padrão salvo na pasta. O setup **não usa** a regra "uma candidata → siga sem perguntar" da seção "Instância da sessão" (ela serve às outras skills). Antes de o usuário escolher o caminho, **não** execute os passos 3 a 10, **não** conclua o setup e **não** mostre company ou projetos de nenhuma instância (só o estado de leitura do Passo 2).
- **Três caminhos**, escolhidos pelo usuário: **continuar um setup em andamento**, **revalidar uma instância** (checkup) ou **configurar uma instância nova**.
- Anuncie o que encontrou antes de agir: "CLI ok, login ok, falta o perfil".
- A única coisa que o usuário precisa trazer é a **URL da instância**.
- Se o diretório de dados estiver isolado (`TWINFORGE_HOME` ou `--data-dir`), use-o em todos os comandos e não toque em `~/.twinforge`.

## Projeto da pasta (contexto)

A CLI **não tem campo de projeto** no perfil (e o `context set` apaga chaves que não conhece), então o projeto escolhido fica em **`.twinforge/project.json`**, na raiz do repositório (`git rev-parse --show-toplevel`, ou a pasta atual): `{"apiBase":"<URL>","companyId":"<id>","projectId":"<id>","projectName":"<nome>"}`. Não tem token e fica fora do git (`.twinforge/` está no `.gitignore`). O `projectId` também vai para `scope.project_id` de `twinforge/roles.yaml` (versionado, vale para o time).

- **Ordem de leitura:** (1) `.twinforge/project.json`; (2) `scope.project_id` do `roles.yaml`, se não for placeholder (`{{...}}`); (3) perguntar.
- **Validação a cada uso:** `apiBase` e `companyId` do arquivo precisam bater com a instância fixada, e o `projectId` precisa existir em `project list`. Se não bater (por exemplo, a pasta foi ligada a outra instância), **não use**: avise e peça a escolha de novo.
- **Uso:** onde um comando aceitar projeto (`--project-id`), use o do arquivo por padrão, **diga qual** ("Projeto: <nome>") e mostre-o no rascunho de qualquer escrita; nunca o use em silêncio.

## Passo 1. CLI

- Checagem: `node --version` (precisa de 20+), `twinforgecli --version`.
- Node ausente ou < 20: explique o requisito e pare aqui; não tente instalar o CLI antes.
- CLI ausente: mostre `npm install -g twinforgecli` e instale **somente com a confirmação do usuário** (ou deixe-o rodar). Oriente apenas esse caminho; **não** oriente o `install.sh` do app completo (é para quem hospeda a instância).
- `E404` e registry privado configurado no `~/.npmrc`: proponha repetir com `--registry https://registry.npmjs.org`.
- Versão antiga (confira com `npm view twinforgecli version`): avise e sugira `/twinforge:update`, sem bloquear.

## Passo 2. Como você quer seguir? (sempre pergunta)

1. **Levante o estado de cada instância, só com leitura.** Reúna as candidatas (seção "Instância da sessão"; fixe o arquivo de contexto e o perfil de cada uma) e, para cada uma, verifique **sem escrever nada**: login (`auth whoami`), perfil existente, company acessível (`company list` contém a company do perfil) e projetos (`project list`). Para a instância que é o **padrão desta pasta**, verifique também se `twinforge/roles.yaml` está **adaptado** (sem `{{...}}` em `scope.project_id` e nos ids). Falha de rede ("Could not reach the TwinForge API") é "sem resposta", não "sem login". Classifique cada instância como **completa** (tudo acima ok) ou **em andamento** (liste o que falta). A permissão para editar agentes (Passo 6) **não** entra aqui: ela exige o teste do Passo 6.
2. **Mostre uma tabela**: perfil, URL, company, estado e o que falta; marque a que é o padrão desta pasta.
3. **Faça UMA pergunta**, com a ferramenta de pergunta com opções (`AskUserQuestion`) quando existir, senão uma lista numerada, **sempre com os três caminhos** (omita só um que seja impossível, por exemplo "continuar" sem nenhuma instância em andamento):
   - **Continuar um setup em andamento** de `<perfil>`: retoma no **primeiro passo pendente** daquela instância (passos 3 a 10, pulando o que já está pronto).
   - **Revalidar uma instância** (checkup): percorre **todo** o processo de setup daquela instância **sem alterar nada na instância remota**, a não ser para reparar uma falha. Veja "Revalidar uma instância".
   - **Configurar uma instância nova:** peça a URL e siga os passos 3 a 10 do começo.
   **Recomendação** (marque, sem decidir pelo usuário): havendo exatamente uma instância em andamento, "continuar" nela (a do padrão da pasta, se for o caso); sem nenhuma em andamento, "revalidar" o padrão da pasta; sem nenhuma candidata, "configurar nova". Se `roles.yaml` da pasta estiver em placeholder e nenhuma instância o adaptou, "configurar nova" também é uma sugestão razoável a mencionar.
4. **Qualquer caminho** preserva as outras conexões: o login é guardado por endereço, então configurar uma instância nova **não remove nem altera** as existentes; no final há várias e dá para alternar entre elas (`/twinforge:instances`).

### Caminho: configurar uma instância nova

1. Sem URL: se o usuário não a tem, diga o que pedir aos responsáveis pela instância: **a URL** e **o convite ou usuário na instância**. Encerre o passo sem erro.
2. Com URL: exija `https://`. Valide com `curl -fsS -m 10 "<URL>/api/health"` (público, sem login). Sem https ou sem resposta: explique e peça outra URL; **não** tente o login.

## Passo 3. Login (uma vez só, pela CLI)

A **CLI é a prioridade**: o login do `twinforgecli` é o que importa, e o login no navegador é só um meio dentro do link de aprovação. O usuário deve entrar **uma única vez**: **não** peça para ele entrar na instância antes; gere primeiro o link de aprovação da CLI e deixe que o próprio link leve ao login.

**Regra crítica (verificada na 0.6.0):** mesmo sem nenhuma flag, a CLI lê o **contexto padrão** da máquina (perfil ativo, com a `companyId` de **outra** instância) e envia essa company ao servidor novo, que responde `Internal server error` (500) sem gerar o link. Passar só `--api-base` **não basta**. Em todo comando de login e de `auth whoami` de uma instância nova, passe também `--context "<arquivo>"` apontando para um arquivo de contexto **vazio** que você cria no scratchpad (conteúdo `{}`), além de `--api-base "<URL>"`. Nunca passe `-C` nem o `--context`/`--profile` de outra instância. O diretório de dados não muda: a credencial continua indo para o `auth.json` por endereço, e o `auth.json` e o contexto das outras instâncias não são tocados.

1. **Checagem:** `twinforgecli auth whoami --api-base "<URL>" --context "<vazio>"`. Se retornar a identidade, já há login nessa instância: pule o passo.
2. **Gere o link de aprovação** em segundo plano (Bash com `run_in_background`): `twinforgecli auth login --no-browser --api-base "<URL>" --context "<vazio>" --json > "<arquivo>.out" 2> "<arquivo>.err"` (arquivos temporários no scratchpad). Ele cria um desafio, imprime a URL de aprovação em **stderr** (o stdout fica vazio até a aprovação, mesmo com `--json`) e **aguarda**; ao aprovar, grava a credencial por endereço (a de outras instâncias não é afetada). A URL tem a forma `<URL>/cli-auth/<uuid>?token=...`; o `token` dela é de aprovação e só vai para o usuário.
3. **Entregue o link e oriente em uma mensagem:**
   - "Abra este link. Se a página pedir login, **entre ali mesmo**; se você ainda **não tem conta**, crie-a ali mesmo (cadastro) ou use o convite que recebeu. Depois de entrar, aprove o acesso da CLI. É o único login necessário."
   - Se a página não oferecer cadastro e o usuário não tem conta: diga o que pedir aos responsáveis da instância (um **convite** ou um usuário) e que, quando tiver, ele pode voltar a rodar o setup. O desafio expira: gere outro.
4. **Opcional, com o plugin Claude in Chrome:** abrir a URL e acionar o botão de aprovar. **Nunca** digite senha ou credenciais; se a página pedir login ou cadastro, **pare** e devolva a vez ao usuário.
5. Quando o processo terminar (código 0), confirme com `twinforgecli auth whoami --api-base "<URL>" --context "<vazio>"` e informe a identidade. Nunca exiba token.
6. **Falhas:**
   - `Internal server error` ao gerar o desafio: confira primeiro se o comando levou o `--context` vazio (causa conhecida: sem ele a CLI usa a company do contexto padrão); refaça com ele. Se persistir mesmo com o contexto vazio, é problema da instância (considere o horário de funcionamento) e vale avisar os responsáveis; não repita em laço.
   - Sem aprovação por muito tempo: o desafio expirou; ofereça gerar outro.
   - Alternativa que exige terminal interativo: o usuário pode rodar `! twinforgecli connect --persona board --api-base "<URL>"`; a skill não o executa.

## Passo 4. Perfil

- Checagem: existe perfil (global) com `apiBase` igual à URL (ignore barra final)?
- Se não: proponha nome, URL, company e persona e, **após confirmação**, rode
  `twinforgecli context set --profile "<nome>" --api-base "<URL>" --company-id "<id>" --persona board`
  (adicione `--use` só se o usuário quiser torná-lo o ativo global). A company vem do passo 5: se ainda não a tem, faça o passo 5 antes de gravar.
- **Nome do perfil:** com outras instâncias já configuradas, peça um nome curto e único (por exemplo, o do projeto) e nunca reutilize o de outro perfil, para não sobrescrevê-lo. Não use `--use`: o perfil ativo global **não muda** (outras sessões continuam como estão).
- **Padrão desta pasta:** depois de criar o perfil, se a pasta atual é um repositório, ofereça salvá-lo como padrão dela (`.twinforge/context.json`, como descrito na seção "Instância da sessão"), para que comandos e skills nesta pasta usem sempre essa instância sem perguntar.
- Perfil de mesmo nome já existe com valores diferentes: **mostre a diferença** (URL, company, persona) e não sobrescreva sem confirmação explícita.

## Passo 5. Company e projetos

Executado **automaticamente** logo depois do perfil (Passo 4), sem perguntar: é o mesmo que o `/twinforge:projects`. Se a pasta já tiver um projeto salvo (`.twinforge/project.json` válido), destaque-o na lista como "projeto da pasta". Antes de o perfil existir, use `--api-base` **e** o `--context` vazio do Passo 3; depois, `--context "<arquivo>" --profile "<perfil>"`.

1. `twinforgecli company list --json --api-base "<URL>"` (ou com o perfil). Mostre as companies **por nome**; o usuário escolhe, você usa o id. Uma única: proponha como padrão e peça confirmação. Na 0.6.0 as chaves são `id` e `name` (company) e `id`, `name` (projeto); mesmo assim inspecione o JSON em vez de supor.
2. `twinforgecli project list --json -C "<companyId>" --api-base "<URL>"`. Mostre os projetos por nome. **Nunca peça uuid.**
3. Várias companies e o usuário não sabe onde está o projeto: ofereça listar os projetos de cada company antes de escolher.
4. **Lista vazia não é sucesso.** Explique as causas prováveis (company errada, convite incompleto, permissões), ofereça olhar as outras companies e, se persistir, diga o que pedir aos responsáveis. Não conclua o passo como concluído.
5. Ao final, **exiba os projetos encontrados**.

## Passo 6. Permissão para criar e editar agentes

Roda **automaticamente** depois dos projetos (Passo 5), sem perguntar: usuários com papel de operador **não** conseguem criar nem editar agentes, e é melhor descobrir isso agora. A checagem é refeita a cada execução do setup.

1. **Papel.** `twinforgecli auth whoami` (instância fixada): leia `memberships[].membershipRole` (por exemplo `owner` ou `admin`) e informe. É só um sinal; o teste abaixo é o que vale.
2. **Escolha um agente:** `twinforgecli agent list --json`. Pegue **um** que **não** esteja em execução (`running`) e prefira um com `AGENTS.md` editável. Sem agentes na instância: pule o teste e avise que ele será feito quando houver um.
3. **Teste de escrita que não muda nada.** Anuncie em uma linha: "Vou testar sua permissão regravando o `AGENTS.md` do agente <nome> com o **mesmo conteúdo** (nada muda)". Esta é a **única exceção** à confirmação prévia de escrita, e só vale para esta regravação idêntica, em um único agente:
   - Leia: `twinforgecli agent instructions-file:get <agentId> --path AGENTS.md --json` e grave o campo `content` em um arquivo temporário (scratchpad), **sem exibi-lo**. Se `editable` for `false` ou o arquivo for `virtual`, escolha outro agente; se nenhum servir, diga que o bundle não é editável (isso **não** é problema de permissão).
   - Grave de volta o mesmo conteúdo: `twinforgecli agent instructions-file:put <agentId> --path AGENTS.md --content-file "<temporário>"`. **A resposta do `put` repete o conteúdo inteiro do arquivo**: redirecione-a para `/dev/null` (ou descarte) e use só o código de saída; **nunca a exiba**.
   - Confira com um novo `instructions-file:get` que o conteúdo continua idêntico e apague o temporário. **Nunca** altere o conteúdo, nunca teste em mais de um agente e nunca em um agente em execução.
4. **Resultado:**
   - **Funcionou:** "Você pode editar agentes (papel <papel>)." Siga em frente.
   - **Falhou por permissão** (403, `forbidden`, "does not have access", "permission"): diga que o usuário **não tem permissão para criar ou editar agentes** nesta instância (informe o papel). Oriente: peça, no canal do Slack **`bizapps-twinforge-forjadores`**, a mudança do seu papel para **owner ou admin**, informando o e-mail, a URL da instância e a company. Ofereça um texto pronto para copiar (a skill **não envia** a mensagem). Quando o papel mudar, é só rodar `/twinforge:setup` de novo: ele retoma neste passo. Enquanto isso, **não ofereça** o Passo 9 (subir o template); o restante do setup segue normalmente.
   - **Falhou por outro motivo** (500, rede, 401): não é permissão; trate pela tabela de erros da skill `twinforge` e não repita em laço.

## Passo 7. Repositório de configuração dos agentes (opcional)

Nunca bloqueia o setup. É uma pasta com `agents/`, `skills/` e `twinforge/roles.yaml`. Pergunte se o usuário quer (checagem: existe `twinforge/roles.yaml` na pasta atual ou em um caminho já informado?). Opções:

1. **Pular.** Diga o que se perde: análise do fluxo dos agentes, aba Engenharia, comparação agente × instância e commits novos do repositório. Diga que dá para retomar depois (rodando o setup de novo). Conclua.
2. **Apontar um clone existente:** peça o caminho e valide a presença de `twinforge/roles.yaml`, `agents/` e `skills/`.
3. **Criar a partir do repositório base da organização (fork):** o repositório base é o do primeiro projeto configurado na organização, e os projetos novos nascem de um **fork** dele. Esta skill não grava qual é: peça a URL ao usuário (ou use a que ele já informou na conversa). Após confirmação, rode `gh repo fork <URL-base> --clone --fork-name <nome-novo>` (ou, se o base estiver marcado como template no GitHub, `gh repo create <nome-novo> --template <URL-base> --private --clone`). Sem `gh` instalado ou autenticado, mostre o comando para o usuário rodar. Depois valide a estrutura (`twinforge/roles.yaml`, `agents/`, `skills/`).
   **Rotule como base a adaptar, não como padrão:** o fork traz dados do projeto de origem (ids de agentes e de projeto em `twinforge/roles.yaml`, nomes de repositório e de cliente). Liste o que precisa ser trocado e **não aplique nada na instância** antes de o usuário revisar.
4. **Puxar o que existe na instância:** use o catálogo (`twinforgecli commands`, termos "agent" e "agent-config") para ler os agentes; mostre o que foi encontrado e só grave arquivos após confirmação.

Sem repositório, uma card pode ser criada sem responsável (`assigneeAgentId` é opcional): sem responsável ou em `backlog`, ninguém é acordado, e o usuário direciona depois para o agente que quiser.

## Passo 8. Conferência final e próximo passo

1. **Resumo.** Versão do CLI, instância (URL), identidade logada, perfil, company, **projetos encontrados** e situação do repositório de agentes, o **resultado da verificação de permissão** do Passo 6 o **projeto da pasta** (de `.twinforge/project.json`) e se ele tem **repositório conectado** (workspace e `GH_TOKEN`, só leitura). **Não diga "setup concluído" se algo ficou pendente:** com placeholders em `roles.yaml`, diga "instância configurada; repositório ainda não adaptado" e liste a pendência.
2. **O que existe no repositório e o que a instância já tem** (só se a pasta tiver `agents/`, `skills/` e `twinforge/`). Leia, não suponha:
   - **Agentes do repositório:** os `agents/*/agent.yaml` (papel e nome). **Fluxo:** as `phases` de `twinforge/workflow.yaml`, em uma frase. **Skills:** quantas há em `skills/` e quais são genéricas (`adobe-*`, `openspec-*`, sem prefixo) e quais são do projeto de origem (`projeto-*`, `bunge-*`; veja `TEMPLATE.md`).
   - **Na instância:** `agent list` e `skill list` da instância fixada (quantos e quais).
   - Se o `roles.yaml` **já estiver adaptado**, rode a auditoria (Passo 10) em modo de leitura e mostre o resultado. Com placeholders, não rode: a auditoria só tem o que comparar **depois** da subida (Passo 9).
   - Diga o que **só existe no repositório** (por exemplo, "o repositório tem 11 agentes e a instância 4").
3. **Mostre os comandos à mão do usuário**, em uma tabela curta (inclua `/twinforge:repo:*` só se a pasta tiver `commands/twinforge/repo/`). Destaque o primeiro:

| Comando | Para quê |
|---|---|
| **`/twinforge:explore`** | **Pedir qualquer coisa em linguagem natural** ("quais cards estão em andamento?", "cria uma card…", "lista os agentes", "quanto gastamos este mês?"). Leitura roda direto; escrita mostra o rascunho e pede confirmação. |
| `/twinforge:projects` | Lista os projetos da company. |
| `/twinforge:instances` | Mostra e troca a instância em uso. |
| `/twinforge:status` · `:fix` · `:update` | Checa o `twinforgecli`, corrige o que falhar e atualiza a CLI. |
| `/twinforge:apply` | Envia uma mudança do OpenSpec para a instância. |
| `/twinforge:workspace` | Conecta o repositório GitHub do projeto à instância (workspace e segredo do PAT, sem o token na conversa). |
| `/twinforge:repo:status` · `:push` · `:pull` | Compara o repositório com a instância, aplica skills na instância e traz o que foi editado lá. |
| `/twinforge:repo:agent` · `:init` · `:local-sync` · `:help` | Operação pontual em um agente, primeira configuração, sincronização local e a lista completa. |

4. **Faça UMA pergunta de próximo passo**, com a ferramenta de pergunta com opções (`AskUserQuestion`) quando existir; senão, uma lista numerada. Opções, nesta ordem:
   1. **Subir os agentes, skills e fluxo do template nesta instância** (só se a pasta tiver `commands/twinforge/repo/` **e** a verificação de permissão do Passo 6 passou; se falhou, não ofereça e lembre do pedido no canal do Slack). Descreva em 3 linhas o que será feito e **marque como recomendada** quando `roles.yaml` tiver placeholders ou a instância tiver menos agentes ou skills que o repositório. Seja honesto: o `/twinforge:repo:push` aplica as **skills** e re-sincroniza o `desiredSkills` de agentes que já existem; **criar os agentes** a partir de `agents/<papel>/agent.yaml` é um passo guiado, sem instalador automático. Tudo com plano e confirmação antes de escrever.
   2. **Pedir algo em linguagem natural agora** (`/twinforge:explore`). Marque como recomendada quando o repositório já estiver adaptado e a instância já tiver o que precisa.
   3. **Ver o que há na instância** (`/twinforge:projects` e `/twinforge:status`).
   4. **Terminar por aqui.**
5. Ao escolher a opção 1, siga o Passo 9. Ao escolher a 2, 3 ou outra coisa, acione a skill ou o comando correspondente.

## Revalidar uma instância (checkup)

É o caminho de **conferência**: percorre todo o processo de setup da instância escolhida e **não muda nada na instância remota**, a não ser para reparar uma falha. Para cada passo, rode só a **checagem** e classifique **OK**, **ATENÇÃO** ou **FALHA**; só **aja** (com as confirmações normais daquele passo) quando houver falha.

| Passo | O que o checkup faz | Se falhar |
|---|---|---|
| 1 CLI | `twinforgecli --version`, Node 20+, versão publicada | Passo 1 |
| 2 Instância | `/api/health` | considerar o horário de funcionamento; nada local a corrigir |
| 3 Login | `auth whoami` | Passo 3 (novo link de aprovação) |
| 4 Perfil | perfil existe e bate com a URL e a company | Passo 4 (com cópia de segurança do contexto) |
| 5 Projetos | `company list` e `project list` | Passo 5 (lista vazia não é sucesso) |
| 5b Repositório do projeto | modo checagem da skill `twinforge-workspace` (workspace e `GH_TOKEN`, só leitura) | skill `twinforge-workspace` |
| 6 Permissão | o teste de regravação idêntica do Passo 6 | orientar o pedido no Slack |
| 7 Repositório | pasta e `roles.yaml` presentes (só leitura) | apenas relatar |
| 10 Auditoria | comparação por `id` (só leitura), se o repositório estiver adaptado | apenas relatar |

Não execute o Passo 9 no checkup. Termine com a tabela de resultados e uma conclusão explícita: "tudo certo" ou a lista do que falhou e do que foi reparado. Nada é gravado na instância remota além da regravação idêntica do Passo 6.

## Passo 9. Subir o template na instância (só se o usuário escolher)

Sempre com a instância fixada (`--context`/`--profile`), plano mostrado e confirmação **antes de cada escrita**. Nada é atribuído nem iniciado: agentes só são acionados com confirmação específica.

1. **Projeto.** Defina o projeto-alvo com o Passo 1 da skill **`twinforge-workspace`**: **criar um projeto novo com o nome do projeto (recomendado)** ou escolher um existente (**por nome**), e salvar a escolha em `.twinforge/project.json` (contexto da pasta, que direciona as skills para esse projeto). Depois, pergunte o repositório de código do projeto (será conectado no item 2). Mostre o diff de `scope.project_id` e `scope.repo` em `twinforge/roles.yaml` e grave só após confirmação.
2. **Repositório do projeto (antes de qualquer skill ou agente).** Os agentes precisam do código: verifique o projeto-alvo com o modo checagem da skill **`twinforge-workspace`** (workspace com a `repoUrl` e `GH_TOKEN` vinculado). Se faltar algo, siga o fluxo dela (`/twinforge:workspace`): URL do repositório, **segredo com o PAT criado pelo próprio usuário em outro terminal, sem o token passar pela conversa**, vínculo ao projeto sem apagar o env e criação do workspace. Use a mesma URL em `scope.repo` do `roles.yaml`. Só siga para as skills e os agentes com o workspace pronto, ou se o usuário **optar por pular**, e nesse caso avise que os agentes não conseguirão puxar o código.
3. **Aviso de conteúdo.** Diga que `projeto-*`, `bunge-*` e as instruções dos agentes ainda trazem referências ao projeto de origem (veja `TEMPLATE.md`) e ofereça subir **só o genérico primeiro** (`adobe-*` genéricas, `openspec-*`, skills de processo sem prefixo) e deixar o resto para depois da adaptação.
4. **Diferença.** Mostre o resultado do `/twinforge:repo:status` (só no repositório, só na instância, divergentes).
5. **Skills.** Aplique com o fluxo do `/twinforge:repo:push` (que tem as suas próprias confirmações).
6. **Agentes.** Não há instalador automático: o `agent.yaml` é uma definição **portável**, não o payload do `agent create`.
   - **Agentes que a instância já tem:** mostre uma tabela (nome, papel, `reportsTo`, estado) ao lado dos agentes do repositório. Os existentes **nunca são alterados nem apagados** por este passo; só o usuário decide mudar um deles, e isso é uma ação à parte, com confirmação própria (por exemplo, um agente em `error` é só relatado).
   - **Pergunte uma vez como montar o PO**, com a ferramenta de pergunta com opções quando existir:
     1. **Manter a estrutura existente e criar a nova a partir de um PO na raiz** (recomendada): o PO é **criado** como um agente novo **sem `reportsTo`**, ao lado do agente de topo que já existe. A plataforma aceitou esse arranjo (verificado em uma instância real) quando o papel é **`general`**; um segundo agente com papel `ceo` **não foi testado**, então não o use sem teste. Os agentes novos reportam ao PO novo; os existentes continuam como estão.
     2. **Usar um agente existente como PO** (por exemplo, o de papel `ceo`): peça o nome, mostre o que seria editado (instruções, modelo) e só edite após confirmação.
     3. **Só as skills por enquanto**, sem criar agentes.
   - **Antes de criar:** mostre nome, papel, modelo, `adapter_type`, orçamento e `desiredSkills`. **Orçamento nunca 0** (bug conhecido: o agente se lê como "esgotado"). Confira se o `adapter_type` existe na instância (por exemplo, a configuração de um agente já existente via `twinforgecli agent configuration <id>`) e use essa configuração como referência do formato do payload; **não adivinhe os campos**.
   - **Ordem e `reports_to`:** resolva `{{company_id}}` e o `reports_to` (que vem por **chave de papel**) criando primeiro o PO e depois quem reporta a ele. Agentes do repositório que a instância já tem (mesmo nome ou papel, como Reflection Coach e Summarizer) **não são recriados**: pergunte se devem ser reaproveitados no `roles.yaml` ou deixados de fora.
   - Crie com `twinforgecli agent create` **após confirmação** (um a um ou em lote que o usuário aprovar). Depois grave os ids reais em `roles.yaml` (inclusive o do PO novo, mostrando o diff) e refaça o `/twinforge:repo:status`.
7. Termine rodando a **auditoria** (Passo 10) e relate o que foi criado, o que ficou de fora e **que nenhum agente foi acionado**.

## Passo 10. Auditoria (verificação final)

Só faz sentido com o repositório **já adaptado**: ela compara o que o `roles.yaml` diz com o que a instância tem, e o `roles.yaml` só é adaptado (e os agentes só são criados) no Passo 9. Por isso vem **depois** da subida. Roda: (a) **ao final do Passo 9**; (b) no **checkup** (revalidação); e (c) a qualquer momento em que o `roles.yaml` já esteja adaptado, quando o Passo 8 pode mostrar o resultado em leitura.

Se `scope.project_id` ou os ids de `roles.yaml` ainda forem placeholders (`{{...}}`), **não rode a comparação** (isso é esperado antes do Passo 9): diga que o repositório ainda não foi adaptado à instância (e liste o que falta trocar: `scope.project_id`, os ids dos papéis e o repositório do projeto), e ofereça a adaptação como próximo passo. Caso contrário, compare os agentes da instância (`twinforgecli agent list --json`) com `twinforge/roles.yaml`. No formato do repositório base, `roles:` mapeia uma chave de papel para `{kind, id}` (com `kind: agent`, o `id` é o uuid do agente), e há um bloco `scope` com o `project_id`. Compare por **`id`** e mostre o `name` da instância ao lado. Papéis com `kind` diferente de `agent` não entram na comparação. Se o arquivo não seguir esse formato, **mostre ao usuário como o interpretou** antes de comparar, ou diga que não foi possível, em vez de adivinhar. Um `scope.project_id` que não existe na instância indica um fork ainda não adaptado: avise. Relate agentes que existem de um lado e não do outro. **Somente leitura**: ofereça, mas não execute, uma correção.

Depois da subida, o resultado esperado é **nenhuma divergência** entre os papéis do `roles.yaml` e a instância. Os agentes que a instância já tinha e que não estão no `roles.yaml` (por exemplo, os agentes padrão preservados) aparecem como "só na instância": é esperado, não é erro.
