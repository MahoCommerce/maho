---
description: Maho's date and time API stores UTC in the database and converts to the store timezone at the edges, with locale helpers for safe input and display formatting.
---

# Date & Time Handling <span class="version-badge">v26.5+</span>

Maho provides a small, opinionated API for working with dates and times, built around one invariant: **the database always stores UTC**, and conversion to the store's timezone happens at the edges (when you receive input from a user, or when you display a value).

The API lives on `Mage_Core_Model_Locale` (accessed via `Mage::app()->getLocale()`), plus a locale-aware display helper on `Mage::helper('core')`. This page is the single reference for what each method does, when to use which, and how to migrate off the deprecated APIs.

## The mental model

Three rules, worth internalizing before you read the API:

1. **DB columns are always UTC**, formatted as `'Y-m-d H:i:s'` (or `'Y-m-d'` for date-only). Never store a store-local string in the DB - it's ambiguous across stores, and it breaks the invariant that the rest of the codebase relies on.
2. **Convert on the way in, and on the way out.** `storeToUtc()` for user input heading toward the DB; `utcToStore()` for DB values heading toward display or computation.
3. **Method names encode the timezone.** Reading `nowUtc()` or `utcToStore()` at a call site should be enough to tell you which timezone the resulting value is in. You shouldn't need to read a docblock.

Everything below follows from those three rules.

## The API at a glance

```php
$locale = Mage::app()->getLocale();

// DB-bound strings - formatDateForDb is the single entry point for DB columns
$locale->formatDateForDb('now');                               // 'Y-m-d H:i:s' (UTC) - current time for DB
$locale->formatDateForDb('now', withTime: false);              // 'Y-m-d' (UTC) - current date for DB
$locale->formatDateForDb($date);                               // normalize arbitrary input to 'Y-m-d H:i:s'
$locale->formatDateForDb($date, withTime: false);              // normalize arbitrary input to 'Y-m-d'

// Non-DB UTC strings - logs, CSV exports, API payloads, etc. (static methods)
Mage_Core_Model_Locale::nowUtc();                             // 'Y-m-d H:i:s' (UTC)
Mage_Core_Model_Locale::todayUtc();                           // 'Y-m-d' (UTC)

// Convert between timezones - always returns DateTimeImmutable
$storeDate = $locale->utcToStore($store, $utcInput);           // DateTimeImmutable in store TZ
$utcDate   = $locale->storeToUtc($store, $storeInput);         // DateTimeImmutable in UTC

// "Now" in the store's timezone - for computation or display
$locale->utcToStore();                                         // DateTimeImmutable in store TZ (current moment)
$locale->utcToStore()->format('Y-m-d');                        // today's date, in the store's timezone

// Locale-aware display formatting ("April 16, 2026" in en_US, "16 avril 2026" in fr_FR)
Mage::helper('core')->formatDate($storeDate, 'short', withTime: true);
```

That's the whole API. Five conversion/formatting methods on `Mage_Core_Model_Locale`, plus `Mage::helper('core')->formatDate()` for locale-aware display strings.

## Method reference

### `formatDateForDb($date, $withTime = true): ?string`

**Use this for anything headed to a DB column.** Normalizes an arbitrary input (string, int timestamp, `DateTime`, `DateTimeImmutable`, `'now'`) into a DB-format string.

```php
$locale->formatDateForDb('now');                               // '2026-04-18 14:32:05'
$locale->formatDateForDb('now', withTime: false);              // '2026-04-18'
$locale->formatDateForDb('2026-04-18 12:00:00');               // '2026-04-18 12:00:00'
$locale->formatDateForDb(1713443525);                          // '2026-04-18 12:32:05' (UTC)
$locale->formatDateForDb($someDateTime);                       // formats in the object's timezone
$locale->formatDateForDb(null);                                // null (empty passes through)
$locale->formatDateForDb('');                                  // null
```

Does not perform timezone conversion. Strings and integers are treated as UTC. For `DateTime` / `DateTimeImmutable` inputs, the method formats whatever timezone the object carries - so convert to UTC *first* if the input is store-local (see `storeToUtc()` below).

This method is the single choke point for DB-bound date formatting. Even though its output for `'now'` happens to equal `nowUtc()`'s output, the call site announces its intent: "this string is going to a DB column."

