---
name: bunge-subtask-contract
description: "Contrato de contexto entre Tech Lead, OpenSpec Manager e devs (Backend/Frontend) no Bunge: como escrever a descrição de uma subtarefa de dev, o que o dev lê ao recebê-la, o formato do relatório de volta, o gate do Tech Lead entre subtarefas e a nota de estado da card. Use ao criar, receber, entregar ou fechar qualquer subtarefa de dev, com ou sem OpenSpec."
---

# Contrato da subtarefa (Bunge)

Cada subtarefa roda num heartbeat novo, sem memória da conversa que a criou.
**O que o dev sabe sobre a tarefa é só o que está na descrição da subtarefa.**
Por isso a descrição é o contexto curado da execução: tudo que o dev precisa,
e nada que ele não precisa. O relatório de volta é o resumo que o Tech Lead
usa para decidir o próximo passo, sem reler o trabalho todo.

Princípio: **referenciar, não copiar.** Aponte o caminho exato do arquivo,
doc ou skill em vez de colar o conteúdo. Cópia desatualiza; caminho não.

## 1. Escrevendo a subtarefa (Tech Lead, ou OpenSpec Manager em card `openspec`)

A descrição da subtarefa é um prompt: é o texto que o dev recebe como
tarefa no início do run. Escreva-a com as mesmas regras de um bom prompt.

### Teste do colega

Antes de criar, leia a descrição como se você fosse um dev competente que
**nunca viu esta card**, não tem acesso ao plano e não pode te perguntar
nada. Ele saberia o que fazer, onde mexer, o que deixar para depois e como
provar que terminou? Se precisaria adivinhar qualquer uma dessas coisas, a
descrição não está pronta.

### Corpo da descrição, nesta ordem

Em card `openspec`, as 4 linhas de cabeçalho de `bunge-openspec-flow` vêm
antes de tudo, intactas.

```markdown
Branch: feature/BUN-<ID-externo> · Worktree: $WS/worktrees/<ID-externo>
Depende de: <BUN-n da subtarefa anterior, ou "nenhuma">

## Objetivo
<1-3 frases: o resultado observável quando esta subtarefa terminar>

## Contexto
<por que esta subtarefa existe e as decisões do plano que valem para ela,
1 frase cada. É o "porquê" que permite ao dev decidir bem os detalhes>

## Entradas (leia só isto)
- <caminho exato: arquivo a alterar, módulo de referência, docs/<área>.md>
- <seção exata de design.md/tasks.md, quando houver>

## Skills
- <até 3 slugs exatos da lista do dev, ex. adobe-create-repository>

## Passos
1. <ação concreta, quando a ordem importa; omita se o objetivo já basta>

## Fora de escopo
- <o que fica para outra subtarefa, e qual: "a tela de admin fica para BUN-n">

## Critérios de aceite (prova exigida)
- <comportamento observável> → prova: <arquivo:linha | saída de comando>

## Se faltar algo
Se a descrição, as Entradas ou as skills não bastarem para decidir algo,
você não precisa adivinhar: comente a lacuna (o que falta, onde procurou)
e mova para `blocked` com o Tech Lead como dono, conforme
`blocked-exige-blocker-declarado`. Reportar uma lacuna é uma entrega válida.
```

### Regras de escrita (engenharia de prompt da Anthropic aplicada)

- **Claro e direto.** Uma subtarefa, um objetivo. Verbos de ação ("crie",
  "exponha", "valide") e nomes exatos. Nada de "melhorar", "ajustar",
  "verificar se faz sentido".
- **Dê o porquê.** Uma regra com motivo é seguida melhor e generalizada
  certo. "Use `addData()`, porque o PUT é parcial e `setData()` apagaria os
  campos não enviados" vale mais do que "use `addData()`".
- **Diga o que fazer, não só o que evitar.** Em vez de "não mexa no admin",
  "a tela de admin fica para BUN-n; aqui só a API".
- **Seções separadas e na mesma ordem sempre.** O dev aprende onde está cada
  coisa. Não misture critério com passo, nem contexto com entrada.
- **Autocontida.** O dev não lê a card pai, o plano nem as subtarefas irmãs.
  A decisão do plano que importa vai em Contexto, numa frase.
- **Caminhos conferidos.** Todo arquivo, classe ou rota citado existe
  (`git show origin/$BUNGE_BASE_BRANCH:<caminho>`) ou está marcado "a criar".
  Nome escrito de memória é a causa mais comum de retrabalho.
- **Skills pelo slug exato**, copiado da lista do dev, nunca de memória (um
  slug parecido, como `adobe-` no lugar de `bunge-`, aponta para nada).
  Todo agente vê no índice skills que não são dele, então esta lista é o que
  diz ao dev quais abrir. Pegue a lista assim:
  ```bash
  B="${TWINFORGE_API_URL%/}"; B="${B%/api}"
  curl -s -H "Authorization: Bearer $TWINFORGE_API_KEY" "$B/api/agents/<id do dev>/skills" \
    | python3 -c 'import json,sys;print("\n".join(s.split("/")[-1] for s in json.load(sys.stdin)["desiredSkills"]))'
  ```
  Não liste as que ele já abre por padrão (`bunge-engineering-practices`,
  `bunge-subtask-contract` e a `bunge-*-code-quality` da especialidade).
