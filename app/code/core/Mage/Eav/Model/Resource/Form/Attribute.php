<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

abstract class Mage_Eav_Model_Resource_Form_Attribute extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Return form attribute IDs by form code
     *
     * @param string $formCode
     * @return list<int|string>
     */
    public function getFormAttributeIds($formCode)
    {
        $bind   = ['form_code' => $formCode];
        $select = $this->_getReadAdapter()->select()
            ->from($this->getMainTable(), 'attribute_id')
            ->where('form_code = :form_code');

        return $this->_getReadAdapter()->fetchIds($select, $bind);
    }
}
