---
name: twinforge-instancias
description: Gerencia mais de uma instância TwinForge no twinforgecli. Use quando o usuário pedir para listar instâncias ou perfis, trocar de instância, saber em qual instância está, ou quando houver mais de uma instância e for preciso escolher antes de agir. Gatilhos - "qual instância estou usando", "trocar de instância", "listar perfis", "usar a outra instância", /twinforge:instances.
---

# twinforge-instancias

Cuida de perfis e instâncias do `twinforgecli`: listar, selecionar a instância da sessão, trocar e conferir antes de escrever. Não assume nenhuma instância específica.

## Regras inegociáveis

- **Nunca** imprima, peça, grave ou repita token, senha ou chave de API, nem em resumos de saída. Se um token aparecer na saída de um comando, omita-o.
- **Nunca** rode `twinforgecli connect` (exige terminal interativo). O caminho scriptável de login é `twinforgecli auth login --no-browser`. Se um comando exigir TTY, peça ao usuário que o rode na sessão com o prefixo `!`.
- **Toda escrita na instância** (criar, atualizar, apagar, atribuir, mover, comentar, enviar) segue: mostrar a instância e a company em uso, o comando e o efeito esperado; esperar um "sim" explícito do usuário; só então executar. Recusa ou pedido de ajuste = nada é executado.
- Nenhum nome de cliente, URL ou perfil é fixo nesta skill: trabalhe com a instância que o usuário tem no `twinforgecli`.

## Instância da sessão

Antes do primeiro comando do `twinforgecli` em cada sessão, fixe a instância de trabalho. A escolha vale só para a sessão e fica na conversa.

1. **Candidatas.** Junte os perfis de duas fontes, ignorando perfis com `apiBase` nulo (o perfil `default` vazio é só um placeholder da CLI):
   - global: `twinforgecli context list --json --context "${TWINFORGE_HOME:-$HOME/.twinforge}/context.json"`
   - pasta: `twinforgecli context show --json`. Se o `contextPath` não for o arquivo global, a pasta atual tem contexto próprio (`.twinforge/context.json` em algum diretório acima); liste esses perfis com `twinforgecli context list --json` (sem `--context`).
2. **Decidir.**
   - Nenhuma candidata → encaminhe para a skill `twinforge-setup` (`/twinforge:setup`).
   - **Padrão salvo da pasta** (a pasta tem `.twinforge/context.json` com perfil válido) → use-o **sem perguntar**, mesmo havendo outras candidatas: `Usando <perfil> (<apiBase>, company <companyId>), padrão salvo desta pasta. Diga "trocar de instância" para mudar.`
   - Uma → diga em uma linha `Usando <perfil> (<apiBase>, company <companyId>)` e siga, sem perguntar.
   - Duas ou mais, sem padrão salvo → pergunte UMA vez qual usar, pelo nome do perfil e pela URL. **Depois da escolha, ofereça salvá-la como padrão desta pasta** ("Não perguntar mais aqui?"). Se aceitar, mostre o conteúdo e, após confirmação, grave `.twinforge/context.json` na raiz do repositório (`git rev-parse --show-toplevel`, ou a pasta atual) só com o perfil escolhido: `{"currentProfile":"<perfil>","profiles":{"<perfil>":{"apiBase":"<URL>","companyId":"<id>","persona":"board"}}}` (sem token), e confira que `.twinforge/` está no `.gitignore` (acrescente, com confirmação). Se recusar, vale só para a sessão.
3. **Fixar.** Guarde o par (arquivo de contexto, perfil). Em TODO comando do `twinforgecli` passe `--context "<arquivo>" --profile "<perfil>"`. Nunca use só `--profile` nem `context use`: sem o arquivo certo, um nome que a CLI não encontra **cai silenciosamente em `http://localhost:3100`**, e `context use` altera o perfil global da máquina e as outras sessões.
4. **Refazer** a seleção se o usuário pedir troca ou se você não souber mais qual instância está fixada (por exemplo, depois de a conversa ser resumida). Nunca assuma uma instância.

