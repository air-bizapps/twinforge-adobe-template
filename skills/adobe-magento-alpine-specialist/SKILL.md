---
name: adobe-magento-alpine-specialist
description: "Alpine.js components on a Hyvä storefront (Magento 2.4.8): CSP-safe registration, binding rules, passing server data, customer section data, events between components, POST with form key. Use only when the theme inherits from Hyva/default or Hyva/default-csp. Hyvä setup, compat modules, Tailwind: adobe-magento-hyva-specialist. On a Luma theme, Alpine is not loaded: use adobe-magento-frontend-standards."
---

# Alpine.js on Hyvä

Client-side behavior for Hyvä templates: toggles, tabs, forms, reading customer data. Only for themes
inheriting from `Hyva/default` or `Hyva/default-csp`; Luma loads no Alpine. Theme setup, compat modules
and Tailwind: `adobe-magento-hyva-specialist`. Interaction that needs Magento state on the server
(validate, recalculate, save): `adobe-magento-magewire-specialist`.

## Rules

- **One pattern that works with and without CSP**: a named function registered with
  `window.addEventListener('alpine:init', () => Alpine.data('initVendorFoo', initVendorFoo))` and markup
  `x-data="initVendorFoo"`, without parentheses. Inline objects (`x-data="{ open: false }"`) and calls
  (`x-data="initVendorFoo()"`) break on the CSP build. Prefix the name with the module so two templates
  don't overwrite each other.
- **Attributes reference only property and method names.** Negation, ternaries and assignments go in
  methods: `x-show="isClosed"` with `isClosed() { return !this.open }`, `@click="close"` instead of
  `@click="open = false"`. The Alpine CSP build evaluates no inline expressions.
- **No arguments in templates.** Put the value in a `data-*` attribute and read it in the method:
  `@click="setTab" data-tab="shipping"`, `setTab(event) { this.tab = event.target.dataset.tab }`.
  `x-for` loops over a precomputed property, not `getItems('x')`.
- **No `x-model` on CSP themes**: use `:value="qty"` plus `@input="setQty"`.
- **Handlers use `@click`/`@submit`, never `onclick=`**, and every inline `<script>` is followed by
  `<?php $hyvaCsp->registerInlineScript() ?>` (rule in `adobe-magento-hyva-specialist`).
- **Server data enters through the component function**, encoded in PHP inside the registered script:
  `items: <?= /* @noEscape */ json_encode($viewModel->getItems(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>`,
  or through `data-*` attributes escaped with `$escaper->escapeHtmlAttr()`. Never echo raw strings into
  JS. Data that differs per customer must not be rendered this way on a cacheable page.
- **Per-customer data comes from Hyvä section data**: listen with
  `@private-content-loaded.window="receiveCustomerData"` and read `event.detail.data` (all sections in
  one object; no per-section subscription). After a change, dispatch
  `window.dispatchEvent(new Event('reload-customer-section-data'))`: Hyvä has no automatic invalidation
  like Luma's `sections.xml`. Data is refetched only if older than an hour or the private content
  version cookie changed.
- **Components talk through events**, not globals: `this.$dispatch('vendor-foo-updated', { id })` and
  `@vendor-foo-updated.window="onUpdated"` on the listener. Prefix event names with the module.
- **POST to a Magento controller with the form key**: `hyva.postForm({ action, data })` for a form
  submit, or `fetch` with `form_key: hyva.getFormKey()` in the body. Without it Magento rejects the
  request as CSRF.
- **Read public data with GraphQL GET** so the answer is cacheable (`adobe-magento-cache-analyst`);
  never send customer or cart queries as cacheable GET.
- **Keep markup usable before Alpine starts**: content rendered by PHP, hidden parts with `x-cloak`
  (styled `display: none`), so there is no layout shift and nothing depends on JS to appear for search
  engines.

## Steps

1. Template: markup with `x-data="initVendorFoo"`, properties and method names only.
2. Script under the markup: the function, `Alpine.data` registration, `registerInlineScript()`.
3. Server data via `json_encode` in the function or `data-*`; private data via `private-content-loaded`.
4. Writes: `hyva.postForm`/`fetch` with form key, then `reload-customer-section-data` if sections changed.

## Verify

- `curl -s "$BASE_URL/page" | grep -c 'x-data="initVendorFoo"'` is at least 1.
- `grep -nE 'x-data="\{|x-model|onclick=' <template>` returns nothing on a CSP theme.
- Browser console: no "Refused to execute inline script", no Alpine expression errors; the component
  reacts with the full page cache enabled and a logged-in customer.

## Pitfalls

- `x-data="{ open: false }"` with `@click="open = !open"` on a CSP theme -> registered component with a `toggle()` method.
- Two templates registering `initFoo` -> the later one wins silently; prefix with the module.
- Customer name rendered in PHP shows another customer's name -> page was cached; read it from section data.
- Mini cart not updating after an AJAX add -> dispatch `reload-customer-section-data`.
- POST answered with a redirect to the home page -> missing form key.

## Docs

- https://docs.hyva.io/hyva-themes/writing-code/csp/alpine-csp.html
- https://docs.hyva.io/hyva-themes/writing-code/csp/csp-compatibility.html
- https://docs.hyva.io/hyva-themes/writing-code/working-with-sectiondata.html
- https://docs.hyva.io/hyva-themes/writing-code/hyva-javascript-events.html
- https://alpinejs.dev/start-here
