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
 * CSV Feed Writer
 *
 * Writes product feed data in CSV format
 */
class Maho_FeedManager_Model_Writer_Csv implements Maho_FeedManager_Model_Writer_WriterInterface
{
    /** @var resource|null */
    protected $_handle = null;
    protected array $_headers = [];
    protected bool $_headerWritten = false;
    protected string $_delimiter = ',';
    protected string $_enclosure = '"';
    protected bool $_includeHeader = true;
    protected array $_columns = [];

    #[\Override]
    public function getFormat(): string
    {
        return 'csv';
    }

    #[\Override]
    public function getFileExtension(): string
    {
        return 'csv';
    }

    #[\Override]
    public function getMimeType(): string
    {
        return 'text/csv';
    }

    #[\Override]
    public function open(string $filePath, ?Maho_FeedManager_Model_Platform_AdapterInterface $platform = null): void
    {
        $this->_handle = fopen($filePath, 'w');

        if ($this->_handle === false) {
            throw new RuntimeException("Cannot open file for writing: {$filePath}");
        }

        // Pre-define headers from platform if available AND no custom headers already set
        if ($platform && empty($this->_headers)) {
            $this->_headers = array_keys($platform->getAllAttributes());
        }

        $this->_headerWritten = false;
    }

    #[\Override]
    public function writeProduct(array $productData): void
    {
        if (!$this->_handle) {
            throw new RuntimeException('Writer not opened');
        }

        // Write headers on first product (if enabled)
        if (!$this->_headerWritten) {
            // If no predefined headers, use keys from first product
            if (empty($this->_headers)) {
                $this->_headers = array_keys($productData);
            }
            if ($this->_includeHeader) {
                $this->_writeRow($this->_headers);
            }
            $this->_headerWritten = true;
        }

        // Build row in header order
        $row = [];
        foreach ($this->_headers as $header) {
            $value = $productData[$header] ?? '';

            // Handle arrays (convert to comma-separated)
            if (is_array($value)) {
                $value = implode(',', $value);
            }

            $row[] = (string) $value;
        }

        $this->_writeRow($row);
    }

    #[\Override]
    public function close(): void
    {
        if ($this->_handle) {
            fclose($this->_handle);
            $this->_handle = null;
        }
    }

    /**
     * Write a row to the CSV file
     */
    protected function _writeRow(array $row): void
    {
        fputcsv($this->_handle, $row, $this->_delimiter, $this->_enclosure);
    }

    /**
     * Set delimiter (tab or comma)
     */
    public function setDelimiter(string $delimiter): self
    {
        $this->_delimiter = $delimiter;
        return $this;
    }

    /**
     * Set enclosure character
     */
    public function setEnclosure(string $enclosure): self
    {
        $this->_enclosure = $enclosure;
        return $this;
    }

    /**
     * Set whether to include header row
     */
    public function setIncludeHeader(bool $include): self
    {
        $this->_includeHeader = $include;
        return $this;
    }

    /**
     * Set headers explicitly
     */
    public function setHeaders(array $headers): self
    {
        $this->_headers = $headers;
        return $this;
    }

    /**
     * Set column definitions from CSV builder
     */
    public function setColumns(array $columns): self
    {
        $this->_columns = $columns;
        // Extract headers from column definitions
        $this->_headers = array_column($columns, 'name');
        return $this;
    }

    /**
     * Get column definitions
     */
    public function getColumns(): array
    {
        return $this->_columns;
    }

    /**
     * Configure writer from feed model
     */
    public function configureFromFeed(Maho_FeedManager_Model_Feed $feed): self
    {
        $delimiter = $feed->getCsvDelimiter();
        if ($delimiter !== null && $delimiter !== '') {
            $this->setDelimiter($delimiter === '&#9;' ? "\t" : $delimiter);
        }

        $enclosure = $feed->getCsvEnclosure();
        if ($enclosure !== null) {
            $this->setEnclosure($enclosure === '&quot;' ? '"' : ($enclosure === '&#39;' ? "'" : $enclosure));
        }

        $includeHeader = $feed->getCsvIncludeHeader();
        $this->setIncludeHeader($includeHeader === null || $includeHeader);

        // Load column definitions if available
        $csvColumns = $feed->getCsvColumns();
        if ($csvColumns) {
            $columns = Mage::helper('core')->jsonDecode($csvColumns);
            if (is_array($columns) && !empty($columns)) {
                $this->setColumns($columns);
            }
        }

        return $this;
    }
}
