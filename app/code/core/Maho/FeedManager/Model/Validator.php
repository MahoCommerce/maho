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
 * Feed Validator
 *
 * Validates generated feed files for format correctness
 */
class Maho_FeedManager_Model_Validator
{
    protected array $_errors = [];
    protected array $_warnings = [];

    /**
     * Validate a feed file
     *
     * @param string $filePath Path to the feed file
     * @param string $format File format (xml, csv, json)
     * @return bool True if valid
     */
    public function validate(string $filePath, string $format): bool
    {
        $this->_errors = [];
        $this->_warnings = [];

        if (!file_exists($filePath)) {
            $this->_errors[] = "File not found: {$filePath}";
            return false;
        }

        $fileSize = filesize($filePath);
        if ($fileSize === 0) {
            $this->_errors[] = 'File is empty';
            return false;
        }

        return match ($format) {
            'xml' => $this->_validateXml($filePath),
            'csv' => $this->_validateCsv($filePath),
            'json' => $this->_validateJson($filePath),
            default => true,
        };
    }

    /**
     * Validate XML file structure
     */
    protected function _validateXml(string $filePath): bool
    {
        // Use libxml internal errors
        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        // Try to load the XML
        $xml = new DOMDocument();
        $xml->preserveWhiteSpace = false;

        // Load with options for large files
        $loaded = $xml->load($filePath, LIBXML_NONET | LIBXML_NOWARNING);

        if (!$loaded) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                $this->_errors[] = $this->_formatLibxmlError($error);
            }
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);
            return false;
        }

        // Validate structure
        $errors = libxml_get_errors();
        foreach ($errors as $error) {
            if ($error->level === LIBXML_ERR_WARNING) {
                $this->_warnings[] = $this->_formatLibxmlError($error);
            } else {
                $this->_errors[] = $this->_formatLibxmlError($error);
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        // Check for root element
        if (!$xml->documentElement) {
            $this->_errors[] = 'XML has no root element';
            return false;
        }

        // Check for items
        $itemCount = $this->_countXmlItems($xml);
        if ($itemCount === 0) {
            $this->_warnings[] = 'XML contains no product items';
        }

        return empty($this->_errors);
    }

    /**
     * Count items in XML document
     */
    protected function _countXmlItems(DOMDocument $xml): int
    {
        // Try common item element names
        $itemNames = ['item', 'entry', 'product'];

        foreach ($itemNames as $itemName) {
            $items = $xml->getElementsByTagName($itemName);
            if ($items->length > 0) {
                return $items->length;
            }
        }

        return 0;
    }

    /**
     * Format libxml error for display
     */
    protected function _formatLibxmlError(\LibXMLError $error): string
    {
        $type = match ($error->level) {
            LIBXML_ERR_WARNING => 'Warning',
            LIBXML_ERR_ERROR => 'Error',
            LIBXML_ERR_FATAL => 'Fatal',
            default => 'Unknown',
        };

        return "{$type} at line {$error->line}: " . trim($error->message);
    }

    /**
     * Validate CSV file structure
     */
    protected function _validateCsv(string $filePath): bool
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->_errors[] = 'Cannot open file for reading';
            return false;
        }

        $lineNumber = 0;
        $headerCount = 0;
        $validLines = 0;
        $invalidLines = [];

        // Detect delimiter (tab or comma)
        $firstLine = fgets($handle);
        rewind($handle);

        $delimiter = str_contains($firstLine, "\t") ? "\t" : ',';

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;

            if ($lineNumber === 1) {
                // Header row
                $headerCount = count($row);
                if ($headerCount === 0) {
                    $this->_errors[] = 'CSV has no headers';
                    fclose($handle);
                    return false;
                }
                continue;
            }

            // Validate column count
            $colCount = count($row);
            if ($colCount !== $headerCount) {
                $invalidLines[] = $lineNumber;
                if (count($invalidLines) <= 5) {
                    $this->_warnings[] = "Line {$lineNumber}: Expected {$headerCount} columns, found {$colCount}";
                }
            } else {
                $validLines++;
            }
        }

        fclose($handle);

        // Report summary of invalid lines
        if (count($invalidLines) > 5) {
            $this->_warnings[] = '... and ' . (count($invalidLines) - 5) . ' more lines with column count mismatch';
        }

        if ($validLines === 0 && $lineNumber > 1) {
            $this->_errors[] = 'No valid data rows found';
            return false;
        }

        if ($lineNumber === 1) {
            $this->_warnings[] = 'CSV contains only headers, no data rows';
        }

        return empty($this->_errors);
    }

    /**
     * Validate JSON file structure
     */
    protected function _validateJson(string $filePath): bool
    {
        // Read file content
        $content = file_get_contents($filePath);
        if ($content === false) {
            $this->_errors[] = 'Cannot read file';
            return false;
        }

        // Try to decode
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->_errors[] = 'Invalid JSON: ' . json_last_error_msg();

            // Try to find approximate error location
            $this->_findJsonErrorLocation($content);

            return false;
        }

        // Check structure
        if (!is_array($data)) {
            $this->_errors[] = 'JSON root must be an array or object';
            return false;
        }

        // Check for products array
        $products = $data['products'] ?? $data;
        if (!is_array($products)) {
            $this->_warnings[] = 'Could not find products array in JSON';
        } elseif (empty($products)) {
            $this->_warnings[] = 'JSON contains no products';
        }

        return empty($this->_errors);
    }

    /**
     * Try to find approximate location of JSON error
     */
    protected function _findJsonErrorLocation(string $content): void
    {
        // Check for common issues
        $lines = explode("\n", $content);
        $lineNumber = 0;

        foreach ($lines as $i => $line) {
            $lineNumber = $i + 1;

            // Check for unescaped control characters
            if (preg_match('/[\x00-\x1F]/', $line)) {
                $this->_warnings[] = "Possible invalid character at line {$lineNumber}";
                break;
            }

            // Check for trailing commas (common error)
            if (preg_match('/,\s*[\]}]/', $line)) {
                $this->_warnings[] = "Possible trailing comma at line {$lineNumber}";
            }
        }
    }

    /**
     * Get validation errors
     */
    public function getErrors(): array
    {
        return $this->_errors;
    }

    /**
     * Get validation warnings
     */
    public function getWarnings(): array
    {
        return $this->_warnings;
    }

    /**
     * Check if validation passed (no errors, warnings allowed)
     */
    public function isValid(): bool
    {
        return empty($this->_errors);
    }

    /**
     * Get all messages (errors + warnings)
     */
    public function getAllMessages(): array
    {
        return array_merge(
            array_map(fn($e) => "[ERROR] {$e}", $this->_errors),
            array_map(fn($w) => "[WARNING] {$w}", $this->_warnings),
        );
    }
}
