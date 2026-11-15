<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Product_Attribute_Backend_Sku extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Maximum SKU string length
     *
     * @var int
     */
    public const SKU_MAX_LENGTH = 64;

    /**
     * Validate SKU
     *
     * @param Mage_Catalog_Model_Product $object
     * @throws Mage_Core_Exception
     * @throws Mage_Eav_Model_Entity_Attribute_Exception_Unique when another product has the SKU
     * @return bool
     */
    #[\Override]
    public function validate($object)
    {
        $helper = Mage::helper('core/string');
        $sku = (string) $object->getSku();

        if ($helper->strlen($sku) > self::SKU_MAX_LENGTH) {
            Mage::throwException(
                Mage::helper('catalog')->__('SKU length should be %s characters maximum.', self::SKU_MAX_LENGTH),
            );
        }
        if ($sku !== Mage::helper('core')->stripTags($sku)) {
            throw new Mage_Core_Exception_Input()->addError('sku', Mage::helper('catalog')->__('HTML tags are not allowed in SKU attribute.'));
        }
        if ($sku !== '' && !$this->getAttribute()->getEntity()->checkAttributeUniqueValue($this->getAttribute(), $object)) {
            throw new Mage_Eav_Model_Entity_Attribute_Exception_Unique(Mage::helper('eav')->__('The value of attribute "%s" must be unique', $this->getAttribute()->getFrontend()->getLabel()))
                ->setAttributeCode($this->getAttribute()->getAttributeCode())
                ->setPart('backend');
        }
        return parent::validate($object);
    }
}
