<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Eav_Model_Entity_Attribute_Frontend_Datetime extends Mage_Eav_Model_Entity_Attribute_Frontend_Abstract
{
    /**
     * Retrieve attribute value
     *
     * @return mixed
     */
    #[\Override]
    public function getValue(\Maho\DataObject $object)
    {
        $data = '';
        $value = parent::getValue($object);
        $format = Mage::app()->getLocale()->getDateFormat(
            Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM,
        );

        if ($value) {
            try {
                $data = Mage::app()->getLocale()->dateImmutable($value, DateTime::ATOM, null, false)->format($format);
            } catch (Exception $e) {
                $data = Mage::app()->getLocale()->dateImmutable($value, null, null, false)->format($format);
            }
        }

        return $data;
    }
}
