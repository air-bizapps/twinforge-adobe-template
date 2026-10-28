---
name: adobe-magento-css-specialist
description: "Magento 2.4.8 LESS pipeline on a Luma-based theme: styles-m/styles-l and @magento_import, where a rule goes (_module, _widgets, _extend), media-target and the breakpoint collector, server-side compiler (less.php) versus Grunt, source maps, minify and critical CSS, compile errors. Use when writing, debugging or compiling storefront styles. Variable and !important rules: adobe-magento-frontend-standards."
---

# LESS and CSS in a Luma theme

How Magento turns the theme's LESS into the CSS the browser gets, and where each rule belongs. Bunge's theme chain is Bunge, Carbono, Translate, Luma, Blank. The rules on variables, `_extend.less`, `!important` and `.media-width()` usage are in `adobe-magento-frontend-standards`; Bunge's own LESS lessons in `bunge-frontend-code-quality`. This skill is the pipeline underneath.

## Rules

- **Two files reach the page.** Blank's `default_head_blocks.xml` loads `css/styles-m.css` always and `css/styles-l.css` only with `media="screen and (min-width: 768px)"` (and print). A rule that ends up in `styles-l` never applies below 768px, whatever its own media query says.
- **`//@magento_import` collects module files.** `styles-m.less` and `styles-l.less` import `source/_module.less`, `source/_widgets.less` and `source/_extend.less` from every enabled module and from the theme's `<Vendor_Module>/web/css/source/`. A theme copy of a module's `_module.less` replaces it; `_extend.less` adds to it. The same applies at theme level: a child's `web/css/source/_extend.less` hides a parent's.
- **Each file is compiled twice**, once with `@media-target: 'mobile'` and once with `'desktop'`. Put shared rules in `& when (@media-common = true) { }` so they are written once, in `styles-m`; put responsive rules in `.media-width(@extremum, @break) when ...` blocks. The collector (`lib/_responsive.less`; Bunge has its own copy) sends `max` widths and `min @screen__s` to `styles-m`, and `min @screen__m`, `min @screen__l` (Bunge only), `min @screen__xl` and `max @screen__l` to `styles-l`.
- **Breakpoints**: `@screen__xxs` 320px, `@screen__xs` 480px, `@screen__s` 640px, `@screen__m` 768px, `@screen__l` 1024px, `@screen__xl` 1440px (`lib/variables/_responsive.less`). Use the variables.
- **Production CSS comes from the PHP compiler** (`wikimedia/less.php` 5.5.1, run by `setup:static-content:deploy` or by the first request in developer mode). Grunt uses less.js 4.2.0 and can accept code the PHP compiler rejects or computes differently. Write math in parentheses, `(@indent__base / 2)`, and confirm the final result with the PHP compiler.
- **Developer mode already writes source maps** (server-side compilation, as configured in Bunge's `app/etc/config.php`), so browser dev tools show the `.less` file and line. Grunt is optional, for watch-and-reload.
- **Bunge minifies CSS** (`dev/css/minify_files` = 1, locked in `config.php`); critical CSS (`dev/css/use_css_critical_path`) is off. Do not turn either on as part of a feature.
- **A separate stylesheet costs a request.** `<css src="css/x.css"/>` in layout compiles `web/css/x.less`; use it only for a page with large, page-specific styles. Otherwise use `_extend.less` or the module's `_module.less`.
- **Use `(reference)` to import for mixins and variables only**: `@import (reference) 'x.less';` makes mixins available without writing its CSS again.

## Steps

1. Find the compiled rule in dev tools; the source map gives the `.less` file in the chain.
2. Override in Bunge: variable in `_theme.less` or `lib/variables/`, new rule in `_extend.less` or the module's `_module.less` (`php "$T/magento-example.php" less`; `$T` is `scripts/` of `adobe-magento-toolbox`).
3. Wrap shared rules in `@media-common`, responsive ones in `.media-width()`.
4. Clean the theme's generated files (`adobe-magento-frontend-standards`, static deployment) and reload.
5. Optional Grunt loop: `cp grunt-config.json.sample grunt-config.json`, `cp local-themes.js dev/tools/grunt/configs/local-themes.js`, `cp package.json.sample package.json`, `npm install`, then `npx grunt exec:bunge` (publishes sources; rerun after adding a file), `npx grunt less:bunge`, `npx grunt watch`. `bunge_en` is the `en_US` entry.

## Verify

- PHP compiler: `bin/magento setup:static-content:deploy pt_BR --area frontend --theme Webjump/theme-frontend-bunge -f`; an error prints `Compilation from source:` with the file.
- `grep -c '<selector>' pub/static/frontend/Webjump/theme-frontend-bunge/pt_BR/css/styles-m.css` (or `styles-l.css`) is 1 for a shared rule, not 2.
- Check the page at 375px, 768px and 1280px.

## Pitfalls

- Rule appears twice in the CSS -> missing `& when (@media-common = true)`.
- Media query compiled to nothing -> the `.media-width()` pair is not in the collector.
- Desktop style ignored on a 700px tablet -> it is in `styles-l`, which the browser does not load there.
- Works with Grunt, fails on deploy -> less.js-only syntax; fix for less.php.
- New `.less` file not picked up by Grunt -> rerun `grunt exec:bunge`.
- Copy of a parent's `_module.less` to change one rule -> everything else in it stops tracking upstream; use `_extend.less`.

## Docs

- https://developer.adobe.com/commerce/frontend-core/guide/css/preprocess/
- https://developer.adobe.com/commerce/frontend-core/guide/css/
- https://developer.adobe.com/commerce/frontend-core/guide/responsive-web-design/
- https://developer.adobe.com/commerce/frontend-core/guide/css/ui-library/
- https://lesscss.org/features/
