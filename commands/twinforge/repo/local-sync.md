---
name: "TwinForge: Local instance sync"
description: "Sync the local 127.0.0.1 TwinForge instance with the cloud — via twinforgecli sync only, never the twinforge binary"
allowed-tools: Bash(twinforgecli sync:*)
category: "TwinForge"
tags: ["twinforge", "local", "sync"]
---

> Se `twinforge/roles.yaml` existir no diretório atual, você já está **dentro** deste repo:
> leia todo caminho `twinforge-repo/...` como `./...` e ignore o aviso de "NO LOCAL CLONE".
>
> Dado faltando (company id, agent id, clone local, login…)? Siga a regra de
> `twinforge-repo/context/twinforge-context.md`: pare, explique qual dado falta e como o
> usuário o obtém, e espere — não adivinhe.

This is about the **local TwinForge server** (the `twinforge` binary, which runs a full stack —
embedded Postgres, API, UI — at `http://127.0.0.1:3100` on this machine) syncing companies down
from the cloud, for working offline. **This is a different system from `/twinforge:repo:pull`/`push`**,
which sync repo files against the cloud company directly — this command never touches
`twinforge-repo/`.

**Hard rule, no exceptions: only ever run `twinforgecli sync ...` subcommands. Never run the bare
`twinforge` command (with no subcommand) from here — it boots the local server as a side effect,
which is expensive to start by accident and easy to leave running without the user noticing.**
If the local server isn't running and the user's request needs it running, tell them to start it
themselves, in their own terminal, outside Claude Code: `twinforge` (bare). Don't run it for them.

## Prerequisite

```bash
which twinforgecli || echo "NOT INSTALLED"
curl -s http://127.0.0.1:3100/api/health || echo "LOCAL SERVER NOT RUNNING"
```

If the local server isn't running and the user's request is read-only (`scope`, `jobs`,
`sources`), most of those still work against the enrolled instance without it — try first, and
only tell the user to start the server if the command itself says it needs it running.

## Steps, by what the user asked for

- **"What would come down?"** → `twinforgecli sync scope --json` — read-only, never applies
  anything. Always offer this before an actual pull.
- **"Sync it"** → confirm with the user which companies (default is everything in scope — ask if
  that's really what they want, or just one: `--company <id>`), then
  `twinforgecli sync pull --detach --json` (always `--detach` — never run this in the foreground,
  it can take a while and would otherwise tie up the whole conversation). Report the job id and
  that `twinforgecli sync jobs` is how to check on it later.
- **"Is it done?" / "did it work?"** → `twinforgecli sync jobs --json`, find the job by id if one
  was given, report its end state.
- **"What's already here, and from where?"** → `twinforgecli sync sources --json`.

Never run `sync pull` without the user confirming first, even if their original request sounded
like a clear "yes, sync everything" — the scope can be wider than they expect (every company the
account can see, by default), and this has caused a real surprise before.
