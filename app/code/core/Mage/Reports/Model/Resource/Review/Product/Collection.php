<?php

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Reports_Model_Resource_Review_Product_Collection extends Mage_Catalog_Model_Resource_Product_Collection
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_useAnalyticFunction = true;
    }
    /**
     * Join review table to result
     *
     * @return $this
     */
    public function joinReview()
    {
        $helper    = Mage::getResourceHelper('core');

        $subSelect = clone $this->getSelect();
        $subSelect->reset()
            ->from(['rev' => $this->getTable('review/review')], 'COUNT(DISTINCT rev.review_id)')
            ->where('e.entity_id = rev.entity_pk_value');

        $this->addAttributeToSelect('name');

        $this->getSelect()
            ->join(
                ['r' => $this->getTable('review/review')],
                'e.entity_id = r.entity_pk_value',
                [
                    'review_cnt'    => new Maho\Db\Expr(sprintf('(%s)', $subSelect)),
                    'last_created'  => new Maho\Db\Expr('MAX(r.created_at)'),
                ],
            )
            ->group('e.entity_id');

        $joinCondition      = [
            'e.entity_id = table_rating.entity_pk_value',
            $this->getConnection()->quoteInto('table_rating.store_id > ?', 0),
        ];

        $percentField       = $this->getConnection()->quoteIdentifier('table_rating.percent');
        $sumPercentField    = "SUM({$percentField})";
        $sumPercentApproved = 'SUM(table_rating.percent_approved)';
        $countRatingId      = 'COUNT(table_rating.rating_id)';

        $this->getSelect()
            ->joinLeft(
                ['table_rating' => $this->getTable('rating/rating_vote_aggregated')],
                implode(' AND ', $joinCondition),
                [
                    'avg_rating'          => new Maho\Db\Expr("$sumPercentField / $countRatingId"),
                    'avg_rating_approved' => new Maho\Db\Expr("$sumPercentApproved / $countRatingId"),
                ],
            );

        return $this;
    }

    #[\Override]
    public function addAttributeToSort($attribute, $dir = self::SORT_ORDER_ASC)
    {
        if (in_array($attribute, ['review_cnt', 'last_created', 'avg_rating', 'avg_rating_approved'])) {
            $this->getSelect()->order($attribute . ' ' . $dir);
            return $this;
        }

        return parent::addAttributeToSort($attribute, $dir);
    }

    /**
     * Get select count sql
     *
     * @return Maho\Db\Select
     */
    #[\Override]
    public function getSelectCountSql()
    {
        $this->_renderFilters();

        $select = clone $this->getSelect();
        $select->reset(Maho\Db\Select::ORDER);
        $select->reset(Maho\Db\Select::LIMIT_COUNT);
        $select->reset(Maho\Db\Select::LIMIT_OFFSET);
        $select->reset(Maho\Db\Select::COLUMNS);
        $select->resetJoinLeft();
        $select->columns(new Maho\Db\Expr('1'));

        $countSelect = clone $select;
        $countSelect->reset();
        $countSelect->from($select, 'COUNT(*)');

        return $countSelect;
    }
}
