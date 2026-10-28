# Mapa: campo no checkout até o pedido

Exemplo funcionando: `magento-example checkout-field`. Use `magento-trace --handle checkout_index_index`
para ver os layouts que montam o checkout neste projeto (o Bunge tem dezenas).

```
1. layout checkout_index_index.xml -> bloco checkout.root (onepage.phtml) com o jsLayout
2. LayoutProcessor (PHP): plugin afterProcess no Magento\Checkout\Block\Checkout\LayoutProcessor
   acrescenta o campo em shipping-step > shippingAddress > shipping-address-fieldset
   - dataScope shippingAddress.custom_attributes.<campo>; template ui/form/field; elementTmpl ui/form/element/input
3. Knockout renderiza o campo; o valor fica em quote.shippingAddress().customAttributes
4. mixin JS de Magento_Checkout/js/action/set-shipping-information (requirejs-config.js > mixins)
   move o valor para shippingAddress.extension_attributes.<campo>
5. POST rest/V1/carts/mine/shipping-information (guest: guest-carts/:cartId) -> ShippingInformationManagementInterface::saveAddressInformation
   - extension_attributes.xml declara o atributo em Magento\Quote\Api\Data\AddressInterface
   - plugin before grava o valor no quote (coluna quote.<campo> do db_schema.xml)
6. place order: etc/fieldset.xml (sales_convert_quote, aspect to_order) copia quote -> sales_order
7. leitura: plugin afterGet/afterGetList no OrderRepositoryInterface expõe como extension attribute do pedido
```

## Onde quebra

| Elo | Defeito | Sintoma |
|---|---|---|
| 2 | caminho do jsLayout que não existe (checkout customizado, carrinho virtual) | campo não aparece; o plugin deve conferir o caminho antes |
| 2 | template inventado | campo não renderiza (C3) |
| 4 | mixin sem a entrada em `mixins`; ou copiar a action em vez de envolver com `wrapper` | valor não chega à API (C7) |
| 5 | atributo não declarado em `extension_attributes.xml` | erro "Property ... does not have accessor" na API |
| 6 | coluna só no quote ou só no pedido; fieldset ausente | valor some ao fechar o pedido |

## Regras

- Checkout é jsLayout (PHP) + Knockout (JS): mudança de campo passa pelo LayoutProcessor, nunca por XML de template.
- O guest usa o id mascarado e cai na mesma interface: um plugin na interface cobre os dois.
- No Bunge, confira se o checkout é o padrão do Magento antes de seguir este mapa (`magento-trace --handle checkout_index_index`).
