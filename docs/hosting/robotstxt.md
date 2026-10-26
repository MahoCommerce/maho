---
description: Learn why robots.txt matters for e-commerce and how to configure crawler directives in Maho to guide search engines and protect private pages.
---

`robots.txt` is a text file that admins create to instruct web robots (typically search engine crawlers) 
how to crawl pages on their website. The file uses the
[Robots Exclusion Protocol](https://en.wikipedia.org/wiki/Robots.txt){:target="_blank"},
which is a protocol with a small set of commands that can be used to indicate access to your site by section and by 
specific kinds of web crawlers (such as mobile crawlers vs. desktop crawlers).

!!! note "Maho v26.9+ generates robots.txt for you"
    Since v26.9, Maho serves a generated `/robots.txt` out of the box, configurable per store
    view under **System > Configuration > Catalog > Crawlers & robots.txt**, including AI
    crawler blocking and [content signals](../user-guide/ai-and-crawlers.md). A `robots.txt`
    file you place in `public/` (as described below) is served by the web server directly and
    overrides the generated one.

## Why is robots.txt important for e-commerce websites?

For an e-commerce platform, `robots.txt` can be crucial for several reasons:

1. **Crawl efficiency**: It helps search engines crawl your site more efficiently by directing them to the most important pages.
2. **Prevent indexing of private or duplicate content**: You can block crawlers from accessing customer account pages, checkout processes, or other sensitive areas.
3. **SEO optimization**: By guiding crawlers to your most valuable content, you can potentially improve your search engine rankings.

## Sample robots.txt for Maho

Maho doesn't provide a default `robots.txt`, because the needs of every project are too different, and it could be even
dangerous to activate a default configuration. We instead provide a sample file for you to use as a base to create your
own solution.

```ini
############################################
## Crawl the Sitemap. Set the correct URL before uncomment

# Sitemap: https://example.com/sitemap.xml

############################################
## Crawlers Setup

User-agent: *

## How many seconds a crawler should wait before loading and crawling page content
## Set a custom crawl rate if you are experiencing traffic issues with your server
## https://www.conductor.com/academy/robotstxt/faq/crawl-delay-10/

Crawl-delay: 10

############################################
## Allow to crawl paging
## (paging inside a listing with more params are disallowed below)

Allow: /*?p=

############################################
## Do not crawl non-SEF paths and generated content
## (if you use a store id in URL you must prefix with * or copy for each store)

Disallow: */index.php/
Disallow: */catalog/product_compare/
Disallow: */catalog/category/view/
Disallow: */catalog/product/view/
Disallow: */catalog/product/gallery/
Disallow: */catalogsearch/
#Allow: */catalogsearch/seo_sitemap
#Allow: */catalogsearch/term/popular
Disallow: */checkout/
Disallow: */contacts/
Disallow: */customer/
Disallow: */newsletter/
Disallow: */review/
Disallow: */sales/
Disallow: */tag/
Disallow: */wishlist/

############################################
## Do not crawl dynamic filters. Uncomment what you need or add custom filters

Disallow: /*?dir*
Disallow: /*?limit*
Disallow: /*?mode*
Disallow: /*?price=*
Disallow: /*?___from_store=*
Disallow: /*?___store=*
Disallow: /*?q=*
# Disallow: /*?cat=*
# Disallow: /*?availability=*
# Disallow: /*?brand=*

############################################
## Do not crawl paths that can be safely ignored by search engines (no clean URLs)

Disallow: /*?p=*&
Disallow: /*.php$
Disallow: /*?SID=

############################################
## Do not allow media indexing for the following bots
## Disallow all or add custom paths. For example */media/ or */skin/

# User-agent: baiduspider-image
# Disallow: /
# Disallow: */media/
# Disallow: */skin/

# User-agent: baiduspider-video
# Disallow: /
# Disallow: */media/
# Disallow: */skin/

# User-agent: msnbot-media
# Disallow: /
# Disallow: */media/
# Disallow: */skin/

# User-agent: Googlebot-Image
# Disallow: /
# Disallow: */media/
# Disallow: */skin/

# User-agent: Googlebot-Video
# Disallow: /
# Disallow: */media/
# Disallow: */skin/
```

## How to activate robots.txt

To activate `robots.txt` on your Maho project, follow these steps:

1. Copy our sample file
2. Save it as `robots.txt` in the `public/` directory of your Maho project.
3. Customize it, you need to review it before publishing it

That's it! Your `robots.txt` file is now active and will be used by web crawlers visiting your site.

## Customizing your robots.txt

For more advanced usage and information, consider consulting the
[Google Search Central documentation on robots.txt](https://developers.google.com/search/docs/advanced/robots/intro){:target="_blank"}.

Also, before publishing, test your `robots.txt` with a tool like
[robots.txt Validator and Testing Tool](https://technicalseo.com/tools/robots-txt/){:target="_blank"}.