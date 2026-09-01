# Backend theme configuration

Expose the theme token layer in the admin. A merchant restyles the storefront without
filesystem access, without a child theme, and without a build.

**Status: implemented.** The sections below describe what shipped. Where the build differed
from the original design, the reason is recorded inline.

This does not replace child themes. It changes who each path serves. A merchant uses the
admin. A developer writes `theme.css` and commits it.

Companion to `THEME-MASTERPLAN.md`. Fits after Phase 1.

## Decisions

1. **Storage**: normal system config, under `design/tokens/*`. Never invent storage.
2. **Field list**: static `system.xml`, one group under the existing `design` section.
3. **Map**: the path-to-variable map lives in `config.xml` under
   `<global><design><tokens>`, so any module extends it by normal config merging.
4. **No defaults**: an empty field emits nothing, and the theme's own `theme.css` value
   applies. This removes stale values on a theme switch and removes the need for
   per-theme defaults.
5. **Emission**: an inline `<style>` element in `<head>`, after `getCssJsHtml()`.
6. **Two blocks**: a light block and a dark block. A value applies in both modes, unless
   the merchant also sets a dark value.
7. **Derivations**: computed in PHP, from a closed set that core owns.
8. **No token declaration in `theme.xml`**: a theme that invents its own CSS variable
   already ships a package. It adds a small module instead. See "Extending it" below.

## The token set

About 75 variables exist. Expose about 17 controls.

The evidence is the ten shipped identities. What every identity sets is what an identity
needs. What one or two set is a signature detail that belongs in Custom CSS.

### Expose

| Control | Variables it writes | Identities that set it |
|---|---|---|
| Primary, Secondary, Accent | 3 colors, `-content` pairs derived | 10/10 |
| Page background | `--color-base-100`, 200 and 300 derived | 10/10 |
| Text ink | `--color-base-content` | 10/10 |
| Body font, Heading font, Web font stylesheet | `--font-body`, `--font-display` + the link | 10/10 |
| Small, field and box radius | the three `--radius-*` | 10/10 |
| Product tile background | `--product-tile-bg` | 10/10 |
| Footer background and ink | the four `--footer-*` | 9/10 |
| Star color | `--maho-color-rating` | 9/10 |
| Heading weight, Heading tracking | `--title-weight`, `--title-tracking` | 8-10/10 |
| Button case, Button tracking | `--btn-case`, `--btn-tracking` | 4/10 |
| Control size | `--size-field` and `--size-selector` | 0/10 |
| Surface depth | `--depth` | 0/10 |
| Border width | `--border` | 0/10 |
| Custom CSS | anything else | escape hatch |

Control size, surface depth and border width are new. No identity sets them, but all
three work across the whole compiled bundle: `--size-field` drives `--size` on `.btn`,
`.input`, `.select` and `.tab`, `--depth` multiplies inside 129 box-shadow expressions,
and `--border` reaches every rule once the prerequisite below is in place.

### Derive, never expose

Every `-content` pair, `--color-base-200`, `--color-base-300`, the four semantic hues
(info, success, warning, error), and the whole `--maho-color-*` block
(`_components.css:19-42`).

Those already resolve from the palette through `color-mix()`. `--maho-color-border`
proves the point: no identity overrides it, because deriving it from `base-300` is right
every time.

### Never expose

`--noise`, `--radius-field-max` (computed), the
`--text-13/15/17` scale, and `--breakpoint-nav`. The breakpoint pairs with `bp.medium` in
`app.js`. The README already says to change them in tandem or not at all.

## Prerequisite: one line in `_theme.css`

`--border` does not reach the whole bundle today. Hand-applied Tailwind utilities
(`@apply border`, `border-t`, `border-line`) compile to a fixed width, so `--border: 2px`
would give thick buttons, inputs, cards and tabs beside 1px tables, dividers and tiles.

Tailwind 4 reads `--default-border-width` for the bare `border`, `border-t`, `border-b`,
`divide-x` and `divide-y` utilities. Add one line to the `@theme` block:

```css
@theme {
    --default-border-width: var(--border);
}
```

Measured on a real rebuild:

| | before | after |
|---|---|---|
| honor `var(--border)` | 58 | 49 |
| fixed `1px` | 45 | 1 |

The count falls from 58 to 49 because the utilities now emit one shared rule instead of
many. The remaining `1px` is Tailwind's preflight `hr` reset, which is not a theme
concern.

Do this first, in the same commit as step 2. Rebuild and commit the bundles, or the
`theme-build` workflow fails.

## How it works

### Emission

`Mage_Core_Model_Design_Tokens::toCss()` reads the map from `config.xml`, reads each
config value for the current store, skips every empty value, applies the derivations, and
returns one string.

