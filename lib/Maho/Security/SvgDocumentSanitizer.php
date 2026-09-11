<?php

/**
 * Removes every part of an SVG document that can run code or read another document.
 *
 * This is the XML half of the policy, and it answers the same interface as the HTML half. Symfony
 * sanitizes a content field, which a browser reads as HTML. This class sanitizes an uploaded .svg
 * file, which a browser reads as XML. A sanitizer must read its input the way its reader does.
 *
 * The policy behind both is one: Maho\Security\SvgAllowlist for the names, and the value filters
 * that the sanitizer config carries. Only the parser differs.
 *
 * This class builds the whole policy itself. The purifier leaves part of its own policy to the
 * W3C baseline of Symfony, and a file has no baseline behind it.
 *
 * One difference stands between the two paths, and it is not in the policy. A content field runs
 * Mage_Core_Model_Input_Filter_MaliciousCode first, and that pass removes `expression()`,
 * `behavior:`, `@import` and a `javascript:` prefix by regular expression. No such pass runs here,
 * so a `style` attribute reaches a saved file exactly as the author wrote it. Every name that the
 * pass removes is either dead in a modern browser or inert inside a CSS `url()`, so the live
 * result is the remote read that Mage_Core_Model_Input_Filter_SvgPaint already describes. Read
 * that class before you widen `style` any further.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Security;

use DOMAttr;
use DOMComment;
use DOMDocument;
use DOMElement;
use DOMEntityReference;
use DOMProcessingInstruction;
use Mage_Core_Helper_Purifier;
use RuntimeException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class SvgDocumentSanitizer implements HtmlSanitizerInterface
{
    public const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    public const XMLNS_NAMESPACE = 'http://www.w3.org/2000/xmlns/';

    protected ?HtmlSanitizerConfig $config = null;

    /** @throws RuntimeException when the document is not SVG that this policy can make safe */
    #[\Override]
    public function sanitize(string $input): string
    {
        $dom = new DOMDocument();
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = false;

        libxml_use_internal_errors(true);
        // Do not add LIBXML_NOENT. That flag turns entity replacement on, not off. A file that
        // declares <!ENTITY x SYSTEM "file:///etc/passwd"> then copies that file into the saved
        // SVG. LIBXML_NONET stops network reads only. It does not stop a file:// read.
        $loaded = $dom->loadXML($input, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        if (!$loaded || $dom->documentElement === null) {
            throw new RuntimeException('Failed to parse SVG as XML');
        }

        // A caller may hold this object through its interface, so the promise of the class must
        // not rest on a check that the caller happens to run first.
        if (SvgAllowlist::canonicalElement($dom->documentElement->localName) === null
            || ($dom->documentElement->namespaceURI !== null
                && $dom->documentElement->namespaceURI !== self::SVG_NAMESPACE)
        ) {
            throw new RuntimeException('SVG has a root element that this policy does not allow');
        }

        // Only an internal subset can declare an entity. A plain public identifier is safe,
        // because libxml reads an external DTD only with LIBXML_DTDLOAD, which this call omits.
        // An export tool writes such an identifier into every file.
        if ($dom->doctype?->internalSubset !== null) {
            throw new RuntimeException('SVG declares an entity');
        }

        $this->sanitizeElement($dom->documentElement);

        return (string) $dom->saveXML($dom->documentElement);
    }

    /** An SVG document has no head and no body, so the context element changes no rule. */
    #[\Override]
    public function sanitizeFor(string $element, string $input): string
    {
        return $this->sanitize($input);
    }

    /** Reads the children first, so this method does not read a branch that it removes. */
    protected function sanitizeElement(DOMElement $element): void
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) {
                // Browsers read a comment in different ways. A processing instruction can load a
                // stylesheet. Neither one draws anything.
                $element->removeChild($child);
                continue;
            }
            if ($child instanceof DOMEntityReference) {
                // No document type reaches this point, so nothing declares this name. saveXML()
                // would write `&name;` back, and a browser refuses to read such a file.
                throw new RuntimeException('SVG uses an entity that it does not declare');
            }
            if (!$child instanceof DOMElement) {
                continue;
            }
            if (SvgAllowlist::canonicalElement($child->localName) === null
                || ($child->namespaceURI !== null && $child->namespaceURI !== self::SVG_NAMESPACE)
            ) {
                $element->removeChild($child);
                continue;
            }
            $this->sanitizeElement($child);
        }

        $this->sanitizeAttributes($element);
    }

    protected function sanitizeAttributes(DOMElement $element): void
    {
        // Pass false. The iterator keys an attribute by its local name, so `xlink:href` and
        // `href` collide and one node never reaches this loop.
        foreach (iterator_to_array($element->attributes, false) as $attribute) {
            /** @var DOMAttr $attribute */
            // Compare the namespace, not the prefix. A document chooses its own prefixes, so a
            // test for the text "xlink:href" does not find "xl:href".
            $isNamespaced = $attribute->namespaceURI !== null
                && $attribute->namespaceURI !== self::XMLNS_NAMESPACE;
            $isAllowed = str_starts_with(strtolower($attribute->localName), Mage_Core_Helper_Purifier::DATA_ATTRIBUTE_PREFIX)
                || SvgAllowlist::allowsAttribute($element->localName, $attribute->localName);
            if ($isNamespaced || !$isAllowed) {
                $element->removeAttributeNode($attribute);
                continue;
            }

            $value = $this->sanitizeValue($element->localName, $attribute);

            if ($value === null) {
                $element->removeAttributeNode($attribute);
            } elseif ($value !== $attribute->value) {
                // Not $attribute->value. That setter reads an entity reference, so a rewritten
                // value holding a bare `&` raises a warning and leaves the attribute empty.
                $element->setAttribute($attribute->nodeName, $value);
            }
        }
    }

    /**
     * Runs the value filters that the config carries, so one list governs both halves.
     *
     * Read the list from the config, not from Mage_Core_Helper_Purifier::attributeSanitizers().
     * The config also holds the two filters that Symfony adds in its own constructor, and a path
     * that reads only the Maho list would skip them.
     */
    protected function sanitizeValue(string $element, DOMAttr $attribute): ?string
    {
        $this->config ??= Mage_Core_Helper_Purifier::buildConfig();
        $value = $attribute->value;

        foreach ($this->config->getAttributeSanitizers() as $sanitizer) {
            $elements = $sanitizer->getSupportedElements();
            $attributes = $sanitizer->getSupportedAttributes();

            if (($elements !== null && !SvgAllowlist::containsName($elements, $element))
                || ($attributes !== null && !SvgAllowlist::containsName($attributes, $attribute->localName))
            ) {
                continue;
            }

            $value = $sanitizer->sanitizeAttribute($element, $attribute->localName, $value, $this->config);

            if ($value === null) {
                return null;
            }
        }

        return $value;
    }
}
