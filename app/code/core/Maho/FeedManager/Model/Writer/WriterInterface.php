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
 * Feed Writer Interface
 *
 * Defines the contract for writing feed data to various formats
 */
interface Maho_FeedManager_Model_Writer_WriterInterface
{
    /**
     * Get writer format code
     */
    public function getFormat(): string;

    /**
     * Open the writer for output
     *
     * @param string $filePath Path to output file
     * @param Maho_FeedManager_Model_Platform_AdapterInterface|null $platform Platform adapter for structure
     */
    public function open(string $filePath, ?Maho_FeedManager_Model_Platform_AdapterInterface $platform = null): void;

    /**
     * Write a single product entry
     *
     * @param array<string, mixed> $productData Mapped product data
     */
    public function writeProduct(array $productData): void;

    /**
     * Resume writing to an existing file in append mode
     *
     * Used by batch generation to reopen a file across HTTP requests.
     * Must be called instead of open() when continuing a previously started file.
     *
     * @param string $filePath Path to existing output file
     * @param Maho_FeedManager_Model_Platform_AdapterInterface|null $platform Platform adapter (needed by XML writer)
     */
    public function resume(string $filePath, ?Maho_FeedManager_Model_Platform_AdapterInterface $platform = null): void;

    /**
     * Pause writing by closing the file handle without writing footer/closing tags
     *
     * Used by batch generation to release the file handle between HTTP requests.
     * The file can be resumed later with resume().
     */
    public function pause(): void;

    /**
     * Close the writer and finalize output
     */
    public function close(): void;

    /**
     * Get the file extension for this format
     */
    public function getFileExtension(): string;

    /**
     * Get the MIME type for this format
     */
    public function getMimeType(): string;
}
