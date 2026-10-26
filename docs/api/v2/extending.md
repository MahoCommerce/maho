---
description: Extend and deploy the Maho v2 API - scaffold new API Platform resources with the CLI, add DTOs and providers from custom modules, plus deployment notes.
---

# Extending & Deployment <span class="version-badge">v26.7+</span>

## Adding a New API Resource

### Scaffolding with the CLI

The fastest way to add a resource is the built-in generator:

```bash
$ ./maho dev:api:resource:create
```

It prompts for (or accepts as options) the target module, the resource short name and the Mage model alias, then writes a ready-to-use DTO and provider into the module's `Api/` directory:

```bash
$ ./maho dev:api:resource:create --module=MyVendor_Widgets --resource=WidgetType --model=widgets/type
```

- For flat-table models it introspects the table and pre-fills typed public properties: the primary key is mapped to the `id` identifier, `created_at`/`updated_at` are marked read-only, and nullability follows the column definitions.
- For EAV entities (whose attributes are not plain table columns) it emits a property stub to fill in manually, pointing at `Mage/Catalog/Api/ProductProvider.php` as the custom-provider reference.
- Full CRUD + GraphQL operations are generated with admin-gated `security:` expressions. The permission ids in those expressions are derived by the same compiler code that builds the permission registry, so they can never drift from the registered ids.

Options: `--route` (REST URI base, defaults to the kebab-cased plural of the resource), `--section` (admin permission section, defaults to the module name), `--with-processor` (generate a custom processor stub instead of reusing the shared `CrudProcessor`), `--force` (overwrite existing files), `--dry-run` (print the files instead of writing them).

After generating, run `composer dump-autoload` (compiles the permission registry from the attribute) and `./maho cache:flush` (refreshes API resource discovery). The resource is then live over REST and GraphQL, with ACL entries in the admin role editor. Reads are gated behind `<resource>/read` by default; change the read operations' `security:` to `'true'` to make them public.

To audit the resources discovered across your installation, with REST routes, HTTP methods, GraphQL availability, access level and permission ids:

```bash
$ ./maho dev:api:resource:list
$ ./maho dev:api:resource:list --module=Maho_Blog --json
```

### Declaring resources manually

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

After adding or modifying the attribute, run `composer dump-autoload`. The compiler walks every class carrying `#[Maho\Config\ApiResource]` (anywhere in `app/code/` or installed packages) and emits `vendor/composer/maho_api_permissions.php`, which `Maho\ApiPlatform\Security\ApiPermissionRegistry` reads to populate the **admin role editor UI** and the list of valid `resource/operation` permission ids.

Authorization itself does **not** go through the registry. Each operation declares the permission it requires literally in its own `security:` expression (e.g. `security: "is_granted('orders/read')"`); API Platform's access checker evaluates that expression for both REST **and** GraphQL and routes the `resource/operation` attribute to `Maho\ApiPlatform\Security\ApiUserVoter`. So the permission you grant in the role editor is simply the `resource/operation` string an operation names in its `security:`.

### Auto-derivation

Most permission-registry fields are derived from the API Platform metadata on the same attribute, set them explicitly only when defaults are wrong:

| Maho field         | Derived from when omitted                                          |
|--------------------|--------------------------------------------------------------------|
| `mahoId`           | `shortName` → kebab-case + plural (`Cart` → `carts`, `CmsPage` → `cms-pages`) |
| `mahoLabel`        | Title-cased `mahoId` (`cms-pages` → `CMS Pages`; ≤3-char segments are upper-cased as acronyms) |
| `mahoSection`      | Module segment of the namespace (`Mage\Catalog\Api\Foo` → `'Catalog'`) |
| `mahoOperations`   | One entry per operation type present in `operations: [...]`. Default labels: `read`/`create`/`write`/`delete` → `View`/`Create`/`Update`/`Delete` |
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

The API Platform loads a dedicated `api` event area (`Mage_Core_Model_App_Area::AREA_API`), similar to `frontend` and `adminhtml`. Observers declared with `#[Maho\Config\Observer('event_name', area: 'api')]` only load when the API is running, they won't fire on regular frontend, admin, or cron requests. Run `composer dump-autoload` after adding or changing observer attributes.

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

**1. Write the observer** with `#[Maho\Config\Observer]` attributes (then run `composer dump-autoload`):

```php
class Vendor_SimpleBundles_Model_Api_Observer
{
    #[Maho\Config\Observer('api_product_dto_build', area: 'api')]
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

    #[Maho\Config\Observer('api_cart_item_dto_build', area: 'api')]
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

**2. API response** now includes the extension data:

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

In production the compiled container is never invalidated automatically: `public/rest.php` boots the kernel with debug off, and in that mode Symfony reuses an existing compiled container without any freshness check, it does not compare file mtimes. Deleting `var/cache/api_platform/{env}/` is therefore part of every deploy that changes API resources, not a fallback; the next request (or the pre-warm above) recompiles.

A few values are baked into the compiled container from store configuration rather than read per request: the CORS allowlist (`apiplatform/general/cors_origins`, falling back to the store's own origin) and the [MCP host allowlist](mcp.md), both derived from your base URLs. Changing a base URL, adding a store, or editing the CORS origins therefore needs the same `var/cache/api_platform/` delete, even though no code changed.