### `static nowUtc(): string`

Returns the current UTC date and time as a `'Y-m-d H:i:s'` string. Use for non-DB purposes - log lines, CSV exports, API payloads, anywhere you need a UTC string outside the ORM. This is a `static` method, so the idiomatic call is `Mage_Core_Model_Locale::nowUtc()` (calling it via an instance also works).

```php
Mage_Core_Model_Locale::nowUtc();                             // '2026-04-18 14:32:05'
```

For DB writes, use `formatDateForDb('now')` instead. The string output is identical (both use `gmdate()` under the hood), but the call site expresses different intent - and keeping `formatDateForDb()` as the single DB-bound entry point makes refactors and audits tractable.

### `static todayUtc(): string`

Returns today's UTC date as a `'Y-m-d'` string. Same rationale as `nowUtc()`: use for non-DB purposes; use `formatDateForDb('now', withTime: false)` for DB writes. Also a `static` method (`Mage_Core_Model_Locale::todayUtc()`).

```php
Mage_Core_Model_Locale::todayUtc();                           // '2026-04-18'
```

### `utcToStore($store = null, $date = null): DateTimeImmutable`

Converts a UTC date to the store's configured timezone. Always returns a `DateTimeImmutable`.

```php
// Convert a known UTC value to store TZ
$dt = $locale->utcToStore($store, '2026-04-18 12:00:00');

// "Now" in the store's timezone (for computation or display)
$dt = $locale->utcToStore();                                   // uses current store, current moment

// Format the result explicitly
$dt->format(Mage_Core_Model_Locale::DATETIME_FORMAT);          // 'Y-m-d H:i:s'
$dt->format(Mage_Core_Model_Locale::DATE_FORMAT);              // 'Y-m-d'
$dt->format(Mage_Core_Model_Locale::HTML5_DATETIME_FORMAT);    // 'Y-m-d\TH:i'
```

The `$store` parameter accepts anything `Mage::app()->getStore()` accepts: store ID, store code, `Mage_Core_Model_Store` instance, or `null` for the current store.

The `$date` parameter accepts a string, an int timestamp, a `DateTime`, a `DateTimeImmutable`, or `null` (= "now"). When given a `DateTime` / `DateTimeImmutable`, the method preserves the object's existing timezone as the *source* timezone - so make sure the object you're passing is actually in UTC.

### `storeToUtc($store = null, $date = null): DateTimeImmutable`

The inverse of `utcToStore()`. Converts a store-local date to UTC. Always returns a `DateTimeImmutable`.

```php
// User submits "2026-04-18 09:00:00" in the store's timezone - convert for the DB
$utcForDb = $locale->formatDateForDb(
    $locale->storeToUtc($store, '2026-04-18 09:00:00')
);

// Compute an expiration: "today + 30 days" in the store's calendar, stored in UTC
$expires = $locale->formatDateForDb(
    $locale->storeToUtc(null, '+30 days')
);
```

When the result is going to a DB column, wrap it in `formatDateForDb()` rather than calling `->format()` directly - that keeps `formatDateForDb()` as the single entry point for DB-bound date strings.

### `Mage::helper('core')->formatDate($date, $format, $withTime, $useTimezone): string`

Locale-aware display formatting. Uses `IntlDateFormatter` to produce human-readable output that respects the store's locale.

```php
$helper = Mage::helper('core');

$helper->formatDate('2026-04-18 14:30:00', 'long', withTime: true);
// en_US: 'April 18, 2026, 2:30:00 PM'
// fr_FR: '18 avril 2026 à 14:30:00'

$helper->formatDate($dateTime, 'short');                       // '4/18/26'
$helper->formatDate($dateTime, 'medium');                      // 'Apr 18, 2026'
$helper->formatDate($dateTime, 'full');                        // 'Saturday, April 18, 2026'
```

The helper accepts `string | int | DateTimeInterface | null`. With `$useTimezone = true` (default), string and int inputs are treated as UTC and converted to the store's timezone before formatting. For `DateTime`/`DateTimeImmutable` inputs, the helper formats the object in whatever timezone it already carries.

This is the method you want when the output is user-facing. For machine-readable output (DB strings, URL params, CSV columns, etc.), use `DateTimeImmutable::format()` directly.

## Common patterns

### Storing "now" in a DB column

