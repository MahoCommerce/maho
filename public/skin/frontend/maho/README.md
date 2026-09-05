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
│   ├── src/                      _-prefixed files are partials, the rest are build entries
│   │   ├── tailwind.css          Build entry of the global theme (adds the @source scan rules)
│   │   ├── _theme.css            Shared theme: Tailwind + DaisyUI + default "maho" theme
│   │   ├── _components.css       Semantic layer: Maho's class contracts mapped via @apply
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

The **Skin** field shows each installed theme with its palette, read from the
theme's own stylesheet, so a theme you add yourself appears with no extra step.
The swatches sit inside the real options through the customizable select API
(`appearance: base-select`); a browser without it shows the plain select.

1. **System > Configuration > Design**: package `maho`, theme `fashion` /
   `electronics` / `food` / `books` / `jewelry` / `beauty` / `home` /
   `sports` / `kids` / `garden` (empty = default).
2. `./maho cache:flush`

## Building a homepage (store owners)

A homepage is CMS content: bento grids, columns, widgets and a handful of
DaisyUI classes, all editable in the page editor. The sample data homepage is
the reference: open it in the editor to see how the blocks nest. The DaisyUI
classes available in content are listed in `default/src/tailwind.css` under
`@source inline(...)`; a class outside that list does not exist in the
compiled stylesheet.

## Restyling from the admin (store owners)

**System > Configuration > Design > Theme Settings** restyles the whole store
without a file and without a build:

| Group | Fields |
|---|---|
| Colors | primary, secondary, accent, page background, text, stars, footer |
| Type | body font, heading font, web font stylesheet, heading weight and letter spacing, button case and letter spacing |
| Shape | small / field / box radius, control size, border width, raised surfaces, product image background |
| Escape hatch | Custom CSS |

Three rules explain the whole feature:

- **An empty field changes nothing.** The theme's own value stands. Clear a field
  to go back, and switching themes carries no stale settings.
- **What follows from a color is worked out for you.** The readable text color on
  each palette color, and the two quiet surfaces behind the page background, are
  derived by contrast. That is why there is no field for them. The two pairs you
  do set yourself, page background with text and footer background with footer
  text, show their contrast ratio live as you type.
- **Settings are scoped.** They save per website and per store view, like every
  other setting, so one theme can carry a different palette per store view.
- **A field holds the CSS value itself.** `Field Radius` takes `999px`, not the
  word "pill", and `Body Font` takes a font stack, not a font name from a list.
  The admin never invents a vocabulary the stylesheet does not use, so the
  export below is a straight copy and any value CSS accepts is allowed.

Each field declares the shape it accepts, so a radius must carry a unit and
`Raised Surfaces` takes only 0 or 1. A wrong value is refused on save with a
note saying what the field expects, and it is never rendered even if it reaches
the database another way.

To load a web font, put its stylesheet URL in `Web Font Stylesheet`. Maho adds
the `<link>` and derives the `preconnect` from the URL, which beats an `@import`
because the preload scanner can see it. Any host works, not a fixed list.

The values render as CSS variables in a `<style id="design-tokens">` element that
loads after `theme.css`, so they win over the theme without `!important`.

**Preview** floats at the top right of the group and shows the storefront as you
type, before you save. It needs the admin and the storefront on the same domain;
where they differ, it shows the saved state instead.

**Mobile**, **Tablet** and **Desktop** switch the width the storefront is rendered
at (390, 820 and 1280 pixels), then scale it to fit the panel. That matters
because the theme changes layout at 771 pixels, so a small panel would otherwise
always show the phone design. The choice is remembered in your browser.

It never needs a manual reload: colors, shape and type arrive as CSS variables,
and a web font arrives as a link element, both injected as you type. Clicking a
link inside the preview loads that page and repaints it.

The preview shows the shop as a shopper sees it. A development tool that renders
on the storefront (a theme switcher, a debug bar) hides itself there by carrying
`data-preview-hide` on its root element:

```html
<div class="my-dev-toolbar" data-preview-hide>...</div>
```

The attribute does nothing outside the preview, so the tool stays visible while
you work on the real page.

Saving applies the change at once. No cache flush is needed.

### Starting from a daisyUI theme

**Import a daisyUI Theme** takes the block that daisyui.com/theme-generator
gives you when you press its CSS button. Paste it, press the button, and the
fields fill in.

A daisyUI theme sets 28 variables. Twelve map to a field, five more Maho works
out itself, and the rest (the semantic hues, `--color-neutral`, `--noise`) are
reported as ignored. Colors arrive as `oklch()` and are converted to hex for the
pickers; daisyUI's palettes sit outside sRGB, so the chroma is lowered until the
color fits rather than clipping each channel, which would swing the hue.

### Moving admin settings into a file

