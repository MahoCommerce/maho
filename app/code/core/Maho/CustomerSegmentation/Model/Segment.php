<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
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
 * @method string getAutoEmailTrigger()
 * @method int getAutoEmailActive()
 * @method int getAllowOverlappingSequences()
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
 * @method Maho_CustomerSegmentation_Model_Segment setAutoEmailTrigger(string $value)
 * @method Maho_CustomerSegmentation_Model_Segment setAutoEmailActive(int $value)
 * @method Maho_CustomerSegmentation_Model_Segment setAllowOverlappingSequences(int $value)
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

    public const EMAIL_TRIGGER_NONE  = 'none';
    public const EMAIL_TRIGGER_ENTER = 'enter';
    public const EMAIL_TRIGGER_EXIT  = 'exit';

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
                $conditions = $this->_decodeRuleData($conditions, 'conditions_serialized');
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
            // Get current membership BEFORE updating
            $adapter = $this->getResource()->getReadConnection();
            $select = $adapter->select()
                ->from(['sc' => $this->getResource()->getTable('customersegmentation/segment_customer')], 'customer_id')
                ->where('sc.segment_id = ?', $this->getId());
            $previousCustomers = $adapter->fetchCol($select);

            $matchedCustomers = $this->getMatchingCustomerIds();
            $this->getResource()->updateCustomerMembership($this, $matchedCustomers);

            $utcDateTime = Mage::app()->getLocale()->utcDate(null, null, true);
            $nowString = $utcDateTime->format(Mage_Core_Model_Locale::DATETIME_FORMAT);

            $this->setMatchedCustomersCount(count($matchedCustomers))
                ->setLastRefreshAt($nowString)
                ->setRefreshStatus(self::STATUS_COMPLETED)
                ->save();

            Mage::dispatchEvent('customer_segment_refresh_after', [
                'segment' => $this,
                'matched_customers' => $matchedCustomers,
                'previous_customers' => $previousCustomers,
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
    public function validate(?\Maho\DataObject $object = null): bool
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

            // Set default values for email automation
            if (!$this->hasData('auto_email_active')) {
                $this->setAutoEmailActive(0);
            }
            if (!$this->hasData('allow_overlapping_sequences')) {
                $this->setAllowOverlappingSequences(0);
            }
        }

        // Validate email automation if enabled
        if ($this->getAutoEmailActive()) {
            $errors = $this->validateEmailAutomation();
            if (!empty($errors)) {
                Mage::throwException(implode("\n", $errors));
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

    /**
     * Email Automation Methods
     */

    /**
     * Check if segment has email automation enabled
     */
    public function hasEmailAutomation(): bool
    {
        if (!(bool) $this->getAutoEmailActive()) {
            return false;
        }

        try {
            return $this->getEmailSequences()->getSize() > 0;
        } catch (Exception $e) {
            // Return false if there are database connection issues
            return false;
        }
    }

    /**
     * Get email sequences for this segment
     */
    public function getEmailSequences(): Maho_CustomerSegmentation_Model_Resource_EmailSequence_Collection
    {
        try {
            return Mage::getResourceModel('customersegmentation/emailSequence_collection')
                ->addSegmentFilter((int) $this->getId())
                ->addActiveFilter()
                ->addStepNumberOrder('ASC');
        } catch (Exception $e) {
            // Return empty collection if there are issues
            Mage::log('Failed to load email sequences: ' . $e->getMessage(), Mage::LOG_WARNING);
            // Create an empty collection of the correct type
            $collection = Mage::getResourceModel('customersegmentation/emailSequence_collection');
            $collection->addFieldToFilter('sequence_id', 0); // Force empty result
            return $collection;
        }
    }

    /**
     * Get automation trigger options for admin form
     */
    public function getAutoEmailTriggerOptions(): array
    {
        return [
            self::EMAIL_TRIGGER_NONE => Mage::helper('customersegmentation')->__('Disabled'),
            self::EMAIL_TRIGGER_ENTER => Mage::helper('customersegmentation')->__('When customer enters segment'),
            self::EMAIL_TRIGGER_EXIT => Mage::helper('customersegmentation')->__('When customer exits segment'),
        ];
    }

    /**
     * Start email sequence for customer
     */
    public function startEmailSequence(int $customerId, string $triggerType): void
    {
        if (!$this->hasEmailAutomation()) {
            return;
        }

        // Check if overlapping sequences are allowed
        if (!$this->getAllowOverlappingSequences()) {
            // If overlapping not allowed, check for any active sequences for this customer/segment
            if ($this->hasAnyActiveSequence($customerId)) {
                Mage::log(
                    "Skipping sequence start for customer {$customerId} in segment {$this->getId()}: existing active sequence found and overlapping not allowed",
                    Mage::LOG_INFO,
                );
                return;
            }
        } else {
            // If overlapping allowed, only check for same trigger type
            if ($this->hasActiveSequence($customerId, $triggerType)) {
                Mage::log(
                    "Skipping sequence start for customer {$customerId} in segment {$this->getId()}: same trigger sequence already active",
                    Mage::LOG_INFO,
                );
                return;
            }
        }

        // Get sequences for this specific trigger type
        $sequences = Mage::getResourceModel('customersegmentation/emailSequence_collection')
            ->addSegmentFilter((int) $this->getId())
            ->addActiveFilter()
            ->addTriggerFilter($triggerType)
            ->addStepNumberOrder('ASC');

        if ($sequences->getSize() === 0) {
            return;
        }

        // Create progress records for all sequences
        $sequenceData = [];
        foreach ($sequences as $sequence) {
            $sequenceData[] = [
                'sequence_id' => $sequence->getId(),
                'step_number' => $sequence->getStepNumber(),
                'delay_minutes' => $sequence->getDelayMinutes(),
            ];
        }

        $resource = Mage::getResourceSingleton('customersegmentation/sequenceProgress');
        $resource->createSequenceProgress($customerId, (int) $this->getId(), $sequenceData, $triggerType);

        Mage::log(
            "Started email sequence for customer {$customerId} in segment {$this->getId()} with trigger {$triggerType}",
            Mage::LOG_INFO,
        );
    }

    /**
     * Check if customer has active sequence for this trigger
     */
    public function hasActiveSequence(int $customerId, string $triggerType): bool
    {
        $resource = Mage::getResourceSingleton('customersegmentation/sequenceProgress');
        return $resource->hasActiveSequence($customerId, $this->getId(), $triggerType);
    }

    /**
     * Check if customer has any active sequence for this segment (regardless of trigger)
     */
    public function hasAnyActiveSequence(int $customerId): bool
    {
        $resource = Mage::getResourceSingleton('customersegmentation/sequenceProgress');
        return $resource->hasAnyActiveSequence($customerId, (int) $this->getId());
    }

    /**
     * Get email automation statistics for this segment
     */
    public function getEmailAutomationStats(): array
    {
        if (!$this->getId()) {
            return [];
        }

        try {
            $resource = Mage::getResourceSingleton('customersegmentation/sequenceProgress');
            return $resource->getSegmentStats((int) $this->getId());
        } catch (Exception $e) {
            // Return empty stats if tables don't exist yet or other DB issues
            Mage::log('Failed to get email automation stats: ' . $e->getMessage(), Mage::LOG_WARNING);
            return [];
        }
    }

    /**
     * Validate email automation settings
     */
    public function validateEmailAutomation(): array
    {
        $errors = [];

        if (!$this->getAutoEmailActive()) {
            return $errors; // No validation needed if automation is disabled
        }

        // Only check sequences if segment already exists (has ID)
        if ($this->getId()) {
            try {
                // Check if segment has any sequences
                $sequences = $this->getEmailSequences();
                if ($sequences->getSize() === 0) {
                    $errors[] = Mage::helper('customersegmentation')->__('At least one email sequence is required when automation is enabled.');
                } else {
                    // Validate each sequence
                    foreach ($sequences as $sequence) {
                        try {
                            $sequence->validate();
                        } catch (Exception $e) {
                            $errors[] = Mage::helper('customersegmentation')->__(
                                'Sequence step %d: %s',
                                $sequence->getStepNumber(),
                                $e->getMessage(),
                            );
                        }
                    }
                }
            } catch (Exception $e) {
                // Skip sequence validation if there are database connection issues
                Mage::log('Failed to validate email sequences: ' . $e->getMessage(), Mage::LOG_WARNING);
            }
        }

        return $errors;
    }

}
