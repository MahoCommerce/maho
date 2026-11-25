---
description: Move an integration from the legacy REST API (Mage_Api2) or the SOAP and XML-RPC API (Mage_Api) to the v2 REST and GraphQL API, with the map of routes, fields, filters and errors.
---

# Migrate from the legacy APIs <span class="version-badge">v26.7+</span>

The legacy REST API at `/api/rest` (module `Mage_Api2`) and the SOAP, XML-RPC and JSON-RPC APIs (module `Mage_Api`) are deprecated. This page tells you how to move an integration to the v2 API. The tables come from a comparison of the answers of both APIs for the same products, customers and orders.

!!! tip "Migrate with an AI agent"
    To let an AI coding agent change the code of your integration, give it the [migration prompt](migration-prompt.md).

## Before you start

- Both APIs can run at the same time. Turn on **REST v2 (new)** in **System > Configuration > Services > API > API Protocols**, and keep the legacy protocol on. Then move one resource at a time.
- Each store has its own OpenAPI document at `/api/docs.json`. It lists every v2 operation and field of the installed version, including the resources of third-party modules. Use it to find the exact fields of a resource.
- The v2 API is beta. A field name can still change, and the release notes list each change.

## What changes

| Topic | Legacy REST | v2 |
|-------|-------------|----|
| Base URL | `/api/rest` | `/api/rest/v2` for REST, `/api/graphql` for GraphQL |
| Authentication | OAuth 1.0a: three steps, then a signature on each request | A bearer token (JWT) from `POST /auth/token` |
| Permissions | **REST - Roles** and **REST - Attributes** under **System > Web Services** | API users and roles under **System > API** |
| Formats | JSON or XML | JSON, or JSON-LD with `Accept: application/ld+json` |
| Field names | `snake_case` | `camelCase` |
| Record ID | `entity_id`, `item_id`, `post_id` | `id` |
| Numbers | Strings: `"28.0000"` | Numbers: `28` |
| Yes/No values | `0` and `1` | `false` and `true` |
| A field with no value | `null` | Not in the answer, or `null` |
| Lists of products, customers and orders | A JSON object, keyed by the record ID | A JSON array |
| Store view | `/store/:store` in the path | `?store=<code>` or the `X-Store-Code` header |
| Store of an admin read | The admin scope (default values) | The default store view. Send `?store=admin` for the admin scope |
| Pagination | `page` and `limit` (default 10, maximum 100) | `page` and `itemsPerPage` (default 20, maximum 100) |
| Filters | `filter[n][attribute]` with an operator | Named filters for each resource, see [Filters and sort](#filters-and-sort) |
| Sort | `order` and `dir` | `sortBy` and `sortDir` (products only) |
| A field that a write does not accept | Ignored | `400` with the name of the field, for products, customers, blog posts and comments |
| Errors | `{"messages": {"error": [{"code": 404, "message": "..."}]}}` | `{"error": "not_found", "message": "...", "code": 404}`, see [Errors](errors.md) |

## Authentication

Pick the v2 grant from the user type of the legacy integration. See [Authentication](authentication.md) for the requests and the answers.

| Legacy user | v2 |
|-------------|----|
| Admin: an OAuth consumer with an admin token | An API user under **System > API > Users**, with a role that gives the resources of the integration. Get a token with the `client_credentials` grant. |
| SOAP, XML-RPC or JSON-RPC API user | A new API user, the same as for an admin integration. |
| Customer: an OAuth consumer with a customer token | The `customer` grant with the email and the password of the customer. Use the `/customers/me` routes. |
| Guest | No token. A read of public data needs no `Authorization` header. |

Do not move a SOAP integration with the `api_user` grant. The token of this grant has the SOAP resources of the role, such as `catalog/product/info`. v2 checks the v2 resources, such as `products/read`, so each request gives `403`. A role cannot have the two sets of resources: a save in the SOAP role editor or in the v2 role editor replaces all the resources of the role.

```bash
curl -X POST https://your-domain.com/api/rest/v2/auth/token \
  -H 'Content-Type: application/json' \
  -d '{"grant_type": "client_credentials", "client_id": "...", "client_secret": "..."}'
```

Send the token in each request: `Authorization: Bearer <token>`. A token expires after `expires_in` seconds. Get a new token when a request gives a `401`.

## Route map

The paths of this section are relative to `/api/rest` (legacy) and `/api/rest/v2` (v2). In v2, a `PUT` on these resources changes only the fields in the body, as the legacy `PUT` did.

### Products

| Legacy | v2 | Notes |
|--------|----|-------|
| `GET /products` | `GET /products` | |
| `GET /products/store/:store` | `GET /products?store=<code>` | |
| `GET /products/category/:category_id` | `GET /products?categoryId=<id>` | The same for the two routes with `/store/:store` |
| `GET /products/:id` | `GET /products/{id}` | |
| `GET /products/:id/store/:store` | `GET /products/{id}?store=<code>` | |
| `POST /products` | `POST /products` | |
| `PUT /products/:id` | `PUT /products/{id}` | |
| `PUT /products/:id/store/:store` | `PUT /products/{id}?store=<code>` | Writes the values of the store view. `useDefault` lists the attributes that go back to the default value. |
| `DELETE /products/:id` | `DELETE /products/{id}` | |

### Product categories and websites

v2 has no sub-resource for the categories and the websites of a product. Read and write the `categoryIds` and `websiteIds` fields of the product. A write replaces the full list.

| Legacy | v2 |
|--------|----|
| `GET /products/:id/categories` | `GET /products/{id}`, field `categoryIds` |
| `POST /products/:id/categories` | `PUT /products/{id}` with `categoryIds` that includes the new ID |
| `DELETE /products/:id/categories/:category_id` | `PUT /products/{id}` with `categoryIds` that does not include the ID |
| `GET /products/:product_id/websites` | `GET /products/{id}`, field `websiteIds` |
| `POST /products/:product_id/websites` | `PUT /products/{id}` with `websiteIds` that includes the new ID |
| `DELETE /products/:product_id/websites/:website_id` | `PUT /products/{id}` with `websiteIds` that does not include the ID |

### Product images

| Legacy | v2 | Notes |
|--------|----|-------|
| `GET /products/:id/images` | `GET /products/{productId}/media` | |
| `GET /products/:id/images/store/:store` | `GET /products/{productId}/media?store=<code>` | |
| `GET /products/:id/images/:image` | `GET /products/{productId}/media` | Find the image by `id` in the list. |
| `GET /products/:id/images/:image/store/:store` | `GET /products/{productId}/media?store=<code>` | Find the image by `id` in the list. |
| `POST /products/:id/images` | `POST /products/{productId}/media` | Send `base64` and `filename`, or `imageUrl`. |
| `PUT /products/:id/images/:image` | `PUT /products/{productId}/media` | Send the image `id` in the body. |
| `DELETE /products/:id/images/:image` | `DELETE /products/{productId}/media?valueId=<id>` | |

### Stock items

The legacy routes use the ID of the stock item. v2 uses the SKU to write and the product to read.

| Legacy | v2 | Notes |
|--------|----|-------|
| `GET /stockitems` | `GET /products` | Each product has `stockQty` and `stockStatus`. v2 has no list of the stock settings. |
| `GET /stockitems/:id` | `GET /products/{id}`, field `stockItem` | Use the `product_id` of the stock item, not its `item_id`. |
| `PUT /stockitems/:id` | `PUT /inventory` | Send the `sku` of the product in the body. |
| `PUT /stockitems` | `PUT /inventory/bulk` | Maximum 100 items in one request |

### Customers and addresses

| Legacy | v2 | Notes |
|--------|----|-------|
| `GET /customers` | `GET /customers` | |
| `GET /customers/:id` | `GET /customers/{id}` | A customer token reads `GET /customers/me`. |
| `PUT /customers/:id` | `PUT /customers/{id}` | A customer token writes `PUT /customers/me`. |
| `DELETE /customers/:id` | None | See [No counterpart in v2](#no-counterpart-in-v2). |
| `GET /customers/:customer_id/addresses` | `GET /customers/{customerId}/addresses` | A customer token reads `GET /customers/me/addresses`. |
| `POST /customers/:customer_id/addresses` | `POST /customers/{customerId}/addresses` | A customer token writes `POST /customers/me/addresses`. |
| `GET /customers/addresses/:id` | `GET /addresses/{id}` | |
| `PUT /customers/addresses/:id` | `PUT /addresses/{id}` | |
| `DELETE /customers/addresses/:id` | `DELETE /addresses/{id}` | |

### Orders

The items, the addresses and the comments of an order are fields of the order in v2.

| Legacy | v2 | Notes |
|--------|----|-------|
| `GET /orders` | `GET /orders` | A customer token reads `GET /customers/me/orders`. |
| `GET /orders/:id` | `GET /orders/{id}` | A customer token reads only the orders of the customer. |
| `GET /orders/:id/items` | `GET /orders/{id}`, field `items` | |
| `GET /orders/:order_id/addresses` | `GET /orders/{id}`, fields `billingAddress` and `shippingAddress` | |
| `GET /orders/:order_id/addresses/:address_type` | `GET /orders/{id}`, field `billingAddress` or `shippingAddress` | |
| `GET /orders/:id/comments` | `GET /orders/{id}`, field `statusHistory` | |
| `GET /orders/comments/:id` | `GET /orders/{id}`, field `statusHistory` | Use the ID of the order, not the ID of the comment. v2 has no route for one comment. |
| `POST /orders/:id/comments` | `POST /orders/{id}/comments` | Send `comment`, `status`, `notifyCustomer` and `visibleOnFront`. |

v2 also has the order actions that the legacy REST API did not have: invoices, shipments, credit memos, hold, unhold and cancel. See [Orders](endpoints.md#orders).

### Blog posts

| Legacy | v2 |
|--------|----|
| `GET /blog/posts` | `GET /blog-posts` |
| `GET /blog/posts/:id` | `GET /blog-posts/{id}` |
| `POST /blog/posts` | `POST /blog-posts` |
| `PUT /blog/posts/:id` | `PUT /blog-posts/{id}` |
| `DELETE /blog/posts/:id` | `DELETE /blog-posts/{id}` |

The legacy list gives all the posts. The v2 list gives only the published posts: active, with no publish date or a publish date that is not in the future. To also get the disabled posts and the scheduled posts, send `GET /blog-posts?scope=all`. Only a token of an admin or an API user can send `scope=all`.

## Field differences

The general rule is: change `snake_case` to `camelCase`, and change `entity_id` to `id`. The tables below list only the fields that do not follow this rule.

### Products

| Legacy | v2 |
|--------|----|
| `type_id` | `type` |
| `status`: `1` or `2` | `status`: `enabled` or `disabled`, to read. `isActive`: `true` or `false`, to write. |
| `visibility`: `1` to `4` | `visibility`: `not_visible`, `catalog`, `search` or `catalog_search` |
| A user-defined attribute, such as `color`, at the top level | A key of `customAttributes`. A multiselect is a list of option IDs: `"160,161"` becomes `[160, 161]`. |
| `stock_data` | `stockItem` to read, with the keys in `snake_case`. `stockData` to write, with the keys in `snake_case` or `camelCase`. |
| `tier_price` | `tierPrices` to read. Write with `/products/{productId}/tier-prices`. |
| `group_price` | `/products/{productId}/group-prices` |
| `regular_price_with_tax`, `regular_price_without_tax` | `price` |
| `final_price_with_tax`, `final_price_without_tax` | `finalPrice` |
| `is_saleable`, `is_in_stock` | `stockStatus`: `in_stock` or `out_of_stock` |
| `total_reviews_count` | `reviewCount` |
| `has_custom_options` | `customOptions` (the list) and `hasRequiredOptions` |
| `url` | `urlKey` and `urlPath` |
| `buy_now_url` | None |

### Product images

| Legacy | v2 |
|--------|----|
| `exclude` | `disabled` |
| `file_content` | `base64` |
| `file_name` | `filename` |
| `file_mime_type` | None. The API reads the type from the content. |

### Stock items

| Legacy | v2 |
|--------|----|
| `item_id`, `product_id`, `stock_id` | `sku` in `PUT /inventory` |
| The other fields, in `snake_case` | `PUT /inventory` takes them in `camelCase`: `qty`, `isInStock`, `manageStock`, `useConfigManageStock` |
| `low_stock_date`, `stock_status_changed_auto` | None |

### Customers and addresses

| Legacy | v2 |
|--------|----|
| `confirmation` | `isConfirmed` |
| `last_logged_in` | None |
| `is_default_billing`, `is_default_shipping`: `0` or `1` | `isDefaultBilling`, `isDefaultShipping`: `false` or `true` |

### Orders

| Legacy | v2 |
|--------|----|
| The amounts, such as `grand_total` and `base_subtotal` | The same names in `camelCase`, in the `prices` object |
| `shipping_incl_tax` | `prices.shippingAmountInclTax` |
| `store_currency_code` | `currency` |
| `payment_method` | `paymentMethod` (the code) and `paymentMethodTitle` |
| `tax_name`, `tax_rate` | None. Each item has `taxPercent`. |
| `gift_message_from`, `gift_message_to`, `gift_message_body` | None |
| `store_to_order_rate`, `base_shipping_tax_amount`, `base_shipping_discount_amount`, `base_subtotal_incl_tax` | None |
| Address `street`: one string, with a line break between the lines | Address `street`: a list of lines |
| Item `item_id` | Item `id` |
| Item `base_original_price`, `base_price_incl_tax`, `base_row_total_incl_tax`, `updated_at` | None |
| Comment `comment` | `statusHistory[].note` |
| Comment `is_customer_notified`, `is_visible_on_front` | `statusHistory[].isCustomerNotified`, `statusHistory[].isVisibleOnFront` |

### Blog posts

| Legacy | v2 |
|--------|----|
| `post_id` | `id` |
| `is_active` | `isActive` to write, `status`: `enabled` or `disabled`, to read |

## Filters and sort

The legacy `filter[n][attribute]` parameter takes any attribute and an operator. v2 has a fixed set of filters for each resource. For a filter that is not in this list, read the pages and filter the records in your code.

| Resource | v2 filters |
|----------|------------|
| Products | `search`, `sku` (part of the SKU), `categoryId`, `priceMin`, `priceMax`, `status`, `type`, `attributeFilters` (a JSON object of attribute codes and option IDs), `createdFrom`, `createdTo`, `updatedSince`, `sortBy`, `sortDir` |
| Customers | `search`, `email`, `telephone`, `groupId`, `websiteId`, `createdFrom`, `createdTo` |
| Orders | `status`, `state`, `email`, `emailLike`, `incrementId`, `search`, `customerId`, `storeId`, `createdFrom`, `createdTo`, `updatedSince` |
| Blog posts | `search`, `categoryId`, `urlKey`, `scope=all` |

A product search with many filters can use `QUERY /products` with the filters in a JSON body. See [Conventions](conventions.md#pagination) for the pagination of the answer.

## From SOAP, XML-RPC and JSON-RPC

Replace `login()` with a token of the `client_credentials` grant, see [Authentication](#authentication). Each call then becomes an HTTP request. The table gives the v2 path of each SOAP resource. The OpenAPI document of the store gives the operations of each path.

| SOAP resource | v2 |
|---------------|----|
| `catalog_product` | `/products`. `getSpecialPrice` and `setSpecialPrice` use the `specialPrice` field. |
| `catalog_product_attribute` | `/product-attributes`, `/product-attributes/{id}/options` |
| `catalog_product_attribute_set` | `/attribute-sets`, `/attribute-sets/{id}/attributes`, `/attribute-sets/{id}/groups` |
| `catalog_product_attribute_media` | `/products/{productId}/media` |
| `catalog_product_attribute_tier_price` | `/products/{productId}/tier-prices` |
| `catalog_product_link` | `/products/{productId}/links/related`, `/links/cross-sell`, `/links/up-sell`, `/products/{productId}/grouped` |
| `catalog_product_custom_option`, `catalog_product_custom_option_value` | `/products/{productId}/custom-options` |
| `catalog_product_downloadable_link` | `/products/{productId}/downloadable-links` |
| `catalog_product_type` | No route. The enum of the `type` field of a product in the OpenAPI document lists the product types. |
| `catalog_category` | `/categories`. `move` sets `parentId` with `PUT /categories/{id}`. `assignedProducts` is `GET /products?categoryId=<id>`. `assignProduct` and `removeProduct` change `categoryIds` of the product. |
| `cataloginventory_stock_item` | `/inventory`, `/inventory/bulk`, and `stockItem` of the product |
| `cart`, `cart_product`, `cart_customer`, `cart_shipping`, `cart_payment`, `cart_coupon` | `/carts` and `/carts/{id}/...`, see [Cart](endpoints.md#cart-authenticated) |
| `giftmessage` | `/carts/{id}/gift-message`, `/carts/{id}/items/{itemId}/gift-message` |
| `customer` | `/customers` |
| `customer_address` | `/customers/{customerId}/addresses`, `/addresses/{id}` |
| `customer_group` | `/customer-groups` |
| `directory_country`, `directory_region` | `/countries`, `/countries/{id}` (with the regions) |
| `core_store` | `/stores` |
| `core_maho` | `/build-info` |
| `sales_order` | `/orders`, `/orders/{id}/comments`, `/orders/{id}/hold`, `/orders/{id}/unhold`, `/orders/{id}/cancel` |
| `sales_order_invoice` | `/orders/{orderId}/invoices`, `/invoices/{id}`, `/invoices/{id}/comments`, `/invoices/{id}/capture`, `/invoices/{id}/void`, `/invoices/{id}/cancel` |
| `sales_order_shipment` | `/orders/{orderId}/shipments`, `/shipments/{id}`, `/shipments/{id}/comments`, `/shipments/{id}/tracks` |
| `sales_order_creditmemo` | `/orders/{orderId}/credit-memos`, `/credit-memos/{id}`, `/credit-memos/{id}/comments` |
| `blog_post` | `/blog-posts` |

## No counterpart in v2

These legacy operations have no v2 counterpart in this version.

| Legacy | What to do |
|--------|------------|
| Delete a customer: `DELETE /customers/:id`, SOAP `customer.delete` | Delete the customer in the admin. |
| The gift message of an order: `gift_message_from`, `gift_message_to`, `gift_message_body` | None |
| A list of the stock settings of all products | Read `stockItem` with `GET /products/{id}` for each product. |
| Copy the website values to the store views: `copy_to_stores` in `POST /products/:product_id/websites` | None |
| Product tags: SOAP `catalog_product_tag` | None |
| The category attributes: SOAP `catalog_category_attribute` | `GET /custom-attributes?entityType=catalog_category` lists the attributes that an admin added. The system attributes have no route. |
| Cancel a credit memo: SOAP `sales_order_creditmemo.cancel` | None |
| Send the shipment email: SOAP `sales_order_shipment.sendInfo` | Send it from the admin. |

If your integration needs one of these operations, [open an issue](https://github.com/MahoCommerce/maho/issues).

## Procedure

1. Turn on **REST v2 (new)**. Keep the legacy protocol on.
2. Under **System > API > Roles**, create a role with the resources that the integration uses.
3. Under **System > API > Users**, create a user with this role. Copy the client ID and the client secret.
4. Get a token, then send one read request to make sure that the token works.
5. Move the reads of one resource. Compare the answers of both APIs for the same records.
6. Move the writes of the same resource.
7. Do steps 5 and 6 for each resource.
8. Make a list of the clients of each legacy protocol. Include the clients that run rarely, such as a monthly export.
9. Turn off a legacy protocol only when these two conditions are true:
    - All the clients of the protocol use v2.
    - The access log of the web server shows no request to the path of the protocol for a period longer than the longest interval between two runs of a client. The paths are `/api/rest` (outside `/api/rest/v2`), `/api/soap`, `/api/v2_soap`, `/api/xmlrpc`, `/api/jsonrpc` and `/api.php`. A request to `/api.php` uses the protocol in its `type` parameter, for example `/api.php?type=soap`.
10. Delete the OAuth consumers, the REST roles and the SOAP API users of the integration.
