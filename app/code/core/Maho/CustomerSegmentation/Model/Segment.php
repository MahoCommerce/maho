<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method string getName()
 * @method string getDescription()
 * @method int getIsActive()
 * @method string getConditionsSerialized()
 * @method string getWebsiteIds()
 * @method string getCustomerGroupIds()
 * @method string getCreatedAt()
 * @method string getUpdatedAt()
 * @method int getMatchedCustomersCount()
 * @method string getLastRefreshAt()
 * @method string getRefreshStatus()
 * @method string getRefreshMode()
 * @method int getPriority()
 * @method Maho_CustomerSegmentation_Model_Segment setName(string $value)
 * @method Maho_CustomerSegmentation_Model_Segment setDescription(string $value)
 * @method Maho_CustomerSegmentation_Model_Segment setIsActive(int $value)
 * @method Maho_CustomerSegmentation_Model_Segment setConditionsSerialized(string $value)
 * @method Maho_CustomerSegmentation_Model_Segment setWebsiteIds(string $value)
 * @method Maho_CustomerSegmentation_Model_Segment setCustomerGroupIds(string $value)
 * @method Maho_CustomerSegmentation_Model_Segment setMatchedCustomersCount(int $value)
 * @method Maho_CustomerSegmentation_Model_Segment setLastRefreshAt(string $value)
 * @method Maho_CustomerSegmentation_Model_Segment setRefreshStatus(string $value)
 * @method Maho_CustomerSegmentation_Model_Segment setRefreshMode(string $value)
 * @method Maho_CustomerSegmentation_Model_Segment setPriority(int $value)
 */
