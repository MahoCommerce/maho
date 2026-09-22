<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

/**
 * @method Mage_Eav_Model_Resource_Form_Fieldset _getResource()
 * @method Mage_Eav_Model_Resource_Form_Fieldset getResource()
 * @method Mage_Eav_Model_Resource_Form_Fieldset_Collection getCollection()
 * @method bool hasLabels()
 * @method bool hasStoreId()
 */
class Mage_Eav_Model_Form_Fieldset extends Mage_Core_Model_Abstract
{
    /**
     * Prefix of model events names
     *
     * @var string
     */
    #[\Override]
    protected $_eventPrefix = 'eav_form_fieldset';

    #[\Override]
    protected function _construct()
    {
        $this->_init('eav/form_fieldset');
    }

    /**
     * Validate data before save data
     *
     * @throws Mage_Core_Exception
     */
    #[\Override]
    protected function _beforeSave()
    {
        if (!$this->getTypeId()) {
            Mage::throwException(Mage::helper('eav')->__('Invalid form type.'));
        }
        if (!$this->getStoreId() && $this->getLabel()) {
            $this->setStoreLabel($this->getStoreId(), $this->getLabel());
        }

        return parent::_beforeSave();
    }

    /**
     * Retrieve fieldset labels for stores
     *
     * @return array
     */
    public function getLabels()
    {
        if (!$this->hasData('labels')) {
            $this->setData('labels', $this->_getResource()->getLabels($this));
        }
        return $this->_getData('labels');
    }

    /**
     * Set fieldset store labels
     * Input array where key - store_id and value = label
     *
     * @return $this
     */
    public function setLabels(array $labels)
    {
        return $this->setData('labels', $labels);
    }

    /**
     * Set fieldset store label
     *
     * @param int $storeId
     * @param string $label
     * @return $this
     */
    public function setStoreLabel($storeId, $label)
    {
        $labels = $this->getLabels();
        $labels[$storeId] = $label;

        return $this->setLabels($labels);
    }

    /**
     * Retrieve label store scope
     *
     * @return int
     */
    public function getStoreId()
    {
        if (!$this->hasStoreId()) {
            $this->setData('store_id', Mage::app()->getStore()->getId());
        }
        return $this->_getData('store_id');
    }

    public function getCode(): ?string
    {
        $value = $this->getData('code');
        return $value === null ? null : (string) $value;
    }

    public function setCode(?string $value): static
    {
        return $this->setData('code', $value);
    }

    public function getLabel(): ?string
    {
        $value = $this->getData('label');
        return $value === null ? null : (string) $value;
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

    public function getTypeId(): ?int
    {
        $value = $this->getData('type_id');
        return $value === null ? null : (int) $value;
    }

    public function setTypeId(?int $value): static
    {
        return $this->setData('type_id', $value);
    }

}
