<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Eav_Model_Entity_Attribute_Backend_Time_Created extends Mage_Eav_Model_Entity_Attribute_Backend_Abstract
{
    /**
     * Returns date format if it matches a certain mask.
     * @param string $date
     * @return null|string
     */
    protected function _getFormat($date)
    {
        if (is_string($date) && (preg_match('#^\d{4,4}-\d{2,2}-\d{2,2}\s\d{2,2}:\d{2,2}:\d{2,2}$#', $date)
            || preg_match('#^\d{4,4}-\d{2,2}-\d{2,2}\w{1,1}\d{2,2}:\d{2,2}:\d{2,2}[+-]\d{2,2}:\d{2,2}$#', $date))
        ) {
            return 'yyyy-MM-dd HH:mm:ss';
        }
        return null;
    }
    /**
     * Set created date
     * Set created date in UTC time zone
     *
     * @param Mage_Core_Model_Abstract $object
     * @return $this
     */
    #[\Override]
    public function beforeSave($object)
    {
        $attributeCode = $this->getAttribute()->getAttributeCode();
        $date = $object->getData($attributeCode);
        if (is_null($date)) {
            if ($object->isObjectNew()) {
                $object->setData($attributeCode, Mage_Core_Model_Locale::now());
            }
        } else {
            // convert to UTC
            $zendDate = Mage::app()->getLocale()->utcDate(null, $date, true, $this->_getFormat($date));
            $object->setData($attributeCode, $zendDate instanceof DateTime ? $zendDate->format(Mage_Core_Model_Locale::DATETIME_FORMAT) : $zendDate);
        }

        return $this;
    }

    /**
     * Convert create date from UTC to current store time zone
     *
     * @param \Maho\DataObject $object
     * @return $this
     */
    #[\Override]
    public function afterLoad($object)
    {
        $attributeCode = $this->getAttribute()->getAttributeCode();
        $date = $object->getData($attributeCode);

        // Handle MySQL zero dates and invalid dates - convert to null
        if (empty($date) || (is_string($date) && preg_match('/^0000-00-00/', $date))) {
            $object->setData($attributeCode);
            parent::afterLoad($object);
            return $this;
        }

        $zendDate = Mage::app()->getLocale()->storeDate(null, $date, true, $this->_getFormat($date));
        $object->setData($attributeCode, $zendDate instanceof DateTime ? $zendDate->format(Mage_Core_Model_Locale::DATETIME_FORMAT) : $zendDate);

        parent::afterLoad($object);

        return $this;
    }
}
