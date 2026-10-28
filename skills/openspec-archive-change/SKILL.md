---
name: openspec-archive-change
description: "OpenSpec Manager only. Arquiva uma change OpenSpec concluída (move para openspec/changes/archive/ e funde as specs em openspec/specs/), depois de conferir os gates. Use quando o Tech Lead te atribuir `[OPENSPEC] Arquivar — <change>`. Headless: nunca pede confirmação; se o gate falhar, bloqueia."
---

# openspec-archive-change (OpenSpec Manager)

Se você **não** é o OpenSpec Manager, pare (ver `projeto-openspec-flow`).

O Tech Lead só cria esta issue depois de ter dado push do código. Você trabalha
sobre `origin/feature/BUN-<EXT>`, que já tem o código e os `[x]` dos devs.

## Passos

**1. Worktree na branch remota.**
```bash
WS=/twinforge/instances/default/workspaces/{{uuid}}
REPO="$WS"; [ -d "$WS/bunge-magento2/.git" ] && REPO="$WS/bunge-magento2"
cd "$REPO" && git fetch origin --prune
WT=$(mktemp -d /tmp/openspec-wt-XXXX)/repo
git worktree add --detach "$WT" "origin/feature/BUN-$EXT"
cd "$WT"
```

**2. Gates (todos precisam passar; se um falhar, `blocked` com o motivo e pare).**
```bash
grep -c '^- \[ \]' "openspec/changes/$CH/tasks.md"            # precisa ser 0
openspec validate "$CH" --strict --json --no-interactive      # valid: true
openspec status --change "$CH" --json                         # 4/4 done
```
E na TwinForge, conformidade com a spec:
1. Todas as subtarefas `[BACK]`/`[FRONT]` da card estão `done`.
2. Liste todos os cenários: `grep -rh '^#### Scenario:' "openspec/changes/$CH/specs/"`.
3. Para cada cenário, ache na seção `Cenários atendidos` do relatório de
   entrega de alguma subtarefa de dev o arquivo:linha que o implementa (e o
   teste escrito, se houver). Abra o arquivo nesse ponto e confira, lendo, que
   o código faz o THEN do cenário. **Não rode testes.**
4. Monte a tabela `cenário → subtarefa → código → teste`. Cenário sem código
   apontado, ou cujo código lido não faz o THEN: `blocked`, comente a tabela
   com os cenários faltando para o Tech Lead reabrir a subtarefa certa, pare.
Tarefas `[ ]` sobrando: liste-as no comentário e bloqueie. Nunca arquive com
tarefa aberta, mesmo que alguém peça.

**3. Arquivar (funde as specs).**
```bash
openspec archive "$CH" -y --json
grep -rl '^TBD - created by archiving' openspec/specs/          # capabilities novas
```
Para cada arquivo listado: troque a linha `TBD - created by archiving ...` da
seção `## Purpose` por 1–2 frases dizendo para que serve a capability (tire do
`proposal.md` arquivado). Depois:
```bash
openspec validate --specs --strict --json --no-interactive     # todas valid: true
git status --short                                             # só openspec/ mudou
```
`--skip-specs` só se a change for de doc ou infra e não tiver pasta `specs/`
(diga isso no comentário).

**4. Commit e push (só `openspec/`).**
```bash
git add openspec/
git diff --cached --name-only | grep -v '^openspec/' && echo "ERRO: arquivo fora de openspec/"   # precisa não imprimir nada
git commit -m "openspec: archive $CH (BUN-$EXT)"
export GH_TOKEN="${GH_TOKEN:-${GITHUB_TOKEN:-}}"
if [ -z "$GH_TOKEN" ]; then
  B="${TWINFORGE_API_URL%/}"; B="${B%/api}"
  GH_TOKEN=$(curl -s -X POST -H "Authorization: Bearer $TWINFORGE_API_KEY" "$B/api/agents/me/secrets/github_token/value" | python3 -c 'import json,sys;print(json.load(sys.stdin).get("value",""))'); export GH_TOKEN
fi
git push origin "HEAD:refs/heads/feature/BUN-$EXT" \
  || git -c "http.extraheader=AUTHORIZATION: basic $(printf 'x-access-token:%s' "$GH_TOKEN" | base64 -w0)" push origin "HEAD:refs/heads/feature/BUN-$EXT"
cd "$REPO" && git worktree remove --force "$WT" && git worktree prune
```
Push recusado por non-fast-forward: alguém enviou commits depois do seu fetch.
Refaça do passo 1 (uma vez). De novo falhou: `blocked`.

**5. Comentário e `done`.**
```
Change `<CH>` arquivada (commit <hash>, enviado)
Movida para: openspec/changes/archive/<AAAA-MM-DD>-<CH>/
Specs atualizadas: <lista de openspec/specs/<cap>/spec.md>
Gates: tasks 0 abertas · validate strict OK · subtarefas de dev done
Módulos sem doc tocados por esta change (candidatos a doc em docs/): <lista de docs/module-index.md ou "nenhum">
Conformidade com a spec:
| Cenário | Subtarefa | Código (arquivo:linha) | Teste escrito |
| ... | ... | ... |
Próximo: Tech Lead tira o PR de draft e fecha a card
```
