---
name: magento-frontend-standards
description: "Magento 2 Luma-based frontend conventions for Bunge's customized theme: theme inheritance, layout XML, blocks/templates/ViewModels, the RequireJS/Knockout JS layer, LESS/CSS, static content deployment, and storefront accessibility/performance. Use for frontend (theme/template/ JS/CSS) work. Builds on `adobe-commerce-standards` (shared base) — read that first; this skill only covers what's specific to frontend work."
---

# Magento Frontend Standards

Frontend-specific depth on top of the shared `adobe-commerce-standards` base.
Bunge's storefront is a **custom theme layered on top of Magento's Luma**
(not Hyvä, not headless/PWA) — everything below assumes that stack.
Examples: `magento-example frontend-page|viewmodel|js|mixin|less`
(`adobe-magento-toolbox`); a real page's path: `magento-trace <url>`.

## Theme architecture

- Bunge's theme is `app/design/frontend/Webjump/theme-frontend-bunge`. Its
  `theme.xml` parent is `Webjump/theme-frontend-carbono`, which inherits
  `Webjump/theme-frontend-translate` (both in `vendor/webjump/`, never
  edited), `Magento/luma`, `Magento/blank`, then the module. Override only
  the file that needs to change, never a whole module's view folder.
- Fallback replaces whole files (`.phtml`, `.html`, `.js`, `.less`): copy the
  one that renders today — the first found in Bunge, Carbono, Translate,
  `vendor/magento/theme-frontend-luma`, `-blank`, module — not the module's.
- Layout XML and `requirejs-config.js` merge instead: write only your delta;
  never `layout/override/` (replaces the handle file, goes stale).
- Before overriding a core Luma template, check whether a layout XML change
  (adding/removing a block, changing an argument) or a plugin on a Block
  class can achieve the same result without a template copy — a template
  override goes stale silently when Luma itself changes upstream; a layout
  change or plugin usually doesn't.

## Layout, blocks, templates, ViewModels

- Layout XML (`layout/*.xml`) controls page structure: containers, blocks,
  handles. Prefer adding/moving blocks via layout XML over PHP-side
  manipulation.
- Handles: `default` (every page), `<route_id>_<controller>_<action>`, plus
  type/id handles (`catalog_product_view_type_configurable`).
  `magento-trace --handle <handle>` lists its module and `app/design` files.
- Change an existing element with `referenceBlock`/`referenceContainer`
  (`<arguments>`, `template`), reposition it with
  `<move element="..." destination="..." before|after="..."/>`, drop it with
  `remove="true"`. Use `display="false"` when another file still references
  the element: it stays in the structure, unrendered.
- A reference or `move` to a name that doesn't exist (typo, removed
  elsewhere) fails silently; developer mode logs `Broken reference` in
  `var/log/system.log` — grep it after a layout change.
- **Business logic goes in a ViewModel** (`Magento\Framework\View\Element\Block\ArgumentInterface`),
  not in the Block class or the `.phtml` template. Blocks should stay thin
  (wiring/context only); templates should stay close to pure markup calling
  ViewModel methods. This is the modern Magento 2 pattern — do not add new
  logic to a legacy Block class when a ViewModel is the right place.
- `.phtml` templates: escape every printed value with `$escaper` (available
  in every template): `escapeHtml()`, `escapeHtmlAttr()`, `escapeUrl()`,
  `escapeJs()`; `$block->escape*()` is deprecated. Unescaped output in a
  customer-facing template is a real XSS risk, not just a lint nitpick.

## JavaScript (RequireJS / Knockout)

- Start JS with `x-magento-init` / `data-mage-init`, not inline `<script>`
  code. `Vendor_Module/js/x` resolves to `view/<area>/web/js/x.js` by itself;
  `map` is only for an alias. Checkout's CSP blocks inline scripts not
  rendered with `$secureRenderer->renderTag()`.
- `requirejs-config.js` is per area (`view/frontend|adminhtml/`; `view/base/`
  for both); the theme's merges last. `paths` + `shim` only for a non-AMD
  library; a mixin set to `false` disables one declared elsewhere; `deps`
  loads on every page — avoid it for feature code.
