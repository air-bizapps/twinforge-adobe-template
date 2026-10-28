---
name: openspec-update-change
description: "OpenSpec Manager only. Revisa os artefatos de uma change OpenSpec ainda não implementada, a partir de um pedido de mudança de um reviewer (Backend/Frontend) ou do Kaio, ou de comentários no PR draft. Nunca mexe em código. Use quando a issue `[OPENSPEC] Proposta` voltar para você com status in_progress, ou quando o Tech Lead pedir revisão de uma change."
---

# openspec-update-change (OpenSpec Manager)

Se você **não** é o OpenSpec Manager, pare (ver `bunge-openspec-flow`).

Só artefatos (`openspec/changes/<CH>/`). Nunca código. O pedido de mudança que
te acordou **é** a autorização: aplique exatamente o que foi pedido, nada além.

## Passos

**1. Juntar o pedido.**
- `CH` = nome da change (do seu comentário "Proposta OpenSpec pronta: `<CH>`").
  `EXT` = ID externo da card pai (a branch é `feature/BUN-$EXT`).
- Leia o último comentário de request-changes da issue (autor, itens pedidos).
- Leia os comentários do PR draft:
  ```bash
  gh pr view "feature/BUN-$EXT" --json number,url,reviews,comments
  gh api "repos/<org>/<repo-do-projeto>/pulls/<n>/comments"
  ```
- Monte a lista numerada de mudanças: `M1 <arquivo> — <o que muda> — pedido por <quem>`.
  Pedido ambíguo (duas leituras possíveis que mudam escopo): responda na issue
  com a pergunta exata, mova para `in_review` e pare. Não adivinhe.
- Pedido que exige código ou muda o objetivo da card: comente que isso é
  decisão do Tech Lead, reatribua ao Tech Lead (`todo`) e pare.

**2. Worktree na branch da card.**
```bash
WS=/twinforge/instances/default/workspaces/{{uuid}}
REPO="$WS"; [ -d "$WS/bunge-magento2/.git" ] && REPO="$WS/bunge-magento2"
cd "$REPO" && git fetch origin --prune
WT=$(mktemp -d /tmp/openspec-wt-XXXX)/repo
git worktree add --detach "$WT" "origin/feature/BUN-$EXT"
cd "$WT"
openspec status --change "$CH" --json
```

**3. Aplicar cada mudança.** Para cada `M#`: reabra o artefato do disco, e se
for mudar a estrutura dele rode `openspec instructions <artifact-id> --change "$CH" --json`
para seguir o template. Mantenha coerência: mudou requisito → ajuste cenário,
`design.md` e `tasks.md` que dependem dele. Mantenha as regras do Bunge do
`openspec-propose` (grupos `[BACK]`/`[FRONT]`, cenários com THEN observável, teste
unitário escrito sem executar, relatório de entrega no fim).

**3a. Generalize — não corrija só o caso citado.** Se o pedido de mudança
aponta uma instância de um **padrão** (ex.: "falta a dependência de sequência
`<module name="X"/>` no `module.xml` de `Y` pro `setup:di:compile` achar a
classe injetada" — não é específico de um módulo, é uma regra: toda classe
nova que injeta algo de outro módulo Webjump novo precisa dessa dependência),
pare antes de aplicar só no arquivo citado e **procure todas as outras
ocorrências do mesmo padrão** em `tasks.md`/`design.md` desta change — outros
módulos novos, outras classes com o mesmo tipo de dependência. Corrija todas
na mesma rodada. Isso existe porque no ciclo real do BUN-889 o Backend pediu
exatamente essa correção duas vezes seguidas — uma para `CustomCustomer`, uma
para `CustomCompany` — porque só o caso citado foi corrigido na primeira
rodada, e o segundo (mesma causa, módulo diferente) só apareceu na rodada
seguinte. Uma rodada de update generalizada custa o mesmo que uma específica;
duas rodadas custam o dobro do tempo do reviewer.

