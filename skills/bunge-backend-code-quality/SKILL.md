---
name: bunge-backend-code-quality
description: "Clean-code patterns and anti-patterns actually observed in Bunge's Magento 2 backend codebase, with real file:line examples — service contracts, DI/preference conventions, the SAP-integration recipe, and concrete mistakes to avoid repeating. Use for any backend code change or review. Bunge-specific — built from auditing this codebase, not generic Magento advice (see `adobe-commerce-standards`/`magento-backend-standards` for that)."
---

# Bunge Backend Code Quality

Lessons from auditing this actual codebase (2026-09-03), not generic
Magento theory. Each rule below is backed by a real example already in the
repo — either a pattern to copy or a mistake not to repeat. Pair with
`CONTEXT.md` (what the system is) and `magento-backend-standards`/
`adobe-commerce-standards` (generic Magento conventions).

## Follow: for a new admin UI Component (grid/form), the skill is `adobe-magento-ui-component-developer` — read it first

If the task is a new admin backoffice grid or form (UI Component, not a
legacy Block/Widget screen), run the `adobe-magento-ui-component-developer` skill start to
finish before writing any XML — it owns this area, has the exact
dot-path/wiring rules, and the mandatory validation gate (below). This
section only adds two local, Bunge-specific things `adobe-magento-ui-component-developer`
doesn't know: which in-repo module is a genuine UI Component reference,
and which one looks like one but isn't.

**`Webjump\BungeWebhook`'s two grids are real, working UI Components** —
`view/adminhtml/ui_component/webjump_bungewebhook_managehooks_listing.xml`
and `..._logs_listing.xml`. Fine to open as one extra local example of a
listing `<dataSource>`/`<columns>` shape, on top of (never instead of) the
vendor references `adobe-magento-ui-component-developer` names.

**`BungeWebhook`'s edit screen is NOT a UI Component — do not copy it for
one.** `Block/Adminhtml/Hook/Edit/Form.php` + `Tabs.php` is the legacy
`Magento\Backend\Block\Widget\Form`/`Tabs` pattern (pre-UI-Component,
tab-based ExtJS-era admin forms). It has no `ui_component/*_form.xml`, no
DataProvider virtualType, none of what `adobe-magento-ui-component-developer` produces.
Reading it while building a UI Component form is how a wrong shape gets
copied in — if the task needs a new admin **form**, use a vendor UI
Component form (`adobe-magento-ui-component-developer` names the file) as the template, never
this module.

**No new admin UI Component file is done until it passes real schema
validation** — not `DOMDocument::load()`/`simplexml_load_file()` (those
only prove the XML is well-formed, not that Magento's schema accepts every
element/attribute in it — see `adobe-magento-ui-component-developer` section 6 for what that
gap actually let through in BUN-14079). Run, from the repo root, no Magento
bootstrap or DB needed:

```bash
php tools/uicomponent/validate.php app/code/Webjump/<Module>
```

Every file must print `OK`. A `FAIL` line names the exact file, line, and
disallowed element — fix it by copying the working shape from the real
vendor file `adobe-magento-ui-component-developer` points to for that component type, not by
guessing. Paste this command's output in the delivery report for any
subtask that adds or changes a `ui_component/*.xml` file; a report without
it is treated as unverified.

For a mixed read/write area (one table fully manageable, others
consultation-only): the read-only tables just get a listing UI component
with no `<actionsColumn>` write actions and no form/edit controller — there
is no separate "read-only" base class in Magento, the restriction is
simply which controllers and buttons you choose to build. Name the ACL
resources so read-only access and manage access are separately grantable
(e.g. `..._view` vs `..._manage`), matching the pattern an existing module
in this repo already followed for its own menu/ACL split — check the
target module's own `etc/acl.xml` for the closest existing example before
inventing new resource names.

## Follow: service-contract-first

New business logic gets an `Api\XInterface` + `Api\Data\XInterface`, a
`di.xml` preference wiring it to `Model\X`, and (for anything crossing a
boundary) a `webapi.xml` route. This is the dominant, expected shape here
— 159 `Api/*Interface` files, 217 references to `RepositoryInterface`.
Reference shapes: `Webjump/BungeProductPrices/etc/di.xml` (21 command
interfaces) or `Webjump/A810` (full repository + management + import +
export contract set).

## Follow: the SAP-integration recipe, don't reinvent it

`Model/Import/<Entity>.php` (extends `AbstractEntity`, validator chain in
`Model/Import/Validator/*`) + `Model/Export/<Entity>.php` +
`Model/Repository/<Entity>.php` + `Api/<Entity>ManagementInterface`
(`insertX`/`deleteX`) + paired REST routes under `/V1/bunge/<domain>`. Model
new SAP data feeds after `app/code/Webjump/A810` (full test coverage per
validator) — full detail in `CONTEXT.md`.

## Follow: preference for your own interfaces, plugin for core/third-party

This codebase defaults to preferences over plugins (155 vs 57 in
`app/code/Webjump/*/etc/di.xml`) — the opposite of Magento's own general
recommendation, but legitimate here because most preferences target the
team's *own* `Api\*Interface`s. **When extending your own interface,
match this style with a preference. When extending a *core* Magento class
or a third-party module's class (Amasty, Mageplaza, the B2B extension),
prefer a plugin** — a preference there risks silently colliding with
another vendor's preference on the same class. Always
`grep -rn 'for="<TargetClass>"' app/code/Webjump/*/etc/di.xml` before
adding a new preference — a real duplicate already exists
(`BungeProductPrices/etc/di.xml:20-23` declares the exact same preference
for `HasSpecialPaymentConditionInterface` twice) because nobody checked.

