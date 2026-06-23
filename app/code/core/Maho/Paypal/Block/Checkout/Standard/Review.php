<?php

/**
 * Renders the PayPal Standard Smart Button inside the multistep review step.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

class Maho_Paypal_Block_Checkout_Standard_Review extends Maho_Paypal_Block_Checkout_Standard_Form
{
    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        $this->setTemplate('maho/paypal/checkout/standard/review.phtml');
    }

    /**
     * The review-step button is only relevant in multistep checkout when PayPal
     * Standard is the currently selected payment method. In onestep checkout the
     * smart button lives in the payment step and stays the terminal action.
     */
    public function isActive(): bool
    {
        if (Mage::getStoreConfigFlag('checkout/options/onestep_checkout_enabled')) {
            return false;
        }

        $method = Mage::getSingleton('checkout/session')->getQuote()->getPayment()->getMethod();
        return $method === Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT;
    }
}
