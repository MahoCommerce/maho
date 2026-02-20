<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Eav_Model_Attribute_Data_Datetime extends Mage_Eav_Model_Attribute_Data_Date
{
    /**
     * Return Data Form Input/Output Filter
     *
     * @return \Maho\Data\Form\Filter\FilterInterface|false
     */
    #[\Override]
    protected function _getFormFilter()
    {
        $filterCode = $this->getAttribute()->getInputFilter();
        if ($filterCode) {
            $filterClass = '\Maho\Data\Form\Filter\\' . ucfirst($filterCode);
            if ($filterCode == 'datetime') {
                $filter = new $filterClass(
                    $this->_getLocale()->getDateTimeFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
                    $this->_getLocale()->getLocale(),
                );
            } else {
                $filter = new $filterClass();
            }
            return $filter;
        }
        return false;
    }

    /**
     * Get Locale
     *
     * @return Mage_Core_Model_Locale
     */
    protected function _getLocale()
    {
        return Mage::app()->getLocale();
    }
}
