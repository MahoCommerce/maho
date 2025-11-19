<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Entity_Quote_Address_Item_Collection extends Mage_Eav_Model_Entity_Collection_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/quote_address_item');
    }

    /**
     * @param int $addressId
     * @return $this
     */
    public function setAddressFilter($addressId)
    {
        $this->addAttributeToFilter('parent_id', $addressId);
        return $this;
    }
}