`Mage_Page_Block_Html_Head::getThemeTokensCss()` returns it. `page/html/head.phtml`
renders it directly after `getCssJsHtml()`.

That position is the only one that beats `theme.css`, which loads as a skin item inside
`getCssJsHtml()`.

Inline, not a generated file. The block is about 1 KB, it varies per store view, and it
changes on every config save. A file needs a per-store path plus cache busting for no
benefit.

### The cascade

```css
:root{--color-primary:#0b6d9f}
@media (prefers-color-scheme: dark){:root{--color-primary:#4cb2e2}}
```

Both blocks always render together. A bare `:root` block that renders after `theme.css`
beats the eight `@media (prefers-color-scheme: dark)` blocks in the industry themes,
because a media query adds no specificity. Emitting both blocks means only the variables
a merchant actually touched lose their theme dark tuning.

### Derivation

Two rules run in PHP, not in CSS. `color-mix()` cannot choose an ink by contrast, and
`contrast-color()` support is still uneven.

- `content`: compute the WCAG relative luminance, test both candidate inks, keep the one
  with the higher ratio.
- `surface-steps`: mix `base-200` and `base-300` from the chosen surface toward the ink at
  fixed percentages.

A module names a derivation in `config.xml`. It never writes one.

### No caching

The plan called for a cached CSS string keyed on store, package and theme. It was dropped
after measurement: a full `resolve()` costs 37 microseconds, while the cache needed its own
key and tag and went stale whenever the config changed without a save. A staleness bug for
no measurable gain is a bad trade.

No schema change and no data script. Decision 4 means there is nothing to seed.

### Fonts

`_addConfiguredFonts()` sits beside the existing `_addThemeFonts()` and is additive, not a
replacement: a store that overrides only the body font still needs the face the theme's own
stylesheet names. It reads one URL field and derives the preconnect from its origin, and it
refuses anything that is not `http` or `https`.

### Validation

The values reach a `<style>` block, so treat them as untrusted.

- Colors must match a grammar that allows hex, `rgb()`, `hsl()`, `oklch()`, and named
  colors, and nothing else.
- Every other value must reject `;`, `}`, `<`, and `/*`.
- Custom CSS cannot follow a grammar. Strip `</style` and `<script`. The precedent is
  `design/head/includes`, which carries the same risk behind the same ACL.

Two grammars, both declared once in `config.xml` beside the map, so a module that adds a
token declares its shape in the same place:

- **Security.** Anything that can close the declaration, the rule or the element is refused.
- **Shape.** Each setting declares a `<type>` (`length`, `integer`, `keyword`, `url`,
  `fontstack`) with an optional `<range>` and `<options>`. A radius must carry a unit, a
  weight has a ceiling, and `--depth` is a flag rather than a number.

Both run twice. `Mage_Adminhtml_Model_System_Config_Backend_Design_Token` runs them on save
and names the expected shape in the error. The emitter runs them again on read and silently
drops what fails, which is the guard that matters: `./maho config:set` writes straight to
the table without a backend model, so save-time validation alone can be bypassed.

Custom CSS drops every `<` rather than hunting for tag names. Nothing can leave a style
element without one, and CSS never needs it (`>` is the child combinator and stays). It
renders inside the same element, after both token blocks, so it always wins.

The element carries `id="design-tokens"`, which names it in devtools and lets a test target
it on an install that also uses `design/head/includes`.

### Admin fields

- **Color picker**: none was written. `\Maho\Data\Form\Element\Color` already renders a
  text input paired with `<input type="color">`, synced both ways, and
  `Mage_Adminhtml_Model_System_Config_Backend_Color` already validates on save. The pair is
  wired with `<frontend_type>color</frontend_type>`, exactly as `Mage/Catalog/etc/system.xml`
  does for the image background.
- **Consequence**: color fields take 6-digit hex only, not `oklch()`. That is the right
  trade for a picker. A developer who wants `oklch()` uses `theme.css` or Custom CSS.
- **Contrast**: the emitter derives every `-content` pair, so a merchant cannot break those.
  The two pairs they set themselves (page background with text, footer background with
  footer text) carry a live ratio badge from
  `Block/System/Config/Form/Field/Design/Contrast.php`, wired by a `<contrast_against>` node.
  Its JavaScript matches the PHP formula to two decimals across the AA and large-text
  boundaries.
- **Live preview**: out of scope.

## Tests

Write the tests first. Each one states a rule from this plan.

`tests/Backend/Unit/Core/DesignTokensTest.php`

- An empty field emits no declaration.
- A set field emits exactly one declaration, with the mapped variable name.
- A `-content` pair derives to the ink with the higher contrast ratio, for a light
  surface and for a dark one.
