<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

use Maho\Security\SvgDocumentSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class Mage_Core_Model_File_Validator_Svg
{
    public const NAME = 'isSvg';

    protected ?HtmlSanitizerInterface $sanitizer = null;

    /** @param HtmlSanitizerInterface|array<mixed>|null $sanitizer */
    public function __construct(HtmlSanitizerInterface|array|null $sanitizer = null)
    {
        // Mage::getModel() hands every model its arguments array, so only a real sanitizer counts.
        $this->sanitizer = $sanitizer instanceof HtmlSanitizerInterface ? $sanitizer : null;
    }

    /**
     * Validation callback for SVG files. Rewrites the file with the part that the policy allows.
     *
     * @throws Mage_Core_Exception
     */
    public function validate(string $filePath): void
    {
        $content = file_get_contents($filePath);

        if ($content === false || empty($content)) {
            throw Mage::exception('Mage_Core', Mage::helper('core')->__('Invalid or empty SVG file.'));
        }

        if (!$this->isSvgContent($content)) {
            throw Mage::exception('Mage_Core', Mage::helper('core')->__('File is not a valid SVG.'));
        }

        try {
            $sanitized = ($this->sanitizer ??= new SvgDocumentSanitizer())->sanitize($content);
        } catch (Exception $e) {
            Mage::logException($e);
            throw Mage::exception('Mage_Core', Mage::helper('core')->__('SVG sanitization failed: %s', $e->getMessage()));
        }

        if (empty($sanitized)) {
            throw Mage::exception('Mage_Core', Mage::helper('core')->__('SVG file could not be sanitized.'));
        }

        if (file_put_contents($filePath, $sanitized) === false) {
            throw Mage::exception('Mage_Core', Mage::helper('core')->__('Could not write sanitized SVG file.'));
        }
    }

    /** Tests that the upload is XML with an `svg` root element, whatever namespace it declares. */
    protected function isSvgContent(string $content): bool
    {
        if (!str_contains($content, '<svg')) {
            return false;
        }

        $libXmlErrorsState = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content, options: LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($libXmlErrorsState);

        return $xml !== false && $xml->getName() === 'svg';
    }
}
