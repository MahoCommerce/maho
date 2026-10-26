---
description: Query the Maho v2 API over GraphQL - the same resources as REST via API Platform, with JWT auth, an introspectable schema, and admin GraphQL support.
---

# GraphQL <span class="version-badge">v26.7+</span>

Maho exposes the same resources over GraphQL as over [REST](endpoints.md), via API Platform's built-in GraphQL support. Every resource's fields, arguments, and types are introspectable from the schema.

**Endpoint:** `POST /api/graphql`
**Admin GraphQL:** `POST /api/admin/graphql` (requires a fully-authenticated admin session)

Both are opt-in: enable the `graphql` / `admin_graphql` protocols under **System → Configuration → Services → API → API Protocols**. Authentication is the same JWT bearer token as REST (see [Authentication](authentication.md)); send it in the `Authorization` header.

```graphql
# Query: fetch a product by its node IRI
query {
  product(id: "/api/rest/v2/products/123") {
    id
    sku
    name
    price
  }
}

# Mutation: update stock for a SKU
mutation {
  updateStock(input: {sku: "ABC-123", qty: 50}) {
    sku
    qty
    previousQty
    success
  }
}
```

## Field naming

API Platform derives every **custom** GraphQL field name as `lcfirst(operationName + ResourceShortName)` - the resource noun is always appended. Only the reserved item/collection queries escape this and yield the clean singular/plural (`product` / `products`, `order` / `orders`). So a custom operation named `setPaymentMethodOn` on the `Cart` resource becomes the field `setPaymentMethodOnCart`; operations are named with a natural qualifier (a verb, or `on` / `to` / `from` / `my` / `guest` / `current`) so the appended noun reads well rather than stuttering (`setPaymentMethodCart`).

Two consequences worth knowing:

- **The signed-in customer is `currentCustomer`** (not `me`), a guest cart/order is `guestCart` / `guestOrder`, and a caller's own collections are `myAddresses` / `myReviews` / `myWishlistItems` / `myRevocationRequests`.
- **Natural-key lookups are collection filter arguments, not separate `by<X>` queries.** Filter the collection to fetch by a key: `products(sku:)`, `products(barcode:)`, `categories(urlKey:)`, `productAttributes(code:)`, `cmsPages(identifier:)`, `cmsBlocks(identifier:)` - each returns the 0-or-1 match.

## Reference

