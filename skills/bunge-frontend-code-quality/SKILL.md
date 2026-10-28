---
name: bunge-frontend-code-quality
description: "Clean-code patterns and anti-patterns actually observed in Bunge's Magento 2 frontend/theme codebase, with real file:line examples — ViewModel usage, RequireJS mixins, LESS conventions, and concrete mistakes to avoid repeating. Use for any frontend code change or review. Bunge-specific — built from auditing this codebase, not generic Magento advice (see `adobe-commerce-standards`/`magento-frontend-standards` for that)."
---

# Bunge Frontend Code Quality

Lessons from auditing this actual codebase (2026-09-03), not generic
Magento/Luma theory. Each rule below is backed by a real example already in
the repo — either a pattern to copy or a mistake not to repeat. Pair with
`CONTEXT.md` (what the system is) and `magento-frontend-standards`/
`adobe-commerce-standards` (generic Magento conventions).

## Follow: ViewModels for new logic, not Block classes or templates

`app/code/Webjump/CustomCatalog/ViewModel/TabsAttribute.php` is the house
reference: implements `ArgumentInterface`, constructor-injected
dependencies, wired via layout XML `<argument xsi:type="object">FQCN
</argument>`, no logic leaking into a Block or `.phtml`. New frontend
features should follow this shape.

**This is violated in real, existing places — don't copy them:**
- `app/code/Webjump/CustomCatalog/Block/CustomCatalog.php` duplicates
  `getIsLoggedIn()`, `getWhiteZoneInfo()`, `isProductNew()` almost verbatim
  from the `TabsAttribute` ViewModel above — the same logic exists twice.
  If a ViewModel already covers a data need, extend/reuse it; don't
  re-derive the same getters on a Block.
- That same Block class extends `Magento\Backend\Block\Template` (the
  **admin** base class) in a storefront context — works by inheritance
  accident, not by design. New frontend Blocks must extend
  `Magento\Framework\View\Element\Template`.

## Avoid: reaching into a service locator from a `.phtml`

Two real instances, neither to imitate:
- `Magento_Theme/templates/html/topmenu.phtml:19-20` — direct
  `$this->helper(\Webjump\BungeGlobalConfig\Helper\Data::class)` call from
  a template. Inject the helper into a Block or ViewModel instead.
- `Magento_Sales/templates/order/items/renderer/default.phtml:28` —
  `\Magento\Framework\App\ObjectManager::getInstance()->get(...)` in a
  template, the textbook ObjectManager-in-template mistake. Constructor-
  inject the dependency into the rendering Block/ViewModel.

## Follow: check for a CMS/Page Builder block before hardcoding content

11 instances of `<block class="Magento\Cms\Block\Block">` in the theme's
layout XML (`Magento_Theme/layout/default.xml:37-119` and others) pull in
merchandiser-editable content (`Footer_Block`, `Header_TabsV4`,
`Mobile_Tabs`, promotional messaging, chat widget). Before hardcoding a
copy or markup change, check whether it actually belongs in a CMS block
someone else edits directly — you may be about to change the wrong file.

## Follow: RequireJS mixins to customize core JS, not full-file overrides

The codebase already does this correctly and extensively — copy the
pattern, don't override whole files. Reference examples:
`Magento_Checkout/web/js/view/progress-bar-mixin.js` (Knockout component:
`return target.extend({ initialize: function(){ this._super(); ... } })`)
and `Magento_Theme/web/js/mage/menu-mixin.js` (jQuery UI widget:
`$.widget('mage.menu', targetWidget.menu, {...})`), wired via the
`config.mixins` block in the relevant `requirejs-config.js`.

**One legacy pattern not to imitate**: `Magento_Theme/web/js/header.js`
mixes vanilla DOM APIs with jQuery and does inline style injection via
`.css({...})` (~lines 24-38) instead of toggling a CSS class — for new
sticky-header/UI-toggle behavior, toggle a class, don't inject inline
styles imperatively.

## Avoid: adding more `!important`

201 existing occurrences, heavily concentrated in
`Magento_Theme/web/css/source/webjump/_footer.less` (56) and
`Magento_Catalog/web/css/source/module/_listings.less` (30) — largely
fighting Page Builder's own default widget styles (`.widget { margin: 0
!important; }`). When you need to override a Page Builder content-type
default, **scope the selector to the specific content-type class or
increase specificity properly** instead of reaching for `!important`. This
codebase already has too much of it; don't add more.

## Know: the shipped color-variable typo

`web/css/source/lib/variables/_colors.less:123-124` defines
`@color-bunge-sucess-dark`/`@color-bunge-sucess-light` (misspelled
"success"). Use the variable exactly as shipped — it's referenced
elsewhere in the codebase. Don't silently rename it in one file; a
coordinated rename across the whole theme would be a separate, deliberate
task, not something to do as a side effect of unrelated work.

## Validate layout XML — the merger tolerates real mistakes silently

`Magento_Catalog/layout/catalog_product_view.xml` lines 46 and 56 contain
stray literal `"` characters as loose text nodes inside a `<block>`
element — copy-paste debris that Magento's layout merger silently
tolerates, which is exactly why it survived. When editing layout XML,
actually look at what you're producing, not just whether the page still
renders.

## Accessibility: don't assume it's handled

`aria-*`/`role` attributes exist in ~15-17 places, concentrated in
interactive/form-heavy areas — real but manual and inconsistent, not
systematized. The custom header/menu JS (`header.js`, `menu-mixin.js`)
does not update `aria-expanded` or similar on open/close. When touching an
expand/collapse or interactive component, check whether the specific one
you're touching actually has correct ARIA state — don't assume the
codebase-wide pattern covers it.

## Performance: no lazy-loading or critical-CSS exists yet

`webjump/module-web-vitals-improvements` is installed (real, deliberate
performance investment) but **no `loading="lazy"` attributes exist
anywhere in the theme's templates**, and no preload/critical-CSS pattern
either. Don't assume a new image-heavy feature gets lazy-loading for free
— add it explicitly if the task calls for it.
