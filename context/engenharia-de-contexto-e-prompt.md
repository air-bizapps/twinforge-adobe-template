# Engenharia de contexto e de prompt nos agentes — princípios, estado e evolução

Registro do que foi aplicado nos agentes do fluxo Tech Lead → Backend/Frontend (e no
OpenSpec Manager, que também escreve subtarefas), do que já foi validado na instância e
do que foi considerado e deixado de fora. A visão prática (que texto vira prompt, quem
escreve, onde ver na UI) está em `context/engenharia-de-prompt-aplicada.md`. Leia antes de mudar `AGENTS.md`, a skill
`projeto-subtask-contract` ou qualquer skill do caminho entre esses agentes.

Sem dados de instância (ids, URLs, caminhos de servidor). Os comportamentos da plataforma
citados aqui estão detalhados em `context/twinforge-context.md`; os padrões de falha, em
`context/licoes-fluxo-openspec.md`.

## Fontes

| Fonte | O que tiramos dela |
|---|---|
| Anthropic, "Effective context engineering for AI agents" (set/2025) | Contexto como recurso finito (*context rot*); menor conjunto de tokens de alto sinal; system prompt na altitude certa; recuperação *just-in-time*; notas estruturadas; subagentes com contexto limpo devolvendo resumo curto; compactação |
| Anthropic, documentação de engenharia de prompt (clareza, contexto/motivação, exemplos, estrutura, permitir "não sei", tom nas versões recentes do Claude) | Regras de escrita da descrição da subtarefa |
| Anthropic, Agent Skills (revelação progressiva) | Skill: só nome + descrição sempre no contexto; o corpo entra sob demanda |
| Testes na própria instância (2026-10-15) | O que a plataforma faz de verdade: índice de skills, sessões, o que entra no system prompt |

Os limites numéricos abaixo (tamanhos, quantidade de skills, linhas) são **calibração nossa**,
não regra da Anthropic. Ajuste-os pela medição (seção "Como evoluir").

## Como a plataforma se comporta (verificado)

Detalhe e evidência em `context/twinforge-context.md`. O que importa para o desenho:

1. **Só o `AGENTS.md` entra no system prompt** (adapter `pi_local`). O resto do pacote de
   instruções (`CONTEXT.md`, `HEARTBEAT.md`, `SOUL.md`, `TOOLS.md`) não é anexado; nos
   agentes do fluxo esses arquivos são ponteiros, e regra "de heartbeat" vai numa seção do
   `AGENTS.md`.
2. **Skills sob demanda, mas o índice é da instância.** Todo agente Pi vê nome + descrição
   das skills de todos os agentes (o Backend viu 40, 11 de outros papéis). O corpo do
   `SKILL.md` só entra quando o agente abre. `desiredSkills` não filtra o índice.
3. **Sessão por issue, renovada a cada heartbeat na prática.** Subtarefas diferentes nunca
   compartilham contexto; mesmo na mesma issue a sessão é resetada (mudança de config de
   workspace em todo run, e por regra em atribuição/review). A memória entre heartbeats é a
   issue: descrição, comentários, documentos.
4. **A descrição da issue chega inteira no prompt do run** (payload de wake), antes de
   qualquer leitura de arquivo. A da subtarefa é o prompt do dev; a da card, o do Tech Lead.
5. Runs **sem issue** retomam a sessão do agente e acumulam contexto (já passou de 1M tokens
   de entrada); `agent runtime-state:reset-session` zera.

Consequência central: **o desenho de "isolar por subtarefa" já é o que a plataforma faz.**
O trabalho é curar o que entra em cada subtarefa e o que volta dela.

## Princípios de contexto aplicados

Status: **validado** = observado funcionando na instância; **aplicado** = está nas
instruções/skills, ainda sem evidência de uso real; **parcial** = validado em parte.

