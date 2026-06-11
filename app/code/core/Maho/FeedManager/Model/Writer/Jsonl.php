<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

/**
 * JSONL (JSON Lines) Feed Writer
 *
 * Writes one JSON object per line, with no wrapping structure.
 */
class Maho_FeedManager_Model_Writer_Jsonl implements Maho_FeedManager_Model_Writer_WriterInterface
{
    /** @var resource|null */
    protected $_handle = null;

    #[\Override]
    public function getFormat(): string
    {
        return 'jsonl';
    }

    #[\Override]
    public function getFileExtension(): string
    {
        return 'jsonl';
    }

    #[\Override]
    public function getMimeType(): string
    {
        return 'application/x-ndjson';
    }

    #[\Override]
    public function open(string $filePath, ?Maho_FeedManager_Model_Platform_AdapterInterface $platform = null): void
    {
        $this->_handle = fopen($filePath, 'w');

        if ($this->_handle === false) {
            throw new RuntimeException("Cannot open file for writing: {$filePath}");
        }
    }

    #[\Override]
    public function writeProduct(array $productData): void
    {
        if (!$this->_handle) {
            throw new RuntimeException('Writer not opened');
        }

        $json = json_encode($productData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            Mage::log('FeedManager: json_encode failed: ' . json_last_error_msg(), Mage::LOG_WARNING);
            return;
        }

        fwrite($this->_handle, $json . "\n");
    }

    #[\Override]
    public function resume(string $filePath, ?Maho_FeedManager_Model_Platform_AdapterInterface $platform = null): void
    {
        $this->_handle = fopen($filePath, 'a');

        if ($this->_handle === false) {
            throw new RuntimeException("Cannot open file for appending: {$filePath}");
        }
    }

    #[\Override]
    public function pause(): void
    {
        if ($this->_handle) {
            fclose($this->_handle);
            $this->_handle = null;
        }
    }

    #[\Override]
    public function close(): void
    {
        if (!$this->_handle) {
            return;
        }

        fclose($this->_handle);
        $this->_handle = null;
    }
}
