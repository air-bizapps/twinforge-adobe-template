---
classe: [C3]
card: BUN-14079
origem: 35cbae78 → 816dcb3c
detector: [UI-TEMPLATE-MISSING]
status: promovido
---
**Sintoma:** edição fica carregando para sempre; HTTP 200, nenhum erro no servidor.
**Errado:** `elementTmpl` = `UI_form_date` e `template` = `ui/form/field/date/date`.
**Certo:** remover (o padrão do componente é `ui/form/element/date`).
