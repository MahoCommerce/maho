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

class Maho_CustomerSegmentation_Model_Resource_Segment extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
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
        if ($conditions instanceof Maho_CustomerSegmentation_Model_Segment_Condition_Combine) {
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
                $websiteId = $customer->getWebsiteId();
                // Ensure websiteId is valid - fallback to default if not set
                if (!$websiteId) {
                    $websiteId = Mage::app()->getDefaultStoreView()->getWebsiteId();
                }
                $newMembers[$customerId] = (int) $websiteId;
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
                $utcDateTime = Mage::app()->getLocale()->utcDate(null, null, true);
                $nowString = $utcDateTime->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
                $insertData[] = [
                    'segment_id'  => $segmentId,
                    'customer_id' => $customerId,
                    'website_id'  => $websiteId,
                    'added_at'    => $nowString,
                    'updated_at'  => $nowString,
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
        $adapter = $this->_getReadAdapter();
        $findInSet = $adapter->getFindInSetExpr($adapter->quote($websiteId), 'website_ids');

        $select = $adapter->select()
            ->from($this->getMainTable(), ['segment_id'])
            ->where('is_active = ?', 1)
            ->where((string) $findInSet);

        return $adapter->fetchCol($select);
    }

    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object): self
    {
        // Encode conditions as JSON
        if ($object->getConditions()) {
            try {
                $object->setConditionsSerialized(Mage::helper('core')->jsonEncode($object->getConditions()->asArray()));
            } catch (\JsonException $e) {
                Mage::logException($e);
                throw $e;
            }
        }

        return parent::_beforeSave($object);
    }

    public function getWebsiteIds(?int $segmentId): array
    {
        if ($segmentId === null) {
            return [];
        }

        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable(), ['website_ids'])
            ->where('segment_id = ?', $segmentId);

        $websiteIds = $adapter->fetchOne($select);
        return $websiteIds ? explode(',', $websiteIds) : [];
    }

    public function getCustomerSegmentRelations(int|string $segmentId): array
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getTable('customersegmentation/segment_customer'), ['customer_id', 'website_id'])
            ->where('segment_id = ?', (int) $segmentId);

        return $adapter->fetchAll($select);
    }
}
