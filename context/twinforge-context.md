# Contexto TwinForge / twinforgecli

Leia este arquivo antes de operar a instância remota via `twinforgecli` (todos os
comandos `/twinforge*` apontam pra cá). Ele descreve **como a plataforma se comporta**
e **quais dados você precisa ter em mãos**. Ele propositalmente **não contém dados de
nenhuma instância** (URLs, ids, nomes de agentes, caminhos de servidor, tokens): esses
vêm do usuário ou do estado ao vivo.

## Regra principal: dado faltando → pedir ao usuário

Antes de qualquer operação, confira a tabela abaixo. Se um dado necessário não puder ser
obtido de uma fonte automática (coluna "Onde achar"), **pare e peça ao usuário**, dizendo:

1. qual dado falta e para que ele serve;
2. onde o usuário encontra (coluna "Como o usuário obtém");
3. o que você vai fazer assim que ele responder.

Nunca invente, deduza de memória, nem reaproveite um id de outra instância/projeto. Peça
um dado por vez, na ordem da tabela. Nunca imprima, grave em arquivo ou commite tokens.

| Dado | Pra que serve | Onde achar (automático) | Como o usuário obtém / o que pedir |
|---|---|---|---|
| `twinforgecli` instalado | tudo | `which twinforgecli` | Instalar pela doc da TwinForge da conta dele (não invente comando de instalação) |
| URL da API da instância | o CLI saber em qual instância falar | `twinforgecli context show` | A URL que ele abre no navegador pra usar a TwinForge. Configurar com `twinforgecli context set --api-base <url>`. Se o perfil estiver vazio, o CLI cai silenciosamente em `localhost:3100` — isso quase sempre é erro, não intenção |
| Login ativo | qualquer chamada autenticada | `twinforgecli company list --json` retorna dados | Só o usuário consegue: ele roda `twinforgecli connect --persona board` num terminal próprio (login interativo). Você não consegue fazer isso por ele |
| Company id | quase todo comando (`-C <id>`) | `twinforge/roles.yaml` do repo, ou `company list --json` | Se houver mais de uma company, perguntar qual é a do projeto |
| Project id | workspace, tarefas, escopo | `scope.project_id` em `twinforge/roles.yaml`, ou listagem de projetos | Perguntar o nome do projeto e resolver o id pela listagem |
| Papel → agent id | `/tfrepo:agent`, sync de skills | `twinforge/roles.yaml`, ou `agent list --json` | Se o papel não estiver no `roles.yaml`, dizer isso e pedir o nome do agente; **não adivinhar id** |
| Clone local deste repo em `twinforge-repo/` | `status`/`pull`/`push` | `test -d twinforge-repo/.git` | Dar o `git clone` do repo `twinforge-adobe-template` (URL já está em `/tfrepo:status`) na raiz do projeto |
| Token do GitHub (PAT) | só `/tfrepo:init` (clone de repo privado pela plataforma) | — | O usuário gera no GitHub e informa; nunca ecoar de volta nem salvar em arquivo versionado |
| Instância local (127.0.0.1) | só `/tfrepo:local-sync` | — | O usuário sobe o servidor local num terminal dele. Nunca rode o binário `twinforge` sem subcomando: ele sobe um servidor completo com Postgres embutido |

## Como a plataforma se comporta (gotchas confirmados)

**Skills e publicação**
- Um `API error 403` ao criar/atualizar skill pode ser o **WAF do nginx**, não política da
  TwinForge (o corpo é uma página HTML "403 Forbidden"). Ele soma pontos por padrão: `<?php`
  sozinho passa, mas `../` (path traversal) barra sozinho, e `$argv` junto com `require` no mesmo
  arquivo barra (cada um sozinho passa). Prova sem escrever nada: `twinforge/waf-probe.py`, que
  manda cada arquivo para uma skill inexistente (404 = passou, 403 = barrado) e, com `--bisect`,
  aponta a linha.
