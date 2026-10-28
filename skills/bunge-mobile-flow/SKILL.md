---
name: bunge-mobile-flow
description: "Contrato do fluxo de trabalho mobile do Bunge na TwinForge (Tech Lead Mobile + Mobile Developer): fluxo leve sem OpenSpec, IDs fixos, convenção de git/branch/push, workspace, relatório de entrega, convenção de status in_review vs blocked. Leia sempre antes de trabalhar numa card do Project Bunge Mobile."
---

# bunge-mobile-flow

Contrato do fluxo de trabalho mobile do Bunge na TwinForge. Fluxo **leve**
(sem OpenSpec) — a POC/MVP está em fase de exploração, não precisa do
aparato completo de proposta/specs/review formal que o time de e-commerce
usa (`bunge-openspec-flow`). Se este projeto amadurecer pra produto real,
revisitar essa decisão.

## IDs fixos

| Quem | ID |
|---|---|
| Tech Lead Mobile | agent `{{uuid}}` |
| Mobile Developer | agent `{{uuid}}` |
| PO | agent `{{uuid}}` |
| Kaio (aprovador humano) | user `zKCKeiFpjszwCIrujWle8voAIbrX8Ebn` |

Repositório: `air-bizapps/bunge-mobile` (privado).
Workspace compartilhado: `/twinforge/instances/default/workspaces/bunge-mobile-project/bunge-mobile`.
Project TwinForge: `Bunge Mobile` (id `{{uuid}}`).

## Projeto

Issues deste escopo vivem num **Project TwinForge separado** do projeto de
e-commerce Magento — nunca criar uma issue de mobile no project do Magento
nem vice-versa. `project_id` dedicado, board próprio.

## Fases e donos

| # | Fase | Dono | Termina quando |
|---|---|---|---|
| 1 | Triagem + refinamento | Tech Lead Mobile | Card entendido, critério de aceite escrito (pela Tech Lead Mobile, se a demanda não trouxer um — mesmo princípio de `bunge-vague-demand-triage`), decomposto em subtarefas |
| 2 | Decisões técnicas sensíveis | Tech Lead Mobile, escalando pro Kaio/PO quando for o caso | Decisão registrada na issue antes de decompor (ex.: Expo vs bare, estrutura de navegação) |
| 3 | Implementação | Mobile Developer, uma subtarefa por vez | Subtarefa `done` com relatório de entrega (arquivo:linha do que foi feito, como testar manualmente) |
| 4 | Push e fechamento | Tech Lead Mobile | Código no `origin`, PR aberto pra `main`, card `done` |

Sem stage de review formal tipo C1-C16 do Magento nessa fase inicial — a
Tech Lead Mobile é quem confere o trabalho do Mobile Developer antes de
fechar a subtarefa, lendo o diff e, quando possível, rodando o app
(simulador/emulador) ela mesma antes de declarar `done`.

## Ambiguidade — pergunte antes de implementar, não depois

