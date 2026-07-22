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
| `{OPERATION} {table}` | CLIENT | Every DB query; SQL text with `?` placeholders only, bind values are never exported |
| `{METHOD}` (HTTP client) | CLIENT | Outgoing requests via `\Maho\Http\Client::create()`; `url.full` is stripped of query string, fragment and userinfo |
| `BLOCK:*`, `OBSERVER:*`, `cron.job*`, `email.send`, `image.process`, `index.reindex`, `payment.*` | INTERNAL | High-level profiler timers |
| `maho {command}` | INTERNAL | Each CLI command is its own trace (command name only, arguments are never recorded) |

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
| `OTEL_EXPORTER_OTLP_ENDPOINT` | Base OTLP URL; `/v1/{signal}` is appended |
| `OTEL_EXPORTER_OTLP_TRACES_ENDPOINT` / `_LOGS_ENDPOINT` / `_METRICS_ENDPOINT` | Per-signal URL, used verbatim |
| `OTEL_EXPORTER_OTLP_HEADERS` | `key=value,key2=value2`, merged over admin headers |
| `OTEL_TRACES_SAMPLER` | `always_on`, `always_off`, `traceidratio`, `parentbased_*` |
| `OTEL_TRACES_SAMPLER_ARG` | Ratio for the `traceidratio` samplers |
| `OTEL_LOGS_EXPORTER` / `OTEL_METRICS_EXPORTER` | `otlp` or `none`, override the admin flags |
| `OTEL_BSP_MAX_QUEUE_SIZE`, `OTEL_BSP_SCHEDULE_DELAY`, `OTEL_BSP_EXPORT_TIMEOUT`, `OTEL_BSP_MAX_EXPORT_BATCH_SIZE` | Batch processor tuning |

Noise controls: **Trace Block Rendering** switches off the high-volume block
spans; **Excluded Paths** skips tracing entirely for matching request paths
(prefix or `*`/`?` wildcard patterns, e.g. `/health`, `/media/*`).

## Distributed tracing

- Outgoing HTTP requests through `\Maho\Http\Client::create()` carry
  `traceparent` and `baggage` (`maho.store`, `maho.currency`) headers.
- **Trust Incoming Trace Headers** (default off) continues traces started by
  upstream callers that send `traceparent`; sampling then honors the parent's
  decision (parent-based sampling). Only enable behind a trusted proxy.
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

The **trace** instrumentation never exports: SQL bind values (placeholders
only), HTTP request/response headers or bodies, URL query strings or userinfo,
CLI arguments, customer names/emails/addresses, or warning/notice-level PHP
error messages (only fatal-class errors include the message text). Credentials
for the OTLP endpoint itself are stored encrypted (Authorization Header field).

These guarantees cover the trace signal only. **Export Logs** ships Monolog
records verbatim — anything a module logs (including third-party code) is
exported, so enable it only against a backend you trust with the contents of
`var/log`.