- **Critério verificável, com a prova pedida.** "Funciona" ou "código
  revisado" não servem. Diga o que o relatório precisa mostrar.
- **Permita o "não sei".** A seção "Se faltar algo" existe para que o dev
  reporte em vez de inventar. Não a remova nem a enfraqueça.
- **Tom normal.** Sem MAIÚSCULAS, "CRÍTICO" ou "NUNCA" repetidos: em modelos
  atuais isso faz a regra pesar demais e atrapalhar o resto. As proibições
  de verdade (push, PR) já estão nas skills do dev; não repita.
- **Sem push, sem PR.** A subtarefa termina em commit local + relatório.
- **Curta.** Mire em 60 linhas ou menos. Lista longa de tarefas vai para
  `tasks.md` (OpenSpec) ou para o documento `plan`, e a subtarefa aponta o trecho.

### Exemplo preenchido

Card comum (sem OpenSpec), segunda subtarefa de "CRUD via API REST para
`bunge_listprice`". Os caminhos são ilustrativos: confira os reais antes.

```markdown
Branch: feature/BUN-9117 · Worktree: $WS/worktrees/9117
Depende de: BUN-211 (Preparar ambiente)

## Objetivo
Expor create, update e delete de `bunge_listprice` como rotas REST
autenticadas por ACL de admin, reaproveitando o repositório que já existe.

## Contexto
- A SAP já grava nessa tabela pelo módulo `Webjump_BungeListPrice`; esta API
  é para o time de pricing corrigir registros sem passar pela SAP.
- Decisão do plano: módulo novo `Webjump_BungeListPriceApi`, para não mexer
  nas rotas que a SAP usa hoje.
- O PUT é parcial: o cliente manda só os campos que mudam.

## Entradas (leia só isto)
- app/code/Webjump/BungeListPrice/Api/ListPriceRepositoryInterface.php (repositório a reaproveitar)
- app/code/Webjump/BungeListPrice/etc/webapi.xml (rotas atuais, que não podem mudar)
- docs/precificacao-impostos.md, seção "1. Alimentação das tabelas de preço base"

## Skills
- adobe-create-repository
- adobe-create-controller
- adobe-create-unit-tests

## Passos
1. Crie o módulo `Webjump_BungeListPriceApi` com sequence para `Webjump_BungeListPrice`.
2. Crie o recurso ACL `Webjump_BungeListPriceApi::rest` e as rotas POST, PUT e DELETE em `/V1/bunge-listprice`.
3. Implemente o service com `addData()` no update, porque o PUT é parcial.
4. Escreva os testes unitários do service, sem rodar.

## Fora de escopo
- Rotas GET: ficam para BUN-213.
- Tela de admin: card própria, ainda não criada.
- Qualquer mudança em `Webjump_BungeListPrice`.

## Critérios de aceite (prova exigida)
- As 3 rotas existem com o recurso ACL acima → prova: arquivo:linha no webapi.xml.
- Update parcial preserva os campos não enviados → prova: arquivo:linha com `addData`.
- As rotas atuais da SAP não mudaram → prova: `git diff --stat` sem arquivos de `Webjump/BungeListPrice`.
- Service com teste unitário escrito → prova: caminho do teste e nomes dos métodos.

## Se faltar algo
Se a descrição, as Entradas ou as skills não bastarem para decidir algo,
você não precisa adivinhar: comente a lacuna (o que falta, onde procurou)
e mova para `blocked` com o Tech Lead como dono, conforme
`blocked-exige-blocker-declarado`. Reportar uma lacuna é uma entrega válida.
```

### Antes de criar: checklist

Marque um item só depois de conferir, não por intenção. Caminho e slug se
conferem por comando.

- [ ] Passa no teste do colega.
- [ ] Objetivo em até 3 frases, com resultado observável.
- [ ] Contexto traz o porquê e as decisões do plano que valem aqui.
- [ ] Todo caminho em Entradas foi conferido ou está marcado "a criar".
- [ ] Até 3 skills, cada slug copiado da saída do comando acima.
- [ ] Fora de escopo diz para onde vai cada coisa excluída.
- [ ] Todo critério tem a prova pedida.
- [ ] Nenhum `git push` ou `gh pr` no texto.

Depois de criar todas as subtarefas da card, confira a cobertura: cada item de
Escopo e de Critérios de aceite da card curada aparece nos Critérios de alguma
subtarefa ou no Fora de escopo de alguma, com destino. Item sem lugar é
requisito perdido. Registre a correspondência no `plan` (item → BUN-n).

## 2. Recebendo a subtarefa (Backend / Frontend)

1. Leia a descrição inteira antes de qualquer comando.
2. Faltou `Branch`, `Objetivo` ou `Critérios`? Não adivinhe: lacuna →
   `blocked` (seção "Se faltar algo"). Pare.
3. Entre no worktree indicado (`bunge-engineering-practices`) e confirme
   `git branch --show-current`.