```php
// ✅ Do
$object->setCreatedAt($locale->formatDateForDb('now'));
$object->setUpdatedAt($locale->formatDateForDb('now'));
```

### Storing a user-submitted date in a DB column

```php
// User submits a date in the store's timezone
$input = $this->getRequest()->getParam('start_date');          // '2026-04-18 09:00:00'

// ✅ Do - convert to UTC, then format through formatDateForDb
$utc = $locale->storeToUtc($store, $input);
$object->setStartDate($locale->formatDateForDb($utc));
```

The conversion and the DB-formatting are two separate concerns: `storeToUtc()` handles the timezone shift, `formatDateForDb()` produces the DB-bound string. Always route DB-bound writes through `formatDateForDb()`, even when you already have a `DateTimeImmutable` in hand - it keeps the method as the single choke point for anything going into a date column.

### Displaying a DB value to the user

```php
$createdAtUtc = $object->getCreatedAt();                       // '2026-04-18 14:30:00' (UTC)

// ✅ Do - locale-aware display
echo Mage::helper('core')->formatDate($createdAtUtc, 'medium', withTime: true);
// en_US: 'Apr 18, 2026, 10:30:00 AM' (if store is in America/New_York)

// ✅ Do - machine-readable, still timezone-converted
echo $locale->utcToStore(null, $createdAtUtc)
    ->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
// '2026-04-18 10:30:00'
```

### Computing "today" in the store's calendar

```php
// ✅ Do
$today = $locale->utcToStore()->format('Y-m-d');               // '2026-04-18' in store TZ
```

### Extending an expiration by N days

```php
// ✅ Do - convert + modify, then format through formatDateForDb
$expiresAt = $locale->storeToUtc()->modify("+{$days} days");
$object->setExpiresAt($locale->formatDateForDb($expiresAt));

// ❌ Don't - silent no-op on DateTimeImmutable
$expiration = $locale->storeToUtc();
$expiration->modify("+{$days} days");                          // return value discarded!
$object->setExpiresAt($locale->formatDateForDb($expiration));  // still "now"
```

Mutators on `DateTimeImmutable` return a **new instance** - they do not modify the original. Either chain directly (`->modify(...)`) or reassign (`$d = $d->modify(...)`). A bare `$d->modify(...)` with the return value discarded is a silent no-op.

### Writing to a log file or CSV export

```php
// ✅ Do - non-DB UTC string
$log->info("Job started at " . Mage_Core_Model_Locale::nowUtc());

// ✅ Do - for a CSV export column
$row[] = Mage_Core_Model_Locale::todayUtc();
```

## Common pitfalls

!!! warning "Don't use `nowUtc()` for DB inserts"
    Use `formatDateForDb('now')` instead. The string output is identical, but the call site expresses DB-binding intent, and keeps `formatDateForDb()` as the single entry point for DB-bound date formatting.

!!! warning "Don't pass `nowUtc()` to a store-local field"
    `nowUtc()` returns a UTC string. If you need "now" in the store's timezone, use `$locale->utcToStore()` and format from the `DateTimeImmutable`.

!!! warning "`utcToStore()` / `storeToUtc()` return `DateTimeImmutable`"
    Mutators like `->setTime()`, `->modify()`, `->add()`, `->sub()`, `->setDate()`, `->setTimezone()` return a **new instance** - they do not modify the original. Either chain directly (`->modify('+1 day')->format(...)`) or reassign (`$d = $d->modify('+1 day')`). A bare `$d->modify(...)` with the return value discarded is a silent no-op.

!!! warning "DateTime inputs preserve their own timezone"
    When you pass a `DateTime` or `DateTimeImmutable` into `utcToStore()` / `storeToUtc()`, the method uses the object's existing timezone as the *source* timezone - not UTC (for `utcToStore`) or the store TZ (for `storeToUtc`). If you construct a `DateTime` from an ambiguous string (`new DateTime('2026-04-18 12:00:00')`), it'll adopt PHP's default timezone, which may not be what you want. Pass strings or timestamps when precision matters, or construct DateTime objects with an explicit `DateTimeZone`.

!!! info "PHP's default timezone is UTC in Maho"
    Maho forces PHP's default timezone to UTC at bootstrap. `nowUtc()` and `todayUtc()` use `gmdate()` internally, so they're correct regardless. Most other code is also correct as a result - but be careful when constructing `new DateTime('...')` without an explicit timezone if the underlying server ever runs with a different default.

