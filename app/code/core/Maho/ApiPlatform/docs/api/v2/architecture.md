---
description: How the Maho v2 API is built on API Platform and Symfony - ApiResource DTOs, state providers and processors, event listeners, and JWT HS256 authentication.
---

# Architecture <span class="version-badge">v26.7+</span>

The API is built on [API Platform](https://api-platform.com/) (Symfony) integrated with Maho Commerce (PHP 8.3+, fork of OpenMage/Magento 1).

- **Entry point:** `public/rest.php`, bootstraps Maho, then hands off to Symfony
- **Resources:** PHP 8 `#[ApiResource]` DTOs, all extend `\Maho\ApiPlatform\Resource`
- **Providers:** State providers (read operations), all extend `\Maho\ApiPlatform\Provider`
- **Processors:** State processors (write operations), all extend `\Maho\ApiPlatform\Processor`
- **Event listeners:** Symfony listeners for cross-cutting concerns (caching, idempotency)
- **Authentication:** JWT (HS256) via the `lcobucci/jwt` library, with strict signature, issuer, audience, and validity-window constraints

**Module structure:**
```
app/code/core/Maho/ApiPlatform/
├── symfony/
│   ├── Resource.php         # Base class for all DTOs ($extensions)
│   ├── Provider.php         # Base class for all providers (auth + pagination)
│   ├── Processor.php        # Base class for all processors (auth + persistence)
│   ├── Kernel.php           # Symfony kernel (firewalls, services, routes)
│   ├── Trait/               # Opt-in traits (ProductLoader, Cache, ActivityLog, StoreAccess)
│   ├── Service/             # Shared services (StoreContext, JwtService, etc.)
│   ├── Security/            # Authentication (JWT, OAuth2, user providers, permission voter)
│   ├── EventListener/       # Cross-cutting concerns (caching, idempotency, store context)
│   └── ...
├── etc/config.xml           # Module config
└── sql/schema.php           # Declarative DB schema

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

Base class for all state processors. It bundles authentication (via `AuthenticationTrait`), the store scope of the token and the activity log. Its default `process()` creates, updates or deletes the model of `$modelAlias` with `loadModel()`, `saveModel()` and `deleteModel()`. A processor whose resource has a service overrides these three methods to call the service, as `Mage\Tax\Api\TaxClassProcessor` does. A processor with other writes overrides `process()` and calls its service: the service holds the rules, so that the admin and the CLI apply them too.

```php
final class MyProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        /** @var My_Module_Service_Entity $service */
        $service = Mage::getService('mymodule/entity');
        $model = $service->getById((int) $uriVariables['id']);
        $model->setName($data->name);
        $service->save($model);
        return MyResource::fromModel($model);
    }
}
```

### Opt-in Traits

Domain-specific traits that can be added to providers or processors as needed:

| Trait | Purpose | Used by |
|---|---|---|
| `ProductLoaderTrait` | Loads a product by ID through `Mage_Catalog_Service_Product`, in the store of the request, with an optional type constraint | Catalog sub-resource providers/processors |
| `CacheTrait` | Cache-aside `remember()` helper for provider responses | ReviewProvider |
| `ActivityLogTrait` | Logs write operations to the admin activity log | Product, Category, CMS, Blog processors |
| `StoreAccessTrait` | Resolves store codes to IDs and validates store-level access | CMS and Blog processors |

### Shared Services

Live under `app/code/core/Maho/ApiPlatform/symfony/Service/`:

| Service | Purpose |
|---|---|
| `StoreContext` | Store scope management, `ensureStore()`, `getStoreId()`, `getStore()`, `setStore()`, `isAvailableForStore()` |
| `JwtService` | JWT issuance/validation for customer and API-user tokens |
| `TokenBlacklist` | Tracks revoked JWT IDs (used by `/auth/logout` and on password change) |
| `StoreDefaults` | Gives the default country and the base currency of a store |
