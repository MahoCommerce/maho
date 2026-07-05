# Maho Theme System (Tailwind CSS 4 + DaisyUI 5)

The `maho` design package is a modern storefront theme built on Tailwind CSS 4
and DaisyUI 5, with industry variants that are **plain CSS** — store owners
never need Node.js.

## Architecture

```
app/design/frontend/maho/
├── default/                      Base theme (parent: base/default) - shares ALL templates
├── fashion/                      Industry variants (parent: maho/default)
├── electronics/                  Each is just an etc/theme.xml
├── food/
├── books/
├── jewelry/
├── beauty/
├── home/
├── sports/
├── kids/
└── garden/

public/skin/frontend/maho/
├── default/
│   ├── src/
│   │   ├── tailwind.css          Build entry (Tailwind + DaisyUI + default "maho" theme)
│   │   ├── components.css        Semantic layer: Maho's class contracts mapped via @apply
│   │   ├── blog.css              Page-specific sources (@reference the shared theme;
│   │   ├── onestep-checkout.css   compiled separately, loaded only on their pages,
│   │   └── checkout.css           shadowing the legacy base/default stylesheets)
│   ├── css/
│   │   ├── styles.css            COMPILED global theme (committed, ~400KB min / ~41KB gz)
│   │   ├── blog.css              COMPILED page bundles (committed)
│   │   ├── onestep-checkout.css
│   │   ├── checkout.css
│   │   └── theme.css             Default identity (customization entry point)
├── fashion/css/theme.css         Industry identities: plain CSS variable overrides
├── electronics/css/theme.css     + Google Fonts + optional dark mode
├── food/css/theme.css
├── books/css/theme.css
├── jewelry/css/theme.css
├── beauty/css/theme.css
├── home/css/theme.css
├── sports/css/theme.css
├── kids/css/theme.css
└── garden/css/theme.css
```

No `.phtml` templates are forked: the compiled CSS styles the semantic class
contracts of `base/default` templates (via `@apply` onto Tailwind utilities and
DaisyUI components), so every template and JS behavior keeps working and
template fixes in `base/default` propagate to all themes automatically.

Every page loads two stylesheets: `css/styles.css` (the compiled engine, shared
by all themes through the skin fallback) and `css/theme.css` (the identity of
the active theme). Both resolve per-theme, so a custom theme can override
either one — that gives you the two customization paths below.

This works the same when Maho is installed as a Composer dependency: the
`maho-composer-plugin` copies this whole folder into the project's `public/`
on `composer install`/`update`, and the design fallback finds the parent
themes' templates inside `vendor/mahocommerce/maho`. Never edit the copied
`maho/default` files in a child project (they are overwritten on update) —
create your own theme instead.

## Picking a theme (store owners)

1. **System > Configuration > Design**: package `maho`, theme `fashion` /
   `electronics` / `food` / `books` / `jewelry` / `beauty` / `home` /
   `sports` / `kids` / `garden` (empty = default).
2. `./maho cache:flush`

## Creating your own theme

Both paths start the same way (example theme name: `pharmacy`):

1. Declare the theme — `app/design/frontend/maho/pharmacy/etc/theme.xml`:

   ```xml
   <theme>
       <parent>maho/default</parent>
       <title>Maho - Pharmacy</title>
   </theme>
   ```

2. Set package `maho` / theme `pharmacy` in admin and flush the cache after
   each change below.

### Option A — pure CSS, no build tools

Create `public/skin/frontend/maho/pharmacy/css/theme.css`. The skin fallback
serves your `theme.css` on top of the stock compiled `styles.css`.

Restyle the whole store by overriding the design tokens — every component
(buttons, badges, cards, forms, nav) derives from them:

```css
@import url('https://fonts.googleapis.com/css2?family=Figtree:wght@400;600;700&display=swap');

:root {
    --font-display: 'Figtree', sans-serif;   /* headings */
    --font-body: 'Figtree', sans-serif;

    /* Colors accept any CSS format (hex, oklch, ...) */
    --color-primary: #0e7a5f;
    --color-primary-content: #f0fdf8;
    --color-base-100: #ffffff;                /* page background */
    --color-base-200: #f3f6f4;                /* quiet surfaces */
    --color-base-300: #dfe7e2;                /* borders */
    --color-base-content: #17221d;            /* text ink */

    /* Every standard border and hairline uses this one variable
       (defaults to base-300) */
    --maho-color-border: #dfe7e2;

    /* Shape */
    --radius-selector: 0.5rem;                /* swatches, badges */
    --radius-field: 0.5rem;                   /* inputs, buttons */
    --radius-box: 1rem;                       /* cards, dialogs */

    /* Backdrop behind product images (grid/list/gallery/cart tiles).
       Defaults to a subtle studio-light gradient derived from the base
       colors; product photos with transparent backgrounds look best on it.
       Accepts any background value: */
    --product-tile-bg: #f6f4f1;
}

/* Optional dark mode */
@media (prefers-color-scheme: dark) {
    :root {
        color-scheme: dark;
        --color-base-100: #131917;
        --color-base-200: #1c2420;
        --color-base-300: #2c3831;
        --color-base-content: #dbe7e1;
    }
}
```