- `surface-steps` derives `base-200` and `base-300` from the chosen surface.
- One control that writes several variables (corner style) emits all of them.
- A value that fails the grammar is rejected, on save and on read.
- A map entry added by another module is picked up.
- A value with no dark counterpart appears in both blocks. A value with one appears
  differently in each.

`tests/Frontend/...`

- The style element renders after `getCssJsHtml()`, so it beats `theme.css`.
- A configured font emits both the preconnect and the stylesheet link.

## Files

| File | Work |
|---|---|
| `maho/default/src/_theme.css` | one line: `--default-border-width` |
| `Mage/Core/Model/Design/Tokens.php` | new: resolution, derivation, CSS build |
| `Mage/Core/etc/config.xml` | new `<global><design><tokens>` map |
| `Mage/Core/etc/system.xml` | new `design/tokens` group, about 17 fields |
| `Mage/Adminhtml/Block/System/Config/Form/Field/Color.php` | new picker |
| `Mage/Adminhtml/Model/System/Config/Source/Design/Font.php` | new font list |
| `Mage/Page/Block/Html/Head.php` | `getThemeTokensCss()`, config-aware `_addThemeFonts()` |
| `page/html/head.phtml` | one line |
| `lib/MahoCLI/Commands/FrontendThemeExport.php` + `maho` | new command |
| `app/locale/en_US/Mage_Core.csv` | new strings |
| `public/skin/frontend/maho/README.md` | new customization path, above Option A |
| `tests/Backend/Unit/Core/DesignTokensTest.php` | new, see Tests |

## What shipped

| Area | Result |
|---|---|
| Fields | 23, in a new `design/tokens` group at sort order 3 |
| Map | `config.xml` under `<global><design><tokens>`, one entry per field |
| Emitter | `Mage_Core_Model_Design_Tokens`, 243 lines, no cache |
| Head | `getThemeTokensCss()` plus one line in `page/html/head.phtml` |
| Tests | 29, all passing (23 backend, 6 frontend) |
| Export | `./maho dev:frontend:theme:export` |

Two mechanisms were added that the plan did not foresee:

- **A `<var>` may list several names.** One value, several variables, comma separated:
  `<var>--size-field,--size-selector</var>`.

  An earlier build had a `<presets>` mechanism instead, so that one "Corner style" select
  could offer `sharp` / `soft` / `rounded` / `pill`. It was removed. Every other select
  stores the raw token value (`1px`, `400`, `uppercase`, `0`), and those two invented a
  vocabulary that appears nowhere in the stylesheet. Three plain radius fields say the same
  thing, keep the export a straight copy, and delete a whole mechanism.
- **Every field holds the raw CSS value.** No source models, no option lists, no catalogue.
  A font field takes a stack, a radius takes `999px`, a weight takes `600`. One extra field
  carries the web font stylesheet URL, and the head block derives the preconnect from it, so
  any font host works instead of a curated list.

## Extending it

A third-party theme adds its own token with a config-only module. No PHP class, no setup
script, no new file format.

`app/etc/modules/Acme_Luxury.xml`

```xml
<config><modules><Acme_Luxury><active>true</active><codePool>local</codePool></Acme_Luxury></modules></config>
```

`app/code/local/Acme/Luxury/etc/config.xml`

```xml
<config>
    <modules><Acme_Luxury><version>1.0.0</version></Acme_Luxury></modules>
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
</config>
```

`app/code/local/Acme/Luxury/etc/system.xml` declares the field at that path, in the
vendor's own section.

Core's emitter reads the merged `<global><design><tokens>` tree, so the new token behaves
exactly like a core one: it is scoped per store view, it is settable with
`./maho config:set`, the export command writes it, and an empty value emits nothing.

The one wart: the field shows in the admin even when the vendor's theme is not active.
That matches how every other extension's configuration behaves, so it needs no special
handling.

## Prerequisites that are not this task

**Dark mode does not ship yet.** The variable plumbing is good work, and the eight
`@media` blocks are real. But three failures remain:

- `widgets.css`, `sociallogin.css` and `paypal.css` fall through to `base/default` and are
  light-only. They carry `background: #fff` and `#f5f5f5`.
- Product images stay white. `canPreserveTransparency()`
  (`Mage_Catalog_Model_Product_Image.php:432`) requires a PNG, WebP, AVIF or GIF
  extension. A JPEG catalog gets `#ffffff` padding on a near-black tile.
- CMS content cannot follow. Inline WYSIWYG styles and uploaded banners ignore tokens.

There is no toggle and no dark screenshot in the harness. Add a dark pass to Phase 4, then
ship step 5 above.
