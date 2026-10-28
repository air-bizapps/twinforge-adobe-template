---
name: "TwinForge: Agent"
description: "Check or wake a single agent (status, skills, config) without a full pull/push"
allowed-tools: Bash(twinforgecli:*), Read
category: "TwinForge"
tags: ["twinforge", "agent"]
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

The argument after `/tfrepo:agent` is a `role_key` (e.g. `tech_lead`, `backend_dev` — the
same keys used in `twinforge-repo/twinforge/roles.yaml` and `twinforge-repo/agents/*/`) and,
optionally, a second word naming what to do (`status` is the default if nothing else is given).

## Prerequisite

```bash
test -f twinforge-repo/twinforge/roles.yaml || echo "NO LOCAL CLONE"
```

Resolve `role_key` → real agent id by reading `roles.yaml`. If the role isn't in there, say so —
don't guess an id.

## What each mode does

- **status** (default): `twinforgecli agent get <id> --json` — name, role, reportsTo, budget,
  last run status. Short summary, not the raw JSON dump.
- **skills**: `twinforgecli agent skills <id> --json` — report any `desired: true` entry whose
  `state` is `missing`, since that's the actionable signal (everything else is normal).
- **wake**: `twinforgecli agent wake <id> --json` — only do this if the user actually asked to
  wake it; don't wake an agent as a side effect of just checking its status.
- **instructions**: `twinforgecli agent instructions-bundle <id> --json` then `instructions-file:get`
  for each file — show what it currently has live, for comparison against
  `twinforge-repo/agents/<role_key>/`.

If the user's second word doesn't match one of these, show this list rather than guessing which
one they meant.
