---
name: bunge-domain-lookup
description: "Responde \"qual e a regra de X no Bunge?\" com evidencia (codigo + docs/module-index.md + docs/vendor-map.md + specs OpenSpec arquivadas), lendo o minimo necessario. O codigo e a fonte de verdade; divergencia com a doc e reportada. Use para qualquer pergunta de regra de negocio do projeto."
---

# bunge-domain-lookup

Objetivo: responder "qual é a regra de X no Bunge?" com evidência, lendo o mínimo.
**O código é a fonte de verdade.** A doc diz onde olhar e o porquê. Se doc e
código divergem, vale o código, e a divergência é reportada.

## Onde está cada coisa

```bash
WS=/twinforge/instances/default/workspaces/{{uuid}}
REPO="$WS"; [ -d "$WS/bunge-magento2/.git" ] && REPO="$WS/bunge-magento2"
cd "$REPO"
```

| Arquivo | Para quê |
|---|---|
| `docs/README.md` | Tabela "Qual arquivo carregar": área da pergunta → uma doc de domínio |
| `docs/<dominio>.md` | Regras documentadas; o frontmatter `modules:` diz quais módulos a doc cobre |
| `docs/module-index.md` | Todo módulo Webjump: doc que o cobre (ou **sem doc**) e pontos de entrada (REST, cron, observers, filas, tabelas, config, data patches) |
| `docs/vendor-map.md` | Classe nativa do Magento/Braspag/Amasty sobrescrita ou interceptada por Webjump |
| `openspec/specs/<capability>/spec.md` | Regras já especificadas e entregues por changes arquivadas |

Se a branch atual não tiver `docs/module-index.md`, leia da base sem trocar de
branch: `git show origin/twinforge:docs/module-index.md`.

## Passos

**1. Identifique a área e o módulo.** Pela pergunta, ache a linha em
`docs/README.md`. Se já sabe o módulo (nome de classe, tabela, rota, tela do
admin), procure-o direto em `docs/module-index.md`:
```bash
grep -n "^### <Modulo>" -A25 docs/module-index.md
```

