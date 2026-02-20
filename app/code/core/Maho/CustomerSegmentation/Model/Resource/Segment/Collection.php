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
        $adapter = $this->getConnection();
        if (is_array($websiteId)) {
            $condition = [];
            foreach ($websiteId as $id) {
                $condition[] = (string) $adapter->getFindInSetExpr($adapter->quote($id), 'website_ids');
            }
            $this->getSelect()->where(implode(' OR ', $condition));
        } else {
            $findInSet = $adapter->getFindInSetExpr($adapter->quote($websiteId), 'website_ids');
            $this->getSelect()->where((string) $findInSet);
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
        $cutoffDateTime = Mage::app()->getLocale()->utcDate(null, null, true);
        $cutoffDateTime->sub(new DateInterval("PT{$hoursAgo}H"));
        $cutoffDate = $cutoffDateTime->format(Mage_Core_Model_Locale::DATETIME_FORMAT);

        $this->addFieldToFilter(
            'last_refresh_at',
            [
                ['lt' => $cutoffDate],
                ['null' => true],
            ],
        );
        return $this;
    }
}
