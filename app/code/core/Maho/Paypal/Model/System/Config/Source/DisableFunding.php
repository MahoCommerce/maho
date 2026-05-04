<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Model_System_Config_Source_DisableFunding
{
    public function toOptionArray(): array
    {
        $helper = Mage::helper('paypal');

        return [
            ['value' => 'credit', 'label' => $helper->__('PayPal Credit')],
            ['value' => 'paylater', 'label' => $helper->__('Pay Later')],
            ['value' => 'venmo', 'label' => $helper->__('Venmo')],
            ['value' => 'bancontact', 'label' => $helper->__('Bancontact')],
            ['value' => 'blik', 'label' => $helper->__('BLIK')],
            ['value' => 'eps', 'label' => $helper->__('eps')],
            ['value' => 'giropay', 'label' => $helper->__('giropay')],
            ['value' => 'ideal', 'label' => $helper->__('iDEAL')],
            ['value' => 'mybank', 'label' => $helper->__('MyBank')],
            ['value' => 'p24', 'label' => $helper->__('Przelewy24')],
            ['value' => 'sepa', 'label' => $helper->__('SEPA')],
            ['value' => 'sofort', 'label' => $helper->__('Sofort')],
        ];
    }
}
