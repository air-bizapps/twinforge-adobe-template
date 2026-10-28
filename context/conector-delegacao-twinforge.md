# Conector externo → TwinForge: delegar cards refinados aos agentes

Contexto para uma **aplicação ou ferramenta externa** (que tem cards refinados e prontos
para desenvolvedor) delegar o desenvolvimento aos agentes TwinForge do Bunge Magento 2
via `twinforgecli`. Leia inteiro antes de implementar o conector.

Sem dados de instância neste arquivo (URL, tokens, ids de issue). Ids de company, projeto
e agentes ficam em `twinforge/roles.yaml` e `agents/*/agent.yaml`; leia de lá, não copie.
Pré-requisitos do CLI (instalado, URL da API, login) e gotchas da plataforma:
`context/twinforge-context.md`. Padrões de falha dos agentes: `context/licoes-fluxo-openspec.md`.

## 1. Modelo mental

- Tudo na TwinForge é **issue**. Delegar = criar uma issue e atribuí-la a um agente. Não
  existe "chamar o agente": o agente é acordado (heartbeat) quando recebe a issue, é
  mencionado, ou responde-se a algo que ele esperava.
- Cada acordada é um run curto e sem memória de conversa. A continuidade vive na
  **issue, nos comentários e nos documentos da issue**. O conector se comunica com os
  agentes por esses mesmos canais.
- O conector **entrega a card e observa**. Quem decompõe, atribui, revisa e dá push são os
  agentes. O conector nunca escreve código nem mexe em git do repo Bunge-Magento2.

## 2. Fluxo (resumo de `twinforge/workflow.yaml` e da skill `projeto-openspec-flow`)

Escopo: **só o pod Magento/e-commerce** (repo `Bunge-Magento2`). O pod Mobile tem fluxo
próprio, sem OpenSpec; card Mobile nunca entra por aqui.

```
Conector cria a card (atribuída ao Tech Lead, com contexto completo)
  → Tech Lead: curadoria (original guardado, descrição reescrita como prompt, gate de DoR)
  → Tech Lead: triagem (domínio, critérios de aceite, pergunta ao solicitante se faltar algo)
  → decide o caminho:
      label `openspec` (ou >1 módulo, integração externa nova, mudança de arquitetura):
          Tech Lead cria `[OPENSPEC] Proposta` → OpenSpec Manager faz propose + subtarefas
          → review técnico Backend/Frontend → (se exigido) aprovação humana
      senão: Tech Lead refina e cria subtarefas direto
  → Tech Lead atribui UMA subtarefa de dev por vez (Backend ou Frontend)
  → dev implementa no worktree da card, commita local, entrega relatório
  → Tech Lead dá push; OpenSpec Manager arquiva (confere todo cenário da spec)
  → Tech Lead tira o PR de draft e fecha a card
```

Um **PR por card**, branch `feature/BUN-<n>` a partir da branch base do projeto. O merge é
humano.

Gates humanos (`approval_required`) ficam em `in_review` com uma *interaction* pendente.
Quais disparam depende da complexidade: caminho simples exige code review humano;
caminho padrão/sensível exige aprovação de proposta e de arquitetura. Valores atuais em
`twinforge/workflow.yaml › policies`. Módulos sensíveis (forçam o caminho padrão) estão
em `policies.complexity.sensitive_modules`.

## 3. Como o conector cria a card

Comando (todos os campos de texto são `--description`, markdown):

```bash
twinforgecli issue create -C <company_id> \
  --project-id <project_id> \
  --assignee-agent-id <tech_lead_id> \
  --title "BUN-<n> - <título>" \
  --description "<corpo, ver 3.2>" \
  --priority <priority> --json
```

- `company_id`, `project_id` (`scope.project_id`) e id do Tech Lead (`roles.tech_lead`):
  de `twinforge/roles.yaml`. Confirme com `twinforgecli project list --json` se tiver dúvida.
- Guarde o `id` (UUID) e o `identifier` retornados. **`issue comments` e `issue update`
  exigem o UUID**; `issue get` aceita os dois.
- Título no padrão `BUN-<n> - <título>`, sem colchetes. `n` é o id externo da card.
  Convenção de branch: `feature/BUN-<n>` com checagem de colisão feita pelo agente.
- **Label `openspec`/`backend`/`frontend`:** `issue create` não tem flag de label. Não
  confirmado como o CLI aplica label; verifique (`twinforgecli issue --help`,
  `twinforgecli openapi`) antes de depender disso. Alternativa segura: escrever no corpo
  "Usar OpenSpec: sim/não" e deixar o Tech Lead aplicar a label (ele já decide e justifica).
