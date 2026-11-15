<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Model_Resource_Entity_Attribute_Group_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    /**
     * Init resource model for collection
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('eav/entity_attribute_group');
    }

    /**
     * Set Attribute Set Filter
     *
     * @param int $setId
     * @return $this
     */
    public function setAttributeSetFilter($setId)
    {
        $this->addFieldToFilter('attribute_set_id', ['eq' => $setId]);
        $this->setOrder('sort_order');
        return $this;
    }

    /**
     * Add the label of each group in $storeId as store_label, or null when the group has none.
     * Mage_Eav_Model_Entity_Attribute_Group::getStoreLabel() then needs no query.
     */
    public function addStoreLabel(int $storeId): static
    {
        $this->getSelect()->joinLeft(
            ['group_label' => $this->getTable('eav/attribute_group_label')],
            $this->getConnection()->quoteInto('group_label.attribute_group_id = main_table.attribute_group_id AND group_label.store_id = ?', $storeId),
            ['store_label' => 'value'],
        );
        return $this;
    }

    /**
     * Set sort order
     *
     * @param string $direction
     * @return $this
     */
    public function setSortOrder($direction = self::SORT_ORDER_ASC)
    {
        return $this->addOrder('sort_order', $direction);
    }
}
