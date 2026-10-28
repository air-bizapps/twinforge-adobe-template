---
name: adobe-magento-knockout-specialist
description: "Storefront Knockout.js 3.5.1 components in Magento 2.4.8: uiElement and uiComponent, defaults (template, tracks, imports, exports, links, listens, modules), observables versus tracks, initialization through Magento_Ui/js/core/app and jsLayout, .html templates and Magento's tag syntax, custom bindings, subscriptions, bindings on injected HTML. Use when writing or changing a Knockout component (checkout, minicart, custom widgets). Admin grids and forms: adobe-magento-ui-component-developer."
---

# Knockout components on the storefront

Magento's dynamic storefront UI (checkout, minicart, customer sections) is Knockout 3.5.1 wrapped in Magento's UI component classes. Short rules (when to use Knockout instead of jQuery, `uiComponent` versus `uiElement`, `tracks`) are in `adobe-magento-frontend-standards`; this is the reference. Module loading and mixins: `adobe-magento-requirejs-specialist`. Admin UI Components (XML grids and forms): `adobe-magento-ui-component-developer`.

## Rules

- **Extend, never build from `ko` alone.** `define(['uiComponent', 'ko'], function (Component, ko) { 'use strict'; return Component.extend({ defaults: { template: 'Vendor_Module/foo' }, initialize: function () { this._super(); return this; } }); });`. `uiComponent` and `uiCollection` are `Magento_Ui/js/lib/core/collection` (has children, renders `elems`); `uiElement` is the leaf. Every overridden method calls `this._super()` and returns what the parent returns.
- **Template path**: `template: 'Vendor_Module/foo'` loads `Vendor_Module/template/foo.html`, from `view/frontend/web/template/foo.html` or the theme override at the same path.
- **Two ways to make a property reactive.** `tracks: { count: true }` turns it into an ES5 accessor: read and write it as `this.count`, subscribe with `ko.getObservable(this, 'count').subscribe(fn)`. `this.observe('count total')` (call it in `initObservable` after `this._super()`) makes functions: read `this.count()`, write `this.count(5)`. Pick one per property; assigning `this.count = 5` to an observed function breaks it silently.
- **Talk between components through declared links**, not `uiRegistry` lookups in methods. `imports: { total: 'checkout.sidebar:total' }` reads, `exports` writes, `links` is two-way, `listens: { total: 'onTotal' }` calls a method on change, `modules: { parent: '${ $.parentName }' }` gives lazy `this.parent()`. `${ $.name }` and `${ $.provider }` are template literals. If you need the registry, use the async form `registry.get('name', callback)`; the component may not exist yet.
- **Initialize from markup or layout, not with `ko.applyBindings`.** Markup: `<div id="x" data-bind="scope: 'vendorFoo'"><!-- ko template: getTemplate() --><!-- /ko --></div>` plus `<script type="text/x-magento-init">{"#x": {"Magento_Ui/js/core/app": {"components": {"vendorFoo": {"component": "Vendor_Module/js/view/foo"}}}}}</script>`. From layout: `<argument name="jsLayout" xsi:type="array">` on the block and `<?= /* @noEscape */ $block->getJsLayout() ?>` as the `core/app` value. Checkout changes go through `jsLayout` or a layout processor: `php "$T/magento-example.php" checkout-field` (`$T`: `scripts/` of `adobe-magento-toolbox`).
- **Magento's template syntax** is compiled to Knockout: `<each args="data: items, as: 'item'">`, `<if args="visible">`, `<translate args="'Add to Cart'"/>`, `<text args="label"/>`, `<scope args="'name'">`, attribute bindings such as `<span text="label"></span>` and `<button click="save">`, alongside `data-bind` and `<!-- ko -->`. Visible strings use `i18n` or `$t`.
- **HTML from data goes through `text`**, which escapes. `html` and `bindHtml` insert markup as is: only for content the server already escaped.
- **Derived values are `ko.pureComputed`**, which sleeps when nobody reads it. Keep the return of each `subscribe()` and call `.dispose()` in `destroy()` when the component can be removed, or the callback keeps running.
- **Custom bindings** go in a RequireJS module that sets `ko.bindingHandlers.vendorFoo = { init, update }` and is a dependency of the component that uses it. For the attribute form (`<div vendorFoo="...">`), also call `renderer.addAttribute('vendorFoo')` from `Magento_Ui/js/lib/knockout/template/renderer`.
- **Customer data is observable.** `customerData.get('cart')` (`Magento_Customer/js/customer-data`) returns an observable; bind it or subscribe to it. Its server side: `adobe-magento-performance-analyst`.

## Steps

1. `php "$T/magento-trace.php" <url>` to find the component name, its JS and template.
2. Change of a core component: mixin with `target.extend` (`magento-example mixin`). New one: `magento-example js`.
3. Declare state in `defaults` (`tracks`, `imports`/`exports`), behavior in methods.
4. Template in `web/template/`, strings translated, data through `text`.
5. Clean generated static files (`adobe-magento-frontend-standards`) and reload.

## Verify

- `php "$T/magento-verify.php"`: component and template paths resolve.
- Browser console: `require('uiRegistry').get('<component name>')` returns the instance; `require('ko').dataFor(document.querySelector('<selector>'))` shows the bound view model.
- No `You cannot apply bindings multiple times` and no `Unable to process binding` in the console.

## Pitfalls

- Value changes but the view does not -> property neither in `tracks` nor observed.
- `this.foo is not a function` -> tracked property called as a function, or the reverse.
- Component missing from the registry -> name mismatch between `scope` and `components`, or JS not deployed.
- HTML injected by AJAX stays inert -> `$(node).applyBindings()` (from `Magento_Ui/js/lib/view/utils/bindings`), not a second `ko.applyBindings`.

## Docs

- https://developer.adobe.com/commerce/frontend-core/ui-components/concepts/
- https://developer.adobe.com/commerce/frontend-core/ui-components/concepts/linking-properties/
- https://developer.adobe.com/commerce/frontend-core/javascript/bindings/
- https://knockoutjs.com/documentation/introduction.html
