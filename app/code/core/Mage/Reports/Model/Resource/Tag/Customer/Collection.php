<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Reports
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Reports_Model_Resource_Tag_Customer_Collection extends Mage_Tag_Model_Resource_Customer_Collection
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->_useAnalyticFunction = true;
    }
    /**
     * Add target count
     *
     * @return $this
     */
    public function addTagedCount()
    {
        $this->getSelect()
            ->columns(['taged' => 'COUNT(tr.tag_relation_id)']);
        return $this;
    }

    /**
     * get select count sql
     *
     * @return Maho\Db\Select
     */
    #[\Override]
    public function getSelectCountSql()
    {
        $countSelect = clone $this->getSelect();
        $countSelect->reset(Maho\Db\Select::ORDER);
        $countSelect->reset(Maho\Db\Select::GROUP);
        $countSelect->reset(Maho\Db\Select::LIMIT_COUNT);
        $countSelect->reset(Maho\Db\Select::LIMIT_OFFSET);
        $countSelect->reset(Maho\Db\Select::COLUMNS);
        $countSelect->columns('COUNT(DISTINCT tr.customer_id)');

        return $countSelect;
    }
}
