---
classe: [C2]
card: BUN-889
origem: 064858c3^ → 064858c3
detector: [XML-XSD]
status: promovido
---
**Errado:** `<index referenceId="..." indexType="unique">`.
**Certo:** `<constraint xsi:type="unique" referenceId="...">` (modelo: `vendor/magento/module-cms/etc/db_schema.xml`).
