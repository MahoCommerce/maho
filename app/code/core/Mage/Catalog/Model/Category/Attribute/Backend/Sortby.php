<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Category_Attribute_Backend_Sortby extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Validate process
     *
     * @param \Maho\DataObject $object
     * @return bool
     */
    #[\Override]
    public function validate($object)
    {
        $attributeCode = $this->getAttribute()->getName();
        $postDataConfig = $object->getData('use_post_data_config');
        if ($postDataConfig) {
            $isUseConfig = in_array($attributeCode, $postDataConfig);
        } else {
            $isUseConfig = false;
            $postDataConfig = [];
        }

        if ($this->getAttribute()->getIsRequired()) {
            $attributeValue = $object->getData($attributeCode);
            if ($this->getAttribute()->isValueEmpty($attributeValue)) {
                if (is_array($attributeValue) && count($attributeValue) > 0) {
                } else {
                    if (!$isUseConfig) {
                        return false;
                    }
                }
            }
        }

        if ($this->getAttribute()->getIsUnique()) {
            if (!$this->getAttribute()->getEntity()->checkAttributeUniqueValue($this->getAttribute(), $object)) {
                $label = $this->getAttribute()->getFrontend()->getLabel();
                Mage::throwException(Mage::helper('eav')->__('The value of attribute "%s" must be unique.', $label));
            }
        }

        if ($attributeCode == 'default_sort_by') {
            if ($available = $object->getData('available_sort_by')) {
                if (!is_array($available)) {
                    $available = explode(',', $available);
                }
                $data = (in_array('default_sort_by', $postDataConfig)) ? Mage::getStoreConfig('catalog/frontend/default_sort_by') :
                       $object->getData($attributeCode);
                if (!in_array($data, $available)) {
                    Mage::throwException(Mage::helper('eav')->__('Default Product Listing Sort by does not exist in Available Product Listing Sort By.'));
                }
            } else {
                if (!in_array('available_sort_by', $postDataConfig)) {
                    Mage::throwException(Mage::helper('eav')->__('Default Product Listing Sort by does not exist in Available Product Listing Sort By.'));
                }
            }
        }

        return true;
    }

    /**
     * Before Attribute Save Process
     *
     * @param \Maho\DataObject $object
     * @return $this
     */
    #[\Override]
    public function beforeSave($object)
    {
        $attributeCode = $this->getAttribute()->getName();
        if ($attributeCode == 'available_sort_by') {
            $data = $object->getData($attributeCode);
            if (!is_array($data)) {
                $data = [];
            }
            $object->setData($attributeCode, implode(',', $data));
        }
        if (is_null($object->getData($attributeCode))) {
            $object->setData($attributeCode, false);
        }
        return $this;
    }

    /**
     * @param \Maho\DataObject $object
     * @return $this|Mage_Eav_Model_Entity_Attribute_Backend_Abstract
     */
    #[\Override]
    public function afterLoad($object)
    {
        $attributeCode = $this->getAttribute()->getName();
        if ($attributeCode == 'available_sort_by') {
            $data = $object->getData($attributeCode);
            if ($data) {
                $object->setData($attributeCode, explode(',', $data));
            }
        }
        return $this;
    }
}
