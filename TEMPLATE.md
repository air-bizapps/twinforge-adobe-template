# TEMPLATE: como usar esta base

Este repositório é a **base a adaptar** para projetos TwinForge + Adobe Commerce (Magento 2). Ele **não é um padrão a copiar sem revisão**: nasceu da configuração do primeiro projeto configurado e herda a estrutura de agentes, skills, fluxo, comandos e integrações dele, com os dados de cliente neutralizados.

## O que vem aqui
| Parte | Onde |
|---|---|
| Pacote de skills `twinforge` (setup, instâncias, diagnóstico, linguagem natural, apply) | `.claude/skills/` e `commands/twinforge/` (`/twinforge:*`) |
| Comandos do repositório de configuração (`init`, `status`, `pull`, `push`, `agent`, `local-sync`, `help`) | `commands/tfrepo/` (`/tfrepo:*`) |
| Agentes, skills da plataforma, fluxo e papéis | `agents/`, `skills/`, `twinforge/` |
| Conhecimento operacional | `context/`, `knowledge/` |
| Integrações | `integrations/` |

Os comandos `/twinforge:*` e `/tfrepo:*` convivem de propósito: há sobreposição entre `/twinforge:setup` e `/tfrepo:init`, a ser resolvida depois.

## Como começar um projeto novo
1. Crie o repositório do projeto: `gh repo create <org>/<projeto>-twinforge --template air-bizapps/twinforge-adobe-template --private --clone`.
2. Abra o Claude Code na pasta e rode `/twinforge:setup` (instala/conecta o `twinforgecli`, faz o login, escolhe instância, company e projeto). Com mais de uma instância, escolha a do projeto e salve como padrão da pasta.
3. Adapte (veja o checklist abaixo) e só então rode `/tfrepo:status` e `/tfrepo:push`. O `push` é barrado enquanto `scope.project_id` for um placeholder.

## O que já foi neutralizado
- **IDs:** todo UUID virou placeholder (`{uuid}`; em `twinforge/roles.yaml`, `{<papel>_agent_id}` e `{project_id}`).
- **Repositório do projeto:** `<org>/<repo-do-projeto>`.
- **`docs/`:** a documentação de domínio do cliente de origem não foi trazida.

## Checklist de adaptação (o que AINDA é específico do projeto de origem)
O nome do cliente de origem aparece **582 vezes** nos arquivos abaixo. Isso não foi trocado em massa porque está em nomes de skills (`bunge-*`), variáveis (`BUNGE_*`), exemplos de código e instruções dos agentes, onde um renomeio cego quebraria referências cruzadas. Revise cada área:

