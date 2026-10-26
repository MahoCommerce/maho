---
description: Use over 6,000 optimized Tabler SVG icons built into Maho directly in your templates with getIconSvg(), no separate icon files to upload or manage.
---

# Maho Icons Library <span class="version-badge">v25.3+</span>

Maho includes a built-in library of **over 6,000 optimized SVG icons** from [Tabler Icons](https://tabler.io/icons) that you can use in your templates without needing to upload or manage separate icon files.

!!! info "Need to upload custom SVG files?"
    For information about uploading custom SVG favicons and placeholders via the admin panel, see the **[SVG Uploads](../hosting/svg-support.md)** documentation.

## Overview

The icons library provides:

- **~5,000 outline icons**: Stroke-based design, perfect for clean interfaces
- **~1,000 filled icons**: Solid fill design, perfect for emphasis and branding
- **Optimized SVG**: All icons are 24x24 viewBox and use `currentColor` for easy styling
- **Automatic caching**: Icons are cached for optimal performance
- **Zero configuration**: Available immediately after Maho installation

## Using Icons in Templates

### Basic Usage

In any block template (`.phtml` file), use the `getIconSvg()` helper method:

```php
<?= $this->getIconSvg('heart') ?>
<?= $this->getIconSvg('search', 'outline') ?>
<?= $this->getIconSvg('star', 'filled') ?>
```

### Method Parameters

**`getIconSvg(string $name, string $variant = 'outline', string $role = 'none'): string`**

- **`$name`** (required): Icon name without the `.svg` extension (e.g., 'heart', 'user', 'shopping-cart')
- **`$variant`** (optional): Either `'outline'` (default) or `'filled'`
- **`$role`** (optional): ARIA role attribute for accessibility (default: 'none')

For CSS backgrounds, use `getIconDataUrl()` instead (see [Using Icons as Data URLs](#using-icons-as-data-urls)):

**`getIconDataUrl(string $name, string $variant = 'outline', array $attributes = []): string`**

- **`$name`** (required): Icon name without the `.svg` extension
- **`$variant`** (optional): Either `'outline'` (default) or `'filled'`
- **`$attributes`** (optional): Attributes to replace on the SVG (e.g. `['fill' => '#ff0000']`); only replaces attributes that already exist

### Practical Examples

#### Navigation

```php
<!-- Mobile menu toggle -->
<button class="offcanvas-toggle" aria-label="<?= $this->__('Menu') ?>">
    <?= $this->getIconSvg('menu-2') ?>
</button>

<!-- Close button -->
<button class="close" aria-label="<?= $this->__('Close') ?>">
    <?= $this->getIconSvg('x') ?>
</button>
```

#### E-commerce Actions

```php
<!-- Shopping cart -->
<a href="<?= $this->getUrl('checkout/cart') ?>" class="cart-link">
    <?= $this->getIconSvg('shopping-cart') ?>
    <span class="cart-qty"><?= $this->helper('checkout/cart')->getSummaryCount() ?></span>
</a>

<!-- Wishlist -->
<a href="<?= $this->getUrl('wishlist') ?>" class="wishlist-link">
    <?= $this->getIconSvg('heart') ?>
</a>

<!-- Compare -->
<a href="<?= $this->helper('catalog/product_compare')->getListUrl() ?>">
    <?= $this->getIconSvg('arrows-diff') ?>
    <?= $this->__('Compare') ?>
</a>
```

#### Search

```php
<!-- Search form -->
<form action="<?= $this->getUrl('catalogsearch/result') ?>" method="get">
    <input type="text" name="q" placeholder="<?= $this->__('Search...') ?>" />
    <button type="submit" aria-label="<?= $this->__('Search') ?>">
        <?= $this->getIconSvg('search') ?>
    </button>
</form>
```

#### User Account

```php
<!-- Account dropdown -->
<div class="account-menu">
    <button class="account-toggle">
        <?= $this->getIconSvg('user') ?>
        <?= $this->helper('customer/session')->isLoggedIn()
            ? $this->helper('customer/session')->getCustomer()->getName()
            : $this->__('Account') ?>
    </button>
</div>
```

#### Product Ratings

```php
<!-- Star rating display (using filled variant) -->
<div class="rating">
    <?php for ($i = 1; $i <= 5; $i++): ?>
        <span class="star <?= $i <= $rating ? 'filled' : 'empty' ?>">
            <?= $this->getIconSvg('star', $i <= $rating ? 'filled' : 'outline') ?>
        </span>
    <?php endfor; ?>
</div>
```

## Using Icons as Data URLs

For CSS backgrounds, use `getIconDataUrl()`:

```php
<?= $this->getIconDataUrl('star', 'filled', ['fill' => '#ff0000']) ?>
```

This returns a CSS `url()` value wrapping the raw (non-base64) inline SVG, ready to drop straight into a `background` declaration:

```
url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" ...>...</svg>')
```

!!! warning "Attributes only replace existing ones"
    The optional third argument replaces attributes that already exist on the SVG, it does not add new ones. For example `['fill' => '#ff0000']` only takes effect on an icon that already has a `fill` attribute. Outline icons ship with `fill="none"`, so passing `fill` is most useful with the `filled` variant.

## Finding Available Icons

Browse the complete icon library at **[https://tabler.io/icons](https://tabler.io/icons)**.

The icon name shown on the website corresponds directly to the first parameter in `getIconSvg()`. For example, the icon at `https://tabler.io/icons/shopping-cart` is used as:

```php
<?= $this->getIconSvg('shopping-cart') ?>
```

## Styling Icons

### CSS Styling

Icons use `currentColor` by default, so they inherit the text color:

```css
/* Change icon color */
.icon-red {
    color: #dc3545;
}

/* Change icon size */
.icon-large {
    font-size: 32px;
}

.icon-large svg {
    width: 1em;
    height: 1em;
}

/* Add spacing */
.icon-with-text svg {
    margin-right: 0.5rem;
    vertical-align: middle;
}
```

### Inline Styling

```php
<span style="color: #007bff; font-size: 24px;">
    <?= $this->getIconSvg('shopping-cart') ?>
</span>
```

## Performance & Caching

### How Caching Works

1. **First Request**: Icon is loaded from `vendor/mahocommerce/icons/icons/{variant}/{name}.svg`
2. **Cache Storage**: Icon SVG is stored in Maho's cache with key `MAHO_ICON_{variant}_{name}`
3. **Subsequent Requests**: Icon is served directly from cache

### Cache Management

Icons use the **'icons'** cache type in Maho:

- View/manage: **System → Cache Management**
- Cache tag: `MAHO_ICON`
- Clear icons cache when troubleshooting icon display issues

### Performance Tips

- Icons are extremely lightweight (~1-3 KB each)
- Inline SVG eliminates HTTP requests
- Cached icons have zero file system overhead after first load
- Using the same icon multiple times on a page only loads it once into cache

## Accessibility

### ARIA Roles

By default, icons have `role="none"` for decorative use. For semantic icons, specify an appropriate role:

```php
<!-- Decorative icon (default) -->
<button>
    <?= $this->getIconSvg('search') ?>
    <?= $this->__('Search') ?>
</button>

<!-- Icon-only button needs aria-label on button -->
<button aria-label="<?= $this->__('Search') ?>">
    <?= $this->getIconSvg('search') ?>
</button>
```

## Icons Library vs Custom SVG Uploads

### Use the Icons Library When:

- Building standard UI elements (buttons, navigation, forms)
- Implementing common e-commerce features (cart, wishlist, search)
- Prototyping quickly without custom design assets
- Need consistent, professionally designed icons
- Want automatic caching and performance optimization

### Upload Custom SVG When:

- Creating brand-specific favicons (see [SVG Uploads](../hosting/svg-support.md))
- Designing unique product placeholder images
- Need custom illustrations not in the library
- Have brand guidelines requiring specific icon designs

The icons library is for **in-template usage**, while SVG uploads are for **admin configuration** (favicon, placeholders).
