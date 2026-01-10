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
 * Feed model
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
 * @method string|null getProductFilters()
 * @method $this setProductFilters(string|null $filters)
 * @method string|null getConditionsSerialized()
 * @method $this setConditionsSerialized(string|null $conditions)
 * @method bool hasConditionsSerialized()
 * @method $this unsConditionsSerialized()
 * @method int getExcludeDisabled()
 * @method $this setExcludeDisabled(int $value)
 * @method int getExcludeOutOfStock()
 * @method $this setExcludeOutOfStock(int $value)
 * @method string|null getLastGeneratedAt()
 * @method $this setLastGeneratedAt(string|null $datetime)
 * @method int|null getLastProductCount()
 * @method $this setLastProductCount(int|null $count)
 * @method int|null getLastFileSize()
 * @method $this setLastFileSize(int|null $size)
 * @method string getCreatedAt()
 * @method string getUpdatedAt()
 */
class Maho_FeedManager_Model_Feed extends Mage_Core_Model_Abstract
{
    public const CONFIGURABLE_MODE_SIMPLE_ONLY = 'simple_only';
    public const CONFIGURABLE_MODE_CHILDREN_ONLY = 'children_only';
    public const CONFIGURABLE_MODE_BOTH = 'both';

    public const STATUS_ENABLED = 1;
    public const STATUS_DISABLED = 0;

    protected $_eventPrefix = 'feedmanager_feed';
    protected $_eventObject = 'feed';

    /**
     * Store rule combine conditions model
     */
    protected ?Mage_Rule_Model_Condition_Combine $_conditions = null;

    /**
     * Store rule form instance (needed for conditions UI)
     */
    protected ?Maho\Data\Form $_form = null;

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('feedmanager/feed');
    }

    /**
     * Get product filters as array (legacy flat format)
     */
    public function getProductFiltersArray(): array
    {
        $filters = $this->getProductFilters();
        if (empty($filters)) {
            return [];
        }
        $data = Mage::helper('core')->jsonDecode($filters) ?: [];

        // If this is the new groups format, flatten it for backwards compatibility
        if (isset($data['groups'])) {
            $flatFilters = [];
            foreach ($data['groups'] as $group) {
                foreach ($group['conditions'] ?? [] as $condition) {
                    if (!empty($condition['attribute'])) {
                        $flatFilters[] = $condition;
                    }
                }
            }
            return $flatFilters;
        }

        return $data;
    }

    /**
     * Set product filters from array (legacy flat format)
     */
    public function setProductFiltersArray(array $filters): self
    {
        $this->setProductFilters(Mage::helper('core')->jsonEncode($filters));
        return $this;
    }

    /**
     * Get condition groups as array (new AND/OR format)
     *
     * Structure:
     * [
     *   ['conditions' => [['attribute' => 'sku', 'operator' => 'eq', 'value' => 'ABC'], ...]],
     *   ['conditions' => [['attribute' => 'price', 'operator' => 'gt', 'value' => '10'], ...]],
     * ]
     *
     * Groups are ANDed together, conditions within a group are ORed
     */
    public function getConditionGroupsArray(): array
    {
        $filters = $this->getProductFilters();
        if (empty($filters)) {
            return [];
        }

        $data = Mage::helper('core')->jsonDecode($filters) ?: [];

        // If this is the new groups format, return as-is
        if (isset($data['groups'])) {
            return $data['groups'];
        }

        // Convert legacy flat format to groups format (each filter becomes its own AND group)
        if (!empty($data)) {
            $groups = [];
            foreach ($data as $filter) {
                if (!empty($filter['attribute'])) {
                    $groups[] = ['conditions' => [$filter]];
                }
            }
            return $groups;
        }

        return [];
    }

    /**
     * Set condition groups from array
     */
    public function setConditionGroupsArray(array $groups): self
    {
        // Filter out empty conditions and groups
        $cleanGroups = [];
        foreach ($groups as $group) {
            $cleanConditions = [];
            foreach ($group['conditions'] ?? [] as $condition) {
                if (!empty($condition['attribute'])) {
                    $cleanConditions[] = [
                        'attribute' => $condition['attribute'],
                        'operator' => $condition['operator'] ?? 'eq',
                        'value' => $condition['value'] ?? '',
                    ];
                }
            }
            if (!empty($cleanConditions)) {
                $cleanGroups[] = ['conditions' => $cleanConditions];
            }
        }

        $this->setProductFilters(Mage::helper('core')->jsonEncode(['groups' => $cleanGroups]));
        return $this;
    }

    /**
     * Get conditions instance
     */
    public function getConditionsInstance(): Maho_FeedManager_Model_Rule_Condition_Combine
    {
        return Mage::getModel('feedmanager/rule_condition_combine');
    }

    /**
     * Set rule conditions model
     */
    public function setConditions(Mage_Rule_Model_Condition_Combine $conditions): self
    {
        $this->_conditions = $conditions;
        return $this;
    }

    /**
     * Get rule combine conditions model
     */
    public function getConditions(): Mage_Rule_Model_Condition_Combine
    {
        if (empty($this->_conditions)) {
            $this->_resetConditions();
        }

        // Load conditions if serialized data exists
        if ($this->hasConditionsSerialized()) {
            $conditions = $this->getConditionsSerialized();
            if (!empty($conditions)) {
                $conditions = Mage::helper('core/unserializeArray')->unserialize($conditions);
                if (is_array($conditions) && !empty($conditions)) {
                    $this->_conditions->loadArray($conditions);
                }
            }
            $this->unsConditionsSerialized();
        }

        return $this->_conditions;
    }

    /**
     * Reset conditions
     */
    protected function _resetConditions(): self
    {
        $this->_conditions = $this->getConditionsInstance();
        $this->_conditions->setRule($this)->setId('1')->setPrefix('conditions');
        return $this;
    }

    /**
     * Get form instance for conditions rendering
     */
    public function getForm(): Maho\Data\Form
    {
        if (!$this->_form) {
            $this->_form = new Maho\Data\Form();
        }
        return $this->_form;
    }

    /**
     * Prepare data before saving
     */
    #[\Override]
    protected function _beforeSave(): self
    {
        // Serialize conditions
        if ($this->_conditions) {
            $this->setConditionsSerialized(serialize($this->_conditions->asArray()));
            $this->_conditions = null;
        }

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
        return $directory . DS . $this->getFilename() . '.' . $this->getFileFormat();
    }

    /**
     * Get the public URL for the feed
     */
    public function getOutputUrl(): string
    {
        $baseUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA);
        $directory = Mage::getStoreConfig('feedmanager/general/output_directory') ?: 'feeds';
        return $baseUrl . $directory . '/' . $this->getFilename() . '.' . $this->getFileFormat();
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
