---
description: The data sets, metrics and groupings of the Report Explorer, how each metric treats canceled and refunded orders, and where its numbers differ from the classic reports.
admin_pages: [report_explorer]
---

# Report Metrics

This page lists the data of the [Report Explorer](report-explorer.md): what each metric counts,
and where a number can be different from a classic report.

## Rules for every report

- Amounts are in the global currency. Each base amount of an order is multiplied by its rate to
  the global currency, as the classic reports do.
- The days of a period are days in the time zone of the default configuration, also when you
  select one store view.
- A ratio, such as **Average order value** or **Margin %**, is the ratio of the sums. The total
  row divides the total of one metric by the total of the other. A division by 0 shows "–".
- A grouping by category counts an item once in each of its categories, so the rows add up to
  more than the total. The page shows a warning for this.

## Orders

Each row of the data is one order. The **Orders** control picks the orders that count (see
[Orders that count](report-explorer.md#orders-that-count)).

| Metric | What it counts |
|--------|----------------|
| Orders | The orders |
| Units | Units ordered, without the canceled units. A configurable or bundle product counts once |
| Gross sales | Subtotal without tax and shipping, without the canceled subtotal |
| Discounts | The discount amount, without the canceled discount, as a positive number |
| Returns | The refunded subtotal, without the refunded discount |
| Net sales | Gross sales minus discounts minus returns |
| Sales total | The grand total, without the canceled amount |
| Revenue | The invoiced amount without tax and shipping, minus the refunded amount without tax and shipping |
| Profit | Paid minus refunded, without tax and shipping, minus the cost of the invoiced items |
| Invoiced, Paid, Refunded, Canceled | The total of each, as the classic Orders report shows them |
| Tax, Shipping, Discounts invoiced | The amount of the order, and the invoiced amount with "invoiced" in the name |
| Average order value | Sales total divided by orders |
| Customers | The number of different customer emails |
| Orders of new customers | Orders whose customer email has no earlier placed order |
| Orders of returning customers | Orders whose customer email has an earlier placed order |

The **New or returning** grouping uses the same rule. A guest and a registered customer with the
same email are one customer.

## Order items

Each row is one item of an order. A child item of a configurable or bundle product is not a row.
The parent row counts the units.

| Metric | What it counts |
|--------|----------------|
| Quantity ordered | The quantity ordered |
| Units | Quantity ordered minus quantity canceled |
| Units invoiced, shipped, refunded | The quantity of each |
| Cost | Cost of the item times units. A configurable product takes the cost of its child. A bundle product has cost 0 |
| Gross margin | Net sales minus cost |
| Margin % | Gross margin divided by net sales |
| Return rate % | Units refunded divided by units |
| Cost coverage % | The share of order lines whose product had a cost. A margin is reliable only near 100% |

The attributes that **Attributes for Sales by Attribute** selects become groupings, and each
one gets a "Sales by" report.

## Invoices, credit memos and taxes

The invoices and the credit memos use the date of the document, not the date of the order. Each
invoice and each credit memo is one row. Their default orders are **Orders that are not
canceled**.

The taxes data has one row for each tax rate of an order, with the tax amount of that rate.

## Stock

The stock data has no period: it shows the stock now. It holds only the products whose type
keeps a quantity (simple, virtual and downloadable products, for example).

| Metric | What it counts |
|--------|----------------|
| Quantity | The quantity in stock |
| Stock value at cost | Quantity times the cost of the product |
| Stock value at price | Quantity times the price of the product |
| Units sold in 30 days | Units in orders that are not canceled, in the last 30 days |
| Units sold per day | Units sold in 30 days divided by 30 |
| Days of stock left | Quantity divided by units sold per day |

- **Low stock**: the stock is managed, and the quantity is below **Notify for Quantity Below**.
- **Dead stock**: the product is in stock and has no sale in an order that is not canceled in the
  last **Dead Stock After (days)**.
- **Products never sold**: the product is in no order.

The stock data has no store view selector: the quantity and the sales count for all the store views.

## Carts

Each row is one cart with items. The date is the last activity of the cart.

| State | Rule |
|-------|------|
| Converted | An order names the cart |
| Active | The cart is active and had activity within **Abandoned After (hours)** |
| Abandoned | The cart is active, has an email (of the customer or of the billing address), and had no activity in **Abandoned After (hours)** |
| Abandoned without an email | The same, without an email |
| Recent | The cart is active, has no order, and had activity within **Recent Carts (hours)** |

**Conversion %** is converted carts divided by carts. **Abandonment %** is abandoned carts
divided by abandoned and converted carts. The store deletes old carts (**Checkout > Shopping
Cart > Quote Lifetime (days)**), so a cart trend reaches back only that number of days.

## Other data

- **Customers**: one row for each customer account, dated by its creation.
- **Product views**: the daily view counts that **Refresh Statistics** saves for the most viewed
  products. The days of this data are already in local time.
- **Search terms**: one row for each stored search term, with its searches and results. It has no
  period.
- **Reviews**: one row for each review of a product.
- **Tags**: one row for each use of a tag on a product. It has no period.
- **Downloads**: one row for each bought link. **Downloads bought** is 0 for an unlimited link.

## Differences from the classic reports

The Orders preset **Sales overview**, with **Placed orders** and the order date or the update
date, gives the same numbers as the classic **Sales > Orders** report, and a test checks this.
No test compares the other presets with the classic reports. These are the known differences:

| Classic report | Difference |
|----------------|------------|
| Invoiced, Refunded | The classic report counts the newest invoice or credit memo of each order, with the order totals. The Explorer counts each document with its own totals |
| Bestsellers | The classic report also counts the child items of configurable products |
| Customers by orders total, by number of orders | The classic report sums the base amounts without the rate to the global currency, leaves out guests, and subtracts the canceled discount |
| Products > Most Viewed | The classic report keeps the 5 most viewed products of each store and period before it adds them up |
| Downloads | The classic report counts a link once for each store title of the link |
| Shopping Cart > Abandoned carts | The classic report lists every active cart with items. The Explorer needs an email and the idle time |
| Shopping Cart > Products in carts | The classic report counts the orders of all time, and parent and child cart rows |
| Reviews by customer | The classic report leaves out the customers without a middle name |
