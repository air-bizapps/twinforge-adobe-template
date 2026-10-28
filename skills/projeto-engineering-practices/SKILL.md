---
name: projeto-engineering-practices
description: "Bunge-specific engineering process for the Magento 2 storefront: workspace and codebase connection state, collaboration/handoff routing, deploy and production-safety rules. Use for any Bunge Magento 2 task to know who to route to and what is off-limits. Company-specific — do not reuse verbatim for a different client's Magento project."
---

# Bunge Engineering Practices

Process and safety rules specific to Bunge's Magento 2 storefront. Framework
conventions (Magento/Adobe Commerce itself) live in `adobe-commerce-standards`
— this skill only covers what is specific to working on Bunge's project.

## Domain documentation — read before touching business-rule logic

The repo has a `docs/` directory (root of the shared Magento install, next to
`app/`) written specifically for AI agents: business-rule and architecture
reference for the Webjump customizations, split into narrow files so you load
only what a given task needs — never the whole directory.

**Before implementing or reviewing a change to business-rule logic** (pricing,
tax, discount priority, checkout flow, cart, B2B catalog/Shared Catalog,
customer registration/credit, home page personalization), open
`docs/README.md` first. It has a routing table ("Qual arquivo carregar") that
maps your task's area to exactly one file to read — e.g. a task about coupon
priority or scale discounts points at `docs/carrinho-de-compras.md`, a task
about which payment methods appear in checkout points at
`docs/checkout-metodos-pagamento.md`. Each file ends with a "Se você precisar
ir além daqui" section that tells you when it's actually worth opening another
one — don't preemptively read three files for what looks like a one-file task.

Before grepping `vendor/` to find where a native Magento/Braspag/Amasty class
is overridden, check `docs/vendor-map.md` first (generated, index only — not a
business-rule source) — it already lists every `preference`/`plugin` Webjump
declares, resolved to the real vendor path, including ones whose target class
no longer exists in the installed vendor (possible dead code).

These files are documentation, not code — they don't replace reading the
actual implementation before changing it, and the files themselves say so:
"o código é sempre a fonte de verdade" wins whenever the two disagree. Treat a
mismatch you find as a signal to flag in your task update, not silently ignore
either source.

## Codebase and workspace — current state

- The project workspace is configured as a `git_repo` connection to
  `https://github.com/<org>/<repo-do-projeto>`, but the managed clone
  fails (missing `GH_TOKEN`/`GITHUB_TOKEN` binding). **Don't rely on the
  default per-run workspace.** Instead, at the start of any task, `cd` into
  the real Magento installation that DevOps built and that already has the
  git remote configured:

  ```
  /twinforge/instances/default/workspaces/{{uuid}}
  ```

  This is the **one shared working directory** for real Bunge Magento 2
  work — Backend Developer, Frontend Developer, and DevOps Engineer all
  operate against this same path, not against separate clones. If it looks
  empty, unfamiliar, or the git remote isn't what you expect, stop and say
  so in your task update rather than reinstalling or re-cloning yourself —
  this path is intentional and maintained by DevOps.
