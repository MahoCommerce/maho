<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Resource_Segment_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('customersegmentation/segment');
    }

    public function addCustomerCountToSelect(): self
    {
        $this->getSelect()->joinLeft(
            ['sc' => $this->getTable('customersegmentation/segment_customer')],
            'main_table.segment_id = sc.segment_id',
            ['customer_count' => 'COUNT(DISTINCT sc.customer_id)'],
        )->group('main_table.segment_id');

        return $this;
    }

    public function addWebsiteFilter(int|array $websiteId): self
    {
        if (is_array($websiteId)) {
            $condition = [];
            foreach ($websiteId as $id) {
                $condition[] = $this->getConnection()->quoteInto('FIND_IN_SET(?, website_ids)', $id);
            }
            $this->getSelect()->where(implode(' OR ', $condition));
        } else {
            $this->getSelect()->where('FIND_IN_SET(?, website_ids)', $websiteId);
        }

        return $this;
    }

    public function addIsActiveFilter(): self
    {
        $this->addFieldToFilter('is_active', 1);
        return $this;
    }

    public function addCustomerFilter(int $customerId): self
    {
        $this->getSelect()->joinInner(
            ['sc' => $this->getTable('customersegmentation/segment_customer')],
            'main_table.segment_id = sc.segment_id AND sc.customer_id = ' . (int) $customerId,
            [],
        );

        return $this;
    }

    #[\Override]
    public function toOptionArray(): array
    {
        return $this->_toOptionArray('segment_id', 'name');
    }

    #[\Override]
    public function toOptionHash(): array
    {
        return $this->_toOptionHash('segment_id', 'name');
    }

    public function setOrderByPriority(string $direction = self::SORT_ORDER_DESC): self
    {
        $this->setOrder('priority', $direction);
        return $this;
    }

    public function addRefreshStatusFilter(string|array $status): self
    {
        $this->addFieldToFilter('refresh_status', $status);
        return $this;
    }

    public function addAutoRefreshFilter(): self
    {
        $this->addFieldToFilter('refresh_mode', Maho_CustomerSegmentation_Model_Segment::MODE_AUTO);
        return $this;
    }

    public function addNeedsRefreshFilter(int $hoursAgo = 24): self
    {
        $this->addFieldToFilter(
            'last_refresh_at',
            [
                ['lt' => $this->getConnection()->getDateSubSql('NOW()', $hoursAgo, Varien_Db_Adapter_Interface::INTERVAL_HOUR)],
                ['null' => true],
            ],
        );
        return $this;
    }
}
