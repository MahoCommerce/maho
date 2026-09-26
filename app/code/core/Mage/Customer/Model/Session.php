<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

/**
 * Customer session model
 *
 * @package    Mage_Customer
 *
 * @method $this unsBeforeWishlistRequest()
 * @method bool  hasDisplayOutOfStockProducts()
 * @method $this unsForgottenEmail()
 * @method $this unsNoReferer(bool $value)
 * @method $this unsRequireTwofa()
 * @method $this unsTwofaPendingCustomerId()
 * @method bool hasWishlistItemCount()
 */
class Mage_Customer_Model_Session extends Mage_Core_Model_Session_Abstract
{
    /**
     * Customer object
     *
     * @var Mage_Customer_Model_Customer
     */
    protected $_customer;

    /**
     * Flag with customer id validations result
     *
     * @var bool
     */
    protected $_isCustomerIdChecked = null;

    protected ?int $_checkedCustomerId = null;

    /**
     * Retrieve customer sharing configuration model
     *
     * @return Mage_Customer_Model_Config_Share
     */
    public function getCustomerConfigShare()
    {
        return Mage::getSingleton('customer/config_share');
    }

    public function __construct()
    {
        $namespace = 'customer';
        if ($this->getCustomerConfigShare()->isWebsiteScope()) {
            $namespace .= '_' . (Mage::app()->getStore()->getWebsite()->getCode());
        }

        $this->init($namespace);
        Mage::dispatchEvent('customer_session_init', ['customer_session' => $this]);
    }

    /**
     * Set customer object and setting customer id in session
     *
     * @return  Mage_Customer_Model_Session
     */
    public function setCustomer(Mage_Customer_Model_Customer $customer)
    {
        // check if customer is not confirmed
        if ($customer->isConfirmationRequired()) {
            if ($customer->getConfirmation()) {
                return $this->_logout();
            }
        }
        $this->_customer = $customer;
        $this->setId($customer->getId());
        // save customer as confirmed, if it is not
        if ((!$customer->isConfirmationRequired()) && $customer->getConfirmation()) {
            $customer->setConfirmation(null)->save();
            $customer->setIsJustConfirmed();
        }
        return $this;
    }

    /**
     * Retrieve customer model object
     *
     * @return Mage_Customer_Model_Customer
     */
    public function getCustomer()
    {
        if ($this->_customer instanceof Mage_Customer_Model_Customer) {
            return $this->_customer;
        }

        $customer = Mage::getModel('customer/customer')
            ->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
        if ($this->getId()) {
            $customer->load($this->getId());
        }

        $this->setCustomer($customer);
        return $this->_customer;
    }

    /**
     * Set customer id
     *
     * @param int|null $id
     * @return $this
     */
    public function setCustomerId($id)
    {
        $this->setData('customer_id', $id);
        return $this;
    }

    /**
     * Retrieve customer id from current session
     *
     * @return int|null
     */
    public function getCustomerId()
    {
        if ($this->getData('customer_id')) {
            return $this->getData('customer_id');
        }
        return ($this->isLoggedIn()) ? $this->getId() : null;
    }

    /**
     * Set customer group id
     *
     * @param int|null $id
     * @return $this
     */
    public function setCustomerGroupId($id)
    {
        $this->setData('customer_group_id', $id);
        return $this;
    }

    /**
     * Get customer group id
     * If customer is not logged in system, 'not logged in' group id will be returned
     *
     * @return int
     */
    public function getCustomerGroupId()
    {
        if ($this->getData('customer_group_id')) {
            return $this->getData('customer_group_id');
        }
        if ($this->isLoggedIn() && $this->getCustomer()) {
            return $this->getCustomer()->getGroupId();
        }
        return Mage_Customer_Model_Group::NOT_LOGGED_IN_ID;
    }

    /**
     * Checking customer login status
     *
     * @return bool
     */
    public function isLoggedIn()
    {
        return (bool) $this->getId() && (bool) $this->checkCustomerId($this->getId());
    }

    /**
     * Check exists customer (light check)
     *
     * @param int $customerId
     * @return bool
     */
    public function checkCustomerId($customerId)
    {
        // Keyed by id: the session can point at another customer later in the request
        if ($this->_isCustomerIdChecked === null || $this->_checkedCustomerId !== (int) $customerId) {
            $this->_checkedCustomerId = (int) $customerId;
            $this->_isCustomerIdChecked = Mage::getResourceSingleton('customer/customer')->checkCustomerId($customerId);
        }
        return $this->_isCustomerIdChecked;
    }

