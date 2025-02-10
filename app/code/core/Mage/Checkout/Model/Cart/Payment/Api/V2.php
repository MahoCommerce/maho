<?php

/**
 * Maho
 *
 * @package    Mage_Checkout
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Shopping cart api
 *
 * @package    Mage_Checkout
 */
class Mage_Checkout_Model_Cart_Payment_Api_V2 extends Mage_Checkout_Model_Cart_Payment_Api
{
    /**
      * @param object $data
      * @return array
      */
    #[\Override]
    protected function _preparePaymentData($data)
    {
        if (($_data = get_object_vars($data)) !== null) {
            return parent::_preparePaymentData($_data);
        }

        return [];
    }
}
