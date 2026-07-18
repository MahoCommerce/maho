<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Paypal
 */

declare(strict_types=1);

/**
 * Advanced Checkout extends the shared PayPal base rather than Mage_Payment_Model_Method_Cc
 * because card data never touches the server (PCI-compliant hosted fields).
 * The $_canSaveCc flag is kept for compatibility with the Cc-aware admin UI.
 */
class Maho_Paypal_Model_Method_AdvancedCheckout extends Maho_Paypal_Model_Method_Abstract
{
    protected $_code = Maho_Paypal_Model_Config::METHOD_ADVANCED_CHECKOUT;

    protected $_formBlockType = 'paypal/checkout_advanced_form';

    protected $_canUseInternal = false;
    protected $_canSaveCc = false;

    /**
     * Card data never touches the server — skip CC validation
     */
    #[\Override]
    public function validate(): self
    {
        return $this;
    }

    /**
     * Card data never touches the server — skip CC assignment
     */
    #[\Override]
    public function assignData($data): self
    {
        if (!($data instanceof \Maho\DataObject)) {
            $data = new \Maho\DataObject($data);
        }

        $info = $this->getInfoInstance();

        $paypalOrderId = $data->getPaypalOrderId();
        if ($paypalOrderId) {
            $info->setAdditionalInformation('paypal_order_id', $paypalOrderId);
            $info->setData('paypal_order_id', $paypalOrderId);
        }

        return $this;
    }
}
