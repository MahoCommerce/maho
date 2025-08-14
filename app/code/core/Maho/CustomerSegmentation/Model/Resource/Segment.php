<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Resource_Segment extends Mage_Core_Model_Resource_Db_Abstract
{
    protected function _construct(): void
    {
        $this->_init('customersegmentation/segment', 'segment_id');
    }

    public function getMatchingCustomerIds(Maho_CustomerSegmentation_Model_Segment $segment, ?int $websiteId = null): array
    {
        $select = $this->_getReadAdapter()->select()
            ->from(['e' => $this->getTable('customer/entity')], ['entity_id']);

        // Apply website filter
        $websiteIds = $websiteId ? [$websiteId] : $segment->getWebsiteIdsArray();
        if (!empty($websiteIds)) {
            $select->where('e.website_id IN (?)', $websiteIds);
        }

        // Apply customer group filter
        $groupIds = $segment->getCustomerGroupIdsArray();
        if (!empty($groupIds)) {
            $select->where('e.group_id IN (?)', $groupIds);
        }

        // Apply segment conditions
        $conditions = $segment->getConditions();
        if ($conditions) {
            $conditionsSql = $conditions->getConditionsSql($this->_getReadAdapter(), $websiteId);
            if ($conditionsSql) {
                $select->where($conditionsSql);
            }
        }

        // Only active customers
        $select->where('e.is_active = ?', 1);

        return $this->_getReadAdapter()->fetchCol($select);
    }

    public function updateCustomerMembership(Maho_CustomerSegmentation_Model_Segment $segment, array $customerIds): self
    {
        $adapter = $this->_getWriteAdapter();
        $segmentId = $segment->getId();
        $segmentCustomerTable = $this->getTable('customersegmentation/segment_customer');

        // Get current members
        $currentMembers = $adapter->fetchPairs(
            $adapter->select()
                ->from($segmentCustomerTable, ['customer_id', 'website_id'])
                ->where('segment_id = ?', $segmentId),
        );

        // Prepare new members data
        $newMembers = [];
        foreach ($customerIds as $customerId) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            if ($customer->getId()) {
                $newMembers[$customerId] = $customer->getWebsiteId();
            }
        }

        // Delete removed members
        $toDelete = array_diff_key($currentMembers, $newMembers);
        if (!empty($toDelete)) {
            $adapter->delete($segmentCustomerTable, [
                'segment_id = ?' => $segmentId,
                'customer_id IN (?)' => array_keys($toDelete),
            ]);
        }

        // Insert new members
        $toInsert = array_diff_key($newMembers, $currentMembers);
        if (!empty($toInsert)) {
            $insertData = [];
            foreach ($toInsert as $customerId => $websiteId) {
                $insertData[] = [
                    'segment_id'  => $segmentId,
                    'customer_id' => $customerId,
                    'website_id'  => $websiteId,
                    'added_at'    => now(),
                    'updated_at'  => now(),
                ];
            }
            $adapter->insertMultiple($segmentCustomerTable, $insertData);
        }

        return $this;
    }

    public function isCustomerInSegment(int $segmentId, int $customerId, ?int $websiteId = null): bool
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->getTable('customersegmentation/segment_customer'), ['customer_id'])
            ->where('segment_id = ?', $segmentId)
            ->where('customer_id = ?', $customerId);

        if ($websiteId !== null) {
            $select->where('website_id = ?', $websiteId);
        }

        return (bool) $this->_getReadAdapter()->fetchOne($select);
    }

    public function applySegmentToCollection(Maho_CustomerSegmentation_Model_Segment $segment, Mage_Customer_Model_Resource_Customer_Collection $collection): self
    {
        $collection->getSelect()->joinInner(
            ['segment_customer' => $this->getTable('customersegmentation/segment_customer')],
            'segment_customer.customer_id = e.entity_id',
            [],
        )->where('segment_customer.segment_id = ?', $segment->getId());

        return $this;
    }

    public function getCustomerSegmentIds(int $customerId, ?int $websiteId = null): array
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->getTable('customersegmentation/segment_customer'), ['segment_id'])
            ->where('customer_id = ?', $customerId);

        if ($websiteId !== null) {
            $select->where('website_id = ?', $websiteId);
        }

        return $this->_getReadAdapter()->fetchCol($select);
    }

    public function getActiveSegmentIds(int $websiteId): array
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->getMainTable(), ['segment_id'])
            ->where('is_active = ?', 1)
            ->where('FIND_IN_SET(?, website_ids)', $websiteId);

        return $this->_getReadAdapter()->fetchCol($select);
    }

    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object): self
    {
        // Serialize conditions
        if ($object->getConditions()) {
            $object->setConditionsSerialized(serialize($object->getConditions()->asArray()));
        }

        return parent::_beforeSave($object);
    }
}
