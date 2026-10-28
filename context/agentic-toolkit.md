# Agentic Toolkit × TwinForge — a interface de operação da instância

O Agentic Toolkit (app local, repositório próprio) tem a funcionalidade **Twinforge**
(menu Desenvolvimento): mostra a instância deste projeto como um quadro e permite gerenciá-la
pela tela, pelo assistente do app ou por um Claude conectado ao MCP do app. Este documento diz
o que o app lê **daqui**, como as regras de saúde se ligam às lições do fluxo e como operar.
Sem dados de instância (URL, ids de issue, tokens): esses ficam na configuração do app, no
diretório de dados dele, por workspace.

## O que o app lê deste repositório

O app só **lê** este clone (o "repositório de configuração" escolhido em Twinforge →
Configuração); mudanças aqui continuam entrando por PR e indo à instância por `/tfrepo:push`.

| Arquivo | Para quê |
|---|---|
| `twinforge/roles.yaml` | Quem é o Tech Lead (cards novas e a correção "atribuir ao Tech Lead" vão para ele), quem são os devs e o OpenSpec Manager, `scope.project_id` (só as cards desse projeto entram no quadro) e `scope.repo` (para achar este repo a partir do workspace) |
| `twinforge/toolkit.yaml` | Limites das regras de saúde e regras desligadas |
| `agents/*/agent.yaml` | Aba Repositório: modelo, adapter e `desired_skills` de cada agente comparados com a instância (só relatório, como `/tfrepo:status`) |
| `skills/`, `context/*.md` | Contagem de skills; `twinforge-context.md` e `licoes-fluxo-openspec.md` entram no material que o Claude lê em "Analisar com o Claude" |
| git do clone | Branch, commits recentes e, na Atualização do dia, os commits novos (`git fetch` + `git log` do `origin/HEAD`) — o que foi implementado nos agentes e skills |

## Regras de saúde (o que vira "problema" no quadro)

Funções puras sobre o que a instância devolve; as mesmas no quadro, no MCP, no monitor e nos
testes do app. Cada uma é uma lição já registrada.

| Regra | Nível | Lição de origem | Correção segura (botão / piloto) |
|---|---|---|---|
| `pai-errado` | crítico | Subtarefas de dev criadas sob a proposta OpenSpec, não sob a card | Mover para a card (lê de volta e comenta o motivo) |
| `bloqueada-sem-bloqueador` | crítico | Card `blocked` sem `blockedByIssueIds` ficou travada | Retomar pedindo o bloqueador ou a continuação |
| `run-falhou` | crítico | Último heartbeat da tarefa terminou com erro | Retomar uma vez |
| `agente-com-problema` | crítico | Responsável pausado ou em erro | Reativar / acordar o agente |
| `parada` | atenção | `in_progress` sem run; comentário comum não acorda ninguém | Retomar (comentário com resume) |
| `card-esquecida` | atenção | Subtarefas fechadas e card aberta | Pedir conferência da entrega e fechamento |
| `pergunta-pendente` | atenção | Interaction esperando pessoa (perguntas, confirmação) | Responder na tela da tarefa |
| `bloqueador-parado` | atenção | O bloqueador da fila está parado | — |
| `dev-concorrente` | atenção | Backend e Frontend rodaram ao mesmo tempo na mesma branch | — |
| `atribuicao-direta` | atenção | Card de pessoa atribuída direto a não-Tech Lead sem `Atribuição direta:` | Atribuir ao Tech Lead |
| `sem-responsavel` | atenção | Tarefa aberta sem agente nem pessoa | Atribuir ao Tech Lead |
| `filhas-abertas` | atenção | Card `done` com subtarefa aberta | — |
| `recuperacao` | info | A plataforma abriu ação de recuperação | — |

Limites (`stalled_minutes`, `forgotten_minutes`), marcadores de título e regras desligadas:
`twinforge/toolkit.yaml`. Uma lição nova que vira regra: implementar no app (`health.ts`, com
teste) e acrescentar a linha aqui.

## Aguardando você (perguntas dos agentes respondidas no app)

Quando um agente pergunta, a issue vai para `in_review` com uma interaction `pending`
(`ask_user_questions`, `request_confirmation`, `suggest_tasks`); aprovações pedidas por agentes
ficam pendentes em `approvals`. A aba "Aguardando você" junta tudo isso (mais as issues em
revisão sem pergunta aberta), com o último comentário do agente como contexto, e responde por
lá. As mesmas regras que o slack-bridge segue, porque valem na plataforma:

- **Decidir antes de comentar**: um comentário de pessoa supersede a interação pendente
  (`supersedeOnUserComment`); o comentário opcional vai depois da resposta.
- **Conferir que ainda está pendente** antes de agir (a mesma pergunta pode ter sido respondida
  na instância ou no Slack).
- Responder já acorda o agente (`continuationPolicy`); não precisa retomar.

Pergunta nova vira contador no menu, faixa no Painel, aviso em qualquer tela do app e, se
permitido, notificação do navegador. Isso não substitui o Slack: os dois leem a mesma fila, e o
que for respondido num some do outro.

