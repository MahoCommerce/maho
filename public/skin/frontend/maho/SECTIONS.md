# Homepage sections cookbook

A recipe for each section a store homepage needs, built only from what the
page editor already provides. Every recipe is paste-ready HTML. Paste it into a
CMS page or a static block, change the links, the images and the ids, and the
active theme styles the result. A store owner can then open the page in the
editor and change every word and picture.

The rules:

1. Use only the building blocks below. Do not invent a CSS class. A class that
   is not in the list does not exist in the compiled stylesheet, so it does
   nothing on the storefront.
2. Let the theme tokens do the styling. A recipe carries no color, no font and
   no radius of its own. The same markup renders as ten different stores on the
   ten industry themes, in light and in dark mode.
3. If a section needs something that no building block provides, record the
   gap at the end of this file. A few lines of CSS in **System > Configuration
   > Design > Theme Settings > Custom CSS** can bridge it in the meantime, as
   the brand strip does.

The demo homepage of the sample data (`maho-sample-data`, CMS page `home`) uses
every recipe on this page. Open it in the editor to see how the blocks nest.

## Building blocks

### Bento grid

A grid with named areas. The editor writes these attributes, so keep all of
them. The `style` attribute repeats the preset so the grid renders before the
editor loads.

```html
<div data-type="maho-bento" data-preset="feature-left" data-gap="medium" data-style="none"
     style="grid-template-areas: 'a b' 'a c'; grid-template-columns: 2fr 1fr; grid-template-rows: 1fr 1fr">
    <div data-type="maho-bento-cell" style="grid-area: a">...</div>
    <div data-type="maho-bento-cell" style="grid-area: b">...</div>
    <div data-type="maho-bento-cell" style="grid-area: c">...</div>
</div>
```

| Preset | Areas | Columns | Rows | Use it for |
|---|---|---|---|---|
| `hero-2` | `'hero hero' 'a b'` | `1fr 1fr` | `2fr 1fr` | one banner over two cards |
| `feature-left` | `'a b' 'a c'` | `2fr 1fr` | `1fr 1fr` | hero with a side column |
| `feature-right` | `'a b' 'c b'` | `1fr 2fr` | `1fr 1fr` | hero with a side column, mirrored |
| `hero-3` | `'hero hero hero' 'a b c'` | `1fr 1fr 1fr` | `2fr 1fr` | one banner over three cards |
| `dashboard` | `'hero hero side' 'a b c'` | `1fr 1fr 1fr` | `2fr 1fr` | banner, side card, three cards |
| `magazine` | `'feat feat side' 'feat feat extra'` | `1fr 1fr 1fr` | `1fr 1fr` | one large picture, two small |
| `showcase` | `'a a b' 'c d d'` | `1fr 1fr 1fr` | `1fr 1fr` | two wide, two narrow |
| `mosaic` | `'a b b' 'a c d'` | `1fr 1fr 1fr` | `1fr 1fr` | one tall, one wide, two small |
| `hero-4` | `'hero hero hero hero' 'a b c d'` | `1fr 1fr 1fr 1fr` | `2fr 1fr` | one banner over four cards |
| `gallery` | `'a a b c' 'd e e c'` | `1fr 1fr 1fr 1fr` | `1fr 1fr` | photo wall |
| `editorial` | `'a b b c' 'a d d c'` | `1fr 1fr 1fr 1fr` | `1fr 1fr` | two tall sides, two wide centers |
| `banner-cards` | `'hero hero hero hero' 'a a b b' 'c d d e'` | `1fr 1fr 1fr 1fr` | `2fr 1fr 1fr` | banner over six cards |

Gap: `none`, `small`, `medium`, `large`. Style: `none` or `cards`. The cards
style draws a hairline border and the theme box radius around each cell.

Tone: `muted`, `primary`, `neutral` or `accent`, on the grid or on one cell,
as `data-tone`. On the grid it paints a full band with padding. On a cell it
paints a colored card. Each tone takes its background and its ink from the
theme palette, so the text stays readable on every theme and in dark mode.
Inside a `primary`, `neutral` or `accent` tone use `btn-neutral` or
`btn-outline`, since `btn-primary` disappears on its own color.

