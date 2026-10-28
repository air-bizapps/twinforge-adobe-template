---
name: inject-validator
description: "Compose entity validation from small, single-purpose validator classes injected via DI, following the real Webjump\\AdminUser\\Model\\Validator\\PasswordValidatorChain pattern already in this codebase — a local {Feature}ValidatorInterface, a chain class that loops over injected validators, and an array of validators wired in di.xml. Use whenever a Command/Service/Handler must validate its inputs before acting. Adapted from a sibling team's skill (FTD B2C); descoped from their FTD\\Framework composite/ValidationResult contract to Bunge's own simpler, already-proven local pattern — no shared framework exists here, and none was needed to answer this."
---

# Inject Validator

Adapted 2026-09-11 from `air-bizapps/ftd-b2c-agent-skills`'s `inject-validator` skill.
**This closes the Tier 2 dependency question with a real answer, checked against the
actual Bunge codebase**: there is no shared `FTD\Framework`-equivalent module, but there
is already a real, working, per-feature composite-validator pattern —
`Webjump\AdminUser\Model\Validator\PasswordValidatorChain` (a local
`PasswordValidatorInterface`, a chain class that loops over an injected array of
validators, wired via `di.xml`'s `<argument name="validations" xsi:type="array">`). This
skill generalizes that real pattern rather than importing FTD's richer
`ValidationResult`/marker-interface/never-throw contract, which would mean building new
shared infrastructure Bunge doesn't have and doesn't need for this to work.

## 1. Scope contract

**Expected input:** the target consumer class (already existing — this skill never
bootstraps a module/class), the checks to enforce, and where the values validated live
(the entity's own getters vs. multiple sources).

**Produces:** a local `{Feature}ValidatorInterface`, one small validator class per
independent check, a chain class implementing that interface (loops over injected
validators), the `di.xml` array wiring, and the consumer edit that validates before any
side effect.

**Out of scope:** creating the target class or its module (→ `create-entity`/
`create-repository`/`create-controller`); unit tests (→ `create-unit-tests`).

## 2. Golden rules

1. **Reuse before writing.** Check the target module's own `Model/Validator/*` first — a
   new validator only when nothing existing covers the check.
2. **Every independent check is its own small class implementing the feature's
   `ValidatorInterface`** — never one method with several `if` branches.
3. **The chain class only loops and delegates** — see the real pattern below. It contains
   no validation logic of its own.
4. **New validators are added by adding one array item in `di.xml`**, never by editing
   the chain class itself.
5. **Never guess where a new validator's rule actually lives** (real business decision vs.
   generic data-format check) — that's a judgment call worth a quick confirmation when
   genuinely ambiguous.

## 3. The real pattern to follow

```php
// Webjump\{Module}\Api\{Feature}ValidatorInterface
interface {Feature}ValidatorInterface
{
    public function validate(\Magento\Framework\Validator\DataObject $validator): void;
}

// Webjump\{Module}\Model\Validator\{Feature}ValidatorChain — pure delegation
class {Feature}ValidatorChain implements {Feature}ValidatorInterface
{
    private array $validations;

    public function __construct(array $validations = [])
    {
        $this->validations = $validations;
    }

    public function validate(\Magento\Framework\Validator\DataObject $validator): void
    {
        foreach ($this->validations as $validation) {
            $validation->validate($validator);
        }
    }
}
```

```xml
<preference for="Webjump\{Module}\Api\{Feature}ValidatorInterface"
            type="Webjump\{Module}\Model\Validator\{Feature}ValidatorChain"/>

<type name="Webjump\{Module}\Model\Validator\{Feature}ValidatorChain">
    <arguments>
        <argument name="validations" xsi:type="array">
            <item name="0" xsi:type="object">Webjump\{Module}\Model\Validator\NotEmptyValidator</item>
            <item name="1" xsi:type="object">Webjump\{Module}\Model\Validator\SomeOtherValidator</item>
        </argument>
    </arguments>
</type>
```

This is a direct generalization of the real
`Webjump\AdminUser\Model\Validator\PasswordValidatorChain` +
`Webjump\AdminUser\etc\di.xml` wiring already in this codebase — read that module for a
complete worked example before writing a new one from scratch.

## 4. Consumer integration

Inject the feature's `{Feature}ValidatorInterface` into the consumer's constructor and
call `validate()` before any side effect (save, delete, external call). Each individual
validator class can throw directly (via `Zend_Validate`/`Magento\Framework\Validator`
rules, matching the real `NotEmptyValidator` example) rather than needing a separate
`ValidationResult` aggregation object — that richer contract is FTD's own addition, not
required for this pattern to work correctly.

## 5. Definition of done

- [ ] Existing validators in the target module checked before writing a new one
- [ ] Each independent check is its own small validator class, no `if`-branch soup
- [ ] Chain class contains only the loop — no validation logic of its own
- [ ] `di.xml` wires the array of validators; no validator hardcoded into the chain class
- [ ] Consumer validates before any side effect
- [ ] `setup:di:compile` succeeds; the validation actually exercised (a passing and a
      failing case)
- [ ] Report names `create-unit-tests` on the new validator(s)/chain as the next step

## Antipatterns

- Writing one validator method with several `if` branches instead of one class per check.
- Putting validation logic inside the chain class instead of a dedicated validator.
- Hardcoding a validator into the chain class's constructor call instead of the `di.xml`
  array.
- Reimplementing a check the target module's own `Model/Validator/*` already covers.
- Importing FTD's own `ValidationResult`/marker-interface contract as new shared
  infrastructure when the simpler, already-proven local pattern does the job.