## Engenharia de contexto e de prompt (telas)

As abas "Eng. de contexto" e "Eng. de prompt" desenham como os princípios deste repositório
funcionam nos agentes. Sem IA, leem as tabelas de `context/engenharia-de-contexto-e-prompt.md`
(princípios C e P, plataforma verificada, calibração, registro de validação, considerado e não
aplicado) e de `context/engenharia-de-prompt-aplicada.md` (mapa dos textos, técnicas); por isso
**mantenha o formato dessas tabelas** (cabeçalhos `## …`, primeira coluna com o id `C1`/`P1`,
status por extenso). Medem na instância o tamanho do system prompt e da issue no prompt, os
tokens e as sessões por agente (`promptMetrics` e `usageJson` das últimas execuções), e mostram
prompts reais. "Analisar com o Claude" lê os `AGENTS.md`, as skills e o `workflow.yaml` e
devolve fluxo, camadas, achados e o que cada agente recebe; o resultado fica no app, não aqui —
achado que vire mudança entra neste repo por PR, com a linha no registro de validação.

## Monitor e piloto automático

- O **monitor** confere o quadro no intervalo configurado (padrão 5 min) enquanto o app está
  aberto, guarda os problemas e desde quando, e alimenta o número no menu e a Atualização do dia.
- O **piloto automático** vem desligado. Ligado, aplica só as correções seguras das regras
  escolhidas (`pai-errado`, `parada`, `card-esquecida`, `run-falhou`), no máximo uma vez a cada
  6 h por problema, e cada ação fica num comentário na issue e na auditoria do app. Segue o
  princípio deste repo: em rodada de teste ele **não** substitui corrigir a skill ou o
  `AGENTS.md` que causou o problema; serve para não deixar a fila parada enquanto isso.

## Integração com as outras funcionalidades do app

Na tela de uma tarefa, "No Agentic Toolkit" junta o que os agentes produziram:

- **Branches**: as citadas nos comentários e `feature/<id>` de cada issue da árvore, buscadas no
  repositório do workspace só como `origin/<branch>` (nunca checkout nem push).
- **OpenSpec**: as mudanças de cada branch abrem na tela do OpenSpec, lidas do git, somente
  leitura (`?ref=origin/<branch>`).
- **Código**: o PR do repositório abre na revisão de PR do Git do app.
- **Diagramas**: "Desenhar a solução" pede ao Claude o diagrama C4 da tarefa, ligado a ela.
- **Pergunte ao código**: citar o identificador de uma issue na pergunta junta a issue (árvore,
  problemas, comentários) ao código.
- **Refinamento**: um card refinado vai para o Tech Lead por "Desenvolver no TwinForge" (com
  negócio, solução técnica, plano, critérios e pontos de atenção na descrição, que a curadoria
  guarda como `original`); uma issue da instância pode ser trazida para refinar localmente.

## Claude com o TwinForge (MCP)

O MCP do app tem dois grupos: **twinforge** (consulta: o que espera resposta, painel, quadro, problemas, tarefa,
integrações, atividade, arquivos dos workspaces, comandos de leitura do `twinforgecli`,
repositório × instância) e **twinforge-gestao** (comentar, retomar, alterar status/responsável/card
pai, corrigir, responder aos agentes e decidir aprovações, acordar/pausar agentes, criar card, mandar card refinado,
buscar branches, analisar com o Claude, atualizar o CLI), este desligado por padrão para clientes
externos. A ferramenta `twinforge_guia` ensina o fluxo e as armadilhas antes de qualquer ação.

`.mcp.json` na raiz deste repo conecta o Claude Code daqui ao MCP do app
(`http://127.0.0.1:4310/api/mcp`). Para usar: ligar o MCP em Configurações → MCP do app, copiar
o token e exportar `AGENTIC_MCP_TOKEN` antes de abrir o Claude Code. Sem o app aberto ou sem o
token, o servidor só aparece como indisponível. Os slash commands `/twinforge:*` continuam sendo
o caminho para sincronizar este repo com a instância.

## Garantias (do lado do app)

- Login: o do `twinforgecli` desta máquina, lido só no servidor do app e enviado só à instância
  configurada; nunca vai para a tela, para o MCP ou para o disco do app. A configuração do
  adapter dos agentes (env, secrets) não sai do servidor.
- `twinforgecli` roda só pelo `CommandRunner` do app: comandos de leitura do catálogo;
  nunca o binário sem subcomando, login, `sync`, nem escrita pelo CLI. Escritas na instância
  são chamadas próprias da API, confirmadas na tela e auditadas; depois de alterar, o app lê a
  issue de novo e diz o que a instância não aplicou.
- Arquivos dos workspaces da instância: listados com a data da leitura; arquivos de credenciais
  (`auth.json`, `env.php`, `.env`, chaves) aparecem na lista mas nunca são lidos.
- O `twinforgecli` é atualizado ao subir o app e na Atualização do dia (`npm install -g` da versão
  publicada, quando mais nova), com histórico na aba Comandos.
