---
title: SEO
description: Configure Maho's built-in SEO tools - page metadata, canonical tags, clean URLs, layered-navigation crawl controls, XML sitemaps and multi-store hreflang.
---

# Search Engine Optimization

Maho includes a broad set of built-in SEO features: page metadata, canonical tags, clean URLs,
automatic crawl controls for layered navigation, XML sitemaps, and multi-store hreflang. This guide
walks through all of them and points you at the admin location for each.

Most settings live in three places under **System > Configuration**:

| Area | Location | What it controls |
|---|---|---|
| **Catalog SEO** | Catalog > Search Engine Optimizations | URLs, canonical tags, layered-navigation crawl controls, HTML sitemap |
| **HTML Head** | General > Design > HTML Head | Global default title, description, keywords, and robots |
| **XML Sitemap** | Catalog > Google Sitemap | Automated XML sitemap generation |

For file-level crawler directives (blocking whole paths, crawl delay, sitemap location), see the
dedicated [robots.txt](../hosting/robotstxt.md) page.

## Page metadata

### Global defaults

Under **Design > HTML Head** you set the store-wide fallbacks used when a page does not provide its
own values:

- **Default Title**, **Title Prefix**, **Title Suffix** - the title shell wrapped around each page title.
- **Default Description** and **Default Keywords** - used when a page has none of its own.
- **Default Robots** - the store-wide robots directive (for example `INDEX,FOLLOW`) applied when no
  more specific rule exists.

The **Page Title Separator** (under Catalog > Search Engine Optimizations) is the character used to
join the title parts, `-` by default.

### Per-page metadata

Products, categories, and CMS pages each have their own **meta title**, **meta description**,
**meta keywords**, and **meta robots** fields, set on the SEO section of their admin edit screens.
The per-entity `meta_robots` field accepts directives such as `INDEX,FOLLOW` or `NOINDEX,FOLLOW`
and lets you override indexing on a single product, category, or page.

### How the robots directive is resolved

For any frontend page, Maho resolves the meta robots tag through a fallback chain, using the first
value it finds:

1. The current **product's** meta robots (on a product page)
2. The current **category's** meta robots (on a category page)
3. The current **CMS page's** meta robots (on a CMS page)
4. The store's **Default Robots** setting

The layered-navigation controls below sit on top of this chain for category pages.

## Canonical tags

A canonical tag tells search engines which URL is the "official" version of a page, consolidating
ranking signals onto it.

| Setting | Default | Effect |
|---|---|---|
| **Use Canonical Link Meta Tag For Categories** | Enabled | Category pages advertise their clean URL (no filter or page parameters) as the canonical version. |
| **Use Canonical Link Meta Tag For Products** | Disabled | Product pages advertise a canonical URL that ignores category context. Off by default, since the right product canonical strategy depends on how you use category paths in URLs. |

!!! note "Changed default in v26.7"
    The category canonical tag now defaults to **enabled** (it was previously off). Fresh installs
    get clean category canonicals out of the box. If you are upgrading and deliberately kept it off,
    review this setting after the update.

### CMS pages <span class="version-badge">v27.1+</span>

CMS pages always get a canonical tag, with no setting. The tag holds the URL of the page without
the query string. Search engines then give a link with tracking parameters, such as
`?utm_source=`, to the URL of the page. The tag follows the **Trailing Slash Behavior** setting
(System > Configuration > General > Web > Url Options). The CMS Home Page points to the base URL
of the store, because its own identifier redirects there.

The **CMS No Route Page** gets no canonical tag, and neither does a page whose meta robots
contains `noindex`.

### Store views on one domain <span class="version-badge">v27.1+</span>

A store view can share the base URL of the default store view of its website, or of its store
group. Its pages then show at the same path with `?___store=<code>`. The canonical tags of its CMS
pages keep that parameter, so that they do not point to the default store view. The canonical tags
of its categories and products keep it too, when the settings above turn them on.

The default robots.txt rules block `?___store=`, so search engines do not fetch these pages until
the store view gets its own base URL or you remove that rule. See [robots.txt](../hosting/robotstxt.md).

