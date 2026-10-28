---
name: create-unit-tests
description: "Write PHPUnit unit tests for Magento 2 classes (Plugins, Observers, ViewModels, and any class with no `extends` or created via override/di.xml preference — Handlers/Commands/services) in Webjump/* modules, following a strict setUp/setMocks/setSubject structure, AAA pattern, and a mandatory mock-decision flow (createMock() first, a custom Mock class only when a method genuinely doesn't exist anywhere in the hierarchy). Out of scope, see section 1 (skip entirely): Controller, a Model extending an abstract entity-model base class, ResourceModel, Collection, Data Patch, DTO. Write the tests only: do NOT run phpunit or measure coverage (no coverage target in the agent flow), and never claim the tests pass. Use when asked to write, add, fix, or complete unit tests for a PHP class. Adapted from a sibling team's skill (FTD B2C)."
---

# Create Unit Tests

Adapted 2026-09-11 from `air-bizapps/ftd-b2c-agent-skills`'s `create-unit-tests` skill.
**Deliberate changes from the source:**

1. **No execution, no coverage target (decision of 2026-09-24).** In the Bunge agent flow the
   developer writes the tests and does not run `vendor/bin/phpunit` or any coverage check —
   running them was putting agents in retry loops. Execution happens later, outside the
   agent's subtask. The source's 100% target, and the earlier 80% one, no longer apply.
2. Coverage annotation is the **`@covers` docblock**, not the `#[CoversClass]` attribute —
   attribute-based coverage is a PHPUnit 10+ convention; PHPUnit 9.6 code should use the
   docblock form so it's unambiguous, not depend on a version-specific shim.
3. `getMockBuilder()`/`withConsecutive()`/`atLeastOnce()`/`atMost()` are **not removed** in
   PHPUnit 9.6 (they were removed starting in 10) — this skill recommends the modern
   alternatives as better practice, not as a hard "the code won't run" prohibition. Don't
   copy the source's "removed API" framing verbatim; it would be a false claim on this
   codebase's actual PHPUnit version.

Bunge runs PHPUnit 9.6.34 (`composer.lock`), not the PHPUnit 10-12 the source targets — that's
why items 2 and 3 above exist.

## Honesty rule (from BUN-21)

BUN-21 claimed "9/9 tests passing" when nothing had actually run. Since tests are no longer
executed in the agent flow, the rule is simpler: **never say or imply the tests pass.** Report
them as "testes escritos, não executados", with the list of test files and scenarios.

## 1. Scope contract (rewritten 2026-09-28 — read this before writing anything)

**Never write a unit test for:**

- **Controller** (`Controller/Adminhtml/*`, `Controller/*`) — thin, framework-glue
  `execute()` methods; the real logic they call into (a Command, a Repository, a
  ViewModel) is what gets tested, not the controller shell around it.
- **Model that extends an abstract entity-model base class** —
  `\Magento\Framework\Model\AbstractModel`, `AbstractExtensibleModel`, or similar
  (check the `extends` clause, not the folder). This is a Magento ORM entity
  object — mostly generated-feeling getters/setters over a `db_schema.xml` table,
  the same reason DTOs and `ResourceModel`/`Collection` below are excluded.
  Real local example: `Webjump\BungeListPrice\Model\ListPrice extends
  AbstractExtensibleModel implements ListPriceInterface` — skip it.
- `ResourceModel` (talks to the DB directly, not an isolable unit) and
  `Collection` (same).
- Data Patch (`Setup/Patch/Data/*` — runs once at install/upgrade, nothing
  repeatable to test).
- DTOs (`Model/Data/*`, `Api/Data/*` — plain getters/setters).
- An empty class (nothing of its own to validate).

Mark these `@codeCoverageIgnore` instead — even if explicitly asked to test them
anyway; explain why rather than complying silently.

**Do write a unit test for:** Plugin, Observer, ViewModel, and any class with
**no `extends`** at all — this is the dominant shape of real business logic in
this codebase (`Model/Command/*`, config providers, builders, gateways — see
`bunge-backend-code-quality`). Real local example:
`Webjump\BungeProductPrices\Model\CatalogPricesBuilder implements
ConfigProviderInterface` (no `extends`) — test it.

Also test a class that exists **because of an override** — either a PHP
`extends` of a vendor/core class (a preference or plugin target replacing/
extending Magento's own behavior) or a `di.xml` `<preference>` pointing an
`Interface` at your own concrete class. **Don't read "wired via `di.xml`" as
a reason to skip it** — `<preference for="X\Api\FooInterface"
type="X\Model\Foo"/>` is this codebase's single most common pattern (the
`Model/Command/*` architecture is built entirely on it — 217 references to
`RepositoryInterface`, per `bunge-backend-code-quality`), and it is exactly
the business logic this list means to include. The `CatalogPricesBuilder`
example above *is* wired this way
(`app/code/Webjump/BungeProductPrices/etc/di.xml:16`) and still gets tested —
`di.xml` there is just how the class is *reached*, not a signal about
whether it's *worth* testing. Being wired via `di.xml` only argues for
skipping a class when that class also matches one of the exclusions above
for an independent reason (e.g. it's a plain `ResourceModel`).

If a class doesn't clearly fit one side of this list (a `Model/` class you're
not sure is an entity model or a service, a class with an unusual base class),
say so explicitly in your report rather than guessing either way silently.

