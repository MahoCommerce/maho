---
description: Learn how Maho generates robots.txt, which rules it writes by default, and how to keep crawlers away from private pages and layered navigation filters.
---

# Robots.txt

`robots.txt` is a text file that tells web robots (search engine crawlers, AI crawlers) which pages
of a site they may crawl. The file uses the
[Robots Exclusion Protocol](https://www.rfc-editor.org/rfc/rfc9309){:target="_blank"} (RFC 9309).

!!! warning "robots.txt is a request, not a block"
    Search engines and most AI crawlers obey `robots.txt`. Scrapers and some bots ignore it. To
    protect the store from bots that ignore it, Maho also limits the layered navigation filters on
    the server. See [Layered navigation filters](#layered-navigation-filters) below.

## Why robots.txt is important for e-commerce

1. **Crawl efficiency**: crawlers spend their time on the pages that matter, not on filter and sort
   variants of the same category.
2. **Private pages**: crawlers stay out of the customer account, the checkout and the cart.
3. **Server load**: each URL that a crawler skips is a page that the store does not render.

## The generated robots.txt <span class="version-badge">v26.9+</span>

Maho serves a generated `/robots.txt`. Configure it for each store view in
**System > Configuration > Catalog > Crawlers & robots.txt**. The settings are:

- **Base Rules**: the rules for all crawlers, one per line. The default rules are listed below.
- **Blocked AI Crawlers**: each selected crawler gets its own `Disallow: /` group.
- **Custom Instructions**: more groups, added at the end of the file.
- **Content signals**: what AI systems may do with your pages. See
  [AI Crawlers & llms.txt](../user-guide/ai-and-crawlers.md).

When **Add Sitemap Lines** is on, Maho adds a `Sitemap:` line for each sitemap of the store.

### Default base rules

```ini
## Allow the first parameter for paging
Allow: /*?p=

## Do not crawl non-SEF paths and generated content
Disallow: */index.php/
Disallow: */catalog/product_compare/
Disallow: */catalog/category/view/
Disallow: */catalog/product/view/
Disallow: */catalog/product/gallery/
Disallow: */catalogsearch/
Disallow: */checkout/
Disallow: */customer/
Disallow: */newsletter/
Disallow: */review/
Disallow: */sales/
Disallow: */tag/
Disallow: */wishlist/

## Do not crawl sort, list mode, page size, price, search and store switch parameters
Disallow: /*?dir*
Disallow: /*?limit*
Disallow: /*?mode*
Disallow: /*?price=*
Disallow: /*?q=*
Disallow: /*?___from_store=*
Disallow: /*?___store=*

## Do not crawl paging with more parameters
Disallow: /*?p=*&

## Do not crawl PHP files
Disallow: /*.php$
```

The rules that start with `*/` also match a store code at the start of the path, for example
`/en/checkout/`. Google and Bing accept this form.

When two rules match one URL, Google uses the longest rule. So `Disallow: /*?p=*&` wins over
`Allow: /*?p=`, and a paging URL with more parameters stays blocked.

### How the rules handle filter URLs

The default rules do not block attribute filters, because the attribute codes are different for
each store. Crawlers that obey `robots.txt` can read `/wigs.html?color=12` and
`/wigs.html?color=12&length=5`. A filtered page gets `NOINDEX,FOLLOW`, and filter links get
`rel="nofollow"`, so search engines do not add these pages to the index. See
[SEO](../user-guide/seo.md).

To keep crawlers away from filter combinations, add rules to **Base Rules**:

- Block one filter in any position, for example the `color` attribute:

    ```ini
    Disallow: /*?color=
    Disallow: /*&color=
    ```

- Block every URL with two or more parameters:

    ```ini
    Disallow: /*?*&
    ```

!!! warning "Disallow: /*?*& also blocks tracking parameters"
    The rule blocks all URLs with two parameters, also a product page with
    `?utm_source=google&utm_medium=cpc`. Google's shopping crawler obeys it, so Merchant Center can
    reject products whose feed links have two tracking parameters. Before you add the rule, check
    the links in your feeds and ads. To keep links that start with a tracking parameter, also add
    `Allow: /*?utm_`.

## Layered navigation filters

Each filter value that a visitor adds makes a new URL. A category with many filters has millions of
combinations. A bot that ignores `robots.txt` can request all of them, and each request uses the
CPU and the database of the store.

Maho refuses these requests before it loads the products:

- A filter value that no filter link contains gets a **404** page. Examples: an option ID that does
  not exist, a category that is not below the current category, a price range that is not a number,
  and the same value two times.
- A request that selects more filter options than **Maximum Selected Filters** gets a **404** page.

Set the limit in **System > Configuration > Catalog > Catalog > Layered Navigation**. The default
is 7. Each selected option counts, so two colors and one length are three options. When a visitor
reaches the limit, the filter options are hidden, and only the active filters show. Thus a visitor
never sees the 404 page. Set 0 to remove the limit.

## Use your own robots.txt file

If you put a `robots.txt` file in the `public/` directory, the web server sends that file, and
Maho does not generate one. Use this only when the settings above cannot express your rules.

To start, copy the default base rules above into the file, and add a `User-agent: *` line before
them. Then add your sitemap URL:

```ini
Sitemap: https://example.com/sitemap.xml
```

!!! note "Crawl-delay"
    Some sample files contain `Crawl-delay: 10`. Bing and Yandex obey it. Google ignores it.

Before you publish a file, test it with the robots.txt report in
[Google Search Console](https://support.google.com/webmasters/answer/6062598){:target="_blank"}.
For more information, read the
[Google Search Central documentation on robots.txt](https://developers.google.com/search/docs/crawling-indexing/robots/intro){:target="_blank"}.
