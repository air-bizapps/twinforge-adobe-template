---
name: adobe-magento-php-specialist
description: "PHP rules for Magento 2.4.8 code on PHP 8.3: what code generation forbids (final, readonly classes), why deprecations become exceptions, comparisons, functions the Magento2 standard rejects and their replacements, exceptions and translated messages, factories. Use when writing or reviewing any PHP class. Base PHP 8.3 rules: adobe-commerce-standards; project examples: bunge-backend-code-quality."
---

# Magento PHP specialist

Language-level rules for PHP inside Magento. `strict_types`, promoted `private readonly`
dependencies, serialized models and enums are in `adobe-commerce-standards`, "PHP 8.3"; overriding a
core class's properties and constructor is in its "Extending a Magento core class" sections. `$T` is
the `scripts/` folder of `adobe-magento-toolbox`.

## Rules

- **Target the PHP every environment runs.** The 2.4.8 framework accepts PHP 8.2 to 8.4. Use 8.3-only
  syntax (typed class constants, `#[\Override]`, `json_validate()`) only after confirming CI, local
  and cloud all run 8.3; never 8.4 syntax (property hooks, asymmetric visibility).
- **No `final` classes or methods, no `readonly class`.** Magento generates `Interceptor` and `Proxy`
  subclasses: `final` blocks them, and the Magento2 standard rejects it (`FinalImplementation`). A
  generated non-readonly child of a `readonly class` is a fatal. Use `private readonly` properties.
- **Deprecations break the request.** `app/bootstrap.php` sets `error_reporting(E_ALL)` and Magento's
  `ErrorHandler` throws an exception for every reported notice, warning and deprecation. Declare every
  property (dynamic properties are deprecated since 8.2) and cast nullable values before string
  functions (`trim((string)$value)`; `null` to a non-nullable built-in parameter is deprecated).
- **Compare strictly**: `===`, `!==`, `in_array($v, $list, true)`, `array_search(..., true)`.
  `strict_types` changes argument passing, not comparisons, and database values are strings.
- **Type every parameter, return and own property.** Return `?Type` or throw; don't return `false`
  for "not found".
- **Replace what the Magento2 standard rejects:**

  | Instead of | Use |
  |---|---|
  | `serialize()`/`unserialize()` | `Magento\Framework\Serialize\SerializerInterface` (`Serializer\Json`) |
  | `curl_*` | an HTTP client service (`adobe-create-http-client`) |
  | `file_get_contents()`, `fopen()` | `Magento\Framework\Filesystem` / `Filesystem\DriverInterface` |
  | `$_GET`, `$_POST`, `$_SERVER` | `Magento\Framework\App\RequestInterface` |
  | `exec()`, `shell_exec()` | `Magento\Framework\Shell` |
  | `md5()` for tokens, `mt_rand()` | `hash('sha256', ...)`, `random_int()` |
  | `new` on a class with dependencies | constructor injection, or its generated `Factory` |
  | `date()`/`time()` for store dates | `Magento\Framework\Stdlib\DateTime\TimezoneInterface` |

  Stored dates are UTC; `TimezoneInterface` converts to the store's timezone.
- **`new` is fine for value objects** without dependencies (exceptions, `\DateTimeImmutable`, DTOs
  you own). Data objects Magento hydrates come from their `Factory::create(['data' => [...]])`.
- **Exceptions**: throw `LocalizedException` subclasses (`NoSuchEntityException`, `InputException`,
  `CouldNotSaveException`) for errors a user or API client sees; catch the narrowest type; log with
  `Psr\Log\LoggerInterface` and a context array, then rethrow or return a defined result. REST
  status per exception: `adobe-commerce-standards`.
- **Translated messages use placeholders**: `__('Order %1 was not found.', $id)`, never string
  concatenation inside `__()`, so `i18n/<locale>.csv` can match the phrase.
- **Docblocks carry what types can't**: `@param ItemInterface[]`, `@throws`, and the full class names
  the Web API reads (`adobe-magento-backend-standards`). No prose that restates the signature.
- **`match` for value mapping** returns a value and throws `UnhandledMatchError` on a missing case;
  add a `default` arm when input comes from outside.
- **No named arguments on calls into Magento core or third-party code**: a parameter rename in an
  upgrade keeps the signature compatible but breaks the call.

## Checklist

- [ ] `declare(strict_types=1);`, typed signatures, no `ObjectManager` (`adobe-commerce-standards`).
- [ ] No `final`, no `readonly class`, no undeclared properties.
- [ ] Strict comparisons; values from DB, request and config cast before typed use.
- [ ] No function from the table above; dates through `TimezoneInterface`.
- [ ] Exceptions are `LocalizedException` subclasses with `__()` placeholders.
- [ ] Every Magento class or method used checked with `magento-api`.

## Verify

- `php -l <file>` for syntax.
- `vendor/bin/phpcs --standard=Magento2 <changed files>` for the Magento2 standard.
- `php "$T/magento-api.php" 'Magento\Module\Class'` before calling or extending a core class.
- `php "$T/magento-verify.php"` ends in `VERIFY OK` (`PHP-SYNTAX`, `PHP-LOAD`, `PHP-PARENT`,
  `PHP-OBJECTMANAGER`, `PHP-HARDCODED-URL`).

## Pitfalls

- `Exception: Deprecated Functionality: Creation of dynamic property` -> declare the property.
- `Deprecated Functionality: trim(): Passing null` -> cast or check for `null` first.
- `Class ...\Interceptor cannot extend final class` after adding a plugin -> remove `final`.
- `TypeError` returning a DB or config value from a typed method -> cast it
  (`adobe-commerce-standards`).
- Untranslated message in the storefront -> concatenation inside `__()`; use `%1`.

## Docs

- https://developer.adobe.com/commerce/php/coding-standards/php/
- https://developer.adobe.com/commerce/php/coding-standards/technical-guidelines/
- https://www.php-fig.org/psr/psr-12/
- https://experienceleague.adobe.com/en/docs/commerce-operations/installation-guide/system-requirements
