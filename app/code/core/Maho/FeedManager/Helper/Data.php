<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_ENABLED = 'feedmanager/general/enabled';
    public const XML_PATH_OUTPUT_DIRECTORY = 'feedmanager/general/output_directory';
    public const XML_PATH_BATCH_SIZE = 'feedmanager/general/batch_size';
    protected $_moduleName = 'Maho_FeedManager';

    /**
     * Check if module is enabled
     */
    public function isEnabled(): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED);
    }

    /**
     * Get output directory path (absolute)
     */
    public function getOutputDirectory(): string
    {
        $relative = Mage::getStoreConfig(self::XML_PATH_OUTPUT_DIRECTORY) ?: 'feeds';
        $path = Mage::getBaseDir('media') . DS . $relative;

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return $path;
    }

    /**
     * Get output directory relative to media
     */
    public function getOutputDirectoryRelative(): string
    {
        return Mage::getStoreConfig(self::XML_PATH_OUTPUT_DIRECTORY) ?: 'feeds';
    }

    /**
     * Get batch size for processing
     */
    public function getBatchSize(): int
    {
        return (int) (Mage::getStoreConfig(self::XML_PATH_BATCH_SIZE) ?: 1000);
    }

    /**
     * Get available platform options for dropdown
     */
    public function getPlatformOptions(): array
    {
        return [
            ''                       => $this->__('-- Select Platform --'),
            'google'                 => $this->__('Google Shopping'),
            'google_local_inventory' => $this->__('Google Local Inventory'),
            'facebook'               => $this->__('Facebook / Meta'),
            'bing'                   => $this->__('Bing Shopping'),
            'pinterest'              => $this->__('Pinterest'),
            'idealo'                 => $this->__('Idealo'),
            'trovaprezzi'            => $this->__('Trovaprezzi'),
            'openai'                 => $this->__('OpenAI Commerce'),
            'custom'                 => $this->__('Custom'),
        ];
    }

    /**
     * Get file format options for dropdown
     */
    public function getFileFormatOptions(): array
    {
        return [
            'xml'   => 'XML',
            'csv'   => 'CSV',
            'json'  => 'JSON',
            'jsonl' => 'JSONL (JSON Lines)',
        ];
    }

    /**
     * Get file formats supported by a platform
     */
    public function getPlatformFormats(string $platform): array
    {
        return Maho_FeedManager_Model_Platform::getPlatformFormats($platform);
    }

    /**
     * Get configurable product mode options
     */
    public function getConfigurableModeOptions(): array
    {
        return Maho_FeedManager_Model_Feed::getConfigurableModeOptions();
    }

    /**
     * Format file size for display
     */
    public function formatFileSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get feed public URL
     */
    public function getFeedUrl(Maho_FeedManager_Model_Feed $feed): string
    {
        $baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
        $extension = $feed->getFileFormat();
        if ($feed->getGzipCompression()) {
            $extension .= '.gz';
        }
        return $baseUrl . $this->getOutputDirectoryRelative() . '/' .
               $feed->getFilename() . '.' . $extension;
    }
}
