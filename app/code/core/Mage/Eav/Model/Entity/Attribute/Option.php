<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

/**
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Option _getResource()
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Option getResource()
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Option_Collection getCollection()
 */
class Mage_Eav_Model_Entity_Attribute_Option extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('eav/entity_attribute_option');
    }

    /**
     * Retrieve swatch hex value
     *
     * @return string|false
     */
    public function getSwatchValue()
    {
        $swatch = Mage::getModel('eav/entity_attribute_option_swatch')
            ->load($this->getId(), 'option_id');
        if (!$swatch->getId()) {
            return false;
        }
        return $swatch->getValue();
    }

    public function getAttributeId(): ?int
    {
        $value = $this->getData('attribute_id');
        return $value === null ? null : (int) $value;
    }

    public function setAttributeId(?int $value): static
    {
        return $this->setData('attribute_id', $value);
    }

    public function getSortOrder(): ?int
    {
        $value = $this->getData('sort_order');
        return $value === null ? null : (int) $value;
    }

    public function setSortOrder(?int $value): static
    {
        return $this->setData('sort_order', $value);
    }

}
