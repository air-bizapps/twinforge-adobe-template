> **Template.** Este repositório é uma base a adaptar, não um padrão. Leia [`TEMPLATE.md`](./TEMPLATE.md) antes de usar: ele lista o que já foi neutralizado e o que ainda é específico do projeto de origem.

# twinforge-adobe-template

Fonte central de tudo que construímos na **instância TwinForge do Bunge**: agentes,
skills, fluxo de trabalho, regras de negócio, comandos e o conhecimento operacional
de como a plataforma se comporta. É a versão versionada, revisável por PR, do que
roda em produção na instância remota.

O repo fica **separado do código da aplicação** (`<org>/<repo-do-projeto>`) de
propósito: mudar uma skill ou a política do fluxo aqui não deve passar pela mesma
régua de revisão de uma mudança de código de produção.

## Objetivo

1. **Centralizar** o que existe na instância remota do Bunge (agentes, skills,
   instruções, fluxo, docs de domínio) num único lugar com histórico, revisão e
   rastreabilidade — em vez de viver só dentro da plataforma, editável sem trilha.
2. **Evoluir uma instância existente.** Mudanças em skills, instruções de agente e
   fluxo nascem aqui (branch → PR → `main`) e são aplicadas na instância com
   `twinforgecli` (`/tfrepo:push`); o caminho inverso (`/tfrepo:pull`) traz pra cá o
   que foi editado direto na nuvem. A instância remota continua sendo a fonte de verdade
   do conteúdo **vivo**; este repo é o espelho versionado e o lugar onde a evolução é
   proposta e revisada.
3. **Servir de base (fork/referência) para uma instância nova.** Quem for montar uma
   nova instância TwinForge para um projeto Magento não precisa começar do zero: parte
   de uma base já testada de agentes, skills e fluxo, e troca só o que é específico do
   cliente.
4. **Preservar o conhecimento operacional** (`context/`): como a plataforma e o
   `twinforgecli` se comportam, que dados são necessários pra operá-los, e quais
   falhas de agentes já aconteceram e onde se corrigem — de forma que uma sessão nova do
   Claude Code (ou uma pessoa nova) consiga continuar sem depender de ninguém lembrar.

## Como usar

### A) Evoluir a instância existente

1. Abra o Claude Code na raiz deste repo (o `CLAUDE.md` e os comandos `/twinforge*` já
   carregam sozinhos).
2. `/tfrepo:status` — compara este repo com a instância ao vivo (só leitura).
3. Altere skills/agentes numa branch `feature/<assunto>` e abra PR para `main`.
4. Depois do merge, `/tfrepo:push` aplica na instância (escreve em produção; confirme
   antes). Se alguém editou direto na nuvem, `/tfrepo:pull` traz de volta — a nuvem vence.
5. Na primeira vez, o Claude pede os dados que faltarem (URL da API, login, ids) e
   explica como obtê-los; nenhum dado de instância é versionado (ver `context/`).

### B) Começar uma instância nova a partir deste repo

Faça fork/clone e separe o que é **reaproveitável** do que é **específico do Bunge**:

| Reaproveitar como está | Adaptar | Substituir |
|---|---|---|
| Skills `adobe-*` (Magento 2 genérico), `openspec-*`, `engineering-memory`, `para-memory-files`, `react-native-standards` | Skills `bunge-*` (fluxo, convenções, qualidade de código): troque ids, nomes e convenções pelas do novo cliente | `docs/` (regras de negócio do Bunge) |
| Estrutura de `agents/<role_key>/` e os papéis (Tech Lead, OpenSpec Manager, Backend, Frontend, PO, DevOps, Concierge) | `agents/*/agent.yaml` (modelo, budget, skills) e o texto dos `AGENTS.md`/`CONTEXT.md` | `twinforge/roles.yaml` (ids reais da nova company/projeto/agentes) |
| `twinforge/workflow.yaml` (fases, aprovação por complexidade, worktree) e `commands/` | Escopo e aprovadores do fluxo | Qualquer id, URL ou nome de cliente que ainda esteja embutido no texto dos `.md` |
| `context/` (comportamento da plataforma e lições do fluxo) | — | — |

Passos: (1) `/tfrepo:init` cria a company, o PAT do GitHub, o projeto e o primeiro
agente numa instância nova; (2) atualize `roles.yaml` e os placeholders `{{company_id}}`;
(3) publique skills e agentes com `/tfrepo:push`; (4) leia `context/` antes de
operar — vários problemas reais já mapeados estão lá. **Ainda não existe um instalador
automático**: a materialização é guiada pelos comandos e feita à mão (ver "Estado atual").

## Princípios

- **Sem dados de instância em `context/`**: nada de URLs, tokens, ids de issue ou caminhos de
  servidor. Dados que o Claude precisa são pedidos ao usuário no momento do uso.