**2. Leia só o necessário.**
- Módulo **com doc** → leia a doc apontada (só ela; siga "Se você precisar ir
  além daqui" no fim da doc apenas se a pergunta exigir).
- Existe spec em `openspec/specs/` para a capability → leia também.
- Módulo **sem doc** → pule para o passo 3 usando os pontos de entrada do índice.
Nunca leia o diretório `docs/` inteiro.

**3. Confirme no código (sempre, inclusive quando a doc responde).**
- Comece pelos pontos de entrada do índice: rota REST → classe de serviço;
  cron → método; observer → classe; consumer → handler; data patch → valores
  criados (status, atributos, configs); `system.xml` → caminho de config.
- Depois leia só a classe que decide a regra (`Model/Command/*`, `Plugin/*`,
  `ViewModel/*`). Use `grep -rn "<termo>" app/code/Webjump/<Modulo>/` restrito ao
  módulo; grep em `app/code/` inteiro só se o índice não ajudar; em `vendor/`,
  só via `docs/vendor-map.md`.
- Regra que depende de valor configurado no admin (não no código): diga isso e
  diga o caminho da config. Não afirme o valor em produção.

**3.5 Verifique camadas de sobrescrita (sempre, após confirmar no código).**
Depois de encontrar a regra nativa ou documentada, verifique se algo muda o
comportamento em produção. Uma regra que "existe no código" pode ser
silenciosamente sobrescrita por configuração ou interceptação.

1. **Configurações do admin ativas** — Procure `system.xml` com
   `showInWebsite="1"` ou `showInStore="1"` que permitem ligar/desligar a
   feature. Exemplo: `btob/website_configuration/catalog_price_visibility`
   esconde preço de não logados, invalidando fluxos que assumem preço visível.
   Diga o caminho da config e que o valor em produção depende do admin.

2. **Plugins** — `etc/di.xml` com `<plugin name="..." type="..."` que
   intercepta `before/after/around` a classe nativa. Um plugin pode mudar
   completamente o comportamento sem tocar na classe original.

3. **Observers** — `etc/frontend/events.xml` ou `etc/events.xml` com
   `<observer name="..." class="...">` que reage a eventos do ciclo de
   vida (ex: `controller_action_predispatch_*`, `checkout_cart_add_product_complete`).

4. **Preferências di.xml** — `<preference for="..." type="...">` que
   substitui a classe nativa por uma customizada via DI.

5. **Vendor map** — Se `docs/vendor-map.md` lista a classe nativa como
   sobrescrita ou interceptada por Webjump, leia a versão customizada.

Se encontrar camada de sobrescrita, o **comportamento real** é o resultado da
camada, não da regra nativa. Reporte ambos.

**4. Classifique e responda com evidência.**

| Classificação | Quando | Como responder |
|---|---|---|
| **Documentada e confirmada** | Doc descreve e o código faz | Regra + doc (arquivo, seção) + código (arquivo:linha) |
| **Só no código** | Módulo sem doc, ou doc omissa, mas o código faz | Regra + código (arquivo:linha) + "não está documentada em docs/" |
| **Divergente** | Doc diz uma coisa, código faz outra | O que o código faz (vale este) + o que a doc diz + os dois locais |
| **Não encontrada** | Nem doc, nem spec, nem código | Exatamente: "Não encontrei essa regra documentada nem implementada no código." + onde procurou |

## Regra inegociável — nunca inventar

Se não encontrar em `docs/`, em `openspec/specs/` e no código, responda:
"Não encontrei essa regra documentada nem implementada no código." e liste
onde procurou. "Não encontrei" é uma resposta correta; uma regra inventada não é.

- Nunca responda com conhecimento genérico de Magento ou de e-commerce B2B.
- Nunca invente nome de classe, método, atributo, tabela ou número de linha.
  Todo caminho citado precisa ter sido aberto nesta sessão.
- Nunca use "provavelmente", "deve ser" ou "normalmente" sobre uma regra.
- Nunca confie só na doc: ela pode estar desatualizada ou incompleta.
- "Não encontrado" escrito dentro de uma doc significa que alguém já buscou no
  código e não achou; não é suposição.

## Formato da resposta

```
Regra: <uma frase>
Classificação: <documentada e confirmada | só no código | divergente | não encontrada>
Doc: <docs/arquivo.md, seção> (ou "nenhuma")
Código: app/code/Webjump/<Modulo>/<Caminho>/<Arquivo>.php, linha <n> (um ou mais)
Camadas de sobrescrita: <config ativa, plugin, observer, preference que altera o comportamento nativo, ou "nenhuma">
Comportamento real: <o que o usuário vê em produção, considerando as camadas de sobrescrita>
Observação: <config do admin, divergência, limite do que foi verificado>
```

Exemplo real (verificado em 2026-09-24):

```
Regra: cliente com credit_block = R, ou com isblocked/is_blocked ativo, não entra no checkout; é redirecionado ao carrinho.
Classificação: divergente (doc incompleta)
Doc: docs/cadastro-de-cliente.md, seção 9 — atribui o bloqueio do checkout só a isblocked/is_blocked; para credit_block = R fala só em "nenhum método de pagamento".
Código: app/code/Webjump/CustomCheckout/Observer/CheckCustomerBlocked.php, linhas 82-92 (observer de controller_action_predispatch_checkout_index_index, em etc/frontend/events.xml); a regra de métodos de pagamento está em Webjump\PaymentTerm\Plugin\Model\ValidatePaymentMethodPlugin.
Camadas de sobrescrita: nenhuma
Comportamento real: o observer bloqueia o acesso ao checkout e redireciona ao carrinho antes de qualquer validação de pagamento.
Observação: o código também bloqueia o checkout para credit_block = R, o que a doc não diz.
```

Exemplo com camada de sobrescrita:

```
Regra: modal de autenticação (login popup) aparece no checkout quando guest checkout está desabilitado.
Classificação: só no código
Doc: nenhuma (README não cita autenticação por modal)
Código: vendor/magento/module-customer/view/frontend/web/js/view/authentication-popup.js (componente Knockout do modal); app/design/frontend/Webjump/theme-frontend-bunge/Magento_Checkout/web/js/disable-viewandedit-button-mixin.js (mixin que chama authenticationPopup.showModal())
Camadas de sobrescrita: config B2B btob/website_configuration (catalog price visibility) — quando preços são ocultos para não logados, o cliente não consegue adicionar produtos ao carrinho sem estar logado, então nunca chega ao checkout como convidado.
Comportamento real: o modal existe no código e funciona, mas é inalcançável no fluxo normal porque o cliente é forçado a fazer login antes de adicionar ao carrinho (mensagem "faça login pra comprar"). O modal só seria alcançável se a config de preço oculto fosse desativada.
Observação: a config de visibilidade de preço é definida no admin; o valor em produção depende da configuração ativa.
```

Exemplo de "não encontrada":

```
Regra: limite de itens por pedido para clientes do Nordeste.
Classificação: não encontrada
Doc: nenhuma (README → carrinho-de-compras.md e checkout-finalizacao-pedido.md não citam)
Código: nada — busquei "limit", "max_qty" e "region" em app/code/Webjump/BungeCart e CustomCheckout, e nos pontos de entrada desses módulos no module-index.
Observação: "Não encontrei essa regra documentada nem implementada no código." Se existir, pode ser configuração do admin; me diga a tela ou o módulo e eu verifico.
```

## Uso no OpenSpec

No `openspec-propose`, rode estes passos para cada regra que a card toca e
leve o resultado para a seção `## Regras de negócio consultadas` do
`design.md`, agrupado por classificação. Toda regra **não encontrada** que muda
escopo ou critério de aceite vira pergunta (interaction), nunca suposição.
