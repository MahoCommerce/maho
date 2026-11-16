---
description: The Reports > Explorer page runs ready-made reports and reports that you build, on live order, cart, customer and stock data, with a chart, a comparison with an earlier period, the records behind a number, an explanation of a change, notes, monthly targets, saved reports pinned to the admin dashboard, and CSV export.
admin_pages: [report_explorer]
---

# Report Explorer

The **Reports > Explorer** page shows reports on the live data of the store. It reads the orders,
invoices, credit memos, carts, customers and stock directly. It does not need **Refresh
Statistics**, so a new order shows in the next report.

The classic reports under **Reports** stay. They read the tables that **Refresh Statistics**
fills. The [Report metrics](report-metrics.md) page lists where the numbers of the two can be
different.

## Run a ready-made report

1. Go to **Reports > Explorer**.
2. In the **Report** menu, select a report, for example **Sales overview** or **Sales by payment
   method**.
3. Change the controls above the report. Each change runs the report again.

When you change a control, the **Report** menu shows **Custom report**. A saved report that you
change shows "(changed)" after its name.

The address of the page holds the report. Copy the address to send the same report to another
administrator.

## The controls

| Control | What it does |
|---------|--------------|
| Report | A ready-made report or a saved report. It sets all the other controls |
| Data | The data of the report: orders, order items, invoices, credit memos, taxes, customers, carts, cart items, stock, product views, search terms, reviews, tags or downloads. A change keeps the period, the store view and the metrics, groupings and filters that the new data also has |
| Period | The days of the report, for example **Last 30 days**. **Custom** shows a **From** and a **To** day |
| Compare with | **The period before** has the same number of days and ends the day before the period. **The year before** has the same days one year earlier |
| Date | The date that puts an order in the period: the order date, or the date of its last change |
| Store view | One store view, or all the store views that you can see |
| Orders | The orders that count. See [Orders that count](#orders-that-count) |
| Metrics | The numbers in the columns. Click the counter to add or remove a metric |
| Group by | Up to three groupings. A date grouping has a step: hour, day, week, month, year, hour of the day or day of the week |
| Filters | A condition on a field, for example **Payment method is Check / Money order** |

The days of a period are days in the time zone of the default configuration (**System >
Configuration > General > Locale Options > Timezone**). The week starts on the day that
**First Day of Week** sets. Amounts are in the global currency.

A report with only a date grouping shows the newest date first. Click a column title to sort the
rows by that column. A change shows in green when it is good and
in red when it is bad. For a metric such as **Returns**, a lower number is good.

## Read the chart

Each metric has a card above the chart, with its total, the total of the earlier period and a
small line of its values over the period. Click a card to show that metric in the chart. A report
with more than six metrics shows five cards. Click **Show more** for the other cards.

The chart shows one metric. Its type follows the first grouping:

- A date grouping shows a line. The earlier period is a gray dotted line on the same axis.
- **Hour of the day** and **Day of the week** show vertical bars.
- Any other grouping shows horizontal bars, with the 15 biggest values.
- With two groupings, the chart shows the 5 biggest values of the second grouping as five lines
  or five bar colors.

The buttons **Auto**, **Line**, **Bar** and **Table** on the chart change its type. **Table** hides
the chart.

When the period changes, a date grouping changes its step: hours for one or two days, days up to
two months, weeks up to six months, then months.

## Look inside a number

Click a row of the table, or a point or a bar of the chart. A menu opens:

- **Only this value** keeps only this row. For a value such as a payment method, it adds a filter.
  For a day, a week or a month, it changes the period to that day, week or month, with a smaller
  step.
- **Break down by** keeps only this row and groups it by another field.
- **Show the records** lists the orders, invoices, customers or other records behind the row,
  with a link to each one.
- **Explain the change** shows which values caused the change of the row against the earlier
  period.
- **Add a note** adds a note on the first day of the row. It shows for a day, a week, a month or a
  year.

The steps show above the report. Click a step to go back to it.

The records need the permission of the records themselves. For example, the orders need **Sales >
Orders > Actions > View**.

## Explain a change

Click **Explain the change** on the chart. The page compares the metric of the chart with the
earlier period, or with the period before when the report has no comparison. For each grouping,
it lists the values with the biggest change, for example the payment methods or the countries.

Each value shows its change and its change in percent. A value that was small in the earlier
period, under 5% of the earlier total of its grouping, shows no percent, because a change from 1 to 9
is +800% and tells less than the change itself. When two groupings split the records in the same
way, such as **Customer group** and **Customer type** when only guests and one group order, the
page shows only the first one. When the metric adds up over the values,
such as **Net sales** or **Orders**, the tooltip of a value also shows its share of the total
change. An average, such as **Average order value**, has no share. Click a value to keep only it.

## Notes

A note marks a day on the chart, for example the day of a newsletter. Click **Add a note** on a
chart over time, or click a day on the chart and select **Add a note**. Then enter the day and the
text. The note shows as a dotted line on the chart with its text above the dot, in the tooltip of
its day, and as a label under the chart, for every administrator. Only the author deletes a note.

The chart also shows the first day of each active cart price rule, as **Promotion starts**.

## Targets

A target is a number for one month, for example net sales of 30,000 in June.

1. Show the metric in the chart.
2. Click **Targets**.
3. Select the month and enter the target.
4. Click **Save**.

A report that groups by the date only, with no filter, shows the target as a green dotted line
and as a percent on the card of the metric. For a day or a week, the target is the monthly target
divided by the days of the month. A target set while one store view is selected applies to that
store view. Otherwise, the target applies to the report of all the store views.
Only a metric that adds up, such as **Net sales** or **Units**, can have a target.

## Pinned reports on the dashboard

Open a saved report and click its star, next to **Save**. The report then shows as a box in the
left column of the admin **Dashboard**, after **Sales Overview**. The box shows the total of the
first metric, its change and a small chart. Click the name of the box to open the report in the
Explorer. Click the star of the box to remove it from the dashboard.

Each administrator pins reports for their own dashboard. A shared report that its owner stops
sharing leaves the dashboard.

## Orders that count

The order reports have an **Orders** control with three values:

- **Placed orders (not new or pending payment)**: every order except the orders in the state
  "new" or "pending payment". A canceled order counts as an order, with no amount. This is the
  default, and the classic Orders, Tax and Shipping reports count the same orders.
- **Orders that are not canceled**: the classic Invoiced, Refunded, Bestsellers and customer
  reports count these orders.
- **All orders**: every order, as the classic Coupons report does.

## Save a report

1. Build the report.
2. Click **Save**.
3. Enter a name.
4. To show the report to the other administrators, select **Share with the other administrators**.
5. To show the report on your dashboard, select **Show on my dashboard**.
6. Click **Save**.

The report shows under **Saved reports** in the **Report** menu. Only its owner changes or deletes a saved report. A
saved report keeps its period as a named period, so **Last 30 days** always shows the last 30
days.

## Export a report

Click **Export CSV**. The file has every row of the report, not only the rows of the page.

If the report has more rows than **Rows per Page**, the export runs in the background. A message
in the admin inbox links to the file when it is ready. A daily job deletes an export file after
7 days.

## Personal data

The groupings and filters with personal data (customer, customer email, billing postcode) show
only for an administrator whose role allows **Customers > Manage Customers**.

Each data set needs the permission of its classic report. For example, the order items need
**Reports > Products**. The page itself needs **Reports > Explorer**.

## Settings

**System > Configuration > Reports > Explorer** has these settings:

| Setting | What it does |
|---------|--------------|
| Rows per Page | The most rows that the page and the API show at once. A larger CSV export runs in the background |
| Rows per Export | The most rows of one CSV export |
| Cache Lifetime of a Current Period | How long a report whose period includes today stays in the cache, in seconds |
| Cache Lifetime of a Past Period | How long a report on a past period stays in the cache, in seconds |
| Dead Stock After (days) | A product in stock with no sale in this number of days is dead stock |
| Abandoned After (hours) | An active cart with an email and no order is abandoned after this time without activity |
| Recent Carts (hours) | An active cart is recent when its last activity is within this time |
| Attributes for Sales by Attribute | Each attribute gets a grouping and a "Sales by" report in the order items |

A save of this section clears the cached reports.