    /**
     * Check if 2fa is required for the given credentials
     */
    public function prelogin(#[\SensitiveParameter] string $username, #[\SensitiveParameter] string $password): void
    {
        try {
            if (!empty($username) && !empty($password)) {
                /** @var Mage_Customer_Model_Customer $customer */
                $customer = Mage::getModel('customer/customer')
                    ->setWebsiteId(Mage::app()->getStore()->getWebsiteId());
                $customer->authenticate($username, $password);
            }
        } catch (Mage_Core_Exception $e) {
            if ($e->getCode() === Mage_Customer_Model_Customer::EXCEPTION_2FA_INVALID) {
                $this->setRequireTwofa();
            }
        } catch (Exception) {
            // Mage::logException($e); // PA DSS violation: this exception log can disclose customer password
        }
    }

    /**
     * Customer authorization
     *
     * @param   string $username
     * @param   string $password
     * @return  bool
     */
    public function login(#[\SensitiveParameter] $username, #[\SensitiveParameter] $password, #[\SensitiveParameter] ?string $twofaVerificationCode = null)
    {
        /** @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::getModel('customer/customer')
            ->setWebsiteId(Mage::app()->getStore()->getWebsiteId());

        if ($customer->authenticate($username, $password, $twofaVerificationCode)) {
            $this->setCustomerAsLoggedIn($customer);
            return true;
        }
        return false;
    }

    /**
     * @param Mage_Customer_Model_Customer $customer
     * @return $this
     */
    public function setCustomerAsLoggedIn($customer)
    {
        $this->setCustomer($customer);
        $this->renewSession();
        Mage::getSingleton('core/session')->renewFormKey();
        Mage::dispatchEvent('customer_login', ['customer' => $customer]);
        return $this;
    }

    /**
     * Authorization customer by identifier
     *
     * @param   int $customerId
     * @return  bool
     */
    public function loginById($customerId)
    {
        $customer = Mage::getModel('customer/customer')->load($customerId);
        if ($customer->getId()) {
            // Programmatic entry point: it cannot drive the interactive 2FA challenge,
            // so it refuses rather than silently bypassing an enabled second factor.
            if ($this->shouldChallengeTwofa($customer)) {
                return false;
            }
            $this->setCustomerAsLoggedIn($customer);
            return true;
        }
        return false;
    }

    /**
     * Whether the given customer must clear a 2FA challenge before being logged in
     */
    public function shouldChallengeTwofa(Mage_Customer_Model_Customer $customer): bool
    {
        return Mage::getStoreConfigFlag('customer/password/allow_2fa') && (bool) $customer->getTwofaEnabled();
    }

    /**
     * Remember which customer is mid-way through a 2FA challenge (identity proven, not yet logged in)
     */
    public function startTwofaChallenge(Mage_Customer_Model_Customer $customer): void
    {
        $this->setTwofaPendingCustomerId((int) $customer->getId());
    }

    /**
     * Customer currently waiting on a 2FA challenge, if any
     */
    public function getTwofaPendingCustomer(): ?Mage_Customer_Model_Customer
    {
        $customerId = $this->getTwofaPendingCustomerId();
        if (!$customerId) {
            return null;
        }
        /** @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::getModel('customer/customer')->load($customerId);
        return $customer->getId() ? $customer : null;
    }

    /**
     * Verify the challenge code and, on success, complete the pending login
     */
    public function completeTwofaChallenge(#[\SensitiveParameter] string $code): bool
    {
        $customer = $this->getTwofaPendingCustomer();
        if (!$customer) {
            return false;
        }
        if (!Mage::helper('core/security')->verifyTotpCode($customer->getTwofaSecret() ?? '', $code)) {
            return false;
        }
        $this->unsTwofaPendingCustomerId();
        $this->setCustomerAsLoggedIn($customer);
        return true;
    }

    /**
     * Logout customer
     *
     * @return $this
     */
    public function logout()
    {
        if ($this->isLoggedIn()) {
            Mage::dispatchEvent('customer_logout', ['customer' => $this->getCustomer()]);
            $this->_logout();
        }
        return $this;
    }

    /**
     * Authenticate controller action by login customer
     */
    public function authenticate(Mage_Core_Controller_Varien_Action $action, ?string $loginUrl = null): bool
    {
        if ($this->isLoggedIn()) {
            return true;
        }

        $this->setBeforeAuthUrl($action->getRequest()->isGet()
            ? Mage::getUrl('*/*/*', ['_current' => true])
            : Mage::helper('customer')->getDefaultBeforeAuthUrl());

        if (isset($loginUrl)) {
            $action->getResponse()->setRedirect($loginUrl);
        } else {
            $action->setRedirectWithCookieCheck(
                Mage_Customer_Helper_Data::ROUTE_ACCOUNT_LOGIN,
                Mage::helper('customer')->getLoginUrlParams(),
            );
        }

        return false;
    }

    /**
     * Set auth url
     *
     * @param string $key
     * @param string $url
     * @return $this
     */
    protected function _setAuthUrl($key, $url)
    {
        return $this->setData($key, Mage::getModel('core/url')->getRebuiltUrl($url));
    }

    /**
     * Logout without dispatching event
     *
     * @return $this
     */
    protected function _logout()
    {
        $this->setId(null);
        $this->unsRememberMe();
        $this->setCustomerGroupId(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID);
        $this->getCookie()->delete($this->getSessionName());
        Mage::getSingleton('core/session')->renewFormKey();
        return $this;
    }

    /**
     * Set Before auth url
     *
     * @param string $url
     * @return $this
     */
    public function setBeforeAuthUrl($url)
    {
        return $this->_setAuthUrl('before_auth_url', $url);
    }

    public function getBeforeAuthUrl(bool $clear = false): string
    {
        // null, not false: any non-null second argument makes getData() index into the value
        return (string) $this->getData('before_auth_url', $clear ?: null);
    }

    public function getAfterAuthUrl(bool $clear = false): string
    {
        return (string) $this->getData('after_auth_url', $clear ?: null);
    }

    /**
     * Set After auth url
     *
     * @param string $url
     * @return $this
     */
    public function setAfterAuthUrl($url)
    {
        return $this->_setAuthUrl('after_auth_url', $url);
    }

    /**
     * Reset core session hosts after resetting session ID
     */
    #[\Override]
    public function renewSession(): self
    {
        parent::renewSession();
        Mage::getSingleton('core/session')->unsSessionHosts();

        return $this;
    }

    public function getAddActionReferer(bool $clear = false): ?string
    {
        $value = $this->getData('add_action_referer', $clear ?: null);
        return $value === null ? null : (string) $value;
    }

    public function setAddActionReferer(?string $value): static
    {
        return $this->setData('add_action_referer', $value);
    }

    public function getAddressFormData(bool $clear = false): ?array
    {
        return $this->getData('address_form_data', $clear ?: null);
    }

    public function setAddressFormData(?array $value): static
    {
        return $this->setData('address_form_data', $value);
    }

    public function getBeforeUrl(bool $clear = false): ?string
    {
        $value = $this->getData('before_url', $clear ?: null);
        return $value === null ? null : (string) $value;
    }

    public function setBeforeUrl(?string $value): static
    {
        return $this->setData('before_url', $value);
    }

    public function getBeforeWishlistRequest(bool $clear = false): ?array
    {
        return $this->getData('before_wishlist_request', $clear ?: null);
    }

    public function setBeforeWishlistRequest(?array $value): static
    {
        return $this->setData('before_wishlist_request', $value);
    }

    public function getBeforeWishlistUrl(bool $clear = false): ?string
    {
        $value = $this->getData('before_wishlist_url', $clear ?: null);
        return $value === null ? null : (string) $value;
    }

    public function setBeforeWishlistUrl(?string $value): static
    {
        return $this->setData('before_wishlist_url', $value);
    }

    public function getCustomerFormData(bool $clear = false): ?array
    {
        return $this->getData('customer_form_data', $clear ?: null);
    }

    public function setCustomerFormData(?array $value): static
    {
        return $this->setData('customer_form_data', $value);
    }

    public function getDisplayOutOfStockProducts(bool $clear = false): ?string
    {
        $value = $this->getData('display_out_of_stock_products', $clear ?: null);
        return $value === null ? null : (string) $value;
    }

    public function setDisplayOutOfStockProducts(?string $value): static
    {
        return $this->setData('display_out_of_stock_products', $value);
    }

    public function getForgottenEmail(bool $clear = false): ?string
    {
        $value = $this->getData('forgotten_email', $clear ?: null);
        return $value === null ? null : (string) $value;
    }

    public function setForgottenEmail(?string $value): static
    {
        return $this->setData('forgotten_email', $value);
    }

    public function getNoReferer(bool $clear = false): ?bool
    {
        $value = $this->getData('no_referer', $clear ?: null);
        return $value === null ? null : (bool) $value;
    }

    public function setNoReferer(?bool $value = true): static
    {
        return $this->setData('no_referer', $value);
    }

    public function getRequireTwofa(bool $clear = false): ?bool
    {
        $value = $this->getData('require_twofa', $clear ?: null);
        return $value === null ? null : (bool) $value;
    }

    public function setRequireTwofa(?bool $value = true): static
    {
        return $this->setData('require_twofa', $value);
    }

    public function getTwofaPendingCustomerId(bool $clear = false): ?int
    {
        $value = $this->getData('twofa_pending_customer_id', $clear ?: null);
        return $value === null ? null : (int) $value;
    }

    public function setTwofaPendingCustomerId(?int $value): static
    {
        return $this->setData('twofa_pending_customer_id', $value);
    }

    public function getUsername(bool $clear = false): ?string
    {
        $value = $this->getData('username', $clear ?: null);
        return $value === null ? null : (string) $value;
    }

    public function setUsername(?string $value): static
    {
        return $this->setData('username', $value);
    }

    public function getWishlistDisplayType(bool $clear = false): ?string
    {
        $value = $this->getData('wishlist_display_type', $clear ?: null);
        return $value === null ? null : (string) $value;
    }

    public function setWishlistDisplayType(?string $value): static
    {
        return $this->setData('wishlist_display_type', $value);
    }

    public function getWishlistItemCount(bool $clear = false): ?int
    {
        $value = $this->getData('wishlist_item_count', $clear ?: null);
        return $value === null ? null : (int) $value;
    }

    public function setWishlistItemCount(?int $value): static
    {
        return $this->setData('wishlist_item_count', $value);
    }

}
