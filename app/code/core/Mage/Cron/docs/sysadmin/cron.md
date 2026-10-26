---
description: Define and run Maho cron jobs for scheduled background tasks like queued emails, sitemap generation and catalog price rules using PHP attributes.
---

# Cron jobs

Maho uses a cron system to run scheduled background tasks - things like sending queued emails, cleaning up old logs, generating sitemaps, and applying catalog price rules.

## Defining cron jobs

!!! info "v26.5+"
    Since v26.5, cron jobs are defined using PHP attributes directly on the method. Previously they were configured in XML - see [Migrating from XML](#migrating-from-xml) if you're upgrading an existing module.

Place the `#[Maho\Config\CronJob]` attribute on a public method to register it as a cron job:

```php
class Maho_AdminActivityLog_Model_Observer
{
    #[Maho\Config\CronJob('adminactivitylog_clean_old_logs', schedule: '0 2 * * *')]
    public function cleanOldLogs(): void
    {
        // Runs daily at 2:00 AM
    }
}
```

After adding or changing any cron job attribute, run:

```bash
composer dump-autoload
```

This compiles all attributes into `vendor/composer/maho_attributes.php`, which is what the runtime reads.

### Parameters

| Parameter    | Type     | Default      | Description |
|--------------|----------|--------------|-------------|
| `id`         | `string` | *(required)* | Job identifier (e.g. `'sitemap_generate'`, `'core_clean_cache'`) |
| `schedule`   | `?string`| `null`       | Cron expression (e.g. `'0 2 * * *'`, `'*/5 * * * *'`) |
| `configPath` | `?string`| `null`       | Config path for admin-configurable schedule |

### Fixed schedule

Use the `schedule` parameter with a standard cron expression:

```php
#[Maho\Config\CronJob('giftcard_process_scheduled_emails', schedule: '*/5 * * * *')]
public function processScheduledEmails(): void { }
```

### Admin-configurable schedule

Use `configPath` instead of `schedule` when the cron expression should be configurable from the admin panel:

```php
#[Maho\Config\CronJob('customersegmentation_refresh_segments', configPath: 'customer/customer_segments/cron_schedule')]
public function refreshSegments(): void { }
```

!!! note
    Provide either `schedule` or `configPath`, not both. If neither is set, the job can only be triggered manually via `./maho cron:run <job_id>`.

Individual cron job failures are caught and recorded without stopping the rest of the cron run.

## Deploy to production

When you deploy a Maho project in production, you need to set up cron this way:

```cron
*/5 * * * * cd /var/www/mahoproject; php ./maho cron:run default >/dev/null 2>&1
*/5 * * * * cd /var/www/mahoproject; php ./maho cron:run always >/dev/null 2>&1
```

Each `cron:run` invocation takes a lock and exits immediately if a previous run
of the same group (`default`, `always`, ...) is still going, so overlapping runs
can't pile up. You don't need to wrap these lines in `flock(1)` or a `ps` guard
yourself.

### Locking across multiple servers

!!! info "v26.7+"
    The configurable lock backend described below is available since v26.7.

Maho serializes background work (cron dispatch, reindexing, order placement, ...)
with named locks. The backend is configured in `app/etc/local.xml` and defaults
to `file`:

```xml
<global>
    <!-- ... -->
    <lock>
        <backend>file</backend> <!-- default; omit the whole block to use it -->
    </lock>
</global>
```

- **`file`** (default): kernel `flock` in `var/locks`, released the instant the
  holding process exits or crashes. Correct for single-server setups.
- **`db`**: the database server's advisory locks (MySQL `GET_LOCK`, PostgreSQL
  advisory locks). Use this when **multiple application servers share one
  database** so they don't both run the same job at once; file locks are local
  to each machine and won't see each other.

!!! warning
    Don't use the `db` backend on Galera-style clusters (no advisory lock
    support) or on SQLite (only emulated via an expiring lock table). Advisory
    locks also belong to the DB session: if the connection drops mid-run (e.g.
    MySQL `wait_timeout` on a long job), the server releases the lock while PHP
    keeps running.

## Check job status