- To change behavior of a core Magento JS component (checkout steps,
  minicart, etc.), use a **RequireJS mixin** (`etc/*_mixins.js` or theme
  `requirejs-config.js` mixins block) rather than overriding the whole
  component file — same reasoning as template overrides: a mixin survives
  upstream changes better than a full-file copy.
- Dynamic, stateful UI (checkout, minicart, product options) uses Knockout.js
  components with `.html` templates bound via `data-bind`. Don't reach for
  plain jQuery DOM manipulation where the surrounding code already uses
  Knockout — mixing paradigms in the same component is a common source of
  hard-to-debug state bugs.
- Knockout components extend `uiComponent` (has children) or `uiElement`
  (leaf); `template: 'Vendor_Module/x'` is `view/<area>/web/template/x.html`.
  A property re-renders only if in `tracks` or `observe()`d (then set it with
  `this.prop(value)`). Share data via `imports`/`exports`/`links`, not `uiRegistry` lookups.

## LESS / CSS

- Theme styles compile from LESS (`web/css/source/_theme.less` and friends).
  Use existing variables before introducing new ones — Bunge's effective
  values are in the theme's `_theme.less` and `lib/variables/*.less`, then
  `Magento/luma`/`Magento/blank` and `lib/web/css/source/lib/variables/`.
- Both replace the originals as full copies: `_theme.less` (overrides only)
  and `lib/variables/_colors.less` (new colors go here). Never delete an
  original variable — whatever uses it stops compiling.
- Add styles in `_extend.less` (theme `web/css/source/` or
  `<Vendor_Module>/web/css/source/`); copying a parent's `_module.less` to
  change one rule replaces the whole file.
- Avoid `!important` — it almost always means a specificity problem that a
  correctly-scoped selector or a layout-level fix would solve properly.
- Mobile-first, responsive within Luma's existing breakpoint system — don't
  introduce a parallel breakpoint scheme. Common rules go in
  `& when (@media-common = true)` (else duplicated in `styles-m`/`styles-l`);
  responsive ones in `.media-width()` with `@screen__*`, not raw `@media`.
  Only pairs the collector calls compile — Bunge's `lib/_responsive.less` has
  `'min', @screen__l` instead of `'max'`; others are dropped silently.

## Static content deployment

- `developer` mode: `.js`/`.html` in `pub/static` are symlinks, but compiled
  CSS and merged `requirejs-config.js` are generated once — after changing
  them, delete the theme's dir in `pub/static/frontend/` and
  `var/view_preprocessed/pub/static/frontend/` (`cache:clean` doesn't). Layout
  XML: `cache:clean layout full_page`; template: `block_html full_page`.
- `production` mode requires `bin/magento setup:static-content:deploy
  [locale]` after any static asset change (LESS, JS, template-adjacent
  static files) — a change that "works locally" but wasn't deployed is a
  common real incident. Cloud builds it per `.magento.env.yaml` `SCD_MATRIX`;
  locally add `pt_BR en_US --theme Webjump/theme-frontend-bunge -f`.
  State in your task update whether this was run when it's relevant.

## Accessibility and performance

- Customer-facing storefront: escape/alt-text discipline (see above),
  keyboard navigability for interactive components, sufficient color
  contrast — don't ship a visual change that regresses these silently.
- Avoid layout shift (reserve space for images/dynamic content), lazy-load
  below-the-fold images, keep JS bundle growth in mind — these directly
  affect Core Web Vitals and real conversion on a commerce storefront, not
  just a lint score.

## Common anti-patterns to flag, not silently fix

- Business logic inside a `.phtml` template or a Block class instead of a
  ViewModel.
- A full core template or JS component file copied into the theme instead
  of a layout XML change, plugin, or RequireJS mixin.
- A copy taken from the module when a parent theme already overrides it.
- Unescaped dynamic output in a template.
- A production-mode change shipped without running
  `setup:static-content:deploy`.
