<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Resource_Report_Bestsellers_Collection extends Mage_Sales_Model_Resource_Report_Collection_Abstract
{
    /**
     * Rating limit
     *
     * @var int
     */
    protected $_ratingLimit        = 5;

    /**
     * Columns for select
     *
     * @var array
     */
    protected $_selectedColumns    = [];

    /**
     * Initialize custom resource model
     */
    public function __construct()
    {
        parent::_construct();
        $this->setModel('adminhtml/report_item');
        $this->_resource = Mage::getResourceModel('sales/report')->init('sales/bestsellers_aggregated_daily');
        $this->setConnection($this->getResource()->getReadConnection());
        // overwrite default behaviour
        $this->_applyFilters = false;
    }

    /**
     * Retrieve columns for select
     *
     * @return array
     */
    protected function _getSelectedColumns()
    {
        $adapter = $this->getConnection();

        if (!$this->_selectedColumns) {
            if ($this->isTotals()) {
                $this->_selectedColumns = $this->getAggregatedColumns();
            } else {
                $this->_selectedColumns = [
                    'period'          =>  sprintf('MAX(%s)', $adapter->getDateFormatSql('period', '%Y-%m-%d')),
                    'qty_ordered'     => 'SUM(qty_ordered)',
                    'product_id'      => 'product_id',
                    'product_name'    => 'MAX(product_name)',
                    'product_price'   => 'MAX(product_price)',
                    'product_type_id' => 'MAX(product_type_id)',
                ];
                if ($this->_period == 'year') {
                    $this->_selectedColumns['period'] = $adapter->getDateFormatSql('period', '%Y');
                } elseif ($this->_period == 'month') {
                    $this->_selectedColumns['period'] = $adapter->getDateFormatSql('period', '%Y-%m');
                }
            }
        }
        return $this->_selectedColumns;
    }

    /**
     * Make select object for date boundary
     *
     * @param mixed $from
     * @param mixed $to
     * @return Maho\Db\Select
     */
    protected function _makeBoundarySelect($from, $to)
    {
        $adapter = $this->getConnection();
        $cols    = $this->_getSelectedColumns();
        $cols['qty_ordered'] = 'SUM(qty_ordered)';
        $sel     = $adapter->select()
            ->from($this->getResource()->getMainTable(), $cols)
            ->where('period >= ?', $from)
            ->where('period <= ?', $to)
            ->group('product_id')
            ->order('qty_ordered DESC')
            ->limit($this->_ratingLimit);

        $this->_applyProductTypeFilter($sel);
        $this->_applyStoresFilterToSelect($sel);

        return $sel;
    }

    /**
     * Add selected data
     *
     * @return $this
     */
    #[\Override]
    protected function _initSelect()
    {
        $select = $this->getSelect();

        // if grouping by product, not by period
        if (!$this->_period) {
            $cols = $this->_getSelectedColumns();
            $cols['qty_ordered'] = 'SUM(qty_ordered)';
            if ($this->_from || $this->_to) {
                $mainTable = $this->getTable('sales/bestsellers_aggregated_daily');
                $select->from($mainTable, $cols);
            } else {
                $mainTable = $this->getTable('sales/bestsellers_aggregated_yearly');
                $select->from($mainTable, $cols);
            }

            //exclude removed products
            $subSelect = $this->getConnection()->select();
            $subSelect->from(['existed_products' => $this->getTable('catalog/product')], new Maho\Db\Expr('1)'));

            $select->exists($subSelect, $mainTable . '.product_id = existed_products.entity_id')
                ->group('product_id')
                ->order('qty_ordered ' . Maho\Db\Select::SQL_DESC)
                ->limit($this->_ratingLimit);

            return $this;
        }

        if ($this->_period == 'year') {
            $mainTable = $this->getTable('sales/bestsellers_aggregated_yearly');
            $select->from($mainTable, $this->_getSelectedColumns());
        } elseif ($this->_period == 'month') {
            $mainTable = $this->getTable('sales/bestsellers_aggregated_monthly');
            $select->from($mainTable, $this->_getSelectedColumns());
        } else {
            $mainTable = $this->getTable('sales/bestsellers_aggregated_daily');
            $select->from($mainTable, $this->_getSelectedColumns());
        }
        if (!$this->isTotals()) {
            $select->group(['period', 'product_id']);
        }
        $select->where('rating_pos <= ?', $this->_ratingLimit);

        return $this;
    }

    /**
     * Redeclare parent method for applying filters after parent method
     * but before adding unions and calculating totals
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeLoad()
    {
        parent::_beforeLoad();

        $this->_applyStoresFilter();

        if ($this->_period) {
            $selectUnions = [];

            // apply date boundaries (before calling $this->_applyDateRangeFilter())
            $dtFormat   = Mage_Core_Model_Locale::DATE_FORMAT;
            $periodFrom = (is_null($this->_from) ? null : (DateTime::createFromFormat(Mage_Core_Model_Locale::DATE_FORMAT, $this->_from) ?: new DateTime($this->_from)));
            $periodTo   = (is_null($this->_to) ? null : (DateTime::createFromFormat(Mage_Core_Model_Locale::DATE_FORMAT, $this->_to) ?: new DateTime($this->_to)));
            if ($this->_period == 'year') {
                if ($periodFrom) {
                    // not the first day of the year
                    if ((int) $periodFrom->format('n') != 1 || (int) $periodFrom->format('j') != 1) {
                        $dtFrom = DateTimeImmutable::createFromMutable($periodFrom);
                        // last day of the year
                        $dtTo = DateTimeImmutable::createFromMutable($periodFrom)
                            ->setDate((int) $periodFrom->format('Y'), 12, 31);
                        if (!$periodTo || $dtTo < $periodTo) {
                            $selectUnions[] = $this->_makeBoundarySelect(
                                $dtFrom->format(Mage_Core_Model_Locale::DATE_FORMAT),
                                $dtTo->format(Mage_Core_Model_Locale::DATE_FORMAT),
                            );

                            // first day of the next year
                            $this->_from = DateTimeImmutable::createFromMutable($periodFrom)
                                ->modify('+1 year')
                                ->setDate($periodFrom->format('Y') + 1, 1, 1)
                                ->format(Mage_Core_Model_Locale::DATE_FORMAT);
                        }
                    }
                }

                if ($periodTo) {
                    // not the last day of the year
                    if ($periodTo->format('n') != 12 || $periodTo->format('j') != 31) {
                        $dtFrom = DateTimeImmutable::createFromMutable($periodTo)
                            ->setDate((int) $periodTo->format('Y'), 1, 1);  // first day of the year
                        $dtTo = DateTimeImmutable::createFromMutable($periodTo);
                        if (!$periodFrom || $dtFrom > $periodFrom) {
                            $selectUnions[] = $this->_makeBoundarySelect(
                                $dtFrom->format(Mage_Core_Model_Locale::DATE_FORMAT),
                                $dtTo->format(Mage_Core_Model_Locale::DATE_FORMAT),
                            );

                            // last day of the previous year
                            $this->_to = DateTimeImmutable::createFromMutable($periodTo)
                                ->modify('-1 year')
                                ->setDate($periodTo->format('Y') - 1, 12, 31)
                                ->format(Mage_Core_Model_Locale::DATE_FORMAT);
                        }
                    }
                }

                if ($periodFrom && $periodTo) {
                    // the same year
                    if ($periodFrom->format('Y') == $periodTo->format('Y')) {
                        $dtFrom = DateTimeImmutable::createFromMutable($periodFrom);
                        $dtTo = DateTimeImmutable::createFromMutable($periodTo);
                        $selectUnions[] = $this->_makeBoundarySelect(
                            $dtFrom->format(Mage_Core_Model_Locale::DATE_FORMAT),
                            $dtTo->format(Mage_Core_Model_Locale::DATE_FORMAT),
                        );

                        $this->getSelect()->where('1<>1');
                    }
                }
            } elseif ($this->_period == 'month') {
                if ($periodFrom) {
                    // not the first day of the month
                    if ($periodFrom->format('j') != 1) {
                        $dtFrom = DateTimeImmutable::createFromMutable($periodFrom);
                        // last day of the month
                        $dtTo = DateTimeImmutable::createFromMutable($periodFrom)
                            ->modify('+1 month')
                            ->setDate((int) $periodFrom->format('Y'), (int) $periodFrom->format('n') + 1, 1)
                            ->modify('-1 day');
                        if (!$periodTo || $dtTo < $periodTo) {
                            $selectUnions[] = $this->_makeBoundarySelect(
                                $dtFrom->format(Mage_Core_Model_Locale::DATE_FORMAT),
                                $dtTo->format(Mage_Core_Model_Locale::DATE_FORMAT),
                            );

                            // first day of the next month
                            $this->_from = DateTimeImmutable::createFromMutable($periodFrom)
                                ->modify('+1 month')
                                ->setDate((int) $periodFrom->format('Y'), (int) $periodFrom->format('n') + 1, 1)
                                ->format(Mage_Core_Model_Locale::DATE_FORMAT);
                        }
                    }
                }

                if ($periodTo) {
                    // not the last day of the month
                    if ($periodTo->format('j') != $periodTo->format('t')) {
                        $dtFrom = DateTimeImmutable::createFromMutable($periodTo)
                            ->setDate((int) $periodTo->format('Y'), (int) $periodTo->format('n'), 1);  // first day of the month
                        $dtTo = DateTimeImmutable::createFromMutable($periodTo);
                        if (!$periodFrom || $dtFrom > $periodFrom) {
                            $selectUnions[] = $this->_makeBoundarySelect(
                                $dtFrom->format(Mage_Core_Model_Locale::DATE_FORMAT),
                                $dtTo->format(Mage_Core_Model_Locale::DATE_FORMAT),
                            );

                            // last day of the previous month
                            $this->_to = DateTimeImmutable::createFromMutable($periodTo)
                                ->setDate((int) $periodTo->format('Y'), (int) $periodTo->format('n'), 1)
                                ->modify('-1 day')
                                ->format(Mage_Core_Model_Locale::DATE_FORMAT);
                        }
                    }
                }

                if ($periodFrom && $periodTo) {
                    // the same month
                    if ($periodFrom->format('Y') == $periodTo->format('Y')
                        && $periodFrom->format('n') == $periodTo->format('n')
                    ) {
                        $dtFrom = DateTimeImmutable::createFromMutable($periodFrom);
                        $dtTo = DateTimeImmutable::createFromMutable($periodTo);
                        $selectUnions[] = $this->_makeBoundarySelect(
                            $dtFrom->format(Mage_Core_Model_Locale::DATE_FORMAT),
                            $dtTo->format(Mage_Core_Model_Locale::DATE_FORMAT),
                        );

                        $this->getSelect()->where('1<>1');
                    }
                }
            }

            $this->_applyDateRangeFilter();
            $this->_applyProductTypeFilter($this->getSelect());

            // add unions to select
            if ($selectUnions) {
                $unionParts = [];
                $cloneSelect = clone $this->getSelect();
                $helper = Mage::getResourceHelper('core');
                $unionParts[] = '(' . $cloneSelect . ')';
                foreach ($selectUnions as $union) {
                    $query = $helper->getQueryUsingAnalyticFunction($union);
                    $unionParts[] = '(' . $query . ')';
                }
                $this->getSelect()->reset()->union($unionParts, Maho\Db\Select::SQL_UNION_ALL);
            }

            if ($this->isTotals()) {
                // calculate total
                $cloneSelect = clone $this->getSelect();
                $this->getSelect()->reset()->from($cloneSelect, $this->getAggregatedColumns());
            } else {
                // add sorting
                $this->getSelect()->order(['period ASC', 'qty_ordered DESC']);
            }
        }

        return $this;
    }

    /**
     * Apply filter to exclude certain product types from the collection
     *
     * @return Mage_Sales_Model_Resource_Report_Collection_Abstract
     */
    protected function _applyProductTypeFilter(\Maho\Db\Select $select)
    {
        $select->where('product_type_id NOT IN (?)', Mage_Catalog_Model_Product_Type::getCompositeTypes());
        return $this;
    }
}
