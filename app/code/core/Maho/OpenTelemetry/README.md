# Maho OpenTelemetry

Native OpenTelemetry instrumentation for Maho: distributed traces, optional log
and metric export, commerce-level span events, and W3C context propagation —
all over OTLP/HTTP, compatible with any OpenTelemetry backend (Grafana
Cloud/Tempo, Jaeger, Datadog, Honeycomb, SigNoz, ...).

## Quick start

```bash
composer require open-telemetry/sdk open-telemetry/exporter-otlp nyholm/psr7
# optional, for log export:
composer require open-telemetry/opentelemetry-logger-monolog
```

(`nyholm/psr7` provides the PSR-17 HTTP factories the OTLP transport discovers
at runtime.)

Local all-in-one backend for development:

```bash
docker run -p 3000:3000 -p 4318:4318 grafana/otel-lgtm
```

Then either configure in the admin (System → Configuration → Developer →
OpenTelemetry) with endpoint `http://localhost:4318/v1/traces`, or use the
standard environment variables:

```bash
OTEL_SERVICE_NAME=maho-dev
OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318
OTEL_TRACES_SAMPLER=traceidratio
OTEL_TRACES_SAMPLER_ARG=1.0
```

Traces appear in Grafana at http://localhost:3000 (Explore → Tempo).

## What gets traced

| Span | Kind | Notes |
|---|---|---|
| `{METHOD} {module/controller/action}` | SERVER | Request root span, renamed after routing |
| `{METHOD} {api_route}` | SERVER | Same for `/api/*`: the API Platform route name (REST, GraphQL, MCP) or `api/{type}` for the legacy servers |
| `{OPERATION} {table}` | CLIENT | Every DB query. `db.query.text` carries the statement as executed (see Data safety) and can be switched off |
| `{METHOD}` (HTTP client) | CLIENT | Outgoing requests via `\Maho\Http\Client::create()`; `url.full` is stripped of query string, fragment and userinfo. Spans the whole exchange, from the request being issued to the body being read, so a failure raised while consuming the response lands on the span rather than after it |
| `process {MessageClass}` | CONSUMER | One span per queue message, continuing the trace of the request that dispatched it; the payload is never recorded |
| `BLOCK:*`, `OBSERVER:*`, `cron.job*`, `email.send`, `image.process`, `index.reindex`, `payment.*` | INTERNAL | High-level profiler timers |
| `cache.*` | INTERNAL | Cache reads and writes, off by default |
| `maho {command}` | INTERNAL | Each CLI command is its own trace (command name only, arguments are never recorded) |

Nothing is traced until the request root span opens, so bootstrap work before
it does not become a scatter of single-span traces.

Commerce moments are recorded as span events with `maho.*` attributes (never
PII): `maho.order.placed`, `maho.cart.add`, `maho.checkout.success`,
`maho.customer.login`. Logged-in customers and admin users tag the trace with
the pseudonymous `enduser.id`.

## Configuration

Admin configuration lives under **Developer → OpenTelemetry**. Standard
`OTEL_*` environment variables override it:

| Variable | Effect |
|---|---|
| `OTEL_SDK_DISABLED=true` | Disables everything, wins over all other settings |
| `OTEL_SERVICE_NAME` | Service name |
| `OTEL_RESOURCE_ATTRIBUTES` | Extra resource attributes, e.g. `deployment.environment.name=staging` |
| `OTEL_SPAN_ATTRIBUTE_VALUE_LENGTH_LIMIT` | Truncates long attribute values (a big `db.query.text`); unlimited by default |
| `OTEL_EXPORTER_OTLP_ENDPOINT` | Base OTLP URL; `/v1/{signal}` is appended |
| `OTEL_EXPORTER_OTLP_TRACES_ENDPOINT` / `_LOGS_ENDPOINT` / `_METRICS_ENDPOINT` | Per-signal URL, used verbatim |
| `OTEL_EXPORTER_OTLP_HEADERS` | `key=value,key2=value2`, merged over admin headers |
| `OTEL_EXPORTER_OTLP_PROTOCOL` | `http/protobuf` (default), `http/json` or `http/ndjson`; also per-signal `_TRACES_`/`_LOGS_`/`_METRICS_`. `grpc` is not supported and falls back with a warning |
| `OTEL_PROPAGATORS` | Which context headers are read and written; default `tracecontext,baggage`. `b3`/`b3multi` need `open-telemetry/extension-propagator-b3` |
| `OTEL_TRACES_SAMPLER` | `always_on`, `always_off`, `traceidratio`, `parentbased_*` |
| `OTEL_TRACES_SAMPLER_ARG` | Ratio for the `traceidratio` samplers |
| `OTEL_LOGS_EXPORTER` / `OTEL_METRICS_EXPORTER` | `otlp` or `none`, override the admin flags |
| `OTEL_BSP_MAX_QUEUE_SIZE`, `OTEL_BSP_SCHEDULE_DELAY`, `OTEL_BSP_EXPORT_TIMEOUT`, `OTEL_BSP_MAX_EXPORT_BATCH_SIZE` | Batch processor tuning |