| # | Princípio | Onde está | Status |
|---|---|---|---|
| C1 | **Menor conjunto de alto sinal.** O que entra todo run precisa mudar decisão. Tech Lead 63 → 12 KB, Backend 35 → 6 KB, Frontend 18,6 → 6,5 KB (AGENTS + CONTEXT do pacote) | `agents/*/AGENTS.md` | aplicado |
| C2 | **Uma fonte por regra.** Nada de procedimento repetido em dois arquivos (as cópias já tinham divergido: `blocked` × `in_review`, workspace × worktree). O `CONTEXT.md` do pacote virou ponteiro | `agents/*/CONTEXT.md` | aplicado |
| C3 | **Altitude certa.** Regra + uma frase de motivo; sem narrativa de incidente com data e id. O porquê fica, a história sai | `agents/tech_lead/AGENTS.md` (guardrails) | aplicado |
| C4 | **Só comportamento atual.** Removido o que estava desatualizado (número de runs simultâneos, ponteiros para seções que não existem, nomes de skill inexistentes) | AGENTS dos três | aplicado |
| C5 | **Referenciar, não copiar.** O contexto do projeto mora no `CONTEXT.md` da raiz do repo de código; detalhe de módulo em `docs/module-index.md`; regra de negócio via `docs/README.md` → um arquivo | AGENTS dos devs, `projeto-subtask-contract` | aplicado |
| C6 | **Just-in-time.** O dev abre só as Entradas e as Skills listadas; o resto entra quando o código ou uma Entrada aponta | `projeto-subtask-contract` §2, AGENTS dos devs | parcial: carregamento sob demanda validado; disciplina do dev não observada em card real |
| C7 | **Isolamento por subtarefa.** O dev não lê a card pai nem as irmãs; precisou delas = lacuna da descrição | `projeto-subtask-contract` §2 | parcial: isolamento da plataforma validado; comportamento do dev não observado |
| C8 | **Resumo curto de volta.** Relatório de entrega ≤ 40 linhas, com uma prova por critério e linha de Lacunas | `projeto-engineering-practices`, `openspec-apply-change` | aplicado |
| C9 | **Prova por comando, não por relato.** Relatório cola a saída real do `git log`; o Tech Lead confere a existência do commit antes de liberar a próxima subtarefa (gate) | `projeto-subtask-contract` §3-4 | aplicado; a necessidade foi validada (relatório de agente citou errado o próprio AGENTS.md no teste) |
| C10 | **Nota estruturada fora da janela.** Documento `estado` na card (≤ 25 linhas), lido antes do histórico de comentários | `projeto-subtask-contract` §5, AGENTS do Tech Lead | aplicado |
| C11 | **A escolha de skills por tarefa é a linha "Skills" da subtarefa** (até 3), já que o índice é compartilhado | `projeto-subtask-contract` §1 | aplicado |
| C12 | **Curar a entrada.** A descrição da card é reescrita como prompt antes do refinamento; o texto bruto vai para o documento `original` (cópia por comando), fora do prompt | `card-curation`, AGENTS do Tech Lead | parcial: cópia e reescrita validadas no teste de 2026-10-15; fidelidade com falhas (ver P11-P12) |
| C13 | **Porta de entrada única.** Tarefa que chega a dev, OpenSpec Manager ou DevOps sem card pai, sem ter sido criada por agente e sem `Atribuição direta:` volta ao Tech Lead sem ser executada | "First check" nos AGENTS de Backend, Frontend, OpenSpec Manager, DevOps | aplicado |
| C15 | **Prova por comando, não por prosa.** Como os testes não rodam na subtarefa, o `magento-verify` (skill `adobe-magento-toolbox`, só leitura) confere os arquivos alterados contra o vendor e os XSD instalados: classe inexistente com "você quis dizer", PHP que não carrega, XML fora do XSD, as causas do spinner infinito em UI Component, RequireJS e Web API. A saída é curta (ID, arquivo:linha, o que fazer, modelo a copiar); o dev roda até `VERIFY OK` (teto de 3 por run) e o Tech Lead repete no gate. Antes de escrever, `magento-example` (módulo de referência provado) e `magento-api` dão o padrão e a assinatura reais, sob demanda | `adobe-magento-toolbox`, `projeto-engineering-practices`, `openspec-apply-change`, `projeto-subtask-contract` §3-4, AGENTS dos devs e do Tech Lead | validado no oráculo: 9/9 fixes humanos da BUN-14079 acusados no commit do agente e 0 erro fora de teste no corrigido; BUN-889 idem. Uso pelos agentes ainda não observado |
| C16 | **Cobertura da card.** Todo item de Escopo/Critérios da card curada tem destino numa subtarefa (critério ou Fora de escopo), registrado no `plan` | `projeto-subtask-contract` §1 | aplicado |
| C17 | **Releitura antes do irreversível.** Antes de push, PR, rebase ou cancelamento, o Tech Lead relê os comentários desde o início do run e confere que os commits da branch são só os do `estado` | AGENTS do Tech Lead §4 | aplicado depois de falha |
| C14 | **Instrução só onde o agente lê.** `HEARTBEAT.md`/`SOUL.md`/`TOOLS.md` viraram ponteiros; o que valia foi para o `AGENTS.md` (havia regra contraditória: escalar ao "CEO", "deixar o código melhor") | `agents/{backend_dev,devops_engineer}/` | aplicado |