## Follow: `db_schema.xml` for new tables, one-class-per-change data patches

Declarative schema (`db_schema.xml`) is required for any new table — 23
modules already use it; only `BungeWebhook` still uses the legacy
`InstallSchema`/`UpgradeSchema` pattern, and that's debt, not a precedent.
EAV attributes and CMS content changes: one `Setup/Patch/Data/*` class per
change, never batched, **never rename or delete a shipped patch class**
(its class name is the patch's identity in the `patch_list` table). Follow
`CustomCustomer/Setup/Patch/Data/` (one class per SAP-driven attribute) or
`SetupContents/Setup/Patch/Data/` (numbered `UpdateXV2`/`V3`/`V4` sequels
for content revisions, never edited in place) as the reference shape.

## Follow: named ACL resources on new REST endpoints, not `anonymous`

Several existing SAP-facing endpoints use `<resource ref="anonymous"/>`
inconsistently — `A810/etc/webapi.xml` requires an ACL resource for
`deleteA810` but not `insertA810`, in the same file. That's existing debt,
not something to extend. **New endpoints default to a named ACL resource.**
If a task seems to require an anonymous endpoint, flag it to the Tech Lead
rather than assuming it's fine because a similar existing one is anonymous.

## Avoid: fixed domains and secrets stored in clear

- Links that go out (e-mail, redirect) come from `Magento\Framework\UrlInterface::getUrl()`
  with query params, never a string like `https://bunge.com.br/...`: a fixed
  domain sends local and staging links to production.
- Access tokens (invite, reset, finalize) are stored as a hash
  (`hash('sha256', $token)`) and compared by hash; only the e-mail carries the
  plain token. A database read must not be enough to use someone's link.
- "Does this customer exist?" is `CustomerRepositoryInterface::get($email, $websiteId)`
  catching `NoSuchEntityException`, not `AccountManagementInterface::isEmailAvailable()`:
  that one returns `true` without checking when guest checkout login is off,
  which is this store's setting.

## Avoid: `ObjectManager::getInstance()` — always constructor-inject

18 existing occurrences in this codebase, all either copied-from-core code
or older modules — none are things to imitate. The worst example,
`app/code/Webjump/CustomLogin/Controller/Account/LoginPost.php:135-137,
152-154,168-170`, pulls `ScopeConfigInterface`/cookie classes via
`ObjectManager::getInstance()->get(...)` inside lazy getters — and that
file is itself the next anti-pattern.

## Avoid: copying a whole core controller to change one behavior

`CustomLogin/Controller/Account/LoginPost.php` is a ~285-line, line-for-
line copy of Magento core's `LoginPost` controller with one behavioral
change, preserving core's own `ObjectManager` calls, and carrying
`@codeCoverageIgnore`/`phpcs:ignoreFile` annotations (lines 41-42) — the
team opted the file *out* of the project's real quality gates
(`grumphp`/`phpcs`/`phpmd` are configured and enforced elsewhere) rather
than fix the design. If a task asks you to change checkout/login/customer
core controller behavior, reach for a plugin around `execute()` (or the
relevant method) first. If a full override is genuinely unavoidable, say
so explicitly and do not add coverage/lint suppressions as a substitute
for a clean design — that combination is the actual anti-pattern, not the
override alone.

## Verify before trusting: module dependency declarations

Two live, real inconsistencies to know about (don't silently "fix" without
checking with the Tech Lead — they may be intentional or mid-migration):
- `CustomSalesOrder/etc/module.xml` declares a sequence dependency on
  `Webjump_BraspagPagador`, which doesn't exist (real module is
  `Webjump_CustomBraspagPagador`).
- `BungeTemplateEmail/etc/module.xml` declares itself as
  `Webjump_BungeEmailTemplate`, not matching its directory name — search
  by the declared name in `module.xml`, never assume directory name is
  authoritative.

## Don't rename known-typo class/file names

`BungeStatusOrder/Setup/Patch/Data/ReadyMiddleareOrder.php` and
`CustomCatalog/Setup/Patch/Data/UpdateCatalogTabsINformationProductAttributes.php`
have real typos baked into shipped, applied patch class names. Renaming
breaks patch identity tracking (`patch_list` table) — leave them as-is;
only add new, correctly-named patches going forward.

## Testing: write tests like the good precedent, don't run them in the subtask

680 unit tests exist under `app/code/Webjump/**/Test/Unit/`, real but
uneven — `A810` has near-1:1 tests per validator (the shape to aim for),
`BungeWebhook` (90 source files) has only 14 tests (not a bar to aim for).
Zero integration tests exist project-wide — unit-test service/command
logic with dependencies mocked; don't reach for an integration test as the
default here, it would be the first one.

Since 2026-09-24 the developer **writes** the unit tests (skill
`create-unit-tests`) but does **not run** them, nor `grumphp` (its task list
includes the full PHPUnit suite), nor any coverage check, inside the subtask —
running them was putting agents in retry loops. There is no coverage target.
Report the tests as "escritos, não executados" in the delivery report (see
`projeto-engineering-practices`). Following the phpcs/phpmd conventions while
writing code still applies.

