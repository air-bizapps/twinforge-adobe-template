# Lições do fluxo de agentes (OpenSpec) — sem dados de instância

Destilado de rodadas de teste do fluxo de desenvolvimento orquestrado por agentes
TwinForge num projeto Magento. Sem ids, números de issue/PR ou caminhos de servidor: o
que importa é o **padrão de falha** e **onde corrigir**. A fonte de verdade das regras
atuais são as skills em `skills/` e os `AGENTS.md` em `agents/`; use isto pra entender
*por que* elas dizem o que dizem e pra reconhecer o mesmo problema quando reaparecer.

## Princípio de trabalho

Em rodada de teste, **corrija o agente (skill/instrução), não a tarefa em andamento**. O
objetivo é medir o fluxo e eliminar a causa. Cada falha observada vira um ajuste de
skill ou de `AGENTS.md`, nunca só um conserto pontual do resultado.

## Padrões de falha e onde corrigir

**Fidelidade à spec e aos artefatos**
- Dev seguiu a skill em vez da spec da change → a spec prevalece; divergência vira lacuna
  reportada, não decisão do dev (`openspec-apply-change`, skills de scaffolding).
- Dev editou artefato de planejamento (ex.: reescreveu algo no `tasks.md`) → dev só marca
  `[x]`; quem edita artefato é o OpenSpec Manager.
- Atualização da change apagou o cabeçalho de metadados (change/tarefas/branch/artefatos)
  da descrição da subtarefa → `openspec-update-change` deve preservar o cabeçalho.
- Classe/FQCN inexistente passou por reviews → reviewer e update conferem todo FQCN no
  vendor antes de citar ou commitar.
- Archive "confirmou" conformidade por amostragem → archive confere **todos** os cenários.

**Relatório de entrega**
- Exigir `arquivo:linha` do código que faz o THEN de cada cenário; não aceitar "provado"
  só por declaração (ex.: ACL no `acl.xml` em vez do `ADMIN_RESOURCE` do controller).
- Só listar em "Cenários atendidos" o que *esta* subtarefa implementou; o resto vai em
  "Cenários pendentes (tarefa X)".
- Testes só de estrutura (arquivo existe, XML tem atributo) não provam comportamento.
- **Relatórios fabricados** (commit citado que nunca existiu, "validate OK" sem rodar):
  ocorreu mais de uma vez, em Backend e Tech Lead. Mitigações: exigir no relatório a saída
  real de `git log --oneline -1`; o Tech Lead confere a **existência** do commit antes de
  todo push (isso não é reverificar qualidade/escopo, e continua indispensável).

**Git, branch e workspace**
- Commit na branch errada (workspace compartilhado entre subtarefas, branch já checked-out
  de outra rodada) → no início de **toda** subtarefa: `git branch --show-current`; se não
  bater com a branch do cabeçalho, `git checkout` (criando da base certa se não existir) e
  nunca confiar no que já está checked-out. Relatório dizendo "Branch: X" não prova que X existe.
- Existiam dois checkouts do repo (clone gerenciado pela plataforma × diretório real onde
  rodam banco/PHP). Causa: project-workspace sem `cwd`. Corrigir na **configuração do
  workspace**, não só em instrução de agente.
- Leitura relativa de arquivo de contexto (`./CONTEXT.md`) dá ENOENT conforme a branch
  checked-out; só o `AGENTS.md` é injetado automaticamente pela plataforma — o resto do
  bundle de instruções **não** é materializado no workspace. Todo conteúdo essencial deve
  estar no `AGENTS.md` (ou numa skill); fallback para arquivo do repo: `git show origin/<base>:<arquivo>`.

**Orquestração (Tech Lead / filas)**
- A plataforma não acorda dependentes em `backlog` (só em estados acordáveis): enfileirar
  subtarefas com blocker encadeado em `blocked`, não em `backlog`.
- Tech Lead fechou a própria card de coordenação como `done` com subtarefas ainda por
  atribuir → ao delegar e ainda houver mais pra atribuir, terminar o heartbeat com
  `blocked` na própria card, nunca `done`; usar `blockedByIssueIds` reais em vez de
  depender da memória do agente entre heartbeats.