## Princípios de prompt aplicados (descrição da subtarefa)

Quem escreve: Tech Lead (card comum) e OpenSpec Manager (card `openspec`). Fonte única:
`projeto-subtask-contract` §1, que tem o template, um exemplo preenchido e o checklist.

| # | Princípio | Como aparece | Status |
|---|---|---|---|
| P1 | **Teste do colega**: um dev competente que nunca viu a card executaria só com a descrição? | Abertura do §1 e 1º item do checklist | parcial |
| P2 | **Claro e direto**: um objetivo por subtarefa, verbos de ação, nomes exatos | Seção Objetivo + regra | validado no teste de calibração |
| P3 | **Dar o porquê**: seção Contexto com motivo e decisões do plano | Seção Contexto | validado no teste de calibração |
| P4 | **Dizer o que fazer, não só o que evitar**: "fica para BUN-n" em vez de "não mexa" | Fora de escopo | parcial: no teste veio como lista de exclusões, sem destino |
| P5 | **Estrutura fixa**: mesmas seções, mesma ordem, sempre | Template | validado no teste de calibração |
| P6 | **Exemplo canônico**: um exemplo preenchido em vez de lista de casos | Exemplo no §1 | aplicado (o teste usou outra demanda e não copiou o exemplo) |
| P7 | **Permitir "não sei"**: reportar lacuna é entrega válida | Seção "Se faltar algo" | validado: o teste reportou lacunas reais |
| P8 | **Tom normal**: sem MAIÚSCULAS/"CRÍTICO" repetidos; proibições de verdade ficam nas skills do dev | Regra de escrita | aplicado |
| P9 | **Critério verificável com a prova pedida** | Seção Critérios | validado no teste de calibração |
| P10 | **Checklist só vale se conferível.** Caminho e slug se conferem por comando, não por intenção | Checklist + comando que lista as skills do dev | aplicado depois de falha (ver abaixo); não retestado |

## Princípios de prompt aplicados (descrição da card)

Quem escreve: Tech Lead, na curadoria. Fonte única: `card-curation`, com template,
exemplo antes/depois e gate de Definition of Ready. Valem também P2, P3, P4, P5, P8 e P9.

