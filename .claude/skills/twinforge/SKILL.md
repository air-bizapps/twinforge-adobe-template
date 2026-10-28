---
name: twinforge
description: Usa o twinforgecli por linguagem natural. Use quando o usuário pedir algo sobre uma instância TwinForge - consultar ou criar cards/issues, ver projetos, companies, agentes, atividade, custos, aprovações, comentar, mover, atribuir. Traduz o pedido em comando consultando o catálogo vivo (twinforgecli commands). Leitura roda direto; escrita mostra o rascunho e pede confirmação. Gatilhos - "quais cards estão em andamento", "cria uma card", "lista os projetos", "comenta na issue", /twinforge, /twinforge:projects, /twinforge:explore.
---

# twinforge

Traduz pedidos em linguagem natural em comandos do `twinforgecli` e os executa na instância em uso. Funciona em qualquer pasta, sem o app aberto, e sem assumir cliente ou projeto: usa o que o `twinforgecli` do usuário já tem.

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

## Do pedido ao comando

1. **Fixe a instância** (seção acima) antes do primeiro comando.
2. **Descubra o comando no catálogo vivo**, sem lista fixa. `twinforgecli commands` imprime um JSON (array de nós com `path`, `description`, `arguments`, `options` e `commands` aninhados). É grande: filtre, não despeje. Exemplo:
   `twinforgecli commands | python3 -c "import sys,json; t=sys.argv[1].lower(); f=lambda ns: [x for n in ns for x in ([n] if t in (n['path']+' '+(n.get('description') or '')).lower() else [])+f(n.get('commands') or [])]; [print(n['path'],'-',n.get('description','')) for n in f(json.load(sys.stdin))]" "<termo>"`
   (ou use `grep -i` no texto). Depois leia a ajuda do comando escolhido: `twinforgecli <comando> --help`. Se nada no catálogo atender, diga isso; **não invente comando**.
3. **Classifique leitura ou escrita.** É leitura só o que a descrição ou a ajuda indicam como consulta (List, Get, Show, Print...). O nome engana: `issue read`, `issue unread` e `issue archive` **alteram estado**. Na dúvida, trate como escrita.
4. **Monte o comando** com `--json` quando existir, `--context "<arquivo>" --profile "<perfil>"` sempre, e `-C <companyId>` quando o comando pedir company (use a do perfil). Resolva nomes em ids consultando a listagem correspondente; **nunca peça uuid** ao usuário.
5. **Execute** conforme o tipo.

## Leitura

Execute direto, sem pedir confirmação, e resuma o resultado de forma legível (tabela curta ou lista; o essencial, não o JSON cru). Se vier vazio, diga isso e sugira por que (filtro, company, permissão).

## Escrita (rascunho e confirmação)

Mostre antes de executar:

```
Instância: <perfil> (<URL>)
Company:   <companyId>
Ação:      <o que vai acontecer, em uma frase>
Comando:   twinforgecli ... (sem segredos)
```

Espere confirmação explícita. Se o usuário recusar ou ajustar, revise o rascunho e não execute nada. Depois de executar, informe o identificador criado ou alterado.

- **Card/issue sem responsável:** crie sem `--assignee-agent-id` e avise que ninguém será acordado enquanto não houver responsável ou enquanto a card estiver em `backlog`. Atribuir um responsável fora de `backlog` aciona agentes (custo): peça confirmação específica para isso.
- Resolva o responsável pelo nome do agente (`twinforgecli agent list --json`), não por id digitado.

## `/twinforge:projects [company]`

1. Fixe a instância. Company: a do argumento (por nome ou id, resolvida com `twinforgecli company list --json`) ou a do perfil.
2. `twinforgecli project list --json -C <companyId> --context "<arquivo>" --profile "<perfil>"`; mostre os projetos por nome.
3. Sem argumento e com várias companies disponíveis, liste os projetos agrupados por company.
4. Lista vazia: explique as causas prováveis (company errada, convite incompleto, permissões), ofereça listar as outras companies e, se persistir, diga que é preciso pedir acesso aos responsáveis da instância.
É somente leitura: não peça confirmação.

