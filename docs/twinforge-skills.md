# twinforge-adobe-skills

Skills e comandos do Claude Code para usar o `twinforgecli` em projetos Adobe Commerce (Magento 2), direto do terminal.

> **Estado:** as seis skills (`*/SKILL.md`) e os nove comandos (`commands/twinforge/`) já foram escritos, mas **ainda não foram testados em uso real** (nem instalados em `~/.claude`). Este documento descreve como tudo **deve funcionar**. A fonte de verdade do planejamento é a mudança OpenSpec `openspec/changes/twinforge-adobe-skills-v1/` (proposal, specs, design e tasks). Quando algo aqui divergir de lá, vale o que está lá.

## Para quem é

Quem trabalha com Adobe Commerce, prefere o terminal e quer usar uma instância TwinForge sem decorar comandos. As skills ajudam a pessoa a **chegar até os projetos dela** no `twinforgecli`: instalar, entrar, escolher company e projeto, e depois pedir as coisas em linguagem natural.

Princípios:

- **Sem referência a cliente, instância ou projeto.** Depois de instaladas, as skills trabalham com a instância e o perfil que o `twinforgecli` já tem ativos. Nada de URL ou perfil fixo nos arquivos.
- **Funcionam em qualquer pasta e sem o app aberto.**
- **Não dependem do OpenSpec.** Ele é só o processo de desenvolvimento deste repositório (a exceção é o `/twinforge:apply`, que lê uma mudança do OpenSpec, de forma opcional).
- **Leitura roda direto; escrita mostra o rascunho e só roda com a sua confirmação.**
- **Nunca** imprimem, pedem ou guardam token ou senha, e **nunca** rodam `twinforgecli connect` (ele exige terminal interativo).

## O que vem no pacote

Seis skills (cada uma é uma pasta com um `SKILL.md`) e nove comandos finos que só acionam a skill certa.

| Skill | Para que serve |
|---|---|
| `twinforge-setup` | Configuração inicial guiada, retomável e repetível sem efeito colateral |
| `twinforge-instancias` | Perfis, troca e seleção da instância da sessão |
| `twinforge` | Linguagem natural: traduz o pedido em comando do `twinforgecli` |
| `twinforge-diagnostico` | `status`, `fix` e `update` do próprio CLI |
| `twinforge-apply` | Envia uma mudança de planejamento para a instância remota |
| `twinforge-workspace` | Conecta o repositório GitHub do projeto à instância (workspace e segredo do PAT) |

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

O template acrescenta os comandos do repositório de configuração:

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

Os comandos são opcionais: as skills também são acionadas por pedidos em linguagem natural.

## Instalação

Requisito: **Node.js 20+** e o `twinforgecli`:

```bash
npm install -g twinforgecli
```

Se falhar com `E404` e você usa um registry npm privado, repita com o registry público:

```bash
npm install -g twinforgecli --registry https://registry.npmjs.org
```

As skills e os comandos (planejado), por uma destas formas:

```bash
npx skills add air-bizapps/twinforge-adobe-skills
```

ou copiando as pastas para `.claude/skills` e `.claude/commands` (no projeto) ou para `~/.claude/` (para todas as pastas).

O instalador `install.sh` do app completo **não** faz parte deste fluxo: ele serve para quem hospeda a instância.

## Como começa uma sessão

Qualquer skill ou comando, no primeiro uso da sessão, resolve **qual instância usar**:

```
1o uso na sessão
   |
   +-- nenhum perfil ---> /twinforge:setup
   +-- 1 perfil --------> "Usando <perfil> (<URL>, <company>)" e segue, sem perguntar
   +-- 2+ perfis -------> pergunta uma vez qual usar (padrão: a da pasta, se houver)
                                          |
        todo comando seguinte: twinforgecli ... --context <arquivo> --profile <perfil>
```

Por que `--context` e `--profile` em cada comando: cada chamada de shell começa do zero, e `twinforgecli context use` mudaria o perfil global da máquina, afetando outras sessões. Passando o par explicitamente, a escolha vale só para a sessão. Só `--profile` não basta: um nome que a CLI não encontra cai silenciosamente em `http://localhost:3100`, e uma pasta com `.twinforge/context.json` esconde os perfis globais. O perfil `default` sem URL que a CLI sempre mostra é só um placeholder e não conta como instância. Se a conversa for resumida e a skill perder a escolha, ela pergunta de novo em vez de assumir.

Para trabalhar em outra instância no meio da sessão, peça a troca; ela refaz a seleção sem mexer no perfil global.

Mesmo com uma única instância, **toda escrita** mostra instância e company e pede confirmação.

## `/twinforge:setup`: o que cada passo faz

O setup **lê o estado real** (máquina e instância) a cada execução e **sempre começa perguntando como você quer seguir**: continuar um setup em andamento, revalidar uma instância (checkup) ou configurar uma nova. Nada é escrito em uma instância sem a sua confirmação.

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

**Revalidar (checkup):**

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

## Linguagem natural: `twinforge` e `/twinforge:explore`

