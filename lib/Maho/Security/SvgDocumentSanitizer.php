<?php

/**
 * Removes every part of an SVG document that can run code or read another document.
 *
 * This is the XML half of the policy, and it answers the same interface as the HTML half. Symfony
 * sanitizes a content field, which a browser reads as HTML. This class sanitizes an uploaded .svg
 * file, which a browser reads as XML. Each one must parse its input the way a browser will.
 *
 * Both halves share one policy: Maho\Security\SvgAllowlist for the names, and the value filters
 * that the sanitizer config carries. Only the parser differs. This class states the whole policy,
 * because the purifier leaves part of its own to the W3C baseline and a file has no baseline.
 *
 * The two paths differ in one way, outside the policy. A content field first runs
 * Mage_Core_Model_Input_Filter_MaliciousCode, which strips `expression()`, `behavior:`, `@import`
 * and a `javascript:` prefix. No such pass runs here, so a `style` attribute reaches a saved file
 * as the author wrote it. Each name that pass strips is dead in a modern browser or inert inside
 * a CSS `url()`, so the worst result is the remote read that
 * Mage_Core_Model_Input_Filter_SvgPaint describes. Read that class before you widen `style`.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Security;

use Dom\Attr;
use Dom\Comment;
use Dom\Element;
use Dom\EntityReference;
use Dom\ProcessingInstruction;
use Dom\XMLDocument;
use DOMException;
use Mage_Core_Helper_Purifier;
use RuntimeException;
use ValueError;
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
        try {
            // Do not add LIBXML_NOENT. That flag turns entity replacement on, not off. A file that
            // declares <!ENTITY x SYSTEM "file:///etc/passwd"> then copies that file into the saved
            // SVG. LIBXML_NONET stops network reads only. It does not stop a file:// read.
            // LIBXML_NOBLANKS drops the text nodes that hold only indentation, so a saved file
            // keeps no pretty printing.
            $dom = XMLDocument::createFromString(
                $input,
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NOBLANKS,
            );
        } catch (DOMException|ValueError $e) {
            throw new RuntimeException('Failed to parse SVG as XML', 0, $e);
        }

        $root = $dom->documentElement ?? throw new RuntimeException('Failed to parse SVG as XML');

        // A caller may hold this object through its interface, so this class cannot rely on a
        // check that a caller happens to run first.
        if (SvgAllowlist::canonicalElement($root->localName) === null
            || ($root->namespaceURI !== null && $root->namespaceURI !== self::SVG_NAMESPACE)
        ) {
            throw new RuntimeException('SVG has a root element that this policy does not allow');
        }

        // Only an internal subset can declare an entity. A plain public identifier is safe,
        // because libxml reads an external DTD only with LIBXML_DTDLOAD, which this call omits.
        // An export tool writes such an identifier into every file.
        if ($dom->doctype?->internalSubset !== null) {
            throw new RuntimeException('SVG declares an entity');
        }

        $this->sanitizeElement($root);

        return (string) $dom->saveXml($root);
    }

    /** An SVG document has no head and no body, so the context element changes no rule. */
    #[\Override]
    public function sanitizeFor(string $element, string $input): string
    {
        return $this->sanitize($input);
    }

    /** Walks the children first, so it never walks a branch that it has already removed. */
    protected function sanitizeElement(Element $element): void
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof Comment || $child instanceof ProcessingInstruction) {
                // Browsers read a comment in different ways. A processing instruction can load a
                // stylesheet. Neither one draws anything.
                $child->remove();
                continue;
            }
            if ($child instanceof EntityReference) {
                // No entity declaration reaches this point: an internal subset is refused above,
                // and an external subset is never loaded without LIBXML_DTDLOAD. saveXml() would
                // write `&name;` back, and a browser refuses to read such a file.
                throw new RuntimeException('SVG uses an entity that it does not declare');
            }
            if (!$child instanceof Element) {
                continue;
            }
            if (SvgAllowlist::canonicalElement($child->localName) === null
                || ($child->namespaceURI !== null && $child->namespaceURI !== self::SVG_NAMESPACE)
            ) {
                $child->remove();
                continue;
            }
            $this->sanitizeElement($child);
        }

        $this->sanitizeAttributes($element);
    }

    protected function sanitizeAttributes(Element $element): void
    {
        foreach (iterator_to_array($element->attributes) as $attribute) {
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
                $attribute->value = $value;
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
    protected function sanitizeValue(string $element, Attr $attribute): ?string
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
