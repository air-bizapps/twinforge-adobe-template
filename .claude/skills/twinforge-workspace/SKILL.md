---
name: twinforge-workspace
description: Conecta o repositório GitHub de um projeto TwinForge à instância, para os agentes conseguirem puxar o código - cria o workspace do projeto com a URL do repositório, guia a criação do segredo com o PAT do GitHub SEM o token passar pela conversa e vincula o segredo ao projeto sem apagar as outras variáveis. Use quando o usuário pedir para configurar, conectar ou corrigir o repositório de um projeto, criar a secret do GitHub, ou quando os agentes não conseguirem acessar o código. Também é chamado pelo setup antes de subir agentes. Gatilhos - "conecta o repositório do projeto", "cria a secret do github", "os agentes não conseguem puxar o repo", /twinforge:workspace.
---

# twinforge-workspace

Faz o que, sem esta skill, é manual: informar o repositório GitHub do projeto na instância e criar o segredo com o PAT para os agentes conseguirem clonar e abrir PR. Funciona em qualquer pasta e sem nenhum aplicativo auxiliar.

## Regras inegociáveis

- **Nunca** peça, receba, imprima, grave ou repita um token (PAT) ou senha, **nem na conversa, nem em arquivo, nem em argumento de comando** (`--value` é proibido). O PAT só entra pelo terminal do próprio usuário, por uma variável de ambiente lida **sem eco** (veja o Passo 4). Se um token aparecer na conversa mesmo assim, avise que ele foi exposto, peça que seja **revogado no GitHub** e gerado outro, e não o repita.
- **Nunca** rode `twinforgecli connect` (exige terminal interativo). Se um comando exigir TTY, oriente o usuário a rodá-lo em **outro terminal**.
- **Toda escrita na instância** (criar segredo, vincular ao projeto, criar ou alterar workspace) segue: mostrar a instância e a company em uso, o comando e o efeito esperado; esperar um "sim" explícito; só então executar. Recusa ou ajuste = nada é executado. A criação do segredo é feita **pelo usuário**, não por você.
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

## Fluxo

Em todo comando, `--context "<arquivo>" --profile "<perfil>"` da instância fixada, e `-C <companyId>` quando o comando pedir.

### Passo 1. Projeto e repositório

1. Liste os projetos (`twinforgecli project list --json`) e peça a escolha **por nome**; nunca peça uuid.
2. Peça a URL do repositório: `https://github.com/<org>/<repo>` (exija `https://`, sem credenciais embutidas na URL e sem `.git` no final).

### Passo 2. Estado atual (somente leitura)

- **Workspace:** `twinforgecli project-workspace list <projectId> --json`. Há um com essa `repoUrl`? Há outro com `repoUrl` diferente? Nenhum?
- **Vínculo do segredo:** `twinforgecli project get <projectId> --json` e leia `env`. Mostre **só as chaves e os tipos** (`secret_ref` ou `plain`), nunca valores. `GH_TOKEN` existe e aponta para qual segredo?
- **Segredos da company:** `twinforgecli secrets list -C <companyId> --json` (só metadados). Existe algum cujo nome ou descrição cita **este** repositório (convenção: `GitHub Token - <repo>`)?
- Diga em uma tabela o que já está pronto e o que falta (workspace, `GH_TOKEN` no projeto, segredo). Se tudo estiver pronto, conclua sem escrever nada.

### Passo 3. Repositório público ou privado

Teste sem credenciais: `GIT_TERMINAL_PROMPT=0 git ls-remote --heads https://github.com/<org>/<repo>`. Responde: é público. Falha: é privado ou a URL está errada (confirme a URL com o usuário). **Os agentes que abrem PR precisam de PAT mesmo em repositório público**; pergunte se vão fazer push ou PR. Sem PAT, pule para o Passo 5 e avise o que os agentes não poderão fazer.

### Passo 4. O segredo com o PAT (o token nunca passa por você)

1. **Segredo já existe para este repositório?** Reutilize só se o nome ou a descrição citar **este** repositório: reaproveitar um token entre repositórios sem reautorizá-lo é um erro já visto. Guarde o `secretId` e vá ao Passo 5.
2. **Senão, oriente a criação do PAT no GitHub** (Settings → Developer settings → Personal access tokens → Fine-grained): escopo **só neste repositório**, permissões de conteúdo e de pull requests em leitura e escrita (e metadados em leitura), validade definida. Não gere nem adivinhe o valor.
3. **Entregue ao usuário o comando para rodar em OUTRO terminal** (não na sessão do Claude Code, e **não** com o prefixo `!`, porque o prompt oculto precisa de um terminal interativo e a saída iria para a conversa). O comando **não contém o token**; o `read -s` lê sem eco e sem gravar no histórico do shell:

