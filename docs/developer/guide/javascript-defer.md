---
description: Improve Maho frontend performance with JavaScript deferral, moving scripts to the end of the body or activating them only on first user interaction via defer modes.
---

# JavaScript Deferral <span class="version-badge">v25.1+</span>

Maho includes a JavaScript deferral system that improves page load performance by controlling how `<script>` tags are loaded on frontend pages. Instead of blocking the initial render, scripts can be deferred or loaded only when a user interacts with the page.

## Configuration

Navigate to **System > Configuration > Developer > JavaScript Settings** in the admin panel. The **JavaScript Deferral Mode** setting (`dev/js/defer_mode`) offers three modes:

| Mode | Value | Description |
|------|-------|-------------|
| Disabled | `0` | Default behavior, scripts load normally |
| Defer Only | `1` | Moves all scripts to the bottom of `<body>` |
| Load on Intent | `2` | Scripts are neutralized and only activated on first user interaction |

## Defer Only Mode

The simplest and safest option. All `<script>` tags are extracted from their original positions in the HTML and moved to the bottom of `<body>`, just before the closing `</body>` tag. This allows the browser to parse and render the page content before downloading and executing JavaScript.

This mode preserves the original execution order of scripts and should be compatible with virtually all themes and extensions.

## Load on Intent Mode

A more aggressive optimization that delays JavaScript execution until the user actually interacts with the page.

### How it works

1. **Neutralization** - All `<script>` tags have their `type` attribute changed to `text/plain` and receive a `data-maho-script` index attribute. This prevents the browser from executing them.

2. **Intent detection** - The loader script (`maho-load-on-intent.js`) listens for the first user interaction via any of these events:
    - `click`, `keydown`, `mousemove`, `mouseover`, `touchstart`, `scroll`, `wheel`, `focusin`, `change`

3. **Parallel preload** - When an interaction is detected, all external scripts are immediately preloaded in parallel using `<link rel="preload" as="script">` tags.

4. **Sequential execution** - Scripts are then executed one by one in their original order. External scripts wait for their `onload` event before the next script runs, ensuring dependency order is preserved.

5. **Synthetic lifecycle events** - After all scripts have executed, the loader fires synthetic `DOMContentLoaded` and `load` events, and sets `document.readyState` to `"complete"`. This ensures scripts that rely on these lifecycle signals still work correctly.

!!! warning "Experimental"
    Load on Intent mode is designed for pages with vanilla JavaScript. It may not work correctly with legacy libraries or complex third-party scripts that make assumptions about load timing. Test thoroughly before enabling in production.

## Excluding Scripts from Deferral

Add the `data-maho-nodefer` attribute to any `<script>` tag that must execute immediately, regardless of the defer mode.

```html
<script data-maho-nodefer>
    // This script will execute immediately, even in Load on Intent mode
    window.criticalConfig = { storeId: 1 };
</script>
```

In a `.phtml` template:

```php
<script data-maho-nodefer src="<?= $this->getJsUrl('critical-analytics.js') ?>"></script>
```

Common use cases for `data-maho-nodefer`:

- Consent management / cookie banners
- Critical analytics or tracking pixels
- Essential inline configuration that other deferred scripts depend on

## Automatic Exclusions

The deferral system is automatically skipped in the following cases:

- **Admin panel** - All admin pages load scripts normally
- **Checkout pages** - The `checkout` module is excluded to avoid breaking payment flows
- **Non-HTML responses** - JSON, XML, and other non-HTML content types are not processed
- **Speculation rules** - Scripts with `type="speculationrules"` are never deferred
- **Already processed** - Pages whose output already contains the loader marker (`mahoLazyJs`) are not processed again

## Programmatic Configuration

The defer mode is stored at config path `dev/js/defer_mode`. Mode constants are defined in `Mage_Core_Model_Source_Js_Defer`:

```php
Mage_Core_Model_Source_Js_Defer::MODE_DISABLED       // 0
Mage_Core_Model_Source_Js_Defer::MODE_DEFER_ONLY     // 1
Mage_Core_Model_Source_Js_Defer::MODE_LOAD_ON_INTENT // 2
```

To check the current mode programmatically:

```php
$mode = (int) Mage::getStoreConfig('dev/js/defer_mode');
```