- Cada `skill file:update` grava uma **versão** com o conteúdo de todos os arquivos da skill, e
  montar essa versão relê arquivo por arquivo, refazendo a cada leitura o inventário de todas as
  skills da company: o upload do n-ésimo arquivo custa ~n refreshes. Medido em 2026-10-21: ~45 s
  por arquivo com 20 arquivos na skill, e crescendo (os 108 arquivos da toolbox dariam ~3 h de
  remota ocupada). Publique primeiro o que os agentes executam (`SKILL.md` e `scripts/`) e avise o
  custo antes. Na plataforma, a correção é o `collectVersionFileInventory` passar
  `assumeInventoryCurrent: true` ao `readFile`. Import pelo GitHub (como as do `agentes-forjados`)
  entra numa versão só.
- O `--payload-json` do `twinforgecli` é um argumento de linha de comando, e o Linux corta argumento
  em 128 KB: arquivo de skill acima de ~100 KB (o payload escapa o conteúdo de novo) não sobe pelo
  CLI. A toolbox divide o módulo de referência em pedaços de ~70 KB por isso.
- O runtime do `pi_local` só enxerga as skills das `desiredSkills` do próprio agente, servidas da
  pasta gerenciada da company. Para testar uma skill nova com um agente que não a tem, adicione-a
  temporariamente (lista completa) e restaure a lista depois.
- Skills criadas com `skills create` guardam o conteúdo **sem frontmatter** (nome e
  descrição são metadados): envie só o corpo em `skill file:update`. Skills importadas/antigas
  mantêm o frontmatter dentro do conteúdo.
- Pasta de skill criada à mão no diretório gerenciado é **sobrescrita** por um `skills create`
  posterior com o mesmo slug. Faça backup antes. O symlink antigo fica como entrada
  "externa" duplicada nos agentes até alguém removê-lo no servidor.
- `skills/sync` de um agente faz **substituição total** de `desiredSkills`: busque a lista
  atual antes e envie a lista completa. Crie/instale a skill antes de referenciá-la.

**Agentes e adapters**
- `CLAUDE.md` na raiz de um repo **só chega** em humanos usando Claude Code e em agentes do
  adapter `claude_local`. Agentes `crucible_local` e `pi_local` leem apenas o próprio
  arquivo de instruções + skills sincronizadas. Qualquer regra que precise chegar aos
  agentes de dev deve ir **numa skill da company** (`CLAUDE.md`/`docs/` é um caminho paralelo,
  não substituto). Pra saber o adapter: `twinforgecli agent get <id>` → `adapterType`.
- **Trocar o adapter muda o formato do modelo.** `pi_local` só aceita o id com prefixo do provedor
  (`air-bizapps/twinforge-coder`); `crucible_local` usa o nome curto (`twinforge-coder`). Trocar
  `adapterType` sem trocar `adapterConfig.model` faz **todo run morrer no adapter** com
  `adapter_failed: Configured Pi model is unavailable: <modelo>. Available models: …` (log vazio,
  zero tokens — o agente nem chega a ler instruções ou skills). Depois de 3 tentativas a
  plataforma abre uma recuperação (`stranded_assigned_issue`) e acorda o **Tech Lead, que pode
  acabar executando a tarefa de dev ele mesmo** (cria worktree/branch/commit), violando o guardrail
  dele. Sempre confira `twinforgecli agent get <id>` (`adapterType` + `adapterConfig.model`) e rode
  uma tarefa trivial antes de liberar trabalho real.
- **Skills `missing` num agente `pi_local` são transitórias.** `agent skills <id>` mostra
  "Configured but not currently linked into the Pi skills home" até o primeiro heartbeat que
  realmente roda; depois viram `installed`. Run que falha no adapter não liga nada, então elas
  seguem `missing`. Um agente Pi abre o `SKILL.md` do disco (`/twinforge/.pi/agent/skills/<slug>--<hash>/`)
  sob demanda; no `crucible_local` o texto das skills é injetado no contexto de todo run (~86k tokens
  de entrada por run no Backend, contra 25–43k no Pi).
