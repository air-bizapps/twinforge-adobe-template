---
name: adobe-magento-luma-specialist
description: "What Magento's Luma and Blank themes provide and how to reuse it on 2.4.8: Blank's structure, Luma's variable layer and icon font, built-in jQuery widgets and modals, the .lib-* LESS mixins, B2B module styles, keeping overrides current after an upgrade. Use when changing something Luma already renders or styles. Conventions: adobe-magento-frontend-standards."
---

# Reusing Luma and Blank

Bunge's theme ends in `Magento/luma`, whose parent is `Magento/blank` (chain: Bunge, Carbono, Translate, Luma, Blank). Most storefront pieces already exist there: reuse them before writing new ones. Fallback and override rules are in `adobe-magento-frontend-standards`; LESS mechanics in `adobe-magento-css-specialist`.

## Rules

- **Know which layer owns a file.** Blank (`vendor/magento/theme-frontend-blank`) has the structure: `web/css/styles-m.less`, `styles-l.less`, `source/_sources.less`, module styles and the `1column`/`2columns-*`/`3columns` page layouts. Luma (`vendor/magento/theme-frontend-luma`) is mostly a variable layer: `web/css/source/_theme.less` (about 300 lines of overrides), Luma Icons font, and `_module.less` copies for some modules. Read both before deciding what to override.
- **B2B pages are not styled by Luma or Blank.** Company, negotiable quote, purchase order and quick order styles come from the module (`vendor/magento/module-company/view/frontend/web/css/source/_module.less` and siblings), and Bunge overrides several of them. Look there first.
- **Use the built-in widgets instead of a new library.** Aliases from `Magento_Theme`: `collapsible`, `accordion`, `tabs`, `dropdownDialog`, `menu`, `sticky`, `loader`, `tooltip`, `validation`. Modals: `Magento_Ui/js/modal/modal`, `Magento_Ui/js/modal/confirm`, `Magento_Ui/js/modal/alert`. Example: `data-mage-init='{"collapsible": {"openedState": "active", "active": false}}'`. They already handle keyboard, ARIA and the Luma styles.
- **Change a widget through a mixin**, never a copy of `mage/*.js` (Bunge does `mage/menu`); how: `adobe-magento-requirejs-specialist`.
- **Style with the `.lib-*` mixins** from `lib/web/css/source/lib/` (in `vendor/magento/magento2-base/lib/web/...`): `.lib-button()`, `.lib-link()`, `.lib-font-face()`, `.lib-icon-font(@icon-cart, ...)` with icon codes from `lib/variables/_icons.less`. They read the theme variables, so a variable change reaches every place. `.lib-css(property, value)` prints nothing when the value is `false` or empty: that is how a parent's property is switched off.
- **Icons**: Luma sets `@icons__font-name: 'luma-icons'` and ships `web/fonts/Luma-Icons.*`. Reuse its glyphs through `@icon-*`; a new icon set is a new font, declared once.
- **Translate and Carbono sit between Bunge and Luma.** A Luma file may already be replaced in `vendor/webjump/theme-frontend-translate` or `-carbono`; that copy, not Luma's, is what renders.
- **Overrides of Luma or Blank files go stale on upgrade**: diff and port them (`adobe-magento-upgrade-specialist`). Each copy you add is one more file to diff, which is why the order in Steps puts copying last.
- **Checkout, minicart and customer sections are Knockout UI components**, not plain templates: change them with a mixin or a `jsLayout` delta (`adobe-magento-knockout-specialist`).

## Steps

1. `php "$T/magento-trace.php" <url>` (`$T`: `scripts/` of `adobe-magento-toolbox`) for template, JS and module.
2. Find what Luma or Blank already gives: widget, mixin, variable, page layout.
3. Prefer, in order: variable in Bunge's `_theme.less`, `_extend.less` rule, layout argument, widget option, mixin; copy a file only when none works.
4. When you copy, take it from the first theme in the chain that has it and record the source path in the task update.

## Verify

- `php "$T/magento-verify.php"`.
- Widget initialized: browser console `jQuery('<selector>').data('mageCollapsible')` (widget name in camel case with `mage` prefix) is defined.
- Overridden file still current: `diff vendor/magento/theme-frontend-luma/<path> app/design/frontend/Webjump/theme-frontend-bunge/<path>` shows only your change.
- Compiled CSS has the rule: `grep -c '<selector>' pub/static/frontend/Webjump/theme-frontend-bunge/pt_BR/css/styles-m.css`.

## Pitfalls

- New accordion written in jQuery while `collapsible` exists -> use the widget and its options.
- `jquery/ui` required for one widget -> it maps to `jquery/compat` and loads all of jQuery UI; require `jquery-ui-modules/widget` (or the specific module).
- Variable changed in Luma's file under `vendor/` -> lost on `composer install`; set it in Bunge's `_theme.less`.
- Style change missing on company pages -> B2B module LESS, not Luma.

## Docs

- https://developer.adobe.com/commerce/frontend-core/guide/themes/
- https://developer.adobe.com/commerce/frontend-core/javascript/widgets/
- https://developer.adobe.com/commerce/frontend-core/guide/css/ui-library/
- https://developer.adobe.com/commerce/frontend-core/guide/themes/inheritance/
