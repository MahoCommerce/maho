<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Block_Checkout_Standard_Form extends Mage_Payment_Block_Form
{
    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        $this->setTemplate('maho/paypal/checkout/standard/form.phtml');
    }

    public function getConfig(): Maho_Paypal_Model_Config
    {
        $model = Mage::getModel('paypal/config');
        assert($model instanceof Maho_Paypal_Model_Config);
        return $model;
    }

    public function getClientId(): string
    {
        return $this->getConfig()->getClientId();
    }

    public function getIntent(): string
    {
        return $this->getConfig()->getNewPaymentAction(Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT);
    }

    public function getCreateOrderUrl(): string
    {
        return Mage::getUrl('paypal/checkout/createOrder', ['_secure' => true]);
    }

    public function getApproveOrderUrl(): string
    {
        return Mage::getUrl('paypal/checkout/approveOrder', ['_secure' => true]);
    }

    public function getJsSdkUrl(): string
    {
        return $this->getConfig()->getJsSdkUrl(Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT);
    }
}
