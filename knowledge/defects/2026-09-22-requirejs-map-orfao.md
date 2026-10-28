---
classe: [C7]
card: BUN-14079
origem: 35cbae78 → e7fc7303
detector: [JS-REQUIREJS-TARGET]
status: promovido
---
**Sintoma:** map do RequireJS para arquivo que não existe (namespace anterior à mudança de módulo).
**Errado:** `'Webjump_BungeListPrice/js/view/currency-brl'` mapeado para ele mesmo, sem o arquivo.
**Certo:** remover; `Vendor_Module/js/x` resolve sozinho pelo caminho padrão.
