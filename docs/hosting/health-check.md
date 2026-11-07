---
description: Run the Maho health check from the CLI or the admin to find legacy files, stale module leftovers, bloated tables and encryption problems, then reclaim disk space with db:optimize.
---

# Health check & database maintenance

`./maho health-check` inspects a Maho project for problems that do not show up as errors: files left
behind by a Magento or OpenMage core, database rows that belong to a module you removed, tables that
hold more free space than data, an encryption key that cannot decrypt what is stored. Run it after a
migration, after an upgrade, after removing a module, and periodically in production.

```bash
./maho health-check
./maho health-check --check-zero-dates   # also scan every date column for stored zero dates (slow)
```

The same report is available in the admin under **System > Tools > Health Check**, next to the PHP,
database and cache facts of the server. The admin page reports; the CLI also offers to clean up.

## The checks

Every check prints `OK` or a warning with the reason and the fix. The checks below are grouped by what
they look at.

**Files and code**

| Check | What it finds | Fix |
|---|---|---|
| Composer autoloader | An optimized autoloader in a development environment, where new classes would not be found | `composer dump-autoload` |
| Legacy core files | Files and folders of a Magento or OpenMage core still in the project | Delete them |
| Custom APIs | `urn:Magento` or `urn:OpenMage` in XML files | Replace with `urn:Maho` |
| Deprecated folders | `lib/Zend`, `skin` and other folders Maho no longer reads | Delete them |
| Frontend themes | A theme with no skin directory, or a skin directory with no matching design folder | Add the missing folder, or delete the orphan |
| Deprecated `Varien_` classes | Code using a `Varien_` class that moved to the `Maho\` namespace | Rename the class |
| Unconverted price reads <span class="version-badge">v26.9+</span> | Code reading a price attribute directly, which skips the website currency rate | Use `getPriceAttributeValue()` |
| Legacy XML routing, observers and cron jobs | XML declarations that have a PHP attribute equivalent | `./maho legacy:migrate-*`, see [routing](../developer/routing.md) |
| Legacy admin frontName | The old `<admin><routers>` override in `local.xml` | `<admin><base_path>` |
| Compiled attribute registry <span class="version-badge">v26.9+</span> | Observers, message handlers and routes in `vendor/composer/maho_attributes.php` whose class or method is gone | `composer dump-autoload` |

**Encryption**

| Check | What it finds | Fix |
|---|---|---|
| Encryption key | A missing, malformed or legacy mcrypt key in `local.xml` | `./maho sys:encryptionkey:regenerate` |
| Encrypted data | Configuration values and admin 2FA secrets that the current key cannot decrypt | Restore the original key, then regenerate |

**Leftovers of removed modules** <span class="version-badge">v26.9+</span>

A module you delete from the project takes its code away and leaves its rows. Nothing else reports
those rows, because `./maho migrate` is forward-only.

| Check | What it finds | Fix |
|---|---|---|
| Orphaned cron jobs | Job codes still declared, usually in `core_config_data`, with no code to run them, and `cron_schedule` rows for codes nothing declares | The CLI offers to delete the config and schedule rows. A job declared in a module's XML must be removed there |
| Phantom payment methods and carriers | Payment methods and shipping carriers configured with no model class. An active one is an error: checkout asks for a model no module can build | The CLI offers to delete their config rows |
| Stale module versions | `core_resource` rows recording the install history of a module that is gone | The CLI offers to delete them |
| Unclaimed config sections | `core_config_data` sections that no installed module declares | Report only. Read the rows before you delete them, a section can be hand-written |
| Unclaimed tables | Tables that no `sql/schema.php` and no resource entity declares | Report only. Never dropped automatically. Back up before you drop one |

Rows that belong to a **disabled** module are listed separately and kept. Enable the module again, or
remove it for good and rerun the check.

**Database**

| Check | What it finds | Fix |
|---|---|---|
| Orphaned role resources | Admin and API role rules that point to ACL resources that no longer exist | The CLI offers to delete them |
| Legacy zero dates | `0000-00-00` values and column defaults that fail under strict `SQL_MODE`. Only with `--check-zero-dates` | See [the database layer](../developer/database-layer.md#upgrading-legacy-stores-zero-dates-and-the-escape-hatch) |
| Table storage engines <span class="version-badge">v26.9+</span> | MySQL tables that are not InnoDB. Writing them inside a transaction fails on MySQL 8.4+, where `enforce_gtid_consistency` defaults to on | `./maho migrate` converts them |
| Table optimization <span class="version-badge">v26.9+</span> | Tables that hold enough reclaimable free space to be worth a rebuild | `./maho db:optimize`, see below |

### Cleanup prompts

When the CLI finds a leftover it can remove safely, it asks first:

```
Checking cron job declarations...
Warning: Found 1 cron job(s) with no code left to run them:
- my_sync: class My_Observer does not exist (12 cron_schedule row(s))
    core_config_data: crontab/jobs/my_sync/run/model
