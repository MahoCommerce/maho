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

class Mage_Reports_Model_Resource_Review_Collection extends Mage_Review_Model_Resource_Review_Collection
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('review/review');
    }

    /**
     * @param string|int $productId
     * @return $this
     */
    public function addProductFilter($productId)
    {
        $this->addFieldToFilter('entity_pk_value', ['eq' => (int) $productId]);

        return $this;
    }

    /**
     * Reset select
     *
     * @return $this
     */
    public function resetSelect()
    {
        $this->_joinFields();
        return $this;
    }

    /**
     * Get select count sql
     *
     * @return Maho\Db\Select
     */
    #[\Override]
    public function getSelectCountSql()
    {
        $countSelect = clone $this->_select;
        $countSelect->reset(Maho\Db\Select::ORDER);
        $countSelect->reset(Maho\Db\Select::LIMIT_COUNT);
        $countSelect->reset(Maho\Db\Select::LIMIT_OFFSET);
        $countSelect->reset(Maho\Db\Select::COLUMNS);
        $countSelect->columns('COUNT(main_table.review_id)');

        return $countSelect;
    }

    /**
     * Set order
     *
     * @param string $attribute
     * @param string $dir
     * @return $this
     */
    #[\Override]
    public function setOrder($attribute, $dir = self::SORT_ORDER_DESC)
    {
        if (in_array($attribute, ['nickname', 'title', 'detail', 'created_at'])) {
            $this->_select->order($attribute . ' ' . $dir);
        } else {
            parent::setOrder($attribute, $dir);
        }

        return $this;
    }
}