Then add any plain CSS you want below the tokens. The compiled framework lives
in CSS cascade layers, while your `theme.css` is unlayered — **your selectors
always win**, no `!important` or specificity battles needed:

```css
/* Example: pill buttons and uppercase nav, just for this theme */
.btn { border-radius: 9999px; }
#nav a { text-transform: uppercase; letter-spacing: 0.04em; }
```

The ten industry themes (`fashion/`, `electronics/`, `food/`, `books/`,
`jewelry/`, `beauty/`, `home/`, `sports/`, `kids/`, `garden/`) are real-world
examples of this path — copy the closest one and edit.

### Option B — your own Tailwind / DaisyUI build

If you want to write Tailwind utilities and DaisyUI components in templates or
CMS content (beyond the curated safelist in `default/src/tailwind.css`), or to
change the compiled layer itself, give your theme its own build. When your
theme ships its own `css/styles.css`, the skin fallback serves it **instead
of** the default compiled one.

1. Install the toolchain (in the Maho repo it's already in `package.json`;
   in a child project run this once — or skip it, the build command in step 3
   offers to install it for you):

   ```bash
   npm install -D tailwindcss @tailwindcss/cli daisyui
   ```

2. Create `public/skin/frontend/maho/pharmacy/src/tailwind.css`:

   ```css
   @import "tailwindcss";

   /* Reuse Maho's whole semantic layer - you get the entire storefront
      styling for free and only override what you care about */
   @import "../../default/src/components.css";

   /* Scan the core templates + your own for used utility classes */
   @source "../../../../../../app/design/frontend";

   @plugin "daisyui" { themes: false; }

   /* Your design tokens (same variables as Option A, resolved at build time) */
   @plugin "daisyui/theme" {
       name: "pharmacy";
       default: true;
       color-scheme: light;
       --color-primary: #0e7a5f;
       --color-base-100: #ffffff;
       /* ... */
   }

   @theme {
       --breakpoint-nav: 771px;   /* keep: matches bp.medium in app.js */
       --font-body: 'Figtree', sans-serif;
       --font-display: 'Figtree', sans-serif;
   }

   /* Your own component layer, with @apply available */
   @layer components {
       .pharmacy-banner { @apply alert alert-info rounded-box; }
   }
   ```

   In a child project the relative paths still work, because the composer
   plugin copies `maho/default/src/` into your `public/` alongside your theme.

3. Compile:

   ```bash
   ./maho dev:frontend:theme:build
   ```

   It finds every theme with build sources (top-level `src/*.css` files that
   `@import "tailwindcss"` or `@reference` the shared theme) and compiles each
   to `css/`. Use `--theme maho/pharmacy` to build one theme only and
   `--watch` while developing (unminified — run a plain build before
   committing). If the toolchain from step 1 is missing, the command offers
   to install it for you. Commit the compiled `styles.css` so production
   never needs Node.js.

Rule of thumb: **Option A for identity** (colors, fonts, shape, a handful of
signature rules — it's what the industry themes do), **Option B when you write
new markup** that needs arbitrary Tailwind utilities or DaisyUI components.

## Developing the default theme (Maho contributors)

Only needed when editing `default/src/*.css` or upgrading Tailwind/DaisyUI:

```bash
./maho dev:frontend:theme:build            # compiles all bundles into public/skin/frontend/maho/default/css/
./maho dev:frontend:theme:build --watch    # rebuild on change (unminified; run a plain build before committing)
```

The command installs the npm toolchain on first run if needed. The underlying
`npm run build:theme` / `npm run watch:theme` scripts still exist (CI uses
them) and do the same thing.

Commit the compiled CSS — it ships pre-built for everyone else. Page-specific
sources (`src/blog.css`, ...) start with `@reference "./tailwind.css"`, which
makes `@apply` and the theme tokens available without re-emitting the global
CSS into each bundle.

Notes:

- The custom `nav:` breakpoint (771px) matches `bp.medium` in
  `public/skin/frontend/base/default/js/app.js` so CSS and JS switch layout
  together. Change them in tandem or not at all.
- DaisyUI components used in templates or CMS content are tree-shaken by
  template scanning (`@source`); a curated safelist in `src/tailwind.css`
  keeps the common ones (`btn`, `badge`, `alert`, `card`, ...) available for
  CMS/database content, which cannot be scanned at build time.
- Watch out for DaisyUI component names colliding with Maho's semantic classes
  (`.label`, `.footer`, `.loading`, `.tab-content`, `.breadcrumbs` are already
  handled): fix collisions with unlayered rules at the bottom of
  `src/components.css`. Star ratings are the real DaisyUI rating component
  (markup emitted by `Mage_Rating_Helper_Data::getStarsHtml()`), colored via
  `--maho-color-rating`.
- Both checkouts are styled: the one-step checkout lives in
  `src/onestep-checkout.css`, the classic multi-step accordion (`.opc`, used
  when one-step checkout is disabled) in `src/checkout.css`.