Você pede, a skill traduz em comando do `twinforgecli` **consultando `twinforgecli commands`** (o catálogo vivo da CLI), sem lista fixa de comandos no `SKILL.md`. Assim, comandos novos de versões futuras funcionam sem atualizar a skill.

- **Leitura** (listar, mostrar, consultar): roda direto, com `--json` quando existe, e resume o resultado.
- **Escrita** (criar card, atribuir, mover, comentar, enviar agentes): mostra a instância, a company, o comando e o efeito, e **só executa depois do seu "sim"**. Na dúvida se um comando lê ou escreve, trata como escrita.
- **Card sem responsável:** a skill avisa que ninguém será acordado enquanto não houver responsável ou enquanto a card estiver em `backlog`.

`/twinforge:explore` inicia um **modo de conversa**: seleciona a instância, lê o catálogo uma vez, anuncia o modo e passa a interpretar as suas mensagens seguintes como pedidos, até você pedir para sair. Dá para trocar de instância dentro do modo.

`/twinforge:projects [company]` lista os projetos da company do perfil (ou da company informada). Com várias companies e sem argumento, agrupa por company.

O `SKILL.md` também traz uma seção separada, opcional, de atalhos para projetos Magento 2 (por exemplo o fluxo Tech Lead / OpenSpec Manager / Backend / Frontend). O núcleo não assume nenhum cliente.

## Diagnóstico: `/twinforge:status`, `/twinforge:fix`, `/twinforge:update`

**`status`** só lê e termina dizendo "tudo certo" ou quais itens precisam de correção. Verifica, em ordem:

| Item | Como |
|---|---|
| Node 20+ e npm | `node --version`, `npm --version` |
| CLI instalado e no PATH | `twinforgecli --version` |
| Versão instalada × publicada | `npm view twinforgecli version` |
| Registry público alcançável | resolução do pacote |
| Arquivos de contexto e credenciais | existem e têm JSON válido |
| Login da instância da sessão | `auth whoami --profile <perfil>` |
| Instância no ar | `GET <url>/api/health` |
| Company acessível | `company list` |

Cada item sai como OK, ATENÇÃO ou FALHA, sem exibir credenciais.

**`fix`** refaz as checagens e, para cada problema, propõe um reparo. Mostra o **plano completo** e só executa com a sua confirmação. Antes de mexer em arquivos seus, faz **cópia de segurança**; no fim, refaz as checagens e relata o que resolveu e o que sobrou. Casos fora do alcance local (instância fora do ar, 403 do proxy) não alteram nada: a skill explica a causa e o que pedir aos responsáveis. Para login ausente ou expirado, encaminha para o fluxo de login do setup.

**`update`** mostra a versão instalada e a mais recente, pede confirmação, atualiza por npm e confere a nova versão e o login depois. Já na última versão, não faz nada.

O diagnóstico **não usa** `twinforgecli doctor`: esse comando é do setup do servidor local (configuração e banco), não dos pré-requisitos de quem usa a CLI contra uma instância hospedada.

## Tabela de erros

A skill `twinforge` traz uma tabela "mensagem de erro → o que dizer ao usuário", em vez de repetir tentativas. Cobre no mínimo:

| Situação | O que a skill faz |
|---|---|
| Login expirado ou ausente | Explica e encaminha para o login do setup |
| Instância fora do ar | Diz que o problema é a instância, sugere checar `/api/health`, não altera configuração |
| 403 do proxy | Explica que é bloqueio de acesso e o que pedir aos responsáveis |
| Company errada | Mostra a company em uso e oferece listar as disponíveis |
| CLI desatualizada | Sugere `/twinforge:update` |
| `E404` na instalação (registry privado) | Propõe repetir com `--registry https://registry.npmjs.org` |
| Node ausente ou antigo | Explica o requisito do Node 20+ |

## Várias instâncias

- O login é guardado **por endereço** (`~/.twinforge/auth.json`): entrar numa segunda instância não invalida a primeira.
- Perfis ficam em `~/.twinforge/context.json`. Três formas de alternar: `context use <perfil>` (global), `--profile` / `--api-base` em um comando, ou um `.twinforge/context.json` na pasta do projeto (a CLI o encontra subindo a partir da pasta atual).
- Ordem de resolução da CLI: `--context`, `TWINFORGE_CONTEXT`, arquivos de pastas ancestrais, perfil padrão.
- Se a pasta aponta para uma instância e o perfil global para outra, a skill **para e pergunta** antes de escrever.
- `--data-dir` ou `TWINFORGE_HOME` isola login, perfis e dados em outra pasta (útil para testes).

## `/twinforge:apply`: desenvolver na instância remota

Parecido com o `opsx:apply`, mas em vez de implementar na sua máquina, **envia a mudança do OpenSpec para a instância**, e o desenvolvimento acontece lá. É uma integração opcional: só funciona se existir `openspec/changes/<nome>/` na pasta atual.

