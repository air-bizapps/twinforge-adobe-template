---
classe: [C1, C2, C6]
card: BUN-221, BUN-229
origem: origin/feature/BUN-221, origin/feature/BUN-229 (cabeça do agente)
detector: [CLASS-MISSING, XML-XSD, PHP-HARDCODED-URL, PHP-LOAD, XML-MALFORMED]
status: promovido (acusado pelo verificador; correção humana ainda não registrada)
---
**Errado (exemplos):** `Magento\Framework\Api\SearchHandlerInterface`, `Magento\Email\Model\Template\Source`
(o real é `Magento\Config\Model\Config\Source\Email\Template`), `...\Serialized\ArrayBackend`,
`Magento\Customer\Model\Metadata`; `system.xml` sem o nó `system`; `routes.xml` com atributo `module`
na rota; namespace `Controller\Public_` em pasta `Controller/Public`; URL `bunge.com.br` fixa.
