<?php

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Reports_Model_Resource_Report_Product_Viewed_Collection extends Mage_Reports_Model_Resource_Report_Collection_Abstract
{
    /**
     * Rating limit
     *
     * @var int
     */
    protected $_ratingLimit        = 5;

    /**
     * Selected columns
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
        $this->_resource = Mage::getResourceModel('sales/report')
            ->init(Mage_Reports_Model_Resource_Report_Product_Viewed::AGGREGATION_DAILY);
        $this->setConnection($this->getResource()->getReadConnection());
        // overwrite default behaviour
        $this->_applyFilters = false;
    }

    /**
     * Retrieve selected columns
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
                    'period'         =>  sprintf('MAX(%s)', $adapter->getDateFormatSql('period', '%Y-%m-%d')),
                    'views_num'      => 'SUM(views_num)',
                    'product_id'     => 'product_id',
                    'product_name'   => 'MAX(product_name)',
                    'product_price'  => 'MAX(product_price)',
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
        $cols['views_num'] = 'SUM(views_num)';
        $select  = $adapter->select()
            ->from($this->getResource()->getMainTable(), $cols)
            ->where('period >= ?', $from)
            ->where('period <= ?', $to)
            ->group('product_id')
            ->order('views_num DESC')
            ->limit($this->_ratingLimit);

        $this->_applyStoresFilterToSelect($select);

        return $select;
    }

    /**
     * Init collection select
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
            $cols['views_num'] = 'SUM(views_num)';
            if ($this->_from || $this->_to) {
                $mainTable = $this->getTable(Mage_Reports_Model_Resource_Report_Product_Viewed::AGGREGATION_DAILY);
                $select->from($mainTable, $cols);
            } else {
                $mainTable = $this->getTable(Mage_Reports_Model_Resource_Report_Product_Viewed::AGGREGATION_YEARLY);
                $select->from($mainTable, $cols);
            }

            //exclude removed products
            $subSelect = $this->getConnection()->select();
            $subSelect->from(['existed_products' => $this->getTable('catalog/product')], new Maho\Db\Expr('1)'));

            $select->exists($subSelect, $mainTable . '.product_id = existed_products.entity_id')
                ->group('product_id')
                ->order('views_num ' . Maho\Db\Select::SQL_DESC)
                ->limit($this->_ratingLimit);

            return $this;
        }

        if ($this->_period == 'year') {
            $mainTable = $this->getTable(Mage_Reports_Model_Resource_Report_Product_Viewed::AGGREGATION_YEARLY);
            $select->from($mainTable, $this->_getSelectedColumns());
        } elseif ($this->_period == 'month') {
            $mainTable = $this->getTable(Mage_Reports_Model_Resource_Report_Product_Viewed::AGGREGATION_MONTHLY);
            $select->from($mainTable, $this->_getSelectedColumns());
        } else {
            $mainTable = $this->getTable(Mage_Reports_Model_Resource_Report_Product_Viewed::AGGREGATION_DAILY);
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
                        $dtTo = DateTimeImmutable::createFromMutable($periodFrom)->setDate((int) $periodFrom->format('Y'), 12, 31);
                        if (!$periodTo || $dtTo < $periodTo) {
                            $selectUnions[] = $this->_makeBoundarySelect(
                                $dtFrom->format($dtFormat),
                                $dtTo->format($dtFormat),
                            );

                            // first day of the next year
                            $this->_from = DateTimeImmutable::createFromMutable($periodFrom)
                                ->modify('+1 year')
                                ->setDate((int) $periodFrom->format('Y') + 1, 1, 1)
                                ->format($dtFormat);
                        }
                    }
                }

                if ($periodTo) {
                    // not the last day of the year
                    if ($periodTo->format('n') != 12 || $periodTo->format('j') != 31) {
                        $dtFrom = DateTimeImmutable::createFromMutable($periodTo)->setDate((int) $periodTo->format('Y'), 1, 1);  // first day of the year
                        $dtTo = DateTimeImmutable::createFromMutable($periodTo);
                        if (!$periodFrom || $dtFrom > $periodFrom) {
                            $selectUnions[] = $this->_makeBoundarySelect(
                                $dtFrom->format($dtFormat),
                                $dtTo->format($dtFormat),
                            );

                            // last day of the previous year
                            $this->_to = DateTimeImmutable::createFromMutable($periodTo)
                                ->modify('-1 year')
                                ->setDate((int) $periodTo->format('Y') - 1, 12, 31)
                                ->format($dtFormat);
                        }
                    }
                }

                if ($periodFrom && $periodTo) {
                    // the same year
                    if ($periodFrom->format('Y') == $periodTo->format('Y')) {
                        $dtFrom = DateTimeImmutable::createFromMutable($periodFrom);
                        $dtTo = DateTimeImmutable::createFromMutable($periodTo);
                        $selectUnions[] = $this->_makeBoundarySelect(
                            $dtFrom->format($dtFormat),
                            $dtTo->format($dtFormat),
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
                        $dtTo = DateTimeImmutable::createFromMutable($periodFrom)->modify('+1 month')->setDate((int) $periodFrom->format('Y'), (int) $periodFrom->format('n') + 1, 1)->modify('-1 day');
                        if (!$periodTo || $dtTo < $periodTo) {
                            $selectUnions[] = $this->_makeBoundarySelect(
                                $dtFrom->format($dtFormat),
                                $dtTo->format($dtFormat),
                            );

                            // first day of the next month
                            $this->_from = DateTimeImmutable::createFromMutable($periodFrom)->modify('+1 month')->setDate((int) $periodFrom->format('Y'), (int) $periodFrom->format('n') + 1, 1)->format($dtFormat);
                        }
                    }
                }

                if ($periodTo) {
                    // not the last day of the month
                    if ($periodTo->format('j') != $periodTo->format('t')) {
                        $dtFrom = DateTimeImmutable::createFromMutable($periodTo)->setDate((int) $periodTo->format('Y'), (int) $periodTo->format('n'), 1);  // first day of the month
                        $dtTo = DateTimeImmutable::createFromMutable($periodTo);
                        if (!$periodFrom || $dtFrom > $periodFrom) {
                            $selectUnions[] = $this->_makeBoundarySelect(
                                $dtFrom->format($dtFormat),
                                $dtTo->format($dtFormat),
                            );

                            // last day of the previous month
                            $this->_to = DateTimeImmutable::createFromMutable($periodTo)->setDate((int) $periodTo->format('Y'), (int) $periodTo->format('n'), 1)->modify('-1 day')->format($dtFormat);
                        }
                    }
                }

                if ($periodFrom && $periodTo) {
                    // the same month
                    if ($periodFrom->format('Y') == $periodTo->format('Y')
                        && $periodFrom->format('n') == $periodTo->format('n')
                    ) {
                        $dtFrom = clone $periodFrom;
                        $dtTo = clone $periodTo;
                        $selectUnions[] = $this->_makeBoundarySelect(
                            $dtFrom->format($dtFormat),
                            $dtTo->format($dtFormat),
                        );

                        $this->getSelect()->where('1<>1');
                    }
                }
            }

            $this->_applyDateRangeFilter();

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
                $this->getSelect()->order(['period ASC', 'views_num DESC']);
            }
        }

        return $this;
    }
}
