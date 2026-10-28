---
name: create-queue
description: "Implement a RabbitMQ/AMQP message queue in a Webjump/* module — the four queue config files (communication/topology/publisher/consumer XML), Publisher and Consumer classes with correct native-retry handling, an optional message DTO, and di.xml/module.xml wiring. Use when adding asynchronous message processing — offloading work to a queue, integrating a webhook, publishing/consuming a topic. Adapted from a sibling team's skill (FTD B2C); the retry-classification and logger pieces are re-based on vanilla Magento since Bunge has no equivalent shared framework."
---

# Create Queue

Adapted 2026-09-11 from `air-bizapps/ftd-b2c-agent-skills`'s `create-queue` skill. One
deliberate change: the source classifies retryable exceptions via its own
`FTD\Framework\IsExceptionRetryableInterface` and injects a feature-flag logger from the
same framework. Bunge has no confirmed equivalent, so this version classifies directly in
the Consumer's own `catch` block and injects a plain `Psr\Log\LoggerInterface` — the core
Magento retry mechanism itself (rethrow `ConnectionLostException` to trigger native
requeue) is unchanged, since that part is vanilla Magento, not FTD-specific.

## 1. Scope contract

**Expected input, all confirmed, never guessed:** target module, a PascalCase `{Name}`
for the work, the dot-separated topic name (`webjump.{module}.{action}`), the message
shape (typed DTO vs. serialized string), the service/command the consumer delegates to,
and the retry behavior (native requeue vs. explicitly-confirmed fire-and-forget).

**Produces:** the four `etc/queue_*.xml`/`communication.xml` config files, Consumer and
Publisher interface+class pairs, an optional message DTO, and the `di.xml`/`module.xml`
wiring.

**Out of scope:** the business logic the consumer delegates to (an existing or separately
scaffolded service/command — never inlined here); unit tests (→ `create-unit-tests`, since
Consumer/Publisher carry real logic and need coverage); operational allowlist changes to
`app/etc/env.php` (report the need, let the requester/infra decide — see section 3).

## 2. Golden rules

1. **The Consumer's `catch` never manually republishes the message to simulate retry.**
   Retry is always native — rethrow `\Magento\Framework\MessageQueue\ConnectionLostException`
   for a transient/retryable failure — except for an explicitly-confirmed fire-and-forget
   consumer.
2. **No file declares an exchange/connection other than `magento`/`amqp`.** That's the
   only broker actually provisioned; anything else fails at runtime, not at review time.
3. **`app/etc/env.php` is never edited without explicit confirmation.** Flag that the new
   consumer needs adding to the `cron_consumers_runner` allowlist; don't apply that edit
   unilaterally — it's shared environment configuration, not module code.
4. **The topic is defined exactly once, as `TOPIC_NAME` on the Publisher interface** —
   never hardcoded in a second place.
5. **`queue_consumer.xml`'s `handler` and any `di.xml` preference always point at the
   interface, never the concrete class.**

## 3. Infrastructure safety (hard rules)

Queue config and `env.php` are shared infrastructure across every module — a mistake here
doesn't just break the new queue, it can silently break what's already running.

| Rule | Why | Instead |
|---|---|---|
| Never declare `exchange` other than `magento`, or `connection` other than `amqp` | Only RabbitMQ is provisioned; another connection type doesn't connect, or targets an unconfigured broker — the failure only shows up at runtime | Always `exchange="magento"` and `connection="amqp"` |
| Never edit `app/etc/env.php` directly (e.g. adding the new consumer to `cron_consumers_runner.consumers`) | Versioned environment configuration, not module code — an extra edit here can drop other modules' consumers from the allowlist or diverge between environments | Report that the new consumer needs adding to the allowlist; leave the actual edit to the requester/infra owner |

Verify before delivering:
```bash
grep -n 'connection="' app/code/Webjump/{Module}/etc/queue_*.xml | grep -v 'connection="amqp"'
grep -n 'exchange name="' app/code/Webjump/{Module}/etc/queue_topology.xml | grep -v 'name="magento"'
```
Both must return empty.

## 4. Native retry integrity (hard rules)

The Consumer is the only place a failure decides whether a message is reprocessed,
dropped, or lost. Getting this wrong isn't a small bug — it's duplicate reprocessing,
messages stuck looping, or silent loss in production, none of which a simple unit test
catches.

| Rule | Why | Instead |
|---|---|---|
| Never manually republish the message inside the Consumer's `catch` to "try again" | Creates duplicate republishing that doesn't integrate with the broker's native retry — the original message can still be ack'd and reprocessed in parallel with the republished copy | Rethrow `\Magento\Framework\MessageQueue\ConnectionLostException` — the broker re-delivers the original message without duplicating it |
| Never swallow the exception (log without rethrow) in a consumer not deliberately built as fire-and-forget | The message is lost with no trace and no reprocessing path — indistinguishable from success in the queue's own logs | Log with the injected logger's `critical()`, then either rethrow `ConnectionLostException` for native retry, or (if fire-and-forget) make that an explicit, confirmed choice |
| Never make a consumer fire-and-forget (log-and-drop) without the requester explicitly confirming that behavior | Fire-and-forget is the exception, not the default — silently dropping messages on an unrequested assumption is a reliability regression | Confirm the desired retry behavior before writing the `catch` block |

Verify before delivering:
```bash
grep -n "publish(" -A2 -B10 app/code/Webjump/{Module}/Model/Queue/{Name}Consumer.php | grep -B10 "catch"
```
Any `publish()` call inside a `catch` block → don't deliver; fix it and report the change.

## 5. Definition of done

- [ ] Module, `{Name}`, topic name, message shape, delegated service, and retry behavior
      all explicitly confirmed — none guessed
- [ ] `exchange="magento"` / `connection="amqp"` everywhere; no other value anywhere
- [ ] No manual republish inside `catch`; native retry via `ConnectionLostException`
      unless fire-and-forget was explicitly confirmed
- [ ] Topic name defined once, as `TOPIC_NAME` on the Publisher interface — no duplicate
      hardcoded string
- [ ] `queue_consumer.xml`'s `handler` and any `di.xml` preference point at the
      interface, never the concrete class
- [ ] No edit made to `app/etc/env.php` — the allowlist need is reported instead
- [ ] `setup:upgrade` + `setup:di:compile` + `cache:flush` succeed; `queue:consumers:list`
      shows the new consumer; a manual message was published and consumed successfully
- [ ] Report names `create-unit-tests` on the Consumer/Publisher as the next step

## Antipatterns

- Manually republishing a message inside the Consumer's `catch` to simulate retry — the
  native mechanism via `ConnectionLostException` already exists for this.
- Declaring an exchange/connection other than `magento`/`amqp` "just for this module."
- Editing `app/etc/env.php` (the `consumers_runner` allowlist) without confirming first.
- Hardcoding the topic string in more than one place instead of referencing
  `Publisher::TOPIC_NAME`.
- Pointing `di.xml`'s preference/`handler` at the Consumer's concrete class instead of
  its interface.
- Putting real business logic directly inside `process()` instead of delegating to an
  injected service/command.