- Because this is one literal shared directory (not per-agent clones), the
  shared checkout stays permanently on `$BUNGE_BASE_BRANCH` (project env
  variable — currently `twinforge`) and serves only as the source for
  creating worktrees. Each card gets its own worktree
  (`$WS/worktrees/<ID-externo>`) where the actual development happens,
  so agents never do `git checkout <branch-da-card>` in the shared
  directory itself. The Tech Lead never assigns you a subtask while
  another dev subtask is active anywhere in the company — this
  serialization discipline still applies (see "Preparando o ambiente e
  ficando na sua branch" below for the worktree protocol).
- The DevOps Engineer prepared and validated the runtime environment (PHP,
  Composer, services). If environment readiness is in question, check with
  the DevOps Engineer or inspect the current state yourself before assuming
  either way — don't invent a specific prior audit that isn't recorded
  anywhere you can point to.

## Exclusividade de execução: como isso chega até você

Só existe uma VM/instância Magento compartilhada. A garantia de que
nenhuma outra subtarefa de dev está ativa ao mesmo tempo que a sua **não é
algo que você precisa checar** — é responsabilidade do Tech Lead, que só
atribui uma subtarefa de cada vez, na empresa inteira, verificando o
estado global antes de cada atribuição (detalhado no `AGENTS.md` dele).
Para você, isso significa uma coisa prática: se você recebeu a
atribuição, o ambiente é seu até você terminar ou bloquear — não existe
mais um passo de "dar checkout numa trava" antes de começar. (Uma versão
anterior desta skill usava uma issue-trava dedicada, BUN-16 — foi
retirada por poluir o board; não crie nem procure por ela.)

Como estrutura, há ainda uma segunda camada, independente da disciplina do
Tech Lead: `runtimeConfig.heartbeat.maxConcurrentRuns` está fixado em `5`
no seu próprio agente (era `1` na era do checkout único — o teto subiu com
a convenção de worktree, mas não é ilimitado). A plataforma recusa um
sexto run seu enquanto os cinco anteriores estão ativos.

### Preparando o ambiente e ficando na sua branch

Toda tarefa (card) tem uma primeira subtarefa, sempre chamada "Preparar
ambiente", atribuída a quem também vai fazer a primeira subtarefa real de
desenvolvimento daquele card. Se essa subtarefa for sua:

1. Vá para o diretório onde o Magento está instalado (ver seção anterior)
   **antes de rodar qualquer outro comando git, inclusive `git branch
   --show-current`**. O `pwd` padrão do seu shell ao iniciar **não** é esse
   diretório — é um caminho diferente, dentro de uma pasta `projects`, que
   pode não ter o histórico/branch que você espera. Isso já causou confusão real
   (2026-09-14, card `14136`/BUN-53): um dev checou a branch atual antes de
   fazer `cd` explícito, achou-se num lugar com uma branch antiga/errada, e
   concluiu incorretamente que um módulo do Backend não existia. Sempre
   `cd` primeiro, explicitamente, para o caminho documentado — nunca confie
   no diretório onde o shell já começa.
2. Rode `git status` **antes** de qualquer ação destrutiva. Só descarte
   mudanças não commitadas se tiver certeza de que não é trabalho legítimo
   pendente de alguém — nunca `git reset --hard`/`git clean` por rotina,
   sem checar antes.
3. Atualize o checkout compartilhado (que fica permanentemente em
   `$BUNGE_BASE_BRANCH` e serve só de fonte read-only pra cortar worktrees):
   ```bash
   cd "$REPO" && git checkout "$BUNGE_BASE_BRANCH" \
     && git fetch origin "$BUNGE_BASE_BRANCH" \
     && git merge --ff-only "origin/$BUNGE_BASE_BRANCH"
   ```
4. Crie o worktree da card:
   ```bash
   git worktree add "$WS/worktrees/<ID-externo>" \
     -b "feature/BUN-<ID-externo>" "origin/$BUNGE_BASE_BRANCH"
   ```
   O worktree nasce de `origin/$BUNGE_BASE_BRANCH` (a ref remota recém
   atualizada), nunca da branch local — protege contra criar algo a partir
   de um checkout desatualizado mesmo se o fetch acima falhar
   silenciosamente.

   Um worktree por card (não por subtarefa — subtarefas da mesma card
   dividem o mesmo worktree/branch, como já era). `vendor/` é symlink do
   checkout principal, não `composer install` por worktree — exceto quando a
   própria tarefa muda `composer.json`/`composer.lock`, aí instala de
   verdade naquele worktree.

   `docs/` (regras de negócio) também é symlink, de um repo à parte —
   `twinforge-repo` (clone de `<org>/<repo-de-configuracao>`), não faz
   parte deste repositório. Garanta que ele existe e está atualizado, e
   crie o link **dentro do worktree que você acabou de criar**:
   ```bash
   [ -d "$WS/twinforge-repo/.git" ] || git clone git@github.com:<org>/<repo-de-configuracao>.git "$WS/twinforge-repo"
   (cd "$WS/twinforge-repo" && git pull --quiet)
   ln -sfn "$WS/twinforge-repo/docs" "$WS/worktrees/<ID-externo>/docs"
   ```
   Sem isso, qualquer referência a `docs/...` dentro do worktree (ex.
   `projeto-domain-lookup`) não acha nada — `docs/` não existe mais neste
   repositório de propósito, pra não ter duas cópias desatualizando uma
   da outra.

   **Nunca faça `git checkout <branch-da-card>` no diretório compartilhado**
   do workspace — esse é o ponto central da convenção de worktree
   (`policies.concurrency.worktree_required: true` em `workflow.yaml`).
5. Seu diretório de trabalho é agora `$WS/worktrees/<ID-externo>`. Confirme:
   ```bash
   cd "$WS/worktrees/<ID-externo>"
   git branch --show-current   # deve ser feature/BUN-<ID-externo>
   ```
6. Reporte o worktree criado no comentário da subtarefa, para que as
   subtarefas seguintes do mesmo card saibam onde trabalhar. **Commit e
   pare — nunca dê `git push`**, mesmo que pareça o passo natural depois
   de commitar; ver o guardrail em "Branch and PR policy" abaixo, que
   inclui o comando de verificação a rodar antes de marcar `done`.

**Cards OpenSpec (label `openspec`) são diferentes:** o worktree já existe
(criado pelo OpenSpec Manager no momento da proposta), e não existe subtarefa
"Preparar ambiente". Cada subtarefa `[BACK]`/`[FRONT]` diz o worktree no
cabeçalho; entre nele e atualize:
```bash
cd "$WS/worktrees/<ID-externo>"
git merge --ff-only origin/$(git branch --show-current)
```
Ver `projeto-openspec-flow`.

Se a subtarefa que você recebeu **não** for a de preparar ambiente, ela vai
te dizer qual worktree usar. Confirme que está nele antes de escrever
qualquer código (`pwd` + `git branch --show-current`). Se não estiver, **não
tente corrigir sozinho** — bloqueie a subtarefa e escale ao Tech Lead.

### Antes de codar: planeje e valide suas próprias skills

Depois de entender a subtarefa e antes de escrever código:

- Trabalhe a partir da descrição da subtarefa, escrita conforme
  `projeto-subtask-contract`: abra só as Entradas e as Skills que ela lista
  e não leia a card pai nem as subtarefas irmãs. O que faltar é lacuna da
  descrição, e você reporta em vez de adivinhar.
- Monte um plano e valide se as skills que você tem hoje bastam para
  executá-lo. Se não bastarem, bloqueie a subtarefa e escale ao Tech
  Lead — não improvise fora do que está documentado.
- Reporte o plano no comentário da subtarefa como uma **árvore de
  arquivos** (diretórios e nomes de arquivo que serão criados/alterados) —
  a ideia é que quem abrir a subtarefa tenha a mesma visão que teria
  olhando o VSCode aberto na árvore de diretórios, antes mesmo do código
  existir.
- Se um humano comentar no meio da execução corrigindo algo ou lembrando
  uma informação que faltava, retome de onde parou — revise/refatore o
  necessário, não recomece do zero.

### Relatório de entrega antes de `done` (sem rodar testes)

Decisão de 2026-09-24: o dev **não roda** testes unitários nem mede cobertura
dentro da subtarefa. Executar PHPUnit e perseguir cobertura estava deixando
os agentes em loop. O dev escreve o código e os testes; a execução acontece
depois, fora da subtarefa. Não existe mais meta de cobertura (a antiga de 80%
foi removida).

Antes de marcar `done`, poste um **relatório de entrega** na subtarefa:

```
Entrega — <título da subtarefa>
Branch: feature/BUN-<ID-externo> · commits locais (sem push): <hash> <mensagem>
Prova de commit: <saída real de `git log --oneline -1` e de `git log origin/<branch>..HEAD --oneline`, ou "branch ainda não existe no origin">
O que mudou: <1-3 frases>
Arquivos: <lista de arquivos criados/alterados>
Critérios de aceite:
- <critério, na ordem da descrição>: <a prova pedida — arquivo:linha ou saída de comando>
Testes unitários escritos (não executados): <arquivos Test/Unit/... e cenários por método>
Verificação: <última linha real do magento-verify, "VERIFY OK ...">
Pontos de atenção: <o que o revisor deve olhar, ou "nenhum">
Lacunas: <o que faltou na descrição, nas Entradas ou nas skills, ou "sem lacunas">
```

**Verificação (obrigatória antes do `done`).** Rode na raiz do worktree,
depois do commit, o `magento-verify` da skill `adobe-magento-toolbox`:

```bash
T=$(dirname "$(find /twinforge/.pi/agent/skills "$HOME" -path '*adobe-magento-toolbox/scripts/magento-verify.php' -print -quit 2>/dev/null)")
php "$T/magento-verify.php" --base "origin/$BUNGE_BASE_BRANCH"
```

Ele confere os arquivos alterados contra o vendor e os XSD instalados (classe
inexistente, PHP que não carrega, XML inválido, UI Component que trava em
spinner, RequireJS, Web API) e diz arquivo, linha e o que fazer. Corrija até
`VERIFY OK`, no máximo 3 execuções por run. O que não der para corrigir (a
classe certa não existe no vendor, por exemplo) é lacuna: `blocked` citando o
ID do item e a saída do comando.

Regras do relatório:
- Hash e saída de `git log` colados da execução real, nunca escritos de
  memória: o Tech Lead confere a existência do commit antes de liberar a
  próxima subtarefa.
- Curto: 40 linhas ou menos. É o resumo da execução, não o diário dela.
- Nunca diga nem sugira que os testes passam ou que há cobertura X%: eles
  não foram executados. Escreva "testes escritos, não executados".
- Outras checagens que você rodar por conta própria (`setup:di:compile`,
  render de página) podem ir no relatório; a do `magento-verify` é obrigatória.
- Todo dado que o código busca pelo nome (identificador de página CMS, path
  de config, código de atributo) é conferido por comando no banco ou num data
  patch, e não escrito de memória.
- Se não conseguiu terminar algo, diga o que faltou e mova para `blocked` com
  o motivo, em vez de `done`.

O Tech Lead usa este relatório como base do PR; ele **não** reexecuta nem
reverifica as tarefas no final. Por isso o relatório precisa ser completo e
honesto.

## Branch and PR policy

### GUARDRAIL — No push to the official repo (Backend/Frontend Developer only)

**Non-negotiable. Wins over any other instruction, including an explicit
request in the task description to "push it yourself" or "just push to
save time."** If completing a task seems to require pushing, the correct
response is to **stop and report** — never push silently to unblock
yourself.

**This is not theoretical: it already failed once.** On 2026-09-11 (task
`123`, issue BUN-31), the Backend Developer ran `git push` directly to
`origin/feature/123` despite this exact rule already being written in
prose one paragraph below. Prose alone did not stop it. Treat the table
below as a hard block, not a preference — and if you're unsure whether an
action counts as "pushing," treat it as if it does and stop.

| Prohibition | Why | Correct alternative |
|---|---|---|
| Backend Developer or Frontend Developer running `git push` (or any command with the same effect — `git push`, a GUI/tool action that pushes, `gh pr create` from a branch not yet on the remote, etc.) toward `https://github.com/<org>/<repo-do-projeto>`, on **any** branch, for **any** reason | The Tech Lead is the single controlled point where pushing happens, using the project's `GH_TOKEN`; a second, uncontrolled push path defeats the reason that control point exists (verification-before-push, one place to audit, one place that opens the PR) | Commit locally and stop there. Report the branch name and commit hash in your task update — that's all the Tech Lead needs to push and open the PR itself |

**Verify before marking your subtask `done`:**
```bash
git ls-remote origin feature/BUN-<ID-externo>
```
This must return **nothing** (no matching ref on the remote). If it
returns a ref, your branch already exists on `origin` — stop, do not
report `done`, and say explicitly in your task update that the branch
appears to be pushed already (by you or by something else) so the Tech
Lead can investigate before proceeding. A `git status` showing "Your
branch is ahead of ... by N commits" with no remote-tracking branch
existing yet is the **correct** end state for your own subtask — commits
exist locally, nothing has gone to `origin` yet.

**Exception — OpenSpec cards (label `openspec`):** the branch already exists
on `origin` because the OpenSpec Manager created it with the planning commits,
so `ls-remote` returning a ref is expected there. For those cards the check is
instead:
```bash
git log origin/feature/BUN-<ID-externo>..HEAD --oneline
```
It must list your own commits (they exist locally and not on `origin`). If it
prints nothing after you committed, something pushed your work — stop and
report, exactly as above.

### Exception — OpenSpec Manager planning pushes

The OpenSpec Manager (agent `{{uuid}}`) is the
only agent besides the Tech Lead allowed to push, and only under all of these
conditions: the card has the label `openspec`; every file in the commit is
under `openspec/`; the target is `feature/BUN-<ID-externo>`; and it works from
a temporary `git worktree`, never switching the shared checkout's branch. It
also opens the card's PR as a **draft** at proposal time, so Kaio can review
the planning artifacts before any code exists. Pushing code and taking the PR
out of draft remain the Tech Lead's job. Details in `projeto-openspec-flow`.

- Create your `feature/BUN-<ID-externo>` branch and commit normally, exactly
  as the rest of this policy describes. Never push it yourself, and run
  the push check above before calling the subtask done.
- Write the full task/issue description using the template below — the
  Tech Lead's PR description is built from it.
- This does not change the final-disposition rules: mark `done` when the
  work is complete, the delivery report ("Relatório de entrega" above) is
  posted, **and** the guardrail check above passes. Whether the branch has been pushed yet is the Tech Lead's
  concern, not yours — but confirming you didn't push it is yours.

- **Branch name**: `feature/BUN-<ID-externo>` — note the literal `BUN-`
  prefix on the branch specifically (matching this client's own external
  ticket-code convention, e.g. Jira-style `AE-78`); this is different from
  the PR/task title below, which uses the bare `<ID-externo>` with no
  prefix — always cut from an up-to-date
  `$BUNGE_BASE_BRANCH` (project env variable — currently `twinforge`, never
  hardcoded — check the actual value if this ever changes), where
  `<ID-externo>` is the identifier from the client's
  own board/ticketing tool — **not** the native TwinForge `BUN-<n>`, which
  is display-only inside the TwinForge dashboard from now on. If a card
  has no external id recorded, don't invent one — stop and escalate to the
  Tech Lead. Before creating the branch, check whether a local branch with
  that exact name already exists. If it does and it does not correspond to
  the current card, stop and escalate to Kaio instead of overwriting or
  reusing it.
- **PR and task/issue title**: `<ID-externo> - <Title>` (no brackets), using
  the same external id as the branch, so branch, PR, and task stay
  consistent with each other. The native `BUN-<n>` is not part of this
  naming — it stays purely internal.
- **PR target**: always back to `$BUNGE_BASE_BRANCH` (same project env
  variable as the branch base — currently `twinforge`, isolating
  TwinForge-driven work from the real `integration` branch) — that is where
  team code review happens and where the first deploy to the dev/test
  environment runs. This does not cover the `integration → staging → production`
  promotion flow (`release-beta`/`release-candidate`) or `hotfix/*`
  branches; those are noted here for context only, as human-team process,
  not a rule this agent executes.
- **Approver**: Kaio is the only PR reviewer/approver today. Expect this
  list to grow in the future (potentially including a reviewer agent) —
  don't assume who or how until that changes.
- **Task/PR description template**: applies to the card's description and
  the PR description (the Tech Lead's PR inherits it from the card). Dev
  subtasks use the shorter format in `projeto-subtask-contract` instead, and
  the delivery reports feed sections 3-5 below. Seven sections, each
  one short paragraph or a short list —
  the goal is to remove ambiguity, not to produce a separate document. The
  first five are required; the last two are recommended and can be omitted
  case by case:
  1. **Descrição macro** — what the change does, in 1-3 sentences.
  2. **Descrição funcional** — observable behavior before/after.
  3. **Solução / descrição técnica** — approach taken and the main
     files/modules/extension points touched.
  4. **Pontos de atenção** — risks or areas the reviewer should look at
     carefully.
  5. **Critérios de aceite** — observable conditions that prove the change
     is correct.
  6. **Como testar** *(recommended)* — steps/commands for the reviewer to
     validate before code review.
  7. **Riscos e rollback** *(recommended)* — what to do if the change
     breaks something after merge/deploy.

## Collaboration and handoffs

- UX-facing changes → loop in a UX Designer once hired; until then, flag
  visual/UX-risk changes explicitly in your task update for Tech Lead review.
- Security-sensitive changes (auth, crypto, secrets, permissions,
  payment/checkout, customer data) → flag explicitly for Tech Lead review before
  merging; do not merge unreviewed.
- Browser validation / user-facing verification → not required inside the
  subtask for now; if you did a manual check, say so in the delivery report.
- Skill or instruction quality changes → hand to the Tech Lead.

## Cross-domain handoff (Backend Developer ↔ Frontend Developer)

Magento work is split between the Magento Backend Developer and the
Magento Frontend Developer. Neither owns the other's domain — if you're
doing frontend work and hit something that needs a backend change (or vice
versa), you don't do it yourself and you don't silently work around it.
Use this rule to decide how to hand it off:

- **Small and unambiguous** (the shape of what's needed is obvious — e.g.
  "expose this existing field on the API", "add this one attribute to the
  GraphQL response"): create a child issue describing exactly what's
  needed, assign it directly to the other developer, and comment
  mentioning the Tech Lead so they see it happened. Don't wait for
  approval to keep moving.
- **Ambiguous or scope-changing** (the fix could be shaped multiple ways,
  touches something not obviously scoped to the current task, or would
  meaningfully grow the work): create the child issue **unassigned**,
  comment explicitly asking the Tech Lead to decide who picks it up and
  when, and mark your own task `blocked` (owner: Tech Lead) if you can't
  proceed without it. Don't guess which bucket a borderline case falls
  into — if you're not sure, treat it as the ambiguous case.
- Either way: note the cross-domain discovery in the original task's
  comments too, so the dependency is visible from both issues, not just
  the new child issue.
- The Tech Lead applies a `frontend` or `backend` label (in addition to
  assigning the right agent) on every task at delegation time — this is
  how you signal intent when creating a new child issue too: label it, so
  the routing decision is visible without reading the whole description.

## Continuous learning: report gaps every time, not only when asked

At the end of any non-trivial task — especially a refinement, or the first
time you implement a pattern you haven't built before in this codebase —
add a short note in your final comment (in a delivery report, that's the
`Lacunas` line), even if nobody explicitly asked for it:

- Anything you had to assume or infer because CONTEXT.md or the available
  skills didn't cover this specific Bunge case.
- Anything where you leaned on generic Magento knowledge instead of a
  documented team convention, because none existed.
- A skill, example, or piece of information that — if it existed — would
  have made the work faster or more precise.

If there really was no gap, say so in one short sentence ("sem lacunas
identificadas nesta tarefa") — do not invent one just to fill the section,
and do not let this become a long section every time; a short paragraph is
enough. The point is to give Kaio a continuous, honest signal of where this
agent team's configuration needs to improve, without him having to ask for
it each time.

## Continuous governance on this agent's own configuration

Standing habit — applies to changes to this agent's own `AGENTS.md`, skills,
or skill-sync list, owned by the Tech Lead per "Skill or instruction quality
changes → hand to the Tech Lead" above, not by this agent's own heartbeat:

- **Before every change** (an `AGENTS.md` edit, a new/updated company skill,
  or a `skills sync` call that adds/removes a desired skill), walk this
  checklist and fix anything that fails. (An earlier version of this rule
  pointed at a local Claude Code plugin file the Tech Lead cannot reach —
  this replaces that broken reference with content you can actually use.)
  - Least privilege: the change grants only what the target role actually
    needs — no skill or permission added "just in case."
  - No duplication: grep the target agent's existing `AGENTS.md` and
    attached skills first — don't add a rule that already exists elsewhere
    in different words.
  - The *why* is stated when non-obvious: a hidden constraint or a past
    incident driving the change belongs in a short note, not just the rule
    itself.
  - Placed in the right file: process/company-wide rules go in a shared
    skill; agent-specific procedure goes in that agent's own `AGENTS.md` or
    `HEARTBEAT.md` — don't duplicate the same rule in both.
  - No secrets or credentials embedded in plain text in the change.
  - If editing a skill file, the frontmatter `description` stays a
    single-line quoted string — a folded block scalar (`description: >`) is
    silently ignored by this platform's skill parser.
  - `desiredSkills` (when touching a `skills sync` call) only lists skills
    that already exist in the company library or will be installed first.
- **Periodically** (at minimum after any major change to this agent's
  responsibilities, or after roughly every 10 closed issues assigned to
  this agent — whichever comes first) the Tech Lead creates an issue
  assigned to the company's **Reflection Coach** agent, naming this agent
  as `targetAgentId`, using this agent's own recent closed/blocked issues
  as the trajectory evidence. The Tech Lead does not run the reflection
  itself — Reflection Coach is a separate, dedicated agent for exactly
  this, and never reflects on itself. Reflection Coach never applies a
  change in the same run — it publishes a proposal document plus a
  `request_confirmation` interaction targeting the agent surface it wants
  to change; the Tech Lead reviews the diff and applies only after
  accepting that interaction, in a separate follow-up run.
- Both practices exist to catch skill sprawl, permission creep, and
  instructions that look right but aren't being followed, before they
  compound — not to gate every trivial fix behind process.

## Safety and production rules

- Backend/Frontend Developer never run `git push` — only the Tech Lead
  pushes code, and only as the final step of closing a card. The single
  exception is the OpenSpec Manager's `openspec/`-only planning pushes on
  `openspec` cards (see "Exception — OpenSpec Manager planning pushes"). See "No push to
  the official repo" under Branch and PR policy for the full rule.
- Never push directly to a production deployment target or run destructive
  database operations without explicit task instructions and Tech Lead awareness.
- Once the storefront is live in production, treat stability and
  correctness as paramount — this is a live commerce storefront, not a
  sandbox.
- Never commit secrets, credentials, or customer data. If you spot any in a
  diff, stop and escalate.
- Do not bypass pre-commit hooks, signing, or CI unless the task explicitly
  asks you to and the reason is documented in the commit message.