- Não crie subtarefas, não crie blockers, não atribua a ninguém além do Tech Lead.
  Card atribuída direto a um dev, ao OpenSpec Manager ou ao DevOps volta sozinha para o
  Tech Lead (checagem de origem no `AGENTS.md` deles), com uma run desperdiçada.
  Criar `blockedByIssueIds` apontando para issue sem assignee dispara reatribuição
  automática (Orphan Blocker).

### 3.1 Idempotência

Antes de criar, procure por `BUN-<n>`: `twinforgecli issue list -C <company_id> --match "BUN-<n>" --json`.
Se já existe, **não crie outra**: comente na existente. Duas cards para o mesmo `n`
geram colisão de branch/PR.

### 3.2 Corpo da card (o que o Tech Lead precisa para não começar frio)

Toda card de topo passa pela **curadoria** do Tech Lead (`skills/card-curation`): ele
copia o texto que chegou para o documento `original` da issue e reescreve a descrição como
prompt, marcando a origem de cada item. O conector não precisa (nem deve) pular isso, mas
pode encurtar o trabalho mandando a card já no formato curado, com tudo marcado `[card]`:

```markdown
Card: <id externo> · Origem: conector · Original: documento `original`

## Objetivo
<resultado observável para quem pediu, 1–3 frases> [card]

## Contexto
- <por que a demanda existe: problema, quem é afetado, restrições> [card]

## Escopo
- <o que entra, um item por linha> [card]

## Fora de escopo
- <o que fica de fora e para onde vai> [card]

## Critérios de aceite
- <comportamento observável, com valores concretos> [card]

## Referências
- <links, Figma, docs, módulos Bunge envolvidos se souber> [card]
- Card externa: <id/link na ferramenta de origem> · Solicitante: <nome ou papel> [card]
- Testes unitários: <sim | não> [card]   (os devs respeitam "sem teste unitário")

## Perguntas abertas
- nenhuma

Não usado do original: nada
```

Regras de qualidade: critérios com valores concretos; nada de "melhorar", "ajustar".
Card nesse formato passa pela curadoria só como conferência. Card em texto livre também é
aceita, mas o Tech Lead vai reescrevê-la e, se faltar objetivo ou critério verificável,
perguntar de volta, o que custa uma rodada de espera.

O texto que o conector enviou nunca se perde: fica no documento `original` da card, com
histórico de revisões.

### 3.3 Entrada pelo Azure DevOps (integração futura)

A integração ainda não existe. Quando for construída, ela segue o mesmo contrato do conector
e mais estas regras, para não brigar com a curadoria:

- **Importa só na criação.** Cria a card atribuída ao Tech Lead com o texto do Azure
  DevOps na descrição (o Tech Lead copia para o `original` e cura).
- **Mudança posterior no Azure DevOps chega como comentário** na card, com o que mudou.
  Nunca sobrescreve a descrição, que depois da curadoria é o prompt dos agentes. O Tech
  Lead incorpora o comentário só na seção afetada.
- **Nunca escreve de volta na descrição do Azure DevOps.** A card do cliente é o registro
  dele. Se o cliente quiser ver a versão curada, ela vai como comentário lá, nunca como
  edição.
- Idempotência pelo id externo (seção 3.1), como no conector.

## 4. Observar o andamento

Status de issue relevantes: `todo` (na fila), `in_progress`, `in_review` (esperando
humano ou revisão), `blocked` (dependência ou falha), `done`, `cancelled`.

```bash
twinforgecli issue get <id_ou_identifier> -C <company_id> --json         # status atual
twinforgecli issue list -C <company_id> --match "BUN-<n>" --json          # card + subtarefas
twinforgecli issue comments <uuid> -C <company_id> --json                 # histórico
twinforgecli issue interactions <uuid> -C <company_id> --json             # perguntas/aprovações pendentes
twinforgecli issue tree-state <uuid> -C <company_id> --json               # árvore de controle da card
```

Use polling espaçado (minutos, não segundos). Cada consulta é barata, mas cada comentário
seu **acorda um agente** e gasta orçamento.

O que o conector deve fazer em cada situação:

| Sinal | Significado | Ação do conector |
|---|---|---|
| Interaction pendente (pergunta ao solicitante) | Tech Lead/OpenSpec Manager precisa de informação | Levar a pergunta ao humano dono da card; responder com `issue interaction:respond` (seção 5) |
| Card `in_review` + approval/confirmation pendente | Gate humano do fluxo | **Não aprove automaticamente.** Encaminhar ao aprovador humano (`human:client_approver`); o conector só repassa a decisão que ele tomar |
| Card `blocked` | Dependência estrutural entre issues (normal enquanto há filhas ativas) ou falha | Se há filhas ativas, ignore. Se não há, avise um humano e não tente "destravar" |
| Card `done` | Fluxo fechou | Registrar o link do PR; **conferir** (seção 6) |
| Nenhuma mudança por muito tempo | Pode ser fila, orçamento ou falha de adapter | Alertar humano; não recriar a card |

