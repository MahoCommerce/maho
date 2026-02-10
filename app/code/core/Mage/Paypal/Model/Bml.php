<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Paypal_Model_Bml extends Mage_Paypal_Model_Express
{
    /**
     * Payment method code
     * @var string
     */
    protected $_code  = Mage_Paypal_Model_Config::METHOD_BML;

    /**
     * Checkout payment form
     * @var string
     */
    protected $_formBlockType = 'paypal/bml_form';

    /**
     * Checkout redirect URL getter for onepage checkout
     *
     * @return string
     */
    #[\Override]
    public function getCheckoutRedirectUrl()
    {
        return Mage::getUrl('paypal/bml/start');
    }
}
