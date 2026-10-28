---
name: openspec-propose
description: "OpenSpec Manager only. Refina a card, cria a change OpenSpec (proposal, specs, design, tasks), valida, abre o PR draft, cria as subtarefas [BACK]/[FRONT] sem assignee e configura os stages de review dos devs — e, quando twinforge/workflow.yaml exigir aprovação para a complexidade da tarefa, pergunta ao Kaio via ask_user_questions antes de liberar o desenvolvimento (issue em in_review, nunca blocked). Use quando receber uma issue `[OPENSPEC] Proposta — ...`. Headless: nunca pergunta e espera sem registrar uma interaction; usa interactions/stages."
---

# openspec-propose (OpenSpec Manager)

Se você **não** é o OpenSpec Manager (agent `1d0bf9cb-…`), pare: siga "Se você
recebeu algo que não é seu" em `bunge-openspec-flow`.

Esta skill só produz **planejamento**. Nunca edite código de produto. Termine
sempre com a issue em `in_review` ou `blocked`, nunca emende para implementação.

## Entrada

A issue `[OPENSPEC] Proposta — <título>`, filha da card. Leia a descrição da
card pai: ela já foi curada pelo Tech Lead (`bunge-card-curation`), com a
origem de cada item (`[card]`, `[comentário]`, `[inferido]`, `[pergunta]`).
Item `[inferido]` é decisão do Tech Lead, que você pode contestar com o código;
`[pergunta]` ainda aberta não é requisito. Leia também os comentários e o
documento `plan`, se houver. O documento `original` (texto bruto da card) só
quando um item da descrição parecer ambíguo ou incompleto.

## Passos

**1. Pré-condições (pare se falhar).**
- A card tem o ID externo do cliente. Sem ele: `blocked` + comentário pedindo
  o ID ao Tech Lead. Pare.
- Rode a skill `bunge-link-verification` em todos os links da card. Link
  inacessível: siga o que a skill manda (bloquear). Pare.

**2. Regras de negócio (antes de decidir qualquer coisa).** A card pai pode já
trazer uma seção `## Achados da triagem (Tech Lead)` (skill
`bunge-vague-demand-triage`, rodada antes de você receber a issue) com
critério de aceite, regras de negócio já levantadas e pontos que já foram
esclarecidos com o cliente. Se ela existir, use como ponto de partida — não
refaça a busca do zero. Ainda assim **confirme contra o código** antes de
escrever `design.md` (código é sempre a fonte de verdade; se algo mudou desde
a triagem, ou se você achar algo que contradiz o que está lá, isso vale mais
que o achado do Tech Lead — registre a divergência). Se a seção não existir
(card sem a triagem, ou fora do fluxo OpenSpec padrão), rode `bunge-domain-lookup`
você mesmo, do zero, para cada regra que a card toca. De qualquer forma,
separe o resultado em três listas, que vão para o `design.md` (passo 7):
- **Documentada e confirmada no código**: doc em `docs/` + arquivo:linha no código.
- **Só no código** (módulo sem doc, ver `docs/module-index.md`): arquivo:linha.
- **Não encontrada**: nem em `docs/`, nem em `openspec/specs/`, nem no código.
Regra "não encontrada" que muda escopo ou critério de aceite vira pergunta no passo 3,
nunca suposição.

