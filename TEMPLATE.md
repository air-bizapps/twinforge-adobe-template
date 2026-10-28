# TEMPLATE: como usar esta base

Este repositório é a **base a adaptar** para projetos TwinForge + Adobe Commerce (Magento 2). Ele **não é um padrão a copiar sem revisão**: nasceu da configuração do primeiro projeto configurado e herda a estrutura de agentes, skills, fluxo, comandos e integrações dele, com os dados de cliente neutralizados.

## O que vem aqui
| Parte | Onde |
|---|---|
| Pacote de skills `twinforge` (setup, instâncias, diagnóstico, linguagem natural, apply, repositório do projeto) | `.claude/skills/` e `commands/twinforge/` (`/twinforge:*`) |
| Comandos do repositório de configuração | `commands/twinforge/repo/` (`/twinforge:repo:*`) |
| Agentes, skills da plataforma, fluxo e papéis | `agents/` (11), `skills/` (76), `twinforge/` |
| Conhecimento operacional | `context/`, `knowledge/` |
| Integrações | `integrations/` |

## Como começar um projeto novo
1. Crie o repositório do projeto: `gh repo create <org>/<projeto>-twinforge --template air-bizapps/twinforge-adobe-template --private --clone`.
2. Abra o Claude Code na pasta e rode `/twinforge:setup`. Ele guia os 10 passos da tabela abaixo; com mais de uma instância, escolha a do projeto e salve como padrão da pasta.
3. Adapte o repositório (checklist mais abaixo). O `/twinforge:repo:push` é barrado enquanto `scope.project_id` de `twinforge/roles.yaml` for um placeholder.

## `/twinforge:setup`: passo a passo
O setup **sempre começa perguntando como você quer seguir** (passo 2) e nunca altera uma instância sem confirmação.

| Passo | O que é feito |
|---|---|
| **1. CLI** | • confere Node 20+ e `twinforgecli --version`<br>• se faltar, mostra `npm install -g twinforgecli` e instala só com a sua confirmação<br>• com `E404` e registry privado, repete com o registry público<br>• avisa se a versão está antiga (`/twinforge:update`) |
| **2. Como seguir** | • levanta o estado de cada instância **só com leitura** (login, perfil, company, projetos e, para o padrão da pasta, se o `roles.yaml` está adaptado)<br>• mostra uma tabela: **completa** ou **em andamento**, e o que falta<br>• faz **uma pergunta**, sempre, com recomendação: **continuar** um setup em andamento, **revalidar** uma instância (checkup) ou **configurar uma nova**<br>• para instância nova, pede a URL (`https://`) e valida `/api/health` |
| **3. Login** | • gera o link de aprovação da CLI com um contexto vazio (evita o erro 500 causado pela company de outra instância)<br>• você entra ou cria a conta pelo próprio link e aprova: **um login só**<br>• confirma com `auth whoami`; a credencial é por endereço e as outras instâncias não são afetadas |
| **4. Perfil** | • grava o perfil com nome único (`context set`, sem `--use`: o perfil ativo global não muda)<br>• oferece salvar como padrão da pasta (`.twinforge/context.json`, sem token, no `.gitignore`) |
| **5. Projetos** (automático) | • lista company e projetos por nome (equivale ao `/twinforge:projects`)<br>• lista vazia não é sucesso: explica as causas e os próximos passos |
| **6. Permissão** (automático) | • lê o seu papel (`auth whoami`)<br>• regrava o `AGENTS.md` de **um** agente parado com o **mesmo conteúdo**, para testar a escrita<br>• se falhar, orienta pedir **owner ou admin** no canal do Slack `bizapps-twinforge-forjadores` e não oferece o passo 10 |
| **7. Repositório de configuração** (opcional) | • pular (e diz o que se perde)<br>• apontar um clone existente<br>• criar a partir do repositório base da organização (você informa a URL)<br>• puxar o que existe na instância |
| **8. Auditoria** | • com o `roles.yaml` adaptado, compara agentes da instância × `roles.yaml` por `id`<br>• com placeholders, não roda e lista o que falta trocar<br>• somente leitura |
| **9. Conferência e próximo passo** | • resumo: CLI, instância, login, perfil, company, projetos, permissão e repositório do projeto<br>• mostra o que existe no repositório × na instância<br>• lista os comandos (tabela abaixo)<br>• faz **uma pergunta** de próximo passo: subir o template, pedir algo em linguagem natural, ver a instância ou terminar |
| **10. Subir o template** (opcional) | • escolhe o projeto-alvo<br>• **conecta o repositório do projeto** (`/twinforge:workspace`: segredo do PAT sem o token na conversa, vínculo ao env sem apagar variáveis e workspace)<br>• avisa do conteúdo do projeto de origem e oferece subir só o genérico primeiro<br>• mostra a diferença (`/twinforge:repo:status`) e aplica as skills (`/twinforge:repo:push`)<br>• cria os agentes com confirmação, **preservando os existentes** e criando o PO na raiz (papel `general`)<br>• nenhum agente é acionado |

