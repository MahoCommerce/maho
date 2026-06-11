<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Model_Resource_Form_Element extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('eav/form_element', 'element_id');
        $this->addUniqueField([
            'field' => ['type_id', 'attribute_id'],
            'title' => Mage::helper('eav')->__('Form Element with the same attribute'),
        ]);
    }

    /**
     * Retrieve select object for load object data
     *
     * @param string $field
     * @param mixed $value
     * @param Mage_Eav_Model_Form_Element $object
     * @return Maho\Db\Select
     */
    #[\Override]
    protected function _getLoadSelect($field, $value, $object)
    {
        $select = parent::_getLoadSelect($field, $value, $object);
        $select->join(
            $this->getTable('eav/attribute'),
            $this->getTable('eav/attribute') . '.attribute_id = ' . $this->getMainTable() . '.attribute_id',
            ['attribute_code', 'entity_type_id'],
        );

        return $select;
    }
}
