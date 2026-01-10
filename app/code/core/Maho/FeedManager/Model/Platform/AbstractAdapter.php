<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Abstract Platform Adapter
 *
 * Base implementation for platform adapters
 */
abstract class Maho_FeedManager_Model_Platform_AbstractAdapter implements Maho_FeedManager_Model_Platform_AdapterInterface
{
    protected string $_code = '';
    protected string $_name = '';
    protected array $_supportedFormats = ['xml'];
    protected string $_defaultFormat = 'xml';
    protected string $_rootElement = 'feed';
    protected string $_itemElement = 'item';
    protected array $_namespaces = [];
    protected array $_requiredAttributes = [];
    protected array $_optionalAttributes = [];
    protected array $_defaultMappings = [];
    protected ?string $_taxonomyFile = null;

    #[\Override]
    public function getCode(): string
    {
        return $this->_code;
    }

    #[\Override]
    public function getName(): string
    {
        return $this->_name;
    }

    #[\Override]
    public function getSupportedFormats(): array
    {
        return $this->_supportedFormats;
    }

    #[\Override]
    public function getDefaultFormat(): string
    {
        return $this->_defaultFormat;
    }

    #[\Override]
    public function getRequiredAttributes(): array
    {
        return $this->_requiredAttributes;
    }

    #[\Override]
    public function getOptionalAttributes(): array
    {
        return $this->_optionalAttributes;
    }

    #[\Override]
    public function getAllAttributes(): array
    {
        return array_merge($this->_requiredAttributes, $this->_optionalAttributes);
    }

    #[\Override]
    public function getDefaultMappings(): array
    {
        return $this->_defaultMappings;
    }

    #[\Override]
    public function getRootElement(): string
    {
        return $this->_rootElement;
    }

    #[\Override]
    public function getItemElement(): string
    {
        return $this->_itemElement;
    }

    #[\Override]
    public function getNamespaces(): array
    {
        return $this->_namespaces;
    }

    #[\Override]
    public function transformProductData(array $productData): array
    {
        // Default: no transformation
        return $productData;
    }

    #[\Override]
    public function validateProductData(array $productData): array
    {
        $errors = [];

        foreach ($this->_requiredAttributes as $attribute => $config) {
            if ($config['required'] && empty($productData[$attribute])) {
                $errors[] = "Missing required attribute: {$attribute}";
            }
        }

        return $errors;
    }

    #[\Override]
    public function getTaxonomyFilePath(): ?string
    {
        if ($this->_taxonomyFile === null) {
            return null;
        }

        return Mage::getBaseDir('lib') . DS . 'Maho' . DS . 'FeedManager' . DS . $this->_taxonomyFile;
    }

    #[\Override]
    public function supportsCategoryMapping(): bool
    {
        return $this->_taxonomyFile !== null;
    }

    /**
     * Get platform-specific helper
     */
    protected function _getHelper(): Maho_FeedManager_Helper_Data
    {
        return Mage::helper('feedmanager');
    }

    /**
     * Sanitize text for feed output
     */
    protected function _sanitizeText(string $text): string
    {
        // Remove HTML tags
        $text = strip_tags($text);
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * Truncate text to specified length
     */
    protected function _truncateText(string $text, int $maxLength, string $suffix = ''): string
    {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        $maxLength -= mb_strlen($suffix);
        return mb_substr($text, 0, $maxLength) . $suffix;
    }

    /**
     * Format price value
     */
    protected function _formatPrice(float $price, string $currencyCode = ''): string
    {
        $formatted = number_format($price, 2, '.', '');
        if ($currencyCode) {
            $formatted .= ' ' . $currencyCode;
        }
        return $formatted;
    }
}