**Never change production code to make it more testable** — that's a separate,
explicitly-scoped task, not a side effect of writing tests.

## 2. Golden rules

1. **`createMock()` first, always.** Only write a custom `Test/Mock/{Name}Mock.php` class
   when the method genuinely doesn't exist anywhere in the real class hierarchy you can
   grep (section 3's five-step flow). A custom Mock created "just in case" is extra
   maintenance for zero benefit.
2. **`setMocks()`/`setSubject()` are called only by `setUp()`, in that order.** The subject
   is never re-instantiated inside an individual test method — a parallel instance
   diverges from what `setUp()` actually prepared.
3. **No test for `__construct`.** Instantiation already happens in `setSubject()`; if the
   constructor is broken, every other test already fails — a dedicated construct test adds
   nothing.
4. **No `ReflectionClass` to reach a private/protected member.** That tests implementation,
   not observable behavior, and breaks on every internal refactor. If private logic
   genuinely needs isolated testing, extract it to its own class with a public method.
5. **Don't run the tests, and don't claim results.** No `vendor/bin/phpunit`, no
   coverage command, no percentage in the report. Write them carefully instead: read the
   class, cover its branches, and list what you covered.

## 3. Mock decision flow — stop at the first match

| # | Check | How | If found |
|---|---|---|---|
| 1 | Method on the interface | `grep -n "public function methodName" <path>/Interface.php` | `createMock(Interface::class)`. Stop. |
| 2 | Method on the concrete class | `grep -n "public function methodName" <path>/ConcreteClass.php` | `createMock(ConcreteClass::class)`. Stop. |
| 3 | Method inherited from a parent (e.g. `AbstractModel`) | `grep -rn "public function methodName" vendor/magento/framework/Model/AbstractModel.php` | Mock the **concrete** class your code actually uses — `createMock(ConcreteClass::class)`, never the parent. PHPUnit mocks already include inherited methods. Stop. |
| 4 | Method exists only as `@method` in a docblock | `grep -n "@method.*methodName" <path>/ConcreteClass.php` | Magic method via `__call()` — PHPUnit can't mock it. Custom Mock class **required**. |
| 5 | Not found anywhere | — | Dynamic method (Extension Attribute, custom EAV attribute via `getData()`, Magento-generated code). Custom Mock class **required**. |

**Magento methods that always exist — never write a custom Mock for these:** `getData()`,
`setData()`, `getId()`, `setId()`, `hasData()`, `unsetData()`, `getEntityId()`, `save()`,
`delete()`, `load()`, `addData()`, `toArray()`.

## 4. Custom Mock class structure (only when step 3 requires one)

- Always `extends` the concrete class, never `implements` the interface alone — `extends`
  inherits real behavior for free; `implements` forces implementing every interface method
  and loses that inheritance.
- Location: `Test/Mock/{Name}Mock.php`, namespace `Webjump\{Module}\Test\Mock`.
- **Zero logic in every overridden method** — no conditional, no calculation, no delegation
  to another method, no `getData()` passthrough. Only a fixed return value per type:

| Return type | Value to return |
|---|---|
| Nullable (`?Type`) | `null` |
| `string` | `''` |
| `int` / `float` | `0` / `0.0` |
| `bool` | `false` (or `true` if the method name reads better that way) |
| `array` | `[]` |
| `self` / fluent setter | `return $this;` |
| `void` | empty method body |

A test-specific value is configured via `willReturn()` **in the test**, never hardcoded in
the Mock — the Mock is reused across tests; a hardcoded value couples it to one scenario.

```php
// Wrong — logic inside the Mock
class QuoteItemMock extends Item {
    public function getProductDigital(): ?string {
        return $this->getData('product_digital'); // logic, not a fixed value
    }
}

// Right — fixed value in the Mock, real value set per-test
class QuoteItemMock extends Item {
    public function getProductDigital(): ?string {
        return null;
    }
}
// in the test:
$this->quoteItemMock->expects($this->once())
    ->method('getProductDigital')->willReturn('digital-product-123');
```

## 5. Test class structure

- `Test/` mirrors the module structure exactly: `Model/Handler/X.php` →
  `Test/Unit/Model/Handler/XTest.php`.
- `declare(strict_types=1);` at the top of every test file.
- `@covers \Webjump\{Module}\{Path}\{ClassName}` docblock on the class — the docblock form,
  not the `#[CoversClass]` attribute (see the PHPUnit-9.6 note at the top).
- Properties declared in sequence with no blank line between them: the subject, then one
  `Dep|MockObject` per dependency.
- `setUp()` calls `setMocks()` then `setSubject()`, in that order, and is the **only**
  caller of either.
- `setMocks()` builds every mock the test class uses — nothing else.
- `setSubject()` instantiates `$this->subject` from those mocks — nothing else.
- Every test method: named `testMethodName` (or `testMethodNameWhenCondition` for a
  specific scenario), body structured as `/** Arrange */` → `/** Act */` → `/** Assert */`.

```php
declare(strict_types=1);

namespace Webjump\ModuleName\Test\Unit\Model\Handler;

use Webjump\ModuleName\Model\Handler\ExampleHandler;
use Magento\Framework\SomeDependency;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Webjump\ModuleName\Model\Handler\ExampleHandler
 */
class ExampleHandlerTest extends TestCase
{
    private ExampleHandler $subject;
    private SomeDependency|MockObject $dependencyMock;

    protected function setUp(): void
    {
        $this->setMocks();
        $this->setSubject();
    }

    public function setMocks(): void
    {
        $this->dependencyMock = $this->createMock(SomeDependency::class);
    }

    public function setSubject(): void
    {
        $this->subject = new ExampleHandler($this->dependencyMock);
    }

    /**
     * Tests the methodName method
     */
    public function testMethodName(): void
    {
        /** Arrange */
        $expectedValue = 'expected result';
        $this->dependencyMock->expects($this->once())
            ->method('someMethod')->willReturn($expectedValue);

        /** Act */
        $result = $this->subject->methodName();

        /** Assert */
        $this->assertEquals($expectedValue, $result);
    }
}
```

## 6. Scenarios and quantifiers

- Cover, for every public method: happy path, null/empty input or output, exceptions
  thrown, boundary values — every conditional branch, loop, and exception path identified
  when reading the class needs its own scenario.
- Prefer `$this->once()`, `$this->never()`, `$this->exactly(n)` for determinism.
  `atLeastOnce()`/`atMost(n)` still work on PHPUnit 9.6 but are looser — reach for them
  only when the exact call count is genuinely variable, not as a default.
- For multiple calls with different parameters, prefer `willReturnCallback()` with a
  closure mapping parameter → return value over `withConsecutive()` — clearer, and
  forward-compatible with a future PHPUnit upgrade:

```php
$this->addressMock->expects($this->exactly(3))->method('getData')
    ->willReturnCallback(function (string $key) use ($subtotal, $shipping, $discount) {
        return ['base_subtotal' => $subtotal, 'base_shipping_amount' => $shipping,
                'base_discount_amount' => $discount][$key] ?? null;
    });
```
- Use `#[DataProvider]`/`@dataProvider` only when multiple scenarios share the exact same
  Arrange/Act/Assert shape and only the input/output value changes — never to paper over
  scenarios whose Arrange genuinely differs.
- Every assertion validates the real captured result from `/** Act */` — never
  `assertTrue(true)` or a comparison of two identical literals.

**Do not run the tests.** In the task update, list each test file written and, per public
method, the scenarios covered — marked "testes escritos, não executados".

## 7. Definition of done

- [ ] Target class isn't Controller/Model-extends-AbstractModel/ResourceModel/Collection/
      Data Patch/DTO/empty (§1) — if it is, it got `@codeCoverageIgnore` and no `Test/Unit/`
      file was created
- [ ] If the class is wired via `di.xml` (a `<preference>` or an override), that alone was
      **not** treated as a reason to skip it (§1) — it was tested unless it also matches one
      of the exclusions above for an independent reason
- [ ] Every mock went through the 5-step decision flow before a custom Mock was written;
      no custom Mock created "just in case"
- [ ] Every custom Mock lives in `Test/Mock/{Name}Mock.php`, uses `extends`, contains zero
      logic
- [ ] `declare(strict_types=1);` present; `@covers` docblock present (not `#[CoversClass]`)
- [ ] `setUp()` calls `setMocks()` then `setSubject()`, and is the only caller of either;
      subject never re-instantiated inside a test
- [ ] No test written for `__construct`
- [ ] Every public method has at least one test; every branch/loop/exception/boundary value
      identified while reading the class is covered
- [ ] Every test follows Arrange/Act/Assert; every assertion checks a real captured result
- [ ] Report lists every test file and its scenarios, marked "testes escritos, não
      executados" — no run, no coverage number, no "tests pass"
- [ ] Any class left out of scope (step 0) is named explicitly in the report, with why

## Antipatterns

- Writing a test for `__construct`, or reaching private/protected members via
  `ReflectionClass`.
- Writing a custom Mock class before checking whether `createMock()` already covers it.
- Putting logic (a conditional, a calculation, a `getData()` passthrough) inside a custom
  Mock instead of a fixed return value.
- Calling `setMocks()`/`setSubject()` outside `setUp()`, or re-instantiating the subject
  inside a test.
- `assertTrue(true)` or any assertion that doesn't depend on the method's real result.
- Testing a class out of scope (Controller/Model-extends-AbstractModel/ResourceModel/
  Collection/Data Patch/DTO/empty) instead of marking `@codeCoverageIgnore`.
- Skipping a real service/Command class just because it's wired via `di.xml` — `di.xml` is
  how it's *reached*, not a reason to skip it (§1); a `<preference>` binding an interface to
  your own concrete class is this codebase's dominant pattern and squarely in scope.
- Running `vendor/bin/phpunit` or a coverage check inside the agent subtask, or retrying
  until something passes.
- Claiming the tests pass, or stating a coverage percentage.

