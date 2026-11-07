<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

class Mage_Eav_Block_Adminhtml_Attribute_Grid extends Mage_Eav_Block_Adminhtml_Attribute_Grid_Abstract
{
    #[\Override]
    protected function _prepareCollection()
    {
        /** @var Mage_Eav_Model_Entity_Type $entityType */
        $entityType = Mage::registry('current_entity_type');
        $this->setCollection(Mage::helper('eav')->getManagedAttributeCollection($entityType));
        return parent::_prepareCollection();
    }
}
