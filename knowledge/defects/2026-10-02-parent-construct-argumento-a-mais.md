---
classe: [C4]
card: BUN-889
origem: d1ed6563^ → d1ed6563
detector: [PHP-PARENT-CTOR]
status: promovido
---
**Errado:** `parent::__construct($context, $uiComponentFactory, $logger, $components, $data)` numa
`Column`: o logger cai no slot `$components` (array).
**Certo:** passar exatamente os parâmetros do construtor do pai (`magento-api` mostra a assinatura).
