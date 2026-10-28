---
classe: [C3]
card: BUN-14079
origem: 35cbae78 → 8a1b6b11
detector: [UI-DS-NO-COMPONENT, UI-COLUMN-DATATYPE-ATTR]
status: promovido
---
**Sintoma:** form em spinner infinito, sem erro em log, console ou rede. Grids com exceção ao montar.
**Errado:** `<dataSource>` só com `<argument name="dataProvider" xsi:type="configurableObject">`,
sem componente JS; `column` com `dataType="select"` como atributo.
**Certo:** `<dataSource ... component="Magento_Ui/js/form/provider">` (modelo:
`vendor/magento/module-inventory-admin-ui/view/adminhtml/ui_component/inventory_source_form.xml`);
`dataType` dentro de `settings`.
**Escapou também:** os testes unitários do agente continuaram importando as classes pelo namespace
antigo (`CLASS-MISSING` em `Test/`), mesmo depois do fix humano.
