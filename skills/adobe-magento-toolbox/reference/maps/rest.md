# Mapa: chamada REST até o banco

Use com `magento-smoke` (ambiente com banco) e `magento-api <interface>`. Exemplo funcionando:
`magento-example rest` e `magento-example service-contract`.

```
PUT /rest/V1/acme-reference/items/5   {"item": {"name": "Novo"}}
 1. Webapi: etc/webapi.xml -> rota + <service class=interface method=save> + <resource ref=ACL>
 2. autenticação: token (admin, integração ou cliente) -> ACL do resource; "anonymous" pula tudo
 3. ServiceInputProcessor: monta os argumentos pelo NOME dos parâmetros e pelos TIPOS DO DOCBLOCK
    - {"item": {...}} vira ItemInterface (preference no di.xml) com só os setters dos campos enviados
    - :id do PUT vai para setId() do objeto do corpo
 4. preference: Acme\Reference\Api\ItemRepositoryInterface -> Model\ItemRepository (+ plugins)
 5. repositório: carrega o existente e mescla SÓ os campos enviados (update parcial), resource->save
 6. resposta: DataObjectProcessor lê os getters pelo docblock e serializa
 7. erros: NoSuchEntityException = 404, CouldNotSave/LocalizedException = 400, sem ACL = 401/403
```

## Onde quebra

| Elo | Defeito | Regra / sintoma |
|---|---|---|
| 1 | método inexistente na interface | `WEBAPI-METHOD-MISSING` |
| 2 | rota `anonymous` sem necessidade | `WEBAPI-ANONYMOUS` (aviso de segurança, C8) |
| 3 | getter/setter sem `@return`/`@param` | `WEBAPI-DOCBLOCK`: "Each method must have a doc block" só em execução (C9) |
| 4 | interface sem preference | `SMOKE-DI-PREFERENCE`: "Cannot instantiate interface" |
| 5 | update que monta objeto novo e apaga o que não veio | dado sumindo em PUT parcial (C9) |
| 5 | collection/resource com tabela errada | `SMOKE-API` |

## Regras

- O parâmetro da URL tem o mesmo nome do parâmetro do método (`:itemId` -> `getById(int $itemId)`).
- Toda rota tem resource do `acl.xml`; `self` para dado do próprio cliente logado.
- Tipos completos no docblock (`\Vendor\Module\Api\Data\ItemInterface[]`), inclusive nos DTOs.
- Doc Adobe: [Web API authentication](https://developer.adobe.com/commerce/webapi/get-started/authentication/).
