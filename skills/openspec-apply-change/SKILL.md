---
name: openspec-apply-change
description: "Backend/Frontend Developer. Implementa as tarefas de uma subtarefa [BACK]/[FRONT] que pertence a uma change OpenSpec (linhas `Change:` e `Tarefas:` na descrição). Implementa só as tarefas atribuídas, marca [x] no tasks.md, escreve os testes sem executá-los, commita localmente sem push e posta o relatório de entrega."
---

# openspec-apply-change (Backend / Frontend)

Use quando a sua subtarefa começar com as linhas `Change:`, `Tarefas:`,
`Branch:` e `Artefatos:`. Siga também tudo de `bunge-engineering-practices`
(workspace, relatório de entrega antes de `done`). Esta skill só acrescenta o que é
específico do OpenSpec.

## Passos

**1. Leia o cabeçalho da subtarefa.** Guarde `CH` (Change), `TASKS` (lista de
números, ex. 2.1 2.2 2.3), `BR` (Branch). Faltando alguma linha: `blocked` +
comentário para o Tech Lead. Pare.

O resto da descrição segue `bunge-subtask-contract` (Entradas, Skills, Fora
de escopo, Cenários a provar). Abra só o que ela lista, além dos artefatos da
change no passo 3; não leia a card pai nem as subtarefas irmãs.

**2. Entre no worktree da card.** A branch já existe no `origin` (criada
pelo OpenSpec Manager). O worktree pode já existir (criado pelo OpenSpec
Manager ou pela subtarefa anterior) ou precisar ser criado:
```bash
WS=/twinforge/instances/default/workspaces/{{uuid}}
REPO="$WS"; [ -d "$WS/bunge-magento2/.git" ] && REPO="$WS/bunge-magento2"
WORKTREE="$WS/worktrees/<ID-externo>"

# Se o worktree já existe, entre nele e atualize
if [ -d "$WORKTREE/.git" ]; then
  cd "$WORKTREE"
  git fetch origin --prune
  git merge --ff-only "origin/$BR"
else
  # Prepare o checkout principal e crie o worktree a partir da branch remota
  cd "$REPO" && git checkout "$BUNGE_BASE_BRANCH" \
    && git fetch origin "$BUNGE_BASE_BRANCH" \
    && git merge --ff-only "origin/$BUNGE_BASE_BRANCH"
  git worktree add "$WORKTREE" "origin/$BR"
  cd "$WORKTREE"
fi
git branch --show-current          # precisa ser $BR
```
Se o `merge --ff-only` falhar (divergência), não resolva sozinho: `blocked` +
comentário com a saída. Pare.

**Nunca faça `git checkout` no diretório compartilhado** (`$REPO`) — ele
fica permanentemente em `$BUNGE_BASE_BRANCH`. Todo trabalho de
desenvolvimento roda no worktree (`$WORKTREE`).

**3. Contexto da change.**
```bash
openspec instructions apply --change "$CH" --json
```
Leia os `contextFiles` (proposal, specs, design, tasks). Se `state` for
`blocked`, comente o motivo que o JSON dá e mova para `blocked`. Pare.

**4. Implemente só as suas tarefas, em ordem.** Para cada número em `TASKS`:
1. Leia a tarefa no `tasks.md` (ação + "entrega:").
2. Implemente, seguindo as skills da sua especialidade. Tarefa de teste
   unitário = escrever o teste (`create-unit-tests`), **sem rodar** PHPUnit nem
   cobertura.
3. Marque a linha: `- [ ] 2.1 ...` → `- [x] 2.1 ...` (só as suas).

**4.5. Verificação de rotas REST (aplica-se se sua tarefa cria ou muda `webapi.xml`).**
Antes de prosseguir para o commit, compare cada rota do `webapi.xml` que você
escreveu contra o `design.md` da change:
```bash
git show "$B:openspec/changes/$CH/design.md" | grep -A 20 'Rotas definidas\|route\|/V1/'
```
Compare **caractere por caractere** — `/V1/bunge/listprice` ≠
`/V1/bunge/listprices` (singular ≠ plural). Se alguma rota diverge do design
aprovado, corrija o `webapi.xml` antes de commitar. Não é uma variação
aceitável — é um bug.