`./maho dev:frontend:theme:export --theme maho/pharmacy` writes the current
settings as a real `css/theme.css`. Commit the file, clear the fields, and the
store looks the same. Add `--store <code>` to export one store view, `--stdout`
to review it first, and `--force` to overwrite.

This is the bridge between the two paths below: a merchant tunes the colors in
the admin, and a developer keeps the result in git.

### Adding your own setting

A theme that needs a field of its own ships a small module. No PHP class:

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

Declare the field in your own `etc/system.xml` at that path. It then behaves like
a core setting: scoped per store view, exported by the command above, and silent
while empty. `app/code/core/Mage/Core/etc/config.xml` is the worked example.

## Color palettes

Every theme carries its own palette. The default identity `maho` is defined in
`default/src/_theme.css` as a DaisyUI theme, and each industry theme sets the
same CSS variables in plain CSS (`fashion/css/theme.css`, ...). DaisyUI's stock
themes (`dracula`, `synthwave`, ...) are not compiled in: they are palettes, not
identities, they carry no typography and none of Maho's treatment variables, and
only eight of the 35 pass the WCAG AA bar (4.5:1) that this package holds every
theme to.

Dark mode follows the OS setting through a `@media (prefers-color-scheme: dark)`
block that redefines the same variables at `:root`. The default theme and eight
of the ten industry themes ship one; `food` and `kids` stay light on purpose.
Write the block in your own `theme.css` and it wins over the compiled one,
because it loads later at the same specificity.

To change colors, use the admin settings above, or write the variables you want
in your theme's `css/theme.css` (see Option A below). To pull in a DaisyUI stock
theme, give your theme its own build (Option B) and name it in the
`@plugin "daisyui"` block.

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

    /* Silhouette shadows under the product cutouts (three sizes; set to
       none for flat tiles) */
    --product-cutout-shadow: none;
    --product-cutout-shadow-sm: none;
    --product-cutout-shadow-lg: none;

    /* Hero titles (page titles + the product page title) - all optional */
    --font-title: 'Fraunces', serif;          /* defaults to --font-display */
    --title-weight: 500;                      /* default 600 */
    --title-size: clamp(2rem, 1.5rem + 1.8vw, 2.875rem);
    --title-tracking: -0.01em;
    --title-leading: 1.1;
    --title-style: italic;                    /* default normal */
    --title-case: uppercase;                  /* default none */

    /* Button typography */
    --btn-case: uppercase;                    /* default none */
    --btn-tracking: 0.06em;                   /* default normal */

    /* Prices (grid, product page, cart) take their own face */
    --font-price: 'Fraunces', serif;          /* default inherit */

    /* In-stock label color (out of stock always stays --color-error) */
    --stock-ink: #1f6f4a;                     /* default --color-success */

    /* Layered navigation: filter titles and the one accent on a listing
       page (price apply button + active filter chips) */
    --filter-title-font: 'Fraunces', serif;   /* default inherit */
    --filter-title-size: 1rem;                /* default 0.8125rem */
    --filter-title-style: italic;             /* default normal */
    --filter-title-case: none;                /* default uppercase */
    --filter-title-tracking: 0;               /* default 0.025em */
    --filter-accent: #b8862b;                 /* default --color-primary */
    --filter-accent-content: #fff8e6;         /* default --color-primary-content */

    /* Product page: frame the whole gallery column (image + thumbnails) */
    --gallery-frame-bg: #eae6dc;              /* default transparent */
    --gallery-frame-pad: 1rem;                /* default 0 */

    /* Footer palette (defaults to the quiet base-200 footer). Titles, links
       and hairlines derive their opacities from --footer-ink automatically */
    --footer-bg: #10231c;
    --footer-ink: #eef6f2;
    --footer-link-hover: #7fd0b4;
    --footer-border: #10231c;                 /* top hairline; match the bg to hide it */
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
   @import "../../default/src/_components.css";

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

   It finds every theme with build sources (top-level `src/*.css` files whose
   names do not start with an underscore) and compiles each to `css/`. Use
   `--theme maho/pharmacy` to build one theme only and `--watch` while
   developing (unminified — run a plain build before committing). If the
   toolchain from step 1 is missing, the command offers to install it for you.
   Commit the compiled `styles.css` so production never needs Node.js.

Rule of thumb: **the admin for a store's own look** (colors, fonts, shape, with
no files and per-store-view scoping), **Option A for identity** (the same tokens
plus a handful of signature rules, which is what the industry themes do),
**Option B when you write new markup** that needs arbitrary Tailwind utilities or DaisyUI components.

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
sources (`src/blog.css`, ...) start with `@reference "./_theme.css"`, which
makes `@apply` and the theme tokens available without re-emitting the global
CSS into each bundle. Reference `_theme.css`, never `tailwind.css`: the
`@source` scan rules live in `tailwind.css`, and referencing a file that
carries them rescans every matching path per bundle (40 seconds each) and
throws the result away.

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
