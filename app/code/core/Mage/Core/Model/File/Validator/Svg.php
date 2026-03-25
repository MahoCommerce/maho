<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


class Mage_Core_Model_File_Validator_Svg
{
    public const NAME = 'isSvg';

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
        $xml = simplexml_load_string($content);
        libxml_clear_errors();

        if ($xml === false) {
            return false;
        }

        // Check if root element is svg (accounting for namespaces)
        $name = $xml->getName();
        return $name === 'svg';
    }

    /**
     * Sanitize SVG content using block-list approach
     * This allows all SVG content except dangerous elements and attributes
     */
    protected function sanitizeSvg(string $content): string
    {
        // Dangerous elements to remove
        $dangerousElements = ['script', 'object', 'embed', 'foreignObject', 'iframe', 'style'];

        // Dangerous attribute prefixes (event handlers)
        $dangerousAttrPrefixes = ['on'];

        try {
            $dom = new DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = false;

            // Load SVG content
            libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($content, LIBXML_NONET | LIBXML_NOENT);
            libxml_clear_errors();

            if (!$loaded) {
                throw new Exception('Failed to parse SVG as XML');
            }

            // Remove dangerous elements
            foreach ($dangerousElements as $tagName) {
                $elements = $dom->getElementsByTagName($tagName);
                $nodesToRemove = [];
                foreach ($elements as $element) {
                    $nodesToRemove[] = $element;
                }
                foreach ($nodesToRemove as $node) {
                    $node->parentNode->removeChild($node);
                }
            }

            // Remove dangerous attributes from all elements
            $xpath = new DOMXPath($dom);
            $allElements = $xpath->query('//*');

            foreach ($allElements as $element) {
                // Only process DOMElement nodes (skip text nodes, etc.)
                if (!$element instanceof DOMElement) {
                    continue;
                }

                $attributesToRemove = [];

                // Check each attribute
                foreach ($element->attributes as $attr) {
                    $attrName = strtolower($attr->name);

                    // Remove event handler attributes (onclick, onload, etc.)
                    foreach ($dangerousAttrPrefixes as $prefix) {
                        if (str_starts_with($attrName, $prefix)) {
                            $attributesToRemove[] = $attr->name;
                            break;
                        }
                    }

                    // Remove javascript: and data: URLs in href/xlink:href
                    if (in_array($attrName, ['href', 'xlink:href'])) {
                        $value = trim($attr->value);
                        if (preg_match('/^(javascript|data):/i', $value)) {
                            $attributesToRemove[] = $attr->name;
                        }
                    }
                }

                // Remove flagged attributes
                foreach ($attributesToRemove as $attrName) {
                    $element->removeAttribute($attrName);
                }
            }

            $sanitized = $dom->saveXML($dom->documentElement);

            return $sanitized;
        } catch (Exception $e) {
            Mage::logException($e);
            throw Mage::exception('Mage_Core', Mage::helper('core')->__('SVG sanitization failed: %s', $e->getMessage()));
        }
    }
}
