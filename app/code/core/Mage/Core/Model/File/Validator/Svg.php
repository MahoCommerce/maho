<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use Maho\Security\SvgAllowlist;

class Mage_Core_Model_File_Validator_Svg
{
    public const NAME = 'isSvg';

    public const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    /**
     * Validation callback for SVG files
     * Sanitizes SVG content to remove potentially malicious code
     *
     * @param  string $filePath Path to temporary uploaded file
     * @throws Mage_Core_Exception
     */
    public function validate(string $filePath): void
    {
        $content = file_get_contents($filePath);

        if ($content === false || empty($content)) {
            throw Mage::exception('Mage_Core', Mage::helper('core')->__('Invalid or empty SVG file.'));
        }

        // Check if it's actually an SVG file (XML with <svg> root element)
        if (!$this->isSvgContent($content)) {
            throw Mage::exception('Mage_Core', Mage::helper('core')->__('File is not a valid SVG.'));
        }

        // Sanitize the SVG content
        $sanitized = $this->sanitizeSvg($content);

        if (empty($sanitized)) {
            throw Mage::exception('Mage_Core', Mage::helper('core')->__('SVG file could not be sanitized.'));
        }

        // Write sanitized content back to file
        if (file_put_contents($filePath, $sanitized) === false) {
            throw Mage::exception('Mage_Core', Mage::helper('core')->__('Could not write sanitized SVG file.'));
        }
    }

    /**
     * Check if content is valid SVG
     */
    protected function isSvgContent(string $content): bool
    {
        // Quick check for SVG declaration
        if (!str_contains($content, '<svg')) {
            return false;
        }

        // Try to parse as XML
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, options: LIBXML_NONET);
        libxml_clear_errors();

        if ($xml === false) {
            return false;
        }

        // Check if root element is svg (accounting for namespaces)
        $name = $xml->getName();
        return $name === 'svg';
    }

    /**
     * Remove every part of an uploaded SVG that can run code or read another document.
     *
     * This method uses the same allowlist as the content purifier, plus the FILE_ONLY elements.
     *
     * @throws Mage_Core_Exception
     */
    protected function sanitizeSvg(string $content): string
    {
        try {
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = false;

            libxml_use_internal_errors(true);
            // Do not add LIBXML_NOENT. That flag turns entity replacement on, not off. A file
            // that declares <!ENTITY x SYSTEM "file:///etc/passwd"> then copies that file into the
            // saved SVG. LIBXML_NONET stops network reads only. It does not stop file:// reads.
            $loaded = $dom->loadXML($content, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
            libxml_clear_errors();

            if (!$loaded) {
                throw new Exception('Failed to parse SVG as XML');
            }

            // Only a document type can declare an entity, and an icon never needs one.
            if ($dom->doctype !== null) {
                throw new Exception('SVG declares a document type');
            }

            $this->sanitizeElement($dom->documentElement);

            return (string) $dom->saveXML($dom->documentElement);
        } catch (Exception $e) {
            Mage::logException($e);
            throw Mage::exception('Mage_Core', Mage::helper('core')->__('SVG sanitization failed: %s', $e->getMessage()));
        }
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
            if (!$child instanceof DOMElement) {
                continue;
            }
            if (SvgAllowlist::canonicalElement($child->localName, includeFileOnly: true) === null
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
        $paint = new Mage_Core_Model_Input_Filter_SvgPaint();
        $animation = new Mage_Core_Model_Input_Filter_SvgAnimation();
        $isAnimation = in_array(
            SvgAllowlist::canonicalElement($element->localName, includeFileOnly: true),
            SvgAllowlist::ANIMATION_ELEMENTS,
            true,
        );

        foreach (iterator_to_array($element->attributes) as $attribute) {
            /** @var DOMAttr $attribute */
            // Compare the namespace, not the prefix. A document chooses its own prefixes, so a
            // test for the text "xlink:href" does not find "xl:href".
            $isNamespaced = $attribute->namespaceURI !== null
                && $attribute->namespaceURI !== 'http://www.w3.org/2000/xmlns/';
            if ($isNamespaced
                || !SvgAllowlist::allowsAttribute($element->localName, $attribute->localName, includeFileOnly: true)
            ) {
                $element->removeAttributeNode($attribute);
                continue;
            }

            $rejected = $isAnimation
                ? !$animation->isSafeValue($attribute->localName, $attribute->value)
                : in_array($attribute->localName, SvgAllowlist::PAINT_ATTRIBUTES, true)
                    && !$paint->isSafeValue($attribute->value);

            if ($rejected) {
                $element->removeAttributeNode($attribute);
            }
        }
    }
}
