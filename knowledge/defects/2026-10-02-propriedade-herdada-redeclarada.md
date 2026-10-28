---
classe: [C4]
card: BUN-889
origem: d1ed6563^ → d1ed6563 e 0c8c6d7e^ → 0c8c6d7e
detector: [PHP-LOAD]
status: promovido
---
**Sintoma:** fatal ao carregar a classe (`setup:upgrade`, `di:compile`); `php -l` passa.
**Errado:** `private RedirectFactory $resultRedirectFactory` num controller (o pai declara `protected`);
`protected Collection $collection` tipado num DataProvider (o pai declara sem tipo).
**Certo:** não redeclarar propriedade herdada; use a do pai.