```bash
# bash
read -rsp "Cole o PAT do GitHub e tecle Enter: " GH_TOKEN; echo; export GH_TOKEN
twinforgecli secrets create -C <companyId> --context "<arquivo>" --profile "<perfil>" \
  --name "GitHub Token - <repo>" --key gh_token --provider local_encrypted \
  --value-env GH_TOKEN --description "PAT com push/PR em <org>/<repo>"
unset GH_TOKEN
```

   No zsh, troque a primeira linha por `read -rs "GH_TOKEN?Cole o PAT do GitHub e tecle Enter: "; echo; export GH_TOKEN`. Preencha `<companyId>`, `<arquivo>`, `<perfil>`, `<repo>` e `<org>` com os valores reais antes de mostrar o comando.
4. **Confirme sem ver o valor:** peça que o usuário avise quando terminar e rode `secrets list` para achar o segredo pelo nome. Guarde o `secretId`. Se não aparecer, ajude a diagnosticar (instância, company e perfil do terminal onde ele rodou) sem pedir o token.

### Passo 5. Vincular o segredo ao projeto (sem apagar o resto)

`project update --env-json` **substitui o env inteiro**. Por isso:
1. Leia o `env` atual (`project get`), preservando **todas** as chaves e campos existentes (incluindo `projectionClass` e `projectionAllowlistKey`).
2. Monte o novo env = o atual **mais** `"GH_TOKEN": {"type":"secret_ref","version":"latest","secretId":"<secretId>"}`.
3. Mostre o **diff de chaves** (adicionadas, mantidas, alteradas; sem valores simples) e o comando `twinforgecli project update <projectId> -C <companyId> --env-json '<json>'`. Se `GH_TOKEN` já existir apontando para **outro** segredo, destaque isso e peça confirmação específica. Execute só após o "sim".

### Passo 6. Workspace do projeto

- Sem workspace do repositório: mostre e, após o "sim", rode `twinforgecli project-workspace create <projectId> --payload-json '{"name":"<repo>","sourceType":"git_repo","repoUrl":"https://github.com/<org>/<repo>"}' --json`.
- Com workspace de outra URL: mostre os dois e ofereça `project-workspace update` (com confirmação); nunca apague um workspace sem pedido explícito.
- Depois, `project-workspace list` e confira `repoUrl`, `sourceType` e `isPrimary`. Se for o único e `isPrimary` for falso, diga isso e ofereça marcá-lo como principal **se o campo permitir** (confirme na resposta da CLI; não invente campos).

### Passo 7. Verificação e resumo (somente leitura)

Releia workspace, `env` (só as chaves) e o segredo (pelo nome). Resuma em uma tabela, **sem nenhum token**: "workspace ok", "`GH_TOKEN` vinculado ao segredo <nome>", "segredo existe". Diga o que **não** dá para verificar daqui: se o clone com o token funciona só aparece quando um agente roda nesse projeto. Sugira conferir no primeiro uso.

## Modo checagem (somente leitura)

Chamado pelo `setup` na revalidação: rode só o Passo 2 para o projeto-alvo e relate **OK** (workspace e `GH_TOKEN` presentes), **ATENÇÃO** (workspace sem `GH_TOKEN`, ou `GH_TOKEN` apontando para segredo de outro repositório) ou **FALHA** (sem workspace). Não escreva nada; para reparar, siga os Passos 3 a 6.

## Erros

| O que aparece | O que dizer |
|---|---|
| `403` / "does not have access" ao criar segredo, vincular ou criar workspace | Seu papel nesta instância não permite; peça no canal do Slack `bizapps-twinforge-forjadores` a mudança para owner ou admin (a skill não envia a mensagem). |
| `Internal server error` com perfil de outra instância | Faltou fixar `--context`/`--profile` corretos; refaça a seleção da instância. |
| Segredo criado em outro terminal não aparece em `secrets list` | O terminal usou outra instância, company ou perfil; compare com os valores do comando. |
| `git ls-remote` falha mesmo com a URL certa | Repositório privado: siga o Passo 4. |
