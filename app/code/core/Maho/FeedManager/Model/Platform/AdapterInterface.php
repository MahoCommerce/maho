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
 * Platform Adapter Interface
 *
 * Defines the contract for platform-specific feed generation
 */
interface Maho_FeedManager_Model_Platform_AdapterInterface
{
    /**
     * Get platform code
     */
    public function getCode(): string;

    /**
     * Get platform display name
     */
    public function getName(): string;

    /**
     * Get supported file formats
     *
     * @return string[]
     */
    public function getSupportedFormats(): array;

    /**
     * Get default file format
     */
    public function getDefaultFormat(): string;

    /**
     * Get required feed attributes for this platform
     *
     * @return array<string, array{label: string, required: bool, description?: string}>
     */
    public function getRequiredAttributes(): array;

    /**
     * Get optional/recommended feed attributes
     *
     * @return array<string, array{label: string, required: bool, description?: string}>
     */
    public function getOptionalAttributes(): array;

    /**
     * Get all available attributes (required + optional)
     *
     * @return array<string, array{label: string, required: bool, description?: string}>
     */
    public function getAllAttributes(): array;

    /**
     * Get default attribute mappings
     *
     * @return array<string, array{source_type: string, source_value: string}>
     */
    public function getDefaultMappings(): array;

    /**
     * Get feed root element name (for XML)
     */
    public function getRootElement(): string;

    /**
     * Get feed item element name (for XML)
     */
    public function getItemElement(): string;

    /**
     * Get XML namespaces
     *
     * @return array<string, string>
     */
    public function getNamespaces(): array;

    /**
     * Get attributes that should use namespace prefix in XML output
     *
     * @return string[]
     */
    public function getNamespacedAttributes(): array;

    /**
     * Transform product data for this platform
     *
     * @param array<string, mixed> $productData Raw product data
     * @return array<string, mixed> Transformed data
     */
    public function transformProductData(array $productData): array;

    /**
     * Validate product data before including in feed
     *
     * @param array<string, mixed> $productData
     * @return array<string> Validation errors (empty if valid)
     */
    public function validateProductData(array $productData): array;

    /**
     * Get category taxonomy file path
     */
    public function getTaxonomyFilePath(): ?string;

    /**
     * Check if platform supports category mapping
     */
    public function supportsCategoryMapping(): bool;

    /**
     * Search taxonomy for matching categories
     *
     * @param string $query Search query
     * @param int $limit Maximum number of results
     * @return array<int, array{id: string, path: string}> Array of matching categories
     */
    public function searchTaxonomy(string $query, int $limit = 10): array;
}
