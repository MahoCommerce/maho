<?php

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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

    /**
     * @return string
     */
    public function getPeriod()
    {
        if (!$this->hasData('period')) {
            $this->setData('period', self::DEFAULT_PERIOD);
        }
        return (string) $this->getData('period');
    }

    /**
     * @return bool
     */
    public function onlyInStock()
    {
        if (!$this->hasData('only_in_stock')) {
            $this->setData('only_in_stock', true);
        }
        return (bool) $this->getData('only_in_stock');
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
     *
     * @return Mage_Catalog_Model_Resource_Product_Collection
     */
    #[\Override]
    protected function _getProductCollection()
    {
        $orderedIds = $this->_getBestsellerProductIds();

        /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
        $collection = Mage::getResourceModel('catalog/product_collection');
        $collection->setVisibility(Mage_Catalog_Model_Product_Visibility::getVisibleInCatalogIds());

        if (empty($orderedIds)) {
            // No sales data yet: render nothing, but keep a valid, empty collection.
            $collection->getSelect()->where('1 = 0');
            return $collection;
        }

        $this->_addProductAttributesAndPrices($collection)
            ->addStoreFilter()
            ->addIdFilter($orderedIds)
            ->addAttributeToFilter('status', Mage_Catalog_Model_Product_Status::STATUS_ENABLED);

        if ($this->onlyInStock()) {
            Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($collection);
        }

        $collection->getSelect()->order($this->_getOrderByIdsExpr($orderedIds));
        $collection->setPageSize($this->getProductsCount())->setCurPage(1);

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

        /** @var Mage_Reports_Model_Resource_Product_Sold_Collection $sold */
        $sold = Mage::getResourceModel('reports/product_sold_collection');
        if ($from !== null) {
            $sold->setDateRange($from, $to);
        } else {
            $sold->addOrderedQty()->setOrder('ordered_qty', Maho\Data\Collection::SORT_ORDER_DESC);
        }
        $sold->setStoreIds([Mage::app()->getStore()->getId()]);
        $sold->setPageSize($this->getProductsCount() * 4)->setCurPage(1);

        $ids = [];
        foreach ($sold as $item) {
            $id = (int) $item->getEntityId();
            if ($id) {
                $ids[] = $id;
            }
        }
        return $ids;
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
