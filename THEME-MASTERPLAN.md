# Maho Multi-Industry Themes — Master Plan ("next level")

## Diagnosis: why the themes still don't feel "amazing"

Competitive research (WoodMart, Flatsome, Porto, Ella, and Shopify's $400 tier: Impulse,
Prestige, Symmetry) shows premium industry demos are **not** distinct designs:

- Differentiation is roughly **70% content** (photography, copy, offers, category names),
  **20% design tokens** (palette, type, radius — what we built), **10% section
  selection/order** on the homepage.
- Every leader ships **one section library + per-industry content packs**. WoodMart's 100+
  demos come from ~400 shared blocks; Prestige's presets are the same 30 sections with
  different settings and photography.
- The Shopify Theme Store quality floor (a useful codified bar): real copy (no lorem ipsum),
  products on sale + sold out + multi-variant + gift card all represented, prefilled
  reviews, Lighthouse perf ≥60 / accessibility ≥90 on home/collection/product.
- A believable demo needs only **30–100 products** per industry — but every data state must
  exist, and grid photography must sit on one consistent backdrop.
- Killer differentiator available to us: **redistributable demo imagery**. WoodMart legally
  cannot ship its demo photos (license-blocked placeholders on import). AI-generated or
  CC0 imagery that we own outright beats the #1 seller's import experience.

We are currently judging 10 token-skins against the 2012 Magento fashion dataset with beige
CMS banners. No amount of CSS fixes that. The assets we already have:

- `../maho-sample-data`: SQL + media pack model, plus the image pipeline
  (`1-remove-bg.sh → 2-apply-gradient.sh → 3-convert-webp.sh`) that produces the
  transparent cutouts the tile system was designed for.
- `Maho_Ai` core module with `ImageProviderInterface` (pluggable AI image generation),
  feed/import infrastructure, Blog module, widgets.
- The theme system itself (tokens + unlayered theme.css) is the right architecture — it
  matches how the winners do "20% tokens".

---

## North star

> Each industry theme = **tokens** (done) + **content pack** (products, photography,
> categories, reviews, blog, menus) + **art-directed CMS homepage** composed from a shared
> section library + **full-surface polish** + passes a **codified quality bar** and a
> **specialist review gauntlet**.

Ship as: one Maho install, one store view per industry (theme + its content pack), with a
store switcher — simultaneously the demo site, the QA rig, and the marketing asset.

---

## Phase 1 — WYSIWYG-native homepage system (bento + widgets, no custom section CSS)

