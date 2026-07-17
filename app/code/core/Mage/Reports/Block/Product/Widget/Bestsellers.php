<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

/**
 * Frontend widget listing the most-sold products for a configurable period.
 */
class Mage_Reports_Block_Product_Widget_Bestsellers extends Mage_Catalog_Block_Product_Widget_Abstract
{
    public const DEFAULT_PERIOD = 'all_time';

    protected $_pageVarName = 'bs';
    protected $_cacheKeyPrefix = 'REPORTS_PRODUCT_WIDGET_BESTSELLERS';

    public function getPeriod(): string
    {
        if (!$this->hasData('period')) {
            $this->setData('period', self::DEFAULT_PERIOD);
        }
        return (string) $this->getData('period');
    }

    #[\Override]
    public function getCacheKeyInfo()
    {
        return array_merge(parent::getCacheKeyInfo(), [
            $this->getPeriod(),
            (int) $this->onlyInStock(),
        ]);
    }

    /**
     * Build the product collection ordered by quantity sold for the selected period.
     */
    #[\Override]
    protected function _getProductCollection(): Mage_Catalog_Model_Resource_Product_Collection
    {
        $orderedIds = $this->_getBestsellerProductIds();

        $collection = $this->_prepareStorefrontCollection($orderedIds);
        if (!empty($orderedIds)) {
            $collection->getSelect()->order($this->_getOrderByIdsExpr($orderedIds));
            $collection->setPageSize($this->getProductsCount())->setCurPage(1);
        }

        return $collection;
    }

    /**
     * Most-sold product ids for the selected period, ordered by quantity sold descending.
     * A buffer is fetched because some ids are dropped by later visibility/status/stock filters.
     *
     * @return int[]
     */
    protected function _getBestsellerProductIds(): array
    {
        [$from, $to] = $this->_getDateRange();

        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');

        $select = $adapter->select()
            ->from(['oi' => $resource->getTableName('sales/order_item')], ['product_id'])
            ->joinInner(
                ['o' => $resource->getTableName('sales/order')],
                'o.entity_id = oi.order_id',
                [],
            )
            ->where('o.state <> ?', Mage_Sales_Model_Order::STATE_CANCELED)
            ->where('oi.parent_item_id IS NULL')
            ->where('oi.store_id = ?', (int) Mage::app()->getStore()->getId())
            ->group('oi.product_id')
            ->order(new Maho\Db\Expr('SUM(oi.qty_ordered) DESC'))
            ->limit($this->getProductsCount() * 4);

        if ($from !== null) {
            $select->where('o.created_at >= ?', $from)
                ->where('o.created_at <= ?', $to);
        }

        return array_map('intval', $adapter->fetchCol($select));
    }

    /**
     * Resolve the configured period to a [from, to] pair of UTC datetime strings.
     * A null "from" means "all time" (no date restriction).
     *
     * @return array{0: ?string, 1: ?string}
     */
    protected function _getDateRange(): array
    {
        $period = $this->getPeriod();
        if ($period === 'all_time') {
            return [null, null];
        }

        $locale = Mage::app()->getLocale();
        $nowStore = $locale->utcToStore();

        // Boundaries are computed in store TZ so month/year align to the merchant's calendar,
        // then converted to UTC for the (UTC) sales_order.created_at column.
        $fromStore = match ($period) {
            'last_7_days' => $nowStore->modify('-7 days'),
            'last_30_days' => $nowStore->modify('-30 days'),
            'month' => $nowStore->modify('first day of this month')->setTime(0, 0, 0),
            'year' => $nowStore->modify('first day of January')->setTime(0, 0, 0),
            default => $nowStore->modify('-30 days'),
        };

        $from = $fromStore->setTimezone(new DateTimeZone('UTC'))->format(Mage_Core_Model_Locale::DATETIME_FORMAT);

        return [$from, $locale->nowUtc()];
    }
}
