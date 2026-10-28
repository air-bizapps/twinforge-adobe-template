---
classe: [C3]
card: módulo de referência (adobe-magento-toolbox)
origem: acme_reference_item_form.xml sem o item template (achado pelo page-health no navegador)
detector: [UI-FORM-NO-LAYOUT, UI-RENDER-SPINNER-ORPHAN]
status: promovido
---
**Sintoma:** form do admin em spinner infinito, sem erro no console nem na rede; XSD e `ui-render-check`
(versão anterior) passavam; todos os campos existiam no `uiRegistry`.
**Causa:** sem `template`, o form usa `templates/form/default`, que mostra um spinner até o componente
`<form>.areas` (área de abas) carregar; esse componente só existe quando `settings/layout` declara abas.
**Certo:** form simples declara `<item name="template" xsi:type="string">templates/form/collapsible</item>`
no `argument data` (como `cms_block_form.xml`); form com abas declara o layout.
**Como foi achado:** `integrations/local-verify/page-health.mjs` ("spinner visível com a rede parada") e
diagnóstico pelo `uiRegistry` (spinner `data-component=<form>.areas` sem componente). Virou regra
estática (form raiz sem `extends`, sem template e sem layout; nenhum form do core cai nela) e regra do
`ui-render-check` (spinner esperando componente que não está na árvore).
