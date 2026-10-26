---
description: The directives available in Maho email and CMS templates - variables, includes, and conditional blocks with full expression support for comparisons and boolean logic.
---

# Template Directives <span class="version-badge">v26.9+</span>

Transactional emails, newsletter templates, and CMS pages/blocks are processed by Maho's template filter, which understands a small set of `{{...}}` directives. This page documents the directives and the condition syntax, including the expression support introduced in 26.9.

## Variables: `{{var}}`

Outputs a template variable. Dotted paths traverse objects, and method calls are supported:

```
Hi {{var customer.name}},
your order {{var order.increment_id}} has shipped.

{{var order.getBillingAddress().format('html')}}
```

For object properties (`order.increment_id`), Maho first looks for a real getter method (`getIncrementId()`) and falls back to raw data access, so computed values like `order.billing_address` resolve the same way they do in PHP code.

## Conditions: `{{if}}` and `{{depend}}`

`{{if}}` renders one of two branches, `{{depend}}` renders its body only when the condition holds:

```
{{if order.getIsNotVirtual()}}
  Your tracking number is {{var shipment.getTrackingNumber()}}.
{{else}}
  Your download is ready in your customer account.
{{/if}}

{{depend comment}}
  Note from the merchant: {{var comment}}
{{/depend}}
```

### Expressions <span class="version-badge">v26.9+</span>