**4.6. Verificação de `getList()` para REST (aplica-se se sua tarefa expõe `getList()` via webapi.xml).**
Abra a interface do repositório que a rota referencia e confira o tipo de
retorno de `getList()`. Se é `\Magento\Framework\Api\Search\SearchResultInterface`
(com `Search\` prefixo — tipo orientado a índice), **não funciona** para CRUD
REST — o output processor só vai serializar `id`. Escale ao Tech Lead antes
de commitar; a correção requer criar `*SearchResultsInterface` + classe
concreta + `<preference>` (receita em `adobe-commerce-standards`, seção
"SearchResultsInterface for REST list endpoints").

**4.7. Verificação de PUT parcial (aplica-se se sua tarefa implementa `update()` com corpo parcial).**
Se o método `update()` recebe um objeto com apenas os campos que o cliente
quer mudar, use `$existing->addData($data->getData())` (merge) — nunca
`setData()` (substitui o array inteiro, zerando campos não enviados).
Confira no código: a linha que aplica os dados deve conter `addData`, não
`setData`.

Pare e mova para `blocked` (comentário para o Tech Lead, que aciona o OpenSpec
Manager) se: a tarefa for ambígua, contradizer o `design.md`, exigir algo fora
da lista `TASKS`. **Nunca edite
`proposal.md`, `design.md` ou specs**; só os `[x]` do `tasks.md`.

**5. Commit (sem push).**
```bash
git add -A && git status --short          # só arquivos da sua tarefa + tasks.md
git commit -m "BUN-<EXT>: <CH> tarefas <TASKS>"
git log "origin/$BR..HEAD" --oneline       # precisa listar seus commits (não houve push)
```
A branch já existe no `origin` (criada pelo OpenSpec Manager). Por isso a prova
de "não houve push" é o comando acima listar seus commits, e não o
`git ls-remote` vazio.

Depois do commit, rode o `magento-verify` (skill `adobe-magento-toolbox`, comando em
`bunge-engineering-practices`) e corrija até `VERIFY OK` antes do passo 6.

**6. Comentário final e `done`.**
```
Apply OpenSpec `<CH>` — tarefas <TASKS> concluídas
Commits locais (sem push): <hash> <mensagem>
Prova de commit: <saída real de `git log --oneline -1` e de `git log "origin/$BR..HEAD" --oneline`>
O que mudou: <1-3 frases>
Arquivos: <lista>
Testes unitários escritos (não executados): <arquivos e cenários por método>
Integridade: <última linha real da checagem, "OK: ...">
tasks.md: <n> de <n> tarefas desta subtarefa marcadas [x]
Verificação de rotas REST: <"rotas de webapi.xml conferidas contra design.md — OK" ou "não aplica">
Cenários atendidos:
- <capability> / <nome do cenário>: <arquivo:linha que implementa o THEN> · teste: <Test/Unit/...::testX, ou "sem teste">
Lacunas: <o que faltou na descrição, nos artefatos ou nas skills, ou "sem lacunas">
```
Hash e saída do `git log` colados da execução real, nunca de memória: o Tech
Lead confere a existência do commit antes de liberar a próxima subtarefa.
Este é o relatório de entrega (ver `bunge-engineering-practices`): o Tech Lead
usa ele direto para o PR, sem reverificar. Todo cenário da linha
`Cenários a provar:` precisa aparecer em `Cenários atendidos`, apontando o
código que o implementa. **Não rode testes para isso**, e nunca escreva que os
testes passam. Cenário que você não implementou vira `blocked` com o motivo, e
não `done`.

## Nunca
- `git push`, `gh pr ...`, `openspec archive`, `openspec new change`.
- Marcar `[x]` em tarefa que não é sua ou que não implementou.
- Rodar `vendor/bin/phpunit`, `grumphp` ou checagem de cobertura.
