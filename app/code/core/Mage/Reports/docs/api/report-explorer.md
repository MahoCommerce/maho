---
description: Run Report Explorer reports, list the records behind them, explain a change, and manage saved reports, the dashboard, notes and targets through the REST API and the MCP tools.
---

# Report Explorer API

The Report Explorer API runs the same reports as **Reports > Explorer**, on live data. It has
these operations:

| Method and path | What it does |
|-----------------|--------------|
| `GET /api/rest/v2/reports/explore/datasets` | Lists the data sets that the caller may read, with their metrics, groupings, filter fields, scopes, date fields and presets |
| `GET /api/rest/v2/reports/explore` | Runs a report from the query string |
| `QUERY /api/rest/v2/reports/explore` | Runs a report from a JSON body (RFC 10008) |
| `GET /api/rest/v2/reports/saved` and `/reports/saved/{id}` | Lists or reads saved reports |
| `POST`, `PATCH`, `PUT`, `DELETE /api/rest/v2/reports/saved...` | Creates, changes or deletes the saved reports of an administrator |
| `GET /api/rest/v2/reports/explore/records` | Lists the records behind a report, such as its orders |
| `GET /api/rest/v2/reports/explore/explain` | Lists the values that caused the change of a metric against an earlier period |
| `POST`, `DELETE /api/rest/v2/reports/saved/{id}/pin` | Puts a saved report on the dashboard of the administrator, or takes it off |
| `GET`, `POST`, `PATCH`, `PUT`, `DELETE /api/rest/v2/reports/annotations...` | Lists, adds, changes or deletes the notes on the days of the charts |
| `GET`, `POST`, `PATCH`, `PUT`, `DELETE /api/rest/v2/reports/targets...` | Lists, adds, changes or deletes the monthly targets of a metric |

See [the API overview](v2/index.md) for the tokens.

## Access

- An admin token reads the data sets that the admin role allows, and needs **Reports >
  Explorer**.
- An API key needs the permission `reports/read`. It reads every data set.
- The personal fields (customer, customer email, billing postcode) need **Customers > Manage
  Customers** for an admin token, or the permission `customers/read` for an API key.
- A token that has a store restriction reads only its stores.
- Only an admin token creates, changes or deletes a saved report, and only its own reports. An API
  key reads only the shared reports.
- The records need the permission of the records: for example `orders/read` for an API key, or
  **Sales > Orders > Actions > View** for an admin token.
- Only an admin token pins a report, and writes a note or a target. Only the author of a note
  changes or deletes it.

## Run a report

Give a preset, or a data set with its metrics:

```bash
curl -H "Authorization: Bearer $TOKEN" \
  'https://example.com/api/rest/v2/reports/explore?preset=sales_by_payment_method&range=last_month'

curl -H "Authorization: Bearer $TOKEN" \
  'https://example.com/api/rest/v2/reports/explore?dataset=sales_orders&metrics=orders,net_sales&dimensions=period:week,shipping_country&from=2026-10-01&to=2026-10-31&compare=previous_year'
```

| Parameter | Value |
|-----------|-------|
| `preset` | The code of a preset. The other parameters change it |
| `dataset` | The code of a data set |
| `metrics` | Comma-separated metric codes |
| `dimensions` | Up to 3 comma-separated grouping codes. The period takes a step after a colon: `period:hour`, `period:day`, `period:week`, `period:month`, `period:year`, `period:hour_of_day`, `period:day_of_week` |
| `filters` | A JSON list of `{"field", "operator", "value"}`. Operators: `eq`, `neq`, `in`, `nin`, `gt`, `gte`, `lt`, `lte`, `contains`, `empty`, `not_empty` |
| `scope` | `placed` (default), `not_canceled` or `all` |
| `dateField` | The date of the period, such as `created_at` or `updated_at` |
| `range` | `today`, `yesterday`, `last_7_days`, `last_30_days` (default), `last_90_days`, `this_month`, `last_month`, `this_year`, `last_year`, `last_12_months` |
| `from`, `to` | Local days, `YYYY-MM-DD`, in place of `range` |
| `compare` | `previous_period` or `previous_year` |
| `storeIds` | Comma-separated store view IDs |
| `sort` | A metric or grouping code of the request, with `-` before it for descending. Without it, a report that groups by the date only lists the newest date first |
| `limit`, `offset` | The rows of the page. `limit` is 100 by default, and at most **Rows per Page** |

The QUERY body takes the same keys, with lists as JSON arrays and filters as objects:

```json
{
  "dataset": "sales_items",
  "metrics": ["units", "net_sales", "margin_pct"],
  "dimensions": ["category_id"],
  "filters": [{"field": "status", "operator": "in", "value": ["complete", "processing"]}],
  "range": "last_90_days",
  "sort": "-net_sales"
}
```

## The answer

```json
{
  "dataset": "sales_orders",
  "request": {"dataset": "sales_orders", "metrics": ["orders", "net_sales"], "dimensions": ["payment_method"]},
  "columns": [
    {"key": "payment_method", "label": "Payment method", "kind": "dimension"},
    {"key": "orders", "label": "Orders", "kind": "metric", "format": "number", "higherIsBetter": true},
    {"key": "net_sales", "label": "Net sales", "kind": "metric", "format": "money", "higherIsBetter": true}
  ],
  "rows": [
    {"dimensions": {"payment_method": "checkmo"}, "labels": {"payment_method": "Check / Money order"}, "values": {"orders": 12, "net_sales": 1840.5}}
  ],
  "totals": {"values": {"orders": 12, "net_sales": 1840.5}},
  "totalRows": 1,
  "warnings": [],
  "period": {"from": "2026-10-01", "to": "2026-10-31", "timezone": "Europe/Rome"},
  "comparePeriod": null,
  "currency": "EUR",
  "cached": false
}
```

