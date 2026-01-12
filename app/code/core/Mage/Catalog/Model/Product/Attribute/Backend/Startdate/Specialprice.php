<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Product_Attribute_Backend_Startdate_Specialprice extends Mage_Catalog_Model_Product_Attribute_Backend_Startdate
{
    /**
     * Get attribute value for save.
     *
     * @param \Maho\DataObject $object
     * @return string|bool
     */
    #[\Override]
    protected function _getValueForSave($object)
    {
        $attributeName  = $this->getAttribute()->getName();
        $startDate      = $object->getData($attributeName);
        if ($startDate === false) {
            return false;
        }
        if ($startDate == '' && $object->getSpecialPrice()) {
            $startDate = Mage::app()->getLocale()->dateImmutable();
        }

        return $startDate;
    }

    /**
     * Before save hook.
     * Prepare attribute value for save
     *
     * @param \Maho\DataObject $object
     * @return Mage_Catalog_Model_Product_Attribute_Backend_Startdate
     */
    #[\Override]
    public function beforeSave($object)
    {
        $startDate = $this->_getValueForSave($object);
        if ($startDate === false) {
            return $this;
        }

        $object->setData($this->getAttribute()->getName(), $startDate);
        parent::beforeSave($object);
        return $this;
    }
}