- **No `pi_local`, o índice de skills é da instância, não do agente** (verificado em 2026-10-15
  com issue de teste). Todo agente Pi recebe o mesmo diretório de skills e vê no system prompt
  nome + descrição de todas as skills ligadas ali pelos agentes (o Backend viu 40, 11 delas de
  outros papéis). `desiredSkills` decide o que a plataforma liga, não o que cada agente enxerga.
  O corpo do `SKILL.md` só entra quando o agente abre o arquivo (sob demanda). Consequência: a
  descrição de cada skill precisa dizer de quem ela é, e a escolha por tarefa vem da linha
  "Skills" da subtarefa (`bunge-subtask-contract`). Só o `AGENTS.md` é anexado ao system prompt;
  o resto do pacote de instruções não.
- **Sessão do agente é por issue e, na prática, renovada a cada heartbeat** (verificado em
  2026-10-15 nos últimos 200 runs + teste). A plataforma guarda uma sessão por issue e tentaria
  retomá-la na mesma issue, mas reseta quando a config efetiva muda ("workspace config" muda em
  todo run) e por regra nos wakes `issue_assigned`, `execution_review_requested` e
  `execution_changes_requested`. Subtarefas diferentes nunca compartilham sessão. A continuidade
  entre heartbeats está na issue (descrição, comentários, documentos), não na memória do modelo.
  Runs sem issue (Concierge, wakes avulsos) retomam a sessão do agente e podem acumular contexto
  (já houve run do Backend com 1,8M tokens de entrada); `agent runtime-state:reset-session` zera.
- `budgetMonthlyCents = 0` significa "sem limite" no servidor, mas um agente lendo o próprio
  registro pode calcular gasto/0 como "orçamento estourado" e se auto-bloquear. Se uma
  tarefa bloquear citando orçamento, confira `budgetMonthlyCents`/`spentMonthlyCents` antes
  de investigar custo; a correção é um valor positivo real. Reconfira ao contratar agente novo
  (nasce com 0).
- `agent update` **não** aceita `-C/--company-id` (passar quebra a saída JSON silenciosamente:
  stdout vazio). `company update` aceita.

**Issues**
- Issues podem **reabrir** depois de `done`: comentários postados por agente sem token de
  agente válido são atribuídos como `authorType: user` e re-disparam o wake. Não re-derive o
  trabalho: faça uma checagem rápida, `PATCH` pra `done`, no máximo um comentário e confira o
  status depois. Se houver pausa (tree hold) restrita a "responder/triar", só responda e
  não mexa no status.
- Comentário de **usuário** (board, CLI com login de board) numa issue `done`, `cancelled` ou
  `blocked` com agente responsável move a issue para `todo` e acorda o agente. Não comente em
  issue de teste já fechada para deixar registro: registre no repo (PR, roteiro).
- `issue comments` exige o **UUID** da issue, não o identificador curto. Conversas do
  Concierge aparecem como issues `todo` ocultas que `issue list` não retorna, mas
  `issue get <id curto>` encontra.
- **Nunca confie num relatório "done" pelo texto**, por mais detalhado que seja (hashes de
  commit, nomes de seção, itens de checklist): já houve relatórios inteiramente fabricados.
  Verifique: `git log`/diff para código; para skill, compare `updatedAt` com a criação da
  issue **e** busque no conteúdo publicado as strings que o relatório diz ter adicionado.
  Se fabricado, reabra citando a evidência e peça read-back antes de novo "done".

**Workspace / infraestrutura**
- Um registro de project-workspace com `cwd: null` e só `repoUrl` faz a plataforma usar um
  clone **próprio** como diretório de execução. Se esse clone não funciona (ex.: sem token),
  agentes commitam no checkout errado ("branch errada" pode ser "workspace errado"). Confira
  `project-workspace list` ao investigar commit/push que "sumiu".
- Versão nova do CLI pode zerar o perfil (`~/.twinforge/context.json`). Sintoma: cai em
  `localhost:3100`. Correção: ver linhas "URL da API" e "Login ativo" da tabela.

## Segurança e escrita em produção

- `push`, `agent delete`, `skill delete` e qualquer coisa sob `sync` escrevem na instância
  de verdade: rode `/tfrepo:status` antes e confirme com o usuário.
- Explore com `twinforgecli <tópico> --help` antes de chutar uma flag.
- Nada de segredo em arquivo versionado, em comentário de issue ou na saída pro usuário.
