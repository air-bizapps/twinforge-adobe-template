---
name: adobe-magento-theme-developer
description: "Magento 2.4.8 storefront theme files: registration, theme.xml parent chain, which files replace and which merge, template and layout overrides, page layouts, etc/view.xml (image sizes, vars), fonts, theme assignment. Use when adding, overriding or locating a file in a theme, or creating a child theme. Daily conventions are in adobe-magento-frontend-standards."
---

# Theme files and inheritance

What a storefront theme is made of and how Magento finds each file. Bunge's theme is `app/design/frontend/Webjump/theme-frontend-bunge`, with parents `Webjump/theme-frontend-carbono` and `Webjump/theme-frontend-translate` (in `vendor/webjump/`, read only), then `Magento/luma` and `Magento/blank`. The fallback rule, copy-from-the-first-parent rule and layout delta rule are in `adobe-magento-frontend-standards`; this skill is the reference for the theme's files.

## Rules

- **A theme is three files plus folders.** `registration.php` calls `ComponentRegistrar::register(ComponentRegistrar::THEME, 'frontend/Vendor/theme', __DIR__)`; `theme.xml` has `<title>`, `<parent>` and `<media><preview_image>`; `etc/view.xml` is optional. `bin/magento setup:upgrade` records the theme; it shows on the storefront only after it is assigned in Content > Design > Configuration.
- **Bunge needs no new theme.** Changes go in the Bunge theme. Create a child theme only for a separate store view with its own look; its `<parent>` is `Webjump/theme-frontend-bunge`.
- **Where an override goes** (paths inside the theme): module template `Vendor_Module/templates/<same path>.phtml`; layout delta `Vendor_Module/layout/<handle>.xml`; Knockout template `Vendor_Module/web/template/<same path>.html`; module JS or LESS `Vendor_Module/web/js|css/...`; theme-wide assets `web/css/source/`, `web/fonts/`, `web/images/`; translations `i18n/<locale>.csv`. A path that differs by one folder silently falls back to the parent.
- **What replaces and what merges.** Templates, `.html`, `.js`, `.less`, images and fonts replace the parent's file whole. Layout XML, `requirejs-config.js` and `etc/view.xml` merge with every parent, so they hold only your change. A theme CSV overrides the module's phrase for that locale.
- **Find the file that renders before copying.** Check, in order: Bunge, `vendor/webjump/theme-frontend-carbono`, `vendor/webjump/theme-frontend-translate`, `vendor/magento/theme-frontend-luma`, `vendor/magento/theme-frontend-blank`, the module's `view/frontend`. Deleting your override restores the parent's file.
- **Templates from another module are addressed by module.** In layout: `template="Magento_Catalog::product/view/details.phtml"`; the theme override still wins through fallback.
- **Page layouts.** A new one needs `Magento_Theme/layouts.xml` (`<layout id="x"><label translate="true">`) and `Magento_Theme/page_layout/x.xml`; Bunge already has `institutional-pages`. Pages pick it with `<page layout="x">` or in the CMS page admin.
- **`etc/view.xml` drives product images and theme vars.** Change only the `<image id="..." type="...">` you need (`width`, `height`, `aspect_ratio`), then run `bin/magento catalog:images:resize` so cached images are rebuilt. `<vars module="...">` holds widget settings (gallery, Page Builder); `<exclude>` keeps files out of JS bundling.
- **Fonts**: files in `web/fonts/`, `@font-face` through `.lib-font-face()` in LESS, preload with `<font src="fonts/x.woff2"/>` in `Magento_Theme/layout/default_head_blocks.xml`. Bunge removes Luma's Open Sans there and loads Lato; remove the parent's font when replacing it, or both download.
- **Never edit `vendor/webjump` or `vendor/magento` themes.** Composer overwrites them; override in Bunge.

## Steps

1. `php "$T/magento-trace.php" <url>` (`$T`: `scripts/` of `adobe-magento-toolbox`) to get handle, template and module.
2. Walk the chain above and copy the first file found to the same relative path in Bunge.
3. Make the change; layout and `view.xml` contain only the delta.
4. Clean what the change needs (`adobe-magento-frontend-standards`, static deployment); for `view.xml` images, `catalog:images:resize`.

## Verify

- `php "$T/magento-verify.php"`: `theme.xml`, layout, `layouts.xml` and `view.xml` against their XSD.
- `bin/magento config:show design/theme/theme_id` (add `--scope=stores --scope-code=<code>`) points to the expected theme.
- Developer mode, template hints on: `bin/magento dev:template-hints:enable`, reload, the path shown is your file; `dev:template-hints:disable` after.
- `curl -s <url> | grep -c '<marker from your template>'`.

## Pitfalls

- Override ignored -> wrong folder (`templates/` missing, module name typo) or a child theme already overrides it.
- Copied the module's template while Carbono had its own -> start again from Carbono's file.
- Parent's layout file copied whole into Bunge -> both merge and blocks duplicate; keep only the delta.
- New image size has no effect -> resize not run, or the `id` differs from the one the template asks for.
- Theme missing from the admin list -> `registration.php` path wrong or `setup:upgrade` not run.

## Docs

- https://developer.adobe.com/commerce/frontend-core/guide/themes/
- https://developer.adobe.com/commerce/frontend-core/guide/themes/inheritance/
- https://developer.adobe.com/commerce/frontend-core/guide/themes/configure/
- https://developer.adobe.com/commerce/frontend-core/guide/layouts/
- https://developer.adobe.com/commerce/frontend-core/guide/templates/