## Layered navigation crawl controls <span class="version-badge">v26.7+</span>

Category pages with layered navigation are a classic SEO trap. A single category can generate a
near-infinite number of URLs as visitors combine filters (color, size, price, brand) and walk
through pagination, wasting crawl budget and creating duplicate content that competes against your
real category page. Maho manages this automatically with three options, all **enabled by default**.

### Add NOINDEX,FOLLOW to Filtered Layered Navigation Pages

When a visitor applies one or more layered-navigation filters, the resulting page is marked
`NOINDEX,FOLLOW`. Search engines are told not to index the filtered view (avoiding duplicate
content), but they still **follow** the links on it, so your products keep getting discovered.

### Add NOINDEX,FOLLOW to Paginated Category Pages

Pages beyond the first (`?p=2` and onward) are marked `NOINDEX,FOLLOW`. The first page of a
category stays indexable as the canonical entry point, while thin "page 2, 3, 4..." views are kept
out of the index without blocking crawlers from walking through them.

### Add rel="nofollow" to Layered Navigation Filter Links

Filter links in the layered-navigation block, including the color swatches, carry
`rel="nofollow"`, signaling to crawlers that filter-combination URLs are not worth prioritizing.
This reduces wasted crawl budget at the source, before a crawler even requests the filtered URL.

### Maximum Selected Filters <span class="version-badge">v26.11+</span>

The three options above are requests to crawlers. Some bots ignore them and request every filter
combination. Maho stops these requests on the server, before it loads the products:

- A request that selects more filter options than this setting gets a 404 page. Each selected
  option counts, so two colors and one length are three options. The default is 7. Set 0 to remove
  the limit.
- A filter value that no filter link contains gets a 404 page, for example an option ID that does
  not exist.

When a visitor reaches the limit, the filter options are hidden and only the active filters show.
A visitor removes a filter to choose another one. The setting is in
**Catalog > Catalog > Layered Navigation**.

### How Maho decides

The robots directive for a category view follows a clear order of precedence:

1. **An explicit category `noindex` always wins.** If you set the category's meta robots field to a
   value containing `noindex`, that directive is respected everywhere and the canonical tag is
   suppressed for that page. The admin's intent is never weakened.
2. **Filtered and paginated views are noindexed as duplicates**, even when the category itself is
   explicitly indexable, as long as the matching option above is enabled.
3. **Otherwise the page is indexable** and renders the category's own robots value plus its
   canonical tag.

!!! info "noindex and canonical are mutually exclusive"
    Maho never emits both a `noindex` directive and a cross-URL canonical on the same page. The two
    are contradictory (telling crawlers to drop this page while pointing them at a different
    canonical can deindex the canonical target), so a noindexed view never advertises a canonical.

Filter detection reads directly from the request, so it does not depend on render order, and
pagination detection uses the catalog toolbar's configured page parameter, so it keeps working even
if you customize the toolbar.

## URL structure

Clean, stable URLs are a core part of SEO. [URL Rewrites](url-rewrites.md) explains how Maho builds
product and category URLs, the URL suffixes, the category path in product URLs, the redirects when a
URL key changes, and custom rewrites.

Maho also normalizes the request URL itself. A request for `/index.php`, or for a page served
through `/index.php/...`, answers with a **301 redirect** to the same page without that segment.
The storefront therefore keeps one canonical entry point on every web server, and you need no
rewrite rule of your own. See [Web Server Configuration](../hosting/web-server.md).

## Breadcrumbs on product pages <span class="version-badge">v27.1+</span>

The breadcrumbs of a product page show one category of the product. The structured data of the
page uses the same breadcrumbs. Maho picks the category in this order:

1. The category in the URL, when the store uses the category path in product URLs.
2. The category that the visitor opened last, when the product is in it.
3. The primary category of the product.

Search engines do not keep the last category, so they always get the primary category and the same
breadcrumbs.

When the product has no **Meta Title**, the page title shows the names of the categories from the
URL or from the last category. The primary category does not change the page title, the meta
robots, the meta keywords or the URL of the product. It is the current category of the page for
everything else: the navigation menu highlights it, the page gets its `category-*` body classes,
Google Analytics and the Meta pixel get its name, and its custom design applies when
**Apply To Products** is **Yes**.