A picture inside a bento cell fills the cell and crops to fit. Center the
subject of the picture, and do not put text in a picture that a cell crops.
Below 32rem of container width every grid collapses to one column, in source
order.

### Columns

Equal cells in one row. Same gap and style values as the bento grid, plus the
`separated` style, which draws a hairline between the columns.

```html
<div data-type="maho-columns" data-preset="3-equal" data-gap="medium" data-style="none">
    <div data-type="maho-column">...</div>
    <div data-type="maho-column">...</div>
    <div data-type="maho-column">...</div>
</div>
```

Presets: `2-equal`, `3-equal`, `4-equal`, `sidebar-left` (1fr 2fr),
`sidebar-right` (2fr 1fr), `wide-center` (1fr 2fr 1fr).

A picture inside a column keeps its own aspect ratio. Use columns, not a bento
grid, when a picture must not be cropped.

### Widgets

A widget is one `{{widget ...}}` directive on its own line. The editor shows
it as a block, and the storefront renders it with the same product cards, star
ratings and blog cards as the catalog pages.

| Widget | Directive type | Notes |
|---|---|---|
| Products list | `catalog/product_widget_list` | a category, a SKU list, or both. `title` renders a section heading |
| New products | `catalog/product_widget_new` | `display_type="new_products"` shows the products marked as new |
| Bestsellers | `reports/product_widget_bestsellers` | needs order statistics, empty on a fresh install |
| On sale | `catalogrule/product_widget_onsale` | needs an active catalog price rule |
| Recently viewed | `reports/product_widget_viewed` | per visitor |
| Blog posts | `blog/widget_posts` | `title`, `posts_count`, optional `category_id` |
| Newsletter form | `newsletter/widget_subscribe` | the only way to put a form on a page |
| Category link | `catalog/category_widget_link` | `id_path="category/4"`, store-aware URL |
| Product link | `catalog/product_widget_link` | `id_path="product/123"` |
| CMS page link | `cms/widget_page_link` | `page_id="3"` |
| Static block | `cms/widget_block` | `block_id="promotional-banner"`, reuses a block on many pages |

Every product widget takes `products_count`, `only_in_stock` and a `template`
value that ends in `_grid.phtml` or `_list.phtml`.

### Links, pictures and text

- A link with a button class renders as a theme button:
  `<a class="btn btn-primary" href="...">Shop women</a>`.
- Write a picture as `<img src="{{media url="wysiwyg/file.webp"}}" alt="...">`.
  The editor keeps it inside a paragraph. A picture that is not linked
  describes the scene in `alt`. A linked picture names the destination in
  `alt` and repeats it in the link `title`: the link takes its accessible
  name from the picture, so "Shop women" reads better than "Woman on a
  staircase".
- Write a store link as `{{store url="women"}}`. It follows the store view.
- Headings `h1` to `h5` and paragraphs accept `style="text-align: center"`.
- A `div` with a class survives a save, so the DaisyUI card below works.
- A `span` survives a save too, with or without a class or style, so the
  DaisyUI badge is written as `<span class="badge badge-primary">New</span>`.
  Spans nest, but two adjacent spans with the same class and style merge into one.

### Classes that exist on the storefront

The compiled stylesheet cannot scan the database, so only these DaisyUI
classes are available in content. Anything else is silently absent.

| Group | Classes |
|---|---|
| Buttons | `btn`, `btn-primary`, `btn-secondary`, `btn-accent`, `btn-neutral`, `btn-ghost`, `btn-outline`, `btn-link`, `btn-soft`, `btn-sm`, `btn-lg`, `btn-xl`, `btn-wide`, `btn-block` |
| Badges | `badge`, `badge-primary`, `badge-secondary`, `badge-accent`, `badge-neutral`, `badge-outline`, `badge-soft`, `badge-success`, `badge-warning`, `badge-error`, `badge-info` |
| Alerts, links | `alert`, `alert-success`, `alert-warning`, `alert-error`, `alert-info`, `divider`, `link`, `link-primary`, `link-hover` |
| Cards, tabs | `card`, `card-body`, `card-title`, `card-actions`, `card-border`, `tabs`, `tab`, `tab-active` |
| Carousel | `carousel`, `carousel-item`, `carousel-center` |
| Stars | `rating`, `rating-half`, `rating-xs`, `rating-sm`, `rating-md`, `rating-lg`, `mask`, `mask-star-2`, `mask-half-1`, `mask-half-2` |

