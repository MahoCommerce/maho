# Maho API Platform, REST & GraphQL API Reference

> **Base URL:** `https://your-domain.com/api`
> **Entry Point:** `public/rest.php` (bootstraps Maho + Symfony API Platform)

## Table of Contents

- [Authentication](#authentication)
- [Request Headers](#request-headers)
- [Pagination](#pagination)
- [HTTP Caching](#http-caching)
- [Idempotency Keys](#idempotency-keys)
- [GraphQL](#graphql)
- [Endpoints](#endpoints)
  - [Auth](#auth)
  - [Store Configuration](#store-configuration)
  - [Products & Catalog](#products--catalog)
  - [Categories](#categories)
  - [Cart (Authenticated)](#cart-authenticated)
  - [Guest Cart](#guest-cart)
  - [Customers](#customers)
  - [Orders](#orders)
  - [Shipments](#shipments)
  - [Credit Memos / Refunds](#credit-memos--refunds)
  - [Invoices](#invoices)
  - [Inventory / Stock Updates](#inventory--stock-updates)
  - [Coupons / Price Rules](#coupons--price-rules)
  - [Gift Cards](#gift-cards)
  - [CMS Content](#cms-content)
  - [Blog](#blog)
  - [Media](#media)
  - [Reviews](#reviews)
  - [Revocation (EU)](#revocation-eu)
  - [Newsletter](#newsletter)
  - [Contact](#contact)
  - [Directory](#directory)
  - [Wishlist](#wishlist)
  - [URL Resolver](#url-resolver)
- [CAPTCHA](#captcha)
- [Base Classes](#base-classes)
- [Opt-in Traits](#opt-in-traits)
- [Shared Services](#shared-services)
- [API Documentation (Swagger UI / OpenAPI)](#api-documentation-swagger-ui--openapi)
- [Web Server Configuration](#web-server-configuration)
- [Adding a New API Resource](#adding-a-new-api-resource)
- [Extending the API (Third-Party Modules)](#extending-the-api-third-party-modules)

---

## Authentication

### JWT Token Authentication

All authenticated endpoints require a Bearer token in the `Authorization` header.

**Get a token:**

The endpoint dispatches by `grant_type`. Supported grants: `customer` (default), `client_credentials`, `api_user`.

```bash
# Customer login (grant_type defaults to "customer")
curl -X POST /api/rest/v2/auth/token \
  -H 'Content-Type: application/json' \
  -d '{"email": "customer@example.com", "password": "password123"}'

# OAuth2 client_credentials (recommended for integrations)
curl -X POST /api/rest/v2/auth/token \
  -H 'Content-Type: application/json' \
  -d '{"grant_type": "client_credentials", "client_id": "...", "client_secret": "..."}'

# Legacy API user (username + api_key)
curl -X POST /api/rest/v2/auth/token \
  -H 'Content-Type: application/json' \
  -d '{"grant_type": "api_user", "username": "admin", "api_key": "..."}'
```

**Response:**
```json
{
  "token": "eyJ...",
  "tokenType": "Bearer",
  "expiresIn": 3600,
  "customer": {"id": 1, "email": "...", "firstName": "...", "lastName": "..."}
}
```

`customer` is populated for the `customer` grant; `apiUser` and `permissions` are populated for `client_credentials` / `api_user` grants. There is no separate `refresh_token` field, call `/auth/refresh` with the existing JWT in the `Authorization` header to get a new token.

**Use the token:**
```bash
curl /api/rest/v2/products \
  -H 'Authorization: Bearer eyJ...'
```

**Refresh a token:** send the current (still-valid) JWT as a Bearer token; the body is ignored.
```bash
curl -X POST /api/rest/v2/auth/refresh \
  -H 'Authorization: Bearer eyJ...'
```

**Other auth endpoints:**
| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/rest/v2/auth/logout` | Revoke the current token |

Password reset and "current customer" live under the Customer resource, see [Customers](#customers).

### Permission Levels

| Level | Access |
|-------|--------|
| **Public** | Store config, countries, categories, products, CMS, blog |
| **Customer** | Own cart, orders, addresses, wishlist, reviews |
| **Admin / API** | All resources, CRUD on products, orders, inventory, coupons, credit memos, shipments |

---

## Request Headers

| Header | Required | Description |
|--------|----------|-------------|
| `Authorization` | For auth endpoints | `Bearer <jwt_token>` |
| `Content-Type` | For POST/PUT/PATCH | `application/json` or `application/ld+json` |
| `Accept` | Optional | `application/json` (default) or `application/ld+json` |
| `X-Store-Code` | Optional | Switch store context (e.g. `default`, `au`) |
| `X-Idempotency-Key` | Optional | Replay protection for mutations (see below) |
| `If-None-Match` | Optional | ETag for conditional GETs (304 support) |

---

## Pagination

Collection endpoints support pagination via query parameters (REST) or arguments (GraphQL):

| Parameter | Default | Max | Description |
|-----------|---------|-----|-------------|
| `page` | 1 |, | Page number |
| `itemsPerPage` (alias: `pageSize`) | 20 | 100 | Items per page |

**Response format depends on the negotiated content type:**

`application/json` (default): a JSON array of items. Total count and next-page links are returned via the `Link` header (`Link: <...?page=2>; rel="next"`).

`application/ld+json`: API Platform's Hydra format:

```json
{
  "@context": "/api/contexts/Product",
  "@id": "/api/rest/v2/products",
  "@type": "Collection",
  "totalItems": 150,
  "member": [...],
  "view": {
    "@id": "/api/rest/v2/products?page=1",
    "@type": "PartialCollectionView",
    "first": "/api/rest/v2/products?page=1",
    "last": "/api/rest/v2/products?page=8",
    "next": "/api/rest/v2/products?page=2"
  }
}
```

---

## HTTP Caching

The API automatically sets cache headers on GET responses:

| Endpoint Type | Cache-Control | Max-Age |
|---------------|---------------|---------|
| Public (unauthenticated) | `public` | 3600 (1 hour) |
| Auth collection | `private, must-revalidate` | 60 (1 min) |
| Auth single resource | `private, must-revalidate` | 300 (5 min) |

All responses include:
- `ETag` header (MD5 of response body)
- `Vary: Authorization, Accept`

**Conditional requests:**
```bash
# First request, note the ETag
curl -v /api/rest/v2/products/123
# < ETag: "abc123"

# Subsequent request, get 304 if unchanged
curl /api/rest/v2/products/123 -H 'If-None-Match: "abc123"'
# Returns 304 Not Modified (no body)
```

---

## Idempotency Keys

Protect against duplicate mutations by including `X-Idempotency-Key` on POST/PUT/PATCH requests. If the same key+user+path+method is seen again within 24 hours, the stored response is replayed.

```bash
# First request, processed normally
curl -X POST /api/rest/v2/orders/123/credit-memos \
  -H 'Authorization: Bearer eyJ...' \
  -H 'X-Idempotency-Key: refund-order-123-v1' \
  -H 'Content-Type: application/json' \
  -d '{"items": [{"orderItemId": 456, "qty": 1}]}'

# Duplicate request, returns stored response
curl -X POST /api/rest/v2/orders/123/credit-memos \
  -H 'Authorization: Bearer eyJ...' \
  -H 'X-Idempotency-Key: refund-order-123-v1' \
  -H 'Content-Type: application/json' \
  -d '{"items": [{"orderItemId": 456, "qty": 1}]}'
# Response includes: X-Idempotency-Replayed: true
```

**Key format:** 1-255 characters, alphanumeric + dashes + underscores (`[a-zA-Z0-9_-]`).

---

## GraphQL

The API supports GraphQL via API Platform's built-in GraphQL support.

**Endpoint:** `POST /api/graphql`
**Admin GraphQL:** `POST /api/admin/graphql` (requires admin session)

```graphql
# Example: Get a product
query {
  product(id: "/api/rest/v2/products/123") {
    id
    sku
    name
    price
  }
}

# Example: Update stock
mutation {
  updateStock(input: {sku: "ABC-123", qty: 50}) {
    sku
    qty
    previousQty
    success
  }
}
```

---

## Endpoints

### Auth

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/auth/token` | None | Get JWT token (`grant_type`: `customer`/`client_credentials`/`api_user`) |
| POST | `/auth/refresh` | Bearer JWT | Refresh JWT token (current token sent via `Authorization` header) |
| POST | `/auth/logout` | Bearer JWT | Revoke the current token |

"Current customer" and password reset are part of the Customer resource, see [Customers](#customers).

---

### Store Configuration

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/store-config` | None | Get store configuration for the current store |
| GET | `/{storeCode}/config` | None | Get store configuration for a specific store code |
| GET | `/stores` | None | List all active stores and websites |
| GET | `/stores/currencies` | None | List allowed currencies |
| POST | `/stores/switch/{storeCode}` | None | Switch store context |

Country listings live under [Directory](#directory) (`/countries`).

---

### Products & Catalog

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/products` | None | List products (paginated) |
| GET | `/products/{id}` | None | Get product by ID |
| POST | `/products` | Admin/API | Create product |
| PUT | `/products/{id}` | Admin/API | Update product |
| DELETE | `/products/{id}` | Admin/API | Delete product |

**Sub-resources** (path parameter is `{productId}` everywhere except `/options`, which uses `{sku}`):

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET / POST / PUT / DELETE | `/products/{productId}/media` | GET=None, writes=Admin/API | Product images |
| GET / POST / DELETE | `/products/{productId}/tier-prices` | Admin/API | Tier pricing (POST replaces, DELETE clears all) |
| GET / POST | `/products/{productId}/custom-options` | GET=None, POST=Admin/API | Custom options |
| PUT / DELETE | `/products/{productId}/custom-options/{id}` | Admin/API | Update/remove a custom option |
| GET | `/products/{sku}/options` | None | Custom options by SKU (resolves configurable parents) |
| GET | `/custom-option-file/{optionId}/{key}` | None | Download a customer-uploaded option file |
| GET / POST / PUT / DELETE | `/products/{productId}/bundle-options` | GET=None, writes=Admin/API | Bundle product options |
| PUT / DELETE | `/products/{productId}/bundle-options/{id}` | Admin/API | Update/remove a bundle option |
| GET / PUT | `/products/{productId}/configurable` | GET=None, PUT=Admin/API | Read super-attributes + child IDs / set them all |
| POST | `/products/{productId}/configurable/children` | Admin/API | Add a child product (body: `{childId: int}`) |
| DELETE | `/products/{productId}/configurable/children/{childId}` | Admin/API | Remove a child product |
| GET / POST / PUT / DELETE | `/products/{productId}/downloadable-links` | GET=None, writes=Admin/API | Downloadable links |
| GET / POST / PUT | `/products/{productId}/grouped` | GET=None, writes=Admin/API | Grouped product links (PUT replaces all) |
| DELETE | `/products/{productId}/grouped/{childProductId}` | Admin/API | Remove a grouped child |
| GET / POST / PUT | `/products/{productId}/links/related` | GET=None, writes=Admin/API | Related products (POST adds one, PUT replaces all) |
| DELETE | `/products/{productId}/links/related/{linkedProductId}` | Admin/API | Remove a related link |
| GET / POST / PUT | `/products/{productId}/links/cross-sell` | GET=None, writes=Admin/API | Cross-sell links (POST adds one, PUT replaces all) |
| DELETE | `/products/{productId}/links/cross-sell/{linkedProductId}` | Admin/API | Remove a cross-sell link |
| GET / POST / PUT | `/products/{productId}/links/up-sell` | GET=None, writes=Admin/API | Up-sell links (POST adds one, PUT replaces all) |
| DELETE | `/products/{productId}/links/up-sell/{linkedProductId}` | Admin/API | Remove an up-sell link |
| GET / POST | `/products/{productId}/reviews` | GET=None, POST=Customer | Reviews for a product |

**Layered navigation:**

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/layered-filters` | None | Get layered navigation filters |

---

### Categories

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/categories` | None | List categories (tree) |
| GET | `/categories/{id}` | None | Get category by ID |
| POST | `/categories` | Admin/API | Create category |
| PUT | `/categories/{id}` | Admin/API | Update category |
| DELETE | `/categories/{id}` | Admin/API | Delete category |

---

### Cart (Authenticated)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/carts/{id}` | Customer/Admin/API | Get a cart by numeric ID (ownership enforced via `verifyCartAccess()`) |
| POST | `/carts` | Customer/Admin/API | Create a new cart for the authenticated customer |
| POST | `/carts/{id}/items` | Customer/Admin/API | Add item to cart |
| PUT | `/carts/{id}/items/{itemId}` | Customer/Admin/API | Update cart item quantity |
| DELETE | `/carts/{id}/items/{itemId}` | Customer/Admin/API | Remove cart item |

---

### Guest Cart

`{id}` is the masked cart ID returned by `POST /guest-carts`.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/guest-carts` | None | Create a guest cart |
| GET | `/guest-carts/{id}` | None | Get a guest cart by masked ID |
| POST | `/guest-carts/{id}/items` | None | Add item to cart |
| PUT | `/guest-carts/{id}/items/{itemId}` | None | Update cart item quantity |
| DELETE | `/guest-carts/{id}/items/{itemId}` | None | Remove cart item |
| GET | `/guest-carts/{id}/totals` | None | Get cart totals |
| POST | `/guest-carts/{id}/coupon` | None | Apply coupon code |
| DELETE | `/guest-carts/{id}/coupon` | None | Remove coupon |
| POST | `/guest-carts/{id}/giftcards` | None | Apply gift card |
| DELETE | `/guest-carts/{id}/giftcards/{code}` | None | Remove gift card |
| POST | `/guest-carts/{id}/shipping-methods` | None | Get available shipping methods |
| GET | `/guest-carts/{id}/payment-methods` | None | Get available payment methods |
| POST | `/guest-carts/{id}/place-order` | None | Place order from guest cart |

---

### Customers

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/customers` | Admin/API | List customers |
| GET | `/customers/{id}` | Customer/Admin/API | Get customer by ID |
| POST | `/customers` | None | Register a customer |
| PUT | `/customers/me` | Customer/API | Update current customer profile |
| POST | `/customers/me/password` | Customer/API | Change password |
| GET | `/customers/me` | Customer/API | Get current authenticated customer |
| GET | `/customers/me/orders` | Customer/API | List own orders |
| GET | `/customers/me/reviews` | Customer/API | List own reviews |
| POST | `/customers/forgot-password` | None | Request password reset email |
| POST | `/customers/reset-password` | None | Reset password with token |
| POST | `/customers/create-from-order` | None | Create a customer account from a placed guest order |

**Addresses** (`Address` resource, same DTO is exposed under three URL families):

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/customers/me/addresses` | Customer/API | List own addresses |
| POST | `/customers/me/addresses` | Customer/API | Create an address for the current customer |
| GET | `/customers/me/addresses/{id}` | Customer/API | Get one of the current customer's addresses |
| PUT | `/customers/me/addresses/{id}` | Customer/API | Update an address |
| DELETE | `/customers/me/addresses/{id}` | Customer/API | Delete an address |
| GET | `/addresses` | Customer/API | List own addresses (alias of `/customers/me/addresses`) |
| POST | `/addresses` | Customer/API | Create an address |
| GET | `/addresses/{id}` | Customer/API | Get an address by ID |
| PUT | `/addresses/{id}` | Customer/API | Update an address |
| DELETE | `/addresses/{id}` | Customer/API | Delete an address |
| GET | `/customers/{customerId}/addresses` | Admin/API | List a customer's addresses |
| POST | `/customers/{customerId}/addresses` | Admin/API | Create an address for a customer |

---

### Orders

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/orders` | Admin/API | List orders (paginated) |
| GET | `/orders/{id}` | Customer/Admin/API | Get order by ID (customers see only their own) |
| POST | `/orders` | None | Place an order from a customer or guest cart |

**Order sub-resources:**

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/orders/{orderId}/shipments` | Admin/API | List shipments for order |
| POST | `/orders/{orderId}/shipments` | Admin/API | Create shipment |
| GET | `/orders/{orderId}/credit-memos` | Admin/API | List credit memos for order |
| POST | `/orders/{orderId}/credit-memos` | Admin/API | Create credit memo/refund |
| GET | `/orders/{orderId}/invoices` | Customer/Admin/API | List invoices for order |
| GET | `/orders/{orderId}/invoices/{id}/pdf` | Customer/Admin/API | Download invoice PDF |

**Customer invoice access:**

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/customers/me/orders/{orderId}/invoices` | Customer/API | List own invoices |
| GET | `/customers/me/orders/{orderId}/invoices/{id}/pdf` | Customer/API | Download own invoice PDF |

---

### Shipments

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/shipments/{id}` | Admin/API | Get shipment by ID |

**Create shipment:**
```bash
curl -X POST /api/rest/v2/orders/123/shipments \
  -H 'Authorization: Bearer eyJ...' \
  -H 'Content-Type: application/json' \
  -d '{
    "items": [{"orderItemId": 456, "qty": 2}],
    "tracks": [{"carrierCode": "auspost", "title": "Australia Post", "trackNumber": "AP123456"}],
    "comment": "Shipped via express",
    "notifyCustomer": true
  }'
```

Omit `items` to ship every remaining item on the order. Each track entry needs at least `trackNumber`; `carrierCode` defaults to `custom` and `title` defaults to the carrier code.

---

### Credit Memos / Refunds

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/credit-memos/{id}` | Admin/API | Get credit memo by ID |
| GET | `/orders/{orderId}/credit-memos` | Admin/API | List credit memos for order |
| POST | `/orders/{orderId}/credit-memos` | Admin/API | Create credit memo |

**Create a credit memo:**
```bash
curl -X POST /api/rest/v2/orders/123/credit-memos \
  -H 'Authorization: Bearer eyJ...' \
  -H 'Content-Type: application/json' \
  -d '{
    "items": [
      {"orderItemId": 456, "qty": 1, "backToStock": true}
    ],
    "comment": "Customer returned item",
    "adjustmentPositive": 5.00,
    "adjustmentNegative": 0,
    "offlineRefund": true
  }'
```

**Response:**
```json
{
  "id": 789,
  "incrementId": "100000001",
  "orderId": 123,
  "orderIncrementId": "100000456",
  "state": "refunded",
  "grandTotal": 29.95,
  "baseGrandTotal": 29.95,
  "subtotal": 24.95,
  "taxAmount": 0,
  "shippingAmount": 0,
  "discountAmount": 0,
  "adjustmentPositive": 5.00,
  "adjustmentNegative": 0,
  "items": [
    {
      "id": 101,
      "orderItemId": 456,
      "sku": "TENNIS-BALL-3PK",
      "name": "Tennis Balls (3 pack)",
      "qty": 1,
      "price": 24.95,
      "rowTotal": 24.95,
      "taxAmount": 0,
      "discountAmount": 0,
      "backToStock": true
    }
  ],
  "comment": "Customer returned item"
}
```

**Parameters:**
- `items[]`, Array of items to refund. Each requires `orderItemId` and `qty`. Optional: `backToStock` (boolean, returns qty to inventory).
- `comment`, Optional refund note.
- `adjustmentPositive`, Additional positive adjustment (add to refund).
- `adjustmentNegative`, Negative adjustment (reduce refund).
- `offlineRefund`, `true` (default) for offline refund, `false` to trigger payment gateway refund.

**GraphQL:**
```graphql
mutation {
  createCreditMemo(input: {
    orderId: 123
    items: [{orderItemId: 456, qty: 1, backToStock: true}]
    comment: "Returned"
    offlineRefund: true
  }) {
    id
    incrementId
    state
    grandTotal
  }
}
```

---

### Invoices

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/orders/{orderId}/invoices` | Customer/Admin/API | List invoices for order |
| GET | `/orders/{orderId}/invoices/{id}/pdf` | Customer/Admin/API | Download invoice PDF |
| GET | `/customers/me/orders/{orderId}/invoices` | Customer/API | List own invoices |
| GET | `/customers/me/orders/{orderId}/invoices/{id}/pdf` | Customer/API | Download own invoice PDF |

There is no standalone collection endpoint or write endpoint for invoices, they are produced as part of the order workflow.

---

### Inventory / Stock Updates

Fast direct-SQL stock updates, no model overhead, no observers.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| PUT | `/inventory` | Admin/API | Update single SKU stock |
| PUT | `/inventory/bulk` | Admin/API | Bulk update (max 100 items) |

**Single update:**
```bash
curl -X PUT /api/rest/v2/inventory \
  -H 'Authorization: Bearer eyJ...' \
  -H 'Content-Type: application/json' \
  -d '{
    "sku": "TENNIS-BALL-3PK",
    "qty": 150,
    "isInStock": true,
    "manageStock": true
  }'
```

**Response:**
```json
{
  "sku": "TENNIS-BALL-3PK",
  "qty": 150,
  "isInStock": true,
  "manageStock": true,
  "previousQty": 42,
  "success": true
}
```

**Bulk update:**
```bash
curl -X PUT /api/rest/v2/inventory/bulk \
  -H 'Authorization: Bearer eyJ...' \
  -H 'Content-Type: application/json' \
  -d '{
    "items": [
      {"sku": "TENNIS-BALL-3PK", "qty": 150},
      {"sku": "RACQUET-PRO-V2", "qty": 25, "isInStock": true},
      {"sku": "GRIP-TAPE-WHT", "qty": 0}
    ]
  }'
```

**Notes:**
- `isInStock` auto-sets to `qty > 0` if not provided.
- `manageStock` defaults to `true` if not provided.
- Qty must be 0–99,999,999.
- Bulk limit: 100 items per request (validated upfront, executed in a DB transaction).

**GraphQL:**
```graphql
mutation {
  updateStock(input: {sku: "TENNIS-BALL-3PK", qty: 150}) {
    sku
    qty
    previousQty
    success
  }
}

mutation {
  updateStockBulk(input: {
    items: [
      {sku: "TENNIS-BALL-3PK", qty: 150},
      {sku: "RACQUET-PRO-V2", qty: 25}
    ]
  }) {
    success
    results {
      sku
      qty
      previousQty
    }
  }
}
```

---

### Coupons / Price Rules

Full CRUD for coupon/discount rule management + validation.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/coupons` | Admin/API | List coupons (paginated, filterable) |
| GET | `/coupons/{id}` | Admin/API | Get coupon by ID |
| POST | `/coupons` | Admin/API | Create coupon + rule |
| PUT | `/coupons/{id}` | Admin/API | Update coupon/rule |
| DELETE | `/coupons/{id}` | Admin/API | Delete coupon + rule |
| POST | `/coupons/validate` | None | Validate a coupon code (public, used by storefront checkouts) |

**Create a coupon:**
```bash
curl -X POST /api/rest/v2/coupons \
  -H 'Authorization: Bearer eyJ...' \
  -H 'Content-Type: application/json' \
  -d '{
    "code": "SUMMER25",
    "discountType": "percent",
    "discountAmount": 25,
    "description": "Summer sale 25% off",
    "isActive": true,
    "usageLimit": 500,
    "usagePerCustomer": 1,
    "fromDate": "2026-01-01",
    "toDate": "2026-03-31",
    "minimumSubtotal": 50
  }'
```

**Discount types:**
| API Value | Maho Action | Description |
|-----------|-------------|-------------|
| `percent` | `by_percent` | Percentage off each item |
| `fixed` | `by_fixed` | Fixed amount off each item |
| `cart_fixed` | `cart_fixed` | Fixed amount off cart total |
| `buy_x_get_y` | `buy_x_get_y` | Buy X get Y free |

**Validate a coupon:**
```bash
curl -X POST /api/rest/v2/coupons/validate \
  -H 'Authorization: Bearer eyJ...' \
  -H 'Content-Type: application/json' \
  -d '{"code": "SUMMER25"}'
```

**Response:**
```json
{
  "id": 0,
  "code": "SUMMER25",
  "isValid": true,
  "validationMessage": "Coupon is valid",
  "discountType": "percent",
  "discountAmount": 25,
  "ruleName": "Summer sale 25% off"
}
```

**Collection filters:**
```
GET /api/rest/v2/coupons?code=SUMMER          # Filter by code (LIKE search)
GET /api/rest/v2/coupons?isActive=true        # Filter by active status
GET /api/rest/v2/coupons?page=2&itemsPerPage=50
```

**GraphQL:**
```graphql
mutation {
  createCoupon(input: {
    code: "SUMMER25"
    discountType: "percent"
    discountAmount: 25
    isActive: true
  }) {
    id
    code
    ruleId
  }
}

mutation {
  validateCoupon(input: {code: "SUMMER25"}) {
    isValid
    validationMessage
    discountType
    discountAmount
  }
}
```

---

### Gift Cards

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/giftcards/{id}` | Admin/API | Get a gift card by ID |
| POST | `/giftcards` | Admin/API | Create a new gift card |

Balance lookups and adjustments are exposed via GraphQL only (`checkGiftcardBalance`, `adjustGiftcardBalance`).

---

### CMS Content

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/cms-pages` | None | List CMS pages |
| GET | `/cms-pages/{id}` | None | Get CMS page |
| POST | `/cms-pages` | Admin/API | Create CMS page |
| PUT | `/cms-pages/{id}` | Admin/API | Update CMS page |
| DELETE | `/cms-pages/{id}` | Admin/API | Delete CMS page |
| GET | `/cms-blocks` | None | List CMS blocks |
| GET | `/cms-blocks/{id}` | None | Get CMS block |
| POST | `/cms-blocks` | Admin/API | Create CMS block |
| PUT | `/cms-blocks/{id}` | Admin/API | Update CMS block |
| DELETE | `/cms-blocks/{id}` | Admin/API | Delete CMS block |

---

### Blog

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/blog-posts` | None | List blog posts |
| GET | `/blog-posts/{id}` | None | Get blog post |
| POST | `/blog-posts` | Admin/API | Create blog post |
| PUT | `/blog-posts/{id}` | Admin/API | Update blog post |
| DELETE | `/blog-posts/{id}` | Admin/API | Delete blog post |
| GET | `/blog-categories` | None | List blog categories |
| GET | `/blog-categories/{id}` | None | Get blog category |

---

### Media

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/media` | Admin/API | List image files in a folder under `wysiwyg/` |
| POST | `/media` | Admin/API | Upload an image (multipart/form-data; auto-converted to the configured format) |
| DELETE | `/media/{path}` | Admin/API | Delete a media file (path must be inside `wysiwyg/`) |

---

### Reviews

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/reviews/{id}` | None | Get review by ID |
| GET | `/products/{productId}/reviews` | None | List reviews for product |
| POST | `/products/{productId}/reviews` | Customer/API | Submit a review (requires authentication) |
| GET | `/customers/me/reviews` | Customer/API | List own reviews |

---

### Revocation (EU)

Contract revocation declarations under EU Directive 2023/2673 (the "revocation button"). The API exposes the **authenticated** channel: a logged-in customer revokes one of their own orders, and admins list and process the declarations. The public, unauthenticated web form remains at `/revocation` and is not part of the API.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/customers/me/revocation-requests` | Customer/API | Submit a revocation against your own order |
| GET | `/customers/me/revocation-requests` | Customer/API | List your own declarations |
| GET | `/revocation-requests` | Admin/API | List all declarations |
| GET | `/revocation-requests/{id}` | Customer/Admin/API | Get one declaration (own request for customers, any for admins) |
| PUT | `/revocation-requests/{id}` | Admin/API | Set the processing status and internal note |

**Submit body:**
```bash
curl -X POST /api/rest/v2/customers/me/revocation-requests \
  -H 'Authorization: Bearer <customer_jwt>' \
  -H 'Content-Type: application/json' \
  -d '{"orderId": 1234, "reason": "I changed my mind"}'
```
- `orderId` (int) or `orderReference` (order increment ID) identifies the order; one is required.
- Ownership is re-checked server-side: an order that isn't the authenticated customer's returns `404`.
- Because the customer is authenticated and owns the order, the recorded declaration is **verified** (`verified: true`), the same trust level as the my-account web link. The declaration row is the legal receipt and is always written, even if the receipt/notification emails are suppressed.
- The submission is gated by the store's cooling-off window; an order past it returns `422`.
- Disabled revocation (`revocation/general/enabled = 0`) returns `404`.

**Response fields:** `id`, `orderId`, `orderReference`, `reason`, `customerName`, `email`, `verified`, `storeId`, `receivedAt`, `processedStatus`, `processedAt`, `suppressedAt`, `suppressedReason`. The internal-only fields `adminNote`, `ip`, and `userAgent` are returned **only** to admins.

**Admin processing:**
```bash
curl -X PUT /api/rest/v2/revocation-requests/1234 \
  -H 'Authorization: Bearer <admin_jwt>' \
  -H 'Content-Type: application/json' \
  -d '{"processedStatus": "accepted", "adminNote": "Refund issued"}'
```
- `processedStatus` must be one of `accepted`, `rejected`, `info_requested`; anything else returns `422`. Setting it stamps `processedAt`.

**GraphQL:** `myRevocationRequests` (customer's own declarations), `submitRevocation(orderId / orderReference, reason)` mutation, plus the standard `revocationRequest` item / collection queries.

---

### Newsletter

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/newsletter/subscribe` | None | Subscribe to newsletter (gated by `newsletter/subscription/allow_guest_subscribe`) |
| POST | `/newsletter/unsubscribe` | None | Unsubscribe by email |
| GET | `/newsletter/status` | Customer/API | Get subscription status |

**Guest subscription control:** Guest (unauthenticated) subscribe is controlled by the Maho config flag `newsletter/subscription/allow_guest_subscribe` (**System > Config > Newsletter > Subscription Options > Allow Guest Subscription**). When disabled, only authenticated customers can subscribe. Recommended: set to **No** for API use to prevent abuse.

**Confirmation emails:** When `newsletter/subscription/confirm` is enabled, new subscriptions receive a confirmation email and remain inactive until confirmed (double opt-in).

---

### Contact

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/contact` | None | Submit contact form |
| GET | `/contact/config` | None | Get contact form config |

`GET /contact/config` response example:

```json
{
  "id": "contact",
  "enabled": true,
  "captchaProvider": "turnstile",
  "captchaSiteKey": "0x4AAA...",
  "honeypotField": "_h_a4b2c1d3"
}
```

`captchaProvider` is one of `none`, `turnstile`, `recaptcha_v3` (or anything an installed third-party module registers). `captchaSiteKey` is `null` when the provider is `none`. Frontends use these two fields to load the matching widget client-side; for richer per-provider config the helper-based event flow described under [CAPTCHA](#captcha) is used instead.

The `honeypotField` value is **deterministic per install** (derived from the encryption key) and **opaque** to the frontend, render it as a hidden input and don't expose its value, e.g. `<input type="text" name="{honeypotField}" style="display:none" tabindex="-1" autocomplete="off" />`. If a request body arrives with a non-empty value in that field, the API silently treats it as spam (returns success without sending the email). When honeypot is disabled in admin, `honeypotField` is `null` and the frontend can skip it.

---

### Directory

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/countries` | None | List countries |
| GET | `/countries/{id}` | None | Get country (with regions) |

---

### Wishlist

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/customers/me/wishlist` | Customer/API | Get wishlist items |
| POST | `/customers/me/wishlist` | Customer/API | Add to wishlist |
| DELETE | `/customers/me/wishlist/{id}` | Customer/API | Remove from wishlist |
| POST | `/customers/me/wishlist/{id}/move-to-cart` | Customer/API | Move item to cart |
| POST | `/customers/me/wishlist/sync` | Customer/API | Sync a guest (localStorage) wishlist into the customer's wishlist |

---

### URL Resolver

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/url-resolver?path=/some-page` | None | Resolve URL to entity |

---

## Error Responses

All errors return JSON with an appropriate HTTP status code:

```json
{
  "type": "https://tools.ietf.org/html/rfc2616#section-10",
  "title": "An error occurred",
  "status": 400,
  "detail": "SKU is required"
}
```

| Status | Meaning |
|--------|---------|
| 400 | Bad request / validation error |
| 401 | Authentication required |
| 403 | Insufficient permissions |
| 404 | Resource not found |
| 405 | Method not allowed |
| 409 | Conflict (e.g. duplicate idempotency key race condition) |
| 500 | Internal server error |

---

## CAPTCHA

The API Platform does not bundle any CAPTCHA provider. Instead, it exposes two events that any captcha module can observe, making the system completely provider-agnostic.

### How it works

**Configuration**, endpoints that need to advertise CAPTCHA settings to a frontend (e.g. `GET /contact/config`) read from store config and/or the `api_captcha_config` event. The exact fields exposed depend on the endpoint; for `/contact/config` they are flat: `captchaProvider`, `captchaSiteKey`, `enabled`. Other endpoints (or third-party modules calling `Mage::helper('apiplatform')->getCaptchaConfig()`) get the open key/value bag populated by the event.

The frontend uses this to load the right widget (Turnstile, reCAPTCHA, etc.) and obtain a token.

**Verification**, on form submission, include the solved token as `captchaToken` in the request body. The API dispatches `api_verify_captcha` and the active module verifies it.

### Events

| Event | Purpose | Observer parameters |
|-------|---------|---------------------|
| `api_captcha_config` | Describe the active provider to the frontend | `config` (DataObject, set `enabled`, `provider`, and any provider-specific fields like `challengeUrl` or `siteKey`) |
| `api_verify_captcha` | Verify a submitted token | `result` (DataObject, set `verified` to `false` and `error` to a message string to reject), `data` (array, the full request body, token is in `captchaToken`) |

### Helper methods

Any API controller or processor can verify captcha tokens via the ApiPlatform helper:

```php
/** @var Maho_ApiPlatform_Helper_Data $helper */
$helper = Mage::helper('apiplatform');

// Get config for the frontend
$captchaConfig = $helper->getCaptchaConfig();

// Verify a token, returns null on success, error message on failure
$error = $helper->verifyCaptcha($requestData);
if ($error !== null) {
    // reject the request
}
```

### Built-in: Altcha (Maho_Captcha)

The native `Maho_Captcha` module observes both events out of the box using [Altcha](https://altcha.org/), a self-hosted, privacy-friendly proof-of-work challenge that requires no third-party API calls.

### Third-party providers

A Turnstile or reCAPTCHA module just needs to observe the same two events. For example:

```xml
<config>
    <api>
        <events>
            <api_captcha_config>
                <observers>
                    <my_turnstile>
                        <class>my_turnstile/observer</class>
                        <method>getCaptchaConfig</method>
                    </my_turnstile>
                </observers>
            </api_captcha_config>
            <api_verify_captcha>
                <observers>
                    <my_turnstile>
                        <class>my_turnstile/observer</class>
                        <method>verifyCaptcha</method>
                    </my_turnstile>
                </observers>
            </api_verify_captcha>
        </events>
    </api>
</config>
```

```php
class My_Turnstile_Model_Observer
{
    public function getCaptchaConfig(\Maho\Event\Observer $observer): void
    {
        $config = $observer->getEvent()->getConfig();
        $config->setEnabled(true);
        $config->setProvider('turnstile');
        $config->setSiteKey(Mage::getStoreConfig('my_turnstile/general/site_key'));
    }

    public function verifyCaptcha(\Maho\Event\Observer $observer): void
    {
        $data = $observer->getEvent()->getData('data');
        $token = $data['captchaToken'] ?? '';
        $result = $observer->getEvent()->getResult();

        // Call Turnstile verify API...
        if (!$this->verifyToken($token)) {
            $result->setVerified(false);
            $result->setError('CAPTCHA verification failed.');
        }
    }
}
```

---

## Architecture

The API is built on [API Platform](https://api-platform.com/) (Symfony) integrated with Maho Commerce (PHP 8.3+, fork of OpenMage/Magento 1).

- **Entry point:** `public/rest.php`, bootstraps Maho, then hands off to Symfony
- **Resources:** PHP 8 `#[ApiResource]` DTOs, all extend `\Maho\ApiPlatform\Resource`
- **Providers:** State providers (read operations), all extend `\Maho\ApiPlatform\Provider`
- **Processors:** State processors (write operations), all extend `\Maho\ApiPlatform\Processor`
- **Event listeners:** Symfony listeners for cross-cutting concerns (caching, idempotency)
- **Authentication:** JWT (HS256) via Firebase JWT library

**Module structure:**
```
app/code/core/Maho/ApiPlatform/
├── symfony/src/
│   ├── Resource.php         # Base class for all DTOs ($extensions)
│   ├── Provider.php         # Base class for all providers (auth + pagination)
│   ├── Processor.php        # Base class for all processors (auth + persistence)
│   ├── Trait/               # Opt-in traits (ProductLoader, Cache, ActivityLog, StoreAccess)
│   ├── Service/             # Shared services (StoreContext, mappers, etc.)
│   ├── Security/            # Authentication (JWT, OAuth2, user providers)
│   ├── EventListener/       # Cross-cutting concerns
│   └── ...
├── docs/                    # This documentation
├── etc/config.xml           # Module config
└── sql/                     # DB migration scripts

app/code/core/Mage|Maho/*/Api/  # Per-module API resources
├── {Entity}.php             # DTO (extends \Maho\ApiPlatform\Resource)
├── {Entity}Provider.php     # State provider (extends \Maho\ApiPlatform\Provider)
└── {Entity}Processor.php    # State processor (extends \Maho\ApiPlatform\Processor)
```

### Base Classes

All API classes extend one of three base classes in `Maho\ApiPlatform`:

#### Resource

Base class for all DTOs. Provides the `$extensions` property for the event-based extension system.

```php
#[ApiResource(...)]
class MyResource extends \Maho\ApiPlatform\Resource
{
    public ?int $id = null;
    public string $name = '';
    // $extensions is inherited from the base class
}
```

#### Provider

Base class for all state providers. Bundles authentication (via `AuthenticationTrait`) and pagination (via `PaginationTrait`). Provides a Security constructor that subclasses can call via `parent::__construct($security)`.

```php
final class MyProvider extends \Maho\ApiPlatform\Provider
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        // Authentication methods available: isAdmin(), requireAuthentication(), etc.
        // Pagination available: $this->extractPagination($context)
    }
}
```

#### Processor

Base class for all state processors. Bundles authentication (via `AuthenticationTrait`) and model persistence (via `ModelPersistenceTrait`). Provides `safeSave()`, `safeDelete()`, `secureAreaDelete()`, and `loadOrFail()`.

```php
final class MyProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $user = $this->getAuthorizedUser();
        $this->requirePermission($user, 'myresource/write');

        $model = Mage::getModel('mymodule/entity');
        $model->setData([...]);
        $this->safeSave($model, 'create entity');
    }
}
```

### Opt-in Traits

Domain-specific traits that can be added to providers or processors as needed:

| Trait | Purpose | Used by |
|---|---|---|
| `ProductLoaderTrait` | Loads a product by ID with store context and optional type constraint | Catalog sub-resource providers/processors |
| `CacheTrait` | Cache-aside `remember()` helper for provider responses | ReviewProvider |
| `ActivityLogTrait` | Logs write operations to the admin activity log | Product, Category, CMS, Blog processors |
| `StoreAccessTrait` | Resolves store codes to IDs and validates store-level access | CMS and Blog processors |

### Shared Services

Live under `app/code/core/Maho/ApiPlatform/symfony/Service/`:

| Service | Purpose |
|---|---|
| `StoreContext` | Store scope management, `ensureStore()`, `getStoreId()`, `storeIdsToStoreCodes()`, `isAvailableForStore()` |
| `JwtService` | JWT issuance/validation for customer and API-user tokens |
| `TokenBlacklist` | Tracks revoked JWT IDs (used by `/auth/logout` and on password change) |
| `StoreDefaults` | Resolves default values per store (currency, locale, etc.) used during DTO building |

---

## API Documentation (Swagger UI / OpenAPI)

The API publishes its own documentation, generated at runtime from the `#[ApiResource]` attributes on each resource. Make sure `/api/docs` is routed to `rest.php` first (see [Web Server Configuration](#web-server-configuration)), otherwise the request falls through to the legacy `Mage_Api` controllers.

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

The Swagger UI / ReDoc / GraphiQL pages load CSS, JS, and fonts from `public/bundles/apiplatform/`. Maho has no `assets:install` console command, so the `mahocommerce/maho-composer-plugin` publishes these files automatically on every `composer install`/`update` (copying them from `vendor/api-platform/core/.../Resources/public`). If the page renders unstyled with `404`s under `/bundles/apiplatform/*`, re-run `composer install`, or publish them manually:

```bash
ln -snf ../../vendor/api-platform/core/src/Symfony/Bundle/Resources/public public/bundles/apiplatform
```

---

## Web Server Configuration

All web servers must route the new API URLs (`/api/rest/v2/*`, `/api/graphql`, `/api/admin/graphql`, `/api/docs`) to `rest.php` (the Symfony API Platform entry point), while letting legacy paths (`/api/rest`, `/api/soap`, `/api/v2_soap`, `/api/xmlrpc`, `/api/jsonrpc`) fall through to the original Magento 1 controllers. Below are example configurations for the three most common setups.

### Why rest.php, not index.php?

`rest.php` boots the Symfony API Platform kernel directly. `index.php` boots the full Maho front-controller stack and then hands off to Symfony via `Maho_ApiPlatform_IndexController::indexAction`. The first path is ~50-100 ms faster per request, noticeable under chatty API clients (POS terminals, headless storefronts making 5-10 requests per user action).

Both paths end up at the same Symfony kernel; `rest.php` is just leaner. Maho is still initialised inside `rest.php` so store context, models, and config are available to API Platform resolvers.

### Rewrite rules are mandatory

The new API URLs are served **only** through `rest.php`; there is no `index.php` fallback. Without the rewrite rules below, `/api/*` requests fall through to Maho's normal front controller, where the legacy `Mage_Api` router claims them and tries to dispatch SOAP/XML-RPC, producing a fatal error instead of the API response. Configure the rules for your web server before using the API.

The bundled `public/.htaccess` already implements this routing for Apache. The snippets below are for installations using nginx/Caddy, or for operators who need to replicate the behaviour in a different web server.

### Legacy SOAP / XMLRPC / JSONRPC

`/api/rest`, `/api/soap`, `/api/v2_soap`, `/api/xmlrpc`, `/api/jsonrpc` are legacy Magento 1 API paths handled by the original `Mage_Api_*Controller` classes. The default `.htaccess` explicitly excludes them from the `rest.php` rewrite so they keep working unchanged for existing consumers.

### Nginx

Add these blocks **before** the main `location /` block in your nginx config.

```nginx
# API Platform endpoints (new REST + GraphQL + docs), no basic auth required.
# Matches /api/rest/v2/*, /api/graphql, /api/admin/graphql, /api/docs.
# Explicitly EXCLUDES legacy paths (/api/rest, /api/soap, /api/v2_soap,
# /api/xmlrpc, /api/jsonrpc) so the original Magento 1 controllers keep
# handling them.
location ~ ^/api/(rest/v2(/|$)|graphql$|admin/graphql$|docs(/|\.|$)) {
    # Bypass any site-wide basic auth / IP restrictions
    satisfy any;
    allow all;
    auth_basic off;

    # CORS headers for API access
    add_header 'Access-Control-Allow-Origin' '*' always;
    add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, PATCH, DELETE, OPTIONS' always;
    add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization, Accept, X-Store-Code, X-Idempotency-Key, If-None-Match' always;
    add_header 'Access-Control-Expose-Headers' 'ETag, X-Idempotency-Replayed, Link' always;

    # Handle preflight requests
    if ($request_method = 'OPTIONS') {
        add_header 'Access-Control-Allow-Origin' '*';
        add_header 'Access-Control-Allow-Methods' 'GET, POST, PUT, PATCH, DELETE, OPTIONS';
        add_header 'Access-Control-Allow-Headers' 'Content-Type, Authorization, Accept, X-Store-Code, X-Idempotency-Key, If-None-Match';
        add_header 'Access-Control-Max-Age' 1728000;
        add_header 'Content-Type' 'text/plain; charset=utf-8';
        add_header 'Content-Length' 0;
        return 204;
    }

    # Route to Symfony API Platform via rest.php
    try_files $uri /rest.php$is_args$args;
}

# REST API PHP handler - no basic auth required
location = /rest.php {
    satisfy any;
    allow all;
    auth_basic off;

    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/your-pool.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param PATH_INFO $fastcgi_path_info;
    fastcgi_param PHP_VALUE "upload_max_filesize=100M \n post_max_size=100M \n max_execution_time=600";
    fastcgi_read_timeout 600;
    fastcgi_send_timeout 600;
    fastcgi_connect_timeout 60;
}

# Optional: Rate limiting for public mutation endpoints
# Define zone in the http {} block:
#   limit_req_zone $binary_remote_addr zone=api_write:10m rate=10r/s;
#
# Then add a location block BEFORE the API Platform block above:
#   location ~ ^/api/rest/v2/(newsletter|contact|auth/token|guest-carts) {
#       satisfy any;
#       allow all;
#       auth_basic off;
#       limit_req zone=api_write burst=5 nodelay;
#       try_files $uri /rest.php$is_args$args;
#   }
```

### Apache (.htaccess)

Add these rules to your `public/.htaccess` **before** the main `RewriteRule .* index.php` catch-all.

```apacheconf
<IfModule mod_rewrite.c>
    RewriteEngine on

    # ---- API Platform routing ----

    # Pass Authorization header through (required for JWT in CGI/FastCGI mode)
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Handle CORS preflight requests for new API endpoints
    RewriteCond %{REQUEST_METHOD} OPTIONS
    RewriteCond %{REQUEST_URI} ^/api/(rest/v2/|graphql|admin/graphql|docs)
    RewriteRule ^(.*)$ $1 [R=204,L]

    # Route new REST API to rest.php
    RewriteRule ^api/rest/v2 rest.php [QSA,L]

    # Legacy Magento 1 REST stays on api.php (must come AFTER the v2 rule)
    RewriteRule ^api/rest api.php?type=rest [QSA,L]

    # Route everything else under /api/* to rest.php, EXCEPT the legacy
    # SOAP/XML-RPC/JSON-RPC paths which fall through to index.php and the
    # original Mage_Api controllers.
    RewriteCond %{REQUEST_URI} !^/api/(soap|v2_soap|xmlrpc|jsonrpc)(/|$)
    RewriteRule ^api(/.*)?$ rest.php [QSA,L]

    # ---- End API Platform routing ----
</IfModule>

# CORS headers for API endpoints
<IfModule mod_headers.c>
    <LocationMatch "^/api/(rest/v2/|graphql|admin/graphql|docs)">
        Header always set Access-Control-Allow-Origin "*"
        Header always set Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, OPTIONS"
        Header always set Access-Control-Allow-Headers "Content-Type, Authorization, Accept, X-Store-Code, X-Idempotency-Key, If-None-Match"
        Header always set Access-Control-Expose-Headers "ETag, X-Idempotency-Replayed, Link"
    </LocationMatch>
</IfModule>

# If using basic auth site-wide, exclude the API endpoints and rest.php:
#
# <LocationMatch "^/(api/|rest\.php)">
#     Satisfy Any
#     Allow from all
#     AuthType None
#     Require all granted
# </LocationMatch>
```

### FrankenPHP / Caddy

```caddyfile
maho.example.com {
    root * /var/www/maho/public

    # ---- API Platform routing ----

    # Match new API endpoints (REST + GraphQL + docs). Legacy paths
    # (/api/rest, /api/soap, /api/v2_soap, /api/xmlrpc, /api/jsonrpc)
    # are NOT included so they fall through to the Magento 1 controllers.
    @api {
        path /api/rest/v2/* /api/graphql /api/admin/graphql /api/docs*
    }
    header @api Access-Control-Allow-Origin "*"
    header @api Access-Control-Allow-Methods "GET, POST, PUT, PATCH, DELETE, OPTIONS"
    header @api Access-Control-Allow-Headers "Content-Type, Authorization, Accept, X-Store-Code, X-Idempotency-Key, If-None-Match"
    header @api Access-Control-Expose-Headers "ETag, X-Idempotency-Replayed, Link"

    # Handle CORS preflight
    @preflight {
        method OPTIONS
        path /api/rest/v2/* /api/graphql /api/admin/graphql /api/docs*
    }
    respond @preflight 204

    # Route new API URLs to rest.php
    @apiRoute {
        path /api/rest/v2/* /api/graphql /api/admin/graphql /api/docs*
        not file
    }
    rewrite @apiRoute /rest.php

    # ---- End API Platform routing ----

    # Static files
    @static file
    handle @static {
        file_server
    }

    # Everything else to index.php (Maho front controller)
    php_server {
        index index.php
    }
}

# Worker mode (optional, persistent PHP workers for better performance)
# Uncomment to use FrankenPHP worker mode with rest.php:
#
# {
#     frankenphp {
#         worker /var/www/maho/public/rest.php 4
#     }
# }
#
# Note: Worker mode keeps the PHP process alive between requests.
# Maho's Mage::init() runs once, subsequent requests reuse the bootstrap.
# This can significantly reduce response times but requires testing to
# ensure no state leaks between requests.
```

---

## Adding a New API Resource

Resources are declared with **one** attribute: `#[\Maho\Config\ApiResource]`, a drop-in subclass of `\ApiPlatform\Metadata\ApiResource` that adds Maho's permission-registry metadata alongside API Platform's HTTP/GraphQL configuration. Use it instead of `\ApiPlatform\Metadata\ApiResource` on every DTO.

```php
namespace MyVendor\MyModule\Api;

use Maho\Config\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;

#[ApiResource(
    shortName: 'WidgetType',
    operations: [
        new Get(uriTemplate: '/widget-types/{id}', security: 'true'),
        new GetCollection(uriTemplate: '/widget-types', security: 'true'),
    ],
    mahoPublicRead: true,                                    // optional override
    mahoSection: 'Widgets',                                  // optional override
    mahoOperations: ['read' => 'View Widget Types'],         // optional override
)]
final class WidgetType { /* ApiProperty fields */ }
```

After adding or modifying the attribute, run `composer dump-autoload`. The compiler walks every class carrying `#[Maho\Config\ApiResource]` (anywhere in `app/code/` or installed packages) and emits `vendor/composer/maho_api_permissions.php`, which `Maho\ApiPlatform\Security\ApiPermissionRegistry` reads at runtime to drive `ApiUserVoter` (REST permission checks), `GraphQlPermissionListener` (GraphQL checks), and the admin role editor UI.

### Auto-derivation

Most permission-registry fields are derived from the API Platform metadata on the same attribute, set them explicitly only when defaults are wrong:

| Maho field         | Derived from when omitted                                          |
|--------------------|--------------------------------------------------------------------|
| `mahoId`           | `shortName` → kebab-case + plural (`Cart` → `carts`, `CmsPage` → `cms-pages`) |
| `mahoLabel`        | Title-cased `mahoId` (`cms-pages` → `CMS Pages`; ≤3-char segments are upper-cased as acronyms) |
| `mahoSection`      | Module segment of the namespace (`Mage\Catalog\Api\Foo` → `'Catalog'`) |
| `mahoOperations`   | One entry per operation type present in `operations: [...]`. Default labels: `read`/`create`/`write`/`delete` → `View`/`Create`/`Update`/`Delete` |
| `mahoRestSegments` | The resource id itself. Augmented (not replaced) by your override, declare only the *additional* segments (e.g. Cart adds `'guest-carts'`) |
| `mahoGraphQlFields`| Each camelCase `name:` from `graphQlOperations[]`. Snake_case names (`item_query`, `add_cart_item`) are skipped, those are API Platform's internal operation identifiers, not schema fields. Augmented by your override for handler-defined fields (e.g. mutations declared in `*MutationHandler` classes the compiler can't see) |
| `mahoPublicRead`   | `true` when every read operation has `security: 'true'`. Override explicitly only if your read security expression doesn't use that literal form |
| `mahoCustomerScoped` | No equivalent, must be explicit for resources bound to a logged-in customer (carts, wishlists, addresses, etc.) |

For customer-scoped resources, the parent's `description:` doubles as admin-UI prose, the compiler reads it via `getDescription()` and surfaces it in the role editor. Write it as action-oriented prose ("View cart, add/remove items, apply coupons, set shipping & payment") so it's useful for both API docs and admins.

### Forward-looking resources (no DTO yet)

Permissions for endpoints you plan to build but haven't shipped go on a stub class with `operations: []` (explicit empty, *not* `null`, which would trigger API Platform's CRUD defaults). API Platform sees the resource but registers zero routes; only the maho fields populate the permission registry. Delete the stub when the real DTO ships.

```php
namespace MyVendor\MyModule\PermissionStubs;

use Maho\Config\ApiResource;

#[ApiResource(
    operations: [],
    mahoId: 'widget-attributes',
    mahoLabel: 'Widget Attributes',
    mahoSection: 'Widgets',
    mahoOperations: ['read' => 'View', 'write' => 'Edit'],
)]
final class WidgetAttributes {}
```

### Multiple `#[ApiResource]` on one class

The attribute is repeatable, a single class can carry several declarations with different `uriTemplate` / `operations` sets that share one permission identity (the Cms `Media` DTO uses this pattern for `/media` and `/media/{path}`). Just give each attribute the same `mahoId` and the compiler unions their segments and GraphQL fields under one registry entry.

## Extending the API (Third-Party Modules)

All API resources extend `\Maho\ApiPlatform\Resource`, which provides an `extensions` field, an open array where modules can inject additional data without modifying core API files. The base class also provides a `toArray()` method for serializing DTOs (used by GraphQL handlers).

Providers build DTOs via `toDto($model)` (the abstract method on the `Provider` base class). A handful of providers (Order, Category, Address, Customer, Product, Cart) also expose a public `mapToDto()` method with domain-specific extra arguments, used directly from GraphQL handlers and custom processors when they need a consistent representation including extensions.

### How It Works

Every resource DTO (Product, Category, Cart, Order, etc.) dispatches a Maho event after building the response object. Your module observes the event and appends data to `$dto->extensions`. These events fire for both **REST and GraphQL**, the GraphQL handlers use the same Provider/Mapper DTO-building methods as REST, ensuring consistent behavior across both APIs.

### Event area: `api`

The API Platform loads a dedicated `api` event area (`Mage_Core_Model_App_Area::AREA_API`), similar to `frontend` and `adminhtml`. Observers registered under `<api><events>` in `config.xml` only load when the API is running, they won't fire on regular frontend, admin, or cron requests.

### Available Events

| Event | Dispatched In | Observer Parameters |
|-------|---------------|---------------------|
| `api_product_dto_build` | ProductProvider | `product` (model), `for_listing` (bool), `dto` |
| `api_category_dto_build` | CategoryProvider | `category` (model), `dto` |
| `api_store_config_dto_build` | StoreConfigProvider | `dto` |
| `api_order_dto_build` | OrderProvider | `order` (model), `dto` |
| `api_order_item_dto_build` | OrderProvider | `item` (model), `dto` |
| `api_customer_dto_build` | CustomerProvider | `customer` (model), `dto` |
| `api_cart_dto_build` | CartMapper | `quote` (model), `dto` |
| `api_cart_item_dto_build` | CartMapper | `item` (model), `dto` |
| `api_wishlist_item_dto_build` | WishlistProvider | `dto` |
| `api_captcha_config` | ApiPlatform Helper | `config` (DataObject) |
| `api_verify_captcha` | ApiPlatform Helper | `result` (DataObject), `data` (array) |

### Quick Example: Simple Bundles Module

A module that adds bundle component data to products and cart items.

**1. Register the observer** in your module's `config.xml`:

```xml
<config>
    <api>
        <events>
            <api_product_dto_build>
                <observers>
                    <simple_bundles>
                        <class>Vendor_SimpleBundles_Model_Api_Observer</class>
                        <method>addBundleToProduct</method>
                    </simple_bundles>
                </observers>
            </api_product_dto_build>
            <api_cart_item_dto_build>
                <observers>
                    <simple_bundles>
                        <class>Vendor_SimpleBundles_Model_Api_Observer</class>
                        <method>addBundleToCartItem</method>
                    </simple_bundles>
                </observers>
            </api_cart_item_dto_build>
        </events>
    </api>
</config>
```

**2. Write the observer:**

```php
class Vendor_SimpleBundles_Model_Api_Observer
{
    public function addBundleToProduct(\Maho\Event\Observer $observer): void
    {
        $product = $observer->getEvent()->getProduct();
        $dto = $observer->getEvent()->getDto();

        // Only add bundle data on detail view, not listings
        if ($observer->getEvent()->getForListing()) {
            return;
        }

        $bundleItems = Mage::getModel('simplebundles/item')
            ->getCollection()
            ->addProductFilter($product->getId());

        if ($bundleItems->count() === 0) {
            return;
        }

        $dto->extensions['simpleBundle'] = [
            'items' => array_map(fn ($item) => [
                'sku' => $item->getSku(),
                'name' => $item->getName(),
                'qty' => (int) $item->getQty(),
            ], $bundleItems->getItems()),
        ];
    }

    public function addBundleToCartItem(\Maho\Event\Observer $observer): void
    {
        $quoteItem = $observer->getEvent()->getItem();
        $dto = $observer->getEvent()->getDto();

        $bundleData = $quoteItem->getOptionByCode('simple_bundle_data');
        if (!$bundleData) {
            return;
        }

        $dto->extensions['simpleBundle'] = json_decode($bundleData->getValue(), true);
    }
}
```

**3. API response** now includes the extension data:

```json
{
  "id": 42,
  "sku": "OUTFIT-SUMMER",
  "name": "Summer Festival Outfit",
  "price": 189.95,
  "extensions": {
    "simpleBundle": {
      "items": [
        {"sku": "DRESS-FLR-M", "name": "Floral Midi Dress", "qty": 1},
        {"sku": "HAT-STRAW", "name": "Wide Brim Straw Hat", "qty": 1},
        {"sku": "SANDAL-TAN-8", "name": "Tan Leather Sandals", "qty": 1}
      ]
    }
  }
}
```

### Guidelines

- **Namespace your data**, use a unique key in `extensions` (e.g. `simpleBundle`, not `items`)
- **Keep it lightweight**, avoid loading heavy collections in listing mode (check `for_listing`)
- **Return serializable data**, arrays and scalars only, no objects
- **Extensions are read-only**, the `extensions` field is populated during read operations; for write operations, use standard Maho model events or custom API processors

## Deployment Notes

### Filesystem permissions

The Symfony kernel writes its compiled container, route table, and metadata cache to `var/cache/api_platform/{env}/` (where `{env}` is `prod` or `dev`). The directory must be writable by the PHP-FPM/Apache user that handles `/api/*` requests. On a fresh deploy:

```bash
mkdir -p var/cache/api_platform
chown -R www-data:www-data var/cache var/log
```

### Cache pre-warm

The first request after a deploy pays a one-time container compilation cost (~hundreds of ms). To keep that out of the critical path, warm the cache during deployment:

```bash
# As the web user, after `composer install` and before flipping the load balancer:
php -r 'require "vendor/autoload.php"; Mage::app(); $k = new Maho\ApiPlatform\Kernel("prod", false); $k->boot();'
```

Run this whenever module API resources change (new/modified `#[ApiResource]` classes), in addition to `composer dump-autoload` which refreshes the permission registry compiled file.

### Cache invalidation

The container cache is keyed by class file mtimes; a normal deploy that overwrites files invalidates it automatically. If you ever need to force a rebuild manually, delete `var/cache/api_platform/{env}/`, the next request will recompile.
