---
description: "Run code in another store view with Mage::app()->emulate(), which switches the store, the design, the locale and the translations, and always puts them back."
---

# Store emulation <span class="version-badge">v27.1+</span>

Store emulation runs a part of a request in another store view. An admin request uses it to send an order email in the store of the order. A cron job uses it to send a newsletter in the store of each subscriber. A PDF uses it to print each document in the store of its order.

```php
$html = Mage::app()->emulate($order->getStoreId(), fn(): string => $block->toHtml());
```

`emulate()` switches the current store, the design, the locale and the translations to the ones of the store. Then it runs the callback and returns its value. In a `finally` block, it puts back the context of the caller, so the context comes back also when the callback throws an exception or a PHP `Error`.

The store decides the area. The admin store (`0`) runs in the admin area, with the package and the themes of the admin. Any other store runs in the frontend area, with its own package and themes.

## What emulate() does

`emulate()` does these steps:

1. It records the current store, the design, the locale and the translator in a `Mage_Core_Model_App_Environment` snapshot.
2. It sets the design of the store.
3. It sets the current store.
4. It sets the locale of the store, from `general/locale/code`.
5. It loads the translations for the store, the locale and the area. With the translation cache on, it reads them from the cache.
6. It runs the callback.
7. It puts back the snapshot.

If a step before the callback fails, `emulate()` puts back the snapshot and throws the error.

### The fast path

If the request already runs in the store, and its translator is loaded for that store and area, `emulate()` runs the callback as it is. In this case the locale of the caller stays. So an admin page that emulates the admin store keeps the interface locale of the admin user.

A nested call uses the fast path too. An email that a caller renders inside `emulate()` for the same store does not switch the context again.

### What emulate() does not switch

`emulate()` does not switch these parts of the request:

- The layout. `Mage::app()->getLayout()` gives the layout of the caller, with the blocks that the caller already made.
- The registry. Values in `Mage::registry()` stay.
- The session, with the customer and the currency that it holds.

If the callback needs one of these for the emulated store, set it in the callback and put it back before the callback returns.

## Email templates

An email template renders in the store of its design config. `send()` renders the body and the subject in one emulation:

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

Replace `startEnvironmentEmulation()` and `stopEnvironmentEmulation()`:

```php
// Old
$emulation = Mage::getSingleton('core/app_emulation');
$environment = $emulation->startEnvironmentEmulation($storeId);
$html = $block->toHtml();
$emulation->stopEnvironmentEmulation($environment);

// New
$html = Mage::app()->emulate($storeId, fn(): string => $block->toHtml());
```

The result of `startEnvironmentEmulation()` holds only an `environment` key. `getInitialDesign()`, `getInitialLocaleCode()` and `getInitialTranslatorState()` on it return null.

Replace `emulateDesign()` and `revertDesign()` of a template with its design config:

```php
// Old
$template->emulateDesign($storeId);
$template->send($email, $name, $variables);
$template->revertDesign();

// New
$template->setDesignConfig(['area' => 'frontend', 'store' => $storeId]);
$template->send($email, $name, $variables);
```

Replace `emulate()` and `revert()` of the locale model:

```php
// Old
Mage::app()->getLocale()->emulate($storeId);
$format = Mage::app()->getLocale()->getJsPriceFormat();
Mage::app()->getLocale()->revert();

// New
$format = Mage::app()->emulate($storeId, fn(): array => Mage::app()->getLocale()->getJsPriceFormat());
```

`Mage_Core_Model_Locale::emulate()` does nothing for the admin store (`0`). Remove such a call. Do not replace it.
