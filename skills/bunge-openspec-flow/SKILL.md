---
name: bunge-openspec-flow
description: "Contrato comum do fluxo OpenSpec com os agentes TwinForge da Bunge: quem faz cada fase (Tech Lead, OpenSpec Manager, Backend, Frontend), formato das issues, stages de review/approval, regras de git/branch/push. Leia sempre que uma issue tiver a label openspec ou mencionar uma OpenSpec change."
---

# Fluxo OpenSpec do Bunge na TwinForge

**Escopo: só Magento/e-commerce** (repo `Bunge-Magento2`, projeto "Onboarding").
O pod de Mobile (`bunge-mobile`, agentes Mobile Tech Lead/Mobile Developer)
tem seu próprio fluxo, mais leve, sem OpenSpec — `bunge-mobile-flow`. As duas
coisas não se misturam: `twinforge/workflow.yaml` e esta skill nunca se
aplicam a uma issue do projeto Mobile, e vice-versa.

Esta skill é o contrato comum. Ela diz **quem** faz cada fase. O **como** de
cada fase está na skill da fase (`openspec-propose`, `openspec-review-proposal`,
`openspec-apply-change`, etc.). Se a fase não é sua, não execute: siga o
"Se você recebeu algo que não é seu" no fim.

## IDs fixos

| Quem | ID |
|---|---|
| Tech Lead | agent `{{uuid}}` |
| OpenSpec Manager | agent `{{uuid}}` |
| Magento Backend Developer | agent `{{uuid}}` |
| Magento Frontend Developer | agent `{{uuid}}` |
| Kaio (revisor do PR, não bloqueia a esteira) | user `zKCKeiFpjszwCIrujWle8voAIbrX8Ebn` |
| Workflow Manager (dono de `twinforge/`, escalonar aqui ambiguidade de fluxo) | agent `{{uuid}}` |

Workspace compartilhado: `/twinforge/instances/default/workspaces/{{uuid}}`.
O repo git é o diretório que tem `.git` e `openspec/`. Normalmente é o subdiretório
`bunge-magento2/`; se ele não existir, é o próprio workspace:

```bash
WS=/twinforge/instances/default/workspaces/{{uuid}}
REPO="$WS"; [ -d "$WS/bunge-magento2/.git" ] && REPO="$WS/bunge-magento2"
cd "$REPO"
```

## Gatilho

1. Card com a label `openspec` → fluxo OpenSpec obrigatório.
2. Card sem a label → o Tech Lead decide. Usa OpenSpec só se tocar mais de um
   módulo, trouxer integração externa nova, mudar arquitetura ou precisar de
   spec viva para várias capabilities. Se usar, adiciona a label `openspec`
   e comenta o motivo em uma frase.
3. Fora disso → fluxo normal (plan → subtarefas), sem OpenSpec.

## Fases e donos

| # | Fase | Dono | Skill | Termina quando |
|---|---|---|---|---|
| 1 | Triagem, achados de domínio + issue de proposta | Tech Lead | esta + `bunge-vague-demand-triage` | Domínio identificado, `bunge-domain-lookup` rodado, critério de aceite escrito (redigido pelo Tech Lead se a card não trouxer um), pontos genuinamente em aberto esclarecidos com o cliente via `ask_user_questions`; issue `[OPENSPEC] Proposta` criada com os achados na descrição e atribuída ao OpenSpec Manager; card pai `blocked` por ela |
| 2 | Refinamento + proposta + subtarefas | OpenSpec Manager | `openspec-propose` | `validate --strict` ok, PR draft aberto, subtarefas criadas sem assignee, stages de review configurados; se a complexidade exigir aprovação, pergunta ao Kaio via `ask_user_questions` antes de liberar; issue `in_review` |
| 3 | Review técnico | Backend, depois Frontend (se houver `[FRONT]`) | `openspec-review-proposal` | Stage aprovado (`done`) ou mudanças pedidas (`in_progress`) |
| 3b | Update | OpenSpec Manager | `openspec-update-change` | Artefatos revisados, validados, enviados; issue de volta a `in_review` |
| 4 | Apply | Backend / Frontend, uma subtarefa por vez, atribuída pelo Tech Lead | `openspec-apply-change` | Tarefas da subtarefa `[x]`, testes escritos (não executados), relatório de entrega com arquivo:linha por cenário, commit local |
| 5 | Push do código | Tech Lead | (CONTEXT do Tech Lead) | Commits de código no `origin` |
| 6 | Conformidade com a spec + archive | OpenSpec Manager, disparado pelo Tech Lead | `openspec-archive-change` | Todo cenário apontado para o código que o implementa; `openspec archive` commitado e enviado |
| 7 | Fechamento | Tech Lead | (CONTEXT do Tech Lead) | PR sai de draft; card `done` |

