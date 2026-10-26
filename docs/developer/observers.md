---
description: "React to events in Maho with the observer pattern, registering methods with the #[Maho\\Config\\Observer] PHP attribute and compiling them via composer dump-autoload."
---

# Event observers

Maho uses an **observer pattern** to let modules react to events dispatched throughout the system. When something significant happens (a product is saved, a customer logs in, an order is placed), the system fires a named event, and any registered observer method is called.

## Dispatching events

Events are fired with `Mage::dispatchEvent()`:

```php
Mage::dispatchEvent('catalog_product_save_after', ['product' => $product]);
```

The second argument is an associative array of data made available to observers through the `$observer` parameter.

## Registering observers

!!! info "v26.5+"
    Since v26.5, observers are defined using PHP attributes directly on the method. Previously they were configured in XML - see [Migrating from XML](#migrating-from-xml) if you're upgrading an existing module.

Place the `#[Maho\Config\Observer]` attribute on a public method to register it as an event observer:

```php
class Mage_Wishlist_Model_Observer
{
    #[Maho\Config\Observer('customer_login')]
    public function customerLogin(Maho\Event\Observer $observer): void
    {
        $customer = $observer->getEvent()->getCustomer();
        // ...
    }
}
```

After adding or changing any observer attribute, run:

```bash
composer dump-autoload
```

This compiles all attributes into `vendor/composer/maho_attributes.php`, which is what the runtime reads.

### Parameters

| Parameter  | Type     | Default      | Description |
|------------|----------|--------------|-------------|
| `event`    | `string` | *(required)* | Event name to observe (e.g. `'catalog_product_save_after'`) |
| `area`     | `string` | `'global'`   | Area scope: `'global'`, `'frontend'`, `'adminhtml'`, `'crontab'`, `'install'`. Comma-separated for multiple areas (e.g. `'frontend,adminhtml'`) |
| `type`     | `string` | `'model'`    | `'model'` (new instance per dispatch) or `'singleton'` (shared instance) |
| `id`       | `?string`| auto         | Observer identifier. Set explicitly when other code references this observer by id |
| `replaces` | `?string`| `null`       | The `id` of another observer on the same event/area to disable and replace |

### Area scoping

By default, observers are **global** - they fire regardless of the current area. Use the `area` parameter only when the observer must be restricted to a specific area:

```php
// Only fires in the frontend
#[Maho\Config\Observer('controller_action_predispatch_customer_account_createpost', area: 'frontend')]
public function verify(Maho\Event\Observer $observer): void { }

// Only fires in the admin panel
#[Maho\Config\Observer('admin_user_authenticate_before', area: 'adminhtml')]
public function verifyAdmin(Maho\Event\Observer $observer): void { }
```

You can also target **multiple areas** with a comma-separated string, which registers the observer in each area without repeating the attribute:

```php
#[Maho\Config\Observer('some_event', area: 'frontend,adminhtml')]
public function handleBothAreas(Maho\Event\Observer $observer): void { }
```

### Multiple events on one method

The attribute is repeatable - apply it multiple times to listen to different events:

```php
#[Maho\Config\Observer('controller_action_predispatch_checkout_onepage_savebilling', area: 'frontend')]
#[Maho\Config\Observer('controller_action_predispatch_contacts_index_post', area: 'frontend')]
#[Maho\Config\Observer('controller_action_predispatch_customer_account_createpost', area: 'frontend')]
public function verify(Maho\Event\Observer $observer): void
{
    // Runs for all three events
}
```

### Named observers

Use the `id` parameter when other code needs to reference this observer:

```php
#[Maho\Config\Observer('sales_order_invoice_pay', id: 'giftcard_create_on_payment')]
public function createGiftcardsOnInvoicePaid(Maho\Event\Observer $observer) { }
```

### Singleton instantiation type

By default, observers use `model` (a fresh instance per dispatch), which is the safer choice - no risk of leaked state between event handlers. Use `type: 'singleton'` only when your observer needs to share state across multiple dispatches within the same request:

```php
#[Maho\Config\Observer('catalog_product_save_after', type: 'singleton')]
public function trackChanges(Maho\Event\Observer $observer) { }
```

### Replacing existing observers

The `replaces` parameter lets a new observer disable and replace an existing one. This is useful for third-party modules overriding core behavior:

```php
#[Maho\Config\Observer('customer_login', replaces: 'original_observer_id')]
public function myCustomLogin(Maho\Event\Observer $observer): void { }
```

The `replaces` value accepts:

- An explicit observer `id` (e.g. `'my_observer'`)
- A class/method string (e.g. `'Mage_Catalog_Model_Observer::myMethod'`)
- A class alias format (e.g. `'catalog/observer::myMethod'`)

## How it works under the hood

- Compiled observers use **class aliases** (not fully-qualified class names), so the rewrite system is fully respected. If a module rewrites a model class, attribute-based observers resolve through the rewrite chain transparently.
- Observer ordering follows **module dependency**, regardless of whether the observer was defined via XML or attribute.
- Observer exceptions are **not** isolated: the dispatch loop has no try/catch, so an exception thrown by one observer propagates up and aborts the remaining observers for that event. The only built-in guard is for a missing observer method (or an uninitialized class), and it only triggers in developer mode, where it raises an exception rather than logging.

## Notable built-in events

Maho dispatches events throughout the request lifecycle, model save flows, and admin actions. A few are designed as integration points for cross-cutting concerns:

- **`controller_action_predispatch_session_start`** <span class="version-badge">v26.5+</span> - fires in `Mage_Core_Controller_Varien_Action::preDispatch()` immediately before the session-start guard. Lets observers flip `FLAG_NO_START_SESSION` at runtime to skip session start on cacheable anonymous GETs, so the response carries no `Set-Cookie` and no empty session row is written. See [Edge caching integration](../hosting/edge-caching.md).

## Migrating from XML

If you're upgrading a module that used XML-based observer configuration:

**Before (config.xml):**

```xml
<config>
    <global>
        <events>
            <customer_login>
                <observers>
                    <wishlist>
                        <class>wishlist/observer</class>
                        <method>customerLogin</method>
                    </wishlist>
                </observers>
            </customer_login>
        </events>
    </global>
</config>
```

**After (PHP attribute):**

```php
#[Maho\Config\Observer('customer_login')]
public function customerLogin(Maho\Event\Observer $observer): void { }
```

The area mapping:

| XML location             | Attribute `area` value                |
|--------------------------|---------------------------------------|
| `<global><events>`       | `'global'` (default, can be omitted)  |
| `<frontend><events>`     | `area: 'frontend'`                    |
| `<adminhtml><events>`    | `area: 'adminhtml'`                   |
| `<crontab><events>`      | `area: 'crontab'`                     |

Remove the corresponding XML blocks from `config.xml` after migrating: both sources are read at runtime and merged into a single list keyed by observer name, so an XML and an attribute observer that share the same name silently overwrite each other (one is dropped without warning).

### Automated migration

Two commands cover this end-to-end:

```bash
./maho health-check                         # detect remaining <events> blocks
./maho legacy:migrate-observers --dry-run   # preview the rewrite
./maho legacy:migrate-observers             # apply
```

`legacy:migrate-observers` scans `app/code/local` and `app/code/community`, resolves each `<class>` alias to a concrete class via `Mage::getConfig()`, inserts `#[Maho\Config\Observer]` above the target method, and removes the migrated XML block. The legacy `<observer_name>` is preserved as an explicit `id:` argument so any `replaces:` references in third-party modules continue to resolve. Run `composer dump-autoload` (or click *Recompile PHP Attributes* on the cache page) after applying. See [Automated migration in routing.md](routing.md#automated-migration) for the full list of migration commands and the route command's caveats.
