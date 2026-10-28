---
classe: [C3]
card: BUN-14079
origem: 35cbae78 → 26bec121
detector: [UI-LEGACY-ACTIONS]
status: promovido
---
**Sintoma:** grid quebra com "Call to undefined method Context::addActions()".
**Errado:** `<item name="actions">` irmão de `config` no `argument data` da `actionsColumn`.
**Certo:** remover; as ações vêm do `prepareDataSource()` da classe da coluna
(modelo: `Magento\Cms\Ui\Component\Listing\Column\BlockActions`).
