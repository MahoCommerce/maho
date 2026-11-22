---
description: The database connection, the statement timeout, the replica, the indexes and the cache of the Report Explorer.
---

# Report Explorer: Database and Cache

The Report Explorer reads live data from the order, invoice, credit memo, cart, customer and stock
tables. Each report runs a few `SELECT` statements with `GROUP BY`. These settings keep the load
on the database under control.

## The report_read connection

The Explorer reads through the connection `report_read`. By default, it uses the connection
`core_read`. In `app/etc/local.xml`, you can give it a timeout for each statement, or point it
at a read replica.

### Set a statement timeout

Add the `initStatements` line for your database. The examples stop a statement after 30
seconds.

MySQL:

```xml
<config>
    <global>
        <resources>
            <report_read>
                <connection>
                    <use/>
                    <host>localhost</host>
                    <username>maho</username>
                    <password>secret</password>
                    <dbname>maho</dbname>
                    <initStatements>SET NAMES utf8mb4; SET SESSION max_execution_time=30000</initStatements>
                    <model>mysql4</model>
                    <type>pdo_mysql</type>
                    <active>1</active>
                </connection>
            </report_read>
        </resources>
    </global>
</config>
```

| Database | initStatements |
|----------|----------------|
| MySQL | `SET SESSION max_execution_time=30000` (milliseconds) |
| MariaDB | `SET SESSION max_statement_time=30` (seconds) |
| PostgreSQL | `SET statement_timeout=30000` (milliseconds) |

The empty `<use/>` stops the connection from using `core_read`, so it needs its own host,
user and database. When the database stops a statement, the page shows an error and the log
gets the exception.

### Use a read replica

Give `report_read` the host of the replica, as in the example. The reports then do not load the
primary database. A replica that lags shows the newest orders later.

## Indexes

Maho adds two indexes for the Explorer:

- `sales_flat_order_item (product_id, created_at)`: the dead stock and never sold reports. It
  replaces the index on `product_id` alone, because a query on `product_id` uses the new index.
- `sales_flat_quote (updated_at)`: the cart reports.

`./maho migrate` creates them. On a large store, the index creation can take some minutes.

## Limits

- A period with hourly steps can have at most 31 days. A period with daily steps can have at most
  400 days. A period by week, month or year can have at most 36,600 days (about 100 years).
- The page and the API show at most **Rows per Page** rows at once.
- An export with more rows than **Rows per Page** runs in the queue `reports`, as an export file
  in `var/reports/export`. A daily job deletes the files after 7 days. The storage mount
  `reports_exports` can point at shared storage (see [Shared storage](storage.md)).

## Speed on a large store

These are the times of a test store on PostgreSQL 18 with 1.2 million orders over two years
(about 1,650 orders a day), 2.2 million order lines and 280,000 customer emails, on a laptop, with
no cache:

| Report | Last 30 days | Last 12 months |
|--------|--------------|----------------|
| Sales overview | 0.14 s (daily totals) | 0.07 s (daily totals) |
| New and returning customers | under 0.1 s (daily totals) | 0.03 s (daily totals) |
| Product performance | 1.7 s | 3.3 s |
| Customer cohorts (12 months) | | 5.9 s |
| Weekly customer cohorts (90 days) | 5.9 s | |
| Explain the change on Sales overview | 2.6 s | 4.9 s |

A report that cannot read the daily totals reads every record of its period, so its time grows
with the number of orders in the period. A report that reads the daily totals first calculates
again the days that changed since the last report, which took 0.75 s for today on this store. On a
store of this size, keep the cache on, and use a read replica for long periods.

### Daily totals of the orders

The table `report_order_daily` holds the totals of the orders for each local day, store view,
order state, status, customer group, customer type and new or returning customer. An order report
on a period of at least **Daily Totals from (days)** (7 by default) reads this table instead of
each order, when it uses only these groupings and filters, and only metrics that add up. For
example, **Sales overview** on the last 12 months reads about 365 rows for each combination of
these fields, not every order. **Customers**, **First order**, **Last order**, a grouping such as
**Payment method**, and the date of the last change still read each order.

The numbers stay the same as the live numbers:

- An order save marks its day as changed. Before a report reads the table, it calculates the
  changed days of its period again from the orders.
- The cron job `reports_update_daily_totals` runs every 5 minutes. It marks the days of the orders
  whose last change is after its previous run, also when a script changed them without a save.
- After an upgrade, the same cron job builds the history, 92 days at each run, newest first. A
  period that starts before the built days reads each order until the history is complete.
- A change of the default time zone starts the build again, because the days change.

A new or returning customer depends on the earlier orders of the same email. When an old order
changes its state to or from "new" or "pending payment", the days of the later orders of that email
are not marked. Set **Daily Totals from (days)** to 0 to read each order always.

## Cache

The results go to the cache type **Report Explorer Results**. A report on a past period stays for
**Cache Lifetime of a Past Period** (24 hours by default). A report that includes today, and each
cart and stock report, stays for **Cache Lifetime of a Current Period** (5 minutes by default). A
save of **System > Configuration > Reports** clears the type.

A new installation enables the cache type. An upgraded store does not: enable **Report Explorer
Results** in **System > Cache Management**, or every report reads the database again.

To see the SQL and the time of each statement of a report, run:

```bash
./maho report:explain --preset=sales_overview --range=last_12_months
```

## Report emails

The cron job `reports_send_subscriptions` runs each hour. It puts each report email that is due in
the `reports` queue, which goes to the `slow` worker pool. The worker runs the report with the
permissions of the administrator, and sends the email through the email queue. So the emails need
both cron and a queue worker.

A report email is marked as checked when the cron job queues it. If the worker fails, the store
does not send that email again. The message waits in **System > Message Queue**, where you can
retry it.

A report email runs the whole report, like a visit to the page. An alert also runs **Explain the
change**, which reads the period once for each grouping. On a large store, a long period takes the
time shown in [Speed on a large store](#speed-on-a-large-store), in the worker and not in a page.
