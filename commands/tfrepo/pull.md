---
name: "TwinForge: Pull"
description: "Sync skills and agent definitions from the live company down into twinforge-repo/"
allowed-tools: Bash(twinforgecli:*), Bash(git:*), Read, Write, Glob
category: "TwinForge"
tags: ["twinforge", "sync", "skills", "agents"]
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

Bring the live company's skills and agent instructions down into `twinforge-repo/` — **the cloud
company always wins** on content; this never edits anything live, only files in the local clone.

## Prerequisite

```bash
test -d twinforge-repo/.git || echo "NO LOCAL CLONE — run: git clone git@github.com:air-bizapps/broto-twinforge.git twinforge-repo"
which twinforgecli || echo "NOT INSTALLED"
cd twinforge-repo && git status --short
```

Stop and tell the user if: the clone is missing (give the clone command above), `twinforgecli`
isn't installed, or `twinforge-repo/` has uncommitted local changes (pulling over uncommitted
work risks losing it — have them commit or stash first, same as any git pull).

**Run `/tfrepo:status` first** if it hasn't been run this session — pulling blind over a repo
with local edits silently overwrites them with the remote version.

## Steps

1. `cd twinforge-repo && git pull` — the clone itself needs to be current before pulling skill
   content into it, or you're comparing against a stale base.
2. Get the company id (same as `/tfrepo:status` step 1).
3. `twinforgecli skill list -C <company-id> --json`, filter to `sourceType: local_path` and
   `attachedAgentCount >= 1` — this is the exact scoping rule the repo already follows (see
   `twinforge-repo/skills/README.md`). Don't widen or narrow it without telling the user why.
4. For each skill in scope: `twinforgecli skill file <id> --path <path> --json` for every path in
   its `fileInventory` (most are a single `SKILL.md`; a few have `references/`/`scripts/` too —
   mirror the inventory exactly, never invent structure). Write to
   `twinforge-repo/skills/<slug>/<path>`.
5. For each agent that references at least one in-scope skill: `twinforgecli agent get <id>` +
   `twinforgecli agent instructions-bundle <id> --json` to list its instruction files, then
   `twinforgecli agent instructions-file:get <id> --path <file>` for each. Update
   `twinforge-repo/agents/<role_key>/agent.yaml` (company id back to the `{{company_id}}`
   placeholder, `reports_to` back to a role key — see `agents/README.md` for the exact
   portability convention) and the `.md` files.
6. Show a summary (skills added/updated, agents added/updated) and let the user review the diff
   (`git -C twinforge-repo diff --stat`) before committing — **never auto-commit**; that's the
   user's call, same as any other pull.