Unless disabled, they are scheduled on every cron run and can never execute,
and their pending rows are never deleted until the declaration is removed.

Delete the configuration and schedule rows of my_sync? [y/N]
```

Answer `N` to keep everything. Run with `--no-interaction` to skip every prompt, for example from a
monitoring script. Nothing is ever deleted without a `y`, and the report-only checks (unclaimed config
sections and tables) never prompt at all.

## Table bloat and `db:optimize` <span class="version-badge">v26.9+</span>

A table that had many rows deleted keeps its disk space. On InnoDB the space stays inside the table
file as free extents. On PostgreSQL the dead rows wait for a vacuum. On SQLite the freed pages sit on
the freelist. The health check flags a table when both thresholds are met:

| Threshold | Value | Reason |
|---|---|---|
| Minimum size on disk | 50 MB | Below this, a rebuild costs more downtime than the space it returns |
| Minimum reclaimable share | 30% | Below this, fragmentation is ordinary churn, not bloat |

Bloat this size usually means rows were purged in bulk, or that a cleanup job such as
`./maho log:clean` is not running. Fix the cause first, then reclaim the space:

```bash
./maho db:optimize                       # scan, list the bloated tables, confirm, rebuild them
./maho db:optimize --dry-run             # print the statements without running anything
./maho db:optimize --table=log_visitor   # rebuild a table whatever its size (repeatable)
./maho db:optimize --all                 # rebuild every table
./maho db:optimize --force               # skip the confirmation, for a scripted maintenance window
```

The scan prints a table with the size on disk, the reclaimable estimate and the share, then asks for
confirmation. After each rebuild it prints the space it freed, measured before and after.

**This is a maintenance-window operation.** Each backend rebuilds the data in place:

| Backend | Statement | Effect while it runs |
|---|---|---|
| MySQL, MariaDB | `OPTIMIZE TABLE` | Online for InnoDB, but writes are slowed. Needs free disk equal to the table |
| PostgreSQL | `VACUUM (FULL, ANALYZE)` | Holds an `ACCESS EXCLUSIVE` lock. Every read and write on the table blocks until it finishes |
| SQLite | `VACUUM` | Rewrites the whole database file. Needs free disk equal to its size. Always whole-file, whatever tables you name |

Some details that change what the numbers mean:

- **MySQL** reads fresh statistics from `information_schema`, not the daily cached ones, so the scan
  reflects the current state. With `innodb_file_per_table` off, every table lives in the shared
  tablespace, a rebuild never returns space to the filesystem, and the scan reports nothing with a note
  saying why.
- **PostgreSQL** infers the reclaimable space from the dead-tuple share, which is a statistics estimate.
  A large standing share means autovacuum is not keeping up, or is blocked by a long transaction or an
  abandoned replication slot. Fix that before you run `VACUUM FULL` by hand.
- **SQLite** has no per-table accounting, so the scan reports the database file as one entry.
- With a table prefix configured, only the prefixed tables are scanned and rebuilt. Other tables in a
  shared database are left alone.
- The command refuses to run inside an open transaction, since no backend can rebuild a table in one.

## Automate it

The CLI exits with a non-zero code when a check fails, so it fits a cron job or a monitoring probe:

```bash
0 6 * * * cd /var/www/maho && ./maho health-check --no-interaction >> var/log/health-check.log 2>&1
```

Keep `--check-zero-dates` out of the scheduled run on a large store: it scans every date column of
every table.