## Listar instâncias e perfis

1. Reúna as candidatas como descrito acima (global e pasta).
2. Mostre uma tabela: perfil, URL (`apiBase`), company (`companyId`), origem (global ou pasta) e qual está fixada na sessão.
3. Para saber se há login em cada URL, rode `twinforgecli auth whoami --context "<arquivo>" --profile "<perfil>"` (somente leitura). Falha de rede ("Could not reach the TwinForge API") significa **sem resposta da instância**, não "sem login"; mostre como indeterminado. Nunca exiba credenciais.

## Trocar de instância

Explique a diferença antes de agir e use a forma que o usuário precisa:

| Forma | Alcance | Como |
|---|---|---|
| Na sessão (padrão) | só esta conversa | refaça a seleção e fixe o novo par arquivo+perfil; não altera nada em disco |
| Pontual | um comando | passe `--context`/`--profile` (ou `--api-base`) só nesse comando |
| Por pasta | todas as sessões nessa pasta | crie `.twinforge/context.json` na pasta do projeto (a CLI o encontra subindo a partir da pasta atual) |
| Global | toda a máquina, todas as sessões | `twinforgecli context use <perfil>` |

- Forma **por pasta** e **global** gravam em disco: mostre o comando ou o conteúdo e só execute após confirmação. Para a pasta, o arquivo tem a forma `{"currentProfile":"<perfil>","profiles":{"<perfil>":{"apiBase":"<URL>","companyId":"<id>","persona":"board"}}}`. O contexto da pasta **substitui** o global dentro dela: só os perfis desse arquivo ficam visíveis lá.
- Ordem de resolução da CLI (quando nada é passado): `--context`, `TWINFORGE_CONTEXT`, arquivos em pastas ancestrais, o padrão global. Quando você fixa `--context`, essa ordem não se aplica.

## Salvar a escolha (não perguntar mais)

Com duas ou mais instâncias, a escolha vale só para a sessão, a menos que o usuário a salve. O padrão salvo é o `.twinforge/context.json` da pasta (contexto por pasta da própria CLI): ele guarda só URL, company e persona, **nunca token** (o login fica em `auth.json`, por endereço). Nas próximas sessões nessa pasta a skill o usa sem perguntar.

- **Mudar numa sessão:** "trocar de instância" refaz a seleção e vale só para a conversa; o padrão salvo continua.
- **Mudar o padrão:** regrave o arquivo (com confirmação) ou apague-o para voltar a perguntar.
- **Efeito colateral a avisar:** dentro dessa pasta, a CLI sem `--context` enxerga só os perfis do arquivo da pasta; por isso as skills sempre passam `--context` e `--profile` explícitos.

## Conferência antes de escrever

Antes de qualquer escrita (você, ou outra skill que peça):

1. Resolva a instância fixada e mostre perfil, URL e company.
2. Se a pasta tem contexto próprio e o perfil global ativo aponta para outra URL, **pare e pergunte** qual instância deve valer, mesmo que já haja uma fixada, a menos que o usuário já tenha escolhido explicitamente nesta sessão.
3. Só prossiga com a instância confirmada.

## Isolamento e credenciais

- `--data-dir <pasta>` ou `TWINFORGE_HOME=<pasta>` isolam login, perfis e dados em outro diretório (útil para testes); nesse caso não toque em `~/.twinforge`.
- O login é guardado **por endereço**: entrar numa segunda instância não invalida a primeira.

## Erros comuns

- "Could not reach the TwinForge API" com URL de instância hospedada: o texto da CLI sugere `pnpm dev`; **ignore essa sugestão** (é para servidor local). Diga que a instância não respondeu. Se houver horário de funcionamento informado pelo usuário, compare com a hora atual (`date`) e diga se é o esperado; se não houver, pergunte se a instância tem horário. Depois, sugira conferir a URL e o `/api/health`.
- Perfil sem `apiBase`: não é utilizável; encaminhe para `twinforge-setup`.
