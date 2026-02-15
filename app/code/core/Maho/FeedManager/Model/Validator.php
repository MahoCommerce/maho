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
            'jsonl' => $this->_validateJsonl($filePath),
            default => true,
        };
    }

    /**
     * Validate XML file structure using streaming XMLReader to avoid OOM on large feeds
     */
    protected function _validateXml(string $filePath): bool
    {
        $previousUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $reader = new XMLReader();
        if (!$reader->open($filePath, flags: LIBXML_NONET | LIBXML_NOWARNING)) {
            $this->_errors[] = 'Cannot open XML file for reading';
            libxml_use_internal_errors($previousUseErrors);
            return false;
        }

        $hasRootElement = false;
        $itemCount = 0;
        $itemNames = ['item', 'entry', 'product'];

        while (@$reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT) {
                if (!$hasRootElement) {
                    $hasRootElement = true;
                }
                if (in_array($reader->localName, $itemNames, true)) {
                    $itemCount++;
                }
            }
        }

        $errors = libxml_get_errors();
        foreach ($errors as $error) {
            if ($error->level === LIBXML_ERR_WARNING) {
                $this->_warnings[] = $this->_formatLibxmlError($error);
            } else {
                $this->_errors[] = $this->_formatLibxmlError($error);
            }
        }

        $reader->close();
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        if (!$hasRootElement) {
            $this->_errors[] = 'XML has no root element';
            return false;
        }

        if ($itemCount === 0) {
            $this->_warnings[] = 'XML contains no product items';
        }

        return empty($this->_errors);
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
     *
     * For small files (< 2MB), performs full validation.
     * For large files, validates only start/end structure to avoid OOM.
     */
    protected function _validateJson(string $filePath): bool
    {
        $fileSize = filesize($filePath);

        if ($fileSize < 2 * 1024 * 1024) {
            return $this->_validateJsonFull($filePath);
        }

        return $this->_validateJsonStructure($filePath, $fileSize);
    }

    /**
     * Full JSON validation for small files
     */
    protected function _validateJsonFull(string $filePath): bool
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            $this->_errors[] = 'Cannot read file';
            return false;
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->_errors[] = 'Invalid JSON: ' . json_last_error_msg();
            $this->_findJsonErrorLocation($content);
            return false;
        }

        if (!is_array($data)) {
            $this->_errors[] = 'JSON root must be an array or object';
            return false;
        }

        $products = $data['products'] ?? $data;
        if (!is_array($products)) {
            $this->_warnings[] = 'Could not find products array in JSON';
        } elseif (empty($products)) {
            $this->_warnings[] = 'JSON contains no products';
        }

        return empty($this->_errors);
    }

    /**
     * Streaming structural validation for large JSON files
     *
     * Reads only the first and last few KB to verify the file
     * has valid JSON opening/closing structure without loading
     * the entire file into memory.
     */
    protected function _validateJsonStructure(string $filePath, int $fileSize): bool
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->_errors[] = 'Cannot open file for reading';
            return false;
        }

        // Read first 8KB to check opening structure
        $head = ltrim((string) fread($handle, 8192));

        if ($head === '') {
            fclose($handle);
            $this->_errors[] = 'File appears empty or contains only whitespace';
            return false;
        }

        $firstChar = $head[0];
        if ($firstChar !== '{' && $firstChar !== '[') {
            fclose($handle);
            $this->_errors[] = 'Invalid JSON: must start with { or [';
            return false;
        }

        // Read last 8KB to check closing structure
        fseek($handle, max(0, $fileSize - 8192));
        $tail = rtrim((string) fread($handle, 8192));
        fclose($handle);

        if ($tail === '') {
            $this->_errors[] = 'File appears truncated';
            return false;
        }

        $lastChar = $tail[strlen($tail) - 1];
        $expectedClose = ($firstChar === '{') ? '}' : ']';

        if ($lastChar !== $expectedClose) {
            $this->_errors[] = sprintf(
                "Invalid JSON structure: opens with '%s' but does not end with '%s'",
                $firstChar,
                $expectedClose,
            );
            return false;
        }

        $sizeMb = round($fileSize / (1024 * 1024), 1);
        $this->_warnings[] = "Large file ({$sizeMb} MB) — only start/end structure validated, full JSON parsing skipped";

        return true;
    }

    /**
     * Validate JSONL (JSON Lines) file structure
     */
    protected function _validateJsonl(string $filePath): bool
    {
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            $this->_errors[] = 'Cannot open file for reading';
            return false;
        }

        $lineNumber = 0;
        $validLines = 0;
        $invalidLines = [];

        while (($line = fgets($handle)) !== false) {
            $lineNumber++;
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (!json_validate($line)) {
                $invalidLines[] = $lineNumber;
                if (count($invalidLines) <= 5) {
                    $this->_warnings[] = "Line {$lineNumber}: Invalid JSON — " . json_last_error_msg();
                }
            } else {
                $validLines++;
            }
        }

        fclose($handle);

        if (count($invalidLines) > 5) {
            $this->_warnings[] = '... and ' . (count($invalidLines) - 5) . ' more lines with invalid JSON';
        }

        if ($validLines === 0) {
            $this->_errors[] = 'No valid JSON lines found';
            return false;
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
