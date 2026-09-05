<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Model_Input_Filter_MaliciousCode
{
    /**
     * Directives that may be masked, subject to the renderer actually implementing them.
     *
     * var, depend and if are deliberately absent even though every renderer implements them:
     * \Maho\Filter\Template returns the construction verbatim when no template variables are
     * assigned (which is exactly the CMS/catalog case), so masking them would put their quotes in
     * front of the browser unfiltered.
     */
    public const DIRECTIVE_KEYWORDS = [
        'media', 'skin', 'store', 'widget', 'config', 'block',
        'layout', 'template', 'protocol', 'htmlescape', 'customvar', 'inlinecss', 'include', 'icon',
    ];

    /**
     * Matches any template construction, used to strip directives that will not be resolved.
     * Mirrors \Maho\Filter\Template::CONSTRUCTION_PATTERN.
     */
    public const ANY_DIRECTIVE_PATTERN = '/\{\{[a-z]{0,10}.*?\}\}/si';

    /**
     * A single directive parameter: name="value", name='value' or an unquoted token.
     *
     * The value may contain NO quote character of either kind, nor <, > or braces. Forbidding only
     * the delimiting quote is not enough: a directive is interpolated into HTML by whoever placed
     * it, and a value delimited with ' that smuggles a " closes an enclosing double-quoted
     * attribute — `{{media url='x" onerror="alert(1)//'}}` inside src="…" resolves to a URL
     * followed by a live event handler. No real directive parameter needs to contain a quote.
     *
     * The name may not be an event handler. A well-formed parameter is still a well-formed HTML
     * attribute, so `{{media url="a" onerror="alert(1)"}}` satisfies every other rule here and
     * names a keyword the renderer resolves — yet emitted verbatim inside alt="…" the HTML parser
     * ends the attribute at the directive's first quote and reads `onerror` as the next attribute
     * of the tag. Resolving the directive discards the extra parameter, so this only bites on a
     * render path that emits rather than resolves; excluding the name keeps the preserved text
     * inert either way. Only `on` + letters is rejected, which is the exact shape the browser
     * treats as a handler — a widget parameter such as `on_sale` is untouched.
     *
     * The exclusion deliberately stops there and is not extended to href/src/formaction/etc, even
     * though those are grafted onto the tag by the same trick. Rejecting `on` + letters is free —
     * no directive parameter is ever named `onclick` — whereas `href` and `src` are plausible
     * widget parameter names, so excluding them would stop masking a legitimate directive and
     * mangle it on save. What keeps those inert is the invariant above: a masked directive is
     * always resolved at render (every handler reads its own named parameters and drops the rest)
     * or removed by stripDirectives(). Preserve that invariant on new render paths rather than
     * growing this list.
     */
    private const DIRECTIVE_PARAM = '(?:\s+(?!on[a-z]+\s*=)[a-z0-9_:.\/-]+\s*=\s*(?:"[^"\'<>{}]*"|\'[^"\'<>{}]*\'|[^\s"\'<>{}]+))';

    /**
     * Matches a template directive ({{media url="..."}}, {{widget ...}}, {{store ...}}, …) so it
     * can be masked while the malicious-code filter runs — see filterPreservingDirectives().
     *
     * Whatever this pattern matches is restored verbatim and never sanitized, so it is the security
     * boundary of the masking technique and is deliberately far stricter than
     * \Maho\Filter\Template::CONSTRUCTION_PATTERN. Two rules make it safe, and both are load-bearing:
     *
     * 1. The keyword must be one $renderer actually resolves. \Maho\Filter\Template leaves a
     *    directive it has no handler for untouched in the output, so masking one hands the payload
     *    straight to the browser. This is renderer-specific, not global: the catalog filter
     *    implements only 5 of the 13 keywords below, so masking {{config}} in a product
     *    description would emit it verbatim on the storefront.
     * 2. The body must be well-formed `name="value"` parameters, and no parameter may be named like
     *    an event handler. A directive legitimately contains quotes, so a body such as
     *    `" onerror="alert(1)` would otherwise close the enclosing HTML attribute and graft a live
     *    event handler onto the tag — without ever using < or >. Requiring well-formed parameters
     *    is not enough on its own, because `onerror="alert(1)"` is itself one: rule (1) vets the
     *    keyword, not the parameters after it, so `{{media url="a" onerror="alert(1)"}}` clears both
     *    halves unless the name is excluded too. See DIRECTIVE_PARAM.
     *
     * Neither rule is sufficient alone: (2) alone would mask an unresolvable keyword whose body
     * happens to parse, and (1) alone would mask a resolvable keyword carrying a handler.
     *
     * @param \Maho\Filter\Template $renderer the processor that will render this content
     */
    public static function getDirectivePattern($renderer): string
    {
        $keywords = array_filter(
            self::DIRECTIVE_KEYWORDS,
            // method_exists, not is_callable: a renderer with a magic __call would satisfy
            // is_callable for every keyword and silently widen the mask to all 14.
            static fn(string $keyword): bool => method_exists($renderer, $keyword . 'Directive'),
        );

        if ($keywords === []) {
            // Match nothing rather than compiling an empty alternation, which would match ''.
            return '/(?!)/';
        }

        return '/\{\{(?:' . implode('|', $keywords) . ')' . self::DIRECTIVE_PARAM . '*\s*\}\}/i';
    }

    /**
     * Neutralize template constructions that a render path is not going to resolve.
     *
     * Leaving an unresolved directive in the markup is not merely cosmetic: its quotes close the
     * enclosing HTML attribute, and whatever follows becomes a live attribute on the tag. That
     * danger comes entirely from the quotes, so only quote-bearing constructions are removed —
     * `{{name}}` and friends are inert and survive as the literal text the author typed, which
     * matters for content that legitimately shows template syntax (docs, tutorials, code samples).
     */
    public static function stripDirectives(?string $content): string
    {
        return (string) preg_replace_callback(
            self::ANY_DIRECTIVE_PATTERN,
            static fn(array $match): string => strpbrk($match[0], '"\'') === false ? $match[0] : '',
            (string) $content,
        );
    }

    /**
     * Regular expressions for cutting malicious code
     */
    protected array $_expressions = [
        //comments, must be first
        '/(\/\*.*\*\/)/Us',
        //tabs
        '/(\t)/',
        //javascript prefix
        '/(javascript\s*:)/Usi',
        //import styles
        '/(@import)/Usi',
        //js in the style attribute
        '/style=[^<]*((expression\s*?\([^<]*?\))|(behavior\s*:))[^<]*(?=\>)/Uis',
        //js attributes
        '/(ondblclick|onclick|onkeydown|onkeypress|onkeyup|onmousedown|onmousemove|onmouseout|onmouseover|onmouseup|onload|onunload|onerror|onanimationstart|onfocus|onloadstart|ontoggle)\s*=[^>]*(?=\>)/Uis',
        //tags
        '/<\/?(script|meta|link|frame|iframe|object).*>/Uis',
        //scripts
        '/<\?\s*?(php|=).*>/Uis',
        //base64 usage
        '/src\s*=[^<]*base64[^<]*(?=\>)/Uis',
        //data attribute
        '/(data(\\\\x3a|:|%3A)(.+?(?=")|.+?(?=\')))/is',
    ];

    /**
     * @param string|array|null $value
     * @return string|array
     */
    public function filter($value)
    {
        if ($value === null) {
            return '';
        }

        do {
            $value = preg_replace($this->_expressions, '', $value ?? '', -1, $count);
        } while ($count !== 0);

        return Mage::helper('core/purifier')->purify($value);
    }

    /**
     * Sanitize admin-authored rich content without mangling the template directives it contains.
     *
     * The malicious-code filter HTML-parses its input, and a directive is not valid HTML — the
     * nested quotes of {{media url="..."}} inside an img src break attribute parsing, leaving a
     * %7B%7B… URL behind. So real directives are masked before filtering and restored after;
     * anything else wrapped in braces (e.g. {{<script>…}}) is left for the filter to strip.
     *
     * Use this on every save path that persists rich content, so the stored value is clean and
     * render only has to resolve the preserved directives. Never run filter() directly over
     * content whose directives are still unresolved.
     *
     * Only a directive $renderer can resolve is masked (see getDirectivePattern()); anything else
     * in braces stays in the filter's hands. The masked text is restored without sanitization, so
     * the invariant that keeps this safe is that a masked directive always resolves at render time
     * and never reaches the browser as written.
     *
     * Pass the processor this content will actually be rendered with. Omitting it preserves
     * nothing: a caller with no renderer has no render path that resolves directives, and masking
     * on the assumption that something downstream will resolve them is how content ends up
     * shipping a live event handler.
     *
     * Note this sanitizes client-side HTML only. A directive's own parameters are preserved as
     * authored and resolved on output; constraining what content directives may do is a separate,
     * platform-wide concern.
     *
     * @param string|null $content
     * @param bool $applyLinkFilter also run linkFilter(), forcing target="_blank" on every link —
     *                              appropriate for article-style content, not for content whose
     *                              links are internal navigation
     * @param \Maho\Filter\Template|null $renderer the processor that will render this content
     * @return string
     * @throws Mage_Core_Exception
     */
    public function filterPreservingDirectives($content, $applyLinkFilter = false, $renderer = null)
    {
        $directives = [];
        $masked = (string) $content;
        if ($renderer !== null) {
            // The nonce keeps author text that happens to spell a token from being rewritten
            // into a directive at restore time.
            $prefix = 'MAHODIRECTIVE' . bin2hex(random_bytes(8)) . 'X';
            $masked = (string) preg_replace_callback(
                self::getDirectivePattern($renderer),
                function (array $match) use (&$directives, $prefix): string {
                    $token = $prefix . count($directives) . 'X';
                    $directives[$token] = $match[0];
                    return $token;
                },
                $masked,
            );
        }

        $result = (string) $this->filter($masked);
        if ($applyLinkFilter) {
            $result = $this->linkFilter($result);
        }

        return $directives === [] ? $result : strtr($result, $directives);
    }

    /**
     * Add expression
     *
     * @param string $expression
     * @return $this
     */
    public function addExpression($expression)
    {
        if (!in_array($expression, $this->_expressions)) {
            $this->_expressions[] = $expression;
        }
        return $this;
    }

    /**
     * Set expressions
     *
     * @return $this
     */
    public function setExpressions(array $expressions)
    {
        $this->_expressions = $expressions;
        return $this;
    }

    /**
     * The filter adds safe attributes to the link
     *
     * @param string $html
     * @param bool $removeWrapper flag for remove wrapper tags: Doctype, html, body
     * @return string
     * @throws Mage_Core_Exception
     */
    public function linkFilter($html, $removeWrapper = true)
    {
        if (stristr($html, '<a ') === false) {
            return $html;
        }

        $libXmlErrorsState = libxml_use_internal_errors(true);
        $dom = $this->_initDOMDocument();
        // DOMDocument::loadHTML() defaults to ISO-8859-1 when no encoding hint
        // is present, which mangles UTF-8 multi-byte sequences during the
        // saveHTML() round-trip (e.g. "ö" becomes "&Atilde;&para;"). Prepend a
        // <?xml encoding> processing instruction so libxml parses as UTF-8.
        // TODO: when the minimum PHP version reaches 8.4, replace this whole
        // DOMDocument + XML-PI workaround with \DOM\HTMLDocument::createFromString(),
        // which parses UTF-8 natively (and drop the <?xml ...> strip from the
        // wrapper regex below).
        if (!$dom->loadHTML('<?xml encoding="UTF-8">' . $html)) {
            Mage::throwException(Mage::helper('core')->__('HTML filtration has failed.'));
        }

        $relAttributeDefaultItems = ['noopener', 'noreferrer'];
        /** @var DOMElement $linkItem */
        foreach ($dom->getElementsByTagName('a') as $linkItem) {
            $relAttributeItems = [];
            $relAttributeCurrentValue = $linkItem->getAttribute('rel');
            if (!empty($relAttributeCurrentValue)) {
                $relAttributeItems = explode(' ', $relAttributeCurrentValue);
            }
            $relAttributeItems = array_unique(array_merge($relAttributeItems, $relAttributeDefaultItems));
            $linkItem->setAttribute('rel', implode(' ', $relAttributeItems));
            $linkItem->setAttribute('target', '_blank');
        }

        if (!$html = $dom->saveHTML()) {
            Mage::throwException(Mage::helper('core')->__('HTML filtration has failed.'));
        }

        if ($removeWrapper) {
            // Strip the wrapper tags libxml adds, plus the XML PI we injected
            // above (libxml may emit it with or without a trailing question
            // mark depending on version; [^>]* matches both forms).
            $html = preg_replace('/<(?:!DOCTYPE|\?xml\b|\/?(?:html|body))[^>]*>\s*/i', '', $html);
        }

        libxml_use_internal_errors($libXmlErrorsState);

        return $html;
    }

    /**
     * Initialize built-in DOM parser instance
     *
     * @return DOMDocument
     */
    protected function _initDOMDocument()
    {
        $dom = new DOMDocument();
        $dom->strictErrorChecking = false;
        $dom->recover = false;

        return $dom;
    }
}
