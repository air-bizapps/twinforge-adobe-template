# Plano — notificações e ações via Slack para todos os agentes

Objetivo: **todo bloqueio e toda intervenção humana de qualquer agente** aparece no Slack
(`#twinforge-adobe-template`), e o humano consegue **agir direto do Slack** (orientar, aceitar,
rejeitar, responder, aprovar). O que é notificado e quais ações existem deve poder evoluir
sem mexer nos agentes.

**Fonte única das regras:** `twinforge/workflow.yaml › policies.notifications` (o que notifica, ações,
quem age, lembretes). O bridge lê esse bloco e um teste trava divergência com as `phases`. Este
arquivo descreve o desenho e as fases; o *quê* está no workflow.

Este arquivo não tem dados de instância (URLs, tokens). Ids de canal/usuário Slack e tokens
vêm de variáveis de ambiente (ver `integrations/slack-bridge/README.md`).

## Princípios (o que torna isso saudável pros agentes)

1. **Os agentes não mudam.** Nenhuma instrução nova em 13 agentes, nenhum token espalhado.
   Um agente que "esquece" de notificar não existe: quem notifica é um sensor determinístico.
2. **Sensor em código, não em LLM.** Custo de tokens zero, resultado verificável (a mensagem
   só é dada como enviada quando o Slack devolve `ok:true`), sem "relatório de done" que não
   aconteceu.
3. **Só leitura do estado + ações explícitas de humano.** O sensor lê (`issue list`,
   `interactions`, `approval list`). Só escreve na TwinForge quando uma pessoa autorizada
   clica num botão.
4. **Falha fechada.** Quem não está na allowlist não executa nada. Texto vindo do Slack nunca
   é interpretado como comando: só vira comentário/decisão atribuído à pessoa.
5. **Sem barulho.** Um card por item pendente, deduplicado; bloqueio "esperando filhas"
   (legítimo) não notifica; teto de cards por varredura; lembretes limitados.
6. **Reversível por fase.** Cada fase pode ser desligada sem afetar as outras nem os agentes.

## Arquitetura

```
TwinForge (instância)                    Bridge (processo único, fora dos agentes)         Slack
  issues blocked / in_review  ──poll──►  detect → diff vs state.json ──chat.postMessage──► card + botões
  interactions pendentes                          │                                          │
  approvals pendentes                             │◄────── Socket Mode (clique/modal) ───────┘
                                                  └─ valida allowlist → twinforgecli (comment/accept/…)
```

- **Fonte de verdade = TwinForge.** O estado local (`state.json`) só guarda "qual card já
  foi postado pra qual item" (dedupe e atualização do card). Se for apagado, a primeira
  varredura reconstrói em modo baseline (sem repostar o que já está aberto).
- **Identidade nas ações:** a TwinForge registra a ação com a credencial do CLI do bridge
  (hoje a board key de um usuário). Pra manter a autoria real, todo comentário/nota de
  decisão leva o prefixo `[via Slack — <nome> (<id>)]`.

## O que é notificado (v1) e as ações

| Evento (detectado) | Card | Ações no Slack |
|---|---|---|
| Issue `blocked` que não está só esperando filhas (`blockerAttention` ≠ `covered`) | motivo (último comentário), agente, há quanto tempo | **Orientar e retomar** (modal → comentário + acorda o responsável), **Cancelar issue** (com confirmação), abrir |
| Interação pendente `request_confirmation` | texto do pedido | **Aceitar**, **Rejeitar** (modal com motivo) |
| Interação pendente `ask_user_questions` (1 pergunta, escolha única) | pergunta + opções | uma opção = um botão |
| Interação `suggest_tasks`, perguntas múltiplas, ou outro tipo | resumo | abrir a issue (decisão complexa fica na UI) |
| Aprovação pendente (`approval`) | tipo + quem pediu | **Aprovar**, **Rejeitar** (modal com nota) |
| Item resolvido por qualquer meio (UI, outro agente) | o mesmo card é atualizado: "resolvido" + botões removidos | — |

Fica pra evoluir (v2, por ordem de valor): run falhada/encalhada (`activeRecoveryAction`),
orçamento do agente > 80%, agente em `error`/`paused`, issue parada há N horas, resumo
diário. Cada um é uma função nova em `src/detect.js` + um bloco em `src/blocks.js`; os
agentes não são tocados.

## Fases

| Fase | Entrega | Risco pros agentes | Critério de pronto |
|---|---|---|---|
| **0** (feita) | PO notifica os bloqueios que ele trata (Step 5 da skill) | só o PO | ALERTA chegou no canal (teste BUN-189) |
| **1** | Bridge de **saída**: sensor + cards, dedupe, baseline, teto, lembretes | nenhum (só leitura) | bloqueio/pedido de decisão real aparece 1× com card; resolvido atualiza o card |
| **2** | Bridge de **entrada**: botões/modais via Socket Mode, allowlist | só quando humano age (acorda 1 agente) | cada ação testada numa issue de teste; usuário fora da allowlist é recusado |
| **3** | Rodar 24/7 (host definido) e **PO vira relator**: o Step 5 deixa de postar no Slack e só escreve o diagnóstico em comentário (o bridge o espelha); remover a ligação `SLACK_BOT_TOKEN` do PO | simplifica o PO | 1 card por bloqueio, sem duplicata; token só no bridge |
| **4** | Evoluir o que notifica (lista v2) e ações novas | nenhum | por item |

Enquanto a fase 3 não acontece, o PO e o bridge podem mandar mensagens parecidas pro mesmo
bloqueio — é aceito como transição (o do PO traz o diagnóstico; o do bridge traz os botões).

## Decisões que são suas (não dá pra deduzir)

- **Onde o bridge roda 24/7** (fase 3): máquina/servidor com `twinforgecli` autenticado, Node 20+
  e saída pra `slack.com`. Até lá, roda sob demanda (`once`) ou numa sessão manual.
- **Credencial do bridge:** o ideal é uma board key dedicada, em vez da pessoal.
- **Allowlist de quem pode agir** (`SLACK_ALLOWED_USER_IDS`): vazio = ninguém age.
- **Tokens expostos em chat:** o bot token e o app token já foram colados numa conversa; ao
  levar o bridge pra produção, gerar novos.

## Riscos e como o desenho responde

| Risco | Resposta |
|---|---|
| Flood de cards (ex.: 30 issues bloqueadas de uma vez) | baseline na 1ª varredura; teto por varredura com resumo "+N itens"; lembrete máx. 3× e a cada 24h |
| Texto malicioso/enganoso vindo do Slack virando instrução pro agente | só allowlist age; texto vira comentário atribuído, nunca é executado nem interpretado pelo bridge |
| Comentário supersede interação pendente (`supersedeOnUserComment`) | aceitar/rejeitar primeiro, comentar depois; nunca comentar antes de decidir |
| Duplo clique / dois humanos | ação revalida o estado antes de agir; item já resolvido só atualiza o card |
| Slack fora do ar / token inválido | o item não é marcado como notificado; tenta de novo na próxima varredura; erro vai pro log |
| Bridge cai | nada quebra nos agentes; ao voltar, a varredura recupera pelo estado atual da TwinForge |
| Carga na instância | 1 varredura/60s = 3 listagens + 1 chamada por issue em `in_review`; sem LLM |
