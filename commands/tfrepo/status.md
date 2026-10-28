---
name: "TwinForge: Status"
description: "Diff twinforge-repo/ (skills + agents) against the live company — read-only, changes nothing"
allowed-tools: Bash(twinforgecli:*), Read, Glob
category: "TwinForge"
tags: ["twinforge", "sync", "diff"]
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

Report what's different between `twinforge-repo/` (the local clone of `twinforge-adobe-template`) and the
live TwinForge company — **never apply anything**, this command only reports. `/tfrepo:pull`
and `/tfrepo:push` should both be run right after this, never blind.

## Prerequisite

```bash
test -d twinforge-repo/.git || echo "NO LOCAL CLONE"
which twinforgecli || echo "NOT INSTALLED"
```

If `twinforge-repo/` doesn't exist, tell the user to clone it first:
`git clone git@github.com:air-bizapps/broto-twinforge.git twinforge-repo` at the repo root (it's
gitignored on purpose — a clone of a separate repo, not a submodule). Stop here without it.

## Steps

1. Get the company id from `twinforge-repo/twinforge/roles.yaml` (top-level `scope.project_id`
   isn't the company id — read `roles.yaml`'s own `roles.*.id` entries' company prefix, or ask
   the user once and remember it for the rest of this command).
2. List what's live: `twinforgecli skill list -C <company-id> --json`, filtered the same way the
   repo was built — `sourceType: local_path` and `attachedAgentCount >= 1`.
3. List what's local: every directory under `twinforge-repo/skills/`.
4. Report three buckets, by slug:
   - **Só no repo** (local dir with no matching remote slug) — candidate for `/tfrepo:push`
     to create.
   - **Só remoto** (remote slug with no matching local dir) — candidate for `/tfrepo:pull` to
     bring down, unless it's intentionally excluded (github/catalog-sourced, or
     attachedAgentCount 0 — see `twinforge-repo/skills/README.md` for the exact scoping rule).
   - **Em ambos, conteúdo diferente** — fetch the remote `SKILL.md` (`twinforgecli skill file
     <id> --path SKILL.md`) and diff it against the local file. This is the bucket that matters
     most: it means someone edited one side without the other, and `pull`/`push` need to know
     which side should win before touching it.
5. Do the same comparison for `agents/*/agent.yaml` — fetch `twinforgecli agent get <id>` for
   each known agent and diff `desiredSkills`/model/budget/etc. against the local `agent.yaml`
   (remember `{{company_id}}` in the local file is a placeholder, not a literal diff target —
   substitute the real id before comparing).
   Also compare `env`: each `env.NAME.secret_ref` in the local `agent.yaml` must match the live
   `adapterConfig.env.NAME` (`type: secret_ref`, `secretId` resolved from the secret's key via
   `twinforgecli secrets list -C <company-id> --json`), and `twinforgecli secrets usage <secretId>`
   must show an `agent` binding on that agent with `configPath: env.NAME`. Never print or compare
   secret *values* — only keys and ids.
6. Print a short summary table (counts per bucket), then the full list of names in each bucket —
   not a wall of raw diffs unless the user asks to see one specifically.