The list lives in `default/src/tailwind.css` under `@source inline(...)`. A
new class goes there, followed by a theme build.

## Recipes

The ids, URLs and file names below come from the sample data. Change them.

### 1. Hero

A bento `feature-left` grid. The large cell holds the campaign picture. The
side column holds a bordered card with the message and a second picture. The
`card-body` wrapper gives the text its padding.

```html
<div data-type="maho-bento" data-preset="feature-left" data-gap="medium" data-style="none" style="grid-template-areas: 'a b' 'a c'; grid-template-columns: 2fr 1fr; grid-template-rows: 1fr 1fr">
    <div data-type="maho-bento-cell" style="grid-area: a">
        <p><a href="{{store url="women"}}" title="Shop the autumn edit for women"><img src="{{media url="wysiwyg/maisonmaho/hero-main.webp"}}" alt="Shop the autumn edit for women"></a></p>
    </div>
    <div data-type="maho-bento-cell" style="grid-area: b" class="card card-border">
        <div class="card-body">
            <p><span class="badge badge-primary">New season</span></p>
            <h1>Dressed for the journey</h1>
            <p>Travel-friendly fabrics, tailored fits, and colors that survive a suitcase. The autumn edit is here.</p>
            <p><a class="btn btn-primary" href="{{store url="women"}}">Shop women</a> <a class="btn btn-ghost" href="{{store url="men"}}">Shop men</a></p>
        </div>
    </div>
    <div data-type="maho-bento-cell" style="grid-area: c">
        <p><a href="{{store url="accessories/eyewear"}}" title="Shop eyewear"><img src="{{media url="wysiwyg/maisonmaho/hero-side.webp"}}" alt="Shop eyewear"></a></p>
    </div>
</div>
```

The `h1` reads through the same `--title-*` variables as a page title, so the
industry theme sets its face, weight and case.

For a full-width rotating banner use the editor's slideshow block instead. Its
pictures carry the message, so it needs no text cell.

### 2. USP strip

Four separated columns. In each one a two-cell table puts the icon beside a
bold line and a plain line: the editor has table tools, the theme draws no
border around a content table, and the cells carry their own vertical
alignment and spacing. The icon is a directive: `{{icon name="truck" size="28"}}` renders one of the
Tabler icons that ship with Maho as inline SVG, in the current text color, so
it follows the theme and the dark mode. Optional parameters: `variant`
(`outline` or `filled`), `class`, and `label` for an icon that carries meaning
(without it the icon is decorative and hidden from screen readers).

```html
<div data-type="maho-columns" data-preset="4-equal" data-gap="medium" data-style="separated">
    <div data-type="maho-column">
        <table><tbody><tr>
            <td style="width: 1%; padding-right: 0.75rem; vertical-align: middle">{{icon name="truck" size="32"}}</td>
            <td style="vertical-align: middle"><strong>Free shipping</strong><br>On every order over $50</td>
        </tr></tbody></table>
    </div>
    <div data-type="maho-column">
        <table><tbody><tr>
            <td style="width: 1%; padding-right: 0.75rem; vertical-align: middle">{{icon name="arrow-back-up" size="32"}}</td>
            <td style="vertical-align: middle"><strong>30-day returns</strong><br>Free returns, no questions asked</td>
        </tr></tbody></table>
    </div>
    <div data-type="maho-column">
        <table><tbody><tr>
            <td style="width: 1%; padding-right: 0.75rem; vertical-align: middle">{{icon name="shield-check" size="32"}}</td>
            <td style="vertical-align: middle"><strong>Secure checkout</strong><br>Cards, PayPal and Apple Pay</td>
        </tr></tbody></table>
    </div>
    <div data-type="maho-column">
        <table><tbody><tr>
            <td style="width: 1%; padding-right: 0.75rem; vertical-align: middle">{{icon name="headset" size="32"}}</td>
            <td style="vertical-align: middle"><strong>Real people</strong><br>Customer care, Monday to Saturday</td>
        </tr></tbody></table>
    </div>
</div>
```