Maho selects the primary category from the categories of the product in the category tree of the
store:

1. Maho skips a category when it, or a category above it, is not active.
2. A category that the navigation menu shows wins over a category that the menu does not show.
   The menu shows a category when **Include in Navigation Menu** is **Yes** for it and for each
   category above it.
3. Then the deepest category wins. For example, **Men > Shirts > Casual** wins over **Men**.
4. If two categories are at the same depth, the category with the lower ID wins.

To keep a sale or a promotion category out of the primary category, set
**Include in Navigation Menu** to **No** on it, or on a category above it. This works for each
product that is also in a category of the menu. A visitor who opens the product from the sale
category still gets it in the breadcrumbs, because the category of the URL and the last category
come first.

## Multi-store hreflang

When a website has more than one active store view, Maho automatically emits
`<link rel="alternate" hreflang="...">` tags pointing to the equivalent URL in each other store
view, based on each store's locale. This tells search engines which language/region version to
serve and prevents the variants from being treated as duplicate content. No configuration is
required: the tags appear automatically once a second active store view exists and the matching URL
rewrite is present.

## XML sitemap

The **Google Sitemap** module (Catalog > Google Sitemap) generates standards-compliant XML
sitemaps covering your **categories**, **products**, and **CMS pages**. Key capabilities:

- Set the change frequency, priority, and whether to show `lastmod` separately for categories,
  products, and CMS pages. The `lastmod` date comes from the content record. URLs without a valid
  recorded date omit the tag, including the blog index.
- **Image extension**: product and category images can be included as `<image:image>` entries to
  help image search.
- **Automatic file splitting**: when a sitemap exceeds the configured maximum URLs per file
  (50,000 by default), Maho splits it into multiple files and generates a sitemap index.
- **Scheduled generation**: a cron job regenerates the sitemap on a configurable frequency and can
  email a recipient if generation fails.

Once generated, reference the sitemap file from your [robots.txt](../hosting/robotstxt.md) so crawlers can
find it.

## HTML sitemap and search terms

Two additional options in the Catalog > Search Engine Optimizations group:

- **Autogenerated Site Map** - a human-friendly HTML sitemap listing categories and products,
  served under `/catalog/seo_sitemap/category` and `/catalog/seo_sitemap/product`.
- **Popular Search Terms** - exposes popular on-site search terms as crawlable links.

## Structured data, AI crawlers and llms.txt

Two related features have their own guides:

- **[Structured Data](structured-data.md)** - the schema.org JSON-LD Maho emits on product,
  category, blog, and CMS pages, which powers rich results and Google Merchant listings. As of
  v26.9 it covers Google's full core product set (shipping details, return policy, item
  condition, variant markup).
- **[AI Crawlers & llms.txt](ai-and-crawlers.md)** - the generated `llms.txt` /
  `llms-full.txt` files and the robots.txt content signals that state your store's policy on
  AI search and AI training (v26.9+).

## Pages Maho keeps out of the index

Some pages should never be indexed, and Maho marks them `noindex, nofollow` out of the box:

- The entire **admin panel**, including the login, forgot-password, and reset-password screens.
- The **maintenance mode** page shown while the store is offline.

You typically reinforce this in [robots.txt](../hosting/robotstxt.md) by disallowing checkout, customer
account, cart, and search paths.

## Recommended setup

For most stores the defaults are the right starting point:

- Category canonical tag: **enabled**
- NOINDEX filtered pages: **enabled**
- NOINDEX paginated pages: **enabled**
- NOFOLLOW filter links: **enabled**
- A populated **Default Robots**, **Default Title**, and **Default Description** under HTML Head
- XML sitemap generation **enabled** and referenced from `robots.txt`

The catalog SEO settings are configurable per **store view**, so multi-store and multi-language
setups can tune crawl behavior independently. Pair them with a tailored [robots.txt](../hosting/robotstxt.md)
for complete control over how search engines crawl and index your catalog.
