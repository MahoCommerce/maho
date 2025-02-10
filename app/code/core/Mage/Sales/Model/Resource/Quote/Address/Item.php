<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Quote address item resource model
 *
 * @package    Mage_Sales
 */
class Mage_Sales_Model_Resource_Quote_Address_Item extends Mage_Sales_Model_Resource_Abstract
{
    /**
     * Main table and field initialization
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/quote_address_item', 'address_item_id');
    }
}