Noise controls: **Trace Block Rendering** switches off the high-volume block
spans; **Trace Cache Operations** (off by default) switches on the higher-volume
cache spans; **Excluded Paths** skips tracing entirely for matching request paths
(prefix or `*`/`?` wildcard patterns, e.g. `/health`, `/media/*`).

Span volume is bounded by sampling, not by a cap: on an unsampled request no
span is built at all, so no attribute is computed. On a sampled request every
operation is recorded, so a page that runs 3000 queries produces 3000 spans.
Raise `OTEL_BSP_MAX_QUEUE_SIZE` above the default 2048 if you want to keep them,
and lower the sampling rate rather than trimming what a trace contains.

## Distributed tracing

- Outgoing HTTP requests through `\Maho\Http\Client::create()` carry a
  `traceparent` header. The `baggage` header (`maho.store`, `maho.currency`)
  only goes to hosts listed under **Baggage Hosts**, so a payment gateway or a
  carrier never receives it.
- Dispatching a queue message stores the current trace context on the message
  row, so the handler's spans join the trace of the request that queued the
  work. The producing request is normally long finished when the handler runs.
  Sampling follows the dispatching request: work queued by an unsampled request
  is not traced either.
- **Trust Incoming Trace Headers** (default off) continues traces started by
  upstream callers; sampling then honors the parent's decision (parent-based
  sampling). Which headers are read depends on `OTEL_PROPAGATORS`, so a caller
  sending B3 can be joined once `open-telemetry/extension-propagator-b3` is
  installed. Only enable behind a trusted proxy.
- **Server-Timing Response Header** (default off) exposes the W3C trace
  context (trace id, span id and sampled flag — nothing else) to browser RUM
  tooling (e.g. Grafana Faro) for frontend↔backend correlation.

## Logs and metrics

- **Export Logs** ships every Monolog record (already tagged with
  `trace_id`/`span_id`) to `/v1/logs`. Requires
  `open-telemetry/opentelemetry-logger-monolog`. Records are exported as-is,
  at the same level as the local files — whatever any module writes to the
  logs leaves the server, so treat the OTLP backend with the same sensitivity
  as `var/log`.
- **Export Metrics** ships delta-temporality metrics to `/v1/metrics`:
  `http.server.request.duration` histogram plus `maho.orders`,
  `maho.order.revenue` and `maho.cart.additions` counters.

Telemetry is flushed after the response is sent to the client, so page latency
is unaffected — but each enabled signal is flushed sequentially, so extra
signals lengthen the worst-case time a PHP worker is held when the collector
is slow or down. `OTEL_BSP_EXPORT_TIMEOUT` and the transport timeout (10s,
single retry) bound it.

## Data safety

The trace instrumentation never exports HTTP request/response headers or
bodies, URL query strings or userinfo, CLI arguments, queue message payloads,
or warning/notice-level PHP error messages (only fatal-class errors include the
message text). Logged-in customers and admin users are identified by id alone
(`enduser.id`), never by name or email. Credentials for the OTLP endpoint itself
are stored encrypted (Authorization Header field).

A failed span carries the exception class in `error.type` and in its status
description, never the message. The `exception` event that accompanies it is the
standard OTel one, so it does carry `exception.message` and
`exception.stacktrace`: an unhandled failure is worth the detail, but treat the
backend as holding whatever your code puts in exception messages.

Two settings do export more, and both are named for what they do:

- **Query Statement** (on by default) puts the SQL statement on every query
  span. Maho writes values into the statement with `quoteInto()` rather than
  binding them, so the statement carries those values: customer email
  addresses, password reset tokens, search terms, coupon codes. Turn it off if
  the OTLP backend must not hold customer data; span names, timings and counts
  are unaffected.
- **Export Logs** ships Monolog records verbatim, so anything any module logs
  (including third-party code) leaves the server. Enable it only against a
  backend you trust with the contents of `var/log`.
