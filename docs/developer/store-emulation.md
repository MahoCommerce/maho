---
description: "Run code in another store view with Mage::app()->emulate(), which switches the store, the design, the locale and the translations, and always puts them back."
---

# Store emulation <span class="version-badge">v27.1+</span>

Store emulation runs a part of a request in another store view. An admin request uses it to send an order email in the store of the order. A cron job uses it to send a newsletter in the store of each subscriber. A PDF uses it to print each document in the store of its order.

Each method on this page takes a callback. The method switches the context, runs the callback, and puts back the context of the caller in a `finally` block. So the context comes back also when the callback throws an exception or a PHP `Error`.

## Pick the method

| Method | Switches | Use it to |
|---|---|---|
| `Mage::app()->emulate($storeId, $callback, $area)` | The current store, the design, the locale and the translations | Render an email, a block or a PDF for another store view |
| `Mage::app()->withStore($storeId, $callback)` | The current store only | Read the configuration or the data of another store, with no output |
| `Mage::app()->getLocale()->withStoreLocale($store, $callback)` | The locale and the translations only | Format a number, a price or a date with the locale of a store |
| `Mage_Rule_Model_Condition_Metadata::runInLocale($locale, $callback)` | The admin store, a locale and the labels of rule conditions | Build the condition labels of a rule in one locale, for the API |

Each method returns the value of the callback:

```php
$html = Mage::app()->emulate($order->getStoreId(), function () use ($order): string {
    return Mage::app()->getLayout()
        ->createBlock('sales/order_email_items')
        ->setOrder($order)
        ->toHtml();
});
```

## What emulate() does

`emulate()` does these steps:

1. It records the current store, the design, the locale and the translator in a `Mage_Core_Model_App_Environment` snapshot.
2. It sets the design for the store and the area. The `frontend` area uses the package and the themes of the store. The `adminhtml` area uses the package and the themes of the admin store, as an admin request does.
3. It sets the current store.
4. It sets the locale of the store, from `general/locale/code`.
5. It loads the translations for the store, the locale and the area. With the translation cache on, it reads them from the cache.
6. It runs the callback.
7. It puts back the snapshot.

If a step before the callback fails, `emulate()` puts back the snapshot and throws the error.

The area is `frontend` when you do not give one. Use the `adminhtml` area for output that uses the admin templates, such as a sales PDF.

### The fast path

If the request already runs in the store and the area, and its translator is loaded for them, `emulate()` runs the callback as it is. In this case the locale of the caller stays. So an admin page that emulates the admin store keeps the interface locale of the admin user.

A nested call uses the fast path too. An email that a caller renders inside `emulate()` for the same store does not switch the context again.

### What emulate() does not switch

`emulate()` does not switch these parts of the request:

- The layout. `Mage::app()->getLayout()` gives the layout of the caller, with the blocks that the caller already made.
- The registry. Values in `Mage::registry()` stay.
- The session, with the customer and the currency that it holds.

If the callback needs one of these for the emulated store, set it in the callback and put it back before the callback returns.

## Email templates

An email template renders in the store and the area of its design config. `send()` renders the body and the subject in one emulation:

```php
$template = Mage::getModel('core/email_template');
$template->setDesignConfig(['area' => 'frontend', 'store' => $storeId]);
$template->send($email, $name, $variables);
```

Do not wrap `send()` in `emulate()` for the same store. The template does the emulation itself.

A subclass of `Mage_Core_Model_Template` runs code in the store of its design config with `withDesignConfig()`:

```php
return $this->withDesignConfig(fn(): string => $processor->filter($this->getTemplateText()));
```

## Move from the old methods

These methods are deprecated with the `#[\Deprecated]` attribute. Developer mode throws an exception on a call.

| Old code | New code |
|---|---|
| `$env = $emulation->startEnvironmentEmulation($storeId, $area);` ... `$emulation->stopEnvironmentEmulation($env);` | `Mage::app()->emulate($storeId, fn() => ..., $area);` |
| `$template->emulateDesign($storeId);` ... `$template->revertDesign();` | `$template->setDesignConfig(['area' => 'frontend', 'store' => $storeId]);` and render in `Mage::app()->emulate()` |
| `$locale->emulate($store);` ... `$locale->revert();` | `$locale->withStoreLocale($store, fn() => ...);` |

The result of `startEnvironmentEmulation()` holds only an `environment` key. `getInitialDesign()`, `getInitialLocaleCode()` and `getInitialTranslatorState()` on it return null.

`Mage_Core_Model_Locale::emulate()` does nothing for the admin store (`0`). Remove such a call. Do not replace it.
