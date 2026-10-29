---
name: twinforge-diagnostico
description: Verifica, corrige e atualiza o próprio twinforgecli. Use quando o usuário pedir para checar se o CLI está funcionando, quando algo do twinforgecli falhar sem causa clara, ou para atualizar o CLI. Gatilhos - "o twinforgecli não funciona", "está tudo certo com o CLI", "checar pré-requisitos", "atualizar o twinforgecli", /twinforge:status, /twinforge:fix, /twinforge:update.
---

# twinforge-diagnostico

Três fluxos: **status** (só leitura), **fix** (corrige com confirmação) e **update** (atualiza o CLI). Não usa `twinforgecli doctor`: ele diagnostica o setup do servidor local (configuração e banco), não os pré-requisitos de quem usa o CLI contra uma instância hospedada.

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

Os itens 1 a 4 da lista abaixo não dependem de instância; rode-os mesmo sem instância fixada. Os itens 5 a 8 precisam dela.

## Status (somente leitura)

Rode as checagens em ordem, sem alterar nada e sem exibir credenciais. Classifique cada item como **OK**, **ATENÇÃO** ou **FALHA**.

| # | Item | Como verificar | Resultado |
|---|---|---|---|
| 1 | Node 20+ e npm | `node --version`, `npm --version` | FALHA se faltar ou for < 20 |
| 2 | CLI instalado e no PATH | `twinforgecli --version` | FALHA se o comando não existir |
| 3 | Versão instalada × publicada | `npm view twinforgecli version` e compare | ATENÇÃO se instalada < publicada |
| 4 | Registry público alcançável | `npm config get registry`; se `npm view` der `E404`/falha e o registry não for `https://registry.npmjs.org`, é registry privado interferindo | ATENÇÃO/FALHA conforme o caso |
| 5 | Arquivos de contexto e credenciais válidos | veja "Validar JSON" abaixo; arquivos: o contexto global, o da pasta (se houver) e `auth.json` do diretório de dados | FALHA se existir e não for JSON válido; arquivo inexistente = ATENÇÃO "sem configuração" (instalação nova), e `context list` devolve só o perfil `default` vazio |
| 6 | Login da instância da sessão | `twinforgecli auth whoami --context "<arquivo>" --profile "<perfil>"` | FALHA se não houver login; "sem resposta" se a rede falhar |
| 7 | Instância no ar | `curl -fsS -m 10 "<apiBase>/api/health"` | FALHA se não responder; trate **503** conforme "Instância fora do ar e horário de funcionamento" |
| 8 | Company acessível | `twinforgecli company list --json --context "<arquivo>" --profile "<perfil>"` e confira que o `companyId` do perfil está na lista | FALHA se ausente |

**Validar JSON sem mostrar o conteúdo:** `node -e 'try{JSON.parse(require("fs").readFileSync(process.argv[1],"utf8"));console.log("OK")}catch(e){console.log("INVALIDO: "+e.message)}' "<arquivo>"`. Nunca faça `cat` do `auth.json`. O diretório de dados é `${TWINFORGE_HOME:-$HOME/.twinforge}`.

Termine SEMPRE com uma conclusão explícita:
- **Tudo certo**: todos OK; não sugira `fix`.
- **Precisa de correção**: liste os itens FALHA e sugira `/twinforge:fix`.
- **Só atenção** (por exemplo, CLI desatualizado): sugira `/twinforge:update`.
Se um item falhar, os que dependem dele (por exemplo, 6 a 8 sem CLI) aparecem como **não verificados**, não como falha.

## Instância fora do ar e horário de funcionamento

Instâncias hospedadas podem ser ligadas e desligadas em horários fixos (por exemplo, subir de manhã e cair à noite). Quando a pergunta for "a instância está no ar?" ou aparecer **503**, "Service Unavailable", `Could not reach the TwinForge API` ou falha em `/api/health` (instância desligada de verdade costuma dar `Could not reach` / `fetch failed` e curl com código `000`, não 503; trate igual):

1. Confira a hora atual (`date`).
2. Verifique se o usuário já informou um horário de funcionamento (nesta conversa, ou no contexto/memória que você tiver). Se sim, diga se agora está **dentro ou fora** da janela: fora = é o esperado, tente de novo quando abrir; dentro = problema real, avise os responsáveis.
3. Se não houver horário conhecido, diga que pode ser horário de funcionamento e **pergunte** se a instância tem um. Se o usuário informar, use-o no restante da sessão e ofereça registrá-lo na memória dele para as próximas.
4. Nunca afirme um horário que você não recebeu do usuário.

**Falha que o status não explica:** se a mensagem não casar com nenhuma linha acima nem com a tabela de erros, e o MCP `twinforge-docs` estiver disponível, busque-a com `search_docs` (só termos gerais; nunca token, id ou URL da instância) e cite a página usada. Sem o MCP, siga só com o que o status mostrou. A doc explica o comportamento do produto; o diagnóstico continua vindo das checagens.

## Fix (corrige com confirmação)

1. Rode todas as checagens do status.
2. Para cada FALHA ou ATENÇÃO, proponha um reparo específico:

| Problema | Reparo |
|---|---|
| Node ausente ou < 20 | Explique o requisito e oriente instalar (por exemplo, via gerenciador de versões do Node). Não instale o Node por conta própria. |
| CLI ausente | `npm install -g twinforgecli` |
| CLI desatualizado | Encaminhe para o fluxo **update** |
| `E404` com registry privado | repita a instalação com `--registry https://registry.npmjs.org` |
| JSON de contexto inválido | Faça cópia de segurança (`<arquivo>.bak-<data>`), explique o que será recriado e recrie o perfil (`twinforgecli context set --context "<arquivo>" --profile <nome> --api-base <URL> --company-id <id> --persona board --use`, com os valores do backup se legíveis, senão pelo `twinforge-setup`). Depois refaça as checagens: o login costuma continuar pendente (401) e segue para o `twinforge-setup` |
| Login ausente ou expirado | Encaminhe para o login do `twinforge-setup`; não peça token nem senha |
| Perfil sem `apiBase` | Encaminhe para o `twinforge-setup` |
| Company do perfil não encontrada | Mostre as companies disponíveis e proponha atualizar o perfil com `twinforgecli context set` |
| Instância fora do ar (503, sem resposta), 403 do proxy | **Nenhum reparo local.** Explique a causa (considerando o horário de funcionamento, se conhecido) e o que pedir aos responsáveis (confirmar a URL, o acesso e o status da instância) |

3. Mostre o **plano completo** (itens e comandos) e espere confirmação explícita. Sem confirmação, não execute nada.
4. Antes de tocar em qualquer arquivo do usuário, faça cópia de segurança.
5. Execute, refaça as checagens e relate o que foi resolvido e o que continua pendente.

## Update

1. Mostre a versão instalada (`twinforgecli --version`) e a publicada (`npm view twinforgecli version`).
2. Já na última versão: informe e **não execute nada**.
3. Senão, peça confirmação e rode `npm install -g twinforgecli@latest` (com `--registry https://registry.npmjs.org` se o registry privado interferir).
4. Depois: confira a nova versão e rode `auth whoami` na instância da sessão (se houver) para confirmar que login e perfil continuam válidos. Relate versão anterior, nova e o resultado.
5. Falha por permissão (`EACCES`): explique a causa; **não sugira `sudo` por padrão**. Oriente usar um gerenciador de versões do Node ou configurar um prefixo global do npm no diretório do usuário. Não repita a tentativa sem nova confirmação.
