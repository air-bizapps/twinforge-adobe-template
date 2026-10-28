---
name: adobe-magento-configuration-specialist
description: "Find, read, change and troubleshoot Magento 2.4.8 system configuration: where a value comes from (env var, env.php, config.php, core_config_data, config.xml), scope fallback, reading it in code, safe ways to write it, B2B feature flags. Use when a setting does not take effect, differs per store or environment, or code must read one. To add a new admin field: adobe-create-config."
---

# Magento configuration

How a configuration value is resolved and how to prove which one the code sees. Declaring a new
field (`system.xml`, defaults, ACL, Provider, encryption) is `adobe-create-config`; what goes into
`config.php` versus `env.php` at deploy is `adobe-magento-deployment-engineer`.

## Rules

**Where a value comes from** (highest wins)
1. Environment variable `CONFIG__<SCOPE>__<PATH>`, path in upper case with `/` as `__`
   (`CONFIG__DEFAULT__CATALOG__SEARCH__OPENSEARCH_SERVER_HOSTNAME`,
   `CONFIG__WEBSITES__<CODE>__...`, `CONFIG__STORES__<CODE>__...`).
2. `app/etc/env.php`, key `system` (per server).
3. `app/etc/config.php`, key `system` (versioned, every environment).
4. Table `core_config_data` (what the Admin saves).
5. `etc/config.xml` `<default>` of the modules, merged in `<sequence>` order.
Levels 1 to 3 lock the field in the Admin (shown disabled), so a value set in a file can't be
fixed by the business there.

**Scope**
- `core_config_data` keys a value by `scope` (`default`, `websites`, `stores`), `scope_id` and `path`.
  A store-view read falls back to its website, then to default; "Use Website"/"Use Default" in the
  Admin deletes the row at that scope.
- `showInDefault`/`showInWebsite`/`showInStore` only decide where the Admin offers the field; a
  value can still exist at a scope the field no longer shows (left over from an old `system.xml`)
  and keep winning. Look for it in the table.
- B2B features are flags at website scope: `btob/website_configuration/company_active`,
  `sharedcatalog_active`, `negotiablequote_active`, `quickorder_active`, `requisition_list_active`,
  `purchaseorder_enabled`. Code that depends on one reads it with the website of the request.

**Reading in code**
- `ScopeConfigInterface::getValue($path, ScopeInterface::SCOPE_STORE, $storeId)`; `isSetFlag()` for
  yes/no. Behind one Provider per module, with the scope id passed in (`adobe-create-config` §5).
- No current store in cron, CLI, consumers, or REST called with `/rest/all` (admin store): without an
  id the read returns the default store view or default value, not the customer's store.
- Values are served from the `config` cache. Changes through the Admin or `config:set` clean it;
  anything else needs `bin/magento cache:clean config`.

**Writing**
- Admin or `bin/magento config:set [--scope=websites|stores --scope-code=<code>] <path> <value>`:
  both run the field's backend model (validation, encryption). `config:set` only accepts paths
  declared in a `system.xml` ("path doesn't exist" otherwise). Lock options: `adobe-create-config` §6.
- Sensitive paths per environment: `bin/magento config:sensitive:set <path> <value>` (writes
  `env.php`).
- Defaults for every install: `config.xml`; change of existing installs: data patch with
  `WriterInterface::save()` (`adobe-create-config` §6).
- Direct SQL on `core_config_data` skips the backend model: an `Encrypted` field saved that way stays
  in clear text and is unreadable by `decrypt()`. Use it only for diagnosis.

## Steps (value does not take effect)

1. Get the effective value: `config:show <path>` per scope that matters.
2. Find its source: environment variable, `env.php`, `config.php`, table rows for all scopes.
3. Check the reading code: same path, scope type and an explicit id.
4. Fix at the right level (usually the Admin or `config:set` at that scope), clean the `config`
   cache, read again. Report the path, scope and source in the delivery.

## Verify

```bash
bin/magento config:show <path>                                     # default scope, effective
bin/magento config:show --scope=websites --scope-code=<code> <path>
bin/magento config:show --scope=stores --scope-code=<code> <path>
mysql -e "SELECT scope, scope_id, value, updated_at FROM core_config_data WHERE path='<path>'"
grep -n "<last path segment>" app/etc/env.php app/etc/config.php
env | grep '^CONFIG__'
bin/magento app:config:status                                     # files differ from the database
bin/magento cache:clean config
```

An encrypted field prints `******` in `config:show`; its stored value starts with
`<key version>:3:`.

## Pitfalls

- Admin save "does nothing" → the path is set in `env.php`, `config.php` or an env variable.
- Right in the Admin, wrong on the site → a row at store scope shadows the website value.
- Right on the site, wrong in cron or e-mail → the code reads without a store id.
- `app:config:dump` without arguments → every database value goes to `config.php` and every field
  locks in the Admin; dump only `scopes themes` unless a value must be identical everywhere.
- New `config.xml` default not applied → a saved row in `core_config_data` wins over defaults.
- Secret pasted into `config.php` → move it to `env.php` with `config:sensitive:set` and mark it
  `sensitive` (`adobe-magento-security-analyst`).

## Docs

- https://developer.adobe.com/commerce/php/development/configuration/
- https://developer.adobe.com/commerce/php/development/configuration/sensitive-environment-settings
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/cli/set-configuration-values
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/deployment/examples/example-environment-variables
- https://experienceleague.adobe.com/en/docs/commerce-operations/configuration-guide/paths/override-config-settings