**Se a issue de proposta espera uma resposta do Kaio antes de liberar o
desenvolvimento ou não é decidido por `twinforge/workflow.yaml`** (ver seção
abaixo), não por uma regra fixa aqui — o único ponto fixo é que a
responsabilidade de pegar problemas antes do apply é 100% da review técnica
dos devs (fase 3), por isso ela tem que ser rigorosa (ver checklist C1-C16
em `openspec-review-proposal`), espere-se ou não essa resposta do Kaio.

## twinforge/workflow.yaml governa as decisões de gate

O Tech Lead segue `twinforge/workflow.yaml` (pasta irmã de `openspec/`, no
mesmo repo) para decidir o que roda automático e o que precisa de aprovação
humana — vale para toda issue, com ou sem a label `openspec`. O parágrafo
acima sobre a aprovação do Kaio descreve o comportamento padrão hoje; se
`workflow.yaml` disser algo diferente para um caso específico (por exemplo
via `policies.standard_path.requires_proposal_approval` ou
`policies.low_complexity_path`), é o arquivo que vale, não esta frase — ele
é a fonte de verdade executável, esta skill é só o contrato de quem-faz-o-quê.

Se você (Tech Lead ou qualquer agente desta esteira) encontrar algo que
`twinforge/workflow.yaml` não cobre, uma contradição entre o arquivo e esta
skill, ou um papel/id que não bate com `twinforge/roles.yaml` — **não decida
sozinho**: coloque a issue em `in_review`, acione o **Workflow Manager**
(tabela de IDs acima; ele só mexe em `twinforge/`, nunca em `openspec/` nem
em código), e espere ele resolver a lacuna antes de seguir. Ele pode
precisar perguntar para um humano antes de responder — isso é esperado, não
é para ser forçado a decidir na hora.

## Formato das issues (todas filhas da card, com `parentId` = card)

| Título | Criada por | Assignee |
|---|---|---|
| `[OPENSPEC] Proposta — <título da card>` | Tech Lead | OpenSpec Manager |
| `[BACK] <resumo> — <change> (tarefas X.Y–X.Z)` | OpenSpec Manager | nenhum; o Tech Lead atribui ao Backend |
| `[FRONT] <resumo> — <change> (tarefas X.Y–X.Z)` | OpenSpec Manager | nenhum; o Tech Lead atribui ao Frontend |
| `[OPENSPEC] Arquivar — <change>` | Tech Lead | OpenSpec Manager |

Toda issue `[BACK]`/`[FRONT]` tem, no início da descrição, estas 4 linhas exatas:

```
Change: <nome-da-change>
Tarefas: <lista, ex. 2.1, 2.2, 2.3>
Branch: feature/BUN-<ID-externo> · Worktree: $WS/worktrees/<ID-externo>
Artefatos: openspec/changes/<nome-da-change>/
```

Depois do cabeçalho, o corpo segue `bunge-subtask-contract` (Objetivo,
Entradas, Skills, Fora de escopo, tarefas, `Cenários a provar`): o dev executa
num contexto limpo e só sabe o que está na subtarefa.

**Nunca** crie issue com `blockedByIssueIds` apontando para uma issue sem
assignee (o mecanismo Orphan Blocker da plataforma reatribui e dispara runs).
As subtarefas `[BACK]`/`[FRONT]` nascem sem assignee e sem blockers; a
ordem é controlada pelo Tech Lead na atribuição, uma por vez.

## Stages da issue de proposta

Configurados pelo OpenSpec Manager (via `PATCH /api/issues/:id` com
`executionPolicy`, no mesmo `PATCH` que leva a issue a `in_review`; ver `openspec-propose` passo 12), nesta ordem:

1. `review` — Backend (sempre)
2. `review` — Frontend (só se `tasks.md` tiver grupo `[FRONT]`)

