<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

/**
 * Feed model
 *
 * Error Handling Pattern:
 * - Getter methods (getPlatformAdapter, getStore): Return null if not found
 * - Validation methods (validate via Mage_Rule): Throw Mage_Core_Exception with user-friendly message
 * - Boolean checks (isEnabled, hasAttributeMappings): Return false on failure, never throw
 * - File operations (getOutputFilePath): Return path string, caller handles file existence
 *
 * @method Maho_FeedManager_Model_Resource_Feed getResource()
 * @method Maho_FeedManager_Model_Resource_Feed _getResource()
 */
class Maho_FeedManager_Model_Feed extends Mage_Rule_Model_Abstract
{
    public const CONFIGURABLE_MODE_SIMPLE_ONLY = 'simple_only';
    public const CONFIGURABLE_MODE_CHILDREN_ONLY = 'children_only';
    public const CONFIGURABLE_MODE_BOTH = 'both';

    public const STATUS_ENABLED = 1;
    public const STATUS_DISABLED = 0;

    #[\Override]
    protected $_eventPrefix = 'feedmanager_feed';
    #[\Override]
    protected $_eventObject = 'feed';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('feedmanager/feed');
    }

    #[\Override]
    public function getConditionsInstance(): Maho_FeedManager_Model_Rule_Condition_Combine
    {
        return Mage::getModel('feedmanager/rule_condition_combine');
    }

    #[\Override]
    public function getActionsInstance(): Mage_Rule_Model_Action_Collection
    {
        return Mage::getModel('rule/action_collection');
    }

    /**
     * Prepare data before saving
     */
    #[\Override]
    protected function _beforeSave()
    {
        // Sanitize filename to prevent path traversal
        $filename = $this->getFilename();
        if ($filename !== null && $filename !== '') {
            $filename = basename($filename);

            // Strip feed format extension if the user included it — the real extension is determined by file_format
            $feedExtensions = ['xml', 'csv', 'json', 'jsonl', 'gz'];
            while (in_array(\Symfony\Component\Filesystem\Path::getExtension($filename), $feedExtensions, true)) {
                $filename = \Symfony\Component\Filesystem\Path::getFilenameWithoutExtension($filename);
            }

            if ($filename === '') {
                Mage::throwException(Mage::helper('feedmanager')->__('Invalid filename.'));
            }
            $outputDir = Mage::helper('feedmanager')->getOutputDirectory();
            if (!\Maho\Io::allowedPath($outputDir . DS . $filename . '.tmp', $outputDir)) {
                Mage::throwException(Mage::helper('feedmanager')->__('Invalid filename.'));
            }
            $this->setFilename($filename);
        }

        $now = Mage::app()->getLocale()->formatDateForDb('now');
        if (!$this->getCreatedAt()) {
            $this->setCreatedAt($now);
        }
        $this->setUpdatedAt($now);

        return parent::_beforeSave();
    }

    /**
     * Check if feed is enabled
     */
    public function isEnabled(): bool
    {
        return (int) $this->getIsEnabled() === self::STATUS_ENABLED;
    }

    /**
     * Get attribute mappings for this feed
     */
    public function getAttributeMappings(): Maho_FeedManager_Model_Resource_AttributeMapping_Collection
    {
        return Mage::getResourceModel('feedmanager/attributeMapping_collection')
            ->addFieldToFilter('feed_id', $this->getId())
            ->setOrder('sort_order', 'ASC');
    }

    /**
     * Get generation logs for this feed
     */
    public function getLogs(): Maho_FeedManager_Model_Resource_Log_Collection
    {
        return Mage::getResourceModel('feedmanager/log_collection')
            ->addFieldToFilter('feed_id', $this->getId())
            ->setOrder('started_at', 'DESC');
    }

    /**
     * Get the full file path for output
     */
    public function getOutputFilePath(): string
    {
        $directory = Mage::helper('feedmanager')->getOutputDirectory();
        $extension = $this->getFileFormat();
        if ($this->getGzipCompression()) {
            $extension .= '.gz';
        }
        return $directory . DS . $this->getFilename() . '.' . $extension;
    }

    /**
     * Get the public URL for the feed
     */
    public function getOutputUrl(): string
    {
        $baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
        $directory = Mage::getStoreConfig('feedmanager/general/output_directory') ?: 'feeds';
        $extension = $this->getFileFormat();
        if ($this->getGzipCompression()) {
            $extension .= '.gz';
        }
        return $baseUrl . $directory . '/' . $this->getFilename() . '.' . $extension;
    }

    /**
     * Get configurable mode options
     */
    public static function getConfigurableModeOptions(): array
    {
        return [
            self::CONFIGURABLE_MODE_SIMPLE_ONLY => 'Simple products only',
            self::CONFIGURABLE_MODE_CHILDREN_ONLY => 'Configurable children only (recommended)',
            self::CONFIGURABLE_MODE_BOTH => 'Both parent and children',
        ];
    }

    public function getFeedId(): ?int
    {
        return $this->getData('feed_id');
    }

    public function getName(): ?string
    {
        return $this->getData('name');
    }

    public function setName(?string $value): static
    {
        return $this->setData('name', $value);
    }

    public function getPlatform(): ?string
    {
        return $this->getData('platform');
    }

    public function setPlatform(?string $value): static
    {
        return $this->setData('platform', $value);
    }

    public function getStoreId(): ?int
    {
        return $this->getData('store_id');
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getIsEnabled(): ?int
    {
        return $this->getData('is_enabled');
    }

    public function setIsEnabled(?int $value): static
    {
        return $this->setData('is_enabled', $value);
    }

    public function getFilename(): ?string
    {
        return $this->getData('filename');
    }

    public function setFilename(?string $value): static
    {
        return $this->setData('filename', $value);
    }

    public function getFileFormat(): ?string
    {
        return $this->getData('file_format');
    }

    public function setFileFormat(?string $value): static
    {
        return $this->setData('file_format', $value);
    }

    public function getGenerationTime(): ?string
    {
        return $this->getData('generation_time');
    }

    public function setGenerationTime(?string $value): static
    {
        return $this->setData('generation_time', $value);
    }

    public function getConfigurableMode(): ?string
    {
        return $this->getData('configurable_mode');
    }

    public function setConfigurableMode(?string $value): static
    {
        return $this->setData('configurable_mode', $value);
    }

    public function getDestinationId(): ?int
    {
        return $this->getData('destination_id');
    }

    public function setDestinationId(?int $value): static
    {
        return $this->setData('destination_id', $value);
    }

    public function getAutoUpload(): ?int
    {
        return $this->getData('auto_upload');
    }

    public function setAutoUpload(?int $value): static
    {
        return $this->setData('auto_upload', $value);
    }

    public function getSchedule(): ?string
    {
        return $this->getData('schedule');
    }

    public function setSchedule(?string $value): static
    {
        return $this->setData('schedule', $value);
    }

    public function getProductFilters(): ?string
    {
        return $this->getData('product_filters');
    }

    public function setProductFilters(?string $value): static
    {
        return $this->setData('product_filters', $value);
    }

    public function getExcludeDisabled(): ?int
    {
        return $this->getData('exclude_disabled');
    }

    public function setExcludeDisabled(?int $value): static
    {
        return $this->setData('exclude_disabled', $value);
    }

    public function getExcludeOutOfStock(): ?int
    {
        return $this->getData('exclude_out_of_stock');
    }

    public function setExcludeOutOfStock(?int $value): static
    {
        return $this->setData('exclude_out_of_stock', $value);
    }

    public function getIncludeProductTypes(): ?string
    {
        return $this->getData('include_product_types');
    }

    public function setIncludeProductTypes(?string $value): static
    {
        return $this->setData('include_product_types', $value);
    }

    public function getConditionGroups(): ?string
    {
        return $this->getData('condition_groups');
    }

    public function setConditionGroups(?string $value): static
    {
        return $this->setData('condition_groups', $value);
    }

    public function getXmlHeader(): ?string
    {
        return $this->getData('xml_header');
    }

    public function setXmlHeader(?string $value): static
    {
        return $this->setData('xml_header', $value);
    }

    public function getXmlItemTemplate(): ?string
    {
        return $this->getData('xml_item_template');
    }

    public function setXmlItemTemplate(?string $value): static
    {
        return $this->setData('xml_item_template', $value);
    }

    public function getXmlFooter(): ?string
    {
        return $this->getData('xml_footer');
    }

    public function setXmlFooter(?string $value): static
    {
        return $this->setData('xml_footer', $value);
    }

    public function getXmlItemTag(): ?string
    {
        return $this->getData('xml_item_tag');
    }

    public function setXmlItemTag(?string $value): static
    {
        return $this->setData('xml_item_tag', $value);
    }

    public function getXmlStructure(): ?string
    {
        return $this->getData('xml_structure');
    }

    public function setXmlStructure(?string $value): static
    {
        return $this->setData('xml_structure', $value);
    }

    public function getCsvColumns(): ?string
    {
        return $this->getData('csv_columns');
    }

    public function setCsvColumns(?string $value): static
    {
        return $this->setData('csv_columns', $value);
    }

    public function getCsvDelimiter(): ?string
    {
        return $this->getData('csv_delimiter');
    }

    public function setCsvDelimiter(?string $value): static
    {
        return $this->setData('csv_delimiter', $value);
    }

    public function getCsvEnclosure(): ?string
    {
        return $this->getData('csv_enclosure');
    }

    public function setCsvEnclosure(?string $value): static
    {
        return $this->setData('csv_enclosure', $value);
    }

    public function getCsvIncludeHeader(): ?int
    {
        return $this->getData('csv_include_header');
    }

    public function setCsvIncludeHeader(?int $value): static
    {
        return $this->setData('csv_include_header', $value);
    }

    public function getJsonStructure(): ?string
    {
        return $this->getData('json_structure');
    }

    public function setJsonStructure(?string $value): static
    {
        return $this->setData('json_structure', $value);
    }

    public function getJsonRootKey(): ?string
    {
        return $this->getData('json_root_key');
    }

    public function setJsonRootKey(?string $value): static
    {
        return $this->setData('json_root_key', $value);
    }

    public function getFormatPreset(): ?string
    {
        return $this->getData('format_preset');
    }

    public function setFormatPreset(?string $value): static
    {
        return $this->setData('format_preset', $value);
    }

    public function getPriceCurrency(): ?string
    {
        return $this->getData('price_currency');
    }

    public function setPriceCurrency(?string $value): static
    {
        return $this->setData('price_currency', $value);
    }

    public function getPriceDecimals(): ?int
    {
        return $this->getData('price_decimals');
    }

    public function setPriceDecimals(?int $value): static
    {
        return $this->setData('price_decimals', $value);
    }

    public function getPriceDecimalPoint(): ?string
    {
        return $this->getData('price_decimal_point');
    }

    public function setPriceDecimalPoint(?string $value): static
    {
        return $this->setData('price_decimal_point', $value);
    }

    public function getPriceThousandsSep(): ?string
    {
        return $this->getData('price_thousands_sep');
    }

    public function setPriceThousandsSep(?string $value): static
    {
        return $this->setData('price_thousands_sep', $value);
    }

    public function getPriceCurrencySuffix(): ?int
    {
        return $this->getData('price_currency_suffix');
    }

    public function setPriceCurrencySuffix(?int $value): static
    {
        return $this->setData('price_currency_suffix', $value);
    }

    public function getTaxMode(): ?string
    {
        return $this->getData('tax_mode');
    }

    public function setTaxMode(?string $value): static
    {
        return $this->setData('tax_mode', $value);
    }

    public function getUseParentValue(): ?int
    {
        return $this->getData('use_parent_value');
    }

    public function setUseParentValue(?int $value): static
    {
        return $this->setData('use_parent_value', $value);
    }

    public function getExcludeCategoryUrl(): ?int
    {
        return $this->getData('exclude_category_url');
    }

    public function setExcludeCategoryUrl(?int $value): static
    {
        return $this->setData('exclude_category_url', $value);
    }

    public function getNoImageUrl(): ?string
    {
        return $this->getData('no_image_url');
    }

    public function setNoImageUrl(?string $value): static
    {
        return $this->setData('no_image_url', $value);
    }

    public function getGzipCompression(): ?int
    {
        return $this->getData('gzip_compression');
    }

    public function setGzipCompression(?int $value): static
    {
        return $this->setData('gzip_compression', $value);
    }

    public function getNotificationMode(): ?string
    {
        return $this->getData('notification_mode');
    }

    public function setNotificationMode(?string $value): static
    {
        return $this->setData('notification_mode', $value);
    }

    public function getNotificationFrequency(): ?string
    {
        return $this->getData('notification_frequency');
    }

    public function setNotificationFrequency(?string $value): static
    {
        return $this->setData('notification_frequency', $value);
    }

    public function getNotificationEmail(): ?string
    {
        return $this->getData('notification_email');
    }

    public function setNotificationEmail(?string $value): static
    {
        return $this->setData('notification_email', $value);
    }

    public function getNotificationSent(): ?int
    {
        return $this->getData('notification_sent');
    }

    public function setNotificationSent(?int $value): static
    {
        return $this->setData('notification_sent', $value);
    }

    public function getLastGeneratedAt(): ?string
    {
        return $this->getData('last_generated_at');
    }

    public function setLastGeneratedAt(?string $value): static
    {
        return $this->setData('last_generated_at', $value);
    }

    public function getLastProductCount(): ?int
    {
        return $this->getData('last_product_count');
    }

    public function setLastProductCount(?int $value): static
    {
        return $this->setData('last_product_count', $value);
    }

    public function getLastFileSize(): ?int
    {
        return $this->getData('last_file_size');
    }

    public function setLastFileSize(?int $value): static
    {
        return $this->setData('last_file_size', $value);
    }

    public function getCreatedAt(): ?string
    {
        return $this->getData('created_at');
    }

    public function setCreatedAt(?string $value): static
    {
        return $this->setData('created_at', $value);
    }

    public function getUpdatedAt(): ?string
    {
        return $this->getData('updated_at');
    }

    public function setUpdatedAt(?string $value): static
    {
        return $this->setData('updated_at', $value);
    }
}
