# TEMPLATE: como usar esta base

Este repositório é a **base a adaptar** para projetos TwinForge + Adobe Commerce (Magento 2). Ele **não é um padrão a copiar sem revisão**: nasceu da configuração do primeiro projeto configurado e herda a estrutura de agentes, skills, fluxo, comandos e integrações dele, com os dados de cliente neutralizados.

## O que vem aqui
| Parte | Onde |
|---|---|
| Pacote de skills `twinforge` (setup, instâncias, diagnóstico, linguagem natural, apply) | `.claude/skills/` e `commands/twinforge/` (`/twinforge:*`) |
| Comandos do repositório de configuração (`init`, `status`, `pull`, `push`, `agent`, `local-sync`, `help`) | `commands/twinforge/repo/` (`/twinforge:repo:*`) |
| Agentes, skills da plataforma, fluxo e papéis | `agents/`, `skills/`, `twinforge/` |
| Conhecimento operacional | `context/`, `knowledge/` |
| Integrações | `integrations/` |

Os comandos `/twinforge:*` e `/twinforge:repo:*` convivem de propósito: há sobreposição entre `/twinforge:setup` e `/twinforge:repo:init`, a ser resolvida depois.

## Como começar um projeto novo
1. Crie o repositório do projeto: `gh repo create <org>/<projeto>-twinforge --template air-bizapps/twinforge-adobe-template --private --clone`.
2. Abra o Claude Code na pasta e rode `/twinforge:setup` (instala/conecta o `twinforgecli`, faz o login, escolhe instância, company e projeto). Com mais de uma instância, escolha a do projeto e salve como padrão da pasta.
3. Adapte (veja o checklist abaixo) e só então rode `/twinforge:repo:status` e `/twinforge:repo:push`. O `push` é barrado enquanto `scope.project_id` for um placeholder.

## O que já foi neutralizado
- **IDs:** todo UUID virou placeholder (`{uuid}`; em `twinforge/roles.yaml`, `{<papel>_agent_id}` e `{project_id}`).
- **Repositório do projeto:** `<org>/<repo-do-projeto>`.
- **`docs/`:** a documentação de domínio do cliente de origem não foi trazida.

## Skills do projeto de origem: o que já foi feito
- **Renomeadas para `projeto-*`** (fluxo e prática do projeto; adapte o conteúdo ao seu): `projeto-openspec-flow`, `projeto-subtask-contract`, `projeto-vague-demand-triage`, `projeto-engineering-practices`, `projeto-domain-lookup`, `projeto-mobile-flow`.
- **Genéricas, sem prefixo** (processo TwinForge, reescritas sem fatos do projeto): `link-verification`, `figma-layout-decomposition`, `backlog-item`, `card-curation`, `po-issue-recovery`.
- **Ainda `bunge-*`**: `bunge-backend-code-quality` e `bunge-frontend-code-quality`. São camadas locais sobre `adobe-*-standards`: comparar com `adobe-commerce-standards`, mover só o que faltar e renomear o resto.
- **Removida do template:** `adobe-magento-house-rules` (notas pessoais de ajuste de um agente, não são base).
- **Atenção:** o prefixo `adobe-*` não garante neutralidade; **35 das 52** skills `adobe-*` ainda citam o projeto de origem.

## Checklist de adaptação (o que AINDA é específico do projeto de origem)
O nome do cliente de origem aparece **358 vezes** (em 92 arquivos) nos arquivos abaixo. Isso não foi trocado em massa porque está em nomes de skills (`bunge-*`), variáveis (`BUNGE_*`), exemplos de código e instruções dos agentes, onde um renomeio cego quebraria referências cruzadas. Revise cada área:

| Área | Menções |
|---|---|
| `integrations/local-verify` | 24 |
| `skills/adobe-magento-ui-component-developer` | 20 |
| `skills/projeto-engineering-practices` | 18 |
| `skills/projeto-subtask-contract` | 16 |
| `skills/bunge-backend-code-quality` | 16 |
| `agents/frontend_dev` | 13 |
| `skills/adobe-magento-toolbox` | 13 |
| `twinforge/workflow.yaml` | 12 |
| `agents/openspec_manager` | 10 |
| `agents/tech_lead` | 9 |
| `skills/adobe-magento-css-specialist` | 9 |
| `agents/backend_dev` | 8 |
| `skills/adobe-magento-luma-specialist` | 8 |
| `skills/adobe-magento-theme-developer` | 8 |
| `agents/mobile_tech_lead` | 7 |
| `skills/openspec-apply-change` | 7 |
| `skills/adobe-magento-frontend-standards` | 7 |
| `skills/adobe-create-unit-tests` | 7 |
| `skills/projeto-openspec-flow` | 7 |
| `agents/mobile_developer` | 6 |
| `skills/adobe-magento-requirejs-specialist` | 6 |
| `skills/projeto-mobile-flow` | 6 |
| `twinforge/README.md` | 6 |
| `skills/README.md` | 5 |
| `skills/adobe-magento-frontend-developer` | 5 |
| `skills/projeto-domain-lookup` | 5 |
| `skills/bunge-frontend-code-quality` | 5 |
| `context/conector-delegacao-twinforge.md` | 5 |
| `skills/adobe-create-http-client` | 4 |
| `skills/adobe-inject-custom-logger` | 4 |
| `TEMPLATE.md` | 4 |
| `CLAUDE.md` | 4 |
| `agents/po` | 3 |
| `agents/workflow_manager` | 3 |
| `skills/adobe-inject-validator` | 3 |
| `skills/adobe-create-import-entity` | 3 |
| `skills/adobe-inject-debug-logger` | 3 |
| `skills/adobe-create-repository` | 3 |
| `skills/adobe-create-controller` | 3 |
| `integrations/slack-bridge` | 3 |
| `agents/README.md` | 2 |
| `skills/adobe-create-plugin` | 2 |
| `skills/adobe-create-queue` | 2 |
| `skills/react-native-standards` | 2 |
| `skills/adobe-srs` | 2 |
| `skills/openspec-propose` | 2 |
| `skills/openspec-update-change` | 2 |
| `skills/openspec-explore` | 2 |
| `skills/adobe-magento-hyva-specialist` | 2 |
| `skills/adobe-commerce-standards` | 2 |
| `skills/adobe-run-unit-tests` | 2 |
| `skills/adobe-create-cronjob` | 2 |
| `skills/projeto-vague-demand-triage` | 2 |
| `skills/adobe-create-config` | 2 |
| `skills/openspec-review-proposal` | 2 |
| `skills/adobe-magento-backend-standards` | 2 |
| `README.md` | 2 |
| `context/engenharia-de-contexto-e-prompt.md` | 2 |
| `knowledge/defects` | 2 |
| `agents/verifier` | 1 |
| `skills/openspec-sync-specs` | 1 |
| `skills/adobe-magento-php-specialist` | 1 |
| `skills/adobe-magento-module-developer` | 1 |
| `skills/openspec-archive-change` | 1 |
| `skills/adobe-create-entity` | 1 |
| `skills/adobe-magento-code-reviewer` | 1 |
| `skills/adobe-magento-feature-developer` | 1 |
| `context/licoes-fluxo-openspec.md` | 1 |
| `context/roteiro-ativacao-verificador.md` | 1 |
| `knowledge/eval` | 1 |
| `knowledge/README.md` | 1 |

Ordem sugerida: (1) `twinforge/roles.yaml` e `workflow.yaml`; (2) `agents/*/agent.yaml` e `AGENTS.md`; (3) skills `projeto-*` e as duas `bunge-*` restantes (renomeie slug e referências juntos); (4) variáveis de ambiente `BUNGE_*`; (5) `context/` e `knowledge/`; (6) escreva o seu `docs/`.

## Manter a base atualizada
Melhorias da base chegam ao projeto por `git merge`/cherry-pick manual (um repositório criado de template não guarda vínculo com a origem). Mudanças genéricas devem voltar para este repositório por PR.