| Área | Menções |
|---|---|
| `agents/tech_lead` | 34 |
| `agents/openspec_manager` | 27 |
| `agents/frontend_dev` | 24 |
| `skills/bunge-engineering-practices` | 24 |
| `integrations/local-verify` | 24 |
| `skills/bunge-subtask-contract` | 21 |
| `skills/adobe-magento-ui-component-developer` | 20 |
| `context/engenharia-de-contexto-e-prompt.md` | 20 |
| `agents/backend_dev` | 19 |
| `skills/bunge-backend-code-quality` | 17 |
| `twinforge/workflow.yaml` | 14 |
| `skills/bunge-mobile-flow` | 13 |
| `skills/adobe-magento-toolbox` | 13 |
| `agents/mobile_tech_lead` | 12 |
| `skills/openspec-propose` | 12 |
| `skills/bunge-openspec-flow` | 12 |
| `twinforge/README.md` | 12 |
| `skills/openspec-apply-change` | 11 |
| `skills/bunge-vague-demand-triage` | 11 |
| `README.md` | 10 |
| `skills/adobe-magento-css-specialist` | 9 |
| `skills/bunge-figma-layout-decomposition` | 9 |
| `agents/mobile_developer` | 8 |
| `skills/adobe-magento-luma-specialist` | 8 |
| `skills/adobe-magento-theme-developer` | 8 |
| `skills/bunge-po-issue-recovery` | 8 |
| `skills/adobe-magento-frontend-standards` | 7 |
| `skills/adobe-create-unit-tests` | 7 |
| `skills/bunge-link-verification` | 7 |
| `skills/bunge-domain-lookup` | 7 |
| `context/conector-delegacao-twinforge.md` | 7 |
| `agents/po` | 6 |
| `agents/workflow_manager` | 6 |
| `skills/adobe-magento-requirejs-specialist` | 6 |
| `context/engenharia-de-prompt-aplicada.md` | 6 |
| `skills/README.md` | 5 |
| `skills/adobe-magento-frontend-developer` | 5 |
| `skills/bunge-frontend-code-quality` | 5 |
| `context/roteiro-ativacao-verificador.md` | 5 |
| `skills/adobe-create-http-client` | 4 |
| `skills/openspec-update-change` | 4 |
| `skills/adobe-inject-custom-logger` | 4 |
| `skills/bunge-backlog-item` | 4 |
| `skills/adobe-magento-backend-standards` | 4 |
| `CLAUDE.md` | 4 |
| `context/licoes-fluxo-openspec.md` | 4 |
| `skills/adobe-magento-house-rules` | 3 |
| `skills/adobe-inject-validator` | 3 |
| `skills/react-native-standards` | 3 |
| `skills/adobe-srs` | 3 |
| `skills/adobe-create-import-entity` | 3 |
| `skills/bunge-card-curation` | 3 |
| `skills/adobe-inject-debug-logger` | 3 |
| `skills/adobe-run-unit-tests` | 3 |
| `skills/adobe-create-repository` | 3 |
| `skills/adobe-create-controller` | 3 |
| `integrations/slack-bridge` | 3 |
| `agents/README.md` | 2 |
| `skills/adobe-create-plugin` | 2 |
| `skills/adobe-create-queue` | 2 |
| `skills/adobe-magento-catalog-specialist` | 2 |
| `skills/openspec-explore` | 2 |
| `skills/adobe-magento-hyva-specialist` | 2 |
| `skills/openspec-archive-change` | 2 |
| `skills/adobe-commerce-standards` | 2 |
| `skills/adobe-create-cronjob` | 2 |
| `skills/adobe-magento-code-reviewer` | 2 |
| `skills/adobe-create-config` | 2 |
| `skills/openspec-review-proposal` | 2 |
| `skills/adobe-magento-feature-developer` | 2 |
| `knowledge/defects` | 2 |
| `agents/verifier` | 1 |
| `agents/concierge` | 1 |
| `skills/openspec-sync-specs` | 1 |
| `skills/engineering-memory` | 1 |
| `skills/adobe-magento-php-specialist` | 1 |
| `skills/adobe-magento-module-developer` | 1 |
| `skills/adobe-magento-order-specialist` | 1 |
| `skills/adobe-create-entity` | 1 |
| `skills/adobe-magento-api-developer` | 1 |
| `skills/adobe-magento-index-analyst` | 1 |
| `twinforge/roles.yaml` | 1 |
| `context/twinforge-context.md` | 1 |
| `knowledge/eval` | 1 |
| `knowledge/README.md` | 1 |

Ordem sugerida: (1) `twinforge/roles.yaml` e `workflow.yaml`; (2) `agents/*/agent.yaml` e `AGENTS.md`; (3) skills `bunge-*` (renomeie slug e referências juntos); (4) variáveis de ambiente `BUNGE_*`; (5) `context/` e `knowledge/`; (6) escreva o seu `docs/`.

## Manter a base atualizada
Melhorias da base chegam ao projeto por `git merge`/cherry-pick manual (um repositório criado de template não guarda vínculo com a origem). Mudanças genéricas devem voltar para este repositório por PR.