### 3. Category tiles

A heading, then four columns. Each column holds a linked picture and a
category link widget as the label. The widget resolves the store URL.

```html
<h2>Shop by department</h2>
<div data-type="maho-columns" data-preset="4-equal" data-gap="small" data-style="none">
    <div data-type="maho-column">
        <p><a href="{{store url="women"}}" title="Shop women"><img src="{{media url="wysiwyg/maisonmaho/tile-women.webp"}}" alt="Shop women"></a></p>
        <p style="text-align: center">{{widget type="catalog/category_widget_link" id_path="category/4" template="catalog/category/widget/link/link_block.phtml"}}</p>
    </div>
    <div data-type="maho-column">
        <p><a href="{{store url="men"}}" title="Shop men"><img src="{{media url="wysiwyg/maisonmaho/tile-men.webp"}}" alt="Shop men"></a></p>
        <p style="text-align: center">{{widget type="catalog/category_widget_link" id_path="category/5" template="catalog/category/widget/link/link_block.phtml"}}</p>
    </div>
    <div data-type="maho-column">
        <p><a href="{{store url="accessories"}}" title="Shop accessories"><img src="{{media url="wysiwyg/maisonmaho/tile-accessories.webp"}}" alt="Shop accessories"></a></p>
        <p style="text-align: center">{{widget type="catalog/category_widget_link" id_path="category/6" template="catalog/category/widget/link/link_block.phtml"}}</p>
    </div>
    <div data-type="maho-column">
        <p><a href="{{store url="home-decor"}}" title="Shop home and decor"><img src="{{media url="wysiwyg/maisonmaho/tile-home.webp"}}" alt="Shop home and decor"></a></p>
        <p style="text-align: center">{{widget type="catalog/category_widget_link" id_path="category/7" template="catalog/category/widget/link/link_block.phtml"}}</p>
    </div>
</div>
```

Use square pictures. For a tile wall with mixed sizes use a bento `hero-3` or
`hero-4` grid, and put the label in a paragraph under the picture.

### 4. Product carousel

One widget per row of products. The `title` becomes the section heading.
Five products fill one row on the desktop container: the grid places as many
tiles as fit above a minimum width, and a sixth does not fit at 1280 pixels.

```html
{{widget type="catalog/product_widget_list" title="Staff picks" category_id="4" sort="newest" only_in_stock="1" products_count="5" template="catalog/product/widget/list/content/list_grid.phtml"}}
{{widget type="catalog/product_widget_new" display_type="new_products" products_count="5" template="catalog/product/widget/new/content/new_grid.phtml"}}
```

For a hand-picked row, pass `skus="ace002, ace001, mem000"` and no category.
`sort="position"` then keeps the SKU order. For a bestseller row use
`reports/product_widget_bestsellers` with `period="last_30_days"`. It renders
empty until the store has orders and the report statistics are refreshed.

### 5. Editorial panel

Two equal columns, large gap. A picture on one side, an eyebrow badge, a
heading, two paragraphs and an outline button on the other.

```html
<div data-type="maho-columns" data-preset="2-equal" data-gap="large" data-style="none">
    <div data-type="maho-column">
        <p><img src="{{media url="wysiwyg/maisonmaho/editorial.webp"}}" alt="Our atelier"></p>
    </div>
    <div data-type="maho-column">
        <p><span class="badge badge-outline">Our story</span></p>
        <h2>Made to be worn, then worn again</h2>
        <p>We started Maison Maho with one rule: nothing leaves the atelier unless we would pack it ourselves. Every fabric is tested for a week on the road before it earns a place in the collection.</p>
        <p>That is why our linens do not crease, our knits do not pill, and our bags carry more than they look.</p>
        <p><a class="btn btn-outline" href="{{store url="about-maho-demo-store"}}">Read our story</a></p>
    </div>
</div>
```

