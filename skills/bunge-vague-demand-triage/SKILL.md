---
name: bunge-vague-demand-triage
description: "Tech Lead's triage step before creating the [OPENSPEC] Proposta issue (bunge-openspec-flow phase 1): decide what's defensible from docs/module-index.md + bunge-domain-lookup, and only escalate what's genuinely unresolvable, in a fixed format. Use on every openspec-labeled card, right after deciding it follows the OpenSpec flow and before delegating to the OpenSpec Manager."
---

# bunge-vague-demand-triage (Tech Lead)

Se você **não** é o Tech Lead, pare — isso é o passo de triagem que acontece
**antes** de criar a issue `[OPENSPEC] Proposta`, na fase 1 de
`bunge-openspec-flow`. O OpenSpec Manager continua dono do refinamento
detalhado e da escrita dos artefatos (`openspec-propose`); esta skill não
substitui aquilo, ela evita que uma demanda vaga chegue lá sem o mínimo de
chão — igual aconteceria numa reunião de kickoff com o cliente antes de
qualquer spec ser escrita.

Regra de ouro: **você decide o que dá pra decidir sozinho** (ver "Refinement
charter" no seu `AGENTS.md`) **e só pergunta o que é genuinamente
irresolvível** a partir da doc + do código. Esta skill existe para a etapa
"o que é genuinamente irresolvível" não ficar por sua conta de forma solta —
ela dá um roteiro fixo pra achar a resposta antes de perguntar, e um formato
fixo pra perguntar quando não achar.

## Quando usar

Toda card com label `openspec`, assim que você decide que ela segue o fluxo
OpenSpec (passo 1 "Triagem" de `bunge-openspec-flow`) e **antes** de criar a
issue `[OPENSPEC] Proposta` (passo 2 "Delegate"). Se a card não tem a label
mas você decidiu usar OpenSpec mesmo assim, use também.

## Passos

**1. Identifique a área/módulo.** Leia a card inteira. Ache a linha certa em
`docs/README.md` ("Qual arquivo carregar"). Se a card já cita uma tela, rota,
tabela ou módulo, confirme direto em `docs/module-index.md`. Isso te dá a doc
de domínio certa (ex.: cadastro B2B → `docs/cadastro-de-cliente.md`) e a lista
de módulos Webjump envolvidos.

**2. Rode `bunge-domain-lookup` para cada regra que a demanda plausivelmente
toca.** Não é uma pergunta genérica — é uma por regra concreta que a mudança
pode afetar (ex.: "como o cadastro B2B é validado e enviado pro SAP hoje?",
"que campos o payload da integração já carrega?", "existe algum campo
parecido — código de vendedor, código de representante — em outro fluxo?").
Guarde o resultado classificado (documentada/só no código/divergente/não
encontrada) — ele vira a base da seção `## Regras de negócio consultadas`
que o OpenSpec Manager já preenche em `design.md`; você está adiantando esse
trabalho, não duplicando-o à toa.

**3. Critério de aceite.** Se a card já tem critério de aceite explícito, siga
para o passo 4. Se **não tem** (comum em demanda vaga passada de boca em
boca), redija você mesmo, usando o que o passo 2 encontrou + os padrões de
`requirements-refinement-fundamentals` (INVEST, Definition of Ready —
objetivo + critério de aceite observável + restrições conhecidas). Marque
cada critério que você redigiu com `[inferido: <motivo>]`, a mesma marca da
curadoria (`bunge-card-curation`), e acrescente-o à seção Critérios de aceite
da descrição da card — isso importa pro OpenSpec Manager e pros devs saberem
o que é fato dado pelo cliente e o que é sua inferência, caso precisem
questionar depois.

**4. Ache os pontos genuinamente em aberto.** Depois dos passos 2 e 3, liste
o que **nem a doc, nem o código, nem julgamento de engenharia comum**
resolvem — só o cliente sabe. Em demanda de integração/cadastro, os pontos
que tipicamente caem aqui:
- **Identificador do valor novo**: por nome, código (de qual sistema —
  SAP, Magento, outro?), e-mail, ou combinação? Qual o formato/tamanho?
- **Obrigatoriedade**: campo obrigatório no cadastro, opcional, ou obrigatório
  só em certos cenários (ex. só B2B, só um tipo de cliente)?
- **Validação**: precisa validar contra uma lista/tabela existente (ex. lista
  de vendedores válidos) antes de aceitar, ou aceita livre?
- **Direção e timing da integração**: o campo novo é enviado no mesmo payload
  que já existe, ou é uma chamada separada? Síncrono (bloqueia o cadastro se
  a API falhar) ou assíncrono (grava local e reenvia depois)?
- **Comportamento em erro**: se a API do BTP/SAP rejeitar o valor, o cadastro
  falha inteiro ou salva sem o campo e sinaliza pendência?
- **Cadastro existente**: clientes já cadastrados sem esse campo — precisa de
  uma migração/backfill, ou fica em branco até o cliente editar?

Ponto que o passo 2 já respondeu (ex. já existe um campo parecido com um
padrão claro) **não** vira pergunta — vira decisão documentada no passo 3,
citando o precedente (arquivo:linha). Só pergunta o que sobrou.

**5. Nada em aberto → siga direto para o passo "Delegate" de
`bunge-openspec-flow`,** com o critério de aceite (passo 3) e as regras
consultadas (passo 2) escritos na descrição da issue `[OPENSPEC] Proposta`
que você vai criar, numa seção `## Achados da triagem (Tech Lead)`.

**6. Algo em aberto → pergunte, não suponha.** Crie uma interaction
`ask_user_questions` **na card pai** (a demanda original, não uma issue nova):

```bash
curl -s -X POST "$TWINFORGE_API_URL/issues/<CARD_ID>/interactions" \
  -H "Authorization: Bearer $TWINFORGE_API_KEY" -H "Content-Type: application/json" \
  -d '{
  "kind": "ask_user_questions",
  "payload": {
    "version": 1,
    "title": "Preciso confirmar antes de abrir a proposta OpenSpec",
    "questions": [
      {
        "id": "vendedor-identificador",
        "prompt": "O campo \"vendedor\" no cadastro B2B deve ser informado por qual identificador?",
        "helpText": "Isso define o tipo de input (texto livre vs. select validado) e o payload enviado ao BTP.",
        "selectionMode": "single",
        "required": true,
        "options": [
          {"id": "codigo-sap", "label": "Código do vendedor no SAP", "description": "Mesmo padrão de outros campos que já referenciam código SAP no cadastro (ex. sapcode em BungeListPrice)."},
          {"id": "nome-livre", "label": "Nome do vendedor, texto livre", "description": "Sem validação contra uma lista — o usuário digita o nome."},
          {"id": "select-lista", "label": "Seleção de uma lista de vendedores cadastrados", "description": "Precisa de uma fonte de dados (tabela/API) com a lista de vendedores válidos."}
        ]
      }
    ]
  }
}'
```

Cada pergunta tem 2-4 opções **plausíveis e específicas ao domínio** (não
"sim"/"não" genérico) — se o passo 2 achou um precedente real no código, cite
o arquivo:linha na `description` de uma das opções, do jeito que o exemplo
acima cita `BungeListPrice`. `selectionMode: "single"` para escolha única,
`"multi"` quando mais de uma opção pode ser verdadeira ao mesmo tempo. Quem
responde sempre pode digitar algo fora das opções — o formulário do
TwinForge já cobre isso, não crie uma opção "outro" manual.

Se seu ambiente expõe uma ferramenta própria (MCP/tool) para criar interaction
de issue, use-a com esse mesmo payload em vez do `curl` acima — o `curl` é o
fallback quando não há. **Depois** de criar a interaction, o comentário e a
mudança de status (`in_review`) seguem a regra normal do seu `AGENTS.md`
("Safety and permissions": sempre pela sua ferramenta/MCP do TwinForge, nunca
`twinforgecli` cru) — comente em uma frase o que está sendo perguntado e por
quê (não repita o JSON) e mova a card para `in_review`. Pare o heartbeat — a
resposta acorda você automaticamente (`continuationPolicy` padrão do
`ask_user_questions` é `wake_assignee`).

**7. Ao ser acordado com a resposta.** Leia `GET /api/issues/<CARD_ID>/interactions`
para pegar as respostas (`answers[].optionIds` e `answers[].otherText` se a
pessoa escreveu algo livre). Incorpore a resposta ao critério de aceite do
passo 3 e à seção de regras do passo 2. Se a resposta abrir uma pergunta nova
genuinamente irresolvível, repita o passo 6 uma vez — não entre num
loop de perguntas; duas rodadas sem fechar é sinal de escalar pro PO em vez
de insistir. Depois, siga para o passo "Delegate" normalmente.

## O que isso muda no handoff pro OpenSpec Manager

A issue `[OPENSPEC] Proposta` que você cria no passo "Delegate" de
`bunge-openspec-flow` agora sempre carrega, além do que já carregava:

```
## Achados da triagem (Tech Lead)

### Critério de aceite
<critério que a card já tinha, OU o que você redigiu no passo 3 — marcado como tal>

### Regras de negócio consultadas
<saída classificada do passo 2, uma por regra>

### Pontos esclarecidos com o cliente
<pergunta feita no passo 6 + resposta escolhida, se houve; "nenhum" se não precisou>
```

O OpenSpec Manager (`openspec-propose`) usa isso como ponto de partida — ele
ainda confirma contra o código antes de escrever `design.md` (código é sempre
a fonte de verdade), mas não repete a busca do zero nem refaz a pergunta que
você já fez, a menos que encontre algo que contradiga o que está aqui.