| # | Princípio | Como aparece | Status |
|---|---|---|---|
| P11 | **Separar fato de inferência**: cada item marcado `[card]`, `[comentário: autor]`, `[inferido: motivo]` ou `[pergunta]` | Passo 2 da skill | parcial: marcas aplicadas no teste, mas detalhe acrescentado pelo Tech Lead saiu como `[card]`; regra de "literalmente" reforçada |
| P12 | **Fidelidade**: nada some (o que não serve vai em `Não usado do original` com motivo); nomes, valores e URLs copiados literalmente | Passo 2, conferência linha a linha e linha final do template | parcial: original preservado por comando (validado); a descrição perdeu acentos e itens (LGPD, usuário do aceite); conferência linha a linha acrescentada |
| P13 | **Organizar, não decidir**: curadoria não escolhe solução; isso é do `plan`/proposta | Passo 2 | aplicado |
| P14 | **Perguntar antes de refinar**: pergunta que muda escopo trava a card (DoR) em `in_review` | Passo 5 | falhou no teste: perguntou, mas planejou e criou subtarefas sobre um "default" que a resposta contradisse; o gate agora termina o heartbeat |
| P15 | **Idempotência**: com `original` existente, só a seção afetada muda, com a origem do comentário | Passo 4 | aplicado |
| P16 | **Resposta primeiro**: acordou com interaction respondida → a resposta entra na descrição antes de qualquer passo, e tudo que assumia o contrário é reescrito | Passo 4 + AGENTS do Tech Lead | aplicado depois de falha |
| P18 | **Pergunta com o tipo certo**: escolha entre opções é `ask_user_questions` com as opções no payload; `request_confirmation` só para sim/não de uma ação descrita | AGENTS do Tech Lead | aplicado depois de falha (confirmação sem opções) |
| P19 | **Gatilho verificável no lugar de julgamento**: OpenSpec obrigatório por critérios conferíveis (back+front, tabela/módulo novo, módulo existente alterado, módulo sensível, integração), contando o que a demanda muda e não o módulo que se planeja criar | AGENTS do Tech Lead, `workflow.yaml` | aplicado depois de falha |
| P17 | **O exemplo canônico obedece a todas as regras**: o exemplo da skill mostrava um ID externo que o texto original não tinha, e o agente repetiu o padrão (sugeriu o número BUN como ID) | Exemplo da skill | corrigido |

## Registro de validação

