<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

/**
 * @method Mage_Eav_Model_Resource_Form_Element _getResource()
 * @method Mage_Eav_Model_Resource_Form_Element getResource()
 * @method Mage_Eav_Model_Resource_Form_Element_Collection getCollection()
 */
class Mage_Eav_Model_Form_Element extends Mage_Core_Model_Abstract
{
    /**
     * Prefix of model events names
     *
     * @var string
     */
    #[\Override]
    protected $_eventPrefix = 'eav_form_element';

    #[\Override]
    protected function _construct()
    {
        $this->_init('eav/form_element');
    }

    /**
     * Validate data before save
     * @throws Mage_Core_Exception
     */
    #[\Override]
    protected function _beforeSave()
    {
        if (!$this->getTypeId()) {
            Mage::throwException(Mage::helper('eav')->__('Invalid form type.'));
        }
        if (!$this->getAttributeId()) {
            Mage::throwException(Mage::helper('eav')->__('Invalid EAV attribute.'));
        }

        return parent::_beforeSave();
    }

    /**
     * Retrieve EAV Attribute instance
     *
     * @return Mage_Eav_Model_Entity_Attribute
     */
    public function getAttribute()
    {
        if (!$this->hasData('attribute')) {
            $attribute = Mage::getSingleton('eav/config')
                ->getAttribute($this->getEntityTypeId(), $this->getAttributeId());
            $this->setData('attribute', $attribute);
        }
        return $this->_getData('attribute');
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

    public function getEntityTypeId(): ?int
    {
        $value = $this->getData('entity_type_id');
        return $value === null ? null : (int) $value;
    }

    public function getFieldsetId(): ?int
    {
        $value = $this->getData('fieldset_id');
        return $value === null ? null : (int) $value;
    }

    public function setFieldsetId(?int $value): static
    {
        return $this->setData('fieldset_id', $value);
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
