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
 * Transformer Interface
 *
 * Defines the contract for data transformation operations
 */
interface Maho_FeedManager_Model_Transformer_TransformerInterface
{
    /**
     * Get transformer code
     */
    public function getCode(): string;

    /**
     * Get transformer display name
     */
    public function getName(): string;

    /**
     * Get transformer description
     */
    public function getDescription(): string;

    /**
     * Transform a value
     *
     * @param mixed $value The input value to transform
     * @param array<string, mixed> $options Transformer-specific options
     * @param array<string, mixed> $productData Full product data for context
     * @return mixed Transformed value
     */
    public function transform(mixed $value, array $options = [], array $productData = []): mixed;

    /**
     * Get option definitions for admin form
     *
     * @return array<string, array{label: string, type: string, required?: bool, note?: string}>
     */
    public function getOptionDefinitions(): array;

    /**
     * Validate options
     *
     * @param array<string, mixed> $options
     * @return array<string> Validation errors (empty if valid)
     */
    public function validateOptions(array $options): array;
}
