---
classe: [C1, C4]
card: BUN-889
origem: d1ed6563^ → d1ed6563
detector: [CLASS-MISSING, PHP-LOAD]
status: promovido
---
**Sintoma:** `setup:di:compile` para.
**Errado:** retorno `SearchResultsInterface` sem `use`: o PHP lê `Webjump\Seller\Model\SearchResultsInterface`.
**Certo:** `use Magento\Framework\Api\SearchResultsInterface;`.
