---
name: "TwinForge: Help"
description: "List all /twinforge commands and what each one does"
allowed-tools: []
category: "TwinForge"
tags: ["twinforge", "help"]
---

Print this list as-is (adjust formatting to the response, but keep every line's content):

**`/twinforge:explore`** (pacote de skills `twinforge`, instalado à parte) — pedido livre em linguagem natural; interpreta e roda o `twinforgecli` certo.

**`/twinforge:setup`** (pacote de skills `twinforge`) — instala/conecta o `twinforgecli`, faz o login e escolhe a instância, company e projeto.

**`/twinforge:instances`** — lista e troca a instância em uso (este repo pode estar ligado a mais de uma).

**`/tfrepo:status`** — compara `twinforge-repo/` (skills + agentes) contra a company ao vivo.
Só leitura, não aplica nada. Rode antes de `pull`/`push`.

**`/tfrepo:pull`** — traz skills e instruções de agente da company ao vivo pra
`twinforge-repo/`. A nuvem sempre vence.

**`/tfrepo:push`** — aplica o que está em `twinforge-repo/` na company ao vivo (cria/renomeia
skill, resincroniza `desiredSkills` dos agentes afetados). Escreve em produção — roda
`/tfrepo:status` antes.

**`/tfrepo:agent <role_key> [status|skills|wake|instructions]`** — operação pontual num
agente só, sem precisar de um pull/push inteiro.

**`/tfrepo:local-sync`** — sincroniza a instância local (127.0.0.1) com a nuvem, via
`twinforgecli sync ...`. Nunca inicia o servidor local sozinho.

**`/tfrepo:init`** — primeira configuração guiada, pra projeto Magento que ainda não tem
TwinForge (cria company, PAT do GitHub, projeto, primeiro agente).

**`/tfrepo:help`** — esta lista.

Se o pedido do usuário não é claramente um desses, aponte pro mais próximo em vez de inventar um
comando que não existe.
