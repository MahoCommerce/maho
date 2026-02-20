<?php

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Reports_Model_Resource_Report_Product_Viewed extends Mage_Sales_Model_Resource_Report_Abstract
{
    public const AGGREGATION_DAILY   = 'reports/viewed_aggregated_daily';
    public const AGGREGATION_MONTHLY = 'reports/viewed_aggregated_monthly';
    public const AGGREGATION_YEARLY  = 'reports/viewed_aggregated_yearly';

    #[\Override]
    protected function _construct()
    {
        $this->_init(self::AGGREGATION_DAILY, 'id');
    }

    /**
     * Aggregate products view data
     *
     * @param mixed $from
     * @param mixed $to
     * @return Mage_Reports_Model_Resource_Report_Product_Viewed
     * @throws Mage_Core_Exception
     */
    public function aggregate($from = null, $to = null)
    {
        $mainTable   = $this->getMainTable();
        $adapter = $this->_getWriteAdapter();

        // convert input dates to UTC to be comparable with DATETIME fields in DB
        $from = $this->_dateToUtc($from);
        $to = $this->_dateToUtc($to);

        $this->_checkDates($from, $to);

        if ($from !== null || $to !== null) {
            $subSelect = $this->_getTableDateRangeSelect(
                $this->getTable('reports/event'),
                'logged_at',
                'logged_at',
                $from,
                $to,
            );
        } else {
            $subSelect = null;
        }
        $this->_clearTableByDateRange($mainTable, $from, $to, $subSelect);
        // convert dates from UTC to current admin timezone
        $periodExpr = $adapter->getDatePartSql(
            $this->getStoreTZOffsetQuery(
                ['source_table' => $this->getTable('reports/event')],
                'source_table.logged_at',
                $from,
                $to,
            ),
        );

        $helper = Mage::getResourceHelper('core');
        $select = $adapter->select();

        $select->group([
            $periodExpr,
            'source_table.store_id',
            'source_table.object_id',
        ]);

        $viewsNumExpr = new Maho\Db\Expr('COUNT(source_table.event_id)');

        $columns = [
            'period'                 => $periodExpr,
            'store_id'               => 'source_table.store_id',
            'product_id'             => 'source_table.object_id',
            'product_name'           => new Maho\Db\Expr(
                sprintf(
                    'MIN(%s)',
                    $adapter->getIfNullSql('product_name.value', 'product_default_name.value'),
                ),
            ),
            'product_price'          => new Maho\Db\Expr(
                sprintf(
                    '%s',
                    $helper->prepareColumn(
                        sprintf(
                            'MIN(%s)',
                            $adapter->getIfNullSql(
                                $adapter->getIfNullSql('product_price.value', 'product_default_price.value'),
                                0,
                            ),
                        ),
                        $select->getPart(Maho\Db\Select::GROUP),
                    ),
                ),
            ),
            'views_num'            => $viewsNumExpr,
        ];

        $select
            ->from(
                [
                    'source_table' => $this->getTable('reports/event')],
                $columns,
            )
            ->where('source_table.event_type_id = ?', Mage_Reports_Model_Event::EVENT_PRODUCT_VIEW);

        /** @var Mage_Catalog_Model_Resource_Product $product */
        $product  = Mage::getResourceSingleton('catalog/product');

        $select->joinInner(
            [
                'product' => $this->getTable('catalog/product')],
            'product.entity_id = source_table.object_id',
            [],
        );

        // join product attributes Name & Price
        $nameAttribute = $product->getAttribute('name');
        $joinExprProductName       = [
            'product_name.entity_id = product.entity_id',
            'product_name.store_id = source_table.store_id',
            $adapter->quoteInto('product_name.attribute_id = ?', $nameAttribute->getAttributeId()),
        ];
        $joinExprProductName        = implode(' AND ', $joinExprProductName);
        $joinExprProductDefaultName = [
            'product_default_name.entity_id = product.entity_id',
            'product_default_name.store_id = 0',
            $adapter->quoteInto('product_default_name.attribute_id = ?', $nameAttribute->getAttributeId()),
        ];
        $joinExprProductDefaultName = implode(' AND ', $joinExprProductDefaultName);
        $select->joinLeft(
            [
                'product_name' => $nameAttribute->getBackend()->getTable()],
            $joinExprProductName,
            [],
        )
        ->joinLeft(
            [
                'product_default_name' => $nameAttribute->getBackend()->getTable()],
            $joinExprProductDefaultName,
            [],
        );
        $priceAttribute                    = $product->getAttribute('price');
        $joinExprProductPrice    = [
            'product_price.entity_id = product.entity_id',
            'product_price.store_id = source_table.store_id',
            $adapter->quoteInto('product_price.attribute_id = ?', $priceAttribute->getAttributeId()),
        ];
        $joinExprProductPrice    = implode(' AND ', $joinExprProductPrice);

        $joinExprProductDefPrice = [
            'product_default_price.entity_id = product.entity_id',
            'product_default_price.store_id = 0',
            $adapter->quoteInto('product_default_price.attribute_id = ?', $priceAttribute->getAttributeId()),
        ];
        $joinExprProductDefPrice = implode(' AND ', $joinExprProductDefPrice);
        $select->joinLeft(
            ['product_price' => $priceAttribute->getBackend()->getTable()],
            $joinExprProductPrice,
            [],
        )
        ->joinLeft(
            ['product_default_price' => $priceAttribute->getBackend()->getTable()],
            $joinExprProductDefPrice,
            [],
        );

        // Filter by date range directly on source column (WHERE is evaluated before GROUP BY,
        // so we can't use the 'period' alias here - it doesn't exist yet)
        if ($from !== null) {
            $select->where('source_table.logged_at >= ?', $from);
        }
        if ($to !== null) {
            $select->where('source_table.logged_at <= ?', $to);
        }
        $select->having($adapter->prepareSqlCondition($viewsNumExpr, ['gt' => 0]));

        $select->useStraightJoin();
        $insertQuery = $helper->getInsertFromSelectUsingAnalytic(
            $select,
            $this->getMainTable(),
            array_keys($columns),
        );
        $adapter->query($insertQuery);

        $helper = Mage::getResourceHelper('reports');
        $helper
            ->updateReportRatingPos('day', 'views_num', $mainTable, $this->getTable(self::AGGREGATION_DAILY));
        $helper
            ->updateReportRatingPos('month', 'views_num', $mainTable, $this->getTable(self::AGGREGATION_MONTHLY));
        $helper
            ->updateReportRatingPos('year', 'views_num', $mainTable, $this->getTable(self::AGGREGATION_YEARLY));

        $this->_setFlagData(Mage_Reports_Model_Flag::REPORT_PRODUCT_VIEWED_FLAG_CODE);

        return $this;
    }
}
