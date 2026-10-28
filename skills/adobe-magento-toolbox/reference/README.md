# Módulo de referência `Acme_Reference`

Um exemplo **funcionando** de cada customização Magento 2.4.8 que os agentes fazem no dia a dia.
O agente consulta este módulo antes de escrever um artefato (`magento-example <tema>`); se o padrão
não estiver aqui, a mesma ferramenta mostra exemplos do `app/code` do projeto e depois do
`vendor/magento`.

Não é instalado em projeto nenhum. O agente copia a **forma** do arquivo e troca `Acme\Reference`,
`Acme_Reference` e `acme_reference` pelo próprio módulo. O `magento-verify` reprova código com
esses nomes em `app/code` (`REFERENCE-NOT-RENAMED`).

## O que tem (temas do `magento-example --list`)

| Área | Temas |
|---|---|
| Estrutura | `module`, `db-schema`, `data-patch`, `eav-product-attribute`, `eav-customer-attribute` |
| Domínio | `service-contract` (update parcial correto), `model`, `plugin` (before, around e after numa classe), `extension-attribute` |
| APIs | `rest` (CRUD com ACL), `graphql` (query com cache, mutations com ACL) |
| Admin | `ui-listing` (filtros, busca, mass action, inline edit), `ui-form` (DataProvider, botões, data), `admin-controller`, `config` (campo criptografado) |
| Assíncrono e integração | `observer`, `queue`, `email`, `cron`, `cli`, `logger`, `http-client` |
| Storefront | `frontend-page`, `viewmodel`, `customer-account`, `js` (x-magento-init, data-mage-init, Knockout), `mixin`, `less`, `checkout-field` |
| Testes | `unit-test` |

Cada arquivo diz no cabeçalho qual padrão segue e quais armadilhas evita, com as classes de
defeito (C1–C11 de `knowledge/README.md`) que originaram a regra.

## Como foi provado (21/10/2026, Magento 2.4.8-p5 do Bunge, serviços nativos do laptop)

| Prova | Resultado |
|---|---|
| `magento-verify --path app/code/Acme/Reference` | OK: 52 PHP, 47 classes carregadas, 28 XML, 63 classes citadas |
| `setup:upgrade` | tabela, colunas em `quote`/`sales_order`, 2 atributos, fila e itens do patch criados |
| `setup:db-declaration:generate-whitelist` | `etc/db_schema_whitelist.json` gerado pelo comando |
| Testes unitários | 8 testes, 13 asserções, verdes |
| REST com token de admin | 401 sem token; POST normaliza o código (plugin before); PUT só com `name` mantém o resto; filtro; DELETE do item `SYSTEM_` barrado (plugin around); 404 depois do delete |
| GraphQL | query pública com filtro e paginação; mutation sem token barrada pela ACL; create, update parcial e delete com token; erros `graphql-input` e `no-such-entity` |
| `ui-render-check` | grid e form renderizam; sem o `component` do dataSource o render acusa `UI-RENDER-DS-NO-COMPONENT` |
| Storefront `/acme-reference` | 200, itens, escopo Knockout e `data-mage-init` no HTML |
| Fila | save publica 1 mensagem; o consumer processa (status 4); falha de SMTP vai para `var/log/acme_reference.log` sem nova tentativa infinita |
| Cron | item sem atualização há 200 dias é desativado |
| `bin/magento acme:reference:items:list` | lista e valida `--status` |

Para repetir: `integrations/local-verify/README.md` (ambiente) e a seção "Provar o módulo" de lá.