Using [Maho's CLI tool](../developer/cli-tool.md) you can get info about jobs and their status with
these two commands:

```
./maho cron:list
+----------------------------------------------+-----------------------------------------------------------+--------------+
| event                                        | model::method                                             | schedule     |
+----------------------------------------------+-----------------------------------------------------------+--------------+
| aggregate_reports_report_product_viewed_data | reports/observer::aggregateReportsReportProductViewedData | 0 0 * * *    |
| aggregate_sales_report_bestsellers_data      | sales/observer::aggregateSalesReportBestsellersData       | 0 0 * * *    |
| aggregate_sales_report_coupons_data          | salesrule/observer::aggregateSalesReportCouponsData       | 0 0 * * *    |
| aggregate_sales_report_invoiced_data         | sales/observer::aggregateSalesReportInvoicedData          | 0 0 * * *    |
| aggregate_sales_report_order_data            | sales/observer::aggregateSalesReportOrderData             | 0 0 * * *    |
| aggregate_sales_report_refunded_data         | sales/observer::aggregateSalesReportRefundedData          | 0 0 * * *    |
| aggregate_sales_report_shipment_data         | sales/observer::aggregateSalesReportShipmentData          | 0 0 * * *    |
| aggregate_sales_report_tax_data              | tax/observer::aggregateSalesReportTaxData                 | 0 0 * * *    |
| api_session_cleanup                          | api/cron::cleanOldSessions                                | 0 35 * * *   |
| catalogrule_apply_all                        | catalogrule/observer::dailyCatalogUpdate                  | 0 1 * * *    |
| catalog_product_alert                        | productalert/observer::process                            |              |
| catalog_product_index_price_reindex_all      | catalog/observer::reindexProductPrices                    | 0 2 * * *    |
| core_clean_cache                             | core/observer::cleanCache                                 | 30 2 * * *   |
| currency_rates_update                        | directory/observer::scheduledUpdateCurrencyRates          |              |
| customer_flowpassword                        | customer/observer::deleteCustomerFlowPassword             | 0 0 1 * *    |
| index_clean_events                           | index/observer::cleanOutdatedEvents                       | 30 */4 * * * |
| log_clean                                    | log/cron::logClean                                        |              |
| newsletter_send_all                          | newsletter/observer::scheduledSend                        | */5 * * * *  |
| paypal_fetch_settlement_reports              | paypal/observer::fetchReports                             |              |
| persistent_clear_expired                     | persistent/observer::clearExpiredCronJob                  | 0 0 * * *    |
| queue_clean_up                               | queue/cron::cleanup                                       | 0 2 * * *    |
| queue_process                                | queue/cron::process                                       | * * * * *    |
| sales_clean_quotes                           | sales/observer::cleanExpiredQuotes                        | 0 0 * * *    |
| sitemap_generate                             | sitemap/observer::scheduledGenerateSitemaps               |              |
+----------------------------------------------+-----------------------------------------------------------+--------------+
```

!!! note "Email delivery jobs"
    <span class="version-badge">v26.9+</span> Emails ride the generic
    [message queue](../developer/message-queue.md): `queue_process` keeps the background worker alive
    (respawning it within a minute if it dies) and `queue_clean_up` prunes old messages nightly.

    <span class="version-badge">&lt;v26.9</span> Queued emails were flushed by `core_email_queue_send_all`
    and pruned by `core_email_queue_clean_up`. Both jobs are gone, and any unsent messages are migrated
    onto the message queue on upgrade.

```
./maho cron:history
+-------------+---------------------------+---------+----------+---------------------+---------------------+-------------+-------------+
| schedule_id | job_code                  | status  | messages | messages            | scheduled_at        | executed_at | finished_at |
+-------------+---------------------------+---------+----------+---------------------+---------------------+-------------+-------------+
| 167         | newsletter_send_all       | pending |          | 2024-08-10 23:09:41 | 2024-08-10 23:09:00 |             |             |
| 168         | newsletter_send_all       | pending |          | 2024-08-10 23:09:41 | 2024-08-10 23:10:00 |             |             |
| ...         | ...                       | ...     | ...      | ...                 | ...                 | ...         | ...         |
+-------------+---------------------------+---------+----------+---------------------+---------------------+-------------+-------------+
```

## Test locally

When developing a Maho project locally, you don't need to set up cron, but you may
want to run a specific cron job.

This can be done passing the `job_code` you want to execute to `./maho cron:run`, like:

```
./maho cron:run newsletter_send_all
newsletter_send_all executed successfully
```

!!! note
    If there's a record in the `cron_schedule` table for the specified `job_code` with status
    of `pending`, that record will be "burnt" otherwise no record will be created but the job
    will be executed anyway.

## Migrating from XML

If you're upgrading a module that used XML-based cron configuration:

**Before (config.xml):**

```xml
<config>
    <crontab>
        <jobs>
            <newsletter_send_all>
                <schedule><cron_expr>*/5 * * * *</cron_expr></schedule>
                <run><model>newsletter/observer::scheduledSend</model></run>
            </newsletter_send_all>
        </jobs>
    </crontab>
</config>
```

**After (PHP attribute):**

```php
#[Maho\Config\CronJob('newsletter_send_all', schedule: '*/5 * * * *')]
public function scheduledSend(Mage_Cron_Model_Schedule $schedule): void { }
```

For config-driven schedules:

```xml
<!-- Before -->
<schedule><config_path>crontab/jobs/catalog_product_alert/schedule/cron_expr</config_path></schedule>
```

```php
// After
#[Maho\Config\CronJob('catalog_product_alert', configPath: 'crontab/jobs/catalog_product_alert/schedule/cron_expr')]
```

Remove the corresponding XML blocks from `config.xml` after migrating; both sources are read at runtime, and duplicates will cause issues.

### Automated migration

Two commands cover this end-to-end:

```bash
./maho health-check                    # detect remaining <crontab><jobs> blocks
./maho legacy:migrate-cron --dry-run   # preview the rewrite
./maho legacy:migrate-cron             # apply
```

`legacy:migrate-cron` scans `app/code/local` and `app/code/community`, parses each `<run><model>alias::method</model>`, resolves the alias, inserts `#[Maho\Config\CronJob]` above the target method, and removes the migrated `<crontab><jobs>` block. It reads `<schedule><cron_expr>` (emitted as `schedule:`) or `<schedule><config_path>` (emitted as `configPath:`). Run `composer dump-autoload` (or click *Recompile PHP Attributes* on the cache page) after applying. See [Automated migration in routing.md](../developer/routing.md#automated-migration) for the full list of migration commands.