**Revised approach (Fabrizio's call, verified against the code):** homepages are built
from what the TipTap editor and the widget system already provide — NOT from a parallel
custom-CSS section vocabulary. Store owners must be able to open any demo homepage in the
admin WYSIWYG and edit it visually. The aesthetics come from the CONTENT inside bento
cells (images, copy, widgets), which the theme tokens already style.

**What already exists:**
- TipTap `MahoBentoGrid` + `MahoColumns` with presets: Hero + 2 Cards, Feature Left/Right,
  Hero + 3, Dashboard, Magazine, Showcase, Mosaic; gap presets; container-query mobile
  collapse; cell images auto object-fit cover. This covers hero, category tiles, editorial
  mosaics, banner duos.
- Widgets: `bestsellers` (period filter, grid/list), `onsale` (by catalog price rule,
  order by newest/best-selling/biggest-discount/random), `new_products`,
  `recently_viewed`/`recently_compared`, `newsletter_subscribe`, `cms_static_block`,
  `cms_page_link`, `catalog_product_link`, `catalog_category_link`. This covers every
  product carousel and the newsletter band.
- USP strips / testimonials / brand strips: plain columns + safelisted DaisyUI classes in
  reusable CMS static blocks — no new CSS.

**Task 1.1 — port the bento/columns structural CSS into the compiled theme (required).**
The maho package's compiled styles.css REPLACES base/default's stylesheet, and it
currently contains zero `[data-type="maho-bento"]` rules — bento content renders broken
on all maho themes today. Port the ~90-line structural block (grid base, gap presets,
column presets, cell overflow/img rules, container-query collapse) into components.css,
tokenizing the two hard-coded values (cards-style border #e5e7eb → var(--color-line),
radius → var(--radius-box)). Rebuild + verify.

**Task 1.2 — widget gap-fill (small core features, high leverage):**
1. `catalog products list` widget — curated collection carousel by category and/or SKU
   list, sort options, grid/list templates (the only major homepage widget missing;
   M2-parity feature).
2. `blog recent posts` widget for Maho_Blog (title/image/excerpt, count) — powers the
   blog-teaser section.
3. (Optional) `category tiles` widget — image cards for the children of a chosen
   category, with per-tile label/offer text; can be done manually with bento + category
   link widgets first, automate later.

**Task 1.3 — homepage recipe cookbook.** For each of the 12 canonical sections, document
the WYSIWYG-native recipe (which bento preset / columns preset / widget / DaisyUI classes)
in `public/skin/frontend/maho/SECTIONS.md`, with paste-ready markup. Build one neutral
demo homepage exercising all recipes and verify on all themes.

### Prompt P1 — WYSIWYG-native homepage system
```
Read public/skin/frontend/maho/README.md, public/skin/frontend/maho/default/src/components.css,
public/js/mage/adminhtml/wysiwyg/tiptap/extensions/bento.js (BENTO_PRESETS),
extensions/columns.js (COLUMN_PRESETS), and the bento structural CSS in
public/skin/frontend/base/default/css/styles.css (search "WYSIWYG Content - Grid Layouts").
1. Port that structural block into components.css (token-aware: cards border ->
   var(--color-line), radius -> var(--radius-box); keep the container-query collapse),
   rebuild with npm run build:theme, and verify bento presets render on http://maho.test/
   via a test CMS page, screenshotted across 3 themes, light + dark.
2. Write public/skin/frontend/maho/SECTIONS.md: for each canonical homepage section
   (hero, USP strip, category tiles, product carousel, editorial panel, deal block,
   testimonials, gallery grid, blog teaser, newsletter band, brand strip, banner duo)
   give the WYSIWYG-native recipe: bento/columns preset + widget directives
   ({{widget type="reports/product_widget_bestsellers" ...}} etc.) + safelisted DaisyUI
   classes, as paste-ready markup. No custom CSS classes may be invented.
3. Where a recipe is impossible without a missing widget, note it as a gap; do not hack
   around it with custom CSS.
```

### Prompt P1b — missing widgets (core feature work)
```
Implement two new widgets following the existing patterns (see
app/code/core/Mage/Reports/etc/widget.xml + Block/Product/Widget/* for reference):
1. Mage_Catalog "Products List" widget: parameters = category (chooser), optional
   comma-separated SKU list, sort (newest/price/name/position/random), count, in-stock
   only, grid/list templates reusing the existing product grid partials so theme tokens
   style it automatically.
2. Maho_Blog "Recent Posts" widget: count, category filter, template with image +
   title + date + excerpt, matching blog list markup so themes style it for free.
Register in widget.xml with translated labels, add en_US locale strings, follow SPDX
header rules, composer dump-autoload, and verify each widget by inserting it into a test
CMS page and screenshotting the frontend.
```

---

## Phase 2 — Per-industry content packs (the 70%)

Per industry: **40–60 products** (real names/descriptions/prices; include: on-sale items,
sold-out product AND sold-out variant, configurable color/size with swatches, a gift card),
**5–8 top categories** (2 levels, mirroring the research table: furniture = Living/Bedroom/
Dining/Rugs/Decor/Outdoor; kids = shop-by-age + category; etc.), **3–8 reviews** on hero
products, **3–6 blog posts**, menu tree, homepage offers/copy, lifestyle banner images.

Image strategy (DECISION #1, see below): AI-generated studio cutouts for grids (run
through the existing remove-bg → gradient → webp pipeline) + AI/CC0 lifestyle shots for
heroes and editorial panels. One consistent backdrop per industry grid.

Packaging (DECISION #3): evolve `maho-sample-data` into per-industry packs —
`packs/<industry>/` with CSV/dataflow-importable catalog + media + CMS content, and an
installer (`./maho sample-data:install <industry> [--store <code>]`). Multi-store layout
(DECISION #2): one root category per industry, one store group per industry, theme set
per store view.

### Prompt P2 — content-pack merchandiser (run once per industry)
```
You are a merchandiser + senior copywriter for a high-end {INDUSTRY} store. Read
~/Desktop/maho-themes-masterplan.md (Phase 2) and ../maho-sample-data/CLAUDE.md. Produce a
complete content pack spec as structured files under ../maho-sample-data/packs/{industry}/:
1. categories.csv — 5–8 top-level categories, 2 levels, per the industry pattern table
2. products.csv — 48 products: name, sku, category, price, special_price (~20% on sale),
   short_description (1 sentence), description (2 paragraphs, benefit-led, zero lorem
   ipsum), attributes (color/size/material where apt), qty (2 sold out), 1 gift card,
   6 configurable products with variants (one with a sold-out variant)
3. reviews.csv — 3–8 reviews for the 10 hero products (varied ratings 3–5, human voice)
4. blog.csv — 4 posts (titles, 300-word bodies, industry-credible topics)
5. homepage.md — copy for every section: hero eyebrow/headline/offer/CTA, USP strip
   items, category tile labels + offers, editorial panel copy, newsletter hook
6. images.md — an image manifest: for every product and banner, a detailed generation
   prompt (studio cutout style for products: single product, centered, soft even light,
   plain light-grey seamless background, no props; lifestyle style for banners: specify
   scene, mood, palette matching the theme tokens in
   public/skin/frontend/maho/{industry}/css/theme.css)
Write real brand-quality copy. Product names must sound like a curated store, not SEO spam.
```

### Prompt P2b — image generation + pipeline (per industry, after P2)
```
Using ../maho-sample-data/packs/{industry}/images.md, generate all product images with
{CHOSEN PROVIDER} at 1200x1200, then run the pipeline: 1-remove-bg.sh, 2-apply-gradient.sh,
3-convert-webp.sh. Place results in packs/{industry}/media/catalog/product/. Generate
banner/lifestyle images at 2400x1200 (hero) and 1200x1500 (editorial). Verify every grid
image is a clean transparent cutout: spot-check 10 by rendering on the store's
--product-tile-bg. Reject and regenerate any with baked-in backgrounds, props, or text.
```

---

## Phase 3 — Per-industry homepage art direction

Compose each industry homepage from the section library + content pack, following the
verified per-industry pattern table:

| Industry | Homepage skeleton |
|---|---|
| fashion | full-bleed lifestyle hero → new arrivals → gendered tiles → lookbook/editorial → UGC grid → newsletter |
| electronics | split product hero + promo duo → deals → tabbed carousels → brand strip → dense grid |
| food | appetite hero + offer → USP (delivery/freshness) → 6 category tiles → deals → recipe editorial → subscription pitch |
| books | book-of-the-month hero → genre tiles → new releases + bestsellers → staff picks editorial → review quotes → blog |
| jewelry | macro hero, sparse text → collection tiles → craftsmanship editorial → featured collection → press logos → gift guide |
| beauty | split model+product hero → shop-by-concern tiles → bestsellers with ratings → ingredient editorial → routine bundles → UGC |
| home | room-scene hero + seasonal offer → room tiles with per-tile discounts → new products → room-ideas lookbook → materials story → blog |
| sports | action hero → activity tiles → best sellers → brand strip → community editorial → UGC |
| kids | bright hero → shop-by-age tiles + category tiles → bestsellers → gift finder → safety/trust strip (buyer = parent) → blog |
| garden | greenery hero → indoor/outdoor/pots/tools tiles → seasonal collection → care-guide editorial → bestsellers → trust strip |

### Prompt P3 — homepage composer (per industry)
```
You are the art director for the Maho {INDUSTRY} demo store. Read
public/skin/frontend/maho/SECTIONS.md, the content pack
../maho-sample-data/packs/{industry}/homepage.md, and the pattern skeleton for {INDUSTRY}
in ~/Desktop/maho-themes-masterplan.md (Phase 3). Build the homepage as CMS page content
(and static blocks where reusable) on the {industry} store view, using ONLY WYSIWYG-native
building blocks - bento/columns presets, widgets, safelisted DaisyUI classes per the
SECTIONS.md recipes - and pack imagery/copy. The page must remain fully editable in the
admin WYSIWYG. Then extend
public/skin/frontend/maho/{industry}/css/theme.css with AT MOST 30 lines of
section-specific tuning (spacing density, one signature treatment) — the sections must
already look right from tokens alone. Verify with full-page playwright screenshots (light
+ dark where the theme has it), iterate twice, self-critique against 2 real reference
stores you screenshot for comparison.
```

---

## Phase 4 — Full-surface completion + feature gap track

**Surfaces to audit/style/verify per theme** (screenshot harness to be extended with a
scripted cart flow): mega-menu dropdowns, search results + autocomplete, product page
(gallery, tabs, related/upsell), cart, BOTH checkouts, account dashboard + orders,
wishlist, blog list/post, contact, 404, CMS About/FAQ, transactional email templates.

**Feature gap track (core PRs, benefit all themes)** — from the premium checklist:
second-image hover swap on cards, quick view / quick add, sticky add-to-cart on mobile
product pages, free-shipping progress bar in cart, mega-menu promo tiles. Each is a
separate core feature decision (DECISION #4) — not theme CSS.

**Quality gates per theme** (Shopify floor, raised): Lighthouse performance ≥ 85 and
accessibility ≥ 95 on home/category/product; WCAG AA contrast verified programmatically;
zero lorem ipsum; every data state visible somewhere.

### Prompt P4 — surface auditor (per theme)
```
Extend the screenshot harness to capture: search results, product page full, cart with 2
items (scripted add-to-cart), onestep checkout, account login, blog list, 404, contact.
Run it for maho/{industry}, Read every screenshot, and file a findings list of anything
broken, unstyled, cramped, or off-brand vs the theme's identity . Fix all findings in
public/skin/frontend/maho/{industry}/css/theme.css only, re-run, iterate until clean.
Then run Lighthouse (npx lighthouse) on home/category/product and report scores.
```

---

## Phase 5 — The review gauntlet (deep specialized reviews)

Per theme, a panel of independent agents, each with real reference material:

1. **Industry design director** — first captures screenshots of 2–3 real best-in-class
   stores in that industry (playwright), then scores our theme side by side: first
   impression, typography, color, spacing/whitespace, photography integration, homepage
   narrative. 1–10 each.
2. **Conversion/UX auditor** — Baymard-style heuristics: nav clarity, card info scent,
   price/CTA prominence, mobile ergonomics, checkout friction.
3. **Accessibility auditor** — axe-core run + manual contrast/focus/keyboard sweep.
4. **Brand strategist** — "does this read as a real funded {industry} brand or a
   template?" — the templated-tells checklist.

Pass bar: average ≥ 8.5, no dimension < 7. Findings feed a fixer agent; re-review until
pass. Then a human checkpoint: you flip through the gallery and give thumbs up/down per
theme.

### Prompt P5 — review panel orchestration (per theme; run as a Workflow if desired)
```
Run a 4-agent review panel for the maho/{industry} theme on http://maho.test/ ({store
code}). Agent A (design director for {INDUSTRY} retail): capture reference screenshots of
{REF1} and {REF2} with playwright, then score our home/category/product/cart screenshots
side-by-side on first-impression, typography, color, whitespace, photography, homepage
narrative (1-10 each, with the single most impactful fix per dimension). Agent B
(conversion auditor): Baymard heuristics on the same pages. Agent C (a11y): run
axe-core via playwright + manual contrast/focus sweep. Agent D (brand strategist):
templated-tells audit. Consolidate into a scored report with a ranked fix list.
```

---

## Phase 6 — Packaging & launch

- maho-demo: multi-store demo deployment, store switcher visible, warm-cache, public URL.
- Docs: README rewrite (theme gallery table with screenshots), SECTIONS.md, pack authoring
  guide, "create your own industry pack" tutorial.
- Marketing: per-theme hero screenshots (light+dark), Lighthouse badges, comparison page.
- PR to main repo; sample-data packs PR to maho-sample-data.

---

## Sequencing — pilot first, then batch

Do NOT run 10 industries through this at once. Pilot **3 contrasting industries** end to
end to prove the pipeline and calibrate prompts:

- **electronics** (cutout-photography, dense, dark-header) — stresses data richness
- **beauty** (lifestyle photography, editorial) — stresses imagery quality
- **garden** (mixed, badge/trust content) — stresses content warmth

Pilot order: Phase 1 (once) → P2+P2b+P3 for the 3 pilots → P4 → P5 → your checkpoint →
then batch the remaining 7 with the calibrated prompts.

## Decisions needed (in order of urgency)

1. **Image strategy**: (a) AI-generated & owned (which provider — via Maho_Ai platforms or
   external CLI?), (b) CC0 curated (Unsplash/Pexels/Burst), (c) hybrid: AI product cutouts
   + CC0 lifestyle. Recommendation: **(c) hybrid**, because owned product imagery is the
   WoodMart-beating differentiator and CC0 lifestyle is fast.
2. **Demo topology**: one install with 10 store views + switcher (recommended) vs separate
   demo DBs per industry.
3. **Pack format**: SQL dumps (current model) vs CSV + `./maho sample-data:install
   <industry>` installer (recommended — re-runnable, reviewable, diffable).
4. **Feature track scope**: which of {second-image swap, quick view, sticky ATC,
   free-shipping bar, mega-menu promo tiles} to green-light as core features. The two
   Phase-1 widgets (catalog products list, blog recent posts) are assumed green-lit -
   they are prerequisites for WYSIWYG-native homepages.
5. **Pilot trio confirmation** and the review-panel pass bar.
6. **Execution mode**: sequential agents per phase vs Workflow orchestration for the
   fan-out phases (P2 across industries, P5 panels).