4. Abra **só** as Entradas e as Skills listadas. Outro arquivo ou skill entra
   quando uma Entrada aponta para ele ou quando o código que você está lendo
   exige; nunca "para garantir".
5. Não leia a card pai nem as subtarefas irmãs. Se precisar delas para
   entender a tarefa, isso é lacuna da descrição: reporte.
6. Algo em Fora de escopo parece necessário? Não faça. Comente e deixe o
   Tech Lead decidir.

## 3. Relatório de entrega (dev → Tech Lead)

Use o template de `bunge-engineering-practices` (card comum) ou de
`openspec-apply-change` (card `openspec`). Os dois exigem:

- **Prova de commit:** cole a saída real de
  `git log --oneline -1` e de `git log origin/<branch>..HEAD --oneline`.
  Hash escrito de memória não vale.
- **Critérios:** cada critério de aceite (ou cenário) com a prova pedida,
  na mesma ordem da descrição.
- **Verificação:** a última linha real do `magento-verify`
  (`VERIFY OK ...`), da skill `adobe-magento-toolbox`.
- **Lacunas:** o que faltou na descrição, nas Entradas ou nas skills para
  fazer sem adivinhar, ou "sem lacunas".

Seja breve: 40 linhas ou menos. O relatório é o resumo da execução, não o
diário dela.

## 4. Atribuição e gate do Tech Lead

**Atribuir** uma subtarefa de dev é uma chamada só: responsável, `todo` e o
estágio do Verificador (o `issue update` do CLI não tem o campo):

```bash
curl -s -X PATCH "${TWINFORGE_API_URL%/api}/api/issues/<id da subtarefa>" \
  -H "Authorization: Bearer $TWINFORGE_API_KEY" -H 'Content-Type: application/json' \
  -d '{"assigneeAgentId":"<dev>","status":"todo","executionPolicy":{"stages":[{"type":"review","participants":[{"type":"agent","agentId":"{{uuid}}"}]}]}}'
```

`assigneeAgentId` é o dev: Backend `{{uuid}}`, Frontend
`{{uuid}}`. O id `465b84e9-…` é o do Verificador e só vai em
`participants`: atribuída a ele, a subtarefa não roda e fica `blocked` ("Verificador so revisa").

Com o estágio, o `done` do dev vira `in_review` com o Verificador, que roda o
`magento-verify` e aprova ou devolve ao dev com os itens (até 3 rodadas).

**Gate.** Quando uma subtarefa de dev chega a `done`, antes de atribuir a
próxima, leia o último comentário do Verificador nela:

- `VERIFY OK`: siga o gate abaixo.
- `Observacao (nao bloqueia)`: o Verificador está em modo observação e só registrou o que viu. Siga o
  gate abaixo: o seu `magento-verify` decide.
- `ESCALATED`: o código **não** passou. Card `in_review` + interação nomeando
  o Kaio, com o comentário do Verificador; não atribua a próxima.
- Nenhum comentário do Verificador: a subtarefa pulou o estágio. Siga o gate
  abaixo, que reabre se o `magento-verify` falhar.

```bash
WT="$WS/worktrees/<ID-externo>"
git -C "$WT" branch --show-current                  # = Branch do cabeçalho
git -C "$WT" cat-file -e <hash do relatório>        # o commit existe
git -C "$WT" log "origin/<branch>..HEAD" --oneline  # lista o commit (sem push)
T=$(dirname "$(find /twinforge/.pi/agent/skills "$HOME" -path '*adobe-magento-toolbox/scripts/magento-verify.php' -print -quit 2>/dev/null)")
(cd "$WT" && php "$T/magento-verify.php" --base "origin/$BUNGE_BASE_BRANCH")   # precisa dar VERIFY OK
```

E confira no texto do relatório: todo critério da descrição tem uma prova.
Isso **não** é reverificar a qualidade do código (isso não se faz mais);
é conferir que a entrega existe e carrega. Já houve relatório inteiramente
fabricado, e já houve entrega com XML inválido, classe inexistente e form que
fica em spinner infinito; o `magento-verify` roda aqui, não só no relatório do
dev. A saída dele já diz arquivo, linha e o que fazer: reabra com ela.

Falhou qualquer item: reabra a subtarefa com a evidência (comando + saída),
reatribua ao mesmo dev e não libere a próxima. Passou: atualize a nota de
estado (abaixo) e atribua a próxima, pela regra de uma por vez.

## 5. Nota de estado da card (Tech Lead)

O Tech Lead mantém na card um documento de issue com a chave `estado`,
reescrito a cada heartbeat em que algo muda. No heartbeat seguinte, leia
este documento primeiro, antes do histórico de comentários.

```markdown
Card: <ID-externo> · Caminho: openspec | comum · Branch: feature/BUN-<ID-externo>
Subtarefas:
- BUN-n [BACK] <resumo> — done · commit <hash> · gate ok
- BUN-m [FRONT] <resumo> — atribuída, aguardando
Decisões: <decisões que valem para as próximas subtarefas, 1 linha cada>
Próximo passo: <uma ação concreta>
```

Mantenha em 25 linhas ou menos. O que não ajuda a decidir o próximo passo
não entra.
