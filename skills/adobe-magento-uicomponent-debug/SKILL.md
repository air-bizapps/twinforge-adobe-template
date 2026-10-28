---
name: magento-uicomponent-debug
description: "Use when a Magento 2 admin UI Component page (grid or form) loads (HTTP 200, page title/menu render) but the component area shows only the loading spinner forever, with no error in server logs, no error in browser console, and no pending/failed request in the Network tab. This is a specific, recognizable failure mode with a specific debugging procedure. Step 0 (php tools/uicomponent/validate.php, real XSD validation) should also be run proactively on every new/changed ui_component file, not only when this exact symptom is reported."
---

# Debugging the "silent infinite spinner" UI Component failure

## Recognize the symptom first

This skill applies specifically when **all** of these are true:
- The admin page itself loads (title bar, menu, breadcrumb all render)
- The UI Component area (grid or form) shows only the default spinner,
  indefinitely
- `var/log/exception.log` and `var/log/system.log` show nothing new for that
  request (only unrelated "Broken reference" INFO lines, which are harmless
  layout warnings, not the cause)
- Browser DevTools Console shows no error
- Browser DevTools Network tab shows no pending or failed request for that
  page load

If there IS a visible error anywhere in that list, this is a different,
easier class of bug — just fix the reported error. This skill is for when
there is genuinely nothing to go on.

## Step 0 (always do this first, takes seconds): schema-validate every file

Before doing anything else, run (repo root, no Magento bootstrap or DB needed):

```bash
php tools/uicomponent/validate.php <path-to-the-module-or-file>
```

This parses and schema-validates every `ui_component/*.xml` file under that
path and prints, for each one, either `OK` or `FAIL` with the exact file,
line, and invalid element/attribute — e.g. an attribute that only exists as
a nested `<item>`/`<settings>` child in the "verbose" argument syntax but was
mistakenly written as a raw XML attribute instead (seen in practice:
`<column ... dataType="select">` — `dataType` is only valid as
`<item name="dataType" xsi:type="string">select</item>` inside the column's
config, not as an attribute on `<column>`), or the reverse — an element
written as a child instead of a tag attribute (`<component>...</component>`
inside a column's `<settings>`, when `component` is only valid directly on
the tag). `adobe-magento-ui-component-developer`'s reference table names, for each component
type, the real vendor file to copy the working shape from once you find a
`FAIL`.

This is the same check `adobe-magento-ui-component-developer` requires as its Definition-of-Done
gate on every new/changed `ui_component` file — run it proactively during
creation, not only when debugging a reported symptom.

This check costs under a second and immediately separates two different bug
classes:
- **Throws here** → ordinary schema/typo bug, fix what the exception says,
  done. Do **not** proceed to the rest of this skill.
- **No exception, component builds fine** → the failure is the silent
  client-side deadlock described below; proceed to "What TO do".

Also: if you're fixing one copy of a bug that came from copy-pasting a
ui_component XML file into two or three near-identical variants (e.g. one
grid per sub-entity type), **grep the sibling files for the same broken
pattern immediately** — don't wait for the user to report each one
separately.

## What this symptom actually means

A UI Component's client-side bootstrap (`Magento_Ui/js/core/app`) builds a
tree of Knockout components from the JSON config embedded in the page. Each
node (form, dataSource, fieldset, field) is only rendered once its declared
`deps` have resolved. If **any node in that tree never gets a JS `component`
correctly registered** (most commonly: the `<dataSource>` never declares its
`component="Magento_Ui/js/form/provider"`), the `deps` promise for that node
**never resolves**. Nothing throws, nothing logs, no HTTP request is needed —
it just waits forever. This is a client-side deadlock in a promise chain, not
a server error and not a network failure, which is exactly why none of the
usual places show anything.

Because the HTML that's embedded in the page (including the JSON config
block) is generated correctly by PHP, **server-side rendering will often
succeed even when the page is completely broken for the user**. Do not let a
successful server-side render convince you the bug is fixed — it only proves
the PHP half is fine.

## What NOT to do (time sinks that do not find this bug)

These were all tried on a real instance of this exact symptom and wasted
significant time without finding the actual cause:

- **Assuming it's a PHP/server hang.** Building a CLI harness that calls
  `prepare()`/`toHtml()` on the component and timing it. If this hangs, it is
  almost always an artifact of your test environment (deleting
  `generated/code` and racing code generation under system load, OPcache
  timing, etc.), not the real bug — the real bug reproduces in the browser
  even when server-side rendering is fast and successful.
