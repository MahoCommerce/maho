<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

/**
 * @method Mage_Core_Model_Resource_Design _getResource()
 * @method Mage_Core_Model_Resource_Design getResource()
 */
class Mage_Core_Model_Design extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('core/design');
    }

    /**
     * @return $this
     */
    public function validate()
    {
        $this->getResource()->validate($this);
        return $this;
    }

    /**
     * @param int $storeId
     * @param string|null $date
     * @return $this
     */
    public function loadChange($storeId, $date = null)
    {
        $result = $this->getResource()
            ->loadChange($storeId, $date);

        if (!empty($result)) {
            if (!empty($result['design'])) {
                $tmp = explode('/', $result['design']);
                $result['package'] = $tmp[0];
                $result['theme'] = $tmp[1];
            }

            $this->setData($result);
        }

        return $this;
    }

    public function getDateFrom(): ?string
    {
        $value = $this->getData('date_from');
        return $value === null ? null : (string) $value;
    }

    public function setDateFrom(?string $value): static
    {
        return $this->setData('date_from', $value);
    }

    public function getDateTo(): ?string
    {
        $value = $this->getData('date_to');
        return $value === null ? null : (string) $value;
    }

    public function setDateTo(?string $value): static
    {
        return $this->setData('date_to', $value);
    }

    public function getDesign(): ?string
    {
        $value = $this->getData('design');
        return $value === null ? null : (string) $value;
    }

    public function setDesign(?string $value): static
    {
        return $this->setData('design', $value);
    }

    public function getPackage(): ?string
    {
        $value = $this->getData('package');
        return $value === null ? null : (string) $value;
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

    public function getTheme(): ?string
    {
        $value = $this->getData('theme');
        return $value === null ? null : (string) $value;
    }

}
