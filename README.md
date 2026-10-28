# twinforge-adobe-template

Base para configurar um projeto **TwinForge + Adobe Commerce (Magento 2)**: o pacote de skills `twinforge` (setup, instâncias, diagnóstico, linguagem natural, envio de mudanças do OpenSpec e repositório do projeto), os comandos do repositório de configuração e uma base de agentes, skills e fluxo já testada em um projeto real.

> **É uma base a adaptar, não um padrão.** Ela nasceu da configuração do primeiro projeto configurado e ainda carrega referências a ele (principalmente nas skills `bunge-*` e nas instruções dos agentes). [`TEMPLATE.md`](./TEMPLATE.md) lista o que já foi neutralizado e o que falta adaptar.

## Como começar um projeto novo

1. Crie o repositório do projeto a partir deste template:
   ```bash
   gh repo create <org>/<projeto>-twinforge --template air-bizapps/twinforge-adobe-template --private --clone
   ```
2. Abra o Claude Code na pasta e rode `/twinforge:setup`. Ele guia os passos da tabela abaixo.
3. Adapte o repositório (checklist em `TEMPLATE.md`) e suba o template pelo passo 10 do setup. O `/twinforge:repo:push` é barrado enquanto `scope.project_id` em `twinforge/roles.yaml` for um placeholder.

## `/twinforge:setup`: o que cada passo faz

O setup **sempre começa perguntando como você quer seguir**: continuar um setup em andamento, revalidar uma instância (checkup) ou configurar uma nova. Nada é escrito em uma instância sem a sua confirmação.

| Passo | O que é feito |
|---|---|
| **1. CLI** | • confere Node 20+ e `twinforgecli --version`<br>• se faltar, mostra `npm install -g twinforgecli` e instala só com a sua confirmação<br>• com `E404` e registry privado, repete com o registry público<br>• avisa se a versão está antiga (`/twinforge:update`) |
| **2. Como seguir** | • levanta o estado de cada instância **só com leitura** (login, perfil, company, projetos e, para o padrão da pasta, se o `roles.yaml` está adaptado)<br>• mostra uma tabela: **completa** ou **em andamento**, e o que falta<br>• faz **uma pergunta**, sempre, com recomendação: **continuar** um setup em andamento, **revalidar** uma instância (checkup) ou **configurar uma nova**<br>• para instância nova, pede a URL (`https://`) e valida `/api/health` |
| **3. Login** | • gera o link de aprovação da CLI com um contexto vazio (evita o erro 500 causado pela company de outra instância)<br>• você entra ou cria a conta pelo próprio link e aprova: **um login só**<br>• confirma com `auth whoami`; a credencial é por endereço e as outras instâncias não são afetadas |
| **4. Perfil** | • grava o perfil com nome único (`context set`, sem `--use`: o perfil ativo global não muda)<br>• oferece salvar como padrão da pasta (`.twinforge/context.json`, sem token, no `.gitignore`) |
| **5. Projetos** (automático) | • lista company e projetos por nome (equivale ao `/twinforge:projects`)<br>• destaca o **projeto da pasta**, se já houver um salvo<br>• lista vazia não é sucesso: explica as causas e os próximos passos |
| **6. Permissão** (automático) | • lê o seu papel (`auth whoami`)<br>• regrava o `AGENTS.md` de **um** agente parado com o **mesmo conteúdo**, para testar a escrita<br>• se falhar, orienta pedir **owner ou admin** no canal do Slack `bizapps-twinforge-forjadores` e não oferece o passo 10 |
| **7. Repositório de configuração** (opcional) | • pular (e diz o que se perde)<br>• apontar um clone existente<br>• criar a partir do repositório base da organização (você informa a URL)<br>• puxar o que existe na instância |
| **8. Auditoria** | • com o `roles.yaml` adaptado, compara agentes da instância × `roles.yaml` por `id`<br>• com placeholders, não roda e lista o que falta trocar<br>• somente leitura |
| **9. Conferência e próximo passo** | • resumo: CLI, instância, login, perfil, company, projetos, **projeto da pasta**, permissão e repositório do projeto<br>• mostra o que existe no repositório × na instância<br>• lista os comandos (tabela abaixo)<br>• faz **uma pergunta** de próximo passo: subir o template, pedir algo em linguagem natural, ver a instância ou terminar |
| **10. Subir o template** (opcional) | • **define o projeto**: escolhe um existente ou **cria um novo com o nome do projeto (recomendado)** e salva a escolha em `.twinforge/project.json` (contexto da pasta, que direciona as skills para ele) e em `scope.project_id` do `roles.yaml`<br>• **conecta o repositório do projeto** (`/twinforge:workspace`: segredo do PAT sem o token na conversa, vínculo ao env sem apagar variáveis e workspace)<br>• avisa do conteúdo do projeto de origem e oferece subir só o genérico primeiro<br>• mostra a diferença (`/twinforge:repo:status`) e aplica as skills (`/twinforge:repo:push`)<br>• cria os agentes com confirmação, **preservando os existentes** e criando o PO na raiz (papel `general`)<br>• nenhum agente é acionado |

