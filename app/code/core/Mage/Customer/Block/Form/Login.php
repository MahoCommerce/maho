<?php

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer login form block
 *
 * @package    Mage_Customer
 *
 * @method $this setCreateAccountUrl(string $value)
 */
class Mage_Customer_Block_Form_Login extends Mage_Core_Block_Template
{
    private $_username = -1;

    /**
     * Set redirect URL before rendering login form
     */
    #[\Override]
    protected function _beforeToHtml(): self
    {
        $this->_setBeforeAuthUrl();
        return parent::_beforeToHtml();
    }

    /**
     * Set the URL to redirect to after successful login
     */
    protected function _setBeforeAuthUrl(): void
    {
        if (Mage::helper('customer')->isLoggedIn()) {
            return;
        }

        $session = Mage::getSingleton('customer/session');

        // Respect an already-set redirect URL (e.g., from checkout when guest checkout is disabled)
        $existingUrl = $session->getBeforeAuthUrl();
        if ($existingUrl && $existingUrl !== Mage::helper('customer')->getLoginUrl()) {
            return;
        }

        if (Mage::getStoreConfigFlag(Mage_Customer_Helper_Data::XML_PATH_CUSTOMER_LOGIN_REDIRECT_TO_DASHBOARD)) {
            $url = Mage::helper('customer')->getDashboardUrl();
        } else {
            $pathInfo = $this->getRequest()->getOriginalPathInfo();
            if (strtolower(substr($pathInfo, -5)) === '.html') {
                $url = Mage::getBaseUrl() . ltrim($pathInfo, '/');
            } else {
                $url = $this->getUrl('*/*/*', ['_current' => true]);
            }
        }
        $session->setBeforeAuthUrl($url);
    }

    /**
     * Retrieve form posting url
     *
     * @return string
     */
    public function getPostActionUrl()
    {
        /** @var Mage_Customer_Helper_Data $helper */
        $helper = $this->helper('customer');
        return $helper->getLoginPostUrl();
    }

    /**
     * Retrieve create new account url
     *
     * @return string
     */
    public function getCreateAccountUrl()
    {
        $url = $this->getData('create_account_url');
        if (is_null($url)) {
            /** @var Mage_Customer_Helper_Data $helper */
            $helper = $this->helper('customer');
            $url = $helper->getRegisterUrl();
        }
        return $url;
    }

    /**
     * Retrieve create new account url with context
     */
    public function getCreateAccountUrlContext(): string
    {
        $url = $this->getCreateAccountUrl();
        if (Mage::helper('checkout')->isContextCheckout()) {
            $url = Mage::helper('core/url')->addRequestParam($url, ['context' => 'checkout']);
        }
        return $url;
    }

    /**
     * Retrieve password forgotten url
     *
     * @return string
     */
    public function getForgotPasswordUrl()
    {
        /** @var Mage_Customer_Helper_Data $helper */
        $helper = $this->helper('customer');
        return $helper->getForgotPasswordUrl();
    }

    /**
     * Retrieve username for form field
     *
     * @return string
     */
    public function getUsername()
    {
        if ($this->_username === -1) {
            $this->_username = Mage::getSingleton('customer/session')->getUsername(true);
        }
        return $this->_username;
    }

    /**
     * Can show the login form?
     * For mini login, which can be login from any page, which should be SSL enabled.
     *
     * @return bool
     */
    public function canShowLogin()
    {
        if (Mage::helper('customer')->isLoggedIn()) {
            return false;
        }

        // Set redirect URL after login (also done in _beforeToHtml)
        $this->_setBeforeAuthUrl();

        return true;
    }

    public function getMinPasswordLength(): int
    {
        return Mage::getStoreConfigAsInt(Mage_Customer_Model_Customer::XML_PATH_MIN_PASSWORD_LENGTH);
    }
}
