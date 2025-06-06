<?php

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Paypal expess checkout shortcut link
 *
 * @method string getShortcutHtmlId()
 * @method string getImageUrl()
 * @method string getCheckoutUrl()
 * @method string getBmlShortcutHtmlId()
 * @method string getBmlCheckoutUrl()
 * @method string getBmlImageUrl()
 * @method string getIsBmlEnabled()
 * @method string getConfirmationUrl()
 * @method string getIsInCatalogProduct()
 * @method string getConfirmationMessage()
 */
class Mage_Paypal_Block_Express_Shortcut extends Mage_Core_Block_Template
{
    /**
     * Position of "OR" label against shortcut
     */
    public const POSITION_BEFORE = 'before';
    public const POSITION_AFTER = 'after';

    /**
     * Whether the block should be eventually rendered
     *
     * @var bool
     */
    protected $_shouldRender = true;

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_paymentMethodCode = Mage_Paypal_Model_Config::METHOD_WPP_EXPRESS;

    /**
     * Start express action
     *
     * @var string
     */
    protected $_startAction = 'paypal/express/start/button/1';

    /**
     * Express checkout model factory name
     *
     * @var string
     */
    protected $_checkoutType = 'paypal/express_checkout';

    /**
     * @return Mage_Core_Block_Abstract
     */
    #[\Override]
    protected function _beforeToHtml()
    {
        $result = parent::_beforeToHtml();
        $config = Mage::getModel('paypal/config', [$this->_paymentMethodCode]);
        $isInCatalog = $this->getIsInCatalogProduct();
        $quote = ($isInCatalog || $this->getIsQuoteAllowed() == '')
            ? null : Mage::getSingleton('checkout/session')->getQuote();

        // check visibility on cart or product page
        $context = $isInCatalog ? 'visible_on_product' : 'visible_on_cart';
        if (!$config->$context) {
            $this->_shouldRender = false;
            return $result;
        }

        if ($isInCatalog) {
            /** @var Mage_Catalog_Model_Product $currentProduct */
            $currentProduct = Mage::registry('current_product');
            if (!is_null($currentProduct)) {
                $price = (float) $currentProduct->getFinalPrice();
                $typeInstance = $currentProduct->getTypeInstance();
                if (empty($price) && !$currentProduct->isSuper() && !$typeInstance->canConfigure($currentProduct)) {
                    $this->_shouldRender = false;
                    return $result;
                }
            }
        }

        // validate minimum quote amount and validate quote for zero grandtotal
        if ($quote !== null && (!$quote->validateMinimumAmount()
            || (!$quote->getGrandTotal() && !$quote->hasNominalItems()))
        ) {
            $this->_shouldRender = false;
            return $result;
        }

        // check payment method availability
        $methodInstance = Mage::helper('payment')->getMethodInstance($this->_paymentMethodCode);
        if (!$methodInstance || !$methodInstance->isAvailable($quote)) {
            $this->_shouldRender = false;
            return $result;
        }

        // set misc data
        /** @var Mage_Core_Helper_Data $helper */
        $helper = $this->helper('core');
        $this->setShortcutHtmlId($helper->uniqHash('ec_shortcut_'))
            ->setCheckoutUrl($this->getUrl($this->_startAction));

        $this->_getBmlShortcut($quote);

        // use static image if in catalog
        if ($isInCatalog || $quote === null) {
            $this->setImageUrl($config->getExpressCheckoutShortcutImageUrl(Mage::app()->getLocale()->getLocaleCode()));
        } else {
            $this->setImageUrl(Mage::getModel($this->_checkoutType, [
                'quote'  => $quote,
                'config' => $config,
            ])->getCheckoutShortcutImageUrl());
        }

        // ask whether to create a billing agreement
        $customerId = Mage::getSingleton('customer/session')->getCustomerId(); // potential issue for caching
        if (Mage::helper('paypal')->shouldAskToCreateBillingAgreement($config, $customerId)) {
            $this->setConfirmationUrl(
                $this->getUrl(
                    $this->_startAction,
                    [Mage_Paypal_Model_Express_Checkout::PAYMENT_INFO_TRANSPORT_BILLING_AGREEMENT => 1],
                ),
            );
            $this->setConfirmationMessage(Mage::helper('paypal')->__('Would you like to sign a billing agreement to streamline further purchases with PayPal?'));
        }

        return $result;
    }

    /**
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return $this
     */
    protected function _getBmlShortcut($quote)
    {
        /** @var Mage_Core_Helper_Data $helper */
        $helper = $this->helper('core');
        $bml = Mage::helper('payment')->getMethodInstance(Mage_Paypal_Model_Config::METHOD_BML);
        $isBmlEnabled = $bml && $bml->isAvailable($quote);
        $this->setBmlShortcutHtmlId($helper->uniqHash('ec_shortcut_bml_'))
            ->setBmlCheckoutUrl($this->getUrl('paypal/bml/start/button/1'))
            ->setBmlImageUrl('https://www.paypalobjects.com/webstatic/en_US/i/buttons/ppcredit-logo-medium.png')
            ->setMarketMessage('https://www.paypalobjects.com/webstatic/en_US/btn/btn_bml_text.png')
            ->setMarketMessageUrl('https://www.securecheckout.billmelater.com/paycapture-content/'
                . 'fetch?hash=AU826TU8&content=/bmlweb/ppwpsiw.html')
            ->setIsBmlEnabled($isBmlEnabled);
        return $this;
    }

    /**
     * Render the block if needed
     *
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if (!$this->_shouldRender) {
            return '';
        }
        return parent::_toHtml();
    }

    /**
     * Check is "OR" label position before shortcut
     *
     * @return bool
     */
    public function isOrPositionBefore()
    {
        return ($this->getIsInCatalogProduct() && !$this->getShowOrPosition())
            || ($this->getShowOrPosition() && $this->getShowOrPosition() === self::POSITION_BEFORE);
    }

    /**
     * Check is "OR" label position after shortcut
     *
     * @return bool
     */
    public function isOrPositionAfter()
    {
        return (!$this->getIsInCatalogProduct() && !$this->getShowOrPosition())
            || ($this->getShowOrPosition() && $this->getShowOrPosition() === self::POSITION_AFTER);
    }
}