Pedido que é claramente pontual (nome errado, referência a um único arquivo
específico sem padrão generalizável) não precisa dessa varredura — use
julgamento: "isso é uma regra que vale pra qualquer X" vs "isso é um erro
neste X específico".

**3b. Verifique o próprio fix contra o código real antes de aplicar.** Não
escreva a correção de memória — se o pedido cita uma classe, interface ou
padrão ("use a classe `Y`", "siga o padrão de `Z`"), abra `Y`/`Z` de verdade
(`git show "$B:<caminho>"`) e confirme namespace, assinatura e uso exatos
antes de escrever no artefato. Isso existe porque no BUN-888 uma correção de
review (trocar a classe base do Grid Collection) foi aplicada citando o nome
errado — a correção da correção só foi pega na rodada seguinte. Tratar cada
fix como uma alegação a verificar, não uma transcrição do pedido, evita isso.

**4. Gate.**
```bash
openspec validate "$CH" --strict --json --no-interactive
openspec status --change "$CH" --json
```
Precisa `valid: true` e 4/4 `done`. Três falhas seguidas: `blocked` com o JSON.

**5. Commit e push (só `openspec/`).**
```bash
git add openspec/
git diff --cached --name-only | grep -v '^openspec/' && echo "ERRO: arquivo fora de openspec/"   # precisa não imprimir nada
git commit -m "openspec: update $CH (BUN-$EXT) — <M1..Mn resumido>"
export GH_TOKEN="${GH_TOKEN:-${GITHUB_TOKEN:-}}"
if [ -z "$GH_TOKEN" ]; then
  B="${TWINFORGE_API_URL%/}"; B="${B%/api}"
  GH_TOKEN=$(curl -s -X POST -H "Authorization: Bearer $TWINFORGE_API_KEY" "$B/api/agents/me/secrets/github_token/value" | python3 -c 'import json,sys;print(json.load(sys.stdin).get("value",""))'); export GH_TOKEN
fi
git push origin "HEAD:refs/heads/feature/BUN-$EXT" \
  || git -c "http.extraheader=AUTHORIZATION: basic $(printf 'x-access-token:%s' "$GH_TOKEN" | base64 -w0)" push origin "HEAD:refs/heads/feature/BUN-$EXT"
cd "$REPO" && git worktree remove --force "$WT" && git worktree prune
```

**6. Subtarefas.** Se `tasks.md` mudou: atualize a descrição das subtarefas
`[BACK]`/`[FRONT]` afetadas (linha `Tarefas:` e lista). Grupo novo → nova
subtarefa sem assignee. Grupo removido → cancele a subtarefa com comentário.
Cenário novo/alterado → atualize a linha `Cenários a provar:` da subtarefa que
vai prová-lo.

Ao reescrever a descrição, **preserve** as 4 linhas de cabeçalho
(`Change:`/`Tarefas:`/`Branch:`/`Artefatos:`) e as seções de
`bunge-subtask-contract` (Objetivo, Entradas, Skills, Fora de escopo). Se o
`design.md` mudou de forma que afeta o grupo, atualize as Entradas
correspondentes. Grupo novo → corpo completo, como em `openspec-propose` passo 11.

**7. De volta para review.** Comente e mova a issue para `in_review` (o stage
que pediu a mudança volta a decidir).

```
Update aplicado em `<CH>` (commit <hash>)
M1 <arquivo>: <o que mudou>
M2 ...
Generalização (3a): <outras ocorrências do mesmo padrão encontradas e corrigidas, ou "nenhuma outra ocorrência — pedido pontual">
Verificação (3b): <classes/arquivos citados no pedido confirmados no código antes de escrever, ou "não aplicável">
validate --strict OK · subtarefas ajustadas: <lista ou "nenhuma">
Não aplicado (fora do meu papel): <lista ou "nada">
```

## Nunca
- Editar arquivo fora de `openspec/`.
- Aplicar mudança que ninguém pediu "aproveitando".
- Mudar `openspec/config.yaml` sem pedido explícito do Tech Lead.

