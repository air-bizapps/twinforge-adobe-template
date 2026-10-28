---
name: adobe-magento-requirejs-specialist
description: "RequireJS 2.3.7 in Magento 2.4.8 storefront: AMD module shapes (init function, jQuery widget, UI component), x-magento-init and data-mage-init, requirejs-config.js keys and merge order, mixins for each target type with mage/utils/wrapper, jQuery UI modules, text! and domReady!, minified files, debugging Script error. Use when creating, loading, configuring or mixing in a storefront JS module. Knockout specifics: adobe-magento-knockout-specialist."
---

# RequireJS modules and mixins

Every storefront script in a Luma-based theme is an AMD module loaded by RequireJS 2.3.7 (jQuery 3.7.1, jQuery UI split into modules). The short rules (no inline scripts, `map` only for aliases, `deps` avoided, mixin before full copy) are in `adobe-magento-frontend-standards`; Bunge's existing mixins in `bunge-frontend-code-quality`. This skill is the reference.

## Rules

- **Pick the module shape by how it starts.** Started from markup and needs only an element: return `function (config, element) { }`. Reusable behavior with options and state: a jQuery widget, `define(['jquery', 'jquery-ui-modules/widget'], function ($) { $.widget('vendor.foo', { options: {}, _create: function () {} }); return $.vendor.foo; })`. Reactive UI: a `uiComponent` (`adobe-magento-knockout-specialist`). Full examples: `php "$T/magento-example.php" js` (`$T`: `scripts/` of `adobe-magento-toolbox`).
- **Start it from markup.** `<div data-mage-init='{"Vendor_Module/js/foo": {"limit": 3}}'>` for one element; `<script type="text/x-magento-init">{"<css selector>": {"Vendor_Module/js/foo": {...}}}</script>` for a selector, or `"*"` for no element. Both parse strict JSON (double quotes, no trailing comma); build it in PHP with a JSON serializer. Magento calls the function with `(config, element)` after page load.
- **Name modules by path.** `Vendor_Module/js/foo` is `view/<area>/web/js/foo.js`; never `.js` or a URL in a dependency, which bypasses `map`, mixins and the minified-file resolver.
- **Require only the jQuery UI part you use**: `jquery-ui-modules/widget`, `jquery-ui-modules/datepicker`, and so on. `jquery/ui` maps to `jquery/compat`, which loads all of jQuery UI.
- **Plugins**: `text!Vendor_Module/template/x.html` loads a file as a string; `domReady!` waits for the DOM (an `x-magento-init` module already runs after it).
- **`requirejs-config.js` locations and merge order**: `lib`, then each module's `view/base` and `view/frontend` file in module load order, then each theme from parent to child (theme root and `<Vendor_Module>/requirejs-config.js`). Later files win for the same key. The merged result is `pub/static/frontend/Webjump/theme-frontend-bunge/pt_BR/requirejs-config.js`.
- **`shim` is only for non-AMD scripts.** It has no effect on a file that calls `define()`; order AMD modules by listing the dependency in `define([...])`. (Bunge's `Magento_Theme/requirejs-config.js` shims an AMD module, which does nothing.)
- **Mixins, by target type.** Key the mixin by the module id Magento uses (`'Magento_Checkout/js/view/shipping': {'Vendor_Module/js/view/shipping-mixin': true}`); the mixin module returns `function (target) { ... return modified; }`.
  - UI component: `return target.extend({ method: function () { var result = this._super(); ...; return result; } });`
  - Function module (actions such as `Magento_Checkout/js/action/place-order`): `return wrapper.wrap(target, function (original, paymentData, messageContainer) { return original(paymentData, messageContainer); });` with `mage/utils/wrapper` (ES5 arguments, as the rest of Magento's JS).
  - Object module: `wrapper.extend(target, { method: function (original) { ... } })`.
  - jQuery widget: `$.widget('mage.foo', target, { _create: function () { this._super(); } }); return $.mage.foo;`. Return the same shape the original returns: `mage/menu` returns `{menu, navigation}`, so its mixin extends `target.menu` and returns `{menu: $.mage.menu, navigation: target.navigation}` (Bunge's `menu-mixin.js` returns only the widget; it works because nothing reads `navigation`, but keep the shape in new mixins).
  Example: `magento-example mixin`.
- **Bunge minifies JS** (`dev/js/minify_files` = 1) without merging or bundling. In production RequireJS asks for `foo.min.js`; a library already shipped minified goes into `dev/js/minify_exclude`.
- **Checkout scripts are checked by SRI**: changing a JS file means a new static deploy, not a copied file into `pub/static`.

## Steps

1. Choose the shape; write the module under `view/frontend/web/js/`.
2. Start it with `data-mage-init` or `x-magento-init`, config from a ViewModel.
3. Alias in `map` only if markup needs a short name; mixin in `config.mixins` for core changes.
4. Clean the generated `requirejs-config.js` (`adobe-magento-frontend-standards`, static deployment) and reload.

## Verify

- `php "$T/magento-verify.php"`: every name in `requirejs-config.js`, `x-magento-init` and `define()` resolves to a file.
- `grep -c 'shipping-mixin' pub/static/frontend/Webjump/theme-frontend-bunge/pt_BR/requirejs-config.js` is 1.
- Browser console: `require.s.contexts._.config.config.mixins['<target>']` lists the mixin; `require.defined('<module>')` is `true` after the page loads it.
- Network tab: no 404 for the module (`.min.js` in production).

## Pitfalls

- `Script error for "Vendor_Module/js/foo"` -> file missing, wrong path, or not deployed; `magento-trace` marks the broken link.
- `Mismatched anonymous define()` -> an AMD file loaded with a plain `<script>` tag; load it through RequireJS.
- Mixin never runs -> wrong target id, mixin key in `map`, or the file was not regenerated.
- `data-mage-init` silently ignored -> invalid JSON (single quotes inside, trailing comma).

## Docs

- https://developer.adobe.com/commerce/frontend-core/javascript/requirejs/
- https://developer.adobe.com/commerce/frontend-core/javascript/mixins/
- https://developer.adobe.com/commerce/frontend-core/javascript/init/
- https://requirejs.org/docs/api.html
