---
description: Maho's async message queue built on Symfony Messenger - dispatch a message, handle it in a background worker with retries, backoff, delays, deduplication and an admin grid.
---

# Message Queue <span class="version-badge">v26.9+</span>

Maho ships a generic asynchronous job queue built on
[Symfony Messenger](https://symfony.com/doc/current/messenger.html){target=_blank}. You dispatch a plain
message object, a background worker picks it up and calls your handler. Failures are retried with
exponential backoff, everything is visible and actionable in the admin, and no extra infrastructure is
required: the default transport is your database, and cron keeps the worker alive for you.

Transactional emails already run through it, so the queue is exercised on every store.

## Quick start

Three pieces: a message, a handler, a dispatch call.

**1. The message** is a flat DTO. It carries data, not behaviour:

```php
final readonly class My_Module_Model_ImportRowMessage
{
    public function __construct(
        public int $productId,
        public string $sku,
    ) {}
}
```

**2. The handler** is any method carrying `#[Maho\Config\MessageHandler]`. The handled message class is
inferred from the first parameter type, and the declaring class is instantiated with `Mage::getSingleton()`
at consume time:

```php
class My_Module_Model_ImportHandler
{
    #[Maho\Config\MessageHandler]
    public function __invoke(My_Module_Model_ImportRowMessage $message): void
    {
        // do the slow work here
    }
}
```

```bash
composer dump-autoload   # required after adding, changing or removing the attribute
```

**3. Dispatch** from anywhere:

```php
\Maho\Queue\QueueManager::dispatch(new My_Module_Model_ImportRowMessage(42, 'ABC-123'));
```

That is the whole contract. The request returns immediately, and the worker running in the background
handles the message.

### Dispatch options

```php
QueueManager::dispatch(
    $message,
    delaySeconds: 300,          // earliest handling time
    queue: 'imports',           // logical queue name
    dedupeKey: 'import-42',     // collapse duplicates
    stamps: [],                 // extra Messenger stamps
);
```

| Argument | Default | Meaning |
|---|---|---|
| `$message` | required | The DTO to hand to the handler |
| `$delaySeconds` | `null` | Do not make the message available for at least this many seconds |
| `$queue` | `default` | Logical queue name. Decides which [worker pool](#worker-pools) consumes the message, and can be consumed in isolation with `queue:work --queue=<name>` |
| `$dedupeKey` | `null` | While a pending or processing message with the same key exists, dispatching is a no-op (DB transport) |
| `$stamps` | `[]` | Additional Messenger stamps, for advanced cases |

`dispatch()` returns the Messenger `Envelope` and throws a `RuntimeException` if the `Maho_Queue` module
is disabled.

### Handler attribute

```php
#[Maho\Config\MessageHandler]                                        // class inferred from the parameter
#[Maho\Config\MessageHandler(message: My_Module_Model_Foo::class)]   // explicit
#[Maho\Config\MessageHandler(priority: 10)]                          // higher runs first
```

Several handlers can subscribe to the same message class; they run in descending priority order.
Handlers belonging to disabled modules are ignored.

Attributes are compiled into `vendor/composer/maho_attributes.php`, so **run `composer dump-autoload`
after any change to a `#[MessageHandler]` attribute**, exactly like observers, cron jobs and routes.

## A complete example

Pushing every new order to an external ERP. The HTTP call must not slow down checkout, and it must
survive the ERP being down for an hour, which is exactly what the queue gives you.

Three files in a module (`app/code/local/My/Erp/`, declared as usual in `app/etc/modules/`).

**The message.** Identifiers only, no loaded models:

```php title="Model/OrderSyncMessage.php"
final readonly class My_Erp_Model_OrderSyncMessage
{
    public function __construct(
        public int $orderId,
        public string $incrementId,
    ) {}
}
```

**The observer** that dispatches it when an order is placed:

```php title="Model/Observer.php"
class My_Erp_Model_Observer
{
    #[Maho\Config\Observer('checkout_submit_all_after')]
    public function queueOrderSync(Maho\Event\Observer $observer): void
    {
        /** @var Mage_Sales_Model_Order $order */
        $order = $observer->getEvent()->getOrder();

        \Maho\Queue\QueueManager::dispatch(
            new My_Erp_Model_OrderSyncMessage((int) $order->getId(), $order->getIncrementId()),
            queue: 'erp',
            dedupeKey: 'erp-order-' . $order->getId(),
        );
    }
}
```

**The handler** that does the slow work:

```php title="Model/OrderSyncHandler.php"
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

class My_Erp_Model_OrderSyncHandler
{
    #[Maho\Config\MessageHandler]
    public function __invoke(My_Erp_Model_OrderSyncMessage $message): void
    {
        $order = Mage::getModel('sales/order')->load($message->orderId);
        if (!$order->getId()) {
            // The order is gone: retrying will never help.
            throw new UnrecoverableMessageHandlingException("Order {$message->orderId} no longer exists");
        }

        // A transport failure throws, and the queue retries it with backoff.
        HttpClient::create(['timeout' => 30])->request('POST', 'https://erp.example.com/orders', [
            'json' => [
                'reference' => $message->incrementId,
                'total' => (float) $order->getGrandTotal(),
                'email' => $order->getCustomerEmail(),
            ],
        ])->getContent();

        $order->addStatusHistoryComment('Synced to the ERP.')->save();
    }
}
```

Then compile the attributes and try it:

```bash
composer dump-autoload
./maho queue:list                          # the 'erp' queue now has a pending message
./maho queue:work --queue=erp --stop-when-empty   # run it now instead of waiting for the worker
```

Place an order (or dispatch the message by hand from `./maho shell`) and watch it move through
**System > Tools > Message Queue**. If the ERP is down, the message shows up as `pending` with a
growing retry count, and lands as `failed` with the HTTP error once the retries run out; fix the ERP
and hit **Retry**.

In production you do not run `queue:work` yourself: the [workers](#the-worker) started by cron are
already consuming every queue. Route `erp` to the `fast` pool if the sync must not wait behind bulk jobs,
see [worker pools](#worker-pools).

## What a message may contain

The stored body is the serialized **message object only**, never the envelope. On the way back in,
the serializer refuses to unserialize anything that is not a registered message class, allowing only
those classes plus `DateTimeImmutable` and `DateTimeZone`.

Practically:

- keep messages flat: scalars, arrays, `DateTimeImmutable`, `DateTimeZone`
- pass **identifiers, not models**: `public int $orderId`, not a loaded `Mage_Sales_Model_Order`
- treat the payload as immutable data; `readonly` classes are a good fit
- a body that can no longer be decoded (class removed, payload corrupted) is marked `failed` with the
  decoding error, so it surfaces in the admin instead of being retried forever

## Failures, retries and backoff

If a handler throws, the message is retried according to the configured policy: `Initial Retry Delay`
seconds, multiplied by `Retry Delay Multiplier` on each attempt, capped at `Max Retry Delay`. With the
defaults, retries happen after roughly 1 minute, 4 minutes and 16 minutes, then the message is marked
`failed` and the exception is written to `var/log/exception.log`.

To fail immediately with no retries, throw Messenger's unrecoverable exception:

```php
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

throw new UnrecoverableMessageHandlingException('Malformed recipient address');
```

Use it for input that will never become valid; let ordinary exceptions bubble for transient problems
(network timeouts, a mail server that is down) that a retry can fix.

Failed messages are never silently dropped. They stay in the table, visible in the admin grid, and can
be retried or discarded from there.

## The worker

Consumption is done by long-running worker processes. You normally never start them yourself: the
`queue_process` cron job runs every minute and acts as a watchdog. For every [worker pool](#worker-pools)
with no live worker, it spawns a detached `./maho queue:work --exclusive --pool=<name>`, logging to
`var/log/queue-worker.log`.

Consequences worth knowing:

- **A dead worker is back within a minute.** Each worker holds a machine-local kernel flock named
  `queue.worker.<pool>.<index>`. The lock disappears the instant the process dies and doubles as the
  liveness probe.
- **Each application server runs its own workers.** Parallel consumption is safe: rows are claimed with an
  atomic conditional update, so two workers never process the same message.
- **Workers recycle.** A resident worker stops after its pool's time limit (one hour by default) and memory
  limit, and the watchdog starts a fresh one. This is how newly deployed code gets picked up.
- **Configuration changes restart them.** A periodic checksum over `core_config_data` stops a worker when
  anything changes, so it never keeps running against stale settings (an old SMTP transport, for example).
- **Shutdown is graceful.** `SIGTERM`/`SIGINT` let the in-flight message finish before exiting.
- If PHP's `exec()` is disabled on the host, the watchdog cannot spawn anything and logs an error. Run
  `./maho queue:work --pool=<name>` for each pool under your own process supervisor instead.

### Worker pools

A pool is a group of `queue:work` processes that consume a subset of the logical queues with limits of
their own. Pools keep latency classes apart: a ten-minute feed build never sits in front of an order
confirmation email, because the two run in different processes.

Maho ships two pools:

| Pool | Queues | Behaviour | Defaults |
|---|---|---|---|
| `fast` | `email`, plus every queue routed to it | Stays resident and polls continuously | 256M memory, 3600s time limit |
| `slow` | Everything else (the catch-all) | On demand: started when its queues have work due, exits after 60 idle seconds | 512M memory, 3600s time limit |

Exactly one pool is the **catch-all**. It consumes every queue that no other pool claims, so a queue you
forget to route still drains. Core makes the *slow* pool the catch-all on purpose: an unrouted newcomer
is likelier to be a slow job than a latency-critical one, and the cost of forgetting is a message
waiting behind a feed rather than a checkout email stuck behind one.

**Route a queue to a pool** from your module's `config.xml`. One node per queue, so modules never clobber
each other:

```xml
<global>
    <queue>
        <routing>
            <erp>fast</erp>
        </routing>
    </queue>
</global>
```

An empty value (`<erp/>`) unroutes the queue and hands it back to the catch-all, which is how
`app/etc/local.xml` can retarget a single queue without touching the module.

**Declare a pool** under `<global><queue><pools>`:

```xml
<global>
    <queue>
        <routing>
            <feeds>bulk</feeds>
            <imports>bulk</imports>
        </routing>
        <pools>
            <bulk>
                <count>2</count>
                <idle_timeout>120</idle_timeout>
                <memory_limit>1G</memory_limit>
                <time_limit>7200</time_limit>
                <sort_order>15</sort_order>
            </bulk>
        </pools>
    </queue>
</global>
```

| Node | Default | Meaning |
|---|---|---|
| `count` | `1` | Workers the watchdog keeps for this pool. An on-demand pool starts one per due message, up to this number |
| `idle_timeout` | unset | Seconds with nothing to do before the worker exits. Unset keeps the worker resident; set it to make the pool on demand |
| `memory_limit` | `256M` | The worker stops once it uses more than this. An empty value removes the limit |
| `time_limit` | `3600` | The worker stops after this many seconds. `0` removes the limit |
| `catch_all` | `0` | Marks the pool that consumes every unrouted queue. Declare it on exactly one pool |
| `active` | `1` | Set to `0` to disable a pool, for example to retire the core `slow` pool from `local.xml` |
| `sort_order` | `0` | Order in which pools claim queues and in which the watchdog starts them |

Rules the registry enforces, with a line in `var/log/system.log` when one is broken:

- a pool with no queue routed to it and no `catch_all` flag is skipped, since it would consume everything
  and become a second catch-all
- a queue routed to an unknown pool falls to the catch-all
- a second `catch_all` pool is dropped
- with no `catch_all` at all, unrouted queues are never consumed, and `queue:list` flags them as `none`
- an invalid `memory_limit` falls back to `256M`
- if every declared pool is dropped, or none is declared, a single `default` pool consumes everything

On a multi-server install, due and busy counts are cluster-global while worker locks are machine-local,
so each server may start an on-demand worker for the same backlog. The excess is bounded by `count` and
drains through the idle timeout.

### CLI

```bash
./maho queue:work                             # consume all queues until stopped, no limits
./maho queue:work --pool=slow                 # consume as the slow pool, with its queues and limits
./maho queue:work --queue=email               # only one queue (repeatable)
./maho queue:work --exclude-queue=feeds       # every queue but this one (repeatable)
./maho queue:work --stop-when-empty           # drain and exit, handy in scripts
./maho queue:list                             # per-queue counts and the pool each queue belongs to
```

| `queue:work` option | Meaning |
|---|---|
| `--pool=NAME` | Consume as this pool: its queues, exclusions, idle timeout, memory and time limits become the defaults |
| `--index=N` | Which worker of the pool this process is, from `0` to `count - 1`. Used by the watchdog |
| `--queue=NAME` | Only consume these queues (repeatable). Overrides the pool's queue list and drops its exclusions |
| `--exclude-queue=NAME` | Never consume these queues (repeatable). Adds to the pool's own exclusions |
| `--limit=N` | Stop after handling N messages |
| `--time-limit=SECONDS` | Stop after this many seconds |
| `--memory-limit=256M` | Stop once memory usage exceeds this limit |
| `--sleep=SECONDS` | Seconds to sleep when the queue is empty (default 1) |
| `--idle-timeout=SECONDS` | Stop after this many seconds with nothing to do; `0` stops on the first empty poll |
| `--stop-when-empty` | Stop as soon as the queue is empty (same as `--idle-timeout=0`) |
| `--exclusive` | Hold the pool worker lock and refuse to start when another exclusive worker holds it (used by the watchdog). Cannot be combined with `--queue` or `--exclude-queue` |

A hand-run `queue:work` without `--pool` has no limits, exactly as before pools existed. A hand-run
`queue:work --exclusive` without `--pool` takes the bare `queue.worker` lock, which tells the watchdog
that one process covers every queue, so it stops spawning pool workers until that process exits.

`queue:list` prints pending, processing, failed and completed counts per queue, the pool that consumes
each queue, and the oldest pending message, which is the quickest way to spot a backlog. A queue shown
with pool `none` is never consumed: route it, or mark a pool `catch_all`.

## Admin

**System > Tools > Message Queue** lists messages with their queue, message class, status, retry count,
truncated error, availability and queue time. Opening a row shows the full error and the serialized body,
with **Retry** and **Discard** buttons; the grid offers both as mass actions.

Only `failed` messages can be retried, since flipping a claimed row would race the worker. Discarding
deletes the row permanently.

Three ACL resources under **System > Tools > Message Queue** let you split access: *View Messages*,
*Retry Messages* and *Discard Messages*.

## Configuration

**System > Configuration > Advanced > System > Message Queue**:

| Field | Config path | Default | Meaning |
|---|---|---|---|
| Max Retries | `system/queue/max_retries` | `3` | Retries before a message is marked failed |
| Initial Retry Delay (seconds) | `system/queue/retry_delay` | `60` | Wait before the first retry |
| Retry Delay Multiplier | `system/queue/retry_multiplier` | `4` | Each retry waits this many times longer |
| Max Retry Delay (seconds) | `system/queue/retry_max_delay` | `21600` | Upper bound for the backoff, `0` for none |
| Redeliver Stuck Messages After (seconds) | `system/queue/redeliver_after` | `3600` | Re-queue messages claimed by a worker that died. Keep it above the runtime of your slowest handler |
| Keep Completed Messages (days) | `system/queue/completed_retention` | `0` | `0` deletes on success, a positive value keeps rows visible in the grid |
| Keep Failed Messages (days) | `system/queue/failed_retention` | `30` | `0` keeps failed messages forever |

The `queue_clean_up` cron job runs at 02:00 daily and applies both retention settings.

Set `Keep Completed Messages` to a few days when you want an audit trail of what ran; leave it at `0` to
keep the table small.

## Storage and lifecycle

The default transport stores messages in `queue_message`, through Maho's DBAL adapter, so it works
identically on MySQL, PostgreSQL and SQLite.

```mermaid
flowchart LR
    A[dispatch] --> P[pending]
    P --> C[processing]
    C -->|handler returns| D[completed<br/>or row deleted]
    C -->|handler throws,<br/>retries left| P
    C -->|retries exhausted| F[failed]
    F -->|Retry from admin or CLI| P
    C -.->|worker died,<br/>claim went stale| P
```

- a worker claims the oldest available row with an atomic `UPDATE ... WHERE status = 'pending'`
- on success the row becomes `completed`, or is deleted outright when completed retention is `0`
- rows stuck in `processing` longer than `redeliver_after` are assumed to belong to a dead worker and
  are put back up for grabs
- **dispatching inside a database transaction participates in it**: the message becomes visible to
  workers only when the transaction commits, so a handler can never see an entity that was rolled back

## Redis transport

For high volume you can move pending messages to Redis. Install the bridge and point Maho at it in
`app/etc/local.xml`:

```bash
composer require symfony/redis-messenger
```

```xml
<global>
    <queue>
        <dsn>redis://localhost:6379/messages</dsn>
    </queue>
</global>
```

Everything else stays the same, with two differences: pending messages live in Redis and therefore do not
appear in the admin grid (which says so with a notice), and **final failures are still written to the
database table**, so failure inspection, retry and discard keep working exactly as before.

If the DSN is set but the bridge package is missing, Maho fails loudly rather than silently falling back
to the database.

## Emails on the queue

Transactional emails are queued messages: `Mage_Core_Model_Email_SendMessage` handled by
`Mage_Core_Model_Email_SendMessageHandler`, on the `email` queue. So they get retries, backoff and the
same admin grid as everything else, and malformed recipient addresses fail immediately instead of being
retried.

- `Mage_Core_Model_Email_Queue::addMessageToQueue()` still works as a shim; new code should dispatch
  `Mage_Core_Model_Email_SendMessage` through `QueueManager::dispatch()` directly
- the old "force check" duplicate guard is now a dedupe key
- in [developer mode](guide/models-and-orm.md#enable-developer-mode) emails are sent synchronously, so
  errors surface immediately and you do not need a worker locally
- `./maho email:queue:process` drains the `email` queue once, `./maho email:queue:clear` removes email
  messages by status and age
- upgrading migrates any unsent `core_email_queue` rows onto the new queue automatically

## Testing

`QueueManager::reset()` drops every memoised service (bus, transport, serializer, handler registry).
Call it between tests, or after changing configuration that the queue reads, to force a clean rebuild.

To assert on what a piece of code queued, dispatch it and read `queue_message` through
`Mage::getModel('queue/message')->getCollection()`; to run the work inline, build a bounded worker with
`\Maho\Queue\WorkerFactory::create(['stopWhenIdle' => true])`.
