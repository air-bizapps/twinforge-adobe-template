> **Template.** Contém referências ao projeto de origem em várias seções; veja [`TEMPLATE.md`](./TEMPLATE.md) e adapte antes de operar.

# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

Repo `twinforge-adobe-template`: configuração e skills da TwinForge pro projeto **Bunge Magento 2**
(código da aplicação em outro repo, `Bunge-Magento2`). Aqui **não há código de aplicação**: o
conteúdo é Markdown/YAML que descreve agentes, skills e fluxo, e que é sincronizado com uma
instância remota da TwinForge via `twinforgecli`. A única exceção é `integrations/slack-bridge/`
(serviço Node pequeno; testes com `cd integrations/slack-bridge && npm test`). Idioma dos docs e
da conversa: **português (pt-BR)**.

## Leia primeiro

1. `context/twinforge-context.md` — como a plataforma se comporta, **quais dados são
   necessários pra operar a instância remota** e o que fazer quando faltam. Obrigatório antes
   de qualquer comando `twinforgecli`.
2. `context/licoes-fluxo-openspec.md` — padrões de falha dos agentes já observados e onde
   cada um se corrige; leia antes de mexer em skill/`AGENTS.md`.
3. `context/engenharia-de-contexto-e-prompt.md` — princípios de contexto e de prompt aplicados
   nos agentes, o que já foi validado na instância, o que ficou de fora e como evoluir; leia
   antes de mudar `AGENTS.md`, a curadoria da card ou o contrato das subtarefas. A visão
   prática (que texto vira prompt e onde vê-lo na UI): `context/engenharia-de-prompt-aplicada.md`.
4. `skills/README.md` e `agents/README.md` — escopo, prefixos, compatibilidade e pendências.
5. `context/agentic-toolkit.md` — a interface de operação da instância (app Agentic Toolkit →
   Twinforge): o que ela lê daqui, as regras de saúde (`twinforge/toolkit.yaml`), o MCP
   (`.mcp.json`) e as garantias. Mudou uma lição que vira regra de saúde? Atualize os dois.

## Mapa do repo

| Caminho | O que é |
|---|---|
| `skills/<slug>/` | Espelho das skills ativas da company (`SKILL.md` + `references/`/`scripts/` só se existirem em produção). Prefixos: `bunge-` (específico do projeto), `adobe-` (Magento genérico), sem prefixo (processo/plataforma) |
| `agents/<role_key>/` | `agent.yaml` portável (`{{company_id}}` é placeholder; `reports_to` é role key) + `AGENTS.md`/`CONTEXT.md`/… exatamente como em produção |
| `twinforge/` | `workflow.yaml` (fases, aprovação, worktree), `roles.yaml` (papel → id real), `toolkit.yaml` (regras de saúde lidas pelo Agentic Toolkit), README |
| `.mcp.json` | Conecta o Claude Code deste repo ao MCP do Agentic Toolkit (ferramentas `twinforge_*`); token por `AGENTIC_MCP_TOKEN`, nunca no arquivo |
| `docs/` | Regras de negócio do Bunge (fonte única; o `docs/` do repo de código foi descontinuado) |
| `commands/` | Slash commands `/twinforge*` (carregados via `.claude/commands`, que é symlink pra cá) |
| `context/` | Contexto operacional, lições e planos (ex.: `plano-notificacoes-slack.md`), **sem dados de instância** |
| `knowledge/` | Defeitos reais dos agentes e casos de avaliação do verificador (`knowledge/README.md`); vira regra do `magento-verify`, não prosa para os agentes |
| `integrations/local-verify/` | Ambiente local de verificação sem Docker (clone + cópia do banco + `php -S`) e `page-health.mjs` (Playwright): prova código Magento no laptop |
| `integrations/slack-bridge/` | Sensor determinístico que espelha bloqueios/decisões humanas no Slack e executa ações de humanos autorizados (README próprio; os agentes não são alterados) |

## Slash commands

`/twinforge "<pedido>"`, `:status`, `:pull`, `:push`, `:agent`, `:local-sync`, `:init`, `:help`
(ver `/tfrepo:help`). Dentro deste repo, `twinforge-repo/` nos comandos significa a raiz
deste repo (`./`). Fluxo seguro: `:status` → `:pull` (nuvem vence) ou `:push` (escreve em
produção; confirmar com o usuário antes).

## Falar com a instância remota

