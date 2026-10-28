---
name: "TwinForge: Init"
description: "Guided first-time TwinForge setup for a Magento project that doesn't have it yet"
allowed-tools: Bash(twinforgecli:*), Bash(git:*), AskUserQuestion, Read, Write
category: "TwinForge"
tags: ["twinforge", "onboarding", "setup"]
---

> **Instância (obrigatório antes de qualquer `twinforgecli` deste comando).** Esta máquina pode ter
> mais de uma instância conectada, e o perfil ativo global pode ser de **outro projeto**. Siga a
> seção "Instância da sessão" da skill `twinforge-instancias` (`/twinforge:instances`): fixe o par
> (arquivo de contexto, perfil) e passe `--context "<arquivo>" --profile "<perfil>"` em **todo**
> `twinforgecli` deste fluxo; nunca dependa do perfil ativo global. Anuncie
> `Usando <perfil> (<URL>, company <id>)`. Antes de qualquer escrita na instância, confirme com o
> usuário que é a instância certa. Se `scope.project_id` de `twinforge/roles.yaml` não existir em
> `twinforgecli project list` da instância fixada, **pare**: este repositório ainda tem dados de
> outro projeto e nada deve ser aplicado.

> Se `twinforge/roles.yaml` existir no diretório atual, você já está **dentro** deste repo:
> leia todo caminho `twinforge-repo/...` como `./...` e ignore o aviso de "NO LOCAL CLONE".
>
> Dado faltando (company id, agent id, clone local, login…)? Siga a regra de
> `twinforge-repo/context/twinforge-context.md`: pare, explique qual dado falta e como o
> usuário o obtém, e espere — não adivinhe.

A guided conversation for someone who has **never used TwinForge on this project before** — not
a script to run unattended. Use `AskUserQuestion` at each decision point; don't assume answers.

## Step 0 — confirm this is actually needed

```bash
which twinforgecli || echo "NOT INSTALLED"
```

If missing: explain what `twinforgecli` is (the TwinForge CLI — creates and manages AI agents
that work on this codebase) and point at the install docs for this account; don't guess a
package name. Stop here without it.

```bash
twinforgecli company list --json
```

If this already lists a company the user confirms is for this project, **stop and say so** —
don't create a duplicate company. Point them at `/tfrepo:pull` instead, to bring down what's
already there.

## Step 1 — explain what TwinForge is, briefly

One short paragraph: it's a platform that runs AI agents (a Tech Lead, developers, etc.) against
this repo, each with its own role, skills, and git permissions — not a single assistant, a small
team. Point at the companion doc/README if `twinforge-repo/` already exists from another
project's template; otherwise this conversation **is** the introduction.

## Step 2 — create the company

Ask (via `AskUserQuestion`) for a company name. Then:

```bash
twinforgecli company create --payload-json '{"name": "<name>"}' --json
```

Save the returned id — everything after this needs it.

## Step 3 — the GitHub PAT

Ask whether they already have a PAT with push access to this repo, or need to create one.
If creating one: point them to GitHub's own token creation page (Settings → Developer settings →
Personal access tokens) — don't generate or guess a token value. Once they have it:

```bash
twinforgecli secrets create -C <company-id> --name "GitHub Token - <repo>" \
  --key gh_token --provider local_encrypted --value-env GH_TOKEN \
  --description "PAT with push/PR access to <repo>"
```

Recommend they `export GH_TOKEN=<their token>` in their own shell right before this command, in a
session that won't be logged — never have them paste the raw token into the conversation. This
mirrors a real issue this project hit: a token was reused across two repos via a secret that was
never actually re-authorized for the second one — name the repo explicitly in the secret
description so that mistake doesn't repeat.

## Step 4 — the project and workspace

Ask for: the project name, and the repo URL this project's agents will work in.

```bash
twinforgecli project create -C <company-id> --name "<name>" \
  --env-json '{"GH_TOKEN":{"type":"secret_ref","version":"latest","secretId":"<secret-id>"}}' --json
twinforgecli project-workspace create <project-id> --payload-json '{"name":"<repo-name>","sourceType":"git_repo","repoUrl":"<repo-url>"}' --json
```

## Step 5 — the first agent

Don't create a full org chart unattended — ask what role they want first (a Tech Lead is the
usual starting point, since other agents typically report to it). Walk through the same fields
`twinforge-repo/agents/*/agent.yaml` documents (name, role, icon, adapter type, model, budget —
**never 0**, see the known bug about `budgetMonthlyCents: 0` being misread as "exhausted" by the
agent itself) before calling `agent create`.

## Step 6 — point at this repo's skills

If `twinforge-repo/` already exists (cloned from a template repo like `twinforge-adobe-template`), offer
to run `/tfrepo:push` to get its skills and the new agent's config live. If it doesn't exist
yet, that's a separate decision — ask whether they want to start one, and if so just create the
repo and the directory structure; don't improvise `/tfrepo:push`'s logic here, point at it
once there's something to push.

## Throughout

Before moving to the next step, check the previous one actually succeeded (read the command's
own JSON output, don't assume success from exit code alone) — this whole flow is linear and
later steps need real ids from earlier ones.
