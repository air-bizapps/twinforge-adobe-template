---
name: twinforge-apply
description: Envia uma mudança de planejamento do OpenSpec (openspec/changes/<nome>/) para a instância TwinForge remota, onde o desenvolvimento acontece, em vez de implementar localmente. Cria uma issue pai com a proposta, anexa design e specs como documentos e cria issues filhas por grupo de tarefas, em backlog e sem responsável por padrão. Integração opcional ao OpenSpec. Gatilhos - "manda essa mudança para a instância", "enviar a proposta para o twinforge", "apply no twinforge", /twinforge:apply.
---

# twinforge-apply

Parecido com o `opsx:apply`, mas em vez de implementar na máquina do usuário, **envia a mudança para a instância remota**, e o desenvolvimento acontece lá. É uma integração opcional: só age se existir `openspec/changes/<nome>/` na pasta atual. As demais skills não dependem do OpenSpec.

Não sincroniza de volta (não marca os checkboxes de `tasks.md` quando os agentes terminam) e **não implementa código localmente**. O andamento o usuário acompanha por leituras (por exemplo, `/twinforge:explore`).

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

## 1. Ler a mudança local

1. Nome: o argumento do comando; sem argumento, se houver uma única mudança ativa em `openspec/changes/` (exclua `archive/`), proponha-a; se houver várias, liste e pergunte qual.
2. Pasta inexistente: informe que o `apply` depende de uma mudança do OpenSpec na pasta atual e **pare**, sem nenhum comando de escrita.
3. Leia `proposal.md`, `design.md`, `specs/**/spec.md` e `tasks.md`. Falta o proposal ou o `tasks.md`: diga o que falta e sugira concluir o planejamento antes de enviar.

## 2. Destino

1. Fixe a instância (seção acima) e confira company e perfil.
2. Liste os projetos com `twinforgecli project list --json -C <companyId> ...` e deixe o usuário escolher **por nome**. Company sem projetos: informe, não crie nada e sugira pedir acesso ou criar o projeto antes.

## 3. Mapeamento

| Local | Na instância |
|---|---|
| `proposal.md` | **issue pai**: `--title` com o nome da mudança, `--description` com o proposal e, no fim, o marcador `<!-- openspec-change: <nome> -->` |
| `design.md` | documento da issue pai, chave `design` (`issue document:put <paiId> design --title "Design" --body-file design.md`) |
| `specs/<capacidade>/spec.md` | documento da issue pai, chave `spec-<capacidade>` |
| grupo `## N. <título>` de `tasks.md` | **uma issue filha** por grupo: `issue create --parent-id <paiId> --title "<N. título>" --description "<itens do grupo>" --project-id <id> --status backlog` |

O conteúdo viaja **dentro** da issue: o agente remoto não enxerga a pasta local (o código ele lê do repositório que o projeto da instância já tem configurado). **Limite de tamanho:** a company expõe `attachmentMaxBytes` em `twinforgecli company list --json` (10 MiB na instância testada). Antes de enviar, compare o tamanho de cada arquivo com esse limite; se algum passar, avise no rascunho e proponha dividir ou omitir. Confirme as opções exatas com `twinforgecli issue create --help` e `issue document:put --help` antes de montar os comandos.

## 4. Sem duplicar

Antes de criar, procure no projeto de destino uma issue que contenha o marcador da mudança: `twinforgecli issue list --json -C <companyId> --project-id <id> --match "openspec-change: <nome>"`. Atenção: `--match` é um filtro **local** sobre o que a listagem devolveu, e `issue list` não tem opção de paginação nem de limite; se a listagem puder estar truncada, avise no rascunho que a busca pode não ser exaustiva.
- Achou: **não crie outra issue pai.** Mostre a issue encontrada e ofereça **atualizar** (descrição e documentos) ou **cancelar**.
- Não achou: siga para o rascunho.

## 5. Rascunho e confirmação

Antes de escrever, mostre:

```
Instância: <perfil> (<URL>)    Company: <companyId>    Projeto: <nome>
Issue pai:  <título>
Documentos: <N> (design + specs)
Filhas:     <M> (uma por grupo de tarefas)
Status:     backlog    Responsável: nenhum agente  (ninguém será acordado)
Envio anterior: <encontrado ou não, e se a busca foi exaustiva>
```

Espere confirmação explícita. Recusa ou ajuste: nada é enviado; revise o rascunho.

**Responsável padrão da company:** a company pode ter `defaultResponsibleUserId` (um usuário, não um agente), e a instância pode atribuí-lo às issues sem responsável. A skill não define responsável; depois de criar, confira com `twinforgecli issue get <id> --json` e informe o responsável efetivo.

## 6. Envio

Crie a issue pai, depois os documentos, depois as filhas. Se um comando falhar no meio: informe o que já foi criado e o que falhou, **não repita a escrita automaticamente** e ofereça retomar sem duplicar (o marcador permite reencontrar a issue pai). Ao final, liste os identificadores criados e diga que **nenhum agente foi acionado**.

## 7. Atribuir e iniciar (ação separada)

Só se o usuário pedir. Resolva o responsável pelo nome (`twinforgecli agent list --json`), mostre responsável e status previstos e **avise que isso aciona agentes e gera custo**. Execute apenas após confirmação específica.
