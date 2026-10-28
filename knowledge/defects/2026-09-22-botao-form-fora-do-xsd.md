---
classe: [C2]
card: BUN-14079
origem: 35cbae78 → 513636aa
detector: [XML-XSD]
status: promovido
---
**Sintoma:** edição abre com "Element 'action': This element is not expected".
**Errado:** `<button name="save">` com `<action>` e `<text>` filhos.
**Certo:** `<button name="save" class="...\SaveButton"/>` com a classe implementando
`ButtonProviderInterface` (modelo: `vendor/magento/module-cms/view/adminhtml/ui_component/cms_block_form.xml`).
