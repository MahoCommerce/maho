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

    protected $_eventPrefix = 'feedmanager_feed';
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
        $value = $this->getData('feed_id');
        return $value === null ? null : (int) $value;
    }

    public function getName(): ?string
    {
        $value = $this->getData('name');
        return $value === null ? null : (string) $value;
    }

    public function setName(?string $value): static
    {
        return $this->setData('name', $value);
    }

    public function getPlatform(): ?string
    {
        $value = $this->getData('platform');
        return $value === null ? null : (string) $value;
    }

    public function setPlatform(?string $value): static
    {
        return $this->setData('platform', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getIsEnabled(): ?int
    {
        $value = $this->getData('is_enabled');
        return $value === null ? null : (int) $value;
    }

    public function setIsEnabled(?int $value): static
    {
        return $this->setData('is_enabled', $value);
    }

    public function getFilename(): ?string
    {
        $value = $this->getData('filename');
        return $value === null ? null : (string) $value;
    }

    public function setFilename(?string $value): static
    {
        return $this->setData('filename', $value);
    }

    public function getFileFormat(): ?string
    {
        $value = $this->getData('file_format');
        return $value === null ? null : (string) $value;
    }

    public function setFileFormat(?string $value): static
    {
        return $this->setData('file_format', $value);
    }

    public function getGenerationTime(): ?string
    {
        $value = $this->getData('generation_time');
        return $value === null ? null : (string) $value;
    }

    public function setGenerationTime(?string $value): static
    {
        return $this->setData('generation_time', $value);
    }

    public function getConfigurableMode(): ?string
    {
        $value = $this->getData('configurable_mode');
        return $value === null ? null : (string) $value;
    }

    public function setConfigurableMode(?string $value): static
    {
        return $this->setData('configurable_mode', $value);
    }

    public function getDestinationId(): ?int
    {
        $value = $this->getData('destination_id');
        return $value === null ? null : (int) $value;
    }

    public function setDestinationId(?int $value): static
    {
        return $this->setData('destination_id', $value);
    }

    public function getAutoUpload(): ?int
    {
        $value = $this->getData('auto_upload');
        return $value === null ? null : (int) $value;
    }

    public function setAutoUpload(?int $value): static
    {
        return $this->setData('auto_upload', $value);
    }

    public function getSchedule(): ?string
    {
        $value = $this->getData('schedule');
        return $value === null ? null : (string) $value;
    }

    public function setSchedule(?string $value): static
    {
        return $this->setData('schedule', $value);
    }

    public function getProductFilters(): ?string
    {
        $value = $this->getData('product_filters');
        return $value === null ? null : (string) $value;
    }

    public function setProductFilters(?string $value): static
    {
        return $this->setData('product_filters', $value);
    }

    public function getExcludeDisabled(): ?int
    {
        $value = $this->getData('exclude_disabled');
        return $value === null ? null : (int) $value;
    }

    public function setExcludeDisabled(?int $value): static
    {
        return $this->setData('exclude_disabled', $value);
    }

    public function getExcludeOutOfStock(): ?int
    {
        $value = $this->getData('exclude_out_of_stock');
        return $value === null ? null : (int) $value;
    }

    public function setExcludeOutOfStock(?int $value): static
    {
        return $this->setData('exclude_out_of_stock', $value);
    }

    public function getIncludeProductTypes(): ?string
    {
        $value = $this->getData('include_product_types');
        return $value === null ? null : (string) $value;
    }

    public function setIncludeProductTypes(?string $value): static
    {
        return $this->setData('include_product_types', $value);
    }

    public function getConditionGroups(): ?string
    {
        $value = $this->getData('condition_groups');
        return $value === null ? null : (string) $value;
    }

    public function setConditionGroups(?string $value): static
    {
        return $this->setData('condition_groups', $value);
    }

    public function getXmlHeader(): ?string
    {
        $value = $this->getData('xml_header');
        return $value === null ? null : (string) $value;
    }

    public function setXmlHeader(?string $value): static
    {
        return $this->setData('xml_header', $value);
    }

    public function getXmlItemTemplate(): ?string
    {
        $value = $this->getData('xml_item_template');
        return $value === null ? null : (string) $value;
    }

    public function setXmlItemTemplate(?string $value): static
    {
        return $this->setData('xml_item_template', $value);
    }

    public function getXmlFooter(): ?string
    {
        $value = $this->getData('xml_footer');
        return $value === null ? null : (string) $value;
    }

    public function setXmlFooter(?string $value): static
    {
        return $this->setData('xml_footer', $value);
    }

    public function getXmlItemTag(): ?string
    {
        $value = $this->getData('xml_item_tag');
        return $value === null ? null : (string) $value;
    }

    public function setXmlItemTag(?string $value): static
    {
        return $this->setData('xml_item_tag', $value);
    }

    public function getXmlStructure(): ?string
    {
        $value = $this->getData('xml_structure');
        return $value === null ? null : (string) $value;
    }

    public function setXmlStructure(?string $value): static
    {
        return $this->setData('xml_structure', $value);
    }

    public function getCsvColumns(): ?string
    {
        $value = $this->getData('csv_columns');
        return $value === null ? null : (string) $value;
    }

    public function setCsvColumns(?string $value): static
    {
        return $this->setData('csv_columns', $value);
    }

    public function getCsvDelimiter(): ?string
    {
        $value = $this->getData('csv_delimiter');
        return $value === null ? null : (string) $value;
    }

    public function setCsvDelimiter(?string $value): static
    {
        return $this->setData('csv_delimiter', $value);
    }

    public function getCsvEnclosure(): ?string
    {
        $value = $this->getData('csv_enclosure');
        return $value === null ? null : (string) $value;
    }

    public function setCsvEnclosure(?string $value): static
    {
        return $this->setData('csv_enclosure', $value);
    }

    public function getCsvIncludeHeader(): ?int
    {
        $value = $this->getData('csv_include_header');
        return $value === null ? null : (int) $value;
    }

    public function setCsvIncludeHeader(?int $value): static
    {
        return $this->setData('csv_include_header', $value);
    }

    public function getJsonStructure(): ?string
    {
        $value = $this->getData('json_structure');
        return $value === null ? null : (string) $value;
    }

    public function setJsonStructure(?string $value): static
    {
        return $this->setData('json_structure', $value);
    }

    public function getJsonRootKey(): ?string
    {
        $value = $this->getData('json_root_key');
        return $value === null ? null : (string) $value;
    }

    public function setJsonRootKey(?string $value): static
    {
        return $this->setData('json_root_key', $value);
    }

    public function getFormatPreset(): ?string
    {
        $value = $this->getData('format_preset');
        return $value === null ? null : (string) $value;
    }

    public function setFormatPreset(?string $value): static
    {
        return $this->setData('format_preset', $value);
    }

    public function getPriceCurrency(): ?string
    {
        $value = $this->getData('price_currency');
        return $value === null ? null : (string) $value;
    }

    public function setPriceCurrency(?string $value): static
    {
        return $this->setData('price_currency', $value);
    }

    public function getPriceDecimals(): ?int
    {
        $value = $this->getData('price_decimals');
        return $value === null ? null : (int) $value;
    }

    public function setPriceDecimals(?int $value): static
    {
        return $this->setData('price_decimals', $value);
    }

    public function getPriceDecimalPoint(): ?string
    {
        $value = $this->getData('price_decimal_point');
        return $value === null ? null : (string) $value;
    }

    public function setPriceDecimalPoint(?string $value): static
    {
        return $this->setData('price_decimal_point', $value);
    }

    public function getPriceThousandsSep(): ?string
    {
        $value = $this->getData('price_thousands_sep');
        return $value === null ? null : (string) $value;
    }

    public function setPriceThousandsSep(?string $value): static
    {
        return $this->setData('price_thousands_sep', $value);
    }

    public function getPriceCurrencySuffix(): ?int
    {
        $value = $this->getData('price_currency_suffix');
        return $value === null ? null : (int) $value;
    }

    public function setPriceCurrencySuffix(?int $value): static
    {
        return $this->setData('price_currency_suffix', $value);
    }

    public function getTaxMode(): ?string
    {
        $value = $this->getData('tax_mode');
        return $value === null ? null : (string) $value;
    }

    public function setTaxMode(?string $value): static
    {
        return $this->setData('tax_mode', $value);
    }

    public function getUseParentValue(): ?int
    {
        $value = $this->getData('use_parent_value');
        return $value === null ? null : (int) $value;
    }

    public function setUseParentValue(?int $value): static
    {
        return $this->setData('use_parent_value', $value);
    }

    public function getExcludeCategoryUrl(): ?int
    {
        $value = $this->getData('exclude_category_url');
        return $value === null ? null : (int) $value;
    }

    public function setExcludeCategoryUrl(?int $value): static
    {
        return $this->setData('exclude_category_url', $value);
    }

    public function getNoImageUrl(): ?string
    {
        $value = $this->getData('no_image_url');
        return $value === null ? null : (string) $value;
    }

    public function setNoImageUrl(?string $value): static
    {
        return $this->setData('no_image_url', $value);
    }

    public function getGzipCompression(): ?int
    {
        $value = $this->getData('gzip_compression');
        return $value === null ? null : (int) $value;
    }

    public function setGzipCompression(?int $value): static
    {
        return $this->setData('gzip_compression', $value);
    }

    public function getNotificationMode(): ?string
    {
        $value = $this->getData('notification_mode');
        return $value === null ? null : (string) $value;
    }

    public function setNotificationMode(?string $value): static
    {
        return $this->setData('notification_mode', $value);
    }

    public function getNotificationFrequency(): ?string
    {
        $value = $this->getData('notification_frequency');
        return $value === null ? null : (string) $value;
    }

    public function setNotificationFrequency(?string $value): static
    {
        return $this->setData('notification_frequency', $value);
    }

    public function getNotificationEmail(): ?string
    {
        $value = $this->getData('notification_email');
        return $value === null ? null : (string) $value;
    }

    public function setNotificationEmail(?string $value): static
    {
        return $this->setData('notification_email', $value);
    }

    public function getNotificationSent(): ?int
    {
        $value = $this->getData('notification_sent');
        return $value === null ? null : (int) $value;
    }

    public function setNotificationSent(?int $value): static
    {
        return $this->setData('notification_sent', $value);
    }

    public function getLastGeneratedAt(): ?string
    {
        $value = $this->getData('last_generated_at');
        return $value === null ? null : (string) $value;
    }

    public function setLastGeneratedAt(?string $value): static
    {
        return $this->setData('last_generated_at', $value);
    }

    public function getLastProductCount(): ?int
    {
        $value = $this->getData('last_product_count');
        return $value === null ? null : (int) $value;
    }

    public function setLastProductCount(?int $value): static
    {
        return $this->setData('last_product_count', $value);
    }

    public function getLastFileSize(): ?int
    {
        $value = $this->getData('last_file_size');
        return $value === null ? null : (int) $value;
    }

    public function setLastFileSize(?int $value): static
    {
        return $this->setData('last_file_size', $value);
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData('created_at');
        return $value === null ? null : (string) $value;
    }

    public function setCreatedAt(?string $value): static
    {
        return $this->setData('created_at', $value);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }

    public function setUpdatedAt(?string $value): static
    {
        return $this->setData('updated_at', $value);
    }
}
