<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Helper_Addresses extends Mage_Core_Helper_Abstract
{
    public const DEFAULT_STREET_LINES_COUNT = 2;

    protected $_moduleName = 'Mage_Adminhtml';

    /**
     * Check if number of street lines is non-zero
     *
     * @return Mage_Customer_Model_Attribute
     */
    public function processStreetAttribute(Mage_Customer_Model_Attribute $attribute)
    {
        if ($attribute->getScopeMultilineCount() <= 0) {
            $attribute->setScopeMultilineCount(self::DEFAULT_STREET_LINES_COUNT);
        }
        return $attribute;
    }
}
