---
description: Full REST endpoint catalogue for the Maho v2 API - auth, store config, products, customers, orders, and more, all under the /api/rest/v2 prefix.
---

# REST Endpoints <span class="version-badge">v26.7+</span>

This page is the **REST v2** reference. The same resources are available over GraphQL - for the query and mutation field names, arguments, and examples, see the [GraphQL reference](graphql.md).

All paths below are relative to the **`/api/rest/v2`** prefix: `GET /products` means `GET https://your-domain.com/api/rest/v2/products`.

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
| GET | `/stores/{id}` | None | Get a single store by ID |
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

**Sub-resources** (parent path parameter is `{productId}` throughout):

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET / POST / PUT / DELETE | `/products/{productId}/media` | GET=None, writes=Admin/API | Product images |
| GET / POST / DELETE | `/products/{productId}/tier-prices` | Admin/API | Tier pricing (POST replaces, DELETE clears all) |
| GET / POST | `/products/{productId}/custom-options` | GET=None, POST=Admin/API | Custom options |
| PUT / DELETE | `/products/{productId}/custom-options/{id}` | Admin/API | Update/remove a custom option |
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

**Attribute metadata** (read-only, scoped to the `catalog_product` entity type):

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/product-attributes` | Admin/API | List product attributes (code, label, input type, options, scope) |
| GET | `/product-attributes/{id}` | Admin/API | Get one attribute's metadata |
| GET | `/attribute-sets` | Admin/API | List product attribute sets |
| GET | `/attribute-sets/{id}` | Admin/API | Get an attribute set and its attribute codes |

The product write endpoints additionally accept `attributeSetId`, `taxClassId`, and a generic `customAttributesWrite` map (`{attribute_code: value}`) for any catalog_product EAV attribute without a dedicated field; system columns (`sku`, `type_id`, `status`, …) are rejected.

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
| GET | `/carts/{id}/totals` | Customer/Admin/API | Get cart totals |
| POST | `/carts/{id}/coupon` | Customer/Admin/API | Apply coupon code |
| DELETE | `/carts/{id}/coupon` | Customer/Admin/API | Remove coupon |
| POST | `/carts/{id}/giftcards` | Customer/Admin/API | Apply gift card |
| DELETE | `/carts/{id}/giftcards/{code}` | Customer/Admin/API | Remove gift card |
| POST | `/carts/{id}/shipping-methods` | Customer/Admin/API | Get available shipping methods |
| GET | `/carts/{id}/payment-methods` | Customer/Admin/API | Get available payment methods |
| POST | `/carts/{id}/place-order` | Customer/Admin/API | Place an order from the authenticated cart |
| PUT | `/carts/{id}/gift-message` | Customer/Admin/API | Set the cart-level gift message |
| DELETE | `/carts/{id}/gift-message` | Customer/Admin/API | Remove the cart-level gift message |
| PUT | `/carts/{id}/items/{itemId}/gift-message` | Customer/Admin/API | Set a per-item gift message |
| DELETE | `/carts/{id}/items/{itemId}/gift-message` | Customer/Admin/API | Remove a per-item gift message |

The checkout sub-resources mirror the guest-cart flow so a logged-in customer can complete checkout entirely over REST. Gift messages require the `sales/gift_options/*` store config to be enabled; the body is `{sender, recipient, message}`. There is no separate gift-message resource - it is a sub-resource of the cart (`/carts/{id}/gift-message`).

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
| PUT / DELETE | `/guest-carts/{id}/gift-message` | None | Set / remove the cart-level gift message |
| PUT / DELETE | `/guest-carts/{id}/items/{itemId}/gift-message` | None | Set / remove a per-item gift message |
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
| GET | `/orders/{incrementId}/details` | Order token | Read a guest order via the per-order one-time `X-Order-Token` header |
| POST | `/orders` | None | Place an order from a customer or guest cart |
| POST | `/carts/{id}/place-order` | Customer/Admin/API | Place an order from the authenticated customer cart |
| POST | `/guest-carts/{id}/place-order` | None | Place an order from a guest cart |

**Checkout addresses:** the `shippingAddress` / `billingAddress` objects in the place-order body accept either a numeric `regionId` **or** a region `region` name - for countries with a fixed region list (US, CA, ...) the API resolves the `region_id` from the code (or name) against `countryId` when `regionId` is omitted, so a client can send `"region": "California"` without looking the ID up first. On a match the returned address carries the canonical region name.

**Guest order lookup** (`GET /orders/{incrementId}/details`): a public, unauthenticated endpoint for rendering order-confirmation views in headless/guest checkouts. The per-order token is passed in the `X-Order-Token` header (never the query string, which would leak into access logs). The token is **single-use**: it's cleared on the first successful read, so refreshing the page won't replay the lookup. The endpoint is IP rate-limited to prevent brute-forcing a token against a known increment ID. A missing/invalid token or unknown increment ID returns `404`. If no customer account exists for the order's email, the response includes an `accountToken` the frontend can use with `POST /customers/create-from-order`.

```bash
curl /api/rest/v2/orders/100000123/details \
  -H 'X-Order-Token: <one-time-token>'
```

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

**Lifecycle actions:** each returns the updated order (the `statusHistory` field reflects the change).

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/orders/{id}/hold` | Admin/API | Put an order on hold |
| POST | `/orders/{id}/unhold` | Admin/API | Release an order from hold |
| POST | `/orders/{id}/cancel` | Customer/Admin/API | Cancel an order (a customer may cancel their own) |
| POST | `/orders/{id}/comments` | Admin/API | Add a status-history comment (`{comment, notifyCustomer?, visibleOnFront?}`) |

---

### Shipments

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/shipments/{id}` | Admin/API | Get shipment by ID |
| POST | `/shipments/{id}/tracks` | Admin/API | Add a tracking number (`{carrierCode?, title?, trackNumber}`) |
| DELETE | `/shipments/{id}/tracks/{trackId}` | Admin/API | Remove a tracking number by its track row ID |

`{trackId}` is the internal track row ID (an integer), **not** the carrier tracking number.

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

=== "curl"

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

=== "JavaScript"

    ```javascript
    const res = await fetch('/api/rest/v2/orders/123/credit-memos', {
      method: 'POST',
      headers: {
        'Authorization': 'Bearer eyJ...',
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        items: [
          { orderItemId: 456, qty: 1, backToStock: true }
        ],
        comment: 'Customer returned item',
        adjustmentPositive: 5.00,
        adjustmentNegative: 0,
        offlineRefund: true
      })
    });
    const data = await res.json();
    ```

=== "Python"

    ```python
    import requests

    res = requests.post('/api/rest/v2/orders/123/credit-memos',
        headers={'Authorization': 'Bearer eyJ...'},
        json={
            'items': [
                {'orderItemId': 456, 'qty': 1, 'backToStock': True}
            ],
            'comment': 'Customer returned item',
            'adjustmentPositive': 5.00,
            'adjustmentNegative': 0,
            'offlineRefund': True
        })
    data = res.json()
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
- Qty must be 0-99,999,999.
- Bulk limit: 100 items per request (validated upfront, executed in a DB transaction).

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

---

### Gift Cards

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/giftcards/{id}` | Admin/API | Get a gift card by numeric ID |
| POST | `/giftcards` | Admin/API | Create a new gift card |

REST `GET /giftcards/{id}` is **admin-only and keyed by numeric ID**. A public **balance check by code** (no recipient/sender PII) is exposed over GraphQL only - see the [GraphQL reference](graphql.md#inventory-promotions).

!!! warning "v26.9+ breaking change"
    A gift card is now associated with a set of websites instead of one. Responses
    carry `websiteIds` (a list of integers) and no longer include the scalar
    `websiteId`. On create, `POST /giftcards` still accepts `websiteId` as a
    legacy alias, honored only when `websiteIds` is omitted, so an older client is
    not silently scoped to the current website. Omitting both defaults to the
    current website.

    A token restricted to specific stores can read a card when any of its websites
    is in scope, but can only write one when **every** website is in scope. The
    same rule applies to the websites being written, so a restricted token cannot
    claim a card by re-scoping it.

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

`POST /cms-pages` requires a non-empty `identifier` (the page's URL key) and rejects a create without one with a `422`. On `PUT`, `identifier` is optional (an omitted field is left unchanged).

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

---

### Newsletter

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/newsletter/subscribe` | None | Subscribe to newsletter (gated by `newsletter/subscription/allow_guest_subscribe`) |
| POST | `/newsletter/unsubscribe` | None | Unsubscribe by email |
| GET | `/newsletter/status` | Customer/API | Get subscription status |

**Guest subscription control:** Guest (unauthenticated) subscribe is controlled by the Maho config flag `newsletter/subscription/allow_guest_subscribe` (**System > Config > Newsletter > Subscription Options > Allow Guest Subscription**). When disabled, only authenticated customers can subscribe, and a guest subscribe returns `403`. Recommended: set to **No** for API use to prevent abuse.

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

`captchaProvider` is one of `none`, `turnstile`, `recaptcha_v3` (or anything an installed third-party module registers). `captchaSiteKey` is `null` when the provider is `none`. Frontends use these two fields to load the matching widget client-side; for richer per-provider config the helper-based event flow described under [CAPTCHA](captcha.md) is used instead.

The `honeypotField` value is **deterministic per install** (derived from the encryption key) and **opaque** to the frontend, render it as a hidden input and don't expose its value, e.g. `<input type="text" name="{honeypotField}" style="display:none" tabindex="-1" autocomplete="off" />`. If a request body arrives with a non-empty value in that field, the API silently treats it as spam (returns success without sending the email). When honeypot is disabled in admin, `honeypotField` is `null` and the frontend can skip it.

---

### Directory

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/countries` | None | List countries |
| GET | `/countries/{id}` | None | Get country (with regions) |

---

### Tax

Tax classes, rates, and rules are admin-only configuration data.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET / POST | `/tax-classes` | Admin/API | List / create tax classes (`{className, classType: PRODUCT\|CUSTOMER}`) |
| GET / PUT / DELETE | `/tax-classes/{id}` | Admin/API | Read / update / delete a tax class |
| GET / POST | `/tax-rates` | Admin/API | List / create rates (`{code, taxCountryId, taxPostcode, rate, …}`) |
| GET / PUT / DELETE | `/tax-rates/{id}` | Admin/API | Read / update / delete a rate |
| GET / POST | `/tax-rules` | Admin/API | List / create rules (`{code, priority, customerTaxClassIds, productTaxClassIds, taxRateIds}`) |
| GET / PUT / DELETE | `/tax-rules/{id}` | Admin/API | Read / update / delete a rule |

A rule's `customerTaxClassIds`, `productTaxClassIds`, and `taxRateIds` round-trip on read.

---

### Customer Groups

Admin-only. Used for group-based pricing and tax-class assignment.

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET / POST | `/customer-groups` | Admin/API | List / create groups (`{code, taxClassId}`) |
| GET / PUT / DELETE | `/customer-groups/{id}` | Admin/API | Read / update / delete a group |

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
