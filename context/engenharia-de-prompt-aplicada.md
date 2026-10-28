# Engenharia de prompt aplicada no fluxo — onde está e como enxergar

Guia prático: quais textos do fluxo viram prompt de um agente, quem escreve cada um, que
técnica de prompt da Anthropic ele segue e **onde você vê o resultado na UI** da TwinForge.
Os princípios, o status de validação e o que ficou de fora estão em
`context/engenharia-de-contexto-e-prompt.md`; este arquivo mostra a aplicação.

Sem dados de instância. As regras em si moram nas skills e nos `AGENTS.md` citados; aqui só
se aponta para elas.

## Por que esses textos são prompts

Na TwinForge o agente não recebe uma conversa: cada heartbeat começa com sessão nova, e o
texto da issue que o acordou entra inteiro no prompt. Então a descrição da card, a descrição
da subtarefa, o relatório e a nota de estado **são** os prompts do fluxo. Escrever bem esses
textos é a engenharia de prompt; o resto é contexto que o agente busca sob demanda.

## Mapa dos textos

| Texto | Quem escreve | Quem lê como prompt | Regra | Onde ver na UI |
|---|---|---|---|---|
| Descrição da card (curada) | Tech Lead | Tech Lead, OpenSpec Manager | `bunge-card-curation` | Descrição da card; texto bruto no documento `original` |
| Descrição da subtarefa | Tech Lead; OpenSpec Manager em card `openspec` | Backend / Frontend | `bunge-subtask-contract` §1 | Descrição da subtarefa |
| Relatório de entrega | Backend / Frontend | Tech Lead (gate e PR) | `bunge-subtask-contract` §3 | Último comentário da subtarefa |
| Nota de estado | Tech Lead | Tech Lead no próximo heartbeat | `bunge-subtask-contract` §5 | Documento `estado` da card |
| Perguntas a pessoas | Tech Lead, OpenSpec Manager | Pessoa que responde | `bunge-vague-demand-triage` passo 6 | Interaction na card, card em `in_review` |
| Aviso de desvio | Devs, OpenSpec Manager, DevOps | Tech Lead | "First check" no `AGENTS.md` de cada um | Comentário na issue desviada |

## Técnicas e onde cada uma aparece

| Técnica (Anthropic) | Card curada | Subtarefa | Relatório |
|---|---|---|---|
| Claro e direto: um objetivo, verbos de ação, nomes exatos | Objetivo | Objetivo, Passos | — |
| Dar o porquê | Contexto | Contexto | — |
| Dizer o que fazer, não só o que evitar | Fora de escopo com destino | Fora de escopo com destino | — |
| Estrutura fixa, mesmas seções na mesma ordem | Template de 7 seções | Template do contrato | Template do relatório |
| Exemplo canônico, um só | Exemplo antes/depois na skill | Exemplo BUN-9117 no contrato | — |
| Permitir "não sei" | `[pergunta]` + gate de DoR | "Se faltar algo" | Linha `Lacunas` |
| Critério verificável | Critérios de aceite | Critério → prova exigida | Uma prova por critério |
| Prova por comando, não por relato | Cópia do `original` por API | Checagem de integridade no gate | Linha `Integridade` com a saída real |
| Separar fato de inferência | Marcas `[card]`, `[comentário]`, `[inferido]`, `[pergunta]` | Entradas conferidas por comando | Saída real do `git log` |
| Tom normal, sem MAIÚSCULAS de ênfase | Regra de escrita | Regra de escrita | — |
| Curto, referenciar em vez de copiar | ≤ 80 linhas; o longo fica no `original` | ≤ 60 linhas | ≤ 40 linhas |

## Curadoria da card

**O que acontece.** Quando o Tech Lead pega uma card de topo sem o documento `original`:

1. Copia o título e a descrição para o documento `original` **por comando** (a cópia não
   passa pelo modelo, então não perde detalhe) e confere o tamanho antes de seguir.
2. Classifica cada informação do original numa seção, marcando a origem. Nada fica sem
   destino: o que não serve vai na linha `Não usado do original`, com o motivo.
