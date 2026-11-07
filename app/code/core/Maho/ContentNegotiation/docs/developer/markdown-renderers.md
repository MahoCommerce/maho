---
description: How Maho serves a markdown version of a page to AI agents, and how a module adds a markdown renderer for its own pages.
---

# Markdown renderers <span class="version-badge">v26.9+</span>

Maho answers a page request with markdown when the request sends `Accept: text/markdown` or when
the URL ends with `.md`. The `Maho_ContentNegotiation` module does this for the catalog, CMS and
blog pages. This guide explains how the module works and how a module adds a renderer for its
own pages.

For the merchant view (settings, headers, cache, web server), see
[AI Crawlers & llms.txt](../user-guide/ai-and-crawlers.md#markdown-for-ai-agents).

## How a request is served

The module runs no controller of its own. Four observers wrap the normal dispatch of the page:

1. `controller_front_init_before`: when the request URI ends with `.md`, the observer removes
   the suffix and marks the request. The front controller then routes the plain URL, so URL
   rewrites match it as they match the HTML URL. `/index.md` maps to the store root.
2. `controller_action_predispatch`: when the route is allowed and a cached document exists, the
   observer sends it and stops the dispatch. The action never runs.
3. `controller_action_layout_generate_blocks_after`: the action loaded its entity and the layout
   built its blocks. The observer resolves the renderer of the route, calls it, caches the
   result, and replaces the response body. The layout output is skipped.
4. `controller_front_send_response_before`: the observer adds the `Link` and `Vary` headers to
   an HTML response, keeps the `.md` suffix on a redirect, and turns an HTML answer to a `.md`
   request into a markdown `404`.

Step 3 fires only when the action generates layout blocks. An action that answers without a
layout never gets a markdown version.

A request asks for markdown when one of these is true:

- The URL had the `.md` suffix.
- The `Accept` header lists `text/markdown` with a higher quality than `text/html`. A wildcard
  never selects markdown.

Only `GET` and `HEAD` requests negotiate.

## The renderer contract

A renderer implements `Maho_ContentNegotiation_Model_Renderer_RendererInterface`:

```php
interface Maho_ContentNegotiation_Model_Renderer_RendererInterface
{
    /** Null when the page has no entity to render, so the HTML response stays as it is. */
    public function render(): ?string;

    /** @return string[] */
    public function getCacheTags(): array;
}
```

`render()` reads the entity the action loaded, for example from `Mage::registry()`, and returns
the full markdown document. Return `null` when the page has nothing to render: the HTML response
is then sent unchanged, and a `.md` request answers `404`.

`getCacheTags()` returns the cache tags of the entity. A save of the entity clears the cached
document through these tags. The document is also tagged with the **Blocks HTML output** cache
group, so a flush of that cache clears every markdown page.

## Register a renderer

Declare the renderer in the `config.xml` of your module, under
`global/contentnegotiation/renderers`. Each child names a route prefix and a model alias:

```xml
<config>
    <global>
        <contentnegotiation>
            <renderers>
                <faq_question>
                    <route>faq/question/view</route>
                    <model>faq/renderer_question</model>
                </faq_question>
            </renderers>
        </contentnegotiation>
    </global>
</config>
```

The route is `module/controller/action`, as the request reports it. The resolver compares the
prefixes in declaration order and the first match wins. Declare a specific route before a
general one, for example `faq/question/view` before `faq/question`.

Two conditions gate the renderer at run time:

- The route must match a line of the **Routes** field in **System > Configuration > Catalog >
  Crawlers & robots.txt > Markdown for AI agents**. The field replaces the default list, so the
  merchant adds a line and keeps the default ones. Document the line to add.
- The action must generate a layout, because the renderer runs after the layout blocks are built.

A renderer alone adds no page to llms.txt. `Mage_Sitemap_Model_Llms` lists the CMS pages, the
categories and the blog index, and links their markdown version when their route has a renderer.

## Write a renderer

Extend `Maho_ContentNegotiation_Model_Renderer_AbstractRenderer`. It returns no cache tags by
default, and it gives you the building blocks of the core renderers:

| Method | What it does |
|--------|--------------|
| `heading($title, $description)` | A level one heading, then the description as a quote. |
| `section($title, $body)` | A level two heading with its body. |
| `toMarkdown($html)` | Converts rendered HTML to markdown with `league/html-to-markdown`. Scripts, styles, forms and SVG are removed. Tables are kept. Text entities are decoded, except angle brackets. |
| `text($html)` | Plain text without tags, with angle brackets escaped. Use it for a name or a value that goes into a line. |
| `link($label, $url)` | A markdown link with a label that is safe inside a table cell. |
| `cell($value)` | A table cell value with the pipe character escaped. |
| `table($headers, $rows)` | A markdown table. Returns an empty string without rows. |
| `formatPrice($price)` | The price in the display currency of the store. |
| `displayPrice($product, $price)` | The price with the tax treatment of the structured data on the page. |
| `availabilityLabel($product)` | "In stock", "Backorder", "Limited availability" or "Out of stock". |
| `productTable($products)` | A table with a link, SKU, price and availability for each product. |

A minimal renderer:

```php
<?php

declare(strict_types=1);

class My_Faq_Model_Renderer_Question extends Maho_ContentNegotiation_Model_Renderer_AbstractRenderer
{
    #[\Override]
    public function render(): ?string
    {
        $question = $this->getQuestion();
        if ($question === null) {
            return null;
        }

        $sections = [$this->heading((string) $question->getTitle(), (string) $question->getMetaDescription())];
        $answer = $this->toMarkdown((string) $question->getAnswerHtml());
        if ($answer !== '') {
            $sections[] = $answer;
        }

        return implode("\n\n", $sections) . "\n";
    }

    #[\Override]
    public function getCacheTags(): array
    {
        return $this->getQuestion()?->getCacheTags() ?: [];
    }

    private function getQuestion(): ?My_Faq_Model_Question
    {
        $question = Mage::registry('current_faq_question');

        return $question instanceof My_Faq_Model_Question && $question->getId() ? $question : null;
    }
}
```

Keep the document self-contained. One URL names one document, so ignore the page number, the
sort order and the filters of the request. Cap a list instead of paging it, as the category
renderer caps its product table at 100 rows, and tell the agent where the rest is.

Resolve template directives before you convert HTML: `toMarkdown()` does not resolve `{{media}}`
or `{{widget}}`. The CMS renderer runs the page template processor first.

## Cache

The document is cached in the **Blocks HTML output** cache for the number of seconds in
**Cache Lifetime**. The key holds the store, the current currency, the customer group and the
request URI with its query, so a page named by a parameter, such as
`catalog/product/view?id=2`, gets its own entry. The `.md` form and the `Accept` form of a page
share one entry, because the suffix is removed before the key is built.

A lifetime of `0` turns the cache off. Nothing outside the cache tags clears an entry, so a
change that touches the document without a save of the entity, such as a stock update, shows
when the lifetime ends.

## Helper

`Mage::helper('contentnegotiation')` answers the questions another module needs:

```php
$helper = Mage::helper('contentnegotiation');

$helper->isEnabled();                             // the store flag
$helper->hasMarkdown('catalog/product/view');     // enabled, route allowed, renderer declared
$helper->toMarkdownUrl($url);                     // the .md URL of a page URL
$helper->isMarkdownRequest($request);             // the current request asks for markdown
```

`toMarkdownUrl()` puts the suffix in place of a trailing slash, drops the query string, and maps
the store root to `/index.md`. Use it wherever you print the markdown URL of a page, as llms.txt
does.

## Test a renderer

The core tests in `tests/Frontend/Integration/ContentNegotiation/` show the pattern: register
the entity in the registry, call `render()` on the model, and assert on the markdown. The
observer tests build a request with the `Accept` header or the `.md` suffix, call the observer
methods directly, and read the response headers.
