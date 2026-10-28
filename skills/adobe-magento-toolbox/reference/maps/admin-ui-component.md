# Mapa: grid e form do admin (UI Component)

Use com `magento-trace admin/<frontName>/<controller>/<action>` ou `magento-trace --ui <nome>`, e o
`ui-render-check <nome>` num ambiente com banco. Exemplo funcionando: `magento-example ui-listing` e
`magento-example ui-form`.

```
GET /admin/acme_reference/item/index
 1. menu.xml (action acme_reference/item/index, resource = ACL) -> acl.xml
 2. etc/adminhtml/routes.xml (router admin, route id acme_reference)
 3. controller Controller/Adminhtml/Item/Index.php (ADMIN_RESOURCE = id do acl.xml)
 4. layout view/adminhtml/layout/acme_reference_item_index.xml -> <uiComponent name="acme_reference_item_listing"/>
 5. view/adminhtml/ui_component/acme_reference_item_listing.xml   (nome do arquivo = namespace)
 6. dataSource acme_reference_item_listing_data_source, component="Magento_Ui/js/grid/provider"
 7. dataProvider: classe real + etc/di.xml mapeia o nome do dataSource para a coleção da grid
 8. no navegador: Magento_Ui/js/core/app cria os componentes; provider e deps = <arquivo>.<dataSource>
 9. dados: POST mui/index/render -> DataProvider::getData() -> collection
```

O form segue o mesmo caminho (`edit`, `uiComponent ..._form`) com `component="Magento_Ui/js/form/provider"`,
DataProvider próprio que estende `ModifierPoolDataProvider`, botões em classes e template
`templates/form/collapsible`.

## Por que o spinner fica infinito sem erro (C3)

O spinner some quando todos os componentes de que a página depende existem. Nada avisa quando um nunca
nasce. Causas reais, todas acusadas pelo `magento-verify` ou pelo `ui-render-check`:

| Causa | Regra |
|---|---|
| dataSource sem `component` (provider JS) | `UI-DS-NO-COMPONENT` |
| `provider`/`deps` com nome que não existe no arquivo; `spinner` diferente do `<columns name>` | `UI-DEPS-UNRESOLVED` |
| form sem `template` nem layout de abas: espera `<form>.areas`, que nunca existe | `UI-FORM-NO-LAYOUT`, `UI-RENDER-SPINNER-ORPHAN` |
| `elementTmpl`/`template` inventado (`UI_form_date`) | `UI-TEMPLATE-MISSING` |
| `dataType` como atributo de `column`; `item actions` em `argument data` | `UI-COLUMN-DATATYPE-ATTR`, `UI-LEGACY-ACTIONS` |

## Regras

- Nomes que têm que bater: arquivo = namespace; `provider` e `deps` = `<arquivo>.<dataSource>`; `spinner` = `<columns name>`; chave em `di.xml` (`collections`) = nome do dataSource.
- Botão é classe `ButtonProviderInterface`; `<button>` não tem `<action>` nem `<text>`.
- Copie do core, nunca da memória: `cms_block_listing.xml`, `cms_block_form.xml` (no `vendor/magento/module-cms`).
- Doc Adobe: [UI components](https://developer.adobe.com/commerce/frontend-core/ui-components/).
