# twinforge/

Config de orquestração para os agentes TwinForge que trabalham neste
repositório. Não confundir com `openspec/`, que é a pasta da ferramenta
[OpenSpec](https://openspec.dev) em si (specs, changes) — essa pasta não pode
ser renomeada nem movida, o nome `openspec` está fixo no CLI. `twinforge/`
existe ao lado dela só pra guardar **como os agentes orquestram o trabalho**
(quem faz o quê, quando precisa de aprovação humana), o que não é
responsabilidade do OpenSpec.

**Escopo: só o pod de Magento/e-commerce.** A Bunge também tem um pod de
Mobile (`bunge-mobile`, projeto TwinForge separado, Mobile Tech
Lead/Mobile Developer) com seu próprio fluxo mais leve, sem OpenSpec
(`bunge-mobile-flow`) — isso aqui nunca se aplica a ele. Ambos os arquivos
declaram isso explicitamente num bloco `scope:` no topo.

## Pré-requisitos

- **[`twinforgecli`](https://docs.twinforge.webjump.ai/cli/)** instalado e
  autenticado contra esta company TwinForge. É a ferramenta usada pra listar
  agentes, confirmar ids e, pro Workflow Manager, abrir/atualizar issues. Sem
  ela, dá pra ler `workflow.yaml`/`roles.yaml` à mão, mas não dá pra validar
  se os ids que estão lá ainda existem.
- Acesso de escrita ao repositório `Bunge-Magento2` (clone local ou o
  workspace compartilhado do TwinForge) — é onde este diretório vive.
- O id desta company TwinForge (`{{uuid}}`) — a
  maioria dos comandos `twinforgecli` abaixo precisa dele via `-C`.

## Primeiros passos

Pra quem está chegando agora (humano ou o próprio Workflow Manager, numa
sessão nova):

```bash
# 1. confirma que a CLI está instalada e autenticada
twinforgecli --version
twinforgecli company list

# 2. lista os agentes reais desta company — compare o resultado com
#    twinforge/roles.yaml antes de confiar em qualquer id que já esteja lá
twinforgecli agent list -C {{uuid}}

# 3. leia os dois arquivos — nessa ordem: workflow.yaml primeiro (o fluxo),
#    roles.yaml depois (quem é quem no fluxo que você acabou de ler)
cat twinforge/workflow.yaml
cat twinforge/roles.yaml
```

Se é sua primeira vez mesmo (nunca leu nada disso), **não comece por aqui** —
comece pela doc ["Fluxo TwinForge — Bunge"](https://claude.ai/code/artifact/{{uuid}}),
que explica o porquê de cada fase existir. Volte pra este diretório só
depois, quando precisar saber exatamente o que o agente vai fazer num caso
concreto.

## O que tem aqui

| Arquivo | Função |
|---|---|
| [`workflow.yaml`](./workflow.yaml) | O fluxo de desenvolvimento ponta a ponta: fases, quem executa cada uma (agente/humano/cliente), se roda automático ou precisa de aprovação, como o trabalho se ramifica por complexidade. É o que o agente Tech Lead lê pra decidir se trava o desenvolvimento ou não. |
| [`roles.yaml`](./roles.yaml) | Mapeia os papéis usados em `workflow.yaml` (`agent:tech_lead`, `human:client_approver`, etc.) para ids reais de agente/pessoa nesta instância do TwinForge. |
| [`waf-probe.py`](./waf-probe.py) | Confere, sem escrever nada, se o WAF da instância deixa publicar os arquivos de uma skill (e, com `--bisect`, qual linha barra). Rode antes de publicar arquivos além do `SKILL.md`. |

## Quem mantém isso

O agente **Workflow Manager** (`{{uuid}}`) é o
dono deste diretório — só ele commita e abre PR aqui dentro. Ele é acionado
direto pelo PO pra mudar política, ou escalado pelo Tech Lead (via issue em
`in_review`) quando algo nestes arquivos está incompleto, ambíguo, ou em
contradição com a realidade (um id desatualizado, um papel sem dono). Não
edite estes arquivos manualmente sem envolvê-lo, ou ele e você vão divergir
na próxima auditoria que ele fizer do roster de agentes.

## Como configurar `roles.yaml`

Isso é trabalho do Workflow Manager (ou de um humano, antes do agente
existir pela primeira vez). Passo a passo:

1. Rode `twinforgecli agent list -C {{uuid}}` e
   confirme, pra cada papel que `workflow.yaml` referencia (`tech_lead`,
   `openspec_manager`, `backend_dev`, `frontend_dev`, e qualquer `agent:<x>`
   novo que apareça num `owner`), que existe um agente real correspondente.
2. Em `roles.yaml`, cada entrada de papel precisa de `kind` (`agent` ou
   `human`) e `id` — o id do agente (campo `id` do passo 1) ou, pra um
   humano, o id de usuário (não o nome; pegue de uma menção existente ou
   pergunte, nunca invente).
3. Se um papel em `workflow.yaml` não tem entrada em `roles.yaml`, ou aponta
   pra um id que o passo 1 não listou mais, isso é a divergência que o
   Workflow Manager audita (ver "Quem mantém isso") — corrija o id
   diretamente se for só isso, ou abra `ask_user_questions` se não estiver
   claro quem deveria assumir o papel agora.
4. Commit só de `twinforge/roles.yaml`, branch a partir de `twinforge`, PR
   pra `twinforge` — nunca misture com mudança em `workflow.yaml` no mesmo
   commit se a motivação for diferente (facilita reverter uma sem a outra).

## Como configurar `workflow.yaml`

1. Decida se a mudança é de **política** (um valor em `policies:` — aprovar
   automaticamente ou não, limite de rodadas, lista de módulos sensíveis) ou
   de **forma do processo** (adicionar, remover ou reordenar uma fase). A
   primeira é comum e o Workflow Manager pode levar pra `ask_user_questions`
   sozinho quando for ambígua; a segunda é rara e deveria vir de uma decisão
   já discutida com o time + cliente (normalmente refletida primeiro na doc
   de apoio, depois aqui).
2. Ao mexer no grafo de fases (`phases:`), mantenha a convenção:
   `type: task` sempre tem `owner` + (`mode: automatic` ou `mode: manual`) +
   `next`; `type: choice` sempre tem `choices:` com `when` + `next`, e usa
   `gate: approval_required` + `approver` quando precisar pausar pra
   aprovação humana — isso sempre significa uma interaction
   `ask_user_questions` real pro `approver` + `status: in_review`, nunca um
   `status: blocked` (ver a convenção de status na skill
   `bunge-openspec-flow` — `blocked` é só pra dependência entre issues).
   Todo `id` de fase referenciado em algum `next` precisa existir — um
   `next` apontando pra um `id` inexistente quebra o fluxo silenciosamente,
   já que não há validador automático rodando sobre este arquivo hoje.
3. Valide que o YAML pelo menos faz parse antes de commitar:
   ```bash
   python3 -c "import yaml; yaml.safe_load(open('twinforge/workflow.yaml'))" && echo OK
   ```
4. Depois de mudar `policies:` ou `phases:`, atualize a doc de apoio se a
   mudança for visível pra quem só lê a narrativa (ex. mudou se tarefa
   simples precisa de aprovação) — o objetivo é a doc e o YAML nunca
   contarem histórias diferentes.
5. Commit só de `twinforge/workflow.yaml` (mais o README se a mudança afetar
   o que ele explica), mesma branch/PR scoped a `twinforge/` do padrão acima.

**Por que dois arquivos e não um só**: `workflow.yaml` é a forma do processo —
pensado pra ser copiado e reaproveitado em outro projeto Magento quase sem
alteração. `roles.yaml` é o que muda de verdade de projeto pra projeto (outro
time, outro Tech Lead, outro aprovador do lado do cliente). Separar os dois
significa que trocar de time não exige reler nem reescrever o fluxo inteiro.

## Documentação de apoio

`workflow.yaml` é a versão executável do fluxo; a visão narrativa — o porquê
de cada decisão, com diagramas — está na doc
["Fluxo TwinForge — Bunge"](https://claude.ai/code/artifact/{{uuid}}).
Quem está vendo isso pela primeira vez, comece pela doc; volte pra cá quando
precisar saber exatamente o que o agente vai fazer num caso específico.

## Setup inicial

1. **`roles.yaml` já está preenchido** com os ids reais desta company
   (Tech Lead, OpenSpec Manager, Backend, Frontend, Workflow Manager, e o
   Kaio como `client_approver`). Se algum desses agentes for substituído,
   é o Workflow Manager quem atualiza isso — ver "Quem mantém isso" acima.
2. **Revise `policies:` no topo de `workflow.yaml`** antes de considerar isso
   pronto pra rodar — são os únicos valores pensados pra mudar com frequência:
   - `intake.max_rounds` — quantas rodadas de pergunta ao cliente antes de
     escalar pro Tech Lead em vez de ficar perguntando pra sempre.
   - `complexity.sensitive_modules` — lista de módulos que, se tocados,
     forçam o caminho de aprovação completa mesmo numa tarefa pequena. Ajuste
     pra refletir onde um bug custa caro neste projeto (aqui: pricing, tax,
     pagamento, termos de pagamento).
   - `low_complexity_path` / `standard_path` — decisão de negócio que varia
     por cliente. Padrão decidido aqui: complexidade baixa desenvolve
     automático, sem pergunta (`requires_proposal_approval: false`);
     complexidade média/alta pergunta ao Kaio via `ask_user_questions` antes
     de liberar (`requires_proposal_approval: true`). Mudar isso pra outro
     cliente é só trocar esses booleans — discuta com o time + cliente antes.
   - `integrations.azure_devops.enabled` — `false` hoje porque a integração
     com Azure DevOps ainda não existe; a fase `ask_client` usa a issue do
     TwinForge como canal enquanto isso. Vira `true` quando a integração for
     plugada — nenhuma outra mudança neste arquivo é esperada nesse momento.
   - `concurrency.max_concurrent_runs_per_agent` — teto de execuções
     simultâneas por agente (hoje 5). Cada tarefa roda isolada num
     `git worktree` próprio (ver a convenção no topo de `workflow.yaml`),
     não mais no diretório compartilhado — por isso dá pra subir esse
     número com segurança. Ajuste pra cima/baixo conforme custo e
     capacidade real observados.
3. **A skill `bunge-openspec-flow` e o `AGENTS.md` do Tech Lead já apontam
   pra cá** (seção "twinforge/workflow.yaml governa as decisões de gate") em
   vez de repetir a regra de aprovação com palavras próprias — isso resolve
   a duplicação que existia antes (ver "Enforcement" abaixo).
4. **`openspec-propose` lê este arquivo no momento de montar o
   `executionPolicy` da issue de proposta** — é aqui que `policies.*` decide
   se nasce uma pergunta de aprovação real (`ask_user_questions` + `in_review`)
   pro Kaio ou não, não só uma expectativa escrita em algum lugar que um
   agente pode esquecer de checar.

## Enforcement: como isso vira comportamento real, não só texto

Uma config que só existe pra um agente "ler e seguir" é fraca — já tivemos
agente reportando tarefa como feita sem ter feito (ver memória do projeto,
BUN-127). Duas coisas tornam isso mais do que boa vontade:

1. **O gate vira mecanismo real da plataforma, não checagem manual.**
   `openspec-propose` (a skill que monta o `executionPolicy` da issue de
   proposta) lê `policies.standard_path`/`policies.low_complexity_path` e,
   se `requires_proposal_approval: true` (hoje: complexidade média/alta),
   cria uma interaction `ask_user_questions` de verdade pro Kaio pedindo
   permissão pra seguir, com a issue em `status: in_review` — nunca
   `blocked`, porque esse status aciona a notificação de demora do
   TwinForge pro PO, pensada pra dependência travada, não pra uma espera
   normal de aprovação (ver a convenção de status na skill
   `bunge-openspec-flow`). Complexidade baixa segue automática, sem
   pergunta nenhuma. Se um agente pular essa leitura, o sintoma aparece na
   própria issue (sem interaction nem `in_review` onde deveria ter),
   visível pra qualquer auditoria, não só se o agente admitir.
2. **Uma regra só é dita uma vez.** Antes desta rodada, a política de
   aprovação estava escrita com palavras próprias em três lugares
   (`bunge-openspec-flow`, `openspec-propose`, `AGENTS.md` do Tech Lead) —
   foi assim que a contradição sobre a aprovação do Kaio apareceu: um lugar
   dizia uma coisa, os outros diziam outra, sem ninguém ter mudado os três
   de propósito. Os três agora apontam pra `twinforge/workflow.yaml` em vez
   de reafirmar a regra — uma mudança de política muda um arquivo, não três.
   O Workflow Manager é o backstop pra quando isso falhar mesmo assim
   (audita o roster, recebe escalonamento do Tech Lead em caso de
   ambiguidade), não a primeira linha de defesa.

## Notificações (Slack)

Toda espera por humano que a plataforma deixa visível vira card no canal do Slack, e a pessoa
autorizada age direto do card. As regras estão em `workflow.yaml › policies.notifications` e são
**lidas** por `integrations/slack-bridge/` (não são instrução pra agente):

- **O que notifica:** gate `approval_required` (interaction pendente + `in_review`), pergunta ao
  `external:client` (`ask_client`) e `blocked` que não seja só "esperando filhas" — a convenção
  `in_review` vs `blocked` da skill `blocked-exige-blocker-declarado` continua valendo.
- **Quem age:** `who_may_act` (papéis humanos; o Slack id vem de `roles.yaml › slack_user_id`).
- **Lacuna declarada:** fases `owner: human:*` com `mode: manual` (`escalate_to_tech_lead`,
  `propose_human`, `architecture`) não deixam sinal detectável; estão em `not_covered`.
- **Sincronia garantida por teste:** `cd integrations/slack-bridge && npm test` falha se `covers` /
  `not_covered` divergirem das `phases`. Ao criar uma fase com gate ou owner `external`/`human`,
  atualize o bloco no mesmo PR.
- O PO ainda notifica diretamente os bloqueios que trata (Step 5 de `bunge-po-issue-recovery`);
  essa regra espelha `events.blocked*` e sai quando o bridge for o único emissor (fase 3 do plano).

## Como ler `workflow.yaml`

Cada fase é um nó de um tipo:

- **`type: task`** — alguém faz um trabalho e segue pra próxima fase (`next`).
  `owner` diz quem: `agent:<papel>` (um agente, papel definido em
  `roles.yaml`), `human:<papel>` (uma pessoa faz manualmente — normalmente
  com `tooling: claude_code`), ou `external:<papel>` (alguém fora da empresa,
  tipo o cliente respondendo uma pergunta).
- **`type: choice`** — nó de decisão, sem trabalho novo: avalia `choices` em
  ordem e segue a primeira cujo `when` for verdadeiro. Uma entrada de
  `choices` pode ter `gate: approval_required` — isso pausa o fluxo: o
  agente cria uma interaction `ask_user_questions` pedindo permissão ao
  `approver`, e a issue fica `status: in_review` até ele responder (nunca
  `blocked` — ver convenção de status abaixo).

Um `when` referencia um sinal calculado por uma fase anterior (ex.
`info_complete`, `low_complexity`) ou um caminho dentro de `policies`
(ex. `policies.standard_path.requires_proposal_approval`) — é assim que o
mesmo grafo de fases se comporta diferente por cliente sem precisar duplicar
fases.

### Convenção de status: `in_review` vs. `blocked`

Fonte de verdade: skill `blocked-exige-blocker-declarado` (anexada a todo
agente deste pod) — payload exato das interactions e quando `blocked` é
mesmo o caso certo. Resumo: esperando um humano responder algo → `in_review`
+ interaction real; esperando outra issue fechar (dependência estrutural)
→ `blocked` + `blockedByIssueIds`. Por isso todo `gate: approval_required`
deste arquivo usa `in_review`, nunca `blocked`.

## Reaproveitando isso em outro projeto

`workflow.yaml` não é exclusivo de e-commerce Magento nem da Bunge — o
vocabulário (`task`/`choice`/`owner`/`gate`/`policies`) foi pensado pra
qualquer fluxo com agentes + humano no loop em pontos específicos. Ao copiar
para outro projeto:

- O número de fases pode mudar bastante — um time pode ter menos gates, outro
  pode inserir uma etapa de fluxo externo do cliente no meio (ex. um sistema
  de aprovação próprio do cliente, não só Azure DevOps).
- Fique à vontade pra segregar em mais arquivos se `workflow.yaml` crescer
  demais pra um fluxo mais complexo (ex. `policies.yaml` separado se as
  políticas ficarem extensas o suficiente pra atrapalhar a leitura do grafo
  de fases).
- `roles.yaml` é o único arquivo que deveria precisar de edição ao trocar de
  time, assumindo que o vocabulário de papéis (`tech_lead`, `dev`,
  `client_approver`, etc.) ainda faz sentido pro novo contexto — se não
  fizer, isso é sinal de que `workflow.yaml` também precisa de ajuste, não só
  os ids.

## `toolkit.yaml`

Lido pelo Agentic Toolkit (funcionalidade Twinforge), não pelos agentes: limites e regras
desligadas das regras de saúde que transformam o quadro da instância em problemas com correção
sugerida. Tabela das regras e de onde cada uma veio em `context/agentic-toolkit.md`.
