<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

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

    /**
     * Return the labels of the group for each store view, by store id. A store view with no label
     * uses the group name.
     *
     * @return array<int, string>
     */
    public function getStoreLabels(): array
    {
        if (!$this->hasData('store_labels')) {
            $this->setData('store_labels', $this->getId() ? $this->_getResource()->getStoreLabels((int) $this->getId()) : []);
        }
        return $this->getData('store_labels');
    }

    /**
     * Set the labels of the group for each store view, by store id. The save replaces all labels.
     *
     * @param array<int, string>|null $value
     */
    public function setStoreLabels(?array $value): static
    {
        return $this->setData('store_labels', $value);
    }

    /**
     * Return the label of the group in $storeId as plain text, or the group name when the store
     * view has no label. The set editor saves the group name with HTML entities, so this decodes it.
     */
    public function getStoreLabel(?int $storeId = null): string
    {
        $storeId ??= (int) Mage::app()->getStore()->getId();
        $label = $this->hasData('store_label') ? $this->getData('store_label') : ($this->getStoreLabels()[$storeId] ?? null);
        if ($label !== null && $label !== '') {
            return (string) $label;
        }
        return html_entity_decode((string) $this->getAttributeGroupName(), ENT_QUOTES | ENT_HTML5);
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
