# agents/

Definição de cada agente TwinForge da company Bunge: o que precisa pra
recriar um agente do zero numa company nova, e o que já está em vigor
hoje e foi puxado da instância real pra cá.

**11 dos 14 agentes da company** — escopo = todo agente que referencia
pelo menos uma skill de `skills/` (ver critério em `skills/README.md`), mais o
Verificador, que não carrega skill mas é um estágio do fluxo.
Os 3 de fora (QA Criteria Validator, Reflection Coach, Summarizer) usam
só skills `github`/`catalog`, nenhuma delas é nossa pra versionar — não
têm pasta aqui ainda, mas isso não significa que não existam, só que
nenhuma skill deles cai no escopo atual deste repo.

| Pod | `role_key` |
|---|---|
| E-commerce (Magento) | `tech_lead`, `openspec_manager`, `backend_dev`, `frontend_dev`, `workflow_manager`, `verifier` |
| Mobile | `mobile_tech_lead`, `mobile_developer` |
| Transversal (singleton, reporta direto à raiz) | `po`, `concierge`, `devops_engineer` |

**Verificador (`verifier`, desde 2026-10-21):** adapter `process`, sem LLM e sem `AGENTS.md`. É o
participante do estágio de revisão das subtarefas de dev: a TwinForge roda o `verify-stage.php` da
skill `adobe-magento-toolbox` (detalhes no `agent.yaml` dele e em `context/roteiro-ativacao-verificador.md`).

**Estado atual (2026-10-07):** os 13 agentes da company usam `adapter_type: pi_local` com
`model: air-bizapps/twinforge-coder` — inclusive os 3 sem pasta aqui. `crucible_local` e
`claude_local` continuam existindo como opções, mas hoje nenhum agente os usa. Trocar o adapter
exige trocar o formato do modelo (ver `context/twinforge-context.md`).

## Estrutura (escalável — um agente novo é só uma pasta nova)

```
agents/
  <role_key>/
    agent.yaml       # metadado portável: role, modelo, budget, skills, permissões
    AGENTS.md         # instrução principal — SEMPRE esse nome exato (ver "Compatibilidade" abaixo)
    CONTEXT.md         # ponteiro curto, quando existe
    HEARTBEAT.md       # idem (Backend e DevOps; o do PO ainda é o template genérico)
    SOUL.md             # idem
    TOOLS.md             # idem
```

No adapter `pi_local` **só o `AGENTS.md` chega ao prompt** do agente (verificado em
2026-10-15). Os outros arquivos do pacote ficam guardados na instância, mas o agente não os
lê: regra escrita só neles é ignorada. Por isso eles são ponteiros, e toda regra de
comportamento mora no `AGENTS.md` ou numa skill.

`<role_key>` usa a mesma chave de `twinforge/roles.yaml` pro pod de
e-commerce; pods sem `roles.yaml` próprio (Mobile, transversal) seguem o
mesmo vocabulário mesmo assim, por consistência. Um agente novo só precisa
de uma pasta nova com o mesmo padrão; não tem limite de profundidade ou
arquivo fixo além de `agent.yaml` — o resto (`AGENTS.md`, `CONTEXT.md`,
etc.) é o que aquele agente específico já usa hoje, nem todo agente tem os
mesmos (o Concierge, por exemplo, não tem nenhum `.md` próprio hoje — roda
só com a skill anexada).

## `agent.yaml` — portável de propósito, com duas referências resolvidas na instalação

- `{{company_id}}` dentro de `desired_skills` — placeholder, nunca um id
  real. O instalador substitui pelo id da company de destino. Skills
  cross-org (ex. `air-bizapps/agentes-forjados/blocked-exige-blocker-declarado`)
  não têm esse placeholder — já são portáveis como estão, vêm de fora da company.
- `reports_to` usa a **chave de papel**, não um id cru (`tech_lead`,
  não `8b56981f-...`). Exceção: `po` — nenhum agente deste repo cria um
  PO; toda company TwinForge já tem o seu, o instalador só aponta pra
  ele. Isso implica uma ordem de criação: quem tem `reports_to: tech_lead`
  só pode ser criado depois que `tech_lead` já existir na company de
  destino (precisa do id real pra preencher `reportsTo`).

- `env` (opcional) — variáveis de ambiente do agente, só como
  **referência** a um secret da company: `NOME: { secret_ref: <chave do
  secret> }`. O valor nunca entra no repo. Na instância isso vira
  `adapterConfig.env.NOME = { type: secret_ref, secretId, version: latest }`,
  e o instalador resolve a chave pro `secretId` da company de destino. O
  secret tem que existir antes (`twinforgecli secrets create --value-env`).
  Hoje só o PO usa (`SLACK_BOT_TOKEN`).

## Limitação conhecida — o `.md` ainda não está templatizado

`agent.yaml` é portável; **o conteúdo de `AGENTS.md`/`CONTEXT.md` ainda não
é.** Eles foram puxados exatamente como estão em produção, e têm ids reais
do Bunge embutidos no meio do texto (ex. "acione o Workflow Manager,
agent `64510e89-...`", ou o id do Kaio como aprovador). Reaproveitar isso
num projeto novo hoje significa ler o arquivo e trocar esses ids à mão —
ainda não existe um passo de template automático pra essa parte. Fica
registrado aqui como próximo passo, não resolvido nesta rodada.

## Compatibilidade — por que os nomes de arquivo não mudam

Dois lugares leem esses arquivos, com a mesma expectativa de nome:

- **Modelos remotos da instância** (`pi_local`/`crucible_local`): o
  TwinForge serve esses arquivos pro agente real em
  `instructionsRootPath/<arquivo>` — o nome importa, é assim que o agente
  acha sua própria instrução. Não renomeie `AGENTS.md` pra outra coisa.
- **Claude Code local**: quando alguém abre uma sessão com `cwd` dentro de
  `agents/<role_key>/`, o Claude Code lê `AGENTS.md` (ou `CLAUDE.md`)
  automaticamente como instrução de projeto — então manter o nome exato
  também significa que um humano rodando Claude Code ali vê exatamente o
  que o agente remoto vê, sem tradução.

Por isso a regra geral deste repo: o texto das instruções é agnóstico de
quem está lendo (nenhum "use a tool X do Claude", nenhuma assunção de uma
interface específica) — isso já era uma prática daqui (ver `instructionsBundleMode:
managed` e os guardrails tipo "Headless: nunca pergunta e espera; usa
interactions/stages" nas skills de OpenSpec), e continua valendo
igualmente aqui.
