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
 * XML Feed Writer
 *
 * Writes product feed data in XML format with namespace support
 */
class Maho_FeedManager_Model_Writer_Xml implements Maho_FeedManager_Model_Writer_WriterInterface
{
    /** @var resource|null */
    protected $_handle = null;
    protected ?Maho_FeedManager_Model_Platform_AdapterInterface $_platform = null;
    protected string $_itemElement = 'item';
    protected array $_namespaces = [];
    protected array $_namespacedAttributes = [];

    #[\Override]
    public function getFormat(): string
    {
        return 'xml';
    }

    #[\Override]
    public function getFileExtension(): string
    {
        return 'xml';
    }

    #[\Override]
    public function getMimeType(): string
    {
        return 'application/xml';
    }

    #[\Override]
    public function open(string $filePath, ?Maho_FeedManager_Model_Platform_AdapterInterface $platform = null): void
    {
        $this->_platform = $platform;
        $this->_handle = fopen($filePath, 'w');

        if ($this->_handle === false) {
            throw new RuntimeException("Cannot open file for writing: {$filePath}");
        }

        // Get platform-specific settings
        $rootElement = $platform ? $platform->getRootElement() : 'feed';
        $this->_itemElement = $platform ? $platform->getItemElement() : 'item';
        $this->_namespaces = $platform ? $platform->getNamespaces() : [];

        // Get namespaced attributes if platform supports it
        if ($platform && method_exists($platform, 'getNamespacedAttributes')) {
            $this->_namespacedAttributes = $platform->getNamespacedAttributes();
        }

        // Write XML declaration
        fwrite($this->_handle, '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL);

        // Write root element with namespaces
        $nsAttrs = '';
        foreach ($this->_namespaces as $prefix => $uri) {
            $nsAttrs .= " {$prefix}=\"{$uri}\"";
        }

        fwrite($this->_handle, "<{$rootElement}{$nsAttrs}>" . PHP_EOL);
    }

    #[\Override]
    public function writeProduct(array $productData): void
    {
        if (!$this->_handle) {
            throw new RuntimeException('Writer not opened');
        }

        $xml = $this->_buildItemXml($productData);
        fwrite($this->_handle, $xml . PHP_EOL);
    }

    #[\Override]
    public function close(): void
    {
        if (!$this->_handle) {
            return;
        }

        $rootElement = $this->_platform ? $this->_platform->getRootElement() : 'feed';
        fwrite($this->_handle, "</{$rootElement}>" . PHP_EOL);
        fclose($this->_handle);
        $this->_handle = null;
    }

    /**
     * Build XML for a single item
     */
    protected function _buildItemXml(array $data): string
    {
        $xml = "  <{$this->_itemElement}>" . PHP_EOL;

        foreach ($data as $key => $value) {
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                continue;
            }

            $elementName = $this->_getElementName($key);

            if (is_array($value)) {
                // Handle arrays (like additional images)
                foreach ($value as $item) {
                    $xml .= "    <{$elementName}>" . $this->_escapeXml((string) $item) . "</{$elementName}>" . PHP_EOL;
                }
            } else {
                $xml .= "    <{$elementName}>" . $this->_escapeXml((string) $value) . "</{$elementName}>" . PHP_EOL;
            }
        }

        $xml .= "  </{$this->_itemElement}>";

        return $xml;
    }

    /**
     * Get element name with namespace prefix if needed
     */
    protected function _getElementName(string $key): string
    {
        // Check if this attribute needs g: prefix (Google/Facebook)
        if (in_array($key, $this->_namespacedAttributes) && isset($this->_namespaces['xmlns:g'])) {
            return 'g:' . $key;
        }

        return $key;
    }

    /**
     * Escape XML special characters
     */
    protected function _escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