| Data | Teste | Resultado | Ação |
|---|---|---|---|
| 2026-10-15 | Leitura dos últimos 200 runs (metadados de sessão e prompt) | Sessão nunca reaproveitada em run com issue; reset por config de workspace e por regra de wake | Desenho de isolamento mantido; nota `estado` e relatório curto viram a memória |
| 2026-10-15 | Issue de teste para o Backend: o que ele enxerga no run | 40 skills no índice = conteúdo do diretório compartilhado; nenhum arquivo aberto para listá-las; citou errado um trecho do próprio AGENTS.md | Linha "Skills" vira o mecanismo de seleção; reforço de prova por comando |
| 2026-10-15 | Comentário de follow-up na mesma issue | Sessão nova; o agente não lembrou da resposta anterior | Confirma: continuidade só pela issue |
| 2026-10-15 | Card real do Azure DevOps (texto livre, sem ID) atribuída ao Tech Lead | Documento `original` íntegro; descrição curada com marcas e 2 perguntas via interaction; comentário "Curadoria aplicada". Falhas: sem acentos; `[card]` com detalhe inventado; LGPD e "usuário do aceite" perdidos; ID externo sugerido a partir do BUN; planejou e atribuiu antes da resposta, e a resposta ("pré-etapa do B2B") não corrigiu a decisão "default" das subtarefas; Backend e Frontend rodaram ao mesmo tempo; "Preparar ambiente" atribuída em `backlog` nunca rodou; card `blocked` sem `blockedByIssueIds` ficou travada | Skill de curadoria (fidelidade, ID, gate termina o heartbeat, resposta primeiro, exemplo corrigido); AGENTS do Tech Lead (atribuir = `todo`, uma por vez contando `todo`, card bloqueada pela subtarefa atual) |
| 2026-10-16 | BUN-228 (OpenSpec forçado por label) | Caminho OpenSpec seguido até o fim; subtarefas de dev criadas sob a issue de proposta, não sob a card, apesar da regra em texto na skill e no AGENTS do OpenSpec Manager | `parentId` por comando + conferência no `openspec-propose`; Tech Lead corrige antes de atribuir. Pendente: o `openspec-propose` (passo 12) manda ler `twinforge/workflow.yaml`, que não está no repo de código |
| 2026-10-16 | Mesma card rodada na instância local (Claude, PR #14 ativo) e na remota (pré-PR #14), as duas branches testadas ponta a ponta no Magento local | Local: OpenSpec, 9/12 cenários (os 3 restantes travados por atributo órfão do banco, não pelo código), checagem de integridade OK. Remota: caminho comum ("módulo isolado"), página 500 e API quebrada — `system.xml` inválido com URN errada, `db_schema_whitelist` em `.xml`, interface inexistente no repositório, 4 outras classes inexistentes, página CMS de termos com identificador errado, link do e-mail com domínio de produção fixo e token no caminho, token em claro, 3 endpoints `anonymous`, sem tela do vendedor. Retomada: bloqueio declarado ok; push feito apesar de pedido humano chegando no meio do run; branch levou commits de card cancelada; confirmação sem opções | C15, C16, C17, P18, P19; `bunge-*-code-quality` lida antes do 1º commit; anti-padrões de domínio fixo e token em claro; `CustomCompany`/`CustomCustomer` em `sensitive_modules` |
| 2026-10-19 | Análise pelo Claude (Agentic Toolkit, só leitura, com as lições e o código da branch) de uma card OpenSpec concluída com gate aprovado | O gate conferiu commit, branch e contagem de arquivos; a branch tem model que estende `DataObject` salvo por `AbstractDb::save`, controller com namespace e imports inexistentes, link do e-mail sem rota, template de e-mail e `config.xml` ausentes, `system.xml` com source inexistente, data source da grid sem registro | Pendente de decisão: o gate passar a exigir o script de integridade e a rota real executados, e as reviews conferirem FQCN no vendor e a herança do model persistido. O quadro do app passa a mostrar problemas de fluxo; correção de qualidade continua nas skills |
| 2026-10-15 | Issue de teste para o Tech Lead: rascunho de subtarefa pelo contrato | Formato, Contexto, Entradas reais, critérios com prova e lacunas: ok. **Slug de skill errado** (`adobe-` no lugar de `bunge-`) e checklist marcado mesmo assim | Slug passa a vir de comando; checklist exige conferir antes de marcar |
| 2026-10-21 | Oráculo do verificador (BUN-14079, BUN-889, 221, 229, 888, 14079-1/-3) e módulo de referência provado no laptop | 9/9 defeitos corrigidos à mão acusados no commit do agente, 0 falso positivo fora de teste no corrigido; alucinações do catálogo (`SearchHandlerInterface`, `Email\Model\Template\Source`) com o nome certo sugerido; o próprio módulo de referência tinha um form em spinner que só o navegador viu (virou regra) | C15 reescrito: `magento-verify` no lugar do script de integridade; `magento-example`/`magento-api` sob demanda (sem prosa nova no AGENTS); linha de precedência para o "CEO" da skill `twinforge` |

## Considerado e não aplicado

| Ideia | Por que não (ainda) | Reabrir quando |
|---|---|---|
| `desired_skills` por tarefa | A plataforma não tem; `desiredSkills` é por agente e nem filtra o índice | A plataforma oferecer escopo de skill por issue ou diretório de skills por agente |
| Tags XML no corpo da subtarefa | Seções Markdown dão a mesma separação, leem melhor no board, e o WAF da instância já bloqueou conteúdo com cara de tag | Se o modelo misturar seções com frequência |
| Apagar o `CONTEXT.md` do pacote | Ele não entra no prompt; virou ponteiro curto, que é inofensivo nos dois casos e evita mexer em `instructions.files` | Ao revisar o `agent.yaml` dos agentes |
| Enxugar o `CONTEXT.md` da raiz do repo de código (13 KB, lido por todos todo heartbeat) | Está em outro repo, fora das alavancas deste | Quando houver PR no repo de código para isso |
| Compactação dentro do heartbeat | Heartbeats já começam com sessão nova; não há histórico longo para compactar | Se a plataforma passar a retomar sessão na mesma issue |
| Tirar `adobe-run-unit-tests` do Tech Lead | Recomendado (ninguém roda testes), mas mudança de `desiredSkills` fora do escopo da rodada | Próxima revisão de skills do Tech Lead |
| Descrição de cada skill dizendo de quem ela é | Necessário porque o índice é compartilhado; muitas skills ainda não dizem | Próxima rodada de skills (prioridade: as `openspec-*` e as de outro papel que aparecem no índice dos devs) |
| Desvio determinístico (bridge ou rotina reatribui ao criar a issue, sem gastar run do agente errado) | Começamos pela instrução para medir a frequência; precisa de gatilho de criação de issue na API | Se os desvios forem frequentes ou o run curto do agente errado pesar no custo |
| Reescrever a card no Azure DevOps | É o registro do cliente; agente editando o texto muda escopo sem rastro do lado dele e exige escrita no sistema do cliente | Se o cliente pedir a versão curada lá; mesmo assim, como comentário |
| Limpar `HEARTBEAT.md`/`SOUL.md` do PO | O PO está fora do caminho Tech Lead → devs; o template genérico (CEO) não chega ao prompt, então é inofensivo | Na próxima revisão do PO |
| Acordar o dono ao destravar (PO comenta e o Tech Lead não acorda) | Comentário de agente não acordou o responsável; o CLI tem `--resume`, mas o campo da API para um agente não foi confirmado | Ao confirmar na API como o comentário pede retomada; então uma linha na `po-issue-recovery` |
| Permissão para o Tech Lead cancelar subtarefa atribuída a dev | Ele não conseguiu cancelar a BUN-222; não verificado se a plataforma tem permissão de gestor | Ao verificar a permissão; senão, pedir ao dev que cancele |
| `reset-session` periódico para runs sem issue | Afeta Concierge e wakes avulsos, fora do fluxo de subtarefas | Se o custo de entrada desses agentes subir |

## Calibração (ajustável)

| Item | Valor atual | Onde |
|---|---|---|
| `AGENTS.md` do Tech Lead | ~14 KB (era ~12; +2 KB de regras que corrigem falhas observadas: gatilhos de OpenSpec, releitura antes do irreversível, tipo de pergunta, origem da branch) | `agents/tech_lead/AGENTS.md` |
| `AGENTS.md` dos devs | ~6 KB | `agents/{backend,frontend}_dev/AGENTS.md` |
| Descrição da card curada | ≤ 80 linhas | `card-curation` |
| Descrição da subtarefa | ≤ 60 linhas | `projeto-subtask-contract` §1 |
| Skills por subtarefa | ≤ 3 | idem |
| Relatório de entrega | ≤ 40 linhas | `projeto-engineering-practices`, `openspec-apply-change` |
| Nota `estado` | ≤ 25 linhas | `projeto-subtask-contract` §5 |

## Como evoluir

**Medir antes de mexer.** Fontes que já existem:
- tamanho do que entra no prompt: `promptMetrics.systemPromptChars` no evento `adapter.invoke`
  de cada run (`twinforgecli run events <runId> --json`);
- custo e tokens: `twinforgecli cost by-agent`, `usageJson` dos runs;
- qualidade da descrição: linhas `Lacunas` dos relatórios e subtarefas reabertas pelo gate;
- curadoria: contagem por origem no comentário "Curadoria aplicada" (muito `[inferido]` = card
  pobre ou Tech Lead decidindo cedo demais) e `[inferido]` corrigidos por pessoas;
- desvio: comentários "Recebi esta tarefa sem passar pelo Tech Lead" por agente e por mês;
- obediência ao contrato: comparar a descrição da subtarefa com o checklist do §1;
- fluxo: os problemas que o monitor do Agentic Toolkit registra (regra, card, desde quando, o
  que o piloto automático corrigiu) — uma regra que dispara toda semana aponta a skill ou o
  `AGENTS.md` a corrigir (`context/agentic-toolkit.md`).

**Testar com issue de calibração**, só leitura, atribuída ao agente que vai mudar: pedir que
ele mostre o que enxerga ou que rascunhe o artefato (subtarefa, relatório) como comentário,
sem criar issue nem mexer em código. Validar o resultado contra o critério, não contra o
relato do agente.

**Ao mudar uma regra:**
1. Escolha um lugar só para ela (tabela "Onde está" acima): procedimento do papel →
   `AGENTS.md`; regra compartilhada → skill; contexto do projeto → `CONTEXT.md` do repo de código.
2. Escreva a regra com uma frase de porquê, sem narrativa.
3. Passe pelo checklist de governança (`context/licoes-fluxo-openspec.md`).
4. Aplique na instância e no repo no mesmo ciclo; confira por leitura de volta.
5. Atualize as tabelas deste arquivo (status e registro de validação).
