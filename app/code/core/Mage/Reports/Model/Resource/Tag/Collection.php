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
class Mage_Reports_Model_Resource_Tag_Collection extends Mage_Tag_Model_Resource_Popular_Collection
{
    /**
     * Add tag popularity to select by specified store ids
     *
     * @param int|array $storeIds
     * @return $this
     */
    public function addPopularity($storeIds)
    {
        $select = $this->getSelect()
            ->joinLeft(
                ['tr' => $this->getTable('tag/relation')],
                'main_table.tag_id = tr.tag_id AND tr.active = 1',
                ['popularity' => 'COUNT(tr.tag_id)'],
            );
        if (!empty($storeIds)) {
            $select->where('tr.store_id IN(?)', $storeIds);
        }

        $select->group('main_table.tag_id');

        /**
         * Allow to use analytic function
         */
        $this->_useAnalyticFunction = true;

        return $this;
    }
}
