---
description: Explore the Maho v2 API documentation - runtime-generated OpenAPI 3.1 and JSON-LD specs plus an opt-in Swagger UI served from /api/docs.
---

# API Documentation (Swagger UI / OpenAPI) <span class="version-badge">v26.7+</span>

The API publishes its own documentation, generated at runtime from the `#[ApiResource]` attributes on each resource. Make sure `/api/docs` is routed to `rest.php` first (see [Web Server Configuration](../../hosting/web-server.md#api-routing-map)), otherwise the request falls through to the legacy `Mage_Api` controllers.

### Machine-readable specs (always available)

| URL | Format | Use |
| --- | --- | --- |
| `/api/docs.json` | OpenAPI 3.1 (JSON) | Import into Postman/Insomnia, generate clients with `openapi-generator` |
| `/api/docs.jsonld` | Hydra / JSON-LD | Hypermedia clients |

These need no extra dependencies. A browser hitting `/api/docs` without the packages below gets the JSON-LD document (content negotiation falls back to it when no HTML renderer is available).

### Browsable Swagger UI (opt-in)

The interactive Swagger UI page at `/api/docs` (served when the browser sends `Accept: text/html`) needs two packages that are **not** part of the base install:

```bash
composer require symfony/twig-bundle symfony/asset
./maho cache:flush
```

- `symfony/twig-bundle` renders the page (also enables ReDoc and the GraphiQL explorer at `/api/graphql`).
- `symfony/asset` provides the `asset()` Twig function the Swagger UI template calls.

Both are required: with neither, `/api/docs` serves the JSON-LD document instead; with Twig but not `symfony/asset`, the page errors because its template can't resolve `asset()`. After installing, clear the compiled kernel so it picks up the new bundles:

```bash
rm -rf var/cache/api_platform/*
./maho cache:flush
```

#### Static assets

The Swagger UI / ReDoc / GraphiQL pages load their CSS, JS, and fonts from `public/bundles/apiplatform/`. The `mahocommerce/maho-composer-plugin` publishes these files automatically on every `composer install` / `update` (Maho's equivalent of Symfony's `assets:install`), so no manual step is needed.