Há um bridge pronto que espelha bloqueios e decisões humanas no Slack e executa ações de
humanos autorizados: `integrations/slack-bridge/` (README próprio). Se o conector precisar
notificar pessoas, considere reaproveitá-lo em vez de duplicar essa lógica.

## 5. Responder o que os agentes perguntam

Perguntas aos humanos chegam como *interactions* na issue. Para responder:

```bash
twinforgecli issue interactions <uuid> -C <company_id> --json            # achar o interactionId e o formato
twinforgecli issue interaction:respond <uuid> <interactionId> -C <company_id> \
  --answers-json '<array JSON no formato da pergunta>' \
  --summary-markdown "<resumo da resposta>"
```

- Leia o formato das opções na própria interaction antes de montar `--answers-json`.
- O conector responde **com a resposta do humano**, nunca a inventa. Resposta do conector
  sem origem humana contamina a spec.
- Pedidos de aprovação formal: `issue interaction:accept` / `interaction:reject`
  (decisão humana; ver tabela da seção 4).
- Comentário avulso para complementar informação: `twinforgecli issue comment <uuid> --body "..."`.
  Cada comentário acorda o assignee atual. `--reopen` reabre issue `done`/`cancelled` e
  `--resume` pede retomada explícita; use só quando for a intenção (seção 6).

## 6. Nunca confiar em "done" pelo texto

Já houve relatórios de agente inteiramente fabricados (commit citado que não existia,
"validate OK" sem rodar). O conector não deve marcar a card de origem como concluída só
porque a issue virou `done`. Verifique de forma independente:

- o PR existe no repo `Bunge-Magento2`, na branch `feature/BUN-<n>`, e saiu de draft;
- os commits do relatório existem na branch;
- cada critério de aceite aparece, nos comentários finais ou no relatório de entrega, com
  `arquivo:linha` (o fluxo exige isso por cenário).

Se algo não bate, **reabra citando a evidência** (comentário) e peça confirmação por
read-back; não dê a card como entregue.

## 7. Falhas comuns e como o conector reage

| Sintoma | Causa provável | O que fazer |
|---|---|---|
| CLI cai em `localhost:3100` | Perfil vazio (versão nova do CLI zera `context.json`) | Reconfigurar `context set --api-base`; sem login, só o humano roda `twinforgecli connect --persona board` |
| `API error 403` com HTML "Forbidden" | WAF do nginx, não política da plataforma | Conteúdo com aparência de tag/caminho (`<...php`, `/.../`) no `--description`. Reenvie sem esses trechos (use texto descritivo ou envie como documento da issue) |
| Issue volta de `done` para aberta | Comentário postado sem token de agente é registrado como `authorType: user` e re-dispara o wake | Não re-derive trabalho; uma checagem rápida, `done` de novo, no máximo um comentário |
| Card bloqueia citando orçamento | `budgetMonthlyCents = 0` lido como estouro | Humano confere `agent get`; correção é valor positivo real |
| Runs morrendo sem tokens | Adapter/modelo incompatível após troca | Avisar humano (ver `twinforge-context.md`, "Trocar o adapter") |
| `issue comments` não acha a issue | Usou o identificador curto | Use o UUID |

## 8. Restrições do conector (resumo)

1. Cria a card **só** para o Tech Lead e **uma vez** por `BUN-<n>`.
2. Não cria subtarefas, não atribui devs, não mexe em blockers.
3. Não aprova gates nem responde pergunta sem decisão humana.
4. Não escreve em git do repo de código nem toca `agents/`, `skills/`, `twinforge/` deste repo.
5. Nunca imprime, loga ou versiona tokens; credencial vem do perfil do CLI do usuário.
6. Mudanças em agente, skill ou `desiredSkills` são feitas fora do conector, pelo dono da
   instância (`push`/`sync` escrevem em produção e exigem confirmação).
7. Verifica a entrega por evidência (seção 6) antes de fechar a card de origem.

## 9. Pontos em aberto (verifique antes de implementar)

- Como aplicar label (`openspec`, `backend`, `frontend`) por CLI ou API.
- Se o conector deve rodar com credencial de *board* (humano) ou de *agente*
  (`agent-prompt`/`--api-key`); esta nota assume board, via perfil do CLI.
- Mapeamento entre o id externo da ferramenta de origem e `BUN-<n>`.
- Se há card Mobile no mesmo fluxo de origem (precisa de roteamento próprio, fora deste arquivo).
- Fonte de verdade para os valores das políticas: `twinforge/workflow.yaml` (pode mudar;
  releia em vez de fixar no conector).
