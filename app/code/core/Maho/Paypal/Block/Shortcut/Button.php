<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Block_Shortcut_Button extends Mage_Core_Block_Template
{
    protected bool $_shouldRender = true;

    #[\Override]
    protected function _beforeToHtml()
    {
        $result = parent::_beforeToHtml();

        $config = $this->_getConfig();
        $isInCatalog = (bool) $this->getIsInCatalogProduct();
        $quote = ($isInCatalog || $this->getIsQuoteAllowed() == '')
            ? null
            : Mage::getSingleton('checkout/session')->getQuote();

        $context = $isInCatalog ? 'visible_on_product' : 'visible_on_cart';
        if (!Mage::getStoreConfigFlag('payment/' . Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT . '/' . $context)) {
            $this->_shouldRender = false;
            return $result;
        }

        if ($isInCatalog) {
            $currentProduct = Mage::registry('current_product');
            if ($currentProduct !== null) {
                $price = (float) $currentProduct->getFinalPrice();
                $typeInstance = $currentProduct->getTypeInstance();
                if (empty($price) && !$currentProduct->isSuper() && !$typeInstance->canConfigure($currentProduct)) {
                    $this->_shouldRender = false;
                    return $result;
                }
            }
        }

        if ($quote !== null && (!$quote->validateMinimumAmount()
            || (!$quote->getGrandTotal() && !$quote->hasNominalItems()))
        ) {
            $this->_shouldRender = false;
            return $result;
        }

        if (!$config->isNewMethodActive(Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT)) {
            $this->_shouldRender = false;
            return $result;
        }

        if (!$config->hasCredentials()) {
            $this->_shouldRender = false;
            return $result;
        }

        $methodInstance = Mage::helper('payment')->getMethodInstance(Maho_Paypal_Model_Config::METHOD_STANDARD_CHECKOUT);
        if (!$methodInstance || !$methodInstance->isAvailable($quote)) {
            $this->_shouldRender = false;
            return $result;
        }

        $this->setShortcutHtmlId(Mage::helper('core')->uniqHash('maho_paypal_shortcut_'));

        return $result;
    }

    #[\Override]
    protected function _toHtml(): string
    {
        if (!$this->_shouldRender) {
            return '';
        }
        return parent::_toHtml();
    }

    public function getJsSdkUrl(): string
    {
        return $this->_getConfig()->getJsSdkUrl();
    }

    public function getClientTokenUrl(): string
    {
        return $this->_getConfig()->getClientTokenUrl();
    }

    public function getCurrencyCode(): string
    {
        return $this->_getConfig()->getCurrencyCode();
    }

    public function getCreateOrderUrl(): string
    {
        return Mage::getUrl('paypal/checkout/createOrder', ['_secure' => true]);
    }

    public function getApproveOrderUrl(): string
    {
        return Mage::getUrl('paypal/checkout/approveOrder', ['_secure' => true]);
    }


    protected function _getConfig(): Maho_Paypal_Model_Config
    {
        $model = Mage::getModel('paypal/config');
        return $model;
    }
}