Item queries take a node IRI (`id: "/api/rest/v2/products/123"`); collection queries are plural and accept pagination plus any listed filter arguments. Admin-only queries/mutations require an admin or service token with the relevant permission (see [Authentication](authentication.md#permission-levels)).

### Catalog

| Resource | Queries | Mutations |
|---|---|---|
| Product | `product(id:)`, `products` - filters `sku:`, `barcode:`, `search:` | - (writes are REST-only) |
| Category | `category(id:)`, `categories` - filter `urlKey:`, `categoryProducts(id:)` | - |
| Product attribute | `productAttribute(id:)`, `productAttributes` - filter `code:` | - |
| Attribute set | `attributeSet(id:)`, `attributeSets` | - |
| Layered filter | `layeredFilter(id:)`, `layeredFilters` | - |
| Review | `review(id:)`, `reviews`, `productReviews(productId:)`, `myReviews` | `submitReview` |

### Cart & checkout

| Resource | Queries | Mutations |
|---|---|---|
| Cart | `cart(id:)`, `carts`, `customerCart`, `guestCart(maskedId:)` | `createCart`, `addToCart`, `updateItemQtyInCart`, `removeItemFromCart`, `applyCouponToCart`, `removeCouponFromCart`, `setShippingAddressOnCart`, `setBillingAddressOnCart`, `setShippingMethodOnCart`, `setPaymentMethodOnCart`, `assignCustomerToCart`, `applyGiftcardToCart`, `removeGiftcardFromCart`, `setGiftMessageOnCart`, `removeGiftMessageFromCart` |

Gift messages have no separate resource - they are the `setGiftMessageOnCart` / `removeGiftMessageFromCart` mutations above.

### Customers

| Resource | Queries | Mutations |
|---|---|---|
| Customer | `currentCustomer`, `customer(id:)` *(admin)*, `customers` *(admin)* | `loginCustomer`, `logoutCustomer`, `quickCreateCustomer` (register), `updateCustomer`, `changePasswordCustomer`, `forgotPasswordCustomer`, `resetPasswordCustomer` |
| Address | `address(id:)`, `addresses`, `myAddresses` | `createAddress`, `updateAddress`, `deleteAddress` |
| Customer group | `customerGroup(id:)`, `customerGroups` *(admin)* | - |

### Sales

| Resource | Queries | Mutations |
|---|---|---|
| Order | `order(id:)`, `orders` *(admin)*, `guestOrder(incrementId:, accessToken:)`, `customerOrders` | `placeOrder`, `cancelOrder`, `holdOrder`, `unholdOrder`, `addCommentOrder` |
| Shipment | `shipment(id:)`, `shipments`, `orderShipments(orderId:)` | `createShipment`, `addTrackShipment`, `removeTrackShipment` |
| Credit memo | `creditMemo(id:)`, `creditMemos`, `orderCreditMemos(orderId:)` | `createCreditMemo` |
| Revocation request | `revocationRequest(id:)`, `revocationRequests` *(admin)*, `myRevocationRequests` | `submitRevocationRequest` |

### Inventory & promotions

| Resource | Queries | Mutations |
|---|---|---|
| Stock | `stock(id:)`, `stocks` *(admin)* | `updateStock`, `bulkUpdateStock` |
| Coupon | `coupon(id:)`, `coupons` *(admin)* | `createCoupon`, `updateCoupon`, `deleteCoupon`, `validateCoupon` |
| Gift card | `giftCard(id:)` *(admin)*, `giftCards` *(admin)*, `checkBalanceGiftCard(code:)` *(public)* | `createGiftCard`, `adjustBalanceGiftCard` |

`checkBalanceGiftCard(code:)` is the public balance check (returns balance + status with the code masked, no recipient/sender PII).

!!! warning "v26.9+ breaking change"
    `createGiftCard` takes `websiteIds: [Int!]` in place of the removed scalar
    `websiteId` argument, and gift card types expose `websiteIds` rather than
    `websiteId`. A client still sending `websiteId` fails input validation.
    Unlike REST create, the mutation accepts no legacy alias. Omitting
    `websiteIds` defaults to the current website.

### Content

| Resource | Queries | Mutations |
|---|---|---|
| CMS page | `cmsPage(id:)`, `cmsPages` - filter `identifier:` | - (writes are REST-only) |
| CMS block | `cmsBlock(id:)`, `cmsBlocks` - filter `identifier:` | - |
| Blog post | `blogPost(id:)`, `blogPosts` | - |
| Blog category | `blogCategory(id:)`, `blogCategories` | - |

### Marketing

| Resource | Queries | Mutations |
|---|---|---|
| Newsletter | `newsletter(id:)` *(admin)*, `newsletters` *(admin)*, `statusNewsletter` | `subscribeNewsletter`, `unsubscribeNewsletter` |
| Wishlist item | `wishlistItem(id:)` *(admin)*, `wishlistItems` *(admin)*, `myWishlistItems` | `addWishlistItem`, `removeWishlistItem`, `moveToCartWishlistItem`, `syncWishlistItem` |

### Store, directory & tax

| Resource | Queries | Mutations |
|---|---|---|
| Store config | `currentStoreConfig`, `storeConfig(id:)`, `storeConfigs` | - |
| Country | `country(id:)`, `countries` | - |
| Tax class / rate / rule *(admin)* | `taxClass` / `taxClasses`, `taxRate` / `taxRates`, `taxRule` / `taxRules` | - (writes are REST-only) |
| URL resolver | `resolveUrlResolveResult(path:)` | - |

`resolveUrlResolveResult` resolves a storefront path to its target entity. The doubled noun is unavoidable under the naming rule - naming it `resolveUrl` would force a `Url` type and a nonsense `url` / `urls` list query - so it's kept as-is.