Swap the two columns to put the picture on the right. Use `sidebar-left` or
`sidebar-right` when the text needs more room than the picture.

### 6. Deal block

A `sidebar-left` layout. The narrow column holds a card with the offer. The
wide column holds a product widget that shows the discounted products with
their sale badges.

```html
<div data-type="maho-columns" data-preset="sidebar-left" data-gap="large" data-style="none">
    <div data-type="maho-column">
        <div class="card card-border">
            <div class="card-body">
                <p><span class="badge badge-error">Up to 20% off</span></p>
                <h2 class="card-title">End of season sale</h2>
                <p>Selected styles from the summer collection, marked down while stock lasts. Prices already include the discount.</p>
                <p><a class="btn btn-primary btn-wide" href="{{store url="sale"}}">Shop the sale</a></p>
            </div>
        </div>
    </div>
    <div data-type="maho-column">
        {{widget type="catalogrule/product_widget_onsale" order="biggest_discount" only_in_stock="1" products_count="3" template="catalogrule/widget/onsale/content/onsale_grid.phtml"}}
    </div>
</div>
```

The on-sale widget lists products that an active catalog price rule discounts.
When the store discounts with special prices instead, use
`catalog/product_widget_list` with the sale category. Both show the old price
and the percentage badge.

### 7. Testimonials

Three columns in the cards style. Each card opens with a star row, then a
quote, then the name. The stars are icon directives: the editor keeps them as
widgets, and the theme colors them with the text. Four filled stars and one
outline star read as 4. Only the first star carries a `label`: it is the one a
screen reader announces, and the other four stay hidden.

```html
<h2>What our customers say</h2>
<div data-type="maho-columns" data-preset="3-equal" data-gap="medium" data-style="cards">
    <div data-type="maho-column">
        <p>{{icon name="star" variant="filled" size="18" label="Rated 5 out of 5"}} {{icon name="star" variant="filled" size="18"}} {{icon name="star" variant="filled" size="18"}} {{icon name="star" variant="filled" size="18"}} {{icon name="star" variant="filled" size="18"}}</p>
        <blockquote><p>The linen shirt went through three countries and two washes in a hostel sink. Still looks new.</p></blockquote>
        <p><strong>Elena R.</strong><br>Verified buyer, Lisbon</p>
    </div>
    <div data-type="maho-column">
        <p>{{icon name="star" variant="filled" size="18" label="Rated 4 out of 5"}} {{icon name="star" variant="filled" size="18"}} {{icon name="star" variant="filled" size="18"}} {{icon name="star" variant="filled" size="18"}} {{icon name="star" size="18"}}</p>
        <blockquote><p>Ordered on Tuesday, wore it to a wedding on Saturday. The fit guide is accurate, which is rare.</p></blockquote>
        <p><strong>Marcus T.</strong><br>Verified buyer, Leeds</p>
    </div>
    <div data-type="maho-column">
        <p>{{icon name="star" variant="filled" size="18" label="Rated 5 out of 5"}} {{icon name="star" variant="filled" size="18"}} {{icon name="star" variant="filled" size="18"}} {{icon name="star" variant="filled" size="18"}} {{icon name="star" variant="filled" size="18"}}</p>
        <blockquote><p>The weekender bag is the best thing I own. Customer care replaced a zip pull for free, two years in.</p></blockquote>
        <p><strong>Priya S.</strong><br>Verified buyer, Toronto</p>
    </div>
</div>
```

To show three stars, turn the fourth star into an outline and update the label.

### 8. Gallery grid

A bento `gallery` grid with a small gap. Five linked pictures, one per cell.
The cells crop the pictures, so use photographs with a centered subject. A
linked picture zooms on hover, like a product tile. A picture without a link
stays still, because the zoom promises a click.