3. Reescreve a descrição no template: Objetivo, Contexto, Escopo, Fora de escopo,
   Critérios de aceite, Referências, Perguntas abertas.
4. Passa pelo gate de Definition of Ready: ID externo, objetivo, um critério verificável e
   nenhuma pergunta que mude escopo. Faltou algo → pergunta às pessoas, card em `in_review`.
5. Comenta com a contagem de itens por origem e o próximo passo.

**Por que reescrever a descrição e não só anexar a versão curada.** A descrição é o que entra
no prompt a cada heartbeat. Se a versão curada ficasse num documento à parte, o texto bruto
continuaria sendo o prompt.

**O que a curadoria não faz.** Não decide a solução (isso é o `plan` ou a proposta OpenSpec)
e não inventa requisito: inferência só com motivo escrito, e o que só quem pediu sabe vira
pergunta.

**Como você enxerga na UI:**

- Descrição da card: a versão curada, com as marcas de origem em cada item.
- Documento `original`: o texto como chegou, com histórico de revisões.
- Comentário "Curadoria aplicada": quantos itens vieram da card, quantos foram inferidos e
  quantas perguntas ficaram abertas.

Para conferir uma curadoria, compare o `original` com a descrição: todo item do original
deve aparecer numa seção ou na linha `Não usado`. Um `[inferido]` que você não aceita se
corrige com um comentário na card, e o Tech Lead atualiza só aquela seção.

Exemplo completo, de uma card escrita à mão até a versão curada: seção "Exemplo" de
`skills/bunge-card-curation/SKILL.md`.

## Desvio na porta de entrada

Tarefa atribuída direto a um dev, ao OpenSpec Manager ou ao DevOps, sem passar pelo Tech
Lead, pula a curadoria e o plano: não tem branch, worktree nem critério. Por isso o
`AGENTS.md` desses agentes começa com uma checagem.

- A tarefa é legítima se tem card pai, se foi criada por outro agente ou se a descrição
  tem a linha `Atribuição direta: <motivo>`.
- Sem nenhum desses, o dev ou o OpenSpec Manager não começa: reatribui ao Tech Lead e
  comenta. O DevOps primeiro confere se é infraestrutura (pedido direto de infraestrutura é
  normal e ele executa); trabalho de produto vai para o Tech Lead.
- A checagem só vale para tarefas atribuídas ao próprio agente; menção em issue de outro
  nunca é desviada.
- O Tech Lead trata a issue desviada como card nova. Se for mesmo uma tarefa pequena para
  um especialista, ele acrescenta `Atribuição direta:` antes de devolver; sem a linha, ela
  voltaria para ele.

**Quando usar `Atribuição direta:` de propósito.** Issue de calibração ou ajuste pontual que
você quer mandar direto a um agente, sem passar pelo Tech Lead: escreva a linha com o motivo
na descrição.

## Por que as regras ficam no `AGENTS.md`

No adapter `pi_local` só o `AGENTS.md` entra no prompt. `HEARTBEAT.md`, `SOUL.md`,
`TOOLS.md` e `CONTEXT.md` do pacote ficam guardados na instância, mas o agente não os lê.
Por isso uma regra "de heartbeat" (como a checagem de desvio) mora numa seção do
`AGENTS.md`, e esses arquivos viraram ponteiros.

## Checklist para revisar o fluxo numa card real

- [ ] A card tem o documento `original` e o comentário "Curadoria aplicada".
- [ ] Todo item da descrição tem marca de origem; nenhum `[inferido]` sem motivo.
- [ ] Perguntas que mudam escopo foram feitas antes do refinamento (card passou por `in_review`).
- [ ] Cada subtarefa tem as seções do contrato, até 3 skills com slug real e critério com prova.
- [ ] Cada relatório tem a saída real do `git log`, a linha `Integridade` terminando em `OK`, uma prova por critério e a linha `Lacunas`.
- [ ] O `plan` mostra para qual subtarefa foi cada item de Escopo/Critérios da card.
- [ ] O documento `estado` diz onde a card está em até 25 linhas.

Item que falha vira ajuste de skill ou de `AGENTS.md`, não conserto pontual da card (ver
"Como evoluir" em `context/engenharia-de-contexto-e-prompt.md`).