class Maho_CustomerSegmentation_Model_Segment extends Mage_Rule_Model_Abstract
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_ERROR      = 'error';

    public const MODE_AUTO   = 'auto';
    public const MODE_MANUAL = 'manual';

    public const CACHE_TAG = 'CUSTOMER_SEGMENT';

    /**
     * Event prefix for observers
     * @var string
     */
    protected $_eventPrefix = 'customer_segment';

    /**
     * Event object for observers
     * @var string
     */
    protected $_eventObject = 'segment';

    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        $this->_init('customersegmentation/segment');
        $this->setIdFieldName('segment_id');
    }

    #[\Override]
    public function getConditionsInstance(): Maho_CustomerSegmentation_Model_Segment_Condition_Combine
    {
        return Mage::getModel('customersegmentation/segment_condition_combine');
    }

    #[\Override]
    public function getActionsInstance(): Mage_Rule_Model_Action_Collection
    {
        return Mage::getModel('rule/action_collection');
    }

    public function getWebsiteIdsArray(): array
    {
        $websiteIds = $this->getWebsiteIds();
        if (is_string($websiteIds) && !empty($websiteIds)) {
            return explode(',', $websiteIds);
        }
        return [];
    }

    public function setWebsiteIdsArray(array $websiteIds): self
    {
        $this->setWebsiteIds(implode(',', $websiteIds));
        return $this;
    }

    public function getCustomerGroupIdsArray(): array
    {
        $groupIds = $this->getCustomerGroupIds();
        if (is_string($groupIds) && !empty($groupIds)) {
            return explode(',', $groupIds);
        }
        return [];
    }

    public function setCustomerGroupIdsArray(array $groupIds): self
    {
        $this->setCustomerGroupIds(implode(',', $groupIds));
        return $this;
    }

    /**
     * Get matching customer IDs for this segment
     */
    public function getMatchingCustomerIds(?int $websiteId = null): array
    {
        if (!$this->getIsActive()) {
            return [];
        }

        /** @var Maho_CustomerSegmentation_Model_Resource_Segment $resource */
        $resource = $this->getResource();
        return $resource->getMatchingCustomerIds($this, $websiteId);
    }

    /**
     * Refresh segment membership
     */
    public function refreshCustomers(): self
    {
        if (!$this->getId()) {
            Mage::throwException(Mage::helper('customersegmentation')->__('Please save the segment first.'));
        }

        $this->setRefreshStatus(self::STATUS_PROCESSING);
        $this->save();

        Mage::dispatchEvent('customer_segment_refresh_before', [
            'segment' => $this,
        ]);

        try {
            $matchedCustomers = $this->getMatchingCustomerIds();
            /** @var Maho_CustomerSegmentation_Model_Resource_Segment $resource */
            $resource = $this->getResource();
            $resource->updateCustomerMembership($this, $matchedCustomers);

            $this->setMatchedCustomersCount(count($matchedCustomers))
                ->setLastRefreshAt(Mage::getSingleton('core/date')->gmtDate())
                ->setRefreshStatus(self::STATUS_COMPLETED)
                ->save();

            Mage::dispatchEvent('customer_segment_refresh_after', [
                'segment' => $this,
                'matched_customers' => $matchedCustomers,
            ]);

            // Clear cache
            $this->cleanCache();

        } catch (Exception $e) {
            $this->setRefreshStatus(self::STATUS_ERROR)->save();
            throw $e;
        }

        return $this;
    }

    public function isCustomerInSegment(int $customerId, ?int $websiteId = null): bool
    {
        if (!$this->getIsActive()) {
            return false;
        }

        // Check cache first
        $cacheKey = $this->_getCacheKey($customerId, $websiteId);
        $cache = Mage::app()->getCache();
        $cachedResult = $cache->load($cacheKey);

        if ($cachedResult !== false) {
            return (bool) $cachedResult;
        }

        // Check database
        /** @var Maho_CustomerSegmentation_Model_Resource_Segment $resource */
        $resource = $this->getResource();
        $isInSegment = $resource->isCustomerInSegment($this->getId(), $customerId, $websiteId);

        // Cache result
        if (Mage::getStoreConfigFlag('customer_segmentation/performance/enable_caching')) {
            $lifetime = (int) Mage::getStoreConfig('customer_segmentation/performance/cache_lifetime');
            $cache->save($isInSegment ? '1' : '0', $cacheKey, [self::CACHE_TAG], $lifetime);
        }

        return $isInSegment;
    }

    protected function _getCacheKey(int $customerId, ?int $websiteId = null): string
    {
        return sprintf('%s_%d_%d_%d', self::CACHE_TAG, $this->getId(), $customerId, (int) $websiteId);
    }

    public function cleanCache(): self
    {
        Mage::app()->getCache()->clean(Zend_Cache::CLEANING_MODE_MATCHING_TAG, [self::CACHE_TAG]);
        return $this;
    }

    public function getCustomersCollection(): Mage_Customer_Model_Resource_Customer_Collection
    {
        $collection = Mage::getResourceModel('customer/customer_collection');

        if ($this->getId()) {
            /** @var Maho_CustomerSegmentation_Model_Resource_Segment $resource */
            $resource = $this->getResource();
            $resource->applySegmentToCollection($this, $collection);
        }

        return $collection;
    }

    /**
     * Validate segment data
     *
     * @throws Mage_Core_Exception
     */
    #[\Override]
    public function validate(?Varien_Object $object = null): bool
    {
        $errors = [];

        if (!$this->getName()) {
            $errors[] = Mage::helper('customersegmentation')->__('Segment name is required.');
        }

        if (!$this->getWebsiteIds()) {
            $errors[] = Mage::helper('customersegmentation')->__('Please select at least one website.');
        }

        if (!empty($errors)) {
            Mage::throwException(implode("\n", $errors));
        }

        return true;
    }

    #[\Override]
    protected function _beforeSave(): self
    {
        parent::_beforeSave();

        // Validate data
        $this->validate();

        // Set default values
        if ($this->isObjectNew()) {
            if (!$this->hasRefreshMode()) {
                $this->setRefreshMode(self::MODE_AUTO);
            }
            if (!$this->hasRefreshStatus()) {
                $this->setRefreshStatus(self::STATUS_PENDING);
            }
            if (!$this->hasPriority()) {
                $this->setPriority(0);
            }
        }

        return $this;
    }

    #[\Override]
    protected function _afterSave(): self
    {
        parent::_afterSave();
        $this->cleanCache();
        return $this;
    }

    #[\Override]
    protected function _afterDelete(): self
    {
        parent::_afterDelete();
        $this->cleanCache();
        return $this;
    }

    public function getRefreshModeOptions(): array
    {
        return [
            self::MODE_AUTO   => Mage::helper('customersegmentation')->__('Automatic'),
            self::MODE_MANUAL => Mage::helper('customersegmentation')->__('Manual'),
        ];
    }

    public function getRefreshStatusOptions(): array
    {
        return [
            self::STATUS_PENDING    => Mage::helper('customersegmentation')->__('Pending'),
            self::STATUS_PROCESSING => Mage::helper('customersegmentation')->__('Processing'),
            self::STATUS_COMPLETED  => Mage::helper('customersegmentation')->__('Completed'),
            self::STATUS_ERROR      => Mage::helper('customersegmentation')->__('Error'),
        ];
    }
}