```html
<h2>As seen on the road</h2>
<div data-type="maho-bento" data-preset="gallery" data-gap="small" data-style="none" style="grid-template-areas: 'a a b c' 'd e e c'; grid-template-columns: 1fr 1fr 1fr 1fr; grid-template-rows: 1fr 1fr">
    <div data-type="maho-bento-cell" style="grid-area: a"><p><a href="{{store url="women"}}" title="Shop women"><img src="{{media url="wysiwyg/maisonmaho/gallery-a.webp"}}" alt="Shop women"></a></p></div>
    <div data-type="maho-bento-cell" style="grid-area: b"><p><a href="{{store url="home-decor"}}" title="Shop home and decor"><img src="{{media url="wysiwyg/maisonmaho/gallery-b.webp"}}" alt="Shop home and decor"></a></p></div>
    <div data-type="maho-bento-cell" style="grid-area: c"><p><a href="{{store url="women"}}" title="Shop women"><img src="{{media url="wysiwyg/maisonmaho/gallery-c.webp"}}" alt="Shop women"></a></p></div>
    <div data-type="maho-bento-cell" style="grid-area: d"><p><a href="{{store url="men"}}" title="Shop men"><img src="{{media url="wysiwyg/maisonmaho/gallery-d.webp"}}" alt="Shop men"></a></p></div>
    <div data-type="maho-bento-cell" style="grid-area: e"><p><a href="{{store url="accessories/bags-luggage"}}" title="Shop bags and luggage"><img src="{{media url="wysiwyg/maisonmaho/gallery-e.webp"}}" alt="Shop bags and luggage"></a></p></div>
</div>
```

The `mosaic`, `showcase` and `editorial` presets give other walls with the
same markup shape.

### 9. Blog teaser

```html
{{widget type="blog/widget_posts" title="From the journal" posts_count="3" template="blog/widget/posts.phtml"}}
```

The widget renders nothing on a store view that has no published post.

### 10. Newsletter band

A one-column block with the `muted` tone, a centered heading, one line of copy
and the newsletter form widget. The widget is the only way to put a form on a
page, because the content sanitizer drops hand-written form controls. Pick the
`primary` tone for a louder band.

```html
<div data-type="maho-columns" data-preset="custom" data-gap="medium" data-style="none" data-tone="muted" style="grid-template-columns: 1fr">
    <div data-type="maho-column">
        <h2 style="text-align: center">Get the packing list</h2>
        <p style="text-align: center">One email a month: new arrivals, restocks, and the sale before it goes public.</p>
        {{widget type="newsletter/widget_subscribe" template="newsletter/subscribe.phtml"}}
    </div>
</div>
```

### 11. Brand strip

A marquee: a track that slides left forever, pauses on hover, and stops when
the visitor prefers reduced motion. This is the one recipe that needs CSS. The
theme does not ship it, so paste the block below into **System > Configuration
> Design > Theme Settings > Custom CSS** (the sample data adds it through the
HTML head includes). The track lists every logo twice. The second copy carries an empty `alt`, so a
screen reader hears each brand once, and the loop lands on an identical frame. Each
logo has fixed width and height attributes, so every mark takes the same space.
The sample logos are monochrome SVG wordmarks in a mid grey, which reads on a
light and on a dark theme. Never ship a real brand mark in demo content: it is
a trademark.

For a row that the visitor scrolls by hand, use the DaisyUI carousel instead:
`<div class="carousel carousel-center">` with one `<div class="carousel-item">`
per logo.

