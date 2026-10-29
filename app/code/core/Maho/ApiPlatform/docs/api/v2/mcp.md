---
description: Expose Maho's catalog, inventory, pricing, orders and customers to AI agents over the Model Context Protocol, with an OAuth 2.1 browser login or the same JWT tokens as REST, and the same permissions.
---

# MCP (Model Context Protocol) <span class="version-badge">v26.9+</span>

Maho speaks the [Model Context Protocol](https://modelcontextprotocol.io/), so AI assistants and agents can read and operate on your store the same way they use any other MCP server.

**Endpoint:** `POST /api/mcp`
**Transport:** streamable HTTP
**Authentication:** an OAuth 2.1 [browser login](#connecting-with-a-browser-login), or the same JWT bearer token as [REST](authentication.md)

There is nothing to configure per tool. The tool catalogue is derived from the [same resource metadata](extending.md) that drives REST and GraphQL, so every resource, including those added by third-party modules, becomes a set of tools the moment it is installed. A default install exposes around 200 of them across catalog, inventory, sales, customers, content, tax and system.

!!! info "Same gates as REST"
    A tool call runs through the identical provider/processor pipeline as the matching REST request, which means it inherits the operation's `security:` expression, the caller's role permissions, admin ACL, write logging and rate limiting. A call is refused exactly when the same REST request would be. There is no separate permission surface to audit.

## Enabling it

MCP is off by default, like every other protocol, and its two supporting packages are not part of a standard install.

1. Install the packages:

    ```bash
    composer require symfony/mcp-bundle nyholm/psr7
    ```

    They ship in Maho's `suggest` list rather than as hard dependencies, so nothing pulls them in for you. Without both, the admin toggle below stays inert (the config field warns you when they are missing).

2. Go to **System → Configuration → Services → API → API Protocols**.
3. Set **MCP (Model Context Protocol)** to *Yes*.
4. Choose how the agent authenticates:
    - **Claude, ChatGPT and other connector interfaces:** on the same page, in **OAuth2 Settings**, set **Authorization Server** to *Yes*. The merchant then connects with a [browser login](#connecting-with-a-browser-login), and nothing is pasted by hand.
    - **Scripts and clients that let you set a header:** create a token for the agent, see [Authentication](authentication.md). A `client_credentials` service account scoped to just the resources the agent needs is the right choice here, not an admin token. See [Connecting with a bearer token](#connecting-with-a-bearer-token).

If you serve Maho with nginx, Caddy, or anything other than the bundled Apache config, make sure `/api/mcp` reaches `rest.php`, see [Web Server Configuration](../../hosting/web-server.md#api-routing-map). The bundled `public/.htaccess` already routes it.

!!! warning "Remote clients need the host allowlist"
    The MCP SDK ships DNS-rebinding protection that, left at its default, only answers requests whose `Host` is localhost. Maho populates the allowlist from your store's base URLs automatically, but it does so when the API container is compiled. **After changing a base URL or adding a store, clear `var/cache/api_platform`** or remote calls will fail with an opaque transport error. The same already applies to the CORS allowlist, see [Cache invalidation](extending.md#cache-invalidation).

## Connecting with a browser login

With the Authorization Server on, Maho implements the MCP authorization flow (OAuth 2.1 with PKCE), so a client connects to your store the way it connects to any hosted MCP server:

1. In the client, add a remote MCP server (a "custom connector" in Claude and ChatGPT) with the URL `https://your-domain.com/api/mcp`.
2. The client discovers the authorization server, registers itself, and opens your admin login in the browser.
3. Log in to the admin as usual. The **Authorize Application** screen shows the application that asks for access. Approve or deny it.
4. The browser returns to the client with a code, the client exchanges it for tokens, and the agent starts calling tools.

The token acts as the admin user who approved it, so the agent can do what that admin's role allows, through the same gates as REST. To approve a connection, the admin role needs the **System → API → Connected Applications** permission. An admin without it gets a denial, not a token with fewer rights.

The same admin is not asked again when the same application asks for the same scope and resource. A change in either brings the screen back.

**Connected Applications** under **System → API** lists the applications that registered. Select one and revoke it to cut its grants: refreshing fails at once, and access tokens already issued stop working when they expire.

When you deactivate an admin user, its access tokens stop working at once, because the API refuses the tokens of an inactive admin. Maho also revokes every grant that the user approved, so no application can refresh its tokens. If you activate the user again before an access token expires (1 hour by default), that access token works again until it expires. After that, each application must ask for approval again.

### Settings

All of them are under **System → Configuration → Services → API → OAuth2 Settings**:

| Setting | Default | What it does |
|---|---|---|
| Authorization Server | No | Turns the browser login on. |
| Allow Applications to Register Themselves | Yes | Dynamic client registration (RFC 7591). Registration grants nothing: an admin must still log in and approve. Turning it off blocks most connector interfaces, which have no field for a client id. |
| MCP Requires Authentication | No | *No:* an agent can browse the public catalog tools without a token, and a client is challenged only when it calls a tool that needs one. *Yes:* `/api/mcp` challenges every request without a token. Turn it on if a client does not start the browser login on its own. |
| Authorization Code Lifetime | 60 | In seconds. A code is used once, seconds after it is issued. |
| Access Token Lifetime | 3600 | In seconds. Shared with the [token endpoint](authentication.md). |
| Refresh Token Lifetime | 86400 | In seconds. Every refresh gives a new refresh token, so a client that is idle for longer than this must log in again. |

### What a client sees

A tool call that needs a token, made without one, returns HTTP `401` with a challenge that points to the metadata of the endpoint:

```http
WWW-Authenticate: Bearer resource_metadata="https://your-domain.com/.well-known/oauth-protected-resource/api/mcp"
```

From there the client reads the documents below. Paths that depend on a setting answer `404` while that setting is off.

| Path | What it is |
|---|---|
| `/.well-known/oauth-protected-resource/api/mcp` | Protected resource metadata of the MCP endpoint (RFC 9728) |
| `/.well-known/oauth-protected-resource` | Protected resource metadata of the host root |
| `/.well-known/oauth-authorization-server` | Authorization server metadata (RFC 8414) |
| `/api/oauth/authorize` | Authorization endpoint. It sends the browser to the admin login and the approval screen. |
| `/api/oauth/token` | Token endpoint, for `authorization_code` and `refresh_token` |
| `/api/oauth/register` | Dynamic client registration (RFC 7591) |
| `/.well-known/mcp.json` | MCP server card, also at `/.well-known/mcp/server-card.json` |
| `/.well-known/api-catalog` | The APIs this install serves (RFC 9727) |
| `/.well-known/ai-catalog.json` | The AI catalog that lists the MCP server card, for agents and for the Lighthouse `ard-schema` audit |

The details a client implementer needs:

- **PKCE is required**, with `S256` only. `plain` is refused.
- **Public and confidential clients:** `token_endpoint_auth_method` can be `none`, `client_secret_post` or `client_secret_basic`.
- **Redirect URIs** must use `https`, or `http` on a loopback address, and carry no fragment.
- **The only scope is `mcp`.** The admin role decides what the token can reach, not the scope.
- **Resource indicators (RFC 8707):** a token is bound to the resource the client asks for. Without a `resource` parameter it is bound to `/api/mcp`, so the rest of the v2 API refuses it.
- **Refresh tokens rotate.** A refresh token or a code that is used twice is treated as leaked, and the whole grant is revoked.

The authorize, token and registration endpoints are rate limited.

## Connecting with a bearer token

For a script, or a client that lets you set a custom header, send a token from the [token endpoint](authentication.md) on every request. This works with or without the Authorization Server.

The handshake is a normal JSON-RPC `initialize` call. The response carries a `Mcp-Session-Id` header that every subsequent message must echo back. The server accepts every protocol version from `2024-11-05` onwards and advertises the newest one it knows in the `initialize` response, so the version it replies with may be later than the one you sent; that is normal version negotiation, not an error.

=== "curl"

    ```bash
    # 1. Handshake, note the Mcp-Session-Id response header
    curl -i -X POST https://your-domain.com/api/mcp \
      -H 'Authorization: Bearer YOUR_TOKEN' \
      -H 'Content-Type: application/json' \
      -H 'Accept: application/json, text/event-stream' \
      -H 'MCP-Protocol-Version: 2025-06-18' \
      -d '{
        "jsonrpc": "2.0",
        "id": 1,
        "method": "initialize",
        "params": {
          "protocolVersion": "2025-06-18",
          "capabilities": {},
          "clientInfo": {"name": "my-agent", "version": "1.0"}
        }
      }'

    # 2. List the tools this token can call
    curl -X POST https://your-domain.com/api/mcp \
      -H 'Authorization: Bearer YOUR_TOKEN' \
      -H 'Content-Type: application/json' \
      -H 'Accept: application/json, text/event-stream' \
      -H 'MCP-Protocol-Version: 2025-06-18' \
      -H 'Mcp-Session-Id: THE_SESSION_ID' \
      -d '{"jsonrpc": "2.0", "id": 2, "method": "tools/list"}'

    # 3. Call one
    curl -X POST https://your-domain.com/api/mcp \
      -H 'Authorization: Bearer YOUR_TOKEN' \
      -H 'Content-Type: application/json' \
      -H 'Accept: application/json, text/event-stream' \
      -H 'MCP-Protocol-Version: 2025-06-18' \
      -H 'Mcp-Session-Id: THE_SESSION_ID' \
      -d '{
        "jsonrpc": "2.0",
        "id": 3,
        "method": "tools/call",
        "params": {"name": "catalog_products_get", "arguments": {"id": 394}}
      }'
    ```

=== "Client config"

    Most MCP clients take a remote server URL plus custom headers. The shape varies by client, but the two things you always need are the URL and the bearer token:

    ```json
    {
      "mcpServers": {
        "maho": {
          "type": "http",
          "url": "https://your-domain.com/api/mcp",
          "headers": {
            "Authorization": "Bearer YOUR_TOKEN"
          }
        }
      }
    }
    ```

!!! note "A pasted token expires"
    The token is a JWT with a lifetime (one hour by default), so one pasted into a client config stops working when it expires. For a long-lived agent, raise the token lifetime or have the agent re-issue via `client_credentials`, see [Authentication](authentication.md). A client that supports the [browser login](#connecting-with-a-browser-login) refreshes its own tokens, so prefer it when the client offers it.

    `GET /api/mcp` returns `405`; the endpoint is POST (plus `DELETE` to end a session and `OPTIONS` for preflight).

## Server instructions

`initialize` returns an `instructions` string that orients the model before it touches anything. It is generated from your store's own configuration and states the store name, the currency prices are quoted in, that IDs are Maho entity IDs rather than SKUs or increment IDs, that store views are selected by store code, and that list tools are paginated.

## Tool names

Names are derived from the resource's section, the static segments of its URI, and the operation verb:

```
catalog_products_list
catalog_products_get
catalog_products_create
catalog_products_update
catalog_products_delete
sales_orders_list
customers_carts_items_gift_message_update
```

The verbs are `list`, `get`, `create`, `update` and `delete`. Path variables are dropped, so a collection and its item differ only by `list` versus `get`.

These names are the protocol's wire identity: an agent's saved prompts and a client's tool allow-lists both key off them, so they are treated as stable. A resource whose section, URI or shortName changes will change its tool names, exactly as it would change its REST paths.

A representative slice of the catalogue:

| Section | Examples |
|---|---|
| Catalog | `catalog_products_*`, `catalog_categories_*`, `catalog_product_attributes_*`, `catalog_attribute_sets_*` |
| Inventory | `catalog_inventory_update`, `catalog_inventory_bulk_update` |
| Sales | `sales_orders_*`, `sales_orders_invoices_list`, `sales_orders_credit_memos_*`, `sales_orders_shipments_*`, `sales_coupons_*` |
| Customers | `customers_customers_*`, `customers_addresses_*`, `customers_customer_groups_*`, `customers_carts_*` |
| Content | `content_cms_pages_*`, `content_cms_blocks_*`, `content_blog_posts_*` |
| Tax | `tax_tax_rates_*`, `tax_tax_rules_*`, `tax_tax_classes_*` |
| System | `system_stores_*`, `system_countries_*`, `core_store_config_get` |

Call `tools/list` against your own install for the authoritative list; it reflects the modules you actually have.

Sub-resources get tools too, so an agent can work at the same granularity REST offers rather than round-tripping the whole entity: `catalog_products_tier_prices_*`, `catalog_products_media_*`, `catalog_products_custom_options_*`, `catalog_products_links_related_*` (plus `cross_sell` and `up_sell`), `sales_orders_comments_create`.

## What a tool looks like

Each tool advertises a right-sized input schema rather than the whole resource:

- **item read or delete** takes just the identifier
- **list** takes `page` and `itemsPerPage`, plus whatever filters the resource declares
- **create or update** takes the resource body, with identifiers merged in

```json
{
  "name": "catalog_products_get",
  "title": "Get Product",
  "description": "Get a product by ID",
  "inputSchema": {
    "type": "object",
    "properties": {
      "id": {
        "type": ["string", "integer"],
        "description": "Identifies the Product to act on."
      }
    },
    "required": ["id"]
  },
  "annotations": {
    "readOnlyHint": true,
    "destructiveHint": false,
    "idempotentHint": true,
    "openWorldHint": false
  }
}
```

The [annotation hints](https://modelcontextprotocol.io/specification/server/tools) let clients decide when to ask the user for confirmation. Reads are marked `readOnlyHint`, deletes are the only operations marked `destructiveHint`, and creates are the only ones marked non-idempotent.

Results come back as JSON text plus `structuredContent`, in the same JSON-LD shape REST returns, so a list tool includes `totalItems` alongside `member`:

```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "result": {
    "isError": false,
    "content": [{"type": "text", "text": "{\"@context\":\"/api/contexts/Product\",\"@type\":\"Collection\",\"totalItems\":85,\"member\":[…]}"}],
    "structuredContent": {"@type": "Collection", "totalItems": 85, "member": []}
  }
}
```

## Pagination and filtering

`tools/list` is cursor-paginated. Follow `nextCursor` until it is absent; a client that reads only the first page will see a fraction of the catalogue. Pages can also come back shorter than nominal, because permission filtering runs after paging, so treat only a missing `nextCursor` as the end, never a short page.

List *tools* paginate too. Pass `page` and `itemsPerPage` as arguments; they reach the resource exactly as the equivalent REST query parameters would.

Filters work the same way, and a list tool advertises the ones its resource declares:

| Tool | Filters |
|---|---|
| `catalog_products_list` | `search`, `sku`, `barcode`, `categoryId`, `priceMin`, `priceMax`, `sortBy`, `sortDir`, `attributeFilters`, `createdFrom`, `createdTo`, `updatedSince` |
| `sales_orders_list` | `status`, `state`, `storeId`, `customerId`, `email`, `emailLike`, `incrementId`, `createdFrom`, `createdTo`, `updatedSince` |
| `customers_customers_list` | `search`, `email`, `telephone` |
| `content_cms_pages_list`, `content_cms_blocks_list` | `identifier`, `search`, `createdFrom`, `createdTo`, `updatedSince` |
| `content_blog_posts_list` | `urlKey`, `search`, `categoryId`, `createdFrom`, `createdTo`, `updatedSince` |
| `catalog_categories_list` | `search`, `urlKey`, `parentId`, `includeInMenu` |
| `tax_tax_rates_list` | `search`, `taxCountryId` |
| `sales_coupons_list` | `code`, `is_active` |

### Attribute filters

`attributeFilters` is a JSON object of attribute codes and values: `{"manufacturer":"12","color":"49"}`. A select or multiselect attribute takes option IDs, not labels. `catalog_product_attributes_list` with `search` set to the attribute code returns the attribute with its options, and the `value` of each option is its ID. That tool needs the `product-attributes/read` permission, which an administrator has. A storefront client gets the option IDs of the filterable attributes of a category from `/layered-filters`. A value that is not a number returns a 400 error, when the options of the attribute are in the option table. In REST, the same filter is `attr_<code>=<value>`.

### Date ranges

`createdFrom`, `createdTo` and `updatedSince` take a UTC date (`2026-07-29`) or datetime (`2026-07-29 14:30:00`). A bare date on `createdTo` covers the **whole** day, so a single day is `createdFrom` and `createdTo` set to the same value:

```json
{"name": "sales_orders_list", "arguments": {"createdFrom": "2026-07-29", "createdTo": "2026-07-29"}}
```

`updatedSince` is the one to poll on for synchronisation, since it catches edits to older records that a `created` range would miss. On orders it is also accepted under its original REST name `since`, which still works but is no longer advertised: one filter with two names is a trap for an agent reading the schema as the contract.

The advertised set is derived from the resource's canonical GraphQL collection query, which is the only machine-readable declaration of what a collection filters on. Two consequences are worth knowing.

**A tool that advertises no filter may still accept one.** Providers read filters from an ad-hoc array, so a resource that filters without declaring the arguments still honours them, it just cannot tell an agent they exist. If you maintain a resource and want its filters discoverable, declare them as `args:` (or `extraArgs:`) on its `collection_query`; the same declaration serves GraphQL clients.

**An unrecognised argument is ignored, not rejected.** A filter name an agent invents is silently dropped and the call returns the unfiltered set, which reads as a filtered answer. Treat the advertised schema as the whole contract rather than guessing names.

Where a resource exposes both a top-level collection and a scoped variant, `/orders` and `/customers/me/orders`, only the top-level one carries the declared filters; the scoped variant reads a different set in its provider.

!!! note "What is deliberately not filterable"
    Storefront-facing collections hard-code their own visibility rules, so there is no filter to see past them: `catalog_products_list` is limited to enabled, catalog-visible products (a text `search` goes through the search index, so it matches search-visible products, a slightly different set), and `content_cms_pages_list`, `catalog_categories_list` and `content_blog_posts_list` to active records, with blog posts scheduled in the future hidden too. A disabled product is not reachable through the product list on any token, by design. Sub-resource lists (`catalog_products_media_list`, `sales_orders_invoices_list`, customer addresses) take the parent id and return a handful of rows, so they carry no filters beyond it. Customer date and group filtering is not available yet: that provider searches through hand-built SQL that needs porting to the collection API first.

## Tool visibility

`tools/list` is filtered to what the calling token can actually use, so an agent is not shown a catalogue it will be refused from. An unauthenticated caller sees only the public tools, which are not all reads: operations open to guests over REST, like guest-cart writes, are public here too. A service account sees the resources its role grants.

This is a usability measure, not the security boundary. Enforcement happens on the call itself, and a tool that is hidden is also refused. Filtering is deliberately optimistic: where the verdict cannot be reached without loading the entity, for instance a customer-scoped resource that compares the loaded record against the token, the tool stays listed.

## Denials

A refused call comes back as a JSON-RPC error with the reason in the message:

```json
{"jsonrpc": "2.0", "id": 3, "error": {"code": -32603, "message": "Your admin role does not grant access to \"sales/order\"."}}
```

```json
{"jsonrpc": "2.0", "id": 3, "error": {"code": -32603, "message": "Authentication required: send a Maho API bearer token with the MCP request."}}
```

The second one also comes with HTTP `401` and the `WWW-Authenticate` challenge, so a client that supports the [browser login](#connecting-with-a-browser-login) can start it.

## Keeping something out of the catalogue

Derivation is opt-out, on the principle that a resource worth exposing over REST is usually worth exposing to an agent. Useful exceptions are upload endpoints, auth handshakes, operations that return a raw file, and anything whose only sensible caller is a human in a browser. There are three levels.

**A whole resource, using Maho's attribute.** `mahoMcp: false` leaves the REST surface untouched. A hypothetical third-party resource:

```php
#[Maho\Config\ApiResource(
    mahoMcp: false,
    shortName: 'SupplierFeed',
    operations: [ /* … */ ],
)]
class SupplierFeed { }
```

**A whole resource, using API Platform's attribute.** There is no `mahoMcp` to set, so declare `mcp:` explicitly. An empty array means no tools; a populated one means you are declaring them yourself and derivation stays out of the way:

```php
#[ApiResource(
    mcp: [],
    shortName: 'ContactForm',
    operations: [ /* … */ ],
)]
class ContactForm { }
```

**A single operation.** Set the `maho_mcp` extra property to `false`:

```php
new Get(
    uriTemplate: '/products/{productId}/custom-options/{id}/file',
    extraProperties: ['maho_mcp' => false],
)
```

Core currently uses the last two: the token handshake and the contact form declare `mcp: []`, and the custom-option file download opts out per-operation. `mahoMcp: false` is there for module authors; no core resource needs it yet. See [Extending & Deployment](extending.md).

## Not the same as the Intelligence MCP server

Maho ships a second, unrelated MCP server in the `Maho_Intelligence` module. That one is **developer-facing**: it runs over stdio and exposes config paths, events, class aliases and database introspection so an AI coding assistant can understand how an install is wired. The endpoint documented here is **merchant-facing**: it runs over HTTP and exposes store data and operations. They share no transport, no tools and no configuration.
