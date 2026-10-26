---
description: Maho lets edge and full-page-cache modules skip session start for anonymous GETs, making CMS, category and product HTML safe to cache on Cloudflare or Varnish.
---

# Edge caching integration <span class="version-badge">v26.5+</span>

Maho dispatches a core event that lets full-page-cache (FPC) or edge-cache modules opt out of session start for cacheable anonymous GETs, without forking core controllers.

## Why session start matters for caching

`Mage_Core_Controller_Varien_Action::preDispatch()` always starts the session unless `FLAG_NO_START_SESSION` was set in the constructor. Today that flag is only set in 3 controllers (Api + 2 OAuth), so for anonymous traffic on CMS, category, or product routes, Maho:

1. emits `Set-Cookie: <session>` on the response, which makes the HTML unsafe to cache in any shared / edge cache (Cloudflare, Bunny, Varnish) - per RFC, `Set-Cookie` combined with `Cache-Control: public` is unsafe.
2. writes an empty session row on every anonymous hit and later garbage-collects it - non-trivial write amplification under crawler / bot traffic.

## The opt-out event

The `controller_action_predispatch_session_start` event fires in `preDispatch()` immediately before the `FLAG_NO_START_SESSION` guard. An observer can inspect the request and flip the flag to skip session start for that response.

Event payload:

| Key                 | Type                                  | Description                       |
|---------------------|---------------------------------------|-----------------------------------|
| `controller_action` | `Mage_Core_Controller_Varien_Action`  | The current controller instance   |

Behavior is fully backward compatible: when no observer is registered, `FLAG_NO_START_SESSION` semantics are unchanged.

## Example: skip session for anonymous cacheable GETs

```php
#[Maho\Config\Observer('controller_action_predispatch_session_start')]
public function maybeSkipSession(Maho\Event\Observer $observer): void
{
    $controller = $observer->getControllerAction();
    $request    = $controller->getRequest();
    $hasSid     = (bool) $request->getCookie(Mage_Core_Controller_Front_Action::SESSION_NAMESPACE);

    if ($request->isGet() && !$hasSid && $this->isCacheableRoute($controller)) {
        $controller->setFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_START_SESSION, true);
    }
}
```

The observer module decides what counts as a cacheable route - typically by matching the controller's module / controller / action against a configured allowlist.

## Caveat: form key on the first POST

If session start is skipped, `getFormKey()` returns empty for that response. The first state-changing POST from an anonymous visitor (e.g. add-to-cart) will fail `_validateFormKey()` and redirect - at that point the session establishes normally and subsequent requests work as usual.

This "first POST establishes session" pattern is acceptable in practice. The observing module is responsible for handling it - for example, by injecting a fresh form key client-side after the cached HTML is served, or by configuring the cache to bypass routes that submit forms.