```html
<div class="marquee">
    <div class="marquee-track">
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/ashford-linen.svg"}}" alt="Ashford Linen Co." width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/halden-leather.svg"}}" alt="Halden Leather" width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/nordlys-knitwear.svg"}}" alt="Nordlys Knitwear" width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/casa-mira.svg"}}" alt="Casa Mira" width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/ottavia-eyewear.svg"}}" alt="Ottavia Eyewear" width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/bruma-sol.svg"}}" alt="Bruma &amp; Sol" width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/fjordline.svg"}}" alt="Fjordline Outerwear" width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/sable-ash.svg"}}" alt="Sable &amp; Ash" width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/meridian.svg"}}" alt="Meridian Watches" width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/terra-ceramics.svg"}}" alt="Terra Ceramics" width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/ashford-linen.svg"}}" alt="" width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/halden-leather.svg"}}" alt="" width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/nordlys-knitwear.svg"}}" alt="" width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/casa-mira.svg"}}" alt="" width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/ottavia-eyewear.svg"}}" alt="" width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/bruma-sol.svg"}}" alt="" width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/fjordline.svg"}}" alt="" width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/sable-ash.svg"}}" alt="" width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/meridian.svg"}}" alt="" width="200" height="53"></p>
        <p style="padding: 0 1.5rem"><img src="{{media url="wysiwyg/maisonmaho/brands/terra-ceramics.svg"}}" alt="" width="200" height="53"></p>
    </div>
</div>
```

The CSS, tokens only, no colors:

```css
.marquee { margin: 2.5rem 0; overflow: hidden; mask-image: linear-gradient(to right, transparent, black 8%, black 92%, transparent) }
.marquee-track { display: flex; width: max-content; align-items: center; animation: marquee 45s linear infinite }
.marquee-track > * { margin: 0; flex-shrink: 0 }
.marquee:hover .marquee-track { animation-play-state: paused }
@media (prefers-reduced-motion: reduce) { .marquee-track { animation: none } }
@keyframes marquee { to { transform: translateX(-50%) } }
```

### 12. Banner duo

Two equal columns. Each holds a linked banner, a heading, one line of copy
and an outline button. Columns do not crop, so a wide banner with text in it
is safe here.

```html
<div data-type="maho-columns" data-preset="2-equal" data-gap="medium" data-style="none">
    <div data-type="maho-column">
        <p><a href="{{store url="accessories/bags-luggage"}}" title="Shop bags and luggage"><img src="{{media url="wysiwyg/maisonmaho/banner-travel.webp"}}" alt="Shop bags and luggage"></a></p>
        <h3>Travel gear for every occasion</h3>
        <p>Weekenders, totes and passport wallets, built for the overhead bin.</p>
        <p><a class="btn btn-outline" href="{{store url="accessories/bags-luggage"}}">Shop bags</a></p>
    </div>
    <div data-type="maho-column">
        <p><a href="{{store url="home-decor"}}" title="Shop gifts in home and decor"><img src="{{media url="wysiwyg/maisonmaho/banner-gift.webp"}}" alt="Shop gifts in home and decor"></a></p>
        <h3>Gifts that travel well</h3>
        <p>Ceramics, throws and candles, wrapped and ready to send.</p>
        <p><a class="btn btn-outline" href="{{store url="home-decor"}}">Shop gifts</a></p>
    </div>
</div>
```

## Reusable sections

Put a section that repeats on several pages (the USP strip, the newsletter
band, the brand strip) in a static block, and place it with
`{{widget type="cms/widget_block" block_id="usp-strip" template="cms/widget/static_block/default.phtml"}}`.
One edit then updates every page.

## Gaps

Sections that the building blocks cannot express today. Each one is a feature
request for the editor or the theme.

| Gap | Effect | What would close it |
|---|---|---|
| Text over a picture | The hero puts the message beside the picture, not on it. A category tile carries its label under the picture. | A background picture on a bento cell, with a dark overlay, as an editor control |
| Horizontal product carousel | Every product widget renders a grid. The DaisyUI carousel works for hand-placed items (see the brand strip), not for widget output. | A `carousel` template option on the product widgets |
| Bestsellers on a new store | The bestsellers widget needs order statistics. A demo store without orders shows nothing. | Seed orders in the content pack, and refresh the report statistics on install |
| On sale on a new store | The on-sale widget needs an active catalog price rule. | Ship one rule in the content pack, or use the products list widget with the sale category |
| Testimonials from real reviews | The quotes are typed by hand. Product reviews cannot be pulled into a widget. | A reviews widget: latest or featured reviews, with stars and product link |
| Countdown | A deal block cannot show a ticking end time. | A countdown widget with an end date |
