---
classe: [C2]
card: BUN-14079
origem: 35cbae78 → 1d0b9b2f
detector: [XML-MALFORMED]
status: promovido
---
**Sintoma:** `setup:upgrade`/`cache:flush` quebram; menu some.
**Errado:** `resource="Vendor_Module::listprice_view` sem a aspa de fechamento em `menu.xml`.
**Certo:** fechar a aspa. Qualquer XML alterado tem que carregar no DOMDocument.