- **Chasing infrastructure theories**: stuck file locks (`var/.regenerate.lock`),
  OPcache staleness, MySQL table locks, PHP-FPM worker exhaustion. Check these
  only if you have a *specific* observation pointing at them (e.g. a process
  actually shown holding a lock via `lsof`). Otherwise they're a distraction.
- **Bisecting fields one at a time inside the existing (broken) file.**
  Removing/re-adding `<field>` elements will never find a bug that lives in
  the shared `<dataSource>`/`<settings>` preamble, because that preamble is
  present in every bisection step. You will get inconsistent, confusing
  results and waste many cycles.
- **Patching the broken file incrementally based on partial memory of "how
  Magento forms look".** This can fix real-but-irrelevant bugs (and there may
  be several) while leaving the actual root cause untouched, because you're
  never comparing structurally against a known-good whole.

## What TO do

1. **Find a real, currently-working admin edit page in `vendor/magento/*`
   that uses the same UI Component type (form or grid).** Good defaults:
   `vendor/magento/module-inventory-admin-ui` (`inventory_source_form.xml` +
   `inventory_source_edit.xml`) for a form, `module-cms`
   (`cms_block_listing.xml`) for a grid.

2. **Prove the container/layout is not the problem** by temporarily pointing
   your layout's `<uiComponent name="...">` at that vendor component's name
   instead of yours, with no other changes. If the vendor component renders
   correctly in your page, the bug is 100% inside your own `ui_component` XML
   or its DataProvider wiring — not the layout, theme, JS bundle, or admin
   session/ACL.

3. **Rebuild your `ui_component` XML as a full replacement copy of the
   working vendor file**, renamed, with **zero fields**, and verify it loads
   in the real browser (not just via a CLI harness) before adding anything
   back. Use a hard refresh (Ctrl+Shift+R) to rule out stale browser-cached
   JS.

4. **Add fields back incrementally from your original file**, testing after
   each batch, until you're back to the full original field set — confirming
   nothing about the fields themselves was the problem.

5. **Only now diff the original broken file against the rebuilt working
   file** (`git diff <old-commit> -- path/to/form.xml`, or a manual diff if
   uncommitted) to see exactly what structural element was wrong. In
   practice this is almost always something in the `<dataSource>` declaration
   — check first for a `<dataSource>` that does NOT have
   `component="Magento_Ui/js/form/provider"` on the tag itself, and does NOT
   have an `<argument name="data"><item name="js_config"><item
   name="component">` block either. Any other structure inside `<dataSource>`
   that isn't one of those two forms (e.g. a fabricated
   `<argument name="dataProvider" xsi:type="configurableObject">`) is
   suspect — it will pass XML schema validation but never register the JS
   component.

6. Also check, in order of likelihood, if step 5 doesn't explain it:
   - `<fieldset>`-level `<dataScope>` that doesn't match your DataProvider's
     actual (flat vs. nested) data shape
   - A field's `component` override (e.g. a custom currency/mask field)
     pointing at a RequireJS module whose static file was never deployed —
     check `pub/static/<area>/<theme>/<locale>/<Vendor_Module>/js/view/<file>.js`
     actually exists; if not, run
     `bin/magento setup:static-content:deploy -f -a adminhtml`
   - `requirejs-config.js` mapping a stale/renamed module namespace

## Quick sanity commands

```bash
# Real schema validation (Step 0) — catches invalid elements/attributes, not just malformed XML
php tools/uicomponent/validate.php path/to/module/view/adminhtml/ui_component

# Well-formedness only (much weaker — passes plenty of schema-invalid XML too)
php -r '$d=new DOMDocument(); var_dump($d->load("path/to/file.xml"));'

# Flush cache after every XML/DI change
bin/magento cache:flush

# Confirm a component's static JS is actually deployed
find pub/static -path "*Vendor_Module*js/view/your-component.js"

# Diff the broken version against your rebuilt working version
git diff <last-good-or-original-commit> -- path/to/ui_component/form.xml
```

## Reporting back

When you find the actual structural diff, show it to the user as a minimal
before/after XML snippet (not the whole file) plus one sentence on the
mechanism ("the dataSource never declared its JS component, so the form's
deps promise never resolved — silent client-side deadlock, no error
anywhere"). That's the useful, transferable finding — not the list of dead
ends you explored to get there.