- **Skill é o canal de entrega** de regra pros agentes de dev; `CLAUDE.md` só alcança humanos
  e agentes `claude_local` (detalhes em `context/twinforge-context.md`).
- **Corrija o agente, não a tarefa**: falha de fluxo vira ajuste de skill/instrução.
- **Verifique antes de confiar** em relatório "done" de agente.
- Mudança entra por PR para `main`; nada de commit direto.

## O que tem aqui

| Diretório | O que é | Origem |
|---|---|---|
| [`twinforge/`](./twinforge) | Config do fluxo de desenvolvimento orquestrado pelos agentes TwinForge — `workflow.yaml` (fases, aprovação por complexidade, worktree), `roles.yaml` (papel → id real), `README.md` (setup e convenções) | Migrado de `Bunge-Magento2` (era `twinforge/` lá, agora vive só aqui) |
| [`skills/`](./skills) | 46 skills ativas da company (prefixo `bunge-`/`adobe-`/sem prefixo — ver `skills/README.md`), cada uma espelhando exatamente o `fileInventory` real da instância | Puxado da instância real via `twinforgecli skill file` — **não** é mais a cópia crua do `ftd-b2c-agent-skills`; essa foi a referência inicial, substituída pelo conteúdo real já adaptado que está em produção |
| [`agents/`](./agents) | Definição de 10 dos 13 agentes da company (e-commerce, mobile, e os transversais PO/Concierge/DevOps): `agent.yaml` portável (role, modelo, budget, skills) + `AGENTS.md`/`CONTEXT.md`/etc. exatamente como em produção | Puxado da instância real via `twinforgecli agent get` + `instructions-file:get` |
| [`commands/`](./commands) | Slash commands do Claude Code (`/twinforge`, `:status`, `:pull`, `:push`, `:agent`, `:local-sync`, `:init`, `:help`). Carregam automaticamente neste repo via `.claude/commands` (symlink); em outro projeto, copiar ou linkar em `.claude/commands/` | Criados localmente em `Bunge-Magento2/.claude/commands/`, agora versionados só aqui |
| [`context/`](./context) | Contexto operacional da TwinForge/`twinforgecli` (comportamento da plataforma, dados a pedir ao usuário, lições do fluxo OpenSpec). Sem dados de instância; ver também `CLAUDE.md` | Escrito a partir de sessões de operação da instância |
| [`integrations/`](./integrations) | `slack-bridge/`: sensor determinístico (sem LLM) que posta no Slack todo bloqueio e pedido de decisão humana de qualquer agente e executa as ações de quem estiver na allowlist. Plano em `context/plano-notificacoes-slack.md` | Escrito aqui; rodar exige host com `twinforgecli` autenticado |
| `twinforge/toolkit.yaml`, [`context/agentic-toolkit.md`](./context/agentic-toolkit.md), `.mcp.json` | Integração com o **Agentic Toolkit** (app local): a funcionalidade Twinforge mostra a instância como quadro de cards com subtarefas, fases e problemas, gerencia as tarefas e os agentes, monitora o quadro e expõe tudo a um Claude por MCP. Daqui ela lê papéis, regras de saúde, agentes e lições; o `.mcp.json` liga o Claude Code deste repo ao MCP do app | Escrito aqui; o app vive em repositório próprio |
| [`docs/`](./docs) | Documentação de regra de negócio do Bunge Magento 2 (cadastro, catálogo B2B, checkout, impostos, etc.) — fonte única; o `docs/` do `Bunge-Magento2` foi descontinuado de propósito, pra não ter duas cópias divergindo | Migrado de `Bunge-Magento2`; cada worktree de card ganha um symlink `docs → twinforge-repo/docs` no momento em que é criado (ver `bunge-engineering-practices`), então nenhuma skill/AGENTS.md precisou trocar a referência `docs/...` que já usava |

## Estado atual e pendências

Base já em pé: skills, agentes, fluxo, docs, comandos e contexto. Ainda não feito:

- Instalador que materializa agentes numa company nova a partir deste repo, e templatizar os
  ids ainda embutidos no texto dos `.md`.
- Aplicar na instância remota a renomeação das skills com prefixo (`adobe-*`/`bunge-*`), que
  hoje só existe aqui — ver `skills/README.md`.
- Os 3 agentes fora do escopo atual (QA Criteria Validator, Reflection Coach, Summarizer).
- Conectar este repo ao workspace dos agentes (veja a nota abaixo).

Lista completa e orientações de trabalho em [`CLAUDE.md`](./CLAUDE.md).

## Nota sobre `twinforge/`

Esse diretório só funciona de verdade quando um agente TwinForge consegue
acessá-lo junto do checkout do código (`Bunge-Magento2`) — isso ainda
precisa ser configurado na instância (clone deste repo num caminho fixo do
workspace, referenciado por variável de ambiente nos `AGENTS.md`). Esse
fio ainda está solto — não assuma que já está em vigor só porque o
arquivo existe aqui.
