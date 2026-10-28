---
name: adobe-create-config
description: "Scaffold a new Magento 2 admin configuration in a Webjump/* module — etc/adminhtml/system.xml (scopes, source/backend models, encrypted fields), etc/config.xml defaults, the mandatory dedicated etc/acl.xml resource, and one Provider class per module that reads values by scope. Use for a new admin-configurable setting — 'add a config field for X', 'make this configurable in Stores > Configuration', 'add API credentials'."
---

# Create Config

Adapted 2026-09-11 from `air-bizapps/ftd-b2c-agent-skills`'s `create-config` skill. Two
deliberate changes: ACL nests under **the module's own root ACL resource** (Bunge has no
`FTD_Framework::config`-equivalent shared root); and "reuse before writing new PHP" points
at **core Magento's own generic classes** (`Magento\Config\Model\Config\Source\Yesno`,
`AbstractFieldArray` + `Serialized\ArraySerialized`, `Magento\Config\Model\Config\Backend\File`)
instead of `FTD_Framework`'s own catalog, which Bunge doesn't have.

## 1. Scope contract

**Expected input, all confirmed, never guessed:** target module, placement (new section
vs. a group/field on an existing one), the field list (id/label/type and scope: default,
website or store), any `<depends>`
between fields, and whether other PHP classes need to read the values back.

**Produces:** `etc/adminhtml/system.xml` written or extended; `etc/config.xml` defaults;
`etc/acl.xml` with a
dedicated resource nested under the module's own root; any `di.xml` wiring needed to reuse
a core Magento generic piece; and — when other classes consume the values — a single
`Model/Provider/{ConfigurationInterface,Configuration}` pair per module.

**Out of scope:** creating the module from scratch if it doesn't exist; the business logic
that consumes the configuration; unit tests for the Provider (→ `adobe-create-unit-tests`).

## 2. Golden rules

1. **Every `<section>` gets its own dedicated `<resource>` in `acl.xml` — no exception.**
   Without it the Roles UI can't grant/revoke access to it in isolation.
2. **The new resource nests under the module's own root ACL resource**, never directly
   under `Magento_Backend::admin` or `Magento_Config::config` — those are ancestor nodes
   to nest *under*, not resources to use directly; using them as-is grants access to any
   admin with basic backend access.
3. **Check a core Magento reusable piece before writing new PHP** for a source model,
   dynamic-rows field, or backend model — see section 4.
4. **A dynamic-list field always uses `AbstractFieldArray` + `Serialized\ArraySerialized`**
   — never Magento's native `type="arrays"` alone, never a hand-written (de)serialization
   backend model.
5. **One `ConfigurationInterface`/`Configuration` pair per module by default**, covering
   the whole `system.xml`. Split into per-feature Providers only if explicitly requested —
   deciding to split on your own diverges from the intended pattern.
6. **Every field the code reads has a default in `etc/config.xml`; every secret is
   encrypted.** Without a default `getValue()` returns `null` until someone saves the
   section; a plain-text secret is readable by anyone with database or dump access.
7. **Never guess field type, placement, or ACL when the request is ambiguous.** Guessing
   produces rework, and for ACL specifically, a wrong resource that has to be manually
   undone later.

## 3. ACL is mandatory (hard rules)

| Rule | Why | Instead |
|---|---|---|
| Never create a `<section>` without its own dedicated `<resource>` | Without it, the Roles UI can't grant/revoke access to this configuration in isolation from the rest of the backend | Every `<section>` references its own `Webjump_{Module}::config`-style resource, declared in `etc/acl.xml` |
| Never point a `<section>`'s `<resource>` directly at `Magento_Backend::admin` or `Magento_Config::config` | These are ancestor nodes to nest under, not resources to use directly — using them as the section's own resource grants access to any admin with basic backend access | Declare the module's own resource and nest it under the standard ancestor chain |
| Never declare a `<resource>` id in `system.xml` with no matching `<resource id="...">` in any `acl.xml` | Magento fails silently — the section may not render for anyone but a super-admin, and the checkbox never appears in Roles | Every id referenced in `system.xml` has a matching declaration in `acl.xml` — check with the grep below before finishing |

Verify before delivering:
```bash
grep -roP '(?<=<resource>)[^<]+' app/code/Webjump/**/etc/adminhtml/system.xml | sort -u > /tmp/cfg_resources.txt
grep -roP '(?<=<resource id=")[^"]+' app/code/Webjump/**/etc/acl.xml | sort -u > /tmp/acl_resources.txt
comm -23 /tmp/cfg_resources.txt /tmp/acl_resources.txt   # must be empty
```

## 4. Reuse before writing new PHP

Before writing a new source model, dynamic-rows block, or backend model, check whether a
core Magento class already covers it:

| Need | Check first |
|---|---|
| Yes/No toggle | `Magento\Config\Model\Config\Source\Yesno` |
| Dynamic key/value or multi-row list | `Magento\Config\Block\System\Config\Form\Field\FieldArray\AbstractFieldArray` (rendering) + `Magento\Config\Model\Config\Backend\Serialized\ArraySerialized` (backend model) — never native `type="arrays"` alone |
| Enable/disable-gated field group | `Magento\Config\Model\Config\Backend\Active`-style backend model pattern (write the module's own if core doesn't already fit, but check first) |
| File upload field | `Magento\Config\Model\Config\Backend\File` |
| Secret (API key, password, token) | `type="obscure"` + `Magento\Config\Model\Config\Backend\Encrypted`; also list the path as `sensitive` in `di.xml` (`Magento\Config\Model\Config\TypePool`), so `app:config:dump` keeps it out of the versioned `config.php` |