Conditions are evaluated with [Symfony ExpressionLanguage](https://symfony.com/doc/current/reference/formats/expression_language.html), so they are not limited to a single variable - comparisons, boolean logic, and method calls all work:

```
{{if order.grand_total > 100 && customer.group_id == 2}}
  Thanks for the big wholesale order!
{{/if}}

{{if order.status in ['complete', 'closed']}}...{{/if}}

{{if customer.getName() starts with 'Dr.'}}...{{/if}}
```

Commonly used operators:

| Category | Operators |
|----------|-----------|
| Comparison | `==` `!=` `<` `<=` `>` `>=` |
| Boolean | `&&` (`and`), <code>&#124;&#124;</code> (`or`), `!` (`not`) |
| Strings | `~` (concatenation), `starts with`, `ends with`, `contains`, `matches` |
| Arrays | `in`, `not in` |
| Numbers | `+` `-` `*` `/` `%` |

The full syntax is documented in the [ExpressionLanguage reference](https://symfony.com/doc/current/reference/formats/expression_language.html).

### Evaluation rules

- **Truthiness follows PHP**: `''`, `0`, `'0'`, `null`, `false`, and empty arrays are false, everything else is true. (Before 26.9, `0` and `'0'` were treated as true - a quirk of the old evaluator, not a feature.)
- **Absent variables evaluate as empty.** `{{depend comment}}` with no `comment` variable simply renders nothing; it does not log or fail.
- **A broken condition never breaks the template.** If an expression fails to parse or evaluate, the else branch renders (or nothing, for `{{depend}}`) and a warning is written to the log with the offending expression.
- **Property paths prefer getters.** As with `{{var}}`, `order.status_label` calls `getStatusLabel()` when it exists and falls back to `getData('status_label')`.

### Security

Template authors get the same power in conditions as in `{{var}}`, no more:

- The ExpressionLanguage `constant()` function is disabled, so templates cannot read PHP or class constants.
- `getConfig()` calls against encrypted configuration paths (API passwords, keys) are neutralized, mirroring the protection applied to `{{var}}` output.

## Includes: `{{include}}` and `{{template}}`

`{{include template="..."}}` includes another template by code; `{{template config_path="..."}}` includes the email template referenced by a system configuration path, which is how the shared header and footer are pulled into every transactional email:

```
{{template config_path="design/email/header"}}
...
{{template config_path="design/email/footer"}}
```

## Sanitization of stored content <span class="version-badge">v26.9+</span>

A directive is not valid HTML. The nested quotes in `{{media url="..."}}` close the surrounding attribute. Any HTML sanitizer that parses content with an unresolved directive in it will therefore mangle that directive, usually into a broken `%7B%7B…` URL.

Maho avoids this by sanitizing admin-authored rich content **on save**. The directives are masked, the malicious-code filter runs, then the directives are restored. This happens in the resource model, so admin, API and programmatic saves are all covered:

| Content | Sanitized in |
|---------|--------------|
| CMS pages | `Mage_Cms_Model_Resource_Page::_beforeSave()` |
| CMS blocks | `Mage_Cms_Model_Resource_Block::_beforeSave()` |
| Product & category WYSIWYG attributes | `Mage_Catalog_Model_Abstract::_beforeSave()` |
| Blog posts | `Maho_Blog_Model_Resource_Post::_beforeSave()` |

Two consequences worth knowing:

- **Render does not filter.** The stored value is already clean, so output only has to resolve the preserved directives.
- **Bulk import is not covered.** It writes attribute values straight to the database instead of through the model.

If you are writing a save path for your own rich-text content, reuse the shared helper rather than reimplementing the masking:

```php
$object->setData('content', Mage::getSingleton('core/input_filter_maliciousCode')
    ->filterPreservingDirectives($object->getData('content'), false,
        Mage::helper('cms')->getPageTemplateProcessor()));
```

The second argument also rewrites links to open in a new tab. Pass `true` for article-style content such as blog posts. Leave it off when the links are ordinary internal navigation, as in CMS and catalog content.

The third argument is the processor that will actually render the content. Always pass it. Which directives are safe to preserve depends on that processor rather than on a global list:

- `Mage_Cms_Model_Template_Filter` resolves all thirteen maskable directives.
- `Mage_Catalog_Model_Template_Filter` resolves five: `{{media}}`, `{{skin}}` and `{{store}}` of its own, plus `{{include}}` and `{{template}}` inherited from `\Maho\Filter\Template`.

Omitting the argument does not fall back to a smaller subset. It masks nothing at all.

A **preview** that is never persisted has no save step to hook into. Resolve the directives first, then run the plain filter over the resolved markup. This is what the newsletter and email template preview blocks do.

!!! warning "The masking pattern is a security boundary"
    Whatever the masking pattern in `Mage_Core_Model_Input_Filter_MaliciousCode` matches is restored **without sanitization**. That makes it much stricter than the renderer's own `CONSTRUCTION_PATTERN`. It enforces three rules:

    1. The keyword must be one the supplied processor actually resolves.
    2. The body must consist of well-formed `name="value"` parameters.
    3. No parameter may be named like an event handler (`on` followed by letters).

    **Rule 1** matters because a renderer leaves a directive it has no handler for untouched in the output. Masking such a directive would hand the payload straight to the browser.

    **Rule 2** matters because excluding `<` and `>` is not enough on its own. An HTML attribute is terminated by a quote, so a body like `" onerror="alert(1)` grafts a live event handler onto the enclosing tag without using an angle bracket at all.

    **Rule 3 is why the first two are not enough.** A well-formed parameter is also a well-formed HTML attribute, so `{{media url="a" onerror="alert(1)"}}` satisfies both: `media` is a keyword the processor resolves, and the body parses. Emitted verbatim inside `alt="…"`, the HTML parser ends the attribute at the directive's first quote and reads `onerror` as the next attribute of the tag. Resolving the directive discards the extra parameter, so this only bites on a render path that emits rather than resolves — excluding the name keeps the preserved text inert either way. Only `on` plus letters is rejected, so a widget parameter such as `on_sale` still masks.

    The exclusion deliberately stops at event handlers rather than growing to cover `href`, `src` and friends, which the same trick can also graft onto the tag. Rejecting `on` plus letters costs nothing, since no directive parameter is ever named `onclick`, whereas `href` and `src` are plausible widget parameter names, so excluding them would stop masking a legitimate directive and mangle it on save. What keeps those inert is the invariant at the end of this box: every handler reads its own named parameters and drops the rest, or `stripDirectives()` removes the construction. Preserve that invariant on new render paths instead of extending this list.

    **Whether the renderer runs at all counts too.** Catalog descriptions reach the template processor only when **Allow Dynamic Media URLs in Products and Categories** (`catalog/frontend/parse_url_directives`) is enabled, and that setting is per store view. Deciding it at save time would be wrong twice over: the flag can be switched off long after the save, and it is per store while the stored value is shared. So the save keeps the directive intact, and `Mage_Catalog_Helper_Output` calls `stripDirectives()` when the rendering store will not resolve it. Nothing is lost, since a directive that is never parsed would not have worked anyway.

    That fallback is keyed off `is_wysiwyg_enabled` alone, deliberately, because that is the flag the save side masks on. An attribute with WYSIWYG enabled but **Allow HTML Tags on Frontend** disabled would otherwise match none of the three branches — neither escaped, nor resolved, nor stripped — and its directives would reach the page exactly as authored.

    **No processor means no preservation.** If you cannot name the processor that will resolve a directive, there isn't one. Masking on the assumption that something downstream will resolve it is how content ends up shipping a live event handler. Product alert emails are the worked example: their markup is echoed into an already-rendered `{{var alertGrid}}`, which the template filter does not rescan, so they strip directives and filter as plain HTML.

    **`{{var}}`, `{{depend}}` and `{{if}}` are never masked**, even though every processor implements them. With no template variables assigned, which is precisely the CMS and catalog case, they render verbatim. The invariant to preserve: **a masked directive must always resolve at render time and never reach the browser as authored.**

!!! tip "Embedding video"
    Sanitization strips `<iframe>`, so a pasted YouTube embed does not survive the save. Use the **YouTube Video** widget instead. It renders the iframe when the page is displayed, so the markup is never stored and never sanitized. It embeds through `youtube-nocookie.com`, YouTube's privacy-enhanced host, which sets no tracking cookies until the visitor starts playback.

    Paste the link in whatever form you have it. Every YouTube host and URL shape below is accepted, with or without a scheme and with or without a `www.` prefix:

    | Form | Example |
    |------|---------|
    | Watch | `youtube.com/watch?v=dQw4w9WgXcQ` |
    | Share | `youtu.be/dQw4w9WgXcQ` |
    | Embed | `youtube.com/embed/dQw4w9WgXcQ` |
    | Shorts | `youtube.com/shorts/dQw4w9WgXcQ` |
    | Live | `youtube.com/live/dQw4w9WgXcQ` |
    | Legacy | `youtube.com/v/dQw4w9WgXcQ` |
    | Mobile | `m.youtube.com/watch?v=dQw4w9WgXcQ` |
    | Privacy host | `youtube-nocookie.com/embed/dQw4w9WgXcQ` |
    | Bare id | `dQw4w9WgXcQ` |

    The widget is YouTube-only. A link that carries no video id, such as a playlist, a search-results page or a channel, renders nothing rather than a broken frame.

!!! warning "What sanitization removes"
    `Mage_Core_Helper_Purifier` sanitizes with [Symfony's HTML sanitizer](https://symfony.com/doc/current/html_sanitizer.html) on the W3C Sanitizer API baseline, which is HTML5-native. `video`, `audio`, `source`, `picture`, `figure`, `details`, `section`, `article`, `mark` and `time` all survive a save, as do `id`, `class`, `style`, `target`, `rel`, `loading`, `srcset` and any `data-*` attribute. Removed:

    | Input | After save |
    |-------|------------|
    | `<script>`, `<iframe>` (Vimeo and other embeds, maps), `<object>`, `<embed>` | removed with contents |
    | `<form>`, `<input>`, `<select>`, `<textarea>` | removed with contents |
    | `data:` URIs in a media `src` | dropped |
    | event handler attributes (`onclick`, `onerror`, ...) | dropped |

    YouTube embeds have the widget above as a replacement. To put a form on a page, use a block or a widget rather than markup in a content field: a form in stored content posts wherever its `action` says, under the merchant's own domain and certificate.

    `class` and `style` are kept even though the W3C baseline classes them as unsafe, because the WYSIWYG preserves both and stores text alignment as inline style. The sanitizer does not parse CSS, so the CSS-level vectors (`expression()`, `behavior:`, `javascript:`) are removed by the regex pass in `Mage_Core_Model_Input_Filter_MaliciousCode`, which runs first.

    `data-*` needs a word of explanation, because the sanitizer matches allowed attributes by exact name and has no wildcard for them. `allowAttribute('data-attr', '*')` allows an attribute literally called `data-attr` on every element; it does not allow `data-role`. Since merchants put arbitrary data attributes on CMS markup, an enumerated list would always be incomplete, so `Mage_Core_Helper_Purifier` reads the `data-*` names off the content being sanitized and allows those. They are inert by definition, and the surrounding policy still applies, so a `data-*` allowance cannot smuggle an event handler through.

    Stored content is only affected when the entity is next saved, so existing pages keep working until someone edits them. To adjust the policy, change `Mage_Core_Helper_Purifier::buildConfig()` rather than skipping sanitization at the call site.

This mechanism addresses client-side HTML only. A directive's parameters are preserved exactly as authored and resolved on output, so constraining what content directives are permitted to do (`{{block}}`, `{{config}}`, `{{layout}}`) is a separate concern.

## Where directives are available

| Context | Filter |
|---------|--------|
| Transactional emails | `Mage_Core_Model_Email_Template_Filter` |
| Newsletter templates | `Mage_Newsletter_Model_Template_Filter` |
| CMS pages and blocks | `Mage_Cms_Model_Template_Filter` |

These filters form an inheritance chain (newsletter extends widget extends CMS extends email), so they all share the directives and condition evaluation described above. On top of the base set, the email filter adds `{{block}}`, `{{layout}}`, `{{store}}`, `{{skin}}`, `{{media}}`, `{{config}}`, `{{customvar}}`, `{{protocol}}`, `{{inlinecss}}`, and output modifiers such as `{{var subscriber_email|escape}}`; `{{widget}}` is available in the CMS, widget, and newsletter contexts.
