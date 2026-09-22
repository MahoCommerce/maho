<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

/**
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Group _getResource()
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Group getResource()
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Group_Collection getCollection()
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Group_Collection getResourceCollection()
 */
class Mage_Eav_Model_Entity_Attribute_Group extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('eav/entity_attribute_group');
    }

    /**
     * Checks if current attribute group exists
     *
     * @return bool
     */
    public function itemExists()
    {
        return $this->_getResource()->itemExists($this);
    }

    /**
     * Delete groups
     *
     * @return $this
     */
    public function deleteGroups()
    {
        return $this->_getResource()->deleteGroups($this);
    }

    public function getAttributeGroupName(): ?string
    {
        $value = $this->getData('attribute_group_name');
        return $value === null ? null : (string) $value;
    }

    public function setAttributeGroupName(?string $value): static
    {
        return $this->setData('attribute_group_name', $value);
    }

    public function getAttributeSetId(): ?int
    {
        $value = $this->getData('attribute_set_id');
        return $value === null ? null : (int) $value;
    }

    public function setAttributeSetId(?int $value): static
    {
        return $this->setData('attribute_set_id', $value);
    }

    public function getAttributes(): ?array
    {
        return $this->getData('attributes');
    }

    public function setAttributes(?array $value): static
    {
        return $this->setData('attributes', $value);
    }

    public function getDefaultId(): ?int
    {
        $value = $this->getData('default_id');
        return $value === null ? null : (int) $value;
    }

    public function setDefaultId(?int $value): static
    {
        return $this->setData('default_id', $value);
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