Nenhum dado de instância é versionado (URL, login, tokens). Ids de company/projeto/agentes
estão em `twinforge/roles.yaml` e `agents/*/agent.yaml`. Procedimento:

1. `which twinforgecli`; `twinforgecli context show` (perfil vazio cai em `localhost:3100` — quase
   sempre errado).
2. URL da API ausente → **peça ao usuário** e configure com `twinforgecli context set --api-base <url>`.
3. Sem login → o usuário roda `twinforgecli connect --persona board` no terminal dele (você não
   consegue). Teste com `twinforgecli company list --json`.
4. Faltou qualquer outro dado da tabela de `context/twinforge-context.md` → pare, diga qual dado,
   pra que serve e como o usuário obtém; espere. Nunca invente ou reaproveite ids.
5. Preferências pessoais da máquina (URL, nome da company, etc.) podem ir em `CLAUDE.local.md`
   (gitignored) — ofereça criar em vez de pedir de novo a cada sessão. Nunca grave token.

## Regras de trabalho

- **Nunca** rode o binário `twinforge` sem subcomando (sobe servidor local completo com Postgres
  embutido); `agent/skill delete` e tudo sob `sync` exigem confirmação explícita.
- A instância remota é a fonte de verdade do conteúdo vivo. Antes de editar skill/agente,
  compare com o estado ao vivo (`/tfrepo:status`).
- Regra pros agentes de dev tem que chegar por **skill** ou `AGENTS.md`, nunca só por `CLAUDE.md`
  (adapters `crucible_local`/`pi_local` não leem `CLAUDE.md`).
- Em rodada de teste de fluxo, corrija **agentes/skills**, não a tarefa em andamento.
- **Nunca confie em relatório "done" de agente** sem verificar timestamp + conteúdo real.
- Antes de mudar `AGENTS.md`/skills/`desiredSkills` de um agente, passe pelo checklist de
  governança (`context/licoes-fluxo-openspec.md`). `skills agent sync` substitui a lista inteira.
- Dados de instância (URLs, ids de issue, hosts, caminhos de servidor, tokens) **não** entram
  em `context/` nem em commits novos de contexto; ids já existentes em `roles.yaml`/`agent.yaml`
  são parte da config e ficam.

## Manter repo e instância sincronizados

O objetivo deste repo é versionar a evolução dos agentes: toda mudança aplicada na
instância (instruções, skills, `desiredSkills`, `env`, permissões) tem que estar refletida
aqui no mesmo ciclo — e vice-versa. Depois de aplicar, rode `/tfrepo:status` e confirme
que está limpo. Atenção: o status **não compara `env`/ligações de secret**; confira à mão
com `twinforgecli agent get <id>` (`adapterConfig.env`) e `twinforgecli secrets usage <id>`.
Secrets nunca entram no repo, só a referência por chave em `agent.yaml` (`env`).

Regras de notificação/ações no Slack moram em `twinforge/workflow.yaml › policies.notifications` (fonte
única; o bridge as lê). Mexeu em fases com gate/owner `external`/`human` ou nessas regras → atualize o bloco
e rode `cd integrations/slack-bridge && npm test`: ele falha se divergirem.

## Git

Branches `feature/<assunto>` a partir de `main`; mudança entra por **PR pra `main`** (não
commitar direto na `main`). Commits em inglês, imperativo curto; PRs em pt-BR com seções
Resumo/Como testar quando fizer sentido. Nada de `--no-verify`/force-push sem pedido.

## Pendências conhecidas (verifique no estado ao vivo antes de agir)

- A renomeação das skills com prefixo **já foi aplicada** na instância (verificado em 2026-10-06).
- Instalador que materializa agentes numa company nova a partir deste repo, e templatizar os
  ids ainda embutidos no texto dos `.md`.
- 3 agentes fora do escopo atual (QA Criteria Validator, Reflection Coach, Summarizer): só usam
  skills de catálogo/github.
- Conectar este repo ao workspace dos agentes (clone em caminho fixo, referenciado por variável
  de ambiente nos `AGENTS.md`) — fio solto.
- Backlog de ajustes de skills/agentes do fluxo OpenSpec está em `context/licoes-fluxo-openspec.md`;
  parte já foi aplicada nas skills — confira o conteúdo atual antes de reaplicar.
- Documentação de regras de negócio: consolidar docs humanos (exportados pelo usuário, a
  instância do SharePoint não é acessível pelo Claude) com análise do código em `docs/`.
