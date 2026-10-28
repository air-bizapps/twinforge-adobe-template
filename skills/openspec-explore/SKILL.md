---
name: openspec-explore
description: "Leitura das specs e changes OpenSpec do Bunge (Tech Lead e OpenSpec Manager). Use para saber se uma capability já tem spec, o estado de uma change, ou para embasar uma triagem. Só leitura: nunca cria nem altera nada."
---

# openspec-explore (só leitura)

Nada aqui escreve arquivo, cria change ou troca de branch.

```bash
WS=/twinforge/instances/default/workspaces/{{uuid}}
REPO="$WS"; [ -d "$WS/bunge-magento2/.git" ] && REPO="$WS/bunge-magento2"
cd "$REPO" && git fetch origin --prune
```

Specs definitivas e changes na base (`twinforge`), sem checkout:
```bash
git ls-tree -r --name-only origin/twinforge -- openspec/specs openspec/changes
git show origin/twinforge:openspec/specs/<cap>/spec.md
```

Numa branch de card:
```bash
git ls-tree -r --name-only origin/feature/BUN-<EXT> -- openspec/changes
git show origin/feature/BUN-<EXT>:openspec/changes/<CH>/tasks.md
```

Se o checkout atual já estiver na branch certa (confira com `git branch --show-current`),
também pode usar a CLI, que é só leitura com estes comandos:
```bash
openspec list --json              # changes ativas
openspec list --specs --json      # capabilities
openspec show <nome> --json       # uma change ou spec
openspec status --change <CH> --json
openspec validate <CH> --strict --json --no-interactive
```

Não existem: `openspec change new`, `openspec spec sync`, `openspec list --archived`.
Changes arquivadas ficam em `openspec/changes/archive/` (leia com `git ls-tree`).
