---
name: adobe-magento-hyva-specialist
description: "Hyvä-only storefront rules for Magento 2.4.8: detecting Hyvä, compatibility modules, hyva_ layout handles, ViewModels via $viewModels, inline script CSP registration, Tailwind sources and CSS build. Use only when the theme inherits from Hyva/default or Hyva/default-csp. Alpine components: adobe-magento-alpine-specialist; server round-trips: adobe-magento-magewire-specialist. On a Luma theme (like Bunge's), use adobe-magento-frontend-standards."
---

# Hyvä storefront (themes, compat modules, Tailwind)

Only for projects whose storefront theme inherits from `Hyva/default` or `Hyva/default-csp`. On a Luma theme, like Bunge's, none of this applies: use `adobe-magento-frontend-standards`. Check first: no `vendor/hyva-themes/` directory means the project is not Hyvä. Alpine.js components are in `adobe-magento-alpine-specialist`; Magewire components in `adobe-magento-magewire-specialist`.

## Rules

- **Luma JavaScript does not run on Hyvä.** Hyvä loads no RequireJS, Knockout or jQuery, so `data-mage-init`, `x-magento-init` and storefront UI Components render nothing. Rewrite the behavior in Alpine (`adobe-magento-alpine-specialist`); never add RequireJS back.
- **A third-party Luma module that renders on the storefront needs a compatibility module** (`Hyva_<Vendor><Module>`), registered in `etc/frontend/di.xml` on `Hyva\CompatModuleFallback\Model\CompatModuleRegistry`, argument `compatModules`, item with `original_module` and `compat_module`. Its templates replace the original ones only on Hyvä store views, on the same relative path. Check first whether the vendor already ships a Hyvä version.
- **Your own module serves both themes.** Luma layout stays in the normal handles; Hyvä-only blocks go in `view/frontend/layout/hyva_<handle>.xml`. On Hyvä store views every handle gets a `hyva_` twin, loaded after it. In PHP: `Hyva\Theme\Service\CurrentTheme::isHyva()`.
- **Logic goes in a ViewModel, not in a Block.** In the template: `$viewModels->require(\Vendor\Module\ViewModel\Foo::class)` (any class implementing `ArgumentInterface`); no layout argument needed. Escape with `$escaper`. Example: `php "$T/magento-example.php" viewmodel` (`$T` is the `scripts/` folder of `adobe-magento-toolbox`).
- **Every inline `<script>` is followed by `<?php $hyvaCsp->registerInlineScript() ?>`** (Hyvä 1.3.11+): strict CSP blocks unregistered inline code. This applies to every template, Alpine or not.
- **Tailwind only emits classes it finds in scanned files.** A module with templates outside the theme must be listed in `app/etc/hyva-themes.json` (compat modules are added automatically; other modules observe `hyva_config_generate_before`) and ship `view/frontend/tailwind/module.css` (Tailwind 4, Hyvä 1.4) or `tailwind.config.js` (Tailwind 3, merged into `purge.content`). The file regenerates on `setup:upgrade`, `module:enable` or `bin/magento hyva:config:generate`.
- **Write full class names.** Never build them by concatenation (`'text-' + color`) in PHP or JS: the scanner can't see them, so they are missing from the CSS.
- **Style with utility classes in templates**; put a custom component class (`@apply`) in the theme's Tailwind sources only when the same group repeats across many templates. Theme colors and spacing go in the theme's Tailwind config or `@theme` (Tailwind 4), not hard-coded hex values.
- **Build the CSS before static deploy**: in the theme's `web/tailwind`, `npm ci` then `npm run build` (writes `web/css/styles.css`); `npm run watch` while developing. The CI build runs it too, or production serves the old CSS.
- **Server data for the browser comes from GraphQL GET queries or customer sections**, not from new uncached controllers: Hyvä reads private data through its own customer-data loader (`adobe-magento-alpine-specialist`), and public GraphQL is cacheable (`adobe-magento-cache-analyst`).

## Steps

1. Confirm Hyvä and the Tailwind major (`tailwindcss` in the theme's `web/tailwind/package.json`).
2. Third-party module: compat module, templates on the same relative path as the original.
3. Own module: `hyva_<handle>.xml`, ViewModel, template; behavior in Alpine or Magewire.
4. Register Tailwind sources and build the CSS.

## Verify

- `php "$T/magento-verify.php"`: layout and `di.xml` against the XSD; ViewModel classes exist.
- `composer show hyva-themes/magento2-default-theme` for the Hyvä version.
- `grep -c 'Vendor/Module' app/etc/hyva-themes.json` is at least 1.
- After `npm run build`, the new class name appears in the theme's `web/css/styles.css`.
- The browser console shows no "Refused to execute inline script" on the page.

## Pitfalls

- Works in developer mode, blank in production -> missing `registerInlineScript()`, or class outside the Tailwind sources.
- Luma template with `x-magento-init` copied into a Hyvä theme -> rewrite in Alpine.
- Compat module template on a different path than the original -> it is never picked; mirror the path.
- Class added in a template but no style -> module not in `hyva-themes.json`, or CSS not rebuilt.

## Docs

- https://docs.hyva.io/hyva-themes/compatibility-modules/index.html
- https://docs.hyva.io/hyva-themes/writing-code/csp/csp-compatibility.html
- https://docs.hyva.io/hyva-themes/working-with-tailwindcss/generating-css.html
- https://docs.hyva.io/hyva-themes/writing-code/working-with-view-models/index.html
- https://tailwindcss.com/docs
