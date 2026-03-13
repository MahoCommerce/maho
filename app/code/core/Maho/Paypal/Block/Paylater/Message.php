<?php

/**
 * Maho
 *
 * @package    Maho_Paypal
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_Paypal_Block_Paylater_Message extends Mage_Core_Block_Template
{
    protected bool $_shouldRender = true;

    #[\Override]
    protected function _beforeToHtml()
    {
        $result = parent::_beforeToHtml();

        if (!$this->_getConfig()->isPayLaterMessagingEnabled()) {
            $this->_shouldRender = false;
            return $result;
        }

        $isInCatalog = (bool) $this->getIsInCatalogProduct();

        if ($isInCatalog) {
            $currentProduct = Mage::registry('current_product');
            if ($currentProduct === null) {
                $this->_shouldRender = false;
                return $result;
            }
            $this->setAmount((float) $currentProduct->getFinalPrice());
            $this->setPlacement('product');
        } else {
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            $this->setAmount((float) $quote->getGrandTotal());
            $this->setPlacement('cart');
        }

        $this->setMessageHtmlId(Mage::helper('core')->uniqHash('maho_paypal_paylater_'));

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

    protected function _getConfig(): Maho_Paypal_Model_Config
    {
        return Mage::getModel('paypal/config');
    }
}
