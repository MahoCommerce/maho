<?php

declare(strict_types=1);

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
 * @method Maho_CustomerSegmentation_Model_Resource_Segment getResource()
 * @method Maho_CustomerSegmentation_Model_Resource_Segment _getResource()
 */
class Maho_CustomerSegmentation_Model_Segment extends Mage_Rule_Model_Abstract
{
    public const STATUS_PENDING    = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_ERROR      = 'error';

    public const MODE_AUTO   = 'auto';
    public const MODE_MANUAL = 'manual';

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
    public function getConditions()
    {
        if (!$this->_conditions) {
            $this->_resetConditions();
        }

        // Load rule conditions if it is applicable
        if ($this->hasConditionsSerialized()) {
            $conditions = $this->getConditionsSerialized();
            if (!empty($conditions)) {
                $conditions = Mage::helper('core/unserializeArray')->unserialize($conditions);
                if (is_array($conditions) && !empty($conditions)) {
                    // Force reset conditions before loading to prevent duplicates
                    $this->_conditions->setConditions([]);
                    $this->_conditions->loadArray($conditions);
                }
            }
            $this->unsConditionsSerialized();
        }

        return $this->_conditions;
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
            $this->getResource()->updateCustomerMembership($this, $matchedCustomers);

            $this->setMatchedCustomersCount(count($matchedCustomers))
                ->setLastRefreshAt(Mage::getSingleton('core/locale')->now())
                ->setRefreshStatus(self::STATUS_COMPLETED)
                ->save();

            Mage::dispatchEvent('customer_segment_refresh_after', [
                'segment' => $this,
                'matched_customers' => $matchedCustomers,
            ]);
        } catch (Exception $e) {
            $this->setRefreshStatus(self::STATUS_ERROR)->save();
            Mage::logException($e);
            throw $e;
        }

        return $this;
    }

    public function isCustomerInSegment(int $customerId, ?int $websiteId = null): bool
    {
        if (!$this->getIsActive()) {
            return false;
        }

        // Check database
        $resource = $this->getResource();
        return $resource->isCustomerInSegment($this->getId(), $customerId, $websiteId);
    }


    public function getCustomersCollection(): Mage_Customer_Model_Resource_Customer_Collection
    {
        $collection = Mage::getResourceModel('customer/customer_collection');

        if ($this->getId()) {
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
    protected function _afterLoad(): self
    {
        parent::_afterLoad();

        // Convert comma-separated strings to arrays for form display
        if ($this->hasData('website_ids')) {
            $websiteIds = $this->getData('website_ids');
            if (is_string($websiteIds) && !empty($websiteIds)) {
                $this->setData('website_ids', explode(',', $websiteIds));
            }
        }

        if ($this->hasData('customer_group_ids')) {
            $groupIds = $this->getData('customer_group_ids');
            if (is_string($groupIds) && !empty($groupIds)) {
                $this->setData('customer_group_ids', explode(',', $groupIds));
            }
        }

        return $this;
    }

    #[\Override]
    protected function _beforeSave(): self
    {
        parent::_beforeSave();

        // Convert array fields to comma-separated strings
        if ($this->hasData('website_ids')) {
            $websiteIds = $this->getData('website_ids');
            if (is_array($websiteIds)) {
                $this->setData('website_ids', implode(',', $websiteIds));
            }
        }

        if ($this->hasData('customer_group_ids')) {
            $groupIds = $this->getData('customer_group_ids');
            if (is_array($groupIds)) {
                $this->setData('customer_group_ids', implode(',', $groupIds));
            }
        }

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

    #[\Override]
    public function loadPost(array $data): self
    {
        // Ensure conditions are properly reset before loading new data
        $this->unsConditions();
        parent::loadPost($data);
        return $this;
    }
}
