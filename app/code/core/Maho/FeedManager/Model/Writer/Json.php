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
 * JSON Feed Writer
 *
 * Writes product feed data in JSON format (streaming)
 */
class Maho_FeedManager_Model_Writer_Json implements Maho_FeedManager_Model_Writer_WriterInterface
{
    /** @var resource|null */
    protected $_handle = null;
    protected bool $_firstProduct = true;
    protected bool $_prettyPrint = false;
    protected string $_rootKey = 'products';
    protected array $_structure = [];

    #[\Override]
    public function getFormat(): string
    {
        return 'json';
    }

    #[\Override]
    public function getFileExtension(): string
    {
        return 'json';
    }

    #[\Override]
    public function getMimeType(): string
    {
        return 'application/json';
    }

    #[\Override]
    public function open(string $filePath, ?Maho_FeedManager_Model_Platform_AdapterInterface $platform = null): void
    {
        $this->_handle = fopen($filePath, 'w');

        if ($this->_handle === false) {
            throw new RuntimeException("Cannot open file for writing: {$filePath}");
        }

        // Start JSON with root key
        fwrite($this->_handle, '{"' . $this->_rootKey . '":[' . ($this->_prettyPrint ? PHP_EOL : ''));
        $this->_firstProduct = true;
    }

    #[\Override]
    public function writeProduct(array $productData): void
    {
        if (!$this->_handle) {
            throw new RuntimeException('Writer not opened');
        }

        // Add comma separator if not first product
        if (!$this->_firstProduct) {
            fwrite($this->_handle, ',');
            if ($this->_prettyPrint) {
                fwrite($this->_handle, PHP_EOL);
            }
        }

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
        if ($this->_prettyPrint) {
            $flags |= JSON_PRETTY_PRINT;
        }

        fwrite($this->_handle, json_encode($productData, $flags));
        $this->_firstProduct = false;
    }

    #[\Override]
    public function close(): void
    {
        if (!$this->_handle) {
            return;
        }

        // Close JSON array
        fwrite($this->_handle, ($this->_prettyPrint ? PHP_EOL : '') . ']}' . PHP_EOL);
        fclose($this->_handle);
        $this->_handle = null;
    }

    /**
     * Enable pretty printing
     */
    public function setPrettyPrint(bool $enabled): self
    {
        $this->_prettyPrint = $enabled;
        return $this;
    }

    /**
     * Set the root key for the JSON array
     */
    public function setRootKey(string $key): self
    {
        $this->_rootKey = $key ?: 'products';
        return $this;
    }

    /**
     * Set structure definition from JSON builder
     */
    public function setStructure(array $structure): self
    {
        $this->_structure = $structure;
        return $this;
    }

    /**
     * Get structure definition
     */
    public function getStructure(): array
    {
        return $this->_structure;
    }

    /**
     * Configure writer from feed model
     */
    public function configureFromFeed(Maho_FeedManager_Model_Feed $feed): self
    {
        $rootKey = $feed->getJsonRootKey();
        if ($rootKey) {
            $this->setRootKey($rootKey);
        }

        // Load structure definition if available
        $jsonStructure = $feed->getJsonStructure();
        if ($jsonStructure) {
            $structure = Mage::helper('core')->jsonDecode($jsonStructure);
            if (is_array($structure) && !empty($structure)) {
                $this->setStructure($structure);
            }
        }

        return $this;
    }
}