### Revalidar uma instância (checkup)
No passo 2, **revalidar** percorre os passos em modo de checagem.

| Passo | Checagem (só leitura) | Se falhar |
|---|---|---|
| 1 CLI | `twinforgecli --version`, Node 20+, versão publicada | passo 1 |
| 2 Instância | `/api/health` | considera o horário de funcionamento; nada local a corrigir |
| 3 Login | `auth whoami` | passo 3 (novo link de aprovação) |
| 4 Perfil | perfil existe e bate com a URL e a company | passo 4, com cópia de segurança do contexto |
| 5 Projetos | `company list` e `project list` | passo 5 |
| 5b Repositório do projeto | workspace e `GH_TOKEN` (`twinforge-workspace`) | `/twinforge:workspace` |
| 6 Permissão | regravação idêntica do `AGENTS.md` | orienta o pedido no Slack |
| 7 e 8 | pasta, `roles.yaml` e auditoria | só relata |

O checkup **não altera a instância remota**, exceto para reparar uma falha e na regravação idêntica do passo 6. Não executa o passo 10.

## Comandos
### `/twinforge:*` (pacote de skills)
| Comando | O que faz |
|---|---|
| **`/twinforge:explore`** | **Linguagem natural: peça qualquer coisa** ("quais cards estão em andamento?", "cria uma card…", "lista os agentes", "quanto gastamos este mês?"). Leitura roda direto; escrita mostra o rascunho e pede confirmação. |
| `/twinforge:setup` | Configuração inicial. Sempre pergunta: continuar um setup em andamento, revalidar uma instância ou configurar uma nova. |
| `/twinforge:projects` | Lista os projetos da company (aceita uma company como argumento). |
| `/twinforge:instances` | Lista, mostra e troca a instância em uso. |
| `/twinforge:workspace` | Conecta o repositório GitHub do projeto: workspace e segredo do PAT, com o token fora da conversa. |
| `/twinforge:apply` | Envia uma mudança do OpenSpec para a instância (issue pai, documentos e uma filha por grupo de tarefas, em `backlog` e sem responsável). |
| `/twinforge:status` | Checa os pré-requisitos do `twinforgecli` e diz se está tudo certo (só leitura). |
| `/twinforge:fix` | Corrige o que o `status` encontrou, com plano, confirmação e cópia de segurança. |
| `/twinforge:update` | Atualiza o `twinforgecli` para a versão mais recente. |

### `/twinforge:repo:*` (repositório de configuração)
| Comando | O que faz |
|---|---|
| `/twinforge:repo:init` | Primeira configuração guiada de uma company (company, PAT, projeto e primeiro agente). |
| `/twinforge:repo:status` | Compara as skills e os agentes do repositório com a company ao vivo (só leitura). |
| `/twinforge:repo:pull` | Traz da company para o repositório as skills e as instruções dos agentes (a nuvem vence). |
| `/twinforge:repo:push` | Aplica as skills do repositório na instância e re-sincroniza o `desiredSkills` dos agentes existentes. **Não cria agentes.** Escreve na instância. |
| `/twinforge:repo:agent` | Operação pontual em um agente (status, skills, wake, instructions). |
| `/twinforge:repo:local-sync` | Sincroniza a instância local (127.0.0.1) com a nuvem via `twinforgecli sync`. |
| `/twinforge:repo:help` | Lista os comandos. |