## Why there's no `nowStore()` / `todayStore()`

This is a deliberate omission. A store-local datetime *string* has no timezone tag attached, which means:

- Storing one in a DB column breaks the "DB is always UTC" invariant.
- In a multi-store setup, two stores with different timezones would write different instants into the same column under the same string.
- For computation or display you want a `DateTimeImmutable` anyway - `utcToStore()` with no args returns exactly that.

If you catch yourself wanting "now as a store-local string," step back and figure out what you actually need:

- **Going to the DB?** Use `formatDateForDb('now')` (it's UTC, which is what DB columns want).
- **Going to a log/CSV/API?** Use `nowUtc()`.
- **Going to the user?** Use `Mage::helper('core')->formatDate(...)` for locale-aware output, or `$locale->utcToStore()->format(...)` for machine-readable output.

No valid use case is missing from the API. The `nowStore()` idiom is always a bug in disguise.

## Deprecated methods (since v26.5)

The following methods still work but will emit deprecation notices. They will be removed in a future major release. Migrate to the replacements below.

| Deprecated | Replacement |
| --- | --- |
| `$locale->date($date, $part, $locale, $useTimezone)` | `$locale->utcToStore()` or `$locale->storeToUtc()` |
| `$locale->dateMutable(...)` | `$locale->utcToStore()` or `$locale->storeToUtc()` |
| `$locale->dateImmutable(...)` | `$locale->utcToStore()` or `$locale->storeToUtc()` (already returns `DateTimeImmutable`) |
| `$locale->storeDate($store, $date, $includeTime, $format)` | `$locale->utcToStore($store, $date)` and format the result (`->format(...)` for display, `$locale->formatDateForDb(...)` for DB writes) |
| `$locale->utcDate($store, $date, $includeTime, $format)` | `$locale->storeToUtc($store, $date)` and format the result (`$locale->formatDateForDb(...)` for DB writes) |
| `$locale->storeTimeStamp($store)` | `$locale->utcToStore($store)->getTimestamp()`, or just `time()` for the real Unix timestamp |
| `Mage_Core_Model_Resource_Abstract::formatDate($date, $withTime)` | `$locale->formatDateForDb($date, $withTime)` |
| `Mage_Core_Model_Resource_Db_Collection_Abstract::formatDate($date, $withTime)` | `$locale->formatDateForDb($date, $withTime)` |

The reasoning behind each deprecation:

- **`date()` / `dateMutable()` / `dateImmutable()`** conflated three concerns in one overloaded signature: parsing, timezone conversion, and format-string handling. The new methods separate them - `utcToStore` / `storeToUtc` do conversion, the caller does formatting.
- **`storeDate()` / `utcDate()`** had boolean flags that changed the return type between `DateTime` and `string` depending on the `$format` argument. Non-obvious at call sites. The replacement always returns `DateTimeImmutable`; the caller formats explicitly.
- **`storeTimeStamp()`** returned an int that looked like a Unix timestamp but was actually derived from the store's wall-clock time - a footgun in anything that assumed it was seconds-since-epoch.
- **`formatDate()` on resource classes** was a thin wrapper with a magic `true` value for "now." The replacement is more direct: `$locale->formatDateForDb('now')` or `$locale->formatDateForDb($input)`.

## Migration examples

### Storing "now" in a DB column

```php
// ❌ Old
$object->setCreatedAt($this->formatDate(true));
$object->setCreatedAt($resource->formatDate(true));
$object->setCreatedAt(Mage::getSingleton('core/date')->gmtDate());

// ✅ New
$object->setCreatedAt(Mage::app()->getLocale()->formatDateForDb('now'));
```

### Converting a UTC DB value for display

```php
// ❌ Old
$display = $locale->storeDate($store, $createdAt, true);       // returned DateTime or string (!)

// ✅ New - machine-readable
$display = $locale->utcToStore($store, $createdAt)
    ->format(Mage_Core_Model_Locale::DATETIME_FORMAT);

// ✅ New - locale-aware
$display = Mage::helper('core')->formatDate($createdAt, 'medium', withTime: true);
```

### Converting user input to UTC before saving

```php
// ❌ Old
$utc = $locale->utcDate($store, $userInput, true)
    ->toString(Mage_Core_Model_Locale::DATETIME_FORMAT);

// ✅ New - route DB-bound writes through formatDateForDb()
$utc = $locale->formatDateForDb(
    $locale->storeToUtc($store, $userInput)
);
```

### Getting a current store-timezone timestamp

```php
// ❌ Old
$ts = $locale->storeTimeStamp($store);                         // ambiguous - wall-clock or real epoch?

// ✅ New - real Unix timestamp of the current moment in store TZ
$ts = $locale->utcToStore($store)->getTimestamp();

// ✅ New - if you actually wanted seconds-since-epoch
$ts = time();
```

### Arithmetic on a DateTime returned from the conversion API

```php
// ❌ Old (when utcToStore returned mutable DateTime)
$start = $locale->utcToStore();
$start->setTime(0, 0, 0);                                      // used to mutate in place
$startStr = $start->format('Y-m-d H:i:s');

// ✅ New (utcToStore returns DateTimeImmutable - mutators return new instances)
$startStr = $locale->utcToStore()
    ->setTime(0, 0, 0)
    ->format('Y-m-d H:i:s');
```

## Breaking changes in v26.5

### `utcToStore()` / `storeToUtc()` return `DateTimeImmutable`

Previously these returned `DateTime`. If you're maintaining a third-party module that consumes the result:

- **Type declarations.** `function (DateTime $d)` no longer accepts the return value. Widen to `DateTimeInterface` to accept both.
- **Mutation without reassignment.** `$d->modify('+1 day')` on the return value is now a silent no-op. Chain (`$d = $d->modify(...)` or `...->modify(...)->format(...)`) or switch to a local mutable copy (`DateTime::createFromImmutable($d)`).

These are the only two patterns that break. If you've been treating the result as a read-only value object (calling `->format()`, `->getTimestamp()`, etc.), no changes are needed.

### `formatDateForDb()` no longer accepts a `bool` first argument

The pre-release signature accepted `true` as a shorthand for "current time." This was a compatibility shim during the migration and has been removed. Use `'now'` instead:

```php
// ❌ No longer works
$locale->formatDateForDb(true);

// ✅ Correct
$locale->formatDateForDb('now');
```

## Behind the scenes

### Why UTC-in-DB?

A timestamp with no timezone attached is ambiguous - `'2026-04-18 09:00:00'` refers to different instants in America/New_York vs. Europe/Paris. If your DB stores such strings without a timezone discipline, then:

- Reports and analytics across stores produce inconsistent results.
- Moving a store to a new timezone retroactively changes the meaning of historical data.
- Cross-store operations (shared catalogs, multi-warehouse inventory) silently corrupt time ordering.

Picking UTC as the canonical storage timezone eliminates all of that: `'2026-04-18 09:00:00'` in the DB always means the same instant, regardless of which store wrote it or which store reads it. The tradeoff is that display code has to convert - which is what `utcToStore()` is for.

### DST handling

`utcToStore()` and `storeToUtc()` use PHP's `DateTimeZone`, which handles DST transitions correctly. Some edge cases to be aware of:

- **Spring-forward gap.** 2:30 AM on the second Sunday of March doesn't exist in America/New_York. If you pass such a string, PHP picks the next valid instant. Usually fine for user-facing flows, but worth knowing when writing tests.
- **Fall-back overlap.** 1:30 AM on the first Sunday of November occurs twice in America/New_York. PHP picks the first occurrence (pre-DST, still EDT).
- **Half-hour offsets.** Timezones like Asia/Kolkata (UTC+5:30) and Asia/Kathmandu (UTC+5:45) work correctly - the conversion is minute-accurate.

The integration test suite covers all these edge cases; see `tests/Backend/Integration/Core/Model/LocaleDateIntegrationTest.php` for worked examples.

### Round-trip semantics

```php
$utc = $locale->utcToStore(null, '2026-04-18 12:00:00');       // UTC → store TZ
$back = $locale->storeToUtc(null, $utc);                       // store TZ → UTC

$back->format('Y-m-d H:i:s') === '2026-04-18 12:00:00';        // true
```

The conversion is lossless for all inputs except during the spring-forward gap (where the input instant doesn't exist in the store's local calendar). The test suite verifies round-trip equality across several timezones and seasons.