```
openspec/changes/<nome>/                  instância TwinForge
  proposal.md   ------------------------> issue pai (descrição, com marcador da mudança)
  design.md     ------------------------> documento da issue pai ("design")
  specs/**      ------------------------> documentos da issue pai (um por capacidade)
  tasks.md      ------------------------> uma issue filha por grupo "## N."
destino: projeto escolhido por nome, status backlog, sem responsável
```

- O conteúdo viaja dentro da issue, porque o agente remoto não enxerga a sua pasta local; o código ele lê do repositório que o projeto da instância já tem configurado.
- **Padrão seguro:** tudo nasce em `backlog` e sem responsável, então o envio **não acorda nenhum agente**. Atribuir e iniciar é uma ação separada, que pede confirmação e avisa que agentes serão acionados.
- **Rascunho antes de enviar:** instância, company, projeto, título, quantidade de documentos e de filhas, status, responsável e o resultado da busca por envio anterior.
- **Sem duplicar:** a skill procura no projeto uma issue com o marcador da mudança. Se achar, oferece atualizar em vez de criar outra.
- **Fora do escopo:** não sincroniza de volta (os checkboxes de `tasks.md` não são marcados quando os agentes concluem) e não implementa nada localmente. O andamento você acompanha por leituras, por exemplo no `/twinforge:explore`.

## `/twinforge:workspace`: repositório do projeto e PAT do GitHub

Os agentes só conseguem puxar e alterar o código se o projeto tiver o **repositório** e um **segredo com o PAT do GitHub**. O `/twinforge:workspace` faz isso (e o `setup` o chama **antes** de subir skills e agentes):

1. Escolhe o projeto por nome e pede a URL `https://github.com/<org>/<repo>`.
2. Lê o estado (somente leitura): workspace, chaves do env do projeto e segredos da company.
3. **O PAT nunca passa pela conversa.** A skill orienta criar o token no GitHub (escopo só desse repositório) e entrega um comando para você rodar em **outro terminal**: ele lê o token por um prompt sem eco (`read -s`), grava o segredo com `--value-env` e limpa a variável. O comando não contém o token e nada vai para o histórico.
4. Confirma o segredo pelo nome (`secrets list`, que só mostra metadados).
5. Vincula o segredo ao projeto **sem apagar as outras variáveis** do env (o comando substitui o env inteiro, então a skill lê, mescla e mostra o diff antes).
6. Cria o workspace (`project-workspace create`) com a `repoUrl`. Toda escrita pede confirmação.

Se o token for colado na conversa por engano, a skill avisa que ele foi exposto e pede para revogá-lo no GitHub.

## Segurança, em resumo

- Nunca imprime, pede ou guarda token ou senha; se um token aparecer na saída de um comando, não o reproduz.
- Nunca roda `twinforgecli connect`. O caminho scriptável é `auth login --no-browser`. Se um comando exigir terminal interativo, a skill orienta você a rodá-lo com o prefixo `!` na sessão.
- Toda escrita na instância: instância e company em uso, o que será feito e confirmação explícita.
- Instalações e reparos locais: plano mostrado e confirmado antes, com cópia de segurança de arquivos seus.

## Decisões ainda em aberto

Registradas em `openspec/changes/twinforge-adobe-skills-v1/design.md`:

- **Fluxo do `/twinforge:workspace` de ponta a ponta:** criar o segredo pelo terminal do usuário, vincular ao projeto e criar o workspace só foi validado na leitura; falta testar a escrita e o prompt oculto no `zsh`.
- **Criação dos agentes** no passo 10 do setup: o `agent.yaml` não é o payload do `agent create` e não há instalador; o passo é guiado e ainda não foi exercitado.
- **Verificação de permissão** (passo 6): a mensagem exata de erro de um usuário operador não foi observada.
- **Restrições da chave** de `issue document:put`: o limite vem de `attachmentMaxBytes` da company (10 MiB na instância testada); as regras da chave não foram verificadas.
- **Envio repetido do `apply`:** a busca pelo marcador usa `issue list --match` (filtro local, sem paginação); o reenvio sem duplicar só será validado numa instância de teste.
- **Sobreposição** entre `/twinforge:setup` e `/twinforge:repo:init`, e a limpeza das menções ao projeto de origem no template.
- **Publicação** em `air-bizapps/twinforge-adobe-skills`, depois dos testes.
- **Servidor local** (`twinforgecli run --instance`): fora do v1.

Já verificados na CLI 0.6.0: formato da URL de aprovação do `auth login --no-browser --json` (sai em stderr), o erro 500 quando o contexto padrão de outra instância é usado no login, e a regravação idêntica do `AGENTS.md` por um admin.

## Desenvolvimento deste repositório

O processo é spec-driven com OpenSpec. Todos os artefatos são escritos em pt-BR (cabeçalhos estruturais e SHALL/MUST em inglês). Use `/opsx:propose`, `/opsx:apply`, `/opsx:archive` etc. em vez de criar arquivos em `openspec/` à mão. A mudança atual é `twinforge-adobe-skills-v1`.
