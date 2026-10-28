---
classe: [C2, C3]
card: BUN-14079
origem: 35cbae78 → 5e10b8c3
detector: [XML-XSD]
status: promovido
---
**Sintoma:** edição não abre ("Element 'dateComponent': This element is not expected").
**Errado:** `<dateComponent>`, `<subSelectors>` e opções cruas de jQuery UI no `formElement` date.
**Certo:** `<options><option name="dateFormat" xsi:type="string">dd/MM/yyyy</option></options>`.
