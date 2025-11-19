<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_ImportExport_Model_Import_Adapter_Array extends Mage_ImportExport_Model_Import_Adapter_Abstract
{
    /**
     * Import data array
     *
     * @var array
     */
    protected $_data = [];

    /**
     * Data pointer
     *
     * @var int
     */
    protected $_position = -1;

    /**
     * Total number of data rows (excluding header)
     *
     * @var int
     */
    protected $_count = 0;

    /**
     * Handle array source data during construction.
     *
     * @param array $data Import data array
     * @return $this
     * @throws Mage_Core_Exception
     */
    #[\Override]
    protected function _handleArraySource($data): self
    {
        if (empty($data)) {
            Mage::throwException(Mage::helper('importexport')->__('Source data array cannot be empty'));
        }

        // Store data and initialize
        $this->_data = array_values($data); // Ensure numeric indices
        $this->_count = count($this->_data);

        return $this;
    }

    /**
     * Initialize adapter by extracting column names from first row
     *
     * @return $this
     * @throws Mage_Core_Exception
     */
    #[\Override]
    protected function _init(): self
    {
        if (empty($this->_data)) {
            Mage::throwException(Mage::helper('importexport')->__('No data provided for import'));
        }

        // First row should contain column names
        $firstRow = reset($this->_data);

        if (!is_array($firstRow)) {
            Mage::throwException(Mage::helper('importexport')->__('Each data row must be an array'));
        }

        // Extract column names - use keys from first row
        $this->_colNames = array_keys($firstRow);

        // If keys are numeric, treat first row as header
        if (is_numeric($this->_colNames[0])) {
            $this->_colNames = array_values($firstRow);
            // Remove header row from data
            array_shift($this->_data);
            $this->_count = count($this->_data);
        }

        // Check for duplicate column names
        if (count($this->_colNames) !== count(array_unique($this->_colNames))) {
            Mage::throwException(Mage::helper('importexport')->__('Column names have duplicates'));
        }

        // Validate we have data after header processing
        if ($this->_count === 0) {
            Mage::throwException(Mage::helper('importexport')->__('No data rows found after header processing'));
        }

        // Start at first data row
        $this->rewind();

        return $this;
    }

    /**
     * Move forward to next element
     */
    #[\ReturnTypeWillChange]
    #[\Override]
    public function next(): void
    {
        $this->_position++;

        if ($this->_position < $this->_count) {
            $row = $this->_data[$this->_position];

            // Handle associative arrays
            if (is_array($row) && !is_numeric(key($row))) {
                $this->_currentRow = array_values($row);
            } else {
                // Handle indexed arrays or convert single values to array
                $this->_currentRow = is_array($row) ? array_values($row) : [$row];
            }

            $this->_currentKey = $this->_position;
        } else {
            $this->_currentRow = null;
            $this->_currentKey = null;
        }
    }

    /**
     * Rewind the Iterator to the first element
     */
    #[\ReturnTypeWillChange]
    #[\Override]
    public function rewind(): void
    {
        $this->_position = -1;
        $this->next();
    }

    /**
     * Seeks to a position
     *
     * @param int $position The position to seek to
     * @throws OutOfBoundsException
     */
    #[\ReturnTypeWillChange]
    #[\Override]
    public function seek($position): void
    {
        if ($position < 0 || $position >= $this->_count) {
            throw new OutOfBoundsException(
                Mage::helper('importexport')->__('Invalid seek position: %d', $position),
            );
        }

        $this->_position = $position - 1;
        $this->next();
    }

    /**
     * Checks if current position is valid
     */
    #[\ReturnTypeWillChange]
    #[\Override]
    public function valid(): bool
    {
        return $this->_position >= 0 &&
               $this->_position < $this->_count &&
               !empty($this->_currentRow);
    }

    /**
     * Get the total number of data rows
     */
    public function getRowCount(): int
    {
        return $this->_count;
    }

    /**
     * Validate source data structure
     *
     * @return $this
     * @throws Mage_Core_Exception
     */
    #[\Override]
    public function validateSource(): self
    {
        // Validate all rows have consistent structure
        $expectedColumnCount = count($this->_colNames);

        foreach ($this->_data as $index => $row) {
            if (!is_array($row)) {
                Mage::throwException(
                    Mage::helper('importexport')->__('Row %d is not an array', $index + 1),
                );
            }

            // For associative arrays, check key consistency
            if (!is_numeric(key($row))) {
                $rowKeys = array_keys($row);
                $missingKeys = array_diff($this->_colNames, $rowKeys);

                if (!empty($missingKeys)) {
                    Mage::log(
                        Mage::helper('importexport')->__(
                            'Row %d missing columns: %s',
                            $index + 1,
                            implode(', ', $missingKeys),
                        ),
                        Mage::LOG_WARNING,
                    );
                }
            }
        }

        return $this;
    }

    /**
     * Get source description
     */
    #[\Override]
    public function getSource(): string
    {
        return $this->_source;
    }
}
