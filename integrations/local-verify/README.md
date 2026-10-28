# Verificação local (laptop, sem Docker)

Ambiente para provar código Magento de verdade fora da instância remota: o loop externo do plano
(Playwright e Claude revisor no PR) e a prova do módulo de referência da skill
`adobe-magento-toolbox`. Usa só os serviços nativos que o Bunge já usa na máquina (MySQL, OpenSearch,
PHP), lidos do `app/etc/env.php` do checkout principal.

## O que o `setup.sh` monta

| Peça | Onde | Por quê |
|---|---|---|
| Clone compartilhado do Bunge-Magento2 na branch base dos agentes | `~/workspace/.verify/bunge` | Não toca o checkout de trabalho (branch e mudanças locais intactas) |
| `vendor` e artefatos do `magento2-base` (`bin/magento`, `lib/`, `setup/`, `pub/*.php`, `phpserver/`) em hardlink | dentro do clone | Sem custo de disco. O `vendor` em link simbólico não serve: o autoload registraria os módulos do checkout principal |
| `env.php` derivado do principal | `app/etc/env.php` do clone | Banco de verificação, `http://127.0.0.1:8099`, prefixo `twinforge_verify` no OpenSearch, sem form key e captcha no admin, modo developer |
| Cópia do banco | banco `magento2` (o usuário MySQL local já tem permissão nele) | Teste escreve à vontade sem mexer no banco `bunge`; sai sem `DEFINER`, que exigiria SUPER |

## Uso

```bash
integrations/local-verify/setup.sh            # monta (mantém o banco se já existir)
integrations/local-verify/setup.sh --refresh-db   # recria o banco de verificação a partir do bunge
```

Servidor (no clone): `php -d memory_limit=2G -d opcache.enable_cli=0 -S 127.0.0.1:8099 -t pub phpserver/router.php`.
O `phpserver/router.php` é o que serve os arquivos estáticos; sem ele, `php -S` devolve 500.

Admin de teste, só no banco de verificação: `php bin/magento admin:user:create`. No clone, desligue
o 2FA (`php bin/magento module:disable Magento_AdminAdobeImsTwoFactorAuth`); a mudança fica no
`app/etc/config.php` do clone, que não é commitado.

## Provar o módulo de referência

Módulo copiado, nunca com link simbólico: o Magento recusa template de fora da raiz.

```bash
cd ~/workspace/.verify/bunge
rsync -a --delete ~/workspace/twinforge-adobe-template/skills/adobe-magento-toolbox/reference/Acme/ app/code/Acme/
php bin/magento module:enable Acme_Reference && php bin/magento setup:upgrade
php <skill>/scripts/magento-verify.php --path app/code/Acme/Reference
php vendor/bin/phpunit -c dev/tests/unit/phpunit.xml.dist app/code/Acme/Reference/Test/Unit
php <skill>/scripts/ui-render-check.php acme_reference_item_listing acme_reference_item_form
php bin/magento setup:di:compile
```

REST e GraphQL: token em `POST /rest/V1/integration/admin/token`, rotas em `etc/webapi.xml` e
`etc/schema.graphqls` do módulo. Resultado da última prova: `skills/adobe-magento-toolbox/reference/README.md`.

## Remover

`DROP DATABASE magento2;` e apagar `~/workspace/.verify/bunge`. Nada fica no checkout principal
nem no banco `bunge`.
