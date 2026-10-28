# twinforge-adobe-skills

Skills e comandos do Claude Code para usar o `twinforgecli` em projetos Adobe Commerce (Magento 2), direto do terminal.

> **Estado:** as cinco skills (`*/SKILL.md`) e os oito comandos (`commands/twinforge/`) já foram escritos, mas **ainda não foram testados em uso real** (nem instalados em `~/.claude`). Este documento descreve como tudo **deve funcionar**. A fonte de verdade do planejamento é a mudança OpenSpec `openspec/changes/twinforge-adobe-skills-v1/` (proposal, specs, design e tasks). Quando algo aqui divergir de lá, vale o que está lá.

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

| Comando | Skill acionada | O que faz |
|---|---|---|
| `/twinforge:setup` | `twinforge-setup` | Configura do zero ou retoma de onde parou |
| `/twinforge:instances` | `twinforge-instancias` | Lista e troca de instância |
| `/twinforge:status` | `twinforge-diagnostico` | Verifica os pré-requisitos e diz se está tudo certo (só leitura) |
| `/twinforge:fix` | `twinforge-diagnostico` | Corrige o que o status encontrou, com confirmação |
| `/twinforge:update` | `twinforge-diagnostico` | Atualiza o `twinforgecli` |
| `/twinforge:projects` | `twinforge` | Lista os projetos da company (aceita uma company como argumento) |
| `/twinforge:explore` | `twinforge` | Inicia um modo de conversa em linguagem natural |
| `/twinforge:apply` | `twinforge-apply` | Envia uma mudança do OpenSpec para a instância |
| `/twinforge:workspace` | `twinforge-workspace` | Conecta o repositório do projeto e o segredo do PAT do GitHub, sem o token na conversa |

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

## `/twinforge:setup`: do zero até ver seus projetos

O setup **lê o estado real** (máquina e instância) a cada execução e **sempre começa perguntando como você quer seguir**: continuar um setup em andamento, revalidar uma instância (checkup) ou configurar uma instância nova.

```
1 CLI --> 2 Instância --> 3 Login --> 4 Perfil --> 5 Projetos --> 6 Permissão --> 7 Repo (opcional) --> 8 Auditoria --> 9 Conferência --> 10 Subir template (opcional)
```

1. **CLI.** Verifica `twinforgecli --version` e o Node 20+. Se faltar, mostra `npm install -g twinforgecli` e instala só com a sua confirmação.
2. **Como você quer seguir (sempre pergunta).** Mostra as instâncias que você já tem, com o estado de cada uma (**completa** ou **em andamento**, e o que falta), e pergunta, com uma recomendação: **continuar um setup em andamento** (retoma no primeiro passo pendente), **revalidar uma instância** (checkup: percorre todo o setup sem alterar nada na instância remota, só repara falhas) ou **configurar uma instância nova** (pede a URL, que precisa ser `https://`). Nem um padrão salvo na pasta dispensa a pergunta.
3. **Login (uma vez só).** Se `twinforgecli auth whoami` já responde, pula. Senão, gera o link de aprovação da CLI e você entra (ou cria a conta) pelo próprio link e aprova. Em instância nova, o login usa um contexto vazio, para a CLI não enviar a company de outra instância.
4. **Perfil.** Grava um perfil com nome único (sem `--use`: o perfil ativo global não muda) e oferece salvá-lo como padrão da pasta (`.twinforge/context.json`, sem token).
5. **Company e projetos.** Roda automaticamente (é o `/twinforge:projects`): lista e mostra os projetos por nome, nunca por uuid. Lista vazia não é sucesso.
6. **Permissão para editar agentes.** Roda automaticamente: lê seu papel e regrava o `AGENTS.md` de **um** agente (que não esteja em execução) com o **mesmo conteúdo** para testar a escrita. Se falhar por permissão, orienta a pedir no canal do Slack `bizapps-twinforge-forjadores` a mudança do papel para owner ou admin.
7. **Repositório de configuração dos agentes (opcional).** Pasta com `agents/`, `skills/` e `twinforge/roles.yaml`. Opções: pular, apontar um clone existente, criar a partir do repositório base da organização (você informa a URL) ou puxar o que existe na instância.
8. **Auditoria.** Só com repositório adaptado: compara por `id` os agentes da instância com `roles.yaml`, sem alterar nada.
9. **Conferência final e próximo passo.** Mostra o que há no repositório e na instância, a lista de comandos (linguagem natural em destaque) e faz **uma pergunta** de próximo passo com a recomendação.
10. **Subir o template (opcional).** Só se você escolher e a permissão do passo 6 passou: projeto-alvo, skills pelo `/twinforge:repo:push` e criação guiada dos agentes, com confirmação antes de cada escrita. Os agentes que a instância já tem **nunca são alterados nem apagados** pelo passo: a estrutura nova nasce de um PO criado na raiz (papel `general`, sem `reportsTo`), ao lado do agente de topo existente, ou de um existente que você indicar.

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

- **Restrições da chave** de `issue document:put`: o limite de tamanho vem de `attachmentMaxBytes` da company (10 MiB na instância testada); as regras da chave não foram verificadas.
- **Envio repetido do `apply`:** a busca pelo marcador usa `issue list --match` (filtro local, sem paginação); o reenvio sem duplicar só será validado numa instância de teste.
- **Publicação** em `air-bizapps/twinforge-adobe-skills`, depois dos testes locais.
- **Servidor local** (`twinforgecli run --instance`): fora do v1.

Já verificados na CLI 0.6.0: formato da URL de aprovação do `auth login --no-browser --json` (sai em stderr) e ausência de conflito do prefixo `twinforge:`.

## Desenvolvimento deste repositório

O processo é spec-driven com OpenSpec. Todos os artefatos são escritos em pt-BR (cabeçalhos estruturais e SHALL/MUST em inglês). Use `/opsx:propose`, `/opsx:apply`, `/opsx:archive` etc. em vez de criar arquivos em `openspec/` à mão. A mudança atual é `twinforge-adobe-skills-v1`.
