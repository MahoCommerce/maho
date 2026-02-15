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
 * Feed model
 *
 * Error Handling Pattern:
 * - Getter methods (getPlatformAdapter, getStore): Return null if not found
 * - Validation methods (validate via Mage_Rule): Throw Mage_Core_Exception with user-friendly message
 * - Boolean checks (isEnabled, hasAttributeMappings): Return false on failure, never throw
 * - File operations (getOutputFilePath): Return path string, caller handles file existence
 *
 * @method int getFeedId()
 * @method string getName()
 * @method $this setName(string $name)
 * @method string getPlatform()
 * @method $this setPlatform(string $platform)
 * @method int getStoreId()
 * @method $this setStoreId(int $storeId)
 * @method int getIsEnabled()
 * @method $this setIsEnabled(int $isEnabled)
 * @method string getFilename()
 * @method $this setFilename(string $filename)
 * @method string getFileFormat()
 * @method $this setFileFormat(string $format)
 * @method string getGenerationTime()
 * @method $this setGenerationTime(string $time)
 * @method string getConfigurableMode()
 * @method $this setConfigurableMode(string $mode)
 * @method int|null getDestinationId()
 * @method $this setDestinationId(int|null $destinationId)
 * @method int getAutoUpload()
 * @method $this setAutoUpload(int $autoUpload)
 * @method string|null getSchedule()
 * @method $this setSchedule(string|null $schedule)
 * @method string|null getProductFilters()
 * @method $this setProductFilters(string|null $filters)
 * @method int getExcludeDisabled()
 * @method $this setExcludeDisabled(int $value)
 * @method int getExcludeOutOfStock()
 * @method $this setExcludeOutOfStock(int $value)
 * @method string|null getIncludeProductTypes()
 * @method $this setIncludeProductTypes(string|null $types)
 * @method string|null getFormatPreset()
 * @method $this setFormatPreset(string|null $preset)
 * @method string|null getPriceCurrency()
 * @method $this setPriceCurrency(string|null $currency)
 * @method int|null getPriceDecimals()
 * @method $this setPriceDecimals(int|null $decimals)
 * @method string|null getPriceDecimalPoint()
 * @method $this setPriceDecimalPoint(string|null $point)
 * @method string|null getPriceThousandsSep()
 * @method $this setPriceThousandsSep(string|null $sep)
 * @method string|null getTaxMode()
 * @method $this setTaxMode(string|null $mode)
 * @method int|null getUseParentValue()
 * @method $this setUseParentValue(int|null $value)
 * @method int|null getExcludeCategoryUrl()
 * @method $this setExcludeCategoryUrl(int|null $value)
 * @method string|null getNoImageUrl()
 * @method $this setNoImageUrl(string|null $url)
 * @method string|null getXmlHeader()
 * @method $this setXmlHeader(string|null $header)
 * @method string|null getXmlItemTemplate()
 * @method $this setXmlItemTemplate(string|null $template)
 * @method string|null getXmlFooter()
 * @method $this setXmlFooter(string|null $footer)
 * @method string|null getConditionGroups()
 * @method $this setConditionGroups(string|null $groups)
 * @method string|null getXmlItemTag()
 * @method $this setXmlItemTag(string|null $tag)
 * @method string|null getCsvColumns()
 * @method $this setCsvColumns(string|null $columns)
 * @method string|null getCsvDelimiter()
 * @method $this setCsvDelimiter(string|null $delimiter)
 * @method string|null getCsvEnclosure()
 * @method $this setCsvEnclosure(string|null $enclosure)
 * @method int|null getCsvIncludeHeader()
 * @method $this setCsvIncludeHeader(int|null $value)
 * @method string|null getJsonStructure()
 * @method $this setJsonStructure(string|null $structure)
 * @method string|null getJsonRootKey()
 * @method $this setJsonRootKey(string|null $key)
 * @method string|null getXmlStructure()
 * @method $this setXmlStructure(string|null $structure)
 * @method int getPriceCurrencySuffix()
 * @method $this setPriceCurrencySuffix(int $value)
 * @method string|null getLastGeneratedAt()
 * @method $this setLastGeneratedAt(string|null $datetime)
 * @method int|null getLastProductCount()
 * @method $this setLastProductCount(int|null $count)
 * @method int|null getLastFileSize()
 * @method $this setLastFileSize(int|null $size)
 * @method string getCreatedAt()
 * @method string getUpdatedAt()
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

        $now = Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
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
}
