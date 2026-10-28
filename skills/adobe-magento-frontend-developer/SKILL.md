---
name: adobe-magento-frontend-developer
description: "Storefront feature workflow on a Luma-based Magento 2.4.8 theme: pick the right layer (layout XML, ViewModel, template, JS, LESS), private content, forms and AJAX, translations, head assets, checkout CSP/SRI, verification. Use when building or changing a storefront feature end to end. Conventions live in adobe-magento-frontend-standards; Bunge's own lessons in bunge-frontend-code-quality."
---

# Storefront feature development (Luma stack)

How to take a storefront change from requirement to a verified page on a Luma-based theme. Bunge's theme chain is Bunge, Carbono, Translate, Luma, Blank. The standing conventions (fallback, layout handles, ViewModels, escaping, mixins, static deploy) are in `adobe-magento-frontend-standards`; this skill is the workflow and the topics that skill does not cover. Not for Hyvä (`adobe-magento-hyva-specialist`).

## Rules

- **Trace before you write.** `php "$T/magento-trace.php" <url>` (`$T` is the `scripts/` folder of `adobe-magento-toolbox`) lists the route, handles, layout files, templates and JS of the page. You then change the file that renders today, not a guess.
- **Pick the smallest layer that does the job**, in this order: layout XML argument or `move`, ViewModel method, template override, JS mixin, new JS component. Each step up costs more on upgrade. Depth per layer: `adobe-magento-theme-developer` (files and fallback), `adobe-magento-css-specialist`, `adobe-magento-requirejs-specialist`, `adobe-magento-knockout-specialist`.
- **Per-customer data never renders in the cached HTML.** Use a customer-data section read in JS; rules and model class in `adobe-magento-performance-analyst`. A block that prints the customer name works in developer mode with cache off and breaks in production.
- **Storefront POST needs a form key.** A `<form method="post">` gets it from `<?= $block->getBlockHtml('formkey') ?>` (`mage/common` also adds it when the page has one). An AJAX POST sends `form_key: $.mage.cookies.get('form_key')` (deps `jquery`, `mage/cookies`). Controller side: `adobe-magento-security-analyst`.
- **Client validation uses Magento's widget**: `data-mage-init='{"validation": {}}'` on the form, `data-validate="{required:true, 'validate-email':true}"` on the field, so messages match the checkout. The server still validates.
- **HTML inserted by AJAX is not initialized.** After inserting, call `$(container).trigger('contentUpdated')` so `x-magento-init`/`data-mage-init` run; Knockout scopes need `$(node).applyBindings()` (`adobe-magento-knockout-specialist`).
- **Translate every visible string.** PHP and templates: `__('Text')`; JS: `$t('Text')` from `mage/translate`; Knockout templates: `i18n`. Add the pair to `i18n/pt_BR.csv` and `i18n/en_US.csv` of the module or of the Bunge theme. Bunge uses `translate_strategy` `dictionary`: JS strings come from `js-translation.json`, written at static deploy.
- **Head assets go through layout**: `default_head_blocks.xml` with `<css src="css/x.css"/>`, `<script src="Vendor_Module::js/x.js"/>`, `<font src="fonts/x.woff2"/>` (rendered as a preload), `<remove src="..."/>`. Each extra file is one more request; prefer `_extend.less` and RequireJS.
- **Checkout (`checkout_index_index`) runs CSP in restrict mode and checks Subresource Integrity.** Inline scripts need `$secureRenderer->renderTag()`, external hosts need `etc/csp_whitelist.xml` (`adobe-magento-security-analyst`), and a JS file changed after static deploy no longer matches `pub/static/frontend/sri-hashes.json`: redeploy.
- **Images**: give `width` and `height` (or an aspect-ratio box) so the layout does not shift, and `loading="lazy"` below the fold (the theme has none today, `bunge-frontend-code-quality`). Product image sizes come from `etc/view.xml` (`adobe-magento-theme-developer`).

## Steps

1. Trace the page; write down the handle, the block name and the file that renders.
2. Data: a ViewModel method (`php "$T/magento-example.php" viewmodel`; whole page: `frontend-page`).
3. Markup: layout delta and, only if needed, a template override taken from the first theme in the chain that has it.
4. Behavior: mixin or new module (`magento-example js|mixin`); per-customer data through customer-data.
5. Style: `_extend.less` or the module's `_module.less` (`magento-example less`).
6. Strings in both CSV files; clean the generated files (`adobe-magento-frontend-standards`, static deployment) and test with full page cache on.

## Verify

- `php "$T/magento-verify.php"` before done: layout XML against the XSD, classes and RequireJS names exist.
- `curl -s <url> | grep -c '<block marker or text>'` with the page cache enabled, logged out and logged in.
- Browser console: no RequireJS `Script error for`, no CSP `Refused to`, no Knockout binding errors.
- `grep 'Broken reference' var/log/system.log` after a layout change.
- Production-like check: `bin/magento setup:static-content:deploy pt_BR en_US --theme Webjump/theme-frontend-bunge -f`.

## Pitfalls

- Works with cache off, wrong for the next visitor with cache on -> per-customer data in HTML; move it to customer-data.
- AJAX POST returns a redirect or "Invalid Form Key" -> send `form_key`.
- New JS string stays in English -> CSV entry missing, or `js-translation.json` not regenerated.
- Script fine on the product page, blocked on checkout -> CSP restrict mode or stale SRI hash.

## Docs

- https://developer.adobe.com/commerce/frontend-core/guide/
- https://developer.adobe.com/commerce/php/development/cache/page/private-content/
- https://developer.adobe.com/commerce/frontend-core/guide/translations/
- https://developer.adobe.com/commerce/frontend-core/javascript/init/
- https://developer.adobe.com/commerce/php/development/security/content-security-policies/
