---
classe: [C1]
card: BUN-14079
origem: 35cbae78 → 2fe91c9d
detector: [CLASS-MISSING]
status: promovido
---
**Sintoma:** "Call to a member function addFieldToFilter() on null" ao abrir a edição.
**Errado:** `Magento\Ui\DataProvider\DocumentDataProvider` e `Magento\Ui\DataProvider\Form\Modifier\Data`
(nunca existiram; também `...\UiComponent\DataProvider\SearchFilter`).
**Certo:** DataProvider próprio estendendo `Magento\Ui\DataProvider\AbstractDataProvider` com a
collection no construtor (modelo: `Magento\Cms\Model\Block\DataProvider`).