Os comandos `repo:*` fixam a instância e **param** se `scope.project_id` de `twinforge/roles.yaml` não existir nela.

Há sobreposição entre `/twinforge:setup` e `/twinforge:repo:init`, a ser resolvida depois.

## O que já foi neutralizado
- **IDs:** todo UUID virou placeholder (`{{uuid}}`; em `twinforge/roles.yaml`, `{{<papel>_agent_id}}` e `{{project_id}}`).
- **Repositório do projeto:** `<org>/<repo-do-projeto>`.
- **`docs/`:** a documentação de domínio do cliente de origem não foi trazida.
- **Integração com o Agentic Toolkit:** removida (o app ainda é local e está em construção).

## Skills do projeto de origem: o que já foi feito
- **Renomeadas para `projeto-*`** (fluxo e prática do projeto; adapte o conteúdo ao seu): `projeto-openspec-flow`, `projeto-subtask-contract`, `projeto-vague-demand-triage`, `projeto-engineering-practices`, `projeto-domain-lookup`, `projeto-mobile-flow`.
- **Genéricas, sem prefixo** (processo TwinForge, reescritas sem fatos do projeto): `link-verification`, `figma-layout-decomposition`, `backlog-item`, `card-curation`, `po-issue-recovery`.
- **Ainda `bunge-*`**: `bunge-backend-code-quality` e `bunge-frontend-code-quality`. São camadas locais sobre `adobe-*-standards`: comparar com `adobe-commerce-standards`, mover só o que faltar e renomear o resto.
- **Removida do template:** `adobe-magento-house-rules` (notas pessoais de ajuste de um agente, não são base).
- **Atenção:** o prefixo `adobe-*` não garante neutralidade; **30 das 51** skills `adobe-*` ainda citam o projeto de origem.

## Checklist de adaptação (o que AINDA é específico do projeto de origem)
O nome do projeto de origem aparece **410 vezes** (em 91 arquivos). Isso não foi trocado em massa porque está em nomes de skills (`bunge-*`), variáveis (`BUNGE_*`), exemplos de código e instruções dos agentes, onde um renomeio cego quebraria referências cruzadas. As 15 áreas com mais ocorrências:

| Área | Ocorrências |
|---|---:|
| `integrations` | 35 |
| `skills/adobe-magento-ui-component-developer` | 27 |
| `skills/projeto-engineering-practices` | 19 |
| `skills/projeto-subtask-contract` | 18 |
| `twinforge` | 18 |
| `skills/bunge-backend-code-quality` | 17 |
| `agents/frontend_dev` | 15 |
| `skills/adobe-magento-css-specialist` | 15 |
| `skills/adobe-magento-toolbox` | 14 |
| `skills/adobe-magento-theme-developer` | 11 |
| `agents/openspec_manager` | 10 |
| `agents/tech_lead` | 10 |
| `agents/backend_dev` | 9 |
| `context` | 9 |
| `skills/adobe-magento-luma-specialist` | 9 |
| demais áreas | 174 |

Ordem sugerida: (1) `twinforge/roles.yaml` e `workflow.yaml`; (2) `agents/*/agent.yaml` e `AGENTS.md`; (3) skills `projeto-*` e as duas `bunge-*` restantes (renomeie slug e referências juntos); (4) variáveis de ambiente `BUNGE_*`; (5) `context/` e `knowledge/`; (6) escreva o seu `docs/`.

## Manter a base atualizada
Melhorias da base chegam ao projeto por `git merge`/cherry-pick manual (um repositório criado de template não guarda vínculo com a origem). Mudanças genéricas devem voltar para este repositório por PR.
