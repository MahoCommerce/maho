---
description: Understand Maho v2 API error responses - JSON bodies with error, message, and code fields, optional details, and the HTTP status codes explained.
---

# Error Responses <span class="version-badge">v26.7+</span>

All errors return JSON with an appropriate HTTP status code:

```json
{
  "error": "unprocessable_entity",
  "message": "The segment name must have 1 to 255 characters.",
  "code": 422,
  "details": {
    "errors": [
      {"field": "name", "message": "The segment name must have 1 to 255 characters."}
    ]
  }
}
```

!!! warning "v26.11+ breaking change"
    Each error now has the status of its meaning (see the table below). Before
    v26.11, most problems in the request body returned `400`. They now return
    `422`, `409` or `404`. The `validation_error` code is replaced by
    `unprocessable_entity` with status `422`. An unexpected server error is now
    always a `500` with a generic message. Before, some endpoints returned `400`
    or `422` with the internal message.

| Field | Description |
|-------|-------------|
| `error` | Stable, machine-readable error code in snake_case. Dispatch on this, not on `message`. |
| `message` | Human-readable explanation, safe to surface in logs or UIs. |
| `code` | The HTTP status code, mirrored in the body for convenience. |
| `details` | Optional object with extra context. When the error is about one or more fields, `details.errors` lists each one as `{"field": "...", "message": "..."}`. |
| `debug` | Only present in developer mode: exception class, file, line, and trace. Never emitted in production. |

Some endpoints emit domain-specific `error` codes beyond the status-derived ones in the table below (e.g. `invalid_client`, `invalid_credentials`, `unsupported_grant_type` from the token endpoint), and every `401` carries a `WWW-Authenticate: Bearer` header.

## Status codes and their error codes

| Status | `error` | Meaning |
|--------|---------|---------|
| 400 | `bad_request`, `invalid_request`, `unsupported_grant_type` | The request cannot be read: invalid JSON, a value of the wrong type or shape, an unknown field, or a bad query string or header. The OAuth token endpoints also return `400`, as RFC 6749 requires. |
| 401 | `unauthorized`, `invalid_client`, `invalid_credentials` | Authentication required or credentials rejected (e.g. a wrong password on the token endpoint or on the GraphQL `loginCustomer` mutation) |
| 403 | `forbidden` | Authenticated but lacking permission for this operation |
| 404 | `not_found` | The resource in the URL or the route does not exist (e.g. the bundle options of a product that is not a bundle, or an unknown product link type) |
| 405 | `method_not_allowed` | HTTP method not supported on this route |
| 409 | `conflict` | The state of the resource does not allow the action (e.g. an order that cannot be invoiced, shipped or refunded, an invoice that cannot be captured, voided or canceled, a coupon code that already exists, the delete of a root category, another operation in progress on the same order, or an idempotency key that is still in use) |
| 422 | `unprocessable_entity` | The request can be read, but its content is not valid (e.g. a missing field, a value out of range, an unknown ID, a [revocation submission](endpoints.md#revocation-eu) past the cooling-off window, or an invalid `processedStatus` value) |
| 429 | `too_many_requests` | The client hit a rate limit (e.g. on guest order lookup, which is IP rate-limited) |
| 500 | `internal_server_error` | Unexpected server error. In production the message is "An internal error occurred", and the server logs the exception |
| 502 / 503 | `bad_gateway` / `service_unavailable` | Upstream or availability problems |

If a protocol is disabled in **System > Configuration > Services > API Platform**, any request to it returns `{"error": "protocol_disabled", ...}` regardless of path.