With `compare`, each row and the totals also have `compare` (the values of the earlier period)
and `change` (`absolute` and `percent` for each metric). Amounts are in the global currency.

For a report that groups by the date only, with no filter, the answer also has:

- `annotations`: the notes and the first days of the cart price rules in the period, each with
  `date`, `title`, `source` (`note` or `promotion`) and `key` (the bucket of the row).
- `targets`: for each metric with a monthly target, `buckets` (the target of each row) and
  `total` (the target of the period). For a day or a week, the target is the monthly target
  divided by the days of the month.

## Records

`GET /reports/explore/records` takes the parameters of the report, and lists the records that the
report counts, newest first. It ignores `metrics` and `dimensions`.

| Parameter | Value |
|-----------|-------|
| `sort` | A record field, with `-` before it for descending |
| `limit`, `offset` | The records of the page. `limit` is 50 by default |

```json
{
  "dataset": "sales_orders",
  "columns": [{"key": "increment_id", "label": "Order #", "format": "text"}, {"key": "sales_total", "label": "Sales total", "format": "money"}],
  "rows": [{"id": 4211, "values": {"increment_id": "100004211", "sales_total": 129.9}, "labels": {}}],
  "totalRows": 18,
  "limit": 50,
  "route": "adminhtml/sales_order/view",
  "routeParam": "order_id"
}
```

`id` is the ID of the record in its admin page: `route` and `routeParam` name that page.

## Explain a change

`GET /reports/explore/explain` takes the parameters of the report, and:

| Parameter | Value |
|-----------|-------|
| `metric` | The metric to explain. Required |
| `explainDimensions` | Comma-separated groupings to check. By default, every grouping that is not personal |
| `top` | The values to list for each grouping, 1 to 20. Default 5 |
| `compare` | The earlier period. Default `previous_period` |

```json
{
  "metric": {"code": "net_sales", "label": "Net sales", "format": "money", "additive": true},
  "total": {"current": 4962, "earlier": 13011, "change": -8049, "percent": -61.86},
  "dimensions": [
    {"code": "shipping_country", "label": "Shipping country", "field": "shipping_country", "drivers": [
      {"value": "US", "label": "United States", "current": 3900, "earlier": 11000, "change": -7100, "percent": -64.55, "share": 88.21}
    ]}
  ]
}
```

`share` is the part of the total change that the value caused, in percent. It is null for a
metric that does not add up over the values, such as an average. A grouping with fewer than two
values is not in the list, because its one value is the total.

## Notes and targets

```bash
curl -X POST -H "Authorization: Bearer $ADMIN_TOKEN" -H 'Content-Type: application/json' \
  -d '{"date": "2026-11-03", "title": "Newsletter sent"}' \
  https://example.com/api/rest/v2/reports/annotations

curl -X POST -H "Authorization: Bearer $ADMIN_TOKEN" -H 'Content-Type: application/json' \
  -d '{"dataset": "sales_orders", "metric": "net_sales", "month": "2026-12", "value": 30000}' \
  https://example.com/api/rest/v2/reports/targets
```

`GET /reports/annotations` takes `from` and `to`. `GET /reports/targets` needs `dataset` and takes
`metric`. A target has one value for each data set, metric, store and month: a new value for the
same month replaces the old one. `storeId` 0 is the target of all the store views. Only a metric
that adds up, such as a sum or a count, takes a target.

## Errors

| Status | When |
|--------|------|
| 400 | `filters` is not valid JSON |
| 403 | The admin role does not allow **Reports > Explorer**, an API key tries to write a saved report, a pin, a note or a target, or an administrator changes the note of another administrator |
| 404 | The saved report does not exist, or it is a private report of another administrator |
| 422 | An unknown code, a value of the wrong type, a missing `metric` for an explanation, a period that is too long for its step, a store, a personal field or records that the caller may not read. `details.errors` names each field |

## Saved reports

```bash
curl -X POST -H "Authorization: Bearer $ADMIN_TOKEN" -H 'Content-Type: application/json' \
  -d '{"name": "Net sales by country", "isShared": true, "request": {"dataset": "sales_orders", "metrics": ["net_sales"], "dimensions": ["shipping_country"], "range": "last_month"}}' \
  https://example.com/api/rest/v2/reports/saved
```

`PATCH` with `application/merge-patch+json` changes only the fields in the body. A `request` in
the body replaces the stored request.

## MCP tools

The admin assistant and other MCP clients get these tools in the section `explorer`:

- `explorer_reports_explore_datasets_list`: the data sets and their codes.
- `explorer_reports_explore_list`: run a report, with the parameters of the GET operation.
- `explorer_reports_saved_list`, `_get`, `_create`, `_update` and `_delete`: the saved reports.
- `explorer_reports_explore_records_list`: the records behind a report.
- `explorer_reports_explore_explain_list`: the values that caused a change.
- `explorer_reports_saved_pin_create` and `_delete`: the dashboard of the administrator.
- `explorer_reports_annotations_*` and `explorer_reports_targets_*`: the notes and the targets.
