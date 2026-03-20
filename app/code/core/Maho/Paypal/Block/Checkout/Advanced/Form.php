<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Block_Checkout_Advanced_Form extends Mage_Payment_Block_Form
{
    protected ?Maho_Paypal_Model_Config $_paypalConfig = null;

    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        $this->setTemplate('maho/paypal/checkout/advanced/form.phtml');
    }

    public function getConfig(): Maho_Paypal_Model_Config
    {
        if ($this->_paypalConfig === null) {
            $this->_paypalConfig = Mage::getModel('paypal/config');
        }
        return $this->_paypalConfig;
    }

    public function getJsSdkUrl(): string
    {
        return $this->getConfig()->getJsSdkUrl();
    }

    public function getClientTokenUrl(): string
    {
        return $this->getConfig()->getClientTokenUrl();
    }

    public function getCurrencyCode(): string
    {
        return $this->getConfig()->getCurrencyCode();
    }

    public function getCreateOrderUrl(): string
    {
        return Mage::getUrl('paypal/checkout/createOrder', ['_secure' => true]);
    }

    public function getApproveOrderUrl(): string
    {
        return Mage::getUrl('paypal/checkout/approveOrder', ['_secure' => true]);
    }

    public function isVaultAvailable(): bool
    {
        return $this->getConfig()->isVaultEnabled()
            && (bool) Mage::getSingleton('customer/session')->getCustomerId();
    }
}
