---
name: openspec-sync-specs
description: "OpenSpec Manager only, e só quando o Tech Lead pedir explicitamente. Funde as specs delta de uma change ativa em openspec/specs/ sem arquivar a change. No fluxo normal NÃO use: o `openspec archive` já faz o sync."
---

# openspec-sync-specs (OpenSpec Manager, uso raro)

No fluxo normal do Bunge o sync acontece dentro do `openspec archive` (ver
`openspec-archive-change`). Use esta skill **só** se o Tech Lead pedir, por
escrito, para publicar as specs antes de a change terminar (ex.: outra card
depende da spec). A CLI 1.13.1 **não tem** comando `spec sync`: o merge é feito
à mão, seguindo as instruções da própria CLI.

## Passos

1. Worktree na branch da card (igual ao passo 1 de `openspec-archive-change`).
2. Leia as regras de merge:
   ```bash
   openspec instructions specs --change "$CH" --json
   openspec status --change "$CH" --json
   ```
3. Para cada `openspec/changes/$CH/specs/<cap>/spec.md` (delta), aplique em
   `openspec/specs/<cap>/spec.md`:
   - `## ADDED Requirements` → acrescente os requisitos (crie o arquivo se a capability for nova, com `# <cap> Specification`, `## Purpose` com 1–2 frases reais — nunca `TBD`, o `--strict` reprova — e `## Requirements`).
   - `## MODIFIED Requirements` → substitua o requisito inteiro de mesmo nome pelo novo texto completo.
   - `## REMOVED Requirements` → apague o requisito de mesmo nome.
   - `## RENAMED Requirements` → troque o título `FROM:` pelo `TO:`.
4. Gate:
   ```bash
   openspec validate --specs --strict --json --no-interactive
   ```
5. Commit `openspec: sync specs of $CH (BUN-$EXT)` e push só de `openspec/`
   (mesmo bloco de push de `openspec-archive-change`, passo 4).
6. Comente na issue quais specs mudaram. A change continua ativa; o
   `openspec archive` depois vai encontrar as specs já aplicadas.