- Uma subtarefa de dev por vez (Magento compartilha VM/branch): exclusividade de execução.
- Tarefa criada por pessoa e atribuída direto a um dev (ou a outro agente) pula curadoria e
  plano e bagunça o fluxo → checagem de origem no início do `AGENTS.md` de quem executa;
  sem card pai, sem criador agente e sem `Atribuição direta:`, volta ao Tech Lead.
- Card escrita à mão mistura pedido, histórico e opinião, e o refinamento herda a confusão →
  curadoria antes do refinamento (`bunge-card-curation`), original preservado em documento.
- Card `blocked` "esperando as subtarefas" sem `blockedByIssueIds` nunca acorda o Tech Lead;
  subtarefa atribuída mas deixada em `backlog` nunca roda. Atribuir = assignee + `todo`; a
  card fica bloqueada pela subtarefa atribuída no momento.
- Tech Lead perguntou ao humano e seguiu planejando com uma resposta "default"; a resposta
  real veio contrária e as subtarefas já escritas não foram corrigidas → gate de DoR
  termina o heartbeat, e a resposta entra na descrição antes de qualquer outro passo.
- Subtarefas `[BACK]`/`[FRONT]` criadas como filhas da issue de proposta em vez da card: dentro
  da proposta, "a card pai" escrito em texto virou "a issue atual" → o `openspec-propose` tira o
  id da card do `parentId` da proposta por comando e confere depois de criar; o Tech Lead move
  a que achar fora do lugar antes de atribuir.
- Entrega "done" com gate aprovado que nem carregava (XML inválido, classe e interface
  inexistentes, identificador de página CMS errado): o gate só conferia a existência do
  commit → checagem de integridade por script no relatório e de novo no gate.
- Regra certa numa skill que o dev não abriu (endpoints `anonymous`) → a `bunge-*-code-quality`
  da especialidade é lida antes do primeiro commit, sem depender da linha Skills.
- Decisão "módulo isolado, caminho comum" para uma demanda que mexe em cadastro, e-mail, loja e
  backoffice → gatilhos verificáveis de OpenSpec, contando o que a demanda muda.
- Push feito enquanto um pedido humano de "segurar" chegava no meio do run, e branch com
  commits de card cancelada → releitura dos comentários e conferência dos commits contra o
  `estado` antes de qualquer ação irreversível; branch sempre a partir de `origin/<base>`.
- Exemplo canônico de skill que viola a própria regra (ID externo que o texto não tinha)
  é copiado pelo agente → todo exemplo passa pelo mesmo checklist que ensina.
- Regra escrita só em `HEARTBEAT.md`/`SOUL.md` nunca chegou ao agente (`pi_local` só injeta o
  `AGENTS.md`) → esses arquivos são ponteiros.
- Pergunta ao usuário (`ask_user_questions`) criada na card pai acorda o agente errado:
  criar na issue de proposta, com passo de retomada e checklist de Definition of Ready.
- Stage `approval` de usuário não tem botão na UI; só `PATCH` de status+comentário na mesma
  requisição (via CLI). Avaliar `request_confirmation` ou reportar como bug da plataforma.
- Card com duas propostas OpenSpec concorrentes (uma apagou a outra no PR, e as subtarefas ativas
  ficaram apontando para a apagada): o comentário do Tech Lead na card `blocked` citava o OpenSpec
  Manager pelo nome, e em issue `blocked` a plataforma acorda o agente nomeado nela, mesmo sem
  @menção; o prazo de alerta de bloqueio repete isso. → Em issue bloqueada, citar a issue, nunca o
  nome do agente (`agents/tech_lead/AGENTS.md`).
- Proposta parada em `in_review` sem revisor acordado: o `executionPolicy` foi gravado depois de
  a issue já estar em `in_review`, e a plataforma só inicia o stage num `PATCH` que traz
  `status: in_review`. → Política e status no mesmo `PATCH`; corrigir reenviando o status
  (`openspec-propose` passo 12).
- Run que esgota orçamento antes do commit fica `blocked` e retoma sozinha: avaliar
  orçamento por subtarefa. (Ver também o gotcha de `budgetMonthlyCents=0`.)

