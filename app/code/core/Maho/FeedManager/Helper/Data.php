<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_ENABLED = 'feedmanager/general/enabled';
    public const XML_PATH_OUTPUT_DIRECTORY = 'feedmanager/general/output_directory';
    public const XML_PATH_BATCH_SIZE = 'feedmanager/general/batch_size';
    #[\Override]
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
     *
     * @deprecated since 26.11 the feed files are on the media mount, use getOutputStorageDirectory()
     */
    public function getOutputDirectory(): string
    {
        $relative = Mage::getStoreConfig(self::XML_PATH_OUTPUT_DIRECTORY) ?: 'feeds';
        $path = Mage::getBaseDir('media') . DS . $relative;

        if (!is_dir($path) && !mkdir($path, 0755, true)) {
            throw new RuntimeException("Feed output directory could not be created: {$path}");
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
     * The mount that holds the public feed files.
     */
    public function getOutputMount(): \Maho\Storage\Mount
    {
        return Mage::getStorage('media');
    }

    /**
     * The output directory as a path on the media mount. Null when the configured directory leaves the mount.
     */
    public function getOutputStorageDirectory(): ?string
    {
        return \Maho\Io::getPathWithinMount($this->getOutputMount(), '', $this->getOutputDirectoryRelative());
    }

    /**
     * Create an empty local temp file and return its path. The caller deletes it.
     */
    public function createTempFile(): string
    {
        $directory = Mage::getConfig()->getVarDir('tmp') ?: sys_get_temp_dir();
        $path = tempnam($directory, 'feed_');
        if ($path === false) {
            throw new RuntimeException("Cannot create a temp file in {$directory}");
        }
        return $path;
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
        return Mage::helper('core')->formatFileSize($bytes);
    }

    /**
     * Get feed public URL. Empty when the feed has no valid path or the mount has no public URL.
     */
    public function getFeedUrl(Maho_FeedManager_Model_Feed $feed): string
    {
        $path = $feed->getStoragePath();
        if ($path === null) {
            return '';
        }
        try {
            return $this->getOutputMount()->publicUrl($path);
        } catch (\League\Flysystem\UnableToGeneratePublicUrl) {
            return '';
        }
    }
}
