# Maho API Platform — REST & GraphQL API Reference

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
  - [Newsletter](#newsletter)
  - [Contact](#contact)
  - [Directory](#directory)
  - [Wishlist](#wishlist)
  - [URL Resolver](#url-resolver)
  - [POS Payments](#pos-payments)

---

## Authentication

### JWT Token Authentication

All authenticated endpoints require a Bearer token in the `Authorization` header.

**Get a token:**

```bash
# Customer login
curl -X POST /api/auth/token \
  -H 'Content-Type: application/json' \
  -d '{"email": "customer@example.com", "password": "password123"}'

# Admin login
curl -X POST /api/auth/token \
  -H 'Content-Type: application/json' \
  -d '{"username": "admin", "password": "admin123", "type": "admin"}'

# API key login
curl -X POST /api/auth/token \
  -H 'Content-Type: application/json' \
  -d '{"api_key": "your-api-key", "api_secret": "your-api-secret", "type": "api"}'
```

**Response:**
```json
{
  "token": "eyJ...",
  "refresh_token": "abc123...",
  "expires_in": 3600,
  "token_type": "Bearer"
}
```

**Use the token:**
```bash
curl /api/products \
  -H 'Authorization: Bearer eyJ...'
```

**Refresh a token:**
```bash
curl -X POST /api/auth/refresh \
  -H 'Content-Type: application/json' \
  -d '{"refresh_token": "abc123..."}'
```

**Other auth endpoints:**
| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/auth/me` | Get current user info |
| POST | `/api/auth/forgot-password` | Send password reset email |
| POST | `/api/auth/reset-password` | Reset password with token |
| POST | `/api/auth/logout` | Invalidate token |

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

Collection endpoints support pagination via query parameters:

| Parameter | Default | Max | Description |
|-----------|---------|-----|-------------|
| `page` | 1 | — | Page number |
| `itemsPerPage` | 20 | 100 | Items per page |

**Response includes pagination metadata:**
```json
{
  "data": [...],
  "meta": {
    "totalItems": 150,
    "itemsPerPage": 20,
    "currentPage": 1
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
# First request — note the ETag
curl -v /api/products/123
# < ETag: "abc123"

# Subsequent request — get 304 if unchanged
curl /api/products/123 -H 'If-None-Match: "abc123"'
# Returns 304 Not Modified (no body)
```

---

## Idempotency Keys

Protect against duplicate mutations by including `X-Idempotency-Key` on POST/PUT/PATCH requests. If the same key+user+path+method is seen again within 24 hours, the stored response is replayed.

```bash
# First request — processed normally
curl -X POST /api/orders/123/credit-memos \
  -H 'Authorization: Bearer eyJ...' \
  -H 'X-Idempotency-Key: refund-order-123-v1' \
  -H 'Content-Type: application/json' \
  -d '{"items": [{"orderItemId": 456, "qty": 1}]}'

# Duplicate request — returns stored response
curl -X POST /api/orders/123/credit-memos \
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
  product(id: "/api/products/123") {
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
| POST | `/auth/token` | None | Get JWT token (customer/admin/API) |
| POST | `/auth/refresh` | None | Refresh JWT token |
| GET | `/auth/me` | Customer/Admin | Get current user info |
| POST | `/auth/forgot-password` | None | Send password reset email |
| POST | `/auth/reset-password` | None | Reset password with token |
| POST | `/auth/logout` | Customer/Admin | Invalidate token |

---

### Store Configuration

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/store-config` | None | Get store configuration |
| GET | `/stores` | None | List all stores |
| GET | `/stores/config` | None | Get store config |
| GET | `/{storeCode}/config` | None | Get config for specific store |
| GET | `/stores/countries` | None | List countries |
| GET | `/stores/currencies` | None | List currencies |
| POST | `/stores/switch/{storeCode}` | None | Switch store context |

---

### Products & Catalog

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/products` | None | List products (paginated) |
| GET | `/products/{id}` | None | Get product by ID |
| POST | `/products` | Admin/API | Create product |
| PUT | `/products/{id}` | Admin/API | Update product |
| DELETE | `/products/{id}` | Admin/API | Delete product |

**Sub-resources:**

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/products/{id}/media` | None | Product images |
| GET | `/products/{id}/tier-prices` | Admin/API | Tier pricing |
| GET | `/products/{id}/custom-options` | None | Custom options |
| GET | `/products/{id}/custom-options/{optionId}` | None | Single custom option |
| GET | `/products/{id}/bundle-options` | None | Bundle product options |
| GET | `/products/{id}/configurable` | None | Configurable product setup |
| GET | `/products/{id}/configurable/children` | None | Configurable children (simples) |
| PUT | `/products/{id}/configurable/children/{childId}` | Admin/API | Add/update child |
| DELETE | `/products/{id}/configurable/children/{childId}` | Admin/API | Remove child |
| GET | `/products/{id}/downloadable-links` | None | Downloadable links |
| GET | `/products/{id}/grouped` | None | Grouped product links |
| PUT | `/products/{id}/grouped/{childProductId}` | Admin/API | Add/update grouped link |
| DELETE | `/products/{id}/grouped/{childProductId}` | Admin/API | Remove grouped link |
| GET | `/products/{id}/links/related` | None | Related products |
| GET | `/products/{id}/links/up_sell` | None | Up-sell products |
| GET | `/products/{id}/links/cross_sell` | None | Cross-sell products |
| PUT | `/products/{id}/links/{type}/{linkedProductId}` | Admin/API | Add product link |
| DELETE | `/products/{id}/links/{type}/{linkedProductId}` | Admin/API | Remove product link |
| GET | `/products/{id}/reviews` | None | Product reviews |
| GET | `/products/{sku}/options` | None | Product options by SKU |

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
| GET | `/carts` | Customer | List customer's carts |
| GET | `/carts/{id}` | Customer | Get cart by ID |
| POST | `/carts` | Customer | Create cart |

---

### Guest Cart

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/guest-carts` | None | Create guest cart |
| GET | `/guest-carts/{cartId}` | None | Get guest cart |
| POST | `/guest-carts/{cartId}/items` | None | Add item to cart |
| PUT | `/guest-carts/{cartId}/items/{itemId}` | None | Update cart item |
| DELETE | `/guest-carts/{cartId}/items/{itemId}` | None | Remove cart item |
| GET | `/guest-carts/{cartId}/totals` | None | Get cart totals |
| PUT | `/guest-carts/{cartId}/coupon` | None | Apply coupon code |
| DELETE | `/guest-carts/{cartId}/coupon` | None | Remove coupon |
| POST | `/guest-carts/{cartId}/giftcard` | None | Apply gift card |
| DELETE | `/guest-carts/{cartId}/giftcard/{code}` | None | Remove gift card |
| POST | `/guest-carts/{cartId}/shipping-methods` | None | Get shipping methods |
| GET | `/guest-carts/{cartId}/payment-methods` | None | Get payment methods |
| POST | `/guest-carts/{cartId}/place-order` | None | Place order |

---

### Customers

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/customers` | Admin/API | List customers |
| GET | `/customers/{id}` | Admin/API | Get customer by ID |
| POST | `/customers` | Admin/API | Create customer |
| PUT | `/customers/{id}` | Admin/API | Update customer |
| DELETE | `/customers/{id}` | Admin/API | Delete customer |
| GET | `/customers/me` | Customer | Get own profile |
| PUT | `/customers/me/password` | Customer | Change password |
| GET | `/customers/me/addresses` | Customer | List own addresses |
| POST | `/customers/me/addresses` | Customer | Create address |
| PUT | `/customers/me/addresses/{id}` | Customer | Update address |
| DELETE | `/customers/me/addresses/{id}` | Customer | Delete address |
| GET | `/customers/me/orders` | Customer | List own orders |
| GET | `/customers/me/reviews` | Customer | List own reviews |
| GET | `/customers/{customerId}/addresses` | Admin/API | List customer addresses |

---

### Orders

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/orders` | Admin/API | List orders (paginated) |
| GET | `/orders/{id}` | Admin/API | Get order by ID |

**Order sub-resources:**

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/orders/{orderId}/shipments` | Admin/API | List shipments for order |
| POST | `/orders/{orderId}/shipments` | Admin/API | Create shipment |
| GET | `/orders/{orderId}/credit-memos` | Admin/API | List credit memos for order |
| POST | `/orders/{orderId}/credit-memos` | Admin/API | Create credit memo/refund |
| GET | `/orders/{orderId}/invoices` | Admin/API | List invoices for order |
| GET | `/orders/{orderId}/invoices/{invoiceId}/pdf` | Admin/API | Download invoice PDF |

**Customer invoice access:**

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/customers/me/orders/{orderId}/invoices` | Customer | List own invoices |
| GET | `/customers/me/orders/{orderId}/invoices/{invoiceId}/pdf` | Customer | Download own invoice PDF |

---

### Shipments

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/shipments/{id}` | Admin/API | Get shipment by ID |

**Create shipment:**
```bash
curl -X POST /api/orders/123/shipments \
  -H 'Authorization: Bearer eyJ...' \
  -H 'Content-Type: application/json' \
  -d '{
    "items": [{"orderItemId": 456, "qty": 2}],
    "tracks": [{"carrier": "auspost", "title": "Australia Post", "number": "AP123456"}],
    "comment": "Shipped via express"
  }'
```

---

### Credit Memos / Refunds

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/credit-memos/{id}` | Admin/API | Get credit memo by ID |
| GET | `/orders/{orderId}/credit-memos` | Admin/API | List credit memos for order |
| POST | `/orders/{orderId}/credit-memos` | Admin/API | Create credit memo |

**Create a credit memo:**
```bash
curl -X POST /api/orders/123/credit-memos \
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
- `items[]` — Array of items to refund. Each requires `orderItemId` and `qty`. Optional: `backToStock` (boolean, returns qty to inventory).
- `comment` — Optional refund note.
- `adjustmentPositive` — Additional positive adjustment (add to refund).
- `adjustmentNegative` — Negative adjustment (reduce refund).
- `offlineRefund` — `true` (default) for offline refund, `false` to trigger payment gateway refund.

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
| GET | `/orders/{orderId}/invoices` | Admin/API | List invoices for order |
| GET | `/orders/{orderId}/invoices/{invoiceId}/pdf` | Admin/API | Download invoice PDF |

---

### Inventory / Stock Updates

Fast direct-SQL stock updates — no model overhead, no observers.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| PUT | `/inventory` | Admin/API | Update single SKU stock |
| PUT | `/inventory/bulk` | Admin/API | Bulk update (max 100 items) |

**Single update:**
```bash
curl -X PUT /api/inventory \
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
curl -X PUT /api/inventory/bulk \
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
| POST | `/coupons/validate` | Admin/API/Customer | Validate coupon code |

**Create a coupon:**
```bash
curl -X POST /api/coupons \
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
curl -X POST /api/coupons/validate \
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
GET /api/coupons?code=SUMMER          # Filter by code (LIKE search)
GET /api/coupons?isActive=true        # Filter by active status
GET /api/coupons?page=2&itemsPerPage=50
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
| GET | `/giftcards` | Admin/API | List gift cards |
| GET | `/giftcards/{id}` | Admin/API | Get gift card by ID |
| POST | `/giftcards` | Admin/API | Create gift card |
| PUT | `/giftcards/{id}` | Admin/API | Update gift card |
| DELETE | `/giftcards/{id}` | Admin/API | Delete gift card |

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

---

### Media

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/media` | Admin/API | List media files |
| GET | `/media/{path}` | None | Get media file |
| POST | `/media` | Admin/API | Upload media file |

---

### Reviews

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/reviews/{id}` | None | Get review by ID |
| GET | `/products/{productId}/reviews` | None | List reviews for product |
| POST | `/products/{productId}/reviews` | Customer | Create review |
| GET | `/customers/me/reviews` | Customer | List own reviews |

---

### Newsletter

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/newsletter/subscribe` | None or Customer | Subscribe to newsletter |
| POST | `/newsletter/unsubscribe` | Customer | Unsubscribe (guests use email link) |
| GET | `/newsletter/status` | Customer | Get subscription status |

**Guest subscription control:** Guest (unauthenticated) subscribe is controlled by the Maho config flag `newsletter/subscription/allow_guest_subscribe` (**System > Config > Newsletter > Subscription Options > Allow Guest Subscription**). When disabled, only authenticated customers can subscribe. Recommended: set to **No** for API use to prevent abuse.

**Confirmation emails:** When `newsletter/subscription/confirm` is enabled, new subscriptions receive a confirmation email and remain inactive until confirmed (double opt-in).

---

### Contact

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/contact` | None | Submit contact form |
| GET | `/contact/config` | None | Get contact form config |

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
| GET | `/customers/me/wishlist` | Customer | Get wishlist items |
| POST | `/customers/me/wishlist` | Customer | Add to wishlist |
| PUT | `/customers/me/wishlist/{id}` | Customer | Update wishlist item |
| DELETE | `/customers/me/wishlist/{id}` | Customer | Remove from wishlist |
| POST | `/customers/me/wishlist/{id}/move-to-cart` | Customer | Move item to cart |
| POST | `/customers/me/wishlist/sync` | Customer | Sync wishlist |

---

### URL Resolver

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/url-resolver?path=/some-page` | None | Resolve URL to entity |

---

### POS Payments

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/pos-payments` | Admin/API | List POS payments |
| GET | `/pos-payments/{id}` | Admin/API | Get POS payment |
| POST | `/pos-payments` | Admin/API | Create POS payment |

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

## Architecture

The API is built on [API Platform](https://api-platform.com/) (Symfony) integrated with Maho Commerce (PHP 8.3+, fork of OpenMage/Magento 1).

- **Entry point:** `public/rest.php` — bootstraps Maho, then hands off to Symfony
- **Resources:** PHP 8 `#[ApiResource]` attributes define REST + GraphQL operations
- **Providers:** `ProviderInterface` implementations fetch data from Maho models
- **Processors:** `ProcessorInterface` implementations handle mutations
- **Event listeners:** Symfony listeners for cross-cutting concerns (caching, idempotency)
- **Authentication:** JWT (HS256) via Firebase JWT library

**Module structure:**
```
app/code/core/Maho/
├── ApiPlatform/           # Core API Platform integration
│   ├── symfony/           # Symfony app (kernel, controllers, services)
│   ├── docs/              # This documentation
│   ├── etc/config.xml     # Module config (version: 1.1.0)
│   └── sql/               # DB migration scripts
├── Catalog/Api/           # Products, categories, media
├── CatalogInventory/Api/  # Stock/inventory updates
├── Checkout/Api/          # Cart management
├── Cms/Api/               # CMS pages & blocks
├── Core/Api/              # Store config, URL resolver
├── Customer/Api/          # Customer management
├── Directory/Api/         # Countries & regions
├── Sales/Api/             # Orders, shipments, credit memos, invoices
├── SalesRule/Api/         # Coupons & price rules
└── ...                    # Other modules
```
