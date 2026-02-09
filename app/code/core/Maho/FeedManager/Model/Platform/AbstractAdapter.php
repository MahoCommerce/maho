<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
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
     * Search taxonomy for matching categories
     *
     * @param string $query Search query
     * @param int $limit Maximum number of results
     * @return array<int, array{id: string, path: string}> Array of matching categories
     */
    #[\Override]
    public function searchTaxonomy(string $query, int $limit = 10): array
    {
        $taxonomyFile = $this->getTaxonomyFilePath();
        if (!$taxonomyFile || !file_exists($taxonomyFile)) {
            return [];
        }

        $results = [];
        $query = strtolower(trim($query));
        $queryParts = explode(' ', $query);

        $handle = fopen($taxonomyFile, 'r');
        if (!$handle) {
            return [];
        }

        while (($line = fgets($handle)) !== false) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            // Parse line - format depends on taxonomy file
            // Google format: "id - Category > Subcategory > ..." or just "Category > Subcategory > ..."
            $lineLower = strtolower($line);

            // Check if all query parts match
            $allMatch = true;
            foreach ($queryParts as $part) {
                if (!str_contains($lineLower, $part)) {
                    $allMatch = false;
                    break;
                }
            }

            if ($allMatch) {
                // Extract ID and path
                if (preg_match('/^(\d+)\s*-\s*(.+)$/', $line, $matches)) {
                    $results[] = [
                        'id' => $matches[1],
                        'path' => trim($matches[2]),
                    ];
                } else {
                    $results[] = [
                        'id' => '',
                        'path' => $line,
                    ];
                }

                if (count($results) >= $limit) {
                    break;
                }
            }
        }

        fclose($handle);

        return $results;
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

    /**
     * Transform condition value to platform format
     */
    protected function _transformCondition(mixed $value): string
    {
        $map = [
            'new' => 'new',
            'refurbished' => 'refurbished',
            'used' => 'used',
            'like new' => 'refurbished',
            'renewed' => 'refurbished',
        ];

        $normalized = strtolower(trim((string) $value));
        return $map[$normalized] ?? 'new';
    }

    /**
     * Transform gender value to platform format
     */
    protected function _transformGender(mixed $value): string
    {
        $map = [
            'male' => 'male',
            'men' => 'male',
            'm' => 'male',
            'female' => 'female',
            'women' => 'female',
            'f' => 'female',
            'unisex' => 'unisex',
            'both' => 'unisex',
        ];

        $normalized = strtolower(trim((string) $value));
        return $map[$normalized] ?? 'unisex';
    }

    /**
     * Transform age group value to platform format
     */
    protected function _transformAgeGroup(mixed $value): string
    {
        $map = [
            'newborn' => 'newborn',
            'infant' => 'infant',
            'baby' => 'infant',
            'toddler' => 'toddler',
            'kids' => 'kids',
            'children' => 'kids',
            'child' => 'kids',
            'adult' => 'adult',
            'adults' => 'adult',
        ];

        $normalized = strtolower(trim((string) $value));
        return $map[$normalized] ?? 'adult';
    }

    /**
     * Transform boolean value to yes/no format
     */
    protected function _transformBoolean(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes']) ? 'yes' : 'no';
    }

    /**
     * Transform availability value to platform format
     *
     * @param mixed $value Raw availability value (qty, boolean, string)
     * @param bool $useUnderscore Use underscore format (in_stock) vs space format (in stock)
     * @return string Normalized availability string
     */
    protected function _transformAvailability(mixed $value, bool $useUnderscore = false): string
    {
        $inStock = $useUnderscore ? 'in_stock' : 'in stock';
        $outOfStock = $useUnderscore ? 'out_of_stock' : 'out of stock';

        // Numeric value (qty or boolean 0/1)
        if (is_numeric($value)) {
            return (int) $value > 0 ? $inStock : $outOfStock;
        }

        // Map various string formats to normalized output
        $map = [
            '1' => $inStock,
            '0' => $outOfStock,
            'in_stock' => $inStock,
            'out_of_stock' => $outOfStock,
            'in stock' => $inStock,
            'out of stock' => $outOfStock,
            'available' => $inStock,
            'unavailable' => $outOfStock,
            'yes' => $inStock,
            'no' => $outOfStock,
        ];

        $normalized = strtolower(trim((string) $value));
        return $map[$normalized] ?? $outOfStock;
    }
}
