# slack-bridge

Sensor determinístico (sem LLM) que espelha no Slack **todo bloqueio e todo pedido de
decisão humana** de qualquer agente da TwinForge, e executa as ações de humanos
autorizados direto dos cards. Plano, fases e riscos: `context/plano-notificacoes-slack.md`.

Os agentes **não são alterados** e não recebem token do Slack: o bridge só lê o estado
(`issue list`, `interactions`, `approval list`) e só escreve na TwinForge quando uma pessoa
da allowlist clica num botão.

## O que vira card

| Item pendente | Botões |
|---|---|
| Issue `blocked` (exceto "esperando filhas") | Orientar e retomar (modal), Cancelar issue (confirma) |
| `request_confirmation` pendente | Aceitar, Rejeitar (modal) |
| `ask_user_questions` com 1 pergunta de escolha única | 1 botão por opção |
| Aprovação (`approval`) pendente | Aprovar, Rejeitar (modal) |
| Qualquer outro tipo de interação | só "Abrir na TwinForge" |

Item resolvido (por Slack, UI ou outro agente) → o mesmo card vira "resolvido" e perde os botões.
Todo comentário/nota gerado pelo Slack leva o prefixo `[via Slack — <nome> (<id>)]`.

## Rodar

Requisitos: Node 20+, `twinforgecli` no PATH **já autenticado** na instância certa
(`twinforgecli whoami`), saída de rede pra `slack.com`.

```bash
cd integrations/slack-bridge && npm install
npm test                                   # testes (sem rede), inclui a sincronia com o workflow.yaml

export TWINFORGE_COMPANY_ID=...            # id da company
export TWINFORGE_UI_BASE=https://<host da TwinForge>   # pro botão "Abrir"
export SLACK_BOT_TOKEN=xoxb-...            # escopo chat:write; bot no canal
export SLACK_CHANNEL=C...                  # id do canal

node src/index.js once --dry-run           # mostra o que postaria, não toca em nada
node src/index.js once                     # uma varredura (cron/systemd timer)

export SLACK_APP_TOKEN=xapp-...            # Socket Mode (botões e modais)
node src/index.js run                      # listener + varredura a cada BRIDGE_POLL_SECONDS
```

Tokens só por variável de ambiente (ou secret manager do host) — nunca em arquivo versionado.

| Variável | Padrão | Pra quê |
|---|---|---|
| `TWINFORGE_COMPANY_ID`, `TWINFORGE_UI_BASE` | — | company e URL da UI (instância-específico) |
| `SLACK_BOT_TOKEN`, `SLACK_APP_TOKEN`, `SLACK_CHANNEL` | — | tokens e **id** do canal (`C…`/`G…`, nunca o nome) |
| `SLACK_ALLOWED_USER_IDS` | (vazio) | **opcional**: só pode RESTRINGIR a lista do workflow (interseção), nunca ampliar |
| `SLACK_TEAM_ID` | (vazio) | opcional: só aceita interações desse workspace |
| `BRIDGE_POLL_SECONDS` / `BRIDGE_MAX_PER_POLL` | 60 / 10 | intervalo e teto de cards novos por varredura (operacional) |
| `BRIDGE_STATE_PATH` / `BRIDGE_AUDIT_PATH` | `./state.json` / `./audit.jsonl` | estado dos cards e trilha de auditoria |
| `WORKFLOW_FILE` / `ROLES_FILE` | `twinforge/workflow.yaml` / `roles.yaml` do repo | de onde ler as regras |

## As regras vivem no `workflow.yaml`

O que notifica, quais ações cada tipo de card oferece, quem pode agir e quando lembrar estão em
`twinforge/workflow.yaml › policies.notifications` — **fonte única**. O bridge só executa:

- `events.<tipo>.notify` liga/desliga o tipo; `events.<tipo>.actions` define os botões (e o bridge
  recusa a ação de um card antigo se ela for desligada).
- `who_may_act` lista papéis humanos; o Slack id sai de `roles.yaml › <papel>.slack_user_id`.
- `reminders` define lembrete (horas e máximo). Essas regras **não** são mais variáveis de ambiente.
- `covers` / `not_covered` espelham as `phases` (gates, perguntas ao cliente, fases humanas manuais).
  `npm test` falha se uma fase com gate for criada sem atualizar `covers`, se uma ação não existir
  pro tipo, ou se o papel não tiver `slack_user_id`. Em produção, bloco inválido = o bridge não sobe;
  divergência só de `covers` vira aviso no log.

**Primeira execução** (sem `state.json`) faz *baseline*: registra o que já está aberto
sem notificar, pra não inundar o canal. Apagar o `state.json` reproduz isso.

## App Slack (configuração única)

Escopos do bot: `chat:write` (e o bot convidado no canal). Para os botões: **Socket Mode**
ligado (gera o `xapp-` com `connections:write`) e **Interactivity** habilitada.

## Garantias de segurança

- **Allowlist, falha fechada:** quem não está em `who_may_act` (workflow.yaml + roles.yaml) recebe recusa efêmera e nada é executado; lista vazia = ninguém age.
- **Canal fixado:** o cliente do Slack só fala com `SLACK_CHANNEL` (sem DM, sem outro canal) e toda interação de outro lugar é descartada; tudo é auditado em `audit.jsonl` (sem texto digitado).
- **Texto do Slack nunca é interpretado:** vira comentário/nota atribuído; o `twinforgecli` é chamado com argumentos em array, sem shell.
- **Revalida antes de agir:** item já resolvido (duplo clique, outra pessoa, UI) não executa nada.
- **Decide antes de comentar:** um comentário de usuário supersede a interação pendente.
- **Varredura parcial nunca "resolve" nada:** falha ao ler a TwinForge aborta a varredura.
- **Slack falhou → não marca como notificado:** tenta de novo na próxima varredura.
- Menções (`<@U…>`) vindas de texto de agente são escapadas e não disparam notificação.

## Para evoluir o que é notificado

Mudar *o que* notifica/quem age = editar `policies.notifications` no workflow.yaml (e `npm test`). Novo *tipo* de card = uma regra em `src/detect.js` (retorna itens com `key`, `kind`, `since`…) +
um bloco em `src/blocks.js` + teste em `test/`. Nova ação = método em `src/actions.js`
(revalida o estado) + handler em `src/listen.js`. Nenhum agente é tocado.
