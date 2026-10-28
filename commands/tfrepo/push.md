---
name: "TwinForge: Push"
description: "Apply twinforge-repo/ skills and agent config to the live company"
allowed-tools: Bash(twinforgecli:*), Bash(git:*), Read, Glob
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

Apply what's in `twinforge-repo/` to the live TwinForge company — this one **does** write to
production (new skills, renamed skills, agent `desiredSkills` resynced). Go slower than
`/tfrepo:pull`; confirm before anything destructive.

## Prerequisite

**Always run `/tfrepo:status` first in this same conversation** and show the user the diff
before applying anything. Pushing blind — especially a rename — risks exactly the kind of
disruption a careless rename caused before (ask the user if they want the full story; the short
version: a live skill got renamed without resyncing the agents that used it, and they briefly
lost access to it). Don't skip this step even if the user says "just push it."

```bash
test -d twinforge-repo/.git || echo "NO LOCAL CLONE"
which twinforgecli || echo "NOT INSTALLED"
```

## Steps

1. **New skill** (local dir, no remote match): `twinforgecli skill create -C <company-id>
   --payload-json '{"name": "<slug>", "markdown": "<SKILL.md content>"}'`. Only the `SKILL.md`
   content matters for the create call itself (it is stored verbatim, frontmatter included).
   Extra files (`references/`, `scripts/`) go one by one with `skill file:update <id>
   --payload-json '{"path": "<rel>", "content": "<text>"}'`, which creates missing folders. Before
   uploading them:
   - run `python3 twinforge-repo/twinforge/waf-probe.py <company-id> twinforge-repo/skills/<slug>
     --bisect` and fix every file it reports (the nginx WAF answers 403 to some content);
   - tell the user the cost: every `file:update` stores a new skill version with the content of
     **all** the skill's files, so N files cost N versions (a 100-file skill is ~50 MB of
     versions). Leave `tests/` out unless the user wants it.
2. **Renamed skill** (slug changed — the content is the same change under a new name): this is
   the one that needs the full protocol, in order, confirming each step before the next:
   a. Create the new slug (step 1's payload).
   b. For every agent whose `desiredSkills` references the old slug: pull its **current live**
      list fresh (`agent get`), substitute old → new, `agent skills:sync <id> --desired-skills
      <full CSV list>` — always the complete list, `skills:sync` replaces it wholly, never a
      partial add.
   c. Check `agent skills <id> --json` for each resynced agent — any entry with `state: missing`
      means it hasn't linked yet. For `pi_local` agents this is normal until a real heartbeat
      runs; `twinforgecli agent wake <id>` and recheck. Don't move to the next step until every
      resynced agent shows zero `missing` among its desired skills.
   d. Only once (c) is clean for every affected agent: confirm the old slug's
      `attachedAgentCount` is now 0 (`skill list`), then `skill delete <old-id>`.
3. **Agent instruction change** (`.md` content differs from live): `twinforgecli agent
   instructions-file:put <id> --path <file> --content-file twinforge-repo/agents/<role>/<file>`.
4. After applying, re-run the comparison from `/tfrepo:status` and confirm it now reports
   clean (no remaining diffs) for everything just pushed — don't just trust the apply calls
   returned success; verify the live state actually matches.