Only write a new PHP class (source model, dynamic-rows block, backend model) when the
case genuinely isn't covered by one of these — and say so explicitly in the report rather
than silently defaulting to a new class. A new source model implements
`Magento\Framework\Data\OptionSourceInterface`; a new backend model extends
`Magento\Framework\App\Config\Value` and validates in `beforeSave()`, where a
`LocalizedException` stops the save and shows its message (model:
`Magento\Config\Model\Config\Backend\Email\Address`). Full proven example:
`magento-example config` (skill `adobe-magento-toolbox`).

## 5. Provider pattern

One `Model/Provider/{Module}ConfigurationInterface` + `Configuration` pair per module,
covering the entire `system.xml` — never `ScopeConfigInterface` called directly from
business logic, and never split into multiple Providers unless explicitly requested.

- **Scope:** `showInDefault`/`showInWebsite`/`showInStore` decide where a value may differ.
  Read with the same scope and an explicit id: `ScopeInterface::SCOPE_STORE` + `$storeId`
  (falls back to website, then default), `SCOPE_WEBSITE` + `$websiteId`, no scope for
  default-only fields. Cron, CLI and queue consumers have no current store: without an id
  Magento reads the default store view, so the caller passes it.
- **Encrypted fields:** decrypt in exactly one place — `EncryptorInterface::decrypt()` in
  the Provider, or a `backend_model="Magento\Config\Model\Config\Backend\Encrypted"`
  attribute on the field in `etc/config.xml` (then `getValue()` returns it decrypted).
  Decrypting twice returns empty or garbage.

## 6. Defaults and per-environment values

- `etc/config.xml` (`<default><section><group><field>`) holds the default; `canRestore="1"`
  lets the admin return to it. Changing a default later does not touch scopes already saved
  in `core_config_data`: to change existing installs, use a data patch with
  `Magento\Framework\App\Config\Storage\WriterInterface::save()`.
- Values that differ per environment (endpoint URL, credentials) never go in `config.xml`
  or code: `bin/magento config:set [--scope=websites|stores --scope-code=<code>] <path>
  <value>` runs the field's backend model like the admin does (an `Encrypted` field is
  saved encrypted). `--lock-env` writes to `app/etc/env.php` (per server, not versioned),
  `--lock-config` to `app/etc/config.php` (versioned, every environment); both override
  the database and lock the field in the admin, so leave out what the business edits there.
- Verify: `bin/magento config:show <path>` (an encrypted field prints `******`; its
  `core_config_data` value starts with `<key version>:3:`), then `magento-verify`.

## 7. Autonomy bands

| Band | Action | Example |
|---|---|---|
| Green — proceed directly | Adding a field to an already-existing `system.xml` following that module's existing patterns; reusing a core Magento class already cataloged in section 4 | Adding a new `<field>` to an existing section, reusing `Magento\Config\Model\Config\Source\Yesno` |
| Yellow — proceed, then report the decision | Writing a new PHP class (dedicated source model, dynamic-row block, backend model) because nothing in section 4 covers the case | Creating `Model/Source/{Name}` because the option list depends on a runtime query |
| Red — stop and ask before acting | Ambiguous placement (new section vs. group on an existing one), unconfirmed field type, unclear parent module for ACL, or a request to split the Provider into multiple files | "Add a config for X" with no indication of toggle vs. list vs. text |

## 8. Definition of done

- [ ] Module confirmed to exist (bootstrapped first if genuinely new)
- [ ] Every `<section>` has its own dedicated ACL resource, nested under the module's own
      root — no orphaned or reused-from-a-different-module resource
- [ ] Every `system.xml` `<resource>` id has a matching `<resource id="...">` in `acl.xml`
      (section 3's grep check passes)
- [ ] A core Magento reusable piece was checked before any new PHP class was written —
      any new class is named explicitly in the report as a yellow-band decision
- [ ] Any dynamic-list field uses `AbstractFieldArray` + `Serialized\ArraySerialized`
- [ ] Each field has a deliberate scope and a `config.xml` default (or is a secret);
      secrets are `obscure` + `Encrypted`, marked `sensitive`, and decrypted once
- [ ] The Provider reads with the field's scope and an explicit store/website id
- [ ] At most one Provider pair exists for the module, unless a split was explicitly
      requested
- [ ] No field type/placement/ACL decision was made by guessing on an ambiguous request
- [ ] `setup:upgrade` + `cache:clean` (+ `setup:di:compile` if a new class/virtualType was
      added) succeed; the section/field manually confirmed visible in Stores >
      Configuration

## Antipatterns

- Declaring a `<resource>` in `system.xml` with no matching `acl.xml` entry — Magento
  fails silently; the section may render for nobody but a super-admin.
- Nesting a new resource directly under `Magento_Backend::admin`/`Magento_Config::config`
  instead of the module's own root resource.
- Writing a new PHP class for a source model/dynamic-rows/backend model without first
  checking whether a core Magento class already covers the case.
- Using native `type="arrays"` (or a hand-written (de)serialization backend model) for a
  dynamic-list field instead of `AbstractFieldArray` + `Serialized\ArraySerialized`.
- Splitting the Provider into multiple files per feature/group without an explicit
  request.
- Guessing field type, placement, or ACL on an ambiguous request instead of stopping to
  ask.