**3. Refinamento.** Siga `requirements-refinement-fundamentals` ("decida quando
consegue defender, pergunte quando não consegue"). Figma → `bunge-figma-layout-decomposition`.
Demanda grande ou ambígua → use `srs` como apoio para organizar requisitos.
Se falta algo que só o cliente/Kaio sabe e isso muda escopo, comportamento ou
critério de aceite: crie uma interaction `ask_user_questions` na **card pai**,
comente o que falta, mova esta issue para `blocked` e pare. Detalhes menores:
assuma, e registre a suposição em `proposal.md`, na seção de impacto.

**4. Nome da change.** kebab-case, curto, sem ID: ex. `add-order-tracking-sap`.
Guarde como `CH`. Guarde o ID externo como `EXT`. Branch = `feature/BUN-$EXT`.

**5. Worktree temporário (nunca troque a branch do checkout compartilhado).**
```bash
WS=/twinforge/instances/default/workspaces/{{uuid}}
REPO="$WS"; [ -d "$WS/bunge-magento2/.git" ] && REPO="$WS/bunge-magento2"
cd "$REPO" && git fetch origin --prune
git ls-remote --heads origin "feature/BUN-$EXT"      # anote se já existe
WT=$(mktemp -d /tmp/openspec-wt-XXXX)/repo
# branch ainda não existe no origin:
git worktree add --detach "$WT" origin/twinforge
# branch já existe no origin (retomada): use origin/feature/BUN-$EXT no lugar de origin/twinforge
cd "$WT"
[ -d "$WS/twinforge-repo/.git" ] || git clone git@github.com:air-bizapps/twinforge-adobe-template.git "$WS/twinforge-repo"
(cd "$WS/twinforge-repo" && git pull --quiet)
ln -sfn "$WS/twinforge-repo/docs" "$WT/docs"
```
Se a branch já existe e **não** é desta card (o PR dela fala de outra coisa):
`blocked`, comente para o Kaio, pare.

`docs/` não é mais um diretório deste repositório — é symlink pro
`twinforge-repo` (clone de `twinforge-adobe-template`), criado acima. Se alguma
etapa de refinamento precisar consultar regra de negócio, é lá que está.

**6. Contexto do projeto.**
```bash
openspec --version            # precisa ser 1.13.x; se não for, blocked + comente
openspec context --json
openspec list --specs --json  # capabilities já existentes: reutilize o caminho delas
```
Leia `openspec/config.yaml` (campo `context` e `rules`) e as specs relacionadas
em `openspec/specs/`. Leia o código real envolvido (módulos `app/code/Webjump/*`,
tema, `di.xml`, layouts) antes de escrever qualquer artefato.

**7. Criar a change.**
```bash
openspec new change "$CH"
openspec status --change "$CH" --json
```
Se a change já existir, você está retomando: não crie outra, continue dela.

**8. Artefatos, em ordem de dependência.** Para cada artefato `ready` no
`status --json` (proposal → specs → design → tasks):
```bash
openspec instructions <artifact-id> --change "$CH" --json
```
Use `template` como estrutura, `instruction` como guia, e `context`/`rules`
como restrição (nunca copie esses blocos para o arquivo). Escreva em
`resolvedOutputPath`. Releia do disco os artefatos anteriores antes do próximo.
Repita `openspec status --change "$CH" --json` até os 4 estarem `done`.

Regras obrigatórias do Bunge para os artefatos:
- `proposal.md`: cite o ID externo e o `BUN-<n>` da card; diga se toca código
  Webjump ou vendor (vendor só via plugin/preference/observer).
- Specs: requisitos com SHALL/MUST; cada requisito com ≥1 cenário
  `#### Scenario:` em WHEN/THEN **com um THEN observável e implementável** (no
  apply o dev aponta o arquivo:linha que atende cada cenário, e o archive confere).
- `design.md`: cite o campo/endpoint exato da documentação real de qualquer
  sistema externo (nunca invente nome de campo). Tenha uma seção
  `## Regras de negócio consultadas` com as três listas do passo 2 (cada item
  com doc e/ou arquivo:linha).
- `tasks.md`: agrupe em seções `## N. [BACK] <resumo>` ou `## N. [FRONT] <resumo>`;
  cada grupo cabe em uma subtarefa de dev; cada tarefa `- [ ] N.M <ação> — entrega: <arquivo(s) ou resultado esperado>`;
  toda classe nova (Command/Plugin/ViewModel) tem tarefa de **escrever** o teste
  unitário (sem rodar, sem meta de cobertura); nenhuma tarefa manda rodar
  PHPUnit, `grumphp`, cobertura ou validar render; o último grupo termina com a
  tarefa "postar o relatório de entrega" (ver `bunge-engineering-practices`).

**9. Gate de máquina (pare se falhar).**
```bash
openspec validate "$CH" --strict --json --no-interactive
openspec status --change "$CH" --json
```
`valid` precisa ser `true` e os 4 artefatos `done`. Se falhar, corrija o
artefato apontado e repita. Depois de 3 tentativas sem passar: `blocked` com o
JSON de erro no comentário. Pare.

**9b. Autocheck técnico — aplique você mesmo o checklist do reviewer antes de
abrir para review.** `openspec-review-proposal` usa uma tabela C1-C11 pra
decidir "aprovado" ou "mudanças pedidas". Rode essa mesma tabela contra o seu
próprio `tasks.md`/`design.md`, uma vez para o grupo `[BACK]` e uma vez para
o `[FRONT]` (se houver), **antes** de criar a issue de proposta. Isso existe
porque, na prática (BUN-888, BUN-889), o padrão dominante de "mudanças
pedidas" do Backend não é preferência de estilo — é exatamente o tipo de
coisa que C1-C11 cobre (classe/arquivo citado que não existe do jeito que
foi descrito, tarefa de wiring faltando — grid collection virtualType,
sequência de módulo pro `setup:di:compile`, atributo custom faltando na lista
de persistência —, rota REST que não bate com o design). Passar seu próprio
rascunho pela mesma régua que vai julgá-lo pega isso ANTES da review, sem
esperar o Backend encontrar um problema por rodada.

Diferença do seu autocheck para a review de verdade: você não aprova nem
reprova nada — você **corrige o que encontrar direto no artefato**, antes de
seguir para o passo 10. Para cada item C1-C11 que falhar no autocheck, ou
você tem certeza da correção (aplique e siga), ou é um ponto genuinamente
técnico que só o Backend/Frontend sabe resolver com segurança — nesse caso
(raro; a maioria dos itens C1-C11 é verificável só lendo o código) comente a
dúvida específica no corpo do PR draft (passo 10) em vez de adivinhar; isso
não bloqueia a criação da proposta, só sinaliza o ponto pro reviewer prestar
atenção nele. Não pule este passo achando que "parece completo" — C1
("todo arquivo/classe citado existe? confira com `git show`") é a categoria
que mais gera rodadas de review perdidas: um nome de classe, virtualType ou
caminho descrito de memória em vez de confirmado no código real.

**10. Commit, push e PR draft.**
```bash
git add openspec/
git diff --cached --name-only | grep -v '^openspec/' && echo "ERRO: arquivo fora de openspec/"   # precisa não imprimir nada
git commit -m "openspec: propose $CH (BUN-$EXT)"
export GH_TOKEN="${GH_TOKEN:-${GITHUB_TOKEN:-}}"
if [ -z "$GH_TOKEN" ]; then
  B="${TWINFORGE_API_URL%/}"; B="${B%/api}"
  GH_TOKEN=$(curl -s -X POST -H "Authorization: Bearer $TWINFORGE_API_KEY" "$B/api/agents/me/secrets/github_token/value" | python3 -c 'import json,sys;print(json.load(sys.stdin).get("value",""))'); export GH_TOKEN
fi
git push origin "HEAD:refs/heads/feature/BUN-$EXT" \
  || git -c "http.extraheader=AUTHORIZATION: basic $(printf 'x-access-token:%s' "$GH_TOKEN" | base64 -w0)" push origin "HEAD:refs/heads/feature/BUN-$EXT"
gh pr list --head "feature/BUN-$EXT" --state open --json number,url   # já existe? reutilize
gh pr create --draft --base twinforge --head "feature/BUN-$EXT" --title "$EXT - <Título da card>" --body-file <arquivo com o template de 7 seções de bunge-engineering-practices, seção 3 dizendo "Planejamento OpenSpec: openspec/changes/$CH/">
```
Nunca imprima nem comente o token. Push recusado ou `gh` ausente: `blocked`
com a mensagem de erro (sem o token). Pare.

**11. Subtarefas (sem assignee, sem blockers).** Para cada grupo `[BACK]`/`[FRONT]`
do `tasks.md`, crie uma issue filha da **card**, e não desta issue de proposta:
o board agrupa as subtarefas pelo pai, e o Tech Lead as procura como filhas da
card. O id certo é o `parentId` da proposta:
```bash
B="${TWINFORGE_API_URL%/}"; B="${B%/api}"
CARD=$(curl -s -H "Authorization: Bearer $TWINFORGE_API_KEY" "$B/api/issues/$TWINFORGE_TASK_ID" \
  | python3 -c 'import json,sys;print(json.load(sys.stdin)["parentId"])')
```
Crie cada subtarefa com `parentId` = `$CARD`, o título e as 4 linhas de
cabeçalho de `bunge-openspec-flow`, label `openspec` + `backend`/`frontend`.
Depois de criar, leia a subtarefa de volta e confira que o `parentId` é
`$CARD`; se não for, corrija com `PATCH` antes de seguir. Depois do cabeçalho, o corpo segue
`bunge-subtask-contract` §1 (a subtarefa roda num contexto limpo e o dev só
sabe o que está nela):
- **Objetivo** do grupo em 1-3 frases;
- **Contexto**: por que o grupo existe e as decisões do `design.md` que valem
  para ele, 1 frase cada;
- **Entradas**: as seções exatas de `design.md` que o grupo usa, os arquivos a
  alterar ou criar e o módulo de referência, todos conferidos com `git show`;
- **Skills**: até 3 slugs da lista do dev (ex. `adobe-create-repository`);
- **Fora de escopo**: o que pertence a outro grupo ou a outra change;
- a lista das tarefas do grupo copiada;
- **Cenários a provar**: `<capability> / <nome do cenário>; ...`, os cenários
  (`#### Scenario:`) que aquele grupo precisa provar com arquivo:linha.

Todo cenário das specs precisa estar em pelo menos uma subtarefa. Guarde os IDs
das subtarefas.

**12. Stages de review, aprovação por complexidade, e handoff.** Antes de
montar o `executionPolicy`, leia `twinforge/workflow.yaml`: o sinal de
complexidade que você mesmo já calculou na triagem decide se esta change
segue `policies.standard_path` ou `policies.low_complexity_path`. Monte os
stages de review dos devs sempre:
```json
{"executionPolicy":{"stages":[
  {"type":"review","participants":[{"type":"agent","agentId":"{{uuid}}"}]},
  {"type":"review","participants":[{"type":"agent","agentId":"{{uuid}}"}]}
]}}
```
Sem grupo `[FRONT]`: tire o segundo stage.

Envie o `executionPolicy` e `"status":"in_review"` no **mesmo** `PATCH` da issue. A plataforma só
inicia o stage, e acorda o revisor, quando o `PATCH` traz `status: in_review`; política gravada
com a issue já em `in_review` não chama ninguém. Se isso aconteceu, reenvie `{"status":"in_review"}`.

**Se a política aplicável tiver `requires_proposal_approval: true`**
(hoje isso é `standard_path` — complexidade média/alta): depois que os
stages de review acima resolverem, **não feche a issue sozinho**. Em vez
disso, crie uma interaction `ask_user_questions` endereçada ao Kaio
(`twinforge/roles.yaml`, papel `client_approver`) perguntando explicitamente
permissão para seguir com esta proposta, deixe a issue em `status: in_review`,
e pare — você é acordado de novo quando ele responder. **Nunca use
`status: blocked` para isso**: `blocked` é para dependência entre issues;
`in_review` é o que sinaliza "um humano precisa agir", e é o que não
aciona a notificação de demora do TwinForge pro PO (que é pensada para
`blocked`, não para uma espera normal de aprovação).

**Se a política tiver `requires_proposal_approval: false`** (hoje isso é
`low_complexity_path` — complexidade baixa): não crie interaction nenhuma
pra isso — a issue fecha sozinha quando o(s) stage(s) de review forem
aprovados pelos devs, sem esperar o Kaio. Ele revisa o PR draft quando
quiser, sem bloquear.

Qualquer ambiguidade sobre qual política vale para este caso (ex. o sinal
de complexidade não bate claramente em nenhuma das duas) — não decida
sozinho: comente a issue, mova para `in_review` e acione o **Workflow
Manager** (`twinforge/roles.yaml`, papel `workflow_manager`), com uma
interaction, não só um comentário.

Quando a issue puder fechar (stages de review ok, e aprovação do Kaio ok
se exigida), ela libera o card pai (bloqueado por ela) para o Tech Lead
seguir à fase 5 (atribuir as subtarefas). Depois comente (template abaixo)
e mova para `in_review`. Limpe o worktree:
```bash
cd "$REPO" && git worktree remove --force "$WT" && git worktree prune
```

## Template do comentário final
```
Proposta OpenSpec pronta: `<CH>`
PR draft: <url>
Artefatos: proposal, specs (<capabilities>), design, tasks — validate --strict OK
Subtarefas criadas (sem assignee): <lista com IDs e grupos>
Suposições registradas: <lista ou "nenhuma">
Regras: <n> documentadas · <n> só no código · <n> não encontradas (detalhe em design.md)
Próximo: review do Backend → review do Frontend (se houver) → pergunta de aprovação ao Kaio via ask_user_questions, se a política exigir → Tech Lead atribui as subtarefas.
```