### O projeto da pasta
No passo 10 (e no `/twinforge:workspace`) você **escolhe um projeto existente ou cria um novo com o nome do projeto** (a recomendação é criar). A escolha é salva no contexto da pasta para as skills saberem em qual projeto atuar:

| Onde | O que guarda | Para quê |
|---|---|---|
| `.twinforge/project.json` | `apiBase`, `companyId`, `projectId`, `projectName` (sem token, fora do git) | As skills usam esse projeto por padrão (`--project-id`), dizem qual estão usando e validam que ele pertence à instância fixada |
| `scope.project_id` em `twinforge/roles.yaml` | o mesmo `projectId` (versionado) | Vale para o time; é o que o `/twinforge:repo:*` confere antes de escrever |

A CLI não tem campo de projeto no perfil, e o `context set` apaga chaves que não conhece; por isso a escolha não fica no `.twinforge/context.json`. O projeto padrão da company (`defaultProjectId`) não é alterado.

**Revalidar (checkup):**

| Passo | Checagem (só leitura) | Se falhar |
|---|---|---|
| 1 CLI | `twinforgecli --version`, Node 20+, versão publicada | passo 1 |
| 2 Instância | `/api/health` | considera o horário de funcionamento; nada local a corrigir |
| 3 Login | `auth whoami` | passo 3 (novo link de aprovação) |
| 4 Perfil | perfil existe e bate com a URL e a company | passo 4, com cópia de segurança do contexto |
| 5 Projetos | `company list` e `project list` | passo 5 |
| 5b Projeto e repositório | projeto da pasta válido, workspace e `GH_TOKEN` (`twinforge-workspace`) | `/twinforge:workspace` |
| 6 Permissão | regravação idêntica do `AGENTS.md` | orienta o pedido no Slack |
| 7 e 8 | pasta, `roles.yaml` e auditoria | só relata |

O checkup **não altera a instância remota**, exceto para reparar uma falha e na regravação idêntica do passo 6. Não executa o passo 10.

## Comandos

### `/twinforge:*`

| Comando | O que faz |
|---|---|
| **`/twinforge:explore`** | **Linguagem natural: peça qualquer coisa** ("quais cards estão em andamento?", "cria uma card…", "lista os agentes", "quanto gastamos este mês?"). Leitura roda direto; escrita mostra o rascunho e pede confirmação. |
| `/twinforge:setup` | Configuração inicial. Sempre pergunta: continuar um setup em andamento, revalidar uma instância ou configurar uma nova. |
| `/twinforge:projects` | Lista os projetos da company (aceita uma company como argumento). |
| `/twinforge:instances` | Lista, mostra e troca a instância em uso. |
| `/twinforge:workspace` | Define o projeto (escolher um existente ou criar um novo, e salvar no contexto da pasta) e conecta o repositório GitHub: workspace e segredo do PAT, com o token fora da conversa. |
| `/twinforge:apply` | Envia uma mudança do OpenSpec para a instância (issue pai, documentos e uma filha por grupo de tarefas, em `backlog` e sem responsável). |
| `/twinforge:status` | Checa os pré-requisitos do `twinforgecli` e diz se está tudo certo (só leitura). |
| `/twinforge:fix` | Corrige o que o `status` encontrou, com plano, confirmação e cópia de segurança. |
| `/twinforge:update` | Atualiza o `twinforgecli` para a versão mais recente. |

### `/twinforge:repo:*`

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

## O que tem aqui

| Onde | O que é |
|---|---|
| `.claude/skills/` | Pacote de skills `twinforge`: `twinforge`, `twinforge-setup`, `twinforge-instancias`, `twinforge-diagnostico`, `twinforge-apply`, `twinforge-workspace`. |
| `commands/twinforge/` | Os comandos `/twinforge:*`. Cada um só aciona a skill. |
| `commands/twinforge/repo/` | Os comandos `/twinforge:repo:*`. |
| `agents/` | 11 agentes (`agent.yaml` portável + `AGENTS.md`/`CONTEXT.md`). |
| `skills/` | 76 skills da plataforma: 51 `adobe-*` (Magento 2; várias ainda citam o projeto de origem), 7 `openspec-*`, skills de processo sem prefixo, 6 `projeto-*` e 2 `bunge-*` (a adaptar). |
| `twinforge/` | `workflow.yaml` (fases e aprovações), `roles.yaml` (papel → agente, com placeholders). |
| `context/`, `knowledge/` | Como a plataforma e o `twinforgecli` se comportam e lições do fluxo de agentes. |
| `integrations/` | Ponte com o Slack e verificação local. |
| `docs/` | Vazia de propósito: documentação de domínio é de cada projeto. O pacote de skills está descrito em `docs/twinforge-skills.md`. |

Orientações de trabalho para o Claude Code estão em [`CLAUDE.md`](./CLAUDE.md).
