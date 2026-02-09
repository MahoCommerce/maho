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

        fwrite($this->_handle, $json . "\n");
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
