---
description: Develop Maho themes the upgrade-safe way. Start from the admin theme settings, set design tokens in theme.css, add CSS rules and local.xml, and override templates only when you must.
---

# Theme Development Guide

This guide explains the best way to develop a theme following Maho's philosophy: keep things simple and maintain upgradability. Rather than copying and modifying entire stylesheets or templates, you achieve the look you want with minimal overrides, so your store can receive Maho updates without conflicts.

!!! info "Maho 26.9 and later"
    Since Maho 26.9 the `base` design package is a compiled skin, built on Tailwind CSS 4 and daisyUI 5, with eleven [industry themes](../about/themes.md) on top of it. This guide describes that system. A theme written against the earlier stylesheets keeps working with the `legacy` package, see [Upgrading an older theme](#upgrading-an-older-theme).

## The Upgradability Principle

Maho allows you to override any file from the core: templates (`.phtml`), JavaScript, CSS, images, and layout XML. The fallback system automatically uses your custom file instead of the core version.

!!! info "How it works"
    The [Maho Composer Plugin](composer-plugin.md) does all the heavy lifting, keeping core files in the vendor directory while your project folder contains only your customizations.

**However, every file you override creates a maintenance burden.**

When Maho releases updates with bug fixes, security patches, or new features, your overridden files do not benefit from these improvements. You need to review and merge changes by hand, and the more files you override, the more work each upgrade requires.

**The golden rule: override as little as possible.**

## How the theme system works

Every storefront page loads two stylesheets:

- `css/styles.css` is the compiled engine. It styles every template of `base/default` through the semantic class names the templates already carry, and it is shared by every theme through the skin fallback.
- `css/theme.css` is the identity of the active theme: design tokens (CSS variables) and a handful of rules. The eleven industry themes are each a `theme.css` of about a hundred lines. No template is forked.

The compiled engine lives in CSS cascade layers, while `theme.css` is unlayered. A rule in `theme.css` therefore always wins, with no `!important` and no specificity battles.

On top of both, **System > Configuration > Design > Theme Settings** lets a merchant set the same tokens in the admin, per website or store view, with a live preview and no files at all. An empty field changes nothing: the theme's own value stands.

## Customization Tiers

Approach theme customization in tiers, starting with the least invasive method:

| Tier | Method | Needs | Upgradability impact |
|------|--------|-------|----------------------|
| 0 | Theme Settings in the admin | Nothing | None |
| 1 | Design tokens in `theme.css` | A theme | None |
| 2 | Additional CSS rules in `theme.css` | A theme | None |
| 3 | Layout XML via `local.xml` | A theme | Minimal |
| 4 | Template overrides | A theme | High, avoid when possible |

Most visual customizations are done with tiers 0 to 2 alone.

## Tier 0: Theme Settings in the admin

Open **System > Configuration > Design > Theme Settings**. The group exposes the tokens that define an identity: the primary, secondary and accent colors, page background and text color, star color, footer colors, body and heading fonts with a web font stylesheet, heading weight and letter spacing, button case and letter spacing, three corner radii, control size, border width, raised surfaces, product image background, a dark mode switch, and a Custom CSS field for anything else. A preview shows the storefront as you type, at phone, tablet and desktop width.

Every field holds the CSS value itself, so `Field Radius` takes `999px`, and `Body Font` takes a font stack. The readable text color on each palette color, and the two quiet surfaces behind the page background, are derived by contrast, so there is no field for them.

**Import a daisyUI Theme** takes the CSS block from the daisyUI theme generator and fills the fields.

When the result should live in git, export it:

```bash
./maho dev:frontend:theme:export --theme acmestore/default
```

The command writes the settings as a `theme.css`. Commit the file and clear the fields. See the [theme commands reference](theme-commands.md#devfrontendthemeexport).

## Setting Up Your Theme

The easiest way to create a new theme is with the CLI command:

```bash
./maho dev:frontend:theme:create
```

This interactive command asks for the package name, the theme name, the parent theme, and whether the theme uses Tailwind. Then it generates the complete directory structure with properly configured files. Every option, and the non-interactive mode, is in the [theme commands reference](theme-commands.md#devfrontendthemecreate).

```bash
./maho dev:frontend:theme:create -p acmestore -t default
```

!!! tip "Why use 'default' as your main theme?"
    Within the same package, any theme automatically falls back to `default`. This means if you later create `acmestore/holiday`, it inherits from `acmestore/default` without extra configuration. If you named your main theme something else (like `acmestore/main`), sub-themes would skip it and fall back directly to `base/default` unless you set the parent in `theme.xml`.

### Generated Structure

```
app/design/frontend/
└── acmestore/
    └── default/
        ├── etc/
        │   └── theme.xml
        └── layout/
            └── local.xml

public/skin/frontend/
└── acmestore/
    └── default/
        └── css/
            └── theme.css
```

`theme.css` needs no entry in `local.xml`: every page already loads it after the compiled `styles.css`. The generated file lists the most common tokens as comments.

### Starting from an industry theme

Pass the industry theme as the parent to start from its identity:

```bash
./maho dev:frontend:theme:create -p acmestore -t default --parent base/fashion
```

The generated `theme.css` then starts with an import of the parent's `theme.css`. Keep that line: the skin fallback serves the first `theme.css` it finds, so your file would otherwise hide the whole identity of the parent.

### Activate Your Theme

In the Maho Admin, go to **System > Configuration > Design** and set:

- **Package**: `acmestore`
- **Theme**: `default`

Then flush the cache.

## Tier 1: Design tokens

The compiled engine derives every component from a set of CSS variables. Redefine them in `theme.css` to change the whole store.

**Example `theme.css`:**

```css
@import url('https://fonts.googleapis.com/css2?family=Figtree:wght@400;600;700&display=swap');

:root {
    --font-display: 'Figtree', sans-serif;   /* headings */
    --font-body: 'Figtree', sans-serif;

    /* Colors accept any CSS format (hex, oklch, ...) */
    --color-primary: #0e7a5f;
    --color-primary-content: #f0fdf8;        /* readable text on primary */
    --color-base-100: #ffffff;                /* page background */
    --color-base-200: #f3f6f4;                /* quiet surfaces */
    --color-base-300: #dfe7e2;                /* borders */
    --color-base-content: #17221d;            /* text ink */

    /* Shape */
    --radius-selector: 0.5rem;                /* swatches, badges */
    --radius-field: 0.5rem;                   /* inputs, buttons */
    --radius-box: 1rem;                       /* cards, dialogs */

    /* Backdrop behind product images */
    --product-tile-bg: #f6f4f1;

    /* Hero titles */
    --font-title: 'Fraunces', serif;          /* defaults to --font-display */
    --title-weight: 500;
    --title-tracking: -0.01em;

    /* Buttons */
    --btn-case: uppercase;
    --btn-tracking: 0.06em;

    /* Footer */
    --footer-bg: #10231c;
    --footer-ink: #eef6f2;
    --footer-link-hover: #7fd0b4;
}
```

With these lines, buttons, links, badges, cards, forms, navigation and the footer across the whole store follow your identity.

The complete token list, with the default of each one, is in the [theme README in the Maho repository](https://github.com/MahoCommerce/maho/blob/main/public/skin/frontend/README.md). The [eleven industry themes](https://github.com/MahoCommerce/maho/tree/main/public/skin/frontend/base) are real-world examples of this tier: copy the closest one and edit.

### Dark mode

Dark mode follows the device setting through a media query that redefines the same variables. Write the block in your own `theme.css` and it wins over the compiled one:

```css
@media (prefers-color-scheme: dark) {
    :root:where(:not([data-color-scheme="light"])) {
        color-scheme: dark;
        --color-base-100: #131917;
        --color-base-200: #1c2420;
        --color-base-300: #2c3831;
        --color-base-content: #dbe7e1;
    }
}
```

The `:where()` clause keeps the merchant's **Dark Mode** setting working: when it is off, the storefront sets `data-color-scheme="light"` on the `html` element and the block does not apply. A theme with no dark palette pins `color-scheme: light` inside that block instead, the way the Food and Kids themes do.

## Tier 2: Additional CSS Rules

When tokens are not enough, add your own rules below them in `theme.css`. Your file is unlayered and loads after the engine, so your selectors always win.

```css
/* Pill buttons and uppercase navigation, just for this theme */
.btn { border-radius: 9999px; }
#nav a { text-transform: uppercase; letter-spacing: 0.04em; }

/* The sale sticker as ink on paper, not a red alert */
.price-box .discount-percent {
    background: var(--color-base-content);
    color: var(--color-base-100);
    border: none;
}
```

**Tips:**

- Use browser DevTools to identify the selectors you need to target
- Do not use `!important`. You do not need it
- Group related customizations with comments for maintainability

## Tier 3: Layout XML Changes

Use `local.xml` to add, move, or remove blocks without touching templates. The generated file contains commented examples.

```xml
<?xml version="1.0"?>
<layout version="0.1.0">
    <!-- Remove the newsletter block from every page -->
    <default>
        <reference name="left">
            <remove name="left.newsletter"/>
        </reference>
    </default>

    <!-- Add a custom block to the homepage -->
    <cms_index_index>
        <reference name="content">
            <block type="core/template" name="custom.block" template="custom/block.phtml"/>
        </reference>
    </cms_index_index>
</layout>
```

Use `./maho dev:frontend:layout:debug <url>` to see the handles, the layout files and the block tree of a page.

## Tier 4: Template Overrides

Copy a template from `base/default` into your theme only when a layout or CSS change cannot do the job. Keep the copy as close to the original as possible, and note the Maho version you copied it from, so you can diff it at the next upgrade.

A template you write yourself can use Tailwind class names. That needs your own build, see the next section.

## Your own Tailwind build

Use this when you write your own templates with Tailwind class names, or `@apply` in your CSS. Scaffold the theme with the build shape:

```bash
./maho dev:frontend:theme:create -p acmestore -t default --tailwind
```

The command writes `src/tailwind.css` instead of `css/theme.css`:

```css
@import "../../../base/default/src/tailwind.css";

:root { --color-primary: #0e7a5f; }

@layer components {
    .acme-banner { @apply alert alert-info rounded-box; }
}
```

That single import carries the whole configuration: the daisyUI plugin, the semantic component layer and every template scan rule, which already covers your own templates. Then compile:

```bash
./maho dev:frontend:theme:build --theme acmestore/default
```

The build writes `css/styles.css`, which the skin fallback serves instead of Maho's bundle. Two things follow:

- The `css/theme.css` slot stays free, so a parent identity such as `base/fashion` still loads on top of your bundle.
- **You own the engine.** Maho's `styles.css` no longer reaches your store, so rebuild after every Maho upgrade. Commit the compiled CSS: production never needs Node.js.

Add `--watch` to rebuild while you work, and run a plain build before you commit. The command installs the toolchain on first run. See the [theme commands reference](theme-commands.md#devfrontendthemebuild).

## Creating Sub-themes

For stores that need theme variations, create sub-themes within your package. Sub-themes inherit from your main theme, so you maintain one base set of customizations and add variations on top.

### Use Cases

- **Seasonal themes**: base theme for everyday use, sub-themes for holidays or promotions
- **A/B testing**: test different designs while sharing core customizations
- **Multi-store setup**: shared identity in the base theme, store-specific branding in sub-themes

### Package Structure

```
app/design/frontend/
└── acmestore/
    ├── default/              # Main store theme
    │   ├── etc/theme.xml
    │   └── layout/local.xml
    ├── holiday/              # Holiday variation (inherits from default)
    │   ├── etc/theme.xml
    │   └── layout/local.xml
    └── premium/              # Premium variation (inherits from default)
        ├── etc/theme.xml
        └── layout/local.xml

public/skin/frontend/
└── acmestore/
    ├── default/css/theme.css   # Main identity
    ├── holiday/css/theme.css   # Holiday colors and decorations
    └── premium/css/theme.css   # Premium branding
```

### Understanding theme.xml

The `dev:frontend:theme:create` command always generates a `theme.xml` that declares the parent theme. Maho can inherit from `default` within the same package on its own, but an explicit `theme.xml` makes the chain clear and is the recommended practice.

Edit `theme.xml` by hand only when a sub-theme inherits from another sub-theme. For example, when `holiday-vip` inherits from `holiday`:

```xml
<?xml version="1.0"?>
<theme>
    <parent>acmestore/holiday</parent>
</theme>
```

### Inheritance Chain

With the `holiday` sub-theme active, Maho looks for files in this order:

1. `acmestore/holiday` (sub-theme)
2. `acmestore/default` (main theme)
3. `base/default` (Maho core)

Core Maho updates flow through automatically, your main theme customizations apply to all sub-themes, and sub-theme changes exist only where needed.

### Sub-theme CSS Strategy

The skin fallback serves one `theme.css`, the first it finds. A sub-theme's `theme.css` therefore replaces the main theme's file instead of adding to it. Import the parent first, then override:

**`public/skin/frontend/acmestore/holiday/css/theme.css`:**

```css
@import url('../../../acmestore/default/css/theme.css');

:root {
    --color-primary: #c62828;
    --color-primary-content: #fff8f8;
    --color-base-200: #fff8e1;
}

/* Seasonal decorations */
.header {
    background-image: url('../images/snowflakes.png');
}
```

The scaffold command writes that import line for you whenever the parent is not `base/default`.

## Image slots <span class="version-badge">v26.11+</span> { #image-slots }

A slot names a place where images show, such as the product listing or the product gallery. Its
value is the largest width in px of the images in that place. A template names the slot, and never
a size:

```php
<img <?= $this->helper('catalog/image')->init($_product, 'image')->getImgAttributes('listing') ?>
     alt="<?= $this->escapeHtml($_product->getName()) ?>">
```

Maho writes `src`, `srcset`, `sizes`, `width` and `height` from the slot and the image ratio, and
the browser picks the file. Your CSS still sets the size of the image box.

Declare the slots in the `<images>` node of `theme.xml`. Each image gets a file at 1x and at 2x of
each slot. When each slot is twice the previous one, the slots share their files. The
`base/default` slots give 5 files for each image: 150, 300, 600, 1200 and 2400.

```xml
<theme>
    <parent>base/default</parent>
    <images>
        <slots>
            <thumbnail>150</thumbnail>
            <listing>300</listing>
            <gallery>600</gallery>
            <banner>1200</banner>
        </slots>
        <product>
            <ratio>1:1</ratio>
            <fit>contain</fit>
        </product>
    </images>
</theme>
```

- A slot that your theme does not declare comes from the parent theme, and at the end from
  `base/default`. The core templates use the four slots above.
- Each image entity (`product`, `category`, `blog`, `swatch`) takes a `ratio`, such as `original`,
  `1:1` or `3:4`, and a `fit`. `contain` fits the image in the box and fills the rest with the
  background color. `cover` fills the box and crops the image.
- An entity can list the slots that its images use, in its own `<slots>` node, so it gets no files
  for the other slots. A slot with a value has that width for this entity only. The list of a child
  theme replaces the list of its parent. `base/default` gives `swatch` one `listing` slot of 36 px.
- Only the theme in the store config of a store view gives these values. A design change, a user
  agent exception or the custom design of a category or a product does not change them.
- A product has one image role, `image`. `small_image` and `thumbnail` are deprecated names of
  it, so do not use them as attribute codes. `init($_product, 'small_image')`, `getSmallImage()`,
  and a product collection that selects, filters or sorts by an old name all give `image`. For a
  second image in one place of the layout, create a product attribute with the input type
  **Media Image**. The gallery shows it as one more role, its images get resized files too, and a
  template calls `init($_product, 'my_code')`.
- Give the first image that a page shows `fetchpriority="high"`, and the images below the fold
  `loading="lazy"`.

After you change the slots, the ratio or the fit, run `./maho storage:image:resize`. The image URLs
follow the new values at once. On a local disk, Maho creates a missing file on its first request.
On a bucket, run the command before the store opens, or the images are missing until the queue has
created them: see [resized images](../hosting/storage.md#resized-images). Then
`./maho storage:image:clean` deletes the files that no store view uses.

## Adding your own setting

A theme that needs a field of its own in Theme Settings ships a small module. No PHP class is needed:

```xml
<!-- app/code/local/Acme/Luxury/etc/config.xml -->
<global>
    <design>
        <tokens>
            <hero_overlay>
                <path>acme_luxury/design/hero_overlay</path>
                <var>--acme-hero-overlay</var>
            </hero_overlay>
        </tokens>
    </design>
</global>
```

Declare the field in your module's `etc/system.xml` at that path. It then behaves like a core setting: scoped per store view, exported by `dev:frontend:theme:export`, and silent while empty. The core `Mage_Core` module's `config.xml` is the worked example.

## Upgrading an older theme

Before 26.9 the `base` package shipped plain stylesheets, and custom themes set variables such as `--maho-color-primary`. Those stylesheets now live in the `legacy` package, which is deprecated and will be removed in a later release. A store that upgrades keeps its look: the upgrade writes package `legacy` when no package was ever chosen.

A custom theme written against the old stylesheets keeps working when it names that package as its parent:

```xml
<theme>
    <parent>legacy/default</parent>
</theme>
```

To move the theme to the compiled skin, set the parent back to `base/default`, rename your stylesheet to `css/theme.css`, and translate its variables to the tokens listed in Tier 1. The industry theme closest to your look is the best template.

## Summary

1. **Start in the admin**: Theme Settings covers most branding, with no files
2. **Set design tokens** in `theme.css` for a theme you keep in git
3. **Add CSS rules** when tokens are not enough
4. **Use `local.xml`** for layout changes
5. **Override templates** only as a last resort, with your own Tailwind build when you write new markup
6. **Consider sub-themes** for seasonal or multi-store variations

Following this approach keeps your themes lean, maintainable, and easy to upgrade.
