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
 * @method Mage_Eav_Model_Resource_Entity_Store _getResource()
 * @method Mage_Eav_Model_Resource_Entity_Store getResource()
 */
class Mage_Eav_Model_Entity_Store extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('eav/entity_store');
    }

    /**
     * Load entity by store
     *
     * @param int $entityTypeId
     * @param int $storeId
     * @return $this
     */
    public function loadByEntityStore($entityTypeId, $storeId)
    {
        $this->_getResource()->loadByEntityStore($this, $entityTypeId, $storeId);
        return $this;
    }

    public function getEntityTypeId(): ?int
    {
        $value = $this->getData('entity_type_id');
        return $value === null ? null : (int) $value;
    }

    public function setEntityTypeId(?int $value): static
    {
        return $this->setData('entity_type_id', $value);
    }

    public function getIncrementLastId(): ?string
    {
        $value = $this->getData('increment_last_id');
        return $value === null ? null : (string) $value;
    }

    public function setIncrementLastId(?string $value): static
    {
        return $this->setData('increment_last_id', $value);
    }

    public function getIncrementPrefix(): ?string
    {
        $value = $this->getData('increment_prefix');
        return $value === null ? null : (string) $value;
    }

    public function setIncrementPrefix(?string $value): static
    {
        return $this->setData('increment_prefix', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

}
