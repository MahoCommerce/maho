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
     * Matches a template directive ({{media url="..."}}, {{widget ...}}, {{store ...}}, …) so it
     * can be masked while the malicious-code filter runs — see filterPreservingDirectives().
     *
     * Deliberately stricter than \Maho\Filter\Template::CONSTRUCTION_PATTERN: the body may not
     * contain <, > or braces. That is the security boundary of the masking technique. Whatever
     * this pattern matches is restored verbatim and never sanitized, and \Maho\Filter\Template
     * leaves an unknown directive in the output untouched — so a permissive body (`.*?`) would
     * let `{{a<script>alert(1)</script>}}` survive both the filter and the renderer.
     */
    public const DIRECTIVE_PATTERN = '/\{\{[a-z]{1,10}[^<>{}]*\}\}/is';

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
     * Note this sanitizes client-side HTML only. A directive's own parameters are preserved as
     * authored and resolved on output; constraining what content directives may do is a separate,
     * platform-wide concern.
     *
     * @param string|null $content
     * @param bool $applyLinkFilter also run linkFilter(), forcing target="_blank" on every link —
     *                              appropriate for article-style content, not for content whose
     *                              links are internal navigation
     * @return string
     * @throws Mage_Core_Exception
     */
    public function filterPreservingDirectives($content, $applyLinkFilter = false)
    {
        $directives = [];
        $masked = (string) preg_replace_callback(
            self::DIRECTIVE_PATTERN,
            function (array $match) use (&$directives): string {
                $token = 'MAHODIRECTIVE' . count($directives) . 'X';
                $directives[$token] = $match[0];
                return $token;
            },
            (string) $content,
        );

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
