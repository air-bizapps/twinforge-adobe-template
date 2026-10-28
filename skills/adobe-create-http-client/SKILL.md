---
name: create-http-client
description: "Scaffold an outbound HTTP client for a Webjump/* module — a plain PHP class wrapping Magento\\Framework\\HTTP\\Client\\Curl, config-driven base URL/auth via the module's own Config provider (create-config's pattern), retry/backoff on transient failures, and no secret ever hardcoded or logged. Follows the real Webjump\\BungeProposalOffer\\Model\\Gateway\\{ProposalClient,AuthClient} pattern already in this codebase. Use for any outbound integration — payment gateways (Braspag/Cielo), SAP feeds, third-party REST APIs. Adapted from a sibling team's skill (FTD B2C); their FTD\\Framework OAuth2/ClientBuilder/RequestInterface abstraction replaced with Bunge's own simpler, already-proven direct-class pattern."
---

# Create HTTP Client

Adapted 2026-09-11 from `air-bizapps/ftd-b2c-agent-skills`'s `create-http-client` skill.
**Closes the Tier 2 dependency question with a real answer**: Bunge has no
`FTD\Framework`-equivalent virtualType-composed HTTP transport layer (`ClientBuilder`,
`RequestInterface`, generic OAuth2 chain) — but it already has a real, working, simpler
pattern for exactly this: `Webjump\BungeProposalOffer\Model\Gateway\{ProposalClient,
AuthClient}`, a plain class wrapping `Magento\Framework\HTTP\Client\Curl`, driven by the
module's own `ConfigInterface`, logging via `Psr\Log\LoggerInterface`. This skill
generalizes that real pattern rather than building FTD's richer abstraction from scratch.

## 1. Scope contract

**Expected input:** target module, the consumer/purpose, base URL (fixed vs. per-request),
authentication mode (OAuth2 client-credentials / static Bearer or API-key / none), and
whether config is admin-managed (`system.xml`) or fixed — all confirmed, never guessed.

**Produces:** a client class wrapping `Curl`, injected `ConfigInterface` + `LoggerInterface`,
a separate `AuthClient` class when the integration needs a fetched token (OAuth2), and — if
admin-managed — the `system.xml` fields via `create-config`'s pattern.

**Out of scope:** the business logic that calls the client (the consumer, already existing
or scaffolded elsewhere); unit tests (→ `create-unit-tests`).

## 2. Golden rules

1. **No raw Guzzle/curl in business logic.** Always `Magento\Framework\HTTP\Client\Curl`,
   wrapped in a dedicated client class — never instantiated ad hoc inside a Command/Service.
2. **Configuration is scope-aware and never hardcoded.** Base URL, credentials, and
   timeout come from the module's `ConfigInterface` (per `create-config`'s pattern), never
   literals in the client class.
3. **Transient failures are retried with backoff, not silently swallowed or immediately
   fatal.** A bounded number of retries on timeout/5xx, then a clear exception.
4. **No secret is ever hardcoded or logged.** Credentials come only from `ConfigInterface`;
   any logged request/response detail excludes the raw auth header/token/key value.
5. **OAuth2/token-fetching logic lives in its own class** (an `AuthClient`), never inlined
   into the main client — matching the real `ProposalClient`/`AuthClient` split already in
   this codebase.

## 3. The real pattern to follow

```php
namespace Webjump\{Module}\Model\Gateway;

use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;
use Webjump\{Module}\Api\ConfigInterface;

class {Name}Client
{
    public function __construct(
        private readonly Curl $curl,
        private readonly ConfigInterface $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function fetch(/* ... */): array
    {
        $url     = $this->config->getEndpoint();
        $timeout = $this->config->getTimeout();
        $this->curl->setTimeout($timeout);
        $this->curl->addHeader('Authorization', 'Bearer ' . $this->getToken());

        $this->curl->get($url);

        $statusCode = (int) $this->curl->getStatus();
        if ($statusCode >= 500 || $statusCode === 0) {
            // transient — retry with backoff up to a bounded limit, then throw
        }
        if ($statusCode !== 200) {
            $this->logger->error('[{Module}][API] Request failed.', [
                'status' => $statusCode, 'url' => $url,
                // never log the Authorization header value itself
            ]);
            throw new \RuntimeException('...');
        }

        return json_decode($this->curl->getBody(), true) ?? [];
    }
}
```

This mirrors `Webjump\BungeProposalOffer\Model\Gateway\ProposalClient` exactly — read that
module (and its sibling `AuthClient` for the OAuth2 token-fetch split) as the worked
example before writing a new integration from scratch.

## 4. Retry/backoff

Retry only on genuinely transient conditions (timeout, connection reset, 5xx) — never on
4xx (a client error retrying won't fix itself). Bound the retry count (e.g. 3 attempts)
with increasing backoff between attempts, and raise a clear, catchable exception once
retries are exhausted rather than returning a silent empty/null result.

## 5. Definition of done

- [ ] No raw Guzzle/curl instantiated in business logic — only the dedicated client class
- [ ] Base URL, credentials, timeout all sourced from `ConfigInterface`, none hardcoded
- [ ] Transient failures retried with a bounded count and backoff; non-transient failures
      raise a clear exception immediately
- [ ] No secret logged — checked the log calls for the raw auth header/token value
- [ ] OAuth2 token-fetching (if needed) lives in its own `AuthClient` class, not inlined
- [ ] `setup:di:compile` succeeds; a real (or sandboxed) call exercised and confirmed
      working, including a forced-failure path if feasible
- [ ] Report names `create-unit-tests` on the new client(s) as the next step

## Antipatterns

- Instantiating Guzzle/curl directly inside a Command/Service instead of a dedicated
  client class.
- Hardcoding a base URL, API key, or timeout instead of reading it from `ConfigInterface`.
- Retrying a 4xx response as if it were transient.
- Swallowing a failed request into a silent empty return instead of raising an exception
  once retries are exhausted.
- Logging the full `Authorization` header or token value for "debugging."
- Inlining OAuth2 token-fetch logic into the main client instead of a separate
  `AuthClient`.
