---
name: create-repository
description: "Implement the Magento 2 Repository Pattern for an entity in a Webjump/* module — Search Results contract, Get/GetList/Save/Delete Commands, Repository orchestrator (pure delegation), di.xml wiring, and mandatory webapi.xml/acl.xml exposure. Use as the follow-up to create-entity, once the entity's DTO/ResourceModel/Collection/db_schema already exist. Adapted from a sibling team's skill (FTD B2C, same Adobe Commerce version); ACL nesting adapted since Bunge has no shared-framework config root."
---

# Create Repository

Adapted 2026-09-11 from `air-bizapps/ftd-b2c-agent-skills`'s `create-repository` skill.
One deliberate change: the source nests every new ACL resource under its own
`FTD_Framework::config` shared-framework root. Bunge has not confirmed an equivalent
shared config root, so this version nests under **the module's own base ACL resource**
(the one `create-controller` already declares for the module) instead — everything else
applies as-is.

## 1. Scope contract

**Expected input:** target module, the entity's PascalCase name (matching its existing
DTO), the primary-key accessor names, and the Web API route base — all confirmed, never
guessed.

**Produces:** the Search Results contract, the four Commands (Get/GetList/Save/Delete),
the Repository orchestrator, `etc/di.xml` preferences, and `etc/webapi.xml` + `etc/acl.xml`.

**Out of scope:** the entity's DTO/`ResourceModel`/Collection/`db_schema.xml` (→
`create-entity` — stop and point there if missing, never fabricate the foundation);
business rules or validation on Save/Delete beyond the persistence-boundary try/catch
(confirm with the requester whether that's follow-up work via `inject-validator`, once its
Tier-2 dependency question resolves); controllers/routes/admin UI (→ `create-controller`/
`adobe-magento-ui-component-developer`); unit tests (→ `create-unit-tests`, as a follow-up).

## 2. Golden rules

1. **The Repository has zero business logic — pure delegation to Commands.** Any
   conditional there is a sign the logic is in the wrong place.
2. **Commands are internal — never injected outside the Repository.** A controller,
   plugin, observer, or any other class depends only on `*RepositoryInterface`.
3. **`GetList` always goes through `CollectionProcessorInterface`.** Returning unfiltered
   collection items silently ignores `SearchCriteria`.
4. **Never scaffold a repository over an entity with no persistence foundation.** DTO/
   ResourceModel/Collection/schema already existing is a precondition — that's
   `create-entity`'s job, not this skill's.
5. **Every new repository exposes Web API + ACL — not optional.** ACL nests under the
   module's own base ACL resource (never a shared-framework root Bunge doesn't have);
   never mark a route `anonymous` without an explicit request.
6. **Never invent an unrequested business rule in Save/Delete.** Confirm with the
   requester before going beyond the persistence-boundary try/catch.

## 3. Architecture invariants (hard rules)

| Rule | Why | Instead |
|---|---|---|
| No business logic (conditional, loop, calculation) inside `{Entity}Repository` | The Repository is the only entry point external consumers know; logic there can't be tested in isolation from the Command and becomes permanent coupling between orchestration and business rule | Move the logic into an existing or new Command; the Repository only calls `execute()` |
| Never inject a Command (`GetInterface`, `SaveInterface`, etc.) directly into a Controller, Plugin, Observer, or anything outside the Repository itself | Once another class depends directly on the Command, the Repository loses the ability to swap the implementation without breaking that consumer | External consumers always depend on `{Entity}RepositoryInterface`, never the internal Commands |
| In `GetList`, never return `$collection->getItems()` without first calling `$this->processor->process($searchCriteria, $collection)` | Silently discards every filter/sort/pagination the caller passed via `SearchCriteria` — the bug only shows up in production when someone filters the list and gets everything back | Always inject `CollectionProcessorInterface` and call `process()` before reading items |

```php
// Wrong — business logic inside the Repository
public function save({Entity}Interface $entity): {Entity}Interface {
    if ($entity->getStatus() === 'active') {
        $entity->calculateTotals();
    }
    return $this->save->execute($entity);
}

// Right — pure delegation
public function save({Entity}Interface $entity): {Entity}Interface {
    return $this->save->execute($entity);
}
```

Verify before delivering:
```bash
# Repository should have no control structures beyond the constructor
grep -nE '\b(if|for|foreach|while)\s*\(' app/code/Webjump/{Module}/Model/Service/{Entity}Repository.php
# GetList must call the processor before reading items
grep -n 'processor->process' app/code/Webjump/{Module}/Model/Command/{Entity}/GetList.php
```

## 4. Foundation check and API exposure (hard rules)

Before writing anything, confirm the entity's foundation exists:
```bash
ls app/code/Webjump/{Module}/Api/Data/{Entity}Interface.php \
   app/code/Webjump/{Module}/Model/Data/{Entity}.php \
   app/code/Webjump/{Module}/Model/ResourceModel/{Entity}.php \
   app/code/Webjump/{Module}/Model/ResourceModel/{Entity}/Collection.php \
   app/code/Webjump/{Module}/etc/db_schema.xml
```
Missing any → stop, point to `create-entity`, never fabricate the foundation.

Every new repository gets Web API + ACL exposure — not an optional step, even if the
request doesn't mention an API consumer. Four routes (get, getList, save, deleteById) and
three ACL resources (view/save/delete), nested under the module's own base ACL resource.
Never mark a route `resources="anonymous"` unless explicitly requested and named in the
report.

Verify before delivering:
```bash
grep -c '<route ' app/code/Webjump/{Module}/etc/webapi.xml   # expect: 4
grep -n 'anonymous' app/code/Webjump/{Module}/etc/webapi.xml  # expect: nothing, unless explicitly requested
```

## 5. Definition of done

- [ ] Entity foundation confirmed present (section 4) — never scaffolded on top of a
      missing one
- [ ] Repository contains zero control-flow logic — pure delegation
- [ ] No Command imported/injected anywhere outside `Model/Service/{Entity}Repository.php`
      or `etc/di.xml`
- [ ] `GetList` calls `CollectionProcessorInterface::process()` before reading items
- [ ] `etc/webapi.xml` has all four routes; `etc/acl.xml` nests under the module's own
      base ACL resource; no route is `anonymous` unless explicitly requested
- [ ] No unrequested business rule/validation added to Save/Delete
- [ ] `setup:di:compile` succeeds; routes confirmed reachable in the browser/API client
- [ ] Report names `create-unit-tests` as the natural next step

## Antipatterns

- Putting any conditional or business logic inside the Repository instead of a Command.
- Injecting a Command directly into a Controller/Plugin/Observer instead of depending on
  `*RepositoryInterface`.
- Returning collection items in `GetList` without running them through
  `CollectionProcessorInterface` first.
- Scaffolding a repository over an entity with no DTO/ResourceModel/Collection — point to
  `create-entity` instead of inventing the foundation.
- Skipping Web API + ACL exposure "because there's no external consumer yet."
- Marking a route `anonymous` without an explicit request.
- Inventing validation/business-rule logic in Save/Delete without confirming first.