## `/twinforge:explore` (modo de conversa)

1. Fixe a instância e anuncie: `Modo explore ativo em <perfil> (<URL>). Peça em linguagem natural; escrever sempre pede confirmação. Diga "sair" para encerrar.`
2. Leia o catálogo uma vez e reutilize (não repita o anúncio a cada pedido).
3. A partir daí, trate cada mensagem do usuário como um pedido: siga **Do pedido ao comando**, **Leitura** e **Escrita**.
4. Se o usuário pedir outra instância, refaça a seleção e passe a usar a nova em todos os comandos seguintes.
5. Ao ouvir "sair" (ou equivalente), confirme a saída e volte ao comportamento normal.

## Tabela de erros

Ao encontrar um destes erros, use a explicação abaixo em vez de repetir tentativas.

| O que aparece | O que dizer ao usuário |
|---|---|
| Login ausente ou expirado (`API error 401: Board authentication required`, "unauthorized") | "Você não está logado (ou o login expirou) nesta instância." Encaminhe para `/twinforge:setup` (passo de login). Não peça token. |
| `Could not reach the TwinForge API`, 503 ou "Service Unavailable" | "A instância não respondeu." Confira a hora atual e o horário de funcionamento, se o usuário o informou: fora da janela é o esperado; dentro, sugira conferir a URL e `<URL>/api/health` e avisar os responsáveis. Se o horário for desconhecido, pergunte. **Ignore a dica `pnpm dev` da CLI** (é para servidor local). Não altere configuração. |
| `API error 403: User does not have access to this company` (company inexistente ou sem acesso; o mesmo erro aparece com uuid que não existe) | Veja a linha "Company inexistente" abaixo; não trate como bloqueio do proxy. |
| 403 sem essa mensagem, "forbidden", página/mensagem de bloqueio do proxy | "O acesso foi bloqueado antes de chegar ao TwinForge ou o usuário não tem permissão." Diga o que pedir aos responsáveis (liberar o acesso, confirmar convite e permissões). Não repita. |
| Company inexistente / não pertence ao usuário (`403 ... does not have access to this company`) | Mostre a company em uso e ofereça listar as disponíveis (`company list`). |
| Comando ou opção desconhecidos | CLI possivelmente desatualizada: sugira `/twinforge:update` e consulte o catálogo de novo. |
| `E404` ao instalar `twinforgecli` | Registry npm privado interferindo; repita com `--registry https://registry.npmjs.org`. |
| `node` ausente ou < 20 | Explique o requisito do Node 20+ antes de instalar o CLI. |
| Chamada caiu em `http://localhost:3100` sem você querer | Perfil não encontrado: faltou `--context`/`--profile` corretos. Pare, refaça a seleção da instância e não escreva nada. |

## Atalhos para Adobe Commerce (opcional)

Esta seção é opcional e separada do núcleo; só use quando o projeto for Magento 2 / Adobe Commerce. O núcleo acima não depende dela.

- Um fluxo comum de agentes em projetos Magento é **Tech Lead → OpenSpec Manager → Backend → Frontend**: o Tech Lead recebe o pedido, o OpenSpec Manager transforma em mudança planejada, Backend e Frontend implementam. Ao criar uma card para esse fluxo, sugira atribuir ao primeiro papel e descrever o pedido em termos de módulo, área (frontend/adminhtml/API) e critério de aceite.
- Para pedir trabalho a partir de uma mudança já planejada, use `/twinforge:apply`.
- Exemplos de pedidos: "cria uma card para ajustar o preço especial no módulo de catálogo", "o que está em andamento no projeto de checkout?". Sempre com o rascunho e a confirmação das regras acima.
