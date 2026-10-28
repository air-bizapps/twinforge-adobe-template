---
name: bunge-card-curation
description: "Tech Lead only. Curadoria da card no Bunge: guarda o texto original da card no documento `original` e reescreve a descrição como prompt (Objetivo, Contexto, Escopo, Fora de escopo, Critérios, Referências, Perguntas), marcando a origem de cada item. Use ao pegar uma card de topo (sem parentId) que ainda não tem o documento `original`, venha ela de pessoa, do conector, do Azure DevOps ou desviada por outro agente. Vem antes do refinamento e da triagem OpenSpec."
---

# Curadoria da card (Tech Lead)

Se você não é o Tech Lead, pare: card nova sem curadoria vai para o Tech Lead
(ver a primeira checagem do seu `AGENTS.md`).

A descrição da card entra inteira no seu prompt a cada heartbeat e é a base do
plano, da proposta OpenSpec e das subtarefas. Card escrita à mão ou vinda do
cliente mistura objetivo, histórico, opinião e anexos, e quem lê depois
precisa adivinhar o que é requisito. A curadoria troca isso por um texto
organizado **sem perder nada e sem inventar nada**: o texto original fica
guardado no documento `original`, e a descrição passa a ser o prompt curado.

## Quando

- Card de topo (sem `parentId`) atribuída a você sem o documento `original`.
- Issue que outro agente desviou para você ("Recebi esta tarefa sem passar
  pelo Tech Lead"): trate como card nova.
- Já existe `original`: a curadoria já foi feita. Não sobrescreva o
  `original`; informação nova chega por comentário e entra na descrição com a
  origem `[comentário: <autor>]` (passo 4).

Vem antes de tudo do procedimento da card, inclusive da triagem OpenSpec.

## 1. Guarde o original, por comando

O original é copiado pela API, nunca redigitado: cópia feita de memória perde
detalhe. Só reescreva a descrição depois que o passo 1 conferir.

```bash
B="${TWINFORGE_API_URL%/}"; B="${B%/api}"; H="Authorization: Bearer $TWINFORGE_API_KEY"
I=<id da card>
if curl -s -H "$H" "$B/api/issues/$I/documents/original" | grep -q '"body"'; then
  echo "JA EXISTE: nao sobrescreva (va para o passo 4)"
else
  curl -s -H "$H" "$B/api/issues/$I" | python3 -c '
import json,sys
i=json.load(sys.stdin)
body="# "+i["title"]+"\n\n"+(i.get("description") or "")
print(json.dumps({"title":"Texto original da card","format":"markdown","body":body,
  "changeSummary":"Copia literal antes da curadoria"}))' \
  | curl -s -X PUT -H "$H" -H "Content-Type: application/json" -H "X-TwinForge-Run-Id: $TWINFORGE_RUN_ID" \
      -d @- "$B/api/issues/$I/documents/original" >/dev/null
fi
curl -s -H "$H" "$B/api/issues/$I" | python3 -c 'import json,sys;print(len(json.load(sys.stdin).get("description") or ""))'
curl -s -H "$H" "$B/api/issues/$I/documents/original" | python3 -c 'import json,sys;print(len(json.load(sys.stdin).get("body") or ""))'
```

O segundo número tem que ser maior que o primeiro (título + descrição). Se o
documento não existir ou vier menor, não reescreva: comente o erro e pare.

## 2. Leia o original e classifique cada informação

Para cada frase ou item do original, decida onde ele vai. Nada fica sem
destino: o que não couber em nenhuma seção vai na linha `Não usado` com o
motivo ("histórico da conversa", "duplicado de X").

Marque a origem de cada item:

- `[card]`: está **literalmente** no original. Nomes, valores, campos, URLs e
  números são copiados como vieram, não parafraseados. Detalhe que você
  acrescentou (onde fica a tela, qual módulo, qual status) não é `[card]`,
  mesmo dentro de um item que veio da card: separe ou marque `[inferido]`.
- `[comentário: <autor>]`: veio de um comentário de pessoa na card.
- `[inferido: <motivo>]`: você deduziu do próprio texto da card. O motivo é
  uma frase ("a card pede o campo no cadastro B2B, então vale só para B2B").
- `[pergunta]`: só quem pediu sabe, e a resposta muda escopo, comportamento
  ou critério. Diga quem responde.

Ponto de atenção, restrição legal ou de segurança (LGPD, auditoria, o que
precisa ser registrado) nunca vai para `Não usado`: vai em Contexto ou vira
critério, mesmo que você ache que não exige código. Quem decide que não exige
é o refinamento, por escrito no `plan`.

ID externo é o id da card no board do cliente. Ele vem do original ou de uma
pessoa, nunca do número `BUN-<n>` da TwinForge (que é só de exibição). Não
está no original → `Card: pendente` e uma `[pergunta]` pedindo o id, sem
sugerir valor.

Curadoria organiza e classifica; ela não decide a solução. Escolha técnica,
módulo a criar e desenho ficam para o refinamento (`plan`) ou para a proposta
OpenSpec. Se você estiver escrevendo "como fazer", pare: isso não é desta etapa.

## 3. Reescreva a descrição

Atualize a descrição da card (sua ferramenta TwinForge) com este corpo, nesta
ordem. Título: `BUN-<ID-externo> - <título>` só quando o ID externo for
conhecido; até lá, mantenha o título original. Escreva em português com
acentos, como o original: texto sem acento é cópia infiel (`pré-cadastro`,
não `pre-cadastro`).

```markdown
Card: <ID-externo, ou "pendente"> · Origem: <manual | conector | azure-devops | desvio de <agente>> · Original: documento `original`

## Objetivo
<1-3 frases: o resultado observável para quem pediu, com a origem>

## Contexto
<por que a demanda existe: problema, quem é afetado, restrições citadas>

## Escopo
- <o que entra, um item por linha, com a origem>

## Fora de escopo
- <o que a card exclui ou que você exclui, e para onde vai>

## Critérios de aceite
- <comportamento observável e verificável, com a origem>

## Referências
- <links, Figma, prints, docs e módulos citados, como vieram na card>

## Perguntas abertas
- [pergunta] <pergunta objetiva> → <quem responde>

Não usado do original: <item → motivo, ou "nada">
```

Regras de escrita:

- **Claro e direto.** Verbos de ação e nomes exatos; nada de "melhorar",
  "ajustar", "verificar se faz sentido" sem dizer o resultado.
- **O porquê fica.** Se a card explica a motivação, ela vai em Contexto: é o
  que permite decidir bem os detalhes depois.
- **Diga o que fazer.** Em Fora de escopo, diga para onde vai cada coisa
  ("relatório fica para card própria"), não só "não fazer".
- **Critério verificável.** "Funcionar corretamente" não é critério. Se a
  card só traz isso, escreva o comportamento que você entende
  `[inferido: ...]` ou vire pergunta.
- **Referenciar, não copiar.** Log, tabela grande ou especificação longa
  ficam no `original`; a descrição aponta: "ver documento `original`, tabela
  de preços". Mire em 80 linhas ou menos.
- **Tom normal**, em português, sem MAIÚSCULAS de ênfase.

Antes de salvar, confira a fidelidade linha a linha: para cada linha do
original, aponte onde ela está na descrição nova (seção e item) ou na linha
`Não usado`. Linha sem destino = volte e inclua.

## 4. Card que já tem `original`

Comentário de pessoa com informação nova (critério, escopo, resposta a
pergunta): edite só a seção afetada, com a origem `[comentário: <autor>]`, e
troque a `[pergunta]` respondida pelo item respondido. Não reescreva o resto.

**Acordou com a resposta de uma interaction:** este é o primeiro passo do
heartbeat, antes de planejar ou atribuir qualquer coisa. Leia as respostas
(`GET /api/issues/<id>/interactions`), escreva cada uma na descrição como
`[resposta: <autor>]` no lugar da `[pergunta]`, e só então siga. Se você já
tinha `plan` ou subtarefas, revise todo item que dependia da pergunta e
reescreva-o; uma decisão "default" que a resposta contradiz não pode
sobreviver em nenhuma descrição.

Card vinda do conector já no formato acima: confira as marcas de origem e
siga. A cópia do passo 1 vale do mesmo jeito, para a trilha ficar completa.

## 5. Gate de Definition of Ready

Depois de reescrever, a card está pronta para o refinamento quando tem:
ID externo, Objetivo, ao menos um critério verificável e nenhuma `[pergunta]`
que mude escopo. Faltou algo: crie uma interaction `ask_user_questions` na
card com as perguntas abertas (formato em `bunge-vague-demand-triage`, passo
6), mova para `in_review` e **termine o heartbeat**. Não escreva `plan`, não
crie subtarefas e não adote uma resposta "default" enquanto essa pergunta
estiver aberta: trabalho feito sobre um palpite que a pessoa depois
contradiz é retrabalho de todo o time. Pergunta menor, que você consegue decidir
com o código e as skills, não trava a card: decida no refinamento e registre
no `plan`.

## 6. Comente

```
Curadoria aplicada. Texto original em [documento original](/<prefixo>/issues/<identificador>#document-original).
Itens: <n> [card] · <n> [comentário] · <n> [inferido] · <n> [pergunta]
Próximo passo: <refinamento | triagem OpenSpec | aguardando respostas em in_review>
```

## Exemplo

Original (como chegou):

```markdown
Oi pessoal, conforme alinhado na call de ontem com o time comercial, precisamos
colocar o vendedor no cadastro de cliente B2B, porque hoje o SAP recebe o
cadastro sem vendedor e o pessoal tem que preencher na mão depois. Acho que
dava pra ser um select. Importante não quebrar o cadastro atual!! Figma:
https://www.figma.com/design/abc/Cadastro?node-id=12-34. Depois a gente vê o
relatório de vendas por vendedor.
```

Curado:

```markdown
Card: pendente · Origem: manual · Original: documento `original`

## Objetivo
O cadastro de cliente B2B passa a enviar ao SAP o vendedor responsável. [card]

## Contexto
- Hoje o SAP recebe o cadastro sem vendedor e o time comercial completa à mão depois. [card]
- Alinhado em call com o time comercial. [card]

## Escopo
- Vendedor no cadastro de cliente B2B. [card]
- Campo no formulário de cadastro B2B. [inferido: a card diz "colocar o vendedor no cadastro"; o desenho exato está no Figma]
- Vendedor incluído no envio do cadastro ao SAP. [inferido: o problema descrito é o SAP receber sem vendedor]

## Fora de escopo
- Relatório de vendas por vendedor: card própria, ainda não criada. [card]

## Critérios de aceite
- Cadastro B2B com vendedor chega ao SAP com o vendedor preenchido. [inferido: objetivo da card]
- Cadastro B2B continua funcionando como hoje para quem não informa vendedor. [inferido: "não quebrar o cadastro atual"; ver pergunta 3]

## Referências
- Figma: https://www.figma.com/design/abc/Cadastro?node-id=12-34 [card]

## Perguntas abertas
- [pergunta] Qual é o id desta card no board do cliente? O texto não traz. → quem criou a card
- [pergunta] O vendedor é escolhido de uma lista (de onde vem a lista) ou digitado? A card sugere select, sem dizer a fonte. → time comercial
- [pergunta] O vendedor é obrigatório no cadastro B2B? → time comercial

Não usado do original: saudação e "acho que" (a sugestão de select virou a pergunta 2).
```
