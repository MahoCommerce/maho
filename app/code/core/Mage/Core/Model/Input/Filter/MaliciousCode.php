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
