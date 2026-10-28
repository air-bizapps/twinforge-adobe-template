# Mapa: da URL do storefront até o JavaScript

Use com `magento-trace <url>`, que mostra esta cadeia com os arquivos reais do projeto e marca com
`X` o elo quebrado. Exemplo funcionando: `magento-example frontend-page` e `magento-example js`.

```
GET /acme-reference/index/index
 1. pub/index.php -> FrontController percorre os roteadores (robots, urlrewrite, standard, cms, default)
 2. roteador standard: frontName "acme-reference" -> etc/frontend/routes.xml (route id acme_reference)
 3. controller: Acme\Reference\Controller\Index\Index (pasta Controller/<controller>/<Action>.php)
 4. handle de layout: <route id>_<controller>_<action> = acme_reference_index_index
 5. layout: view/frontend/layout/acme_reference_index_index.xml (de TODOS os módulos e do tema)
 6. bloco: <block template="Acme_Reference::items.phtml"> + argumento view_model (ViewModel)
 7. template: view/frontend/templates/items.phtml (o tema pode sobrescrever em app/design/frontend/Vendor/tema/Acme_Reference/templates/)
 8. JS: <script type="text/x-magento-init"> e data-mage-init='{"alias": {...}}'
 9. RequireJS: "Acme_Reference/js/item-list" -> view/frontend/web/js/item-list.js
                 alias "acmeToggleDescription" -> map em view/frontend/requirejs-config.js
10. Knockout: uiComponent com template "Acme_Reference/item-counter" -> view/frontend/web/template/item-counter.html
```

## Onde cada elo quebra (e a classe de defeito)

| Elo | Defeito comum | Como aparece |
|---|---|---|
| 2 routes.xml | frontName com `_` trocado por `-`, router errado (admin x standard) | 404 |
| 3 controller | pasta ou nome da action diferente do URL; `new` exige a classe `NewAction` | 404 |
| 4 handle | layout com nome do frontName em vez do route id | página vazia, sem erro |
| 6 bloco | classe de bloco inventada; ViewModel que não implementa `ArgumentInterface` | exceção na página (C1) |
| 7 template | caminho `Modulo::arquivo.phtml` que não existe | bloco some em silêncio |
| 8-9 JS | componente com caminho errado ou alias sem map | erro de RequireJS no console (C7) |
| 10 Knockout | template inventado | componente não renderiza, sem erro claro (C3) |

## Regras

- O handle é `route id`, não `frontName`: `acme_reference_index_index`, mesmo com URL `acme-reference`.
- `Vendor_Module/js/x` resolve sozinho para `view/<área>/web/js/x.js`; só alias precisa de `map`.
- Template Knockout `Vendor_Module/x` = `view/<área>/web/template/x.html`; `ui/x` = `Magento_Ui/templates/x.html`.
- No template, todo valor impresso passa pelo `$escaper`; JSON para `x-magento-init` sai do ViewModel com `JsonHexTag`.
- Doc Adobe: [Routing](https://developer.adobe.com/commerce/php/development/components/routing/),
  [Layouts](https://developer.adobe.com/commerce/frontend-core/guide/layouts/),
  [Call and initialize JS](https://developer.adobe.com/commerce/frontend-core/javascript/init).