Mesmo mecanismo de `bunge-vague-demand-triage`: card vago, decisão técnica
com mais de um caminho razoável (ex. "qual URL a WebView aponta em cada
ambiente?", "o app precisa funcionar offline de alguma forma?") vira uma
interaction `ask_user_questions` na card, antes de decompor em subtarefas —
não uma suposição silenciosa. Perguntar depois que o código já foi escrito
custa muito mais caro numa feature nativa (rebuild, re-deploy) do que numa
mudança web.

## Convenção de status: `in_review` vs. `blocked`

Achado real em BUN-157 (05/10/2026): a Tech Lead Mobile marcou o card
`blocked` via comentário quando o push falhou por token GitHub expirado —
sem `status` real nem interaction. A issue ficou `blocked` por 34h, e o
TwinForge criou automaticamente uma issue nova de "Unblock" **a cada hora**
(11 no total) pro PO, sem que nenhuma resolvesse o problema, porque a causa
raiz (rotacionar o token) só um humano resolve.

Carregue `blocked-exige-blocker-declarado` sempre que for gravar `blocked`
ou precisar de decisão humana (pergunta de escopo, decisão técnica
sensível, credencial/infra quebrada) — ela ensina o protocolo certo
(`ask_user_questions`/`request_confirmation` com `continuationPolicy:
wake_assignee`, e quando `blocked` é mesmo o caso certo). Usar `blocked`
pra pergunta/credencial dispara o alarme de demora do TwinForge pro PO à
toa, repetidamente, sem resolver nada — exatamente o que aconteceu aqui.

## Git, branch e push

- Branch por card: `feature/<ID-externo ou nome curto>`, a partir de `main`.
- Um PR por card, para `main`, título `<ID> - <Título>`.
- **Mobile Developer**: commita no checkout compartilhado, **nunca dá push**
  — mesma regra do time de e-commerce (`bunge-engineering-practices`).
- **Tech Lead Mobile**: dá push quando a(s) subtarefa(s) do card estão
  `done` com relatório de entrega, abre o PR, fecha o card.

## Versionamento — toda entrega visível é uma versão rastreável

O Kaio precisa conseguir escolher/voltar pra qualquer versão específica do
app, não só confiar no histórico de commits. Isso é responsabilidade da
**Tech Lead Mobile**, no momento do push (fase 4), não do Mobile Developer:

1. **Bump de versão em `app.json`** (`expo.version`) — semver: `PATCH` pra
   fix, `MINOR` pra feature nova, `MAJOR` reservado (não usar nesta fase de
   POC). Ex.: `0.1.1` → `0.1.2` pra um fix, → `0.2.0` pra uma feature.
2. **Atualizar o texto do `DemoBanner`** (`src/components/DemoBanner.tsx`,
   constante `BUILD_LABEL`) pra refletir a nova versão — é a faixa vermelha
   no topo do app que existe só nesta fase de demo, pra quem testa saber na
   hora se recarregou a build certa. Não remover o `DemoBanner` sem pedido
   explícito do Kaio.
3. **Tag git no commit que vai pra `main`**: `v<version>` (ex. `v0.1.2`).
4. **GitHub Release** na mesma tag, com notas resumindo o que mudou:
   `gh release create v<version> --notes "<resumo>"`.
5. **`CHANGELOG.md`** na raiz do repo — uma entrada por versão, formato
   Keep a Changelog (`### Added` / `### Changed` / `### Fixed`), atualizado
   no mesmo commit que faz o bump de versão.

Isso dá duas formas de "escolher uma versão": a tag/release no GitHub (pra
baixar ou buildar aquele ponto específico), e o `CHANGELOG.md` (pra saber o
que cada versão tinha, sem precisar ler o diff bruto).

## Relatório de entrega (Mobile Developer)

Mesmo princípio de `bunge-engineering-practices`: todo `done` vem com um
relatório dizendo o que mudou (arquivo:linha), e — específico de mobile —
**como testar manualmente** (ex. "rode `npx expo start`, abra no Expo Go ou
num simulador, navegue até X, confira Y"), já que não há um ambiente de
staging automático tipo o `bunge.local` do Magento nesta fase do MVP.

## Testes

Segue `react-native-standards` — Mobile Developer **escreve** testes
unitários/componente para lógica não-trivial (ex. handlers de navegação,
lógica de retry da WebView), mas não há meta de cobertura nem gate de CI
nesta fase de POC. Rodar manualmente antes de reportar `done` é obrigatório
(ver seção acima), rodar a suíte de testes automatizada é recomendado mas
não bloqueia.

## Quando usar OpenSpec aqui

Não usar, por decisão explícita desta fase (POC/MVP). Se o escopo crescer
pra múltiplas features native (login nativo, push, deep link) depois da
validação inicial, reavaliar com o Kaio se vale adotar o fluxo completo —
não decidir isso unilateralmente.

