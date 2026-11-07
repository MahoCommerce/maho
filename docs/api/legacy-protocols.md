---
description: Enable Maho's legacy Magento 1 API protocols - legacy REST, SOAP v1/v2, XML-RPC and JSON-RPC - and install the optional Laminas packages the SOAP/RPC adapters need.
---

# Legacy API Protocols

!!! warning "Legacy API"
    This page covers Maho's legacy APIs, inherited from Magento 1 and kept for backward compatibility. New integrations should use the modern [REST & GraphQL API (v2)](v2/index.md) instead.

Maho keeps the original Magento 1 API entry points alongside the modern v2 API:

| Protocol code | Path | Documentation |
|---------------|------|---------------|
| `legacy_rest` | `/api/rest` | [REST API](rest/introduction.md) |
| `soap` | `/api/soap` | [SOAP API v1](soap.md#soap) |
| `v2_soap` | `/api/v2_soap` | [SOAP API v2](soap.md#soap-api-version-v2) |
| `xmlrpc` | `/api/xmlrpc` | [XML-RPC](soap.md#xml-rpc) |
| `jsonrpc` | `/api/jsonrpc` | JSON-RPC (same resources as XML-RPC) |

These paths are handled by the original `Mage_Api_*Controller` classes, not by the API Platform kernel that serves the v2 API. Two things gate whether they actually respond.

## Every protocol is disabled by default

Every protocol (the modern `rest_v2`, `graphql`, `admin_graphql` and the legacy `legacy_rest`, `soap`, `v2_soap`, `xmlrpc`, `jsonrpc`) defaults to **off**. Enable only the protocols you use in **System → Configuration → Services → API → API Protocols**. A disabled path returns `404` at the entry point.

## SOAP / XML-RPC / JSON-RPC need optional packages

Those adapters build on Laminas components that are not installed by default - they are declared under `suggest` in `composer.json`. To use them, install the matching package(s):

```bash
composer require laminas/laminas-soap          # SOAP (soap, v2_soap)
composer require laminas/laminas-xmlrpc         # XML-RPC (xmlrpc)
composer require laminas/laminas-json-server    # JSON-RPC (jsonrpc)
```

Without them, an enabled legacy protocol errors when it tries to instantiate the adapter. The legacy REST API at `/api/rest` and the modern REST v2 / GraphQL API have no such dependency.

## Web server routing

`/api/rest` goes to `api.php`, and the four SOAP and RPC paths go to Maho's normal front controller (`index.php`). None of them go to the v2 API entry point (`rest.php`). The bundled `public/.htaccess` already routes them this way; if you use nginx, Caddy, or a custom Apache configuration, replicate the routing as shown in the [API routing map](../hosting/web-server.md#api-routing-map).