**Aprovação do Kaio: pergunta via `ask_user_questions`, condicional, não um
stage.** `openspec-propose` lê `twinforge/workflow.yaml` e só pergunta se a
política da complexidade da tarefa exigir (`requires_proposal_approval:
true`). Quando exigida: depois dos stages de review resolverem, a issue
**não** fecha sozinha — cria-se uma interaction `ask_user_questions`
endereçada ao Kaio pedindo permissão para seguir, a issue fica
`status: in_review` (nunca `blocked` — ver `blocked-exige-blocker-declarado`),
e só depois da
resposta dele o card pai libera para a fase 4. Quando não exigida, os stages
de review dos devs já bastam para fechar a issue sozinha. Em qualquer um dos
dois casos, se o Kaio pedir mudança depois do apply já ter começado, isso
vira uma issue nova de ajuste, não um retrocesso da esteira.

Decisão de reviewer: aprovar = `PATCH` com `status: done` + comentário.
Pedir mudanças = `PATCH` com `status: in_progress` + comentário no template da
`openspec-review-proposal`. A issue volta sozinha para o OpenSpec Manager.

QA fora do fluxo por enquanto: o agente de QA ainda está em fase inicial. A
verificação de que o código atende a spec é feita pelos devs (arquivo:linha
por cenário no relatório de entrega) e conferida pelo OpenSpec Manager no
archive, lendo o código.

Testes unitários: os devs **escrevem**, mas **não rodam** PHPUnit, `grumphp` nem
cobertura (não há meta de cobertura). O Tech Lead **não reverifica** as
tarefas no fim: o relatório de entrega de cada subtarefa é a base do PR. Entre
uma subtarefa e a próxima ele só roda o gate de `bunge-subtask-contract` §4
(o commit existe, branch certa, sem push, uma prova por critério).

## Git, branch e push

- Branch única da card: `feature/BUN-<ID-externo>`, a partir de `twinforge`
  (`$BUNGE_BASE_BRANCH`). Um PR só, para `twinforge`, título
  `<ID-externo> - <Título>`.
- **OpenSpec Manager**: cria a branch no propose e dá push **somente** de
  commits que tocam apenas `openspec/`. Abre o PR como **draft**. Trabalha
  sempre num `git worktree` temporário, nunca trocando a branch do checkout
  compartilhado.
- **Backend/Frontend**: commitam no worktree da card (`$WS/worktrees/<ID-externo>`), **nunca** dão push.
  Como a branch já existe no `origin`, a prova de que não houve push é:
  `git log origin/feature/BUN-<ID>..HEAD --oneline` lista os seus commits.
- **Tech Lead**: dá push do código quando todas as subtarefas de dev estão `done` com relatório de entrega (fase 6, sem reverificar), tira o PR de
  draft no fechamento. O merge é do Kaio.

## Gates (verificados por comando, nunca por opinião)

| Gate | Comando | Passa se |
|---|---|---|
| Proposta válida | `openspec validate <change> --strict --json --no-interactive` | `valid: true` para a change |
| Artefatos completos | `openspec status --change <change> --json` | proposal, specs, design e tasks `done` |
| Pronto para arquivar | `grep -c '^- \[ \]' openspec/changes/<change>/tasks.md` | `0` |
| Dev concluído | subtarefas `[BACK]`/`[FRONT]` da card | todas `done` |
| Conformidade com a spec | cada `#### Scenario:` das specs da change | tem arquivo:linha no relatório de entrega de uma subtarefa de dev (seção `Cenários atendidos`), e o código lido faz o THEN |

## Convenção de status: `in_review` vs. `blocked`

Carregue `blocked-exige-blocker-declarado` sempre que for gravar `blocked`
ou precisar de decisão humana — ela ensina o protocolo certo (payload de
`ask_user_questions`/`request_confirmation`, `continuationPolicy:
wake_assignee`, quando `blocked` é mesmo o caso certo). Vale pra todo
agente desta esteira, em qualquer fase, não só nas que esta skill descreve
em detalhe.

## Se você recebeu algo que não é seu

Não execute. Comente em uma frase o que recebeu e qual fase é de quem (tabela
acima), reatribua ao Tech Lead e mova para `todo`. Nunca rode propose, update
ou archive se você não é o OpenSpec Manager. Nunca escreva código se você é o
OpenSpec Manager ou o Tech Lead.