**Qualidade técnica Magento (UI Component / REST)**
- XSD "permissivo" e checklist só de "XML bem-formado" não seguram: validar contra o XSD
  real (`tools/uicomponent/validate.php`) pega erros de schema, mas **não** pega: layout
  `admin-2columns-left` em listagem simples (coluna `left` em branco), DataProvider de form
  errado, `post: true` ausente na ação de excluir, normalização de data × `dateFormat`,
  argumento obrigatório faltando no `virtualType`. Só abrir a página (ou instanciar de
  verdade via ObjectManager) pega. As skills `adobe-magento-ui-component-developer` e
  `adobe-magento-uicomponent-debug` carregam essas regras — mantenha-as atualizadas.
- Regras de skill não podem se contradir: "nunca escreva DataProvider customizado" vale pra
  **grid**; **form** usa DataProvider próprio (`AbstractDataProvider`/`ModifierPoolDataProvider`),
  como a própria referência do core faz.
- Componente JS de campo deve herdar de `Magento_Ui/js/form/element/*`, não de
  `uiComponent`, e não declarar `elementTmpl` inventado.
- REST: `setData()` substitui o array inteiro (use `addData()` pra merge em update parcial);
  `getList()` deve devolver `SearchResultsInterface` (não `Search\SearchResultInterface`)
  pra trazer os data objects completos; `create()` pode devolver objeto em memória sem
  campos preenchidos pelo banco. Reviews que não rodam a rota real não pegam isso.
- Escopo de testes unitários (`adobe-create-unit-tests`): testar Plugin, Observer,
  ViewModel, classe sem `extends` e classe criada por override/preference; **não** testar
  Controller nem Model que estende `AbstractModel`/`AbstractExtensibleModel`. Respeitar
  "sem teste unitário" quando a card disser isso.

## Hábitos de governança (de quem mantém os agentes)

Princípios de contexto e de prompt que as instruções e o contrato das subtarefas seguem, com o
que já foi validado: `context/engenharia-de-contexto-e-prompt.md`. Mudança que contrarie um
deles precisa dizer por quê.

1. **Antes de mudar** `AGENTS.md`, skills ou `desiredSkills` de um agente: passar pelo
   checklist de revisão de criação de agente (menor privilégio; `desiredSkills` só lista
   skill que existe/será instalada). Pega a maioria dos erros reais (ex.: skill de
   planejamento anexada ao agente errado).
2. **Periodicamente** (a cada ~10 issues fechadas do agente, ou fim de fase de arquitetura):
   rodar a skill `reflection-coach` com `targetAgentId` = agente a calibrar (nunca o próprio).
   Regras: cluster de falha precisa de ≥2 evidências (id + citação literal) ou é descartado;
   propostas têm teto de tamanho; **descoberta e aplicação são runs separadas** — publica
   proposta + `request_confirmation`, aplica só depois de aceita. Janela pequena = pode
   voltar sem proposta, e tudo bem.
3. Mudança direta feita pelo dono da instância supera a convenção interna, mas deixe um
   comentário na issue relacionada pra o Tech Lead ter visibilidade (trilha de auditoria).

## Convenções de branch/PR/tarefa (resumo)

Branch `feature/BUN-<n>` (n = id da issue TwinForge) **com checagem de colisão** antes de
criar (se já existir no remoto e não for desta issue, parar e escalar); título de PR e de
tarefa `BUN-<n> - <título>` (sem colchetes); descrição com 7 seções (macro, funcional,
solução técnica, pontos de atenção, critérios de aceite, como testar, riscos e rollback).
A cópia autoritativa fica na skill `bunge-engineering-practices`. Ao criar subtarefa que
precisa ser renomeada, renomeie no mesmo instante: outro agente pode dar checkout antes.

## PO e recuperação de issues (limites rígidos)

O PO pode destravar issues paradas, mas **só** mexe em metadados (status, assignee,
comentário), nunca em código/git, escopo, critérios de aceite, quebra de tarefas ou
prioridade (isso é do Tech Lead), nunca reatribui entre especialidades, só promove trabalho
órfão se **nenhum** dev estiver `in_progress`, e só resolve recovery como `restored` depois
de verificar concretamente o ambiente. Detalhes: skill `bunge-po-issue-recovery`.
