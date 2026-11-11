<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

class Mage_Customer_AccountController extends Mage_Core_Controller_Front_Action
{
    public const CUSTOMER_ID_SESSION_NAME = 'customerId';
    public const TOKEN_SESSION_NAME = 'token';

    /**
     * Retrieve customer session model object
     *
     * @return Mage_Customer_Model_Session
     */
    protected function _getSession()
    {
        return Mage::getSingleton('customer/session');
    }

    /**
     * Action predispatch
     *
     * Check customer authentication for some actions
     */
    #[\Override]
    public function preDispatch()
    {
        // @todo a brute-force protection here would be nice

        parent::preDispatch();

        if (!$this->getRequest()->isDispatched()) {
            return $this;
        }

        if (!Mage::getStoreConfigFlag('customer/account/enabled_in_frontend')) {
            $action = strtolower($this->getRequest()->getActionName());
            if ($action === 'logout' || $action === 'logoutsuccess') {
                return $this;
            }
            $this->norouteAction();
            $this->setFlag('', self::FLAG_NO_DISPATCH, true);
            return $this;
        }

        $action = strtolower($this->getRequest()->getActionName());
        $openActions = [
            'create',
            'login',
            'prelogin',
            'logoutsuccess',
            'forgotpassword',
            'forgotpasswordpost',
            'changeforgotten',
            'resetpassword',
            'resetpasswordpost',
            'confirm',
            'confirmation',
            'magiclinkrequestpost',
            'magiclinklogin',
            'twofachallenge',
        ];
        $pattern = '/^(' . implode('|', $openActions) . ')/i';

        if (!preg_match($pattern, $action)) {
            if (!$this->_getSession()->authenticate($this)) {
                $this->setFlag('', 'no-dispatch', true);
            }
        } else {
            $this->_getSession()->setNoReferer();
        }
        return $this;
    }

    /**
     * Action postdispatch
     *
     * Remove No-referer flag from customer session after each action
     */
    #[\Override]
    public function postDispatch()
    {
        parent::postDispatch();
        $this->_getSession()->unsNoReferer(false);
        return $this;
    }

    /**
     * Default customer account page
     */
    #[Maho\Config\Route('/customer/account', name: 'customer.account.index', methods: ['GET'])]
    public function indexAction(): void
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');

        $this->getLayout()->getBlock('content')->append(
            $this->getLayout()->createBlock('customer/account_dashboard'),
        );
        $this->getLayout()->getBlock('head')->setTitle($this->__('My Account'));
        $this->renderLayout();
    }

    /**
     * Customer login form page
     */
    #[Maho\Config\Route('/customer/account/login', name: 'customer.account.login', methods: ['GET'])]
    public function loginAction(): void
    {
        if ($this->_getSession()->isLoggedIn()) {
            $this->_redirect('*/*/');
            return;
        }
        $this->getResponse()->setHeader('Login-Required', 'true');
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');
        $this->getLayout()->getBlock('head')->setTitle($this->__('Customer Login'));
        $this->renderLayout();
    }

    /**
     * Login post action
     */
    #[Maho\Config\Route('/customer/account/loginPost', name: 'customer.account.loginPost', methods: ['POST'])]
    public function loginPostAction(): void
    {
        if ($this->_getSession()->isLoggedIn()) {
            $this->_redirect('*/*/');
            return;
        }
        $session = $this->_getSession();

        if ($this->getRequest()->isPost()) {
            $login = $this->getRequest()->getPost('login');
            if (!empty($login['username']) && !empty($login['password'])) {
                try {
                    $customer = $this->getService()->authenticate(
                        (string) $login['username'],
                        (string) $login['password'],
                        (int) Mage::app()->getStore()->getWebsiteId(),
                        isset($login['twofa_verification_code']) ? (string) $login['twofa_verification_code'] : null,
                    );
                    $session->setRememberMe((bool) $this->getRequest()->getPost('remember_me'))
                            ->setCustomerAsLoggedIn($customer);
                    if ($session->getCustomer()->getIsJustConfirmed()) {
                        $this->_welcomeCustomer($session->getCustomer(), true);
                    }
                } catch (Mage_Core_Exception $e) {
                    if ($e->getCode() === Mage_Customer_Model_Customer::EXCEPTION_EMAIL_NOT_CONFIRMED) {
                        /** @var Mage_Customer_Helper_Data $helper */
                        $helper = Mage::helper('customer');
                        $session->addError(
                            $helper->__('This account is not confirmed. %s to resend confirmation email.'),
                            new \Maho\Message\Link(
                                $helper->__('Click here'),
                                $helper->getEmailConfirmationUrl($login['username']),
                            ),
                        );
                    } else {
                        $session->addError($e->getMessage());
                    }
                    $session->setUsername($login['username']);
                } catch (Exception) {
                    // Mage::logException($e); // PA DSS violation: this exception log can disclose customer password
                }

                // A failed attempt must not leave Remember Me set, or an unauthenticated visitor
                // stretches their session cookie to the Remember Me lifetime
                if (!$session->isLoggedIn()) {
                    $session->unsRememberMe();
                }
            } else {
                $session->addError($this->__('Login and password are required.'));
            }
        }

        $this->_loginPostRedirect();
    }

    /**
     * Detect whether 2FA is required before completing the login
     */
    #[Maho\Config\Route('/customer/account/prelogin', name: 'customer.account.prelogin', methods: ['POST'])]
    public function preloginAction(): void
    {
        $result = [];

        $session = $this->_getSession();
        $login = $this->getRequest()->getPost('login');
        if (!$session->isLoggedIn() && Mage::getStoreConfigFlag('customer/password/allow_2fa')
            && !empty($login['username']) && !empty($login['password'])
        ) {
            try {
                $this->getService()->authenticate((string) $login['username'], (string) $login['password'], (int) Mage::app()->getStore()->getWebsiteId());
            } catch (Mage_Core_Exception $e) {
                if ($e->getCode() === Mage_Customer_Model_Customer::EXCEPTION_2FA_INVALID) {
                    $result['require_twofa'] = true;
                }
            } catch (Exception) {
                // Mage::logException($e); // PA DSS violation: this exception log can disclose customer password
            }
        }

        $this->getResponse()->setBodyJson($result);
    }

    /**
     * Define target URL and redirect customer after logging in
     */
    protected function _loginPostRedirect()
    {
        Mage::dispatchEvent('customer_controller_account_login_post_redirect_before', ['controller' => $this]);
        /** @var Mage_Customer_Helper_Data $helper */
        $helper = Mage::helper('customer');

        $session = $this->_getSession();

        // A failed login must stay on the login form, not follow a stale
        // beforeAuthUrl (e.g. checkout). The URL is kept for the next attempt.
        if (!$session->isLoggedIn()) {
            $this->_redirectUrl($helper->getLoginUrl());
            return;
        }

        if (!$session->getBeforeAuthUrl() || $session->getBeforeAuthUrl() === Mage::getBaseUrl()) {
            // Set default URL to redirect customer to
            $session->setBeforeAuthUrl($helper->getAccountUrl());
            // Redirect customer to the last page visited after logging in
            if ($session->isLoggedIn()) {
                if (!Mage::getStoreConfigFlag(Mage_Customer_Helper_Data::XML_PATH_CUSTOMER_LOGIN_REDIRECT_TO_DASHBOARD)) {
                    $referer = $this->getRequest()->getParam(Mage_Customer_Helper_Data::REFERER_QUERY_PARAM_NAME);
                    if ($referer) {
                        // Rebuild referer URL to handle the case when SID was changed
                        $referer = Mage::getModel('core/url')
                            ->getRebuiltUrl(Mage::helper('core')->urlDecodeAndEscape($referer));
                        if ($this->_isUrlInternal($referer)) {
                            $session->setBeforeAuthUrl($referer);
                        }
                    }
                } elseif ($session->getAfterAuthUrl()) {
                    $session->setBeforeAuthUrl($session->getAfterAuthUrl(true));
                }
            } else {
                $session->setBeforeAuthUrl($helper->getLoginUrl());
            }
        } elseif ($session->getBeforeAuthUrl() === $helper->getLogoutUrl()) {
            $session->setBeforeAuthUrl($helper->getDashboardUrl());
        } else {
            if (!$session->getAfterAuthUrl()) {
                $session->setAfterAuthUrl($session->getBeforeAuthUrl());
            }
            if ($session->isLoggedIn()) {
                $session->setBeforeAuthUrl($session->getAfterAuthUrl(true));
            }
        }
        $this->_redirectUrl($session->getBeforeAuthUrl(true));
    }

    /**
     * Customer logout action
     */
    #[Maho\Config\Route('/customer/account/logout', name: 'customer.account.logout', methods: ['GET'])]
    public function logoutAction(): void
    {
        $session = $this->_getSession();
        $session->logout()->renewSession();

        if (Mage::getStoreConfigFlag(Mage_Customer_Helper_Data::XML_PATH_CUSTOMER_LOGIN_REDIRECT_TO_DASHBOARD)) {
            $session->setBeforeAuthUrl(Mage::getBaseUrl());
        } else {
            $session->setBeforeAuthUrl($this->_getRefererUrl());
        }
        $this->_redirect('*/*/logoutSuccess');
    }

    /**
     * Logout success page
     */
    #[Maho\Config\Route('/customer/account/logoutSuccess', name: 'customer.account.logoutSuccess', methods: ['GET'])]
    public function logoutSuccessAction(): void
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    /**
     * Customer register form page
     * Forwards to login page which now includes registration form in tabs
     */
    #[Maho\Config\Route('/customer/account/create', name: 'customer.account.create', methods: ['GET'])]
    public function createAction(): void
    {
        if ($this->_getSession()->isLoggedIn()) {
            $this->_redirect('*/*');
            return;
        }

        // Set parameter to show register tab by default
        $this->getRequest()->setParam('show_register', true);

        // Forward to login action - registration form is now in tabs
        $this->_forward('login');
    }

    /**
     * Create customer account action
     */
    #[Maho\Config\Route('/customer/account/createPost', name: 'customer.account.createPost', methods: ['POST'])]
    public function createPostAction(): void
    {
        $errUrl = $this->_getUrl('*/*/create', ['_secure' => true]);

        $session = $this->_getSession();
        if ($session->isLoggedIn()) {
            $this->_redirect('*/*/');
            return;
        }

        if (!$this->getRequest()->isPost()) {
            $this->_redirectError($errUrl);
            return;
        }

        $customer = $this->_getCustomer();

        try {
            $errors = $this->_getCustomerErrors($customer);

            if (empty($errors)) {
                $this->getService()->register($customer, (int) Mage::app()->getStore()->getId(), $session->getBeforeAuthUrl());
                $this->_successProcessRegistration($customer);
                return;
            }
            $this->_addSessionError($errors);
        } catch (Mage_Core_Exception_Input $e) {
            $this->_addSessionError(array_column($e->getErrors(), 'message'));
        } catch (Mage_Core_Exception $e) {
            $session->setCustomerFormData($this->getRequest()->getPost());
            if ($e->getCode() === Mage_Customer_Model_Customer::EXCEPTION_EMAIL_EXISTS) {
                $session->addError(
                    $this->__('There is already an account with this email address. If you are sure that it is your email address, %s to get your password and access your account.'),
                    new \Maho\Message\Link($this->__('click here'), $this->_getUrl('customer/account/forgotpassword')),
                );
            } else {
                $session->addError($e->getMessage());
            }
        } catch (Exception $e) {
            $session->setCustomerFormData($this->getRequest()->getPost());
            $session->addException($e, $this->__('Cannot save the customer.'));
        }

        $this->_redirectError($errUrl);
    }

    /**
     * Success Registration
     *
     * @return $this
     * @throws Mage_Core_Exception
     */
    protected function _successProcessRegistration(Mage_Customer_Model_Customer $customer)
    {
        $session = $this->_getSession();
        $magicLinkMode = Mage::helper('customer')->getMagicLinkRegistrationMode();

        if ($customer->isConfirmationRequired()) {
            /** @var Mage_Customer_Helper_Data $customerHelper */
            $customerHelper = Mage::helper('customer');
            $session->addSuccess(
                $this->__('Account confirmation is required. Please, check your email for the confirmation link. To resend the confirmation email please %s.'),
                new \Maho\Message\Link(
                    $this->__('click here'),
                    $customerHelper->getEmailConfirmationUrl($customer->getEmail()),
                ),
            );
            $url = $this->_getUrl('*/*/index', ['_secure' => true]);
        } elseif ($magicLinkMode === Mage_Customer_Model_Config_Registrationmode::MODE_NO_PASSWORD) {
            $session->addSuccess($this->__(
                'Thank you for registering! We have sent you an email with a login link to %s.',
                $customer->getEmail(),
            ));
            $url = $this->_getUrl('*/*/index', ['_secure' => true]);
        } else {
            $session->setRememberMe((bool) $this->getRequest()->getPost('remember_me'))
                    ->setCustomerAsLoggedIn($customer);
            $url = $this->_welcomeCustomer($customer);
        }
        $this->_redirectSuccess($url);
        return $this;
    }

    /**
     * Get Customer Model
     *
     * @return Mage_Customer_Model_Customer
     */
    protected function _getCustomer()
    {
        /** @var ?Mage_Customer_Model_Customer $customer */
        $customer = Mage::registry('current_customer');
        if (!$customer) {
            $customer = Mage::getModel('customer/customer')->setId(null);
        }
        if ($this->getRequest()->getParam('is_subscribed', false)) {
            $customer->setIsSubscribed(1);
        }
        /**
         * Initialize customer group id
         */
        $customer->getGroupId();

        return $customer;
    }

    /**
     * Add session error method
     *
     * @param string|array $errors
     */
    protected function _addSessionError($errors)
    {
        $session = $this->_getSession();
        $session->setCustomerFormData($this->getRequest()->getPost());
        if (is_array($errors)) {
            foreach ($errors as $errorMessage) {
                $session->addError($errorMessage);
            }
        } else {
            $session->addError($this->__('Invalid customer data'));
        }
    }

    /**
     * Validate customer data and return errors if they are
     *
     * @param Mage_Customer_Model_Customer $customer
     * @return array
     */
    protected function _getCustomerErrors($customer)
    {
        $errors = [];
        $request = $this->getRequest();
        if ($request->getPost('create_address')) {
            $errors = $this->_getErrorsOnCustomerAddress($customer);
        }
        $customerForm = $this->_getCustomerForm($customer);
        $customerData = $customerForm->extractData($request);
        $customerErrors = $customerForm->validateData($customerData);
        if ($customerErrors !== true) {
            $errors = array_merge($customerErrors, $errors);
        } else {
            $customerForm->compactData($customerData);
            $customer->setPassword((string) $request->getPost('password'));
            $customer->setPasswordConfirmation((string) $request->getPost('confirmation'));
        }
        return $errors;
    }

    /**
     * Get Customer Form Initialized Model
     *
     * @param Mage_Customer_Model_Customer $customer
     * @return Mage_Customer_Model_Form
     */
    protected function _getCustomerForm($customer)
    {
        $customerForm = Mage::getModel('customer/form');
        $customerForm->setFormCode('customer_account_create');
        $customerForm->setEntity($customer);
        return $customerForm;
    }

    /**
     * Gets customer address
     *
     * @param Mage_Customer_Model_Customer $customer
     * @return array $errors
     */
    protected function _getErrorsOnCustomerAddress($customer)
    {
        $errors = [];
        $address = Mage::getModel('customer/address');
        $addressForm = Mage::getModel('customer/form');
        $addressForm->setFormCode('customer_register_address')
            ->setEntity($address);

        $addressData = $addressForm->extractData($this->getRequest(), 'address', false);
        $addressErrors = $addressForm->validateData($addressData);
        if (is_array($addressErrors)) {
            $errors = array_merge($errors, $addressErrors);
        }
        $address->setId(null)
            ->setIsDefaultBilling($this->getRequest()->getParam('default_billing', false))
            ->setIsDefaultShipping($this->getRequest()->getParam('default_shipping', false));
        $addressForm->compactData($addressData);
        $customer->addAddress($address);
        return $errors;
    }

    /**
     * Add the welcome message and return the success URL. Mage_Customer_Service_Customer sends the welcome email:
     * register() for a new account and confirm() for a confirmed account.
     *
     * @param bool $isJustConfirmed not used
     * @return string
     * @throws Mage_Core_Model_Store_Exception
     * @throws Mage_Core_Exception
     */
    protected function _welcomeCustomer(Mage_Customer_Model_Customer $customer, $isJustConfirmed = false)
    {
        $this->_getSession()->addSuccess(
            $this->__('Thank you for registering with %s.', Mage::app()->getStore()->getFrontendName()),
        );
        if ($this->_isVatValidationEnabled()) {
            // Show corresponding VAT message to customer
            /** @var Mage_Customer_Helper_Address $helper */
            $helper = Mage::helper('customer/address');
            $configAddressType = $helper->getTaxCalculationAddressType();
            $userPrompt = match ($configAddressType) {
                Mage_Customer_Model_Address_Abstract::TYPE_SHIPPING => $this->__('If you are a registered VAT customer, please %s to enter you shipping address for proper VAT calculation'),
                default => $this->__('If you are a registered VAT customer, please %s to enter you billing address for proper VAT calculation'),
            };
            $this->_getSession()->addSuccess(
                $userPrompt,
                new \Maho\Message\Link($this->__('click here'), $this->_getUrl('customer/address/edit')),
            );
        }

        $successUrl = $this->_getUrl('*/*/index', ['_secure' => true]);
        if ($this->_getSession()->getBeforeAuthUrl()) {
            $successUrl = $this->_getSession()->getBeforeAuthUrl(true);
        }
        return $successUrl;
    }

    /**
     * Confirm customer account by id and confirmation key
     */
    #[Maho\Config\Route('/customer/account/confirm', name: 'customer.account.confirm', methods: ['GET'])]
    public function confirmAction(): void
    {
        $session = $this->_getSession();
        if ($session->isLoggedIn()) {
            $this->_getSession()->logout()->regenerateSessionId();
        }
        try {
            $id      = (int) $this->getRequest()->getParam('id');
            $key     = $this->getRequest()->getParam('key');
            $backUrl = $this->getRequest()->getParam('back_url', false);
            if (!$id || !is_string($key) || $key === '') {
                throw new Mage_Core_Exception($this->__('Bad request.'));
            }

            $customer = $this->getService()->confirm($id, $key);
            if ($customer->getIsJustConfirmed()) {
                // Customers who opted into 2FA must clear it before the session is logged in
                if ($session->shouldChallengeTwofa($customer)) {
                    $session->startTwofaChallenge($customer);
                    $this->_redirect('*/*/twofaChallenge');
                    return;
                }

                // log in and send greeting email, then die happy
                $session->setCustomerAsLoggedIn($customer);
                $successUrl = $this->_welcomeCustomer($customer, true);
                $this->_redirectSuccess($backUrl ?: $successUrl);
                return;
            }

            // die happy
            $this->_redirectSuccess($this->_getUrl('*/*/index', ['_secure' => true]));
            return;
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_redirectError($this->_getUrl('*/*/index', ['_secure' => true]));
            return;
        }
    }

    /**
     * Send confirmation link to specified email
     */
    #[Maho\Config\Route('/customer/account/confirmation', name: 'customer.account.confirmation')]
    public function confirmationAction(): void
    {
        if ($this->_getSession()->isLoggedIn()) {
            $this->_redirect('*/*/');
            return;
        }

        $email = (string) $this->getRequest()->getPost('email');
        if ($email !== '') {
            try {
                $this->getService()->sendConfirmationLink($email, (int) Mage::app()->getStore()->getWebsiteId());
                // The same answer for each address, so that it does not show which addresses have an account
                $this->_getSession()->addSuccess($this->__(
                    'If an account with %s waits for a confirmation, you will receive an email with the confirmation link.',
                    $email,
                ));
                $this->_getSession()->setUsername($email);
                $this->_redirectSuccess($this->_getUrl('*/*/index', ['_secure' => true]));
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
                $this->_redirectError($this->_getUrl('*/*/*', ['email' => $email, '_secure' => true]));
            }
            return;
        }

        // output form
        $this->loadLayout();

        $this->getLayout()->getBlock('accountConfirmation')
            ->setEmail($this->getRequest()->getParam('email', $email));

        $this->_initLayoutMessages('customer/session');
        $this->renderLayout();
    }

    /**
     * Get Url method
     *
     * @param string $url
     * @param array $params
     * @return string
     */
    protected function _getUrl($url, $params = [])
    {
        return Mage::getUrl($url, $params);
    }

    /**
     * Forgot customer password page
     * Forwards to login page which now includes forgot password in dialog
     */
    #[Maho\Config\Route('/customer/account/forgotpassword', name: 'customer.account.forgotPassword', methods: ['GET'])]
    public function forgotPasswordAction(): void
    {
        // Set parameter to auto-open forgot password dialog
        $this->getRequest()->setParam('show_forgot', true);

        // Forward to login action - forgot password is now a dialog
        $this->_forward('login');
    }

    /**
     * Forgot customer password action
     * @throws Mage_Core_Model_Store_Exception
     */
    #[Maho\Config\Route('/customer/account/forgotPasswordPost', name: 'customer.account.forgotPasswordPost', methods: ['POST'])]
    public function forgotPasswordPostAction(): void
    {
        $email = (string) $this->getRequest()->getPost('email');
        $session = $this->_getSession();
        try {
            $this->getService()->sendPasswordResetLink($email, (int) Mage::app()->getStore()->getWebsiteId());
            $session->addSuccess($this->__(
                'If there is an account associated with %s you will receive an email with a link to reset your password.',
                $email,
            ));
            $this->_redirect('*/*/');
            return;
        } catch (Mage_Core_Exception_Input $e) {
            $session->setForgottenEmail($email);
            $session->addError($e->getMessage());
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        }
        $this->_redirect('*/*/forgotpassword');
    }

    /**
     * Display reset forgotten password form
     */
    #[Maho\Config\Route('/customer/account/changeForgotten', name: 'customer.account.changeForgotten', methods: ['GET'])]
    public function changeForgottenAction(): void
    {
        try {
            [$customerId, $resetPasswordLinkToken] = $this->_getRestorePasswordParameters($this->_getSession());
            $this->_validateResetPasswordLinkToken($customerId, $resetPasswordLinkToken);
            $this->loadLayout();
            $this->renderLayout();
        } catch (Exception) {
            $this->_getSession()->addError(Mage::helper('customer')->__('Your password reset link has expired.'));
            $this->_redirect('*/*/forgotpassword');
        }
    }

    /**
     * Checks reset forgotten password token
     *
     * User is redirected on this action when he clicks on the corresponding link in password reset confirmation email.
     */
    #[Maho\Config\Route('/customer/account/resetPassword', name: 'customer.account.resetPassword', methods: ['GET'])]
    public function resetPasswordAction(): void
    {
        $customerId = (int) $this->getCustomerId();
        $resetPasswordLinkToken = (string) $this->getRequest()->getQuery('token');
        try {
            $this->_validateResetPasswordLinkToken($customerId, $resetPasswordLinkToken);
            $this->_saveRestorePasswordParameters($customerId, $resetPasswordLinkToken)
                ->_redirect('*/*/changeforgotten');
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('*/*/forgotpassword');
        }
    }

    /**
     * Reset forgotten password
     * Used to handle data received from reset forgotten password form
     */
    #[Maho\Config\Route('/customer/account/resetPasswordPost', name: 'customer.account.resetPasswordPost', methods: ['POST'])]
    public function resetPasswordPostAction(): void
    {
        [$customerId, $resetPasswordLinkToken] = $this->_getRestorePasswordParameters($this->_getSession());
        $password = (string) $this->getRequest()->getPost('password');
        $passwordConfirmation = (string) $this->getRequest()->getPost('confirmation');

        $session = $this->_getSession();
        try {
            $this->getService()->resetPasswordById($customerId, $resetPasswordLinkToken, $password, $passwordConfirmation);
            $session->unsetData(self::TOKEN_SESSION_NAME);
            $session->unsetData(self::CUSTOMER_ID_SESSION_NAME);
            $session->addSuccess($this->__('Your password has been updated.'));
            $this->_redirect('*/*/login');
            return;
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $session->addError($error['message']);
            }
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
            $this->_redirect('*/*/');
            return;
        } catch (Throwable $e) {
            Mage::logException($e);
            $session->addError($this->__('Cannot save a new password.'));
        }
        $session->setCustomerFormData($this->getRequest()->getPost());
        $this->_redirect('*/*/changeforgotten');
    }

    /**
     * Process magic link request
     */
    #[Maho\Config\Route('/customer/account/magicLinkRequestPost', name: 'customer.account.magicLinkRequestPost', methods: ['POST'])]
    public function magicLinkRequestPostAction(): void
    {
        // Handle both 'email' field (from magic link request page) and 'login[username]' (from login form)
        $email = (string) $this->getRequest()->getPost('email');
        if (!$email) {
            $loginData = $this->getRequest()->getPost('login');
            $email = is_array($loginData) ? (string) ($loginData['username'] ?? '') : '';
        }

        try {
            Mage::getService('customer/customer')->sendMagicLink($email, (int) Mage::app()->getStore()->getWebsiteId());
        } catch (Mage_Core_Exception_NoSuchEntity) {
            $this->norouteAction();
            return;
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $this->_getSession()->addError($error['message']);
            }
            $this->_redirect('*/*/magiclinkrequest');
            return;
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('*/*/magiclinkrequest');
            return;
        }

        // Always show success message (security: don't reveal if email exists)
        $this->_getSession()
            ->addSuccess(Mage::helper('customer')
                ->__(
                    'If there is an account associated with %s you will receive an email with a login link.',
                    $email,
                ));
        $this->_redirect('*/*/');
        return;
    }

    /**
     * Validate magic link token and login customer
     */
    #[Maho\Config\Route('/customer/account/magicLinkLogin', name: 'customer.account.magicLinkLogin', methods: ['GET'])]
    public function magicLinkLoginAction(): void
    {
        // Check if magic link is enabled
        if (!Mage::helper('customer')->isMagicLinkEnabled()) {
            $this->norouteAction();
            return;
        }

        if ($this->_getSession()->isLoggedIn()) {
            $this->_redirect('*/*/');
            return;
        }

        $token = (string) $this->getRequest()->getParam('token');

        if (empty($token)) {
            $this->_getSession()->addError($this->__('Invalid login link.'));
            $this->_redirect('*/*/login');
            return;
        }

        try {
            $customer = $this->getService()->getByMagicLinkToken($token);

            // Customers who opted into 2FA must still clear it before the session is logged in
            if ($this->_getSession()->shouldChallengeTwofa($customer)) {
                $this->_getSession()->startTwofaChallenge($customer);
                $this->_redirect('*/*/twofaChallenge');
                return;
            }

            // Login customer
            $this->_getSession()->setCustomerAsLoggedIn($customer);

            $this->_getSession()->addSuccess($this->__('You have been successfully logged in.'));

            // Redirect using same logic as normal login
            $this->_loginPostRedirect();
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->_redirect('*/*/login');
        }
    }

    /**
     * @return string|false
     */
    protected function getCustomerId()
    {
        $customerId = $this->getRequest()->getQuery('id', false);
        if (is_string($customerId) && strlen($customerId) > 12) {
            $customerCollection = Mage::getModel('customer/customer')
                ->getCollection()
                ->addAttributeToSelect(['rp_customer_id'])
                ->addFieldToFilter('rp_customer_id', $customerId);
            $customerId = count($customerCollection) === 1
                ? $customerCollection->getFirstItem()->getId()
                : false;
        }

        return $customerId;
    }

    /**
     * Check if password reset token is valid
     *
     * @param int $customerId
     * @param string $resetPasswordLinkToken
     * @throws Mage_Core_Exception
     */
    protected function _validateResetPasswordLinkToken($customerId, $resetPasswordLinkToken)
    {
        $this->getService()->getByResetToken((int) $customerId, (string) $resetPasswordLinkToken);
    }

    /**
     * Forgot customer account information page
     */
    #[Maho\Config\Route('/customer/account/edit', name: 'customer.account.edit', methods: ['GET'])]
    public function editAction(): void
    {
        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');
        $this->_initLayoutMessages('catalog/session');

        $block = $this->getLayout()->getBlock('customer_edit');
        if ($block) {
            $block->setRefererUrl($this->_getRefererUrl());
        }
        $data = $this->_getSession()->getCustomerFormData(true);
        $customer = $this->_getSession()->getCustomer();
        if (!empty($data)) {
            $customerForm = Mage::getModel('customer/form')
                ->setFormCode('customer_account_edit')
                ->setEntity($customer);
            $customer->addData(array_intersect_key($data, $customerForm->getAttributes()));
        }
        if ($this->getRequest()->getParam('changepass') == 1) {
            $customer->setChangePassword(1);
        }

        $this->getLayout()->getBlock('head')->setTitle($this->__('Account Information'));
        $this->renderLayout();
    }

    /**
     * Change customer password action
     */
    #[Maho\Config\Route('/customer/account/editPost', name: 'customer.account.editPost', methods: ['POST'])]
    public function editPostAction()
    {
        if (!$this->getRequest()->isPost()) {
            $this->_redirect('*/*/edit');
            return;
        }

        $session = $this->_getSession();
        $customer = $session->getCustomer();
        $customerForm = Mage::getModel('customer/form');
        $customerForm->setFormCode('customer_account_edit')->setEntity($customer);
        $customerData = $customerForm->extractData($this->getRequest());

        $errors = $customerForm->validateData($customerData);
        if ($errors === true) {
            $errors = [];
            $customerForm->compactData($customerData);
            if ($this->getRequest()->getParam('change_password')) {
                $newPassword = (string) $this->getRequest()->getPost('password');
                if ($newPassword === '') {
                    $errors[] = $this->__('New password field cannot be empty.');
                }
                $customer->setPassword($newPassword)
                    ->setPasswordConfirmation((string) $this->getRequest()->getPost('confirmation'));
            }
        }

        try {
            if ($errors === []) {
                $this->getService()->updateAccount($customer, (string) $this->getRequest()->getPost('current_password'), requirePassword: true);
                $session->setCustomer($customer)->addSuccess($this->__('The account information has been saved.'));
                $this->_redirect('customer/account');
                return;
            }
            foreach ($errors as $message) {
                $session->addError($message);
            }
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $session->addError($error['message']);
            }
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Throwable $e) {
            Mage::logException($e);
            $session->addError($this->__('Cannot save the customer.'));
        }
        $session->setCustomerFormData($this->getRequest()->getPost());
        $this->_redirect('*/*/edit');
    }

    /**
     * Two-factor authentication management page
     */
    #[Maho\Config\Route('/customer/account/twofa', name: 'customer.account.twofa', methods: ['GET'])]
    public function twofaAction(): void
    {
        if (!Mage::getStoreConfigFlag('customer/password/allow_2fa')) {
            $this->norouteAction();
            return;
        }

        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');

        $this->getLayout()->getBlock('head')->setTitle($this->__('Two-Factor Authentication'));
        $this->renderLayout();
    }

    /**
     * Enable or disable two-factor authentication for the current customer
     */
    #[Maho\Config\Route('/customer/account/twofaPost', name: 'customer.account.twofaPost', methods: ['POST'])]
    public function twofaPostAction(): void
    {
        $session = $this->_getSession();
        $customer = $session->getCustomer();

        try {
            $enable = $this->getRequest()->getPost('action') !== 'disable';
            $this->getService()->setSecondFactor(
                $customer,
                (string) $this->getRequest()->getPost('current_password'),
                $enable,
                (string) $this->getRequest()->getPost('twofa_verification_code'),
            );
            $session->addSuccess($enable
                ? $this->__('Two-factor authentication has been enabled.')
                : $this->__('Two-factor authentication has been disabled.'));
        } catch (Mage_Core_Exception_NoSuchEntity) {
            $this->norouteAction();
            return;
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Exception $e) {
            $session->addException($e, $this->__('An error occurred while updating two-factor authentication.'));
        }

        $this->_redirect('*/*/twofa');
    }

    /**
     * Prompt for the 2FA code after a passwordless login (magic link, account confirmation)
     */
    #[Maho\Config\Route('/customer/account/twofaChallenge', name: 'customer.account.twofaChallenge', methods: ['GET'])]
    public function twofaChallengeAction(): void
    {
        $session = $this->_getSession();
        if ($session->isLoggedIn() || !$session->getTwofaPendingCustomer()) {
            $this->_redirect('*/*/login');
            return;
        }

        $this->loadLayout();
        $this->_initLayoutMessages('customer/session');

        $this->getLayout()->getBlock('head')->setTitle($this->__('Two-Factor Authentication'));
        $this->renderLayout();
    }

    /**
     * Verify the 2FA code and complete a pending passwordless login
     */
    #[Maho\Config\Route('/customer/account/twofaChallengePost', name: 'customer.account.twofaChallengePost', methods: ['POST'])]
    public function twofaChallengePostAction(): void
    {
        $session = $this->_getSession();
        if ($session->isLoggedIn() || !$session->getTwofaPendingCustomer()) {
            $this->_redirect('*/*/login');
            return;
        }

        $code = (string) $this->getRequest()->getPost('twofa_verification_code');
        try {
            if ($session->completeTwofaChallenge($code)) {
                $session->addSuccess($this->__('You have been successfully logged in.'));
                $this->_loginPostRedirect();
                return;
            }
            $session->addError($this->__('2FA verification code is invalid.'));
        } catch (Mage_Core_Exception_TooManyRequests $e) {
            $session->addError($e->getMessage());
        }
        $this->_redirect('*/*/twofaChallenge');
    }

    /**
     * Filtering posted data. Converting localized data if needed
     *
     * @param array $data
     * @return array
     */
    protected function _filterPostData($data)
    {
        $data = $this->_filterDates($data, ['dob']);
        return $data;
    }

    /**
     * Check whether VAT ID validation is enabled
     *
     * @param Mage_Core_Model_Store|string|int $store
     * @return bool
     */
    protected function _isVatValidationEnabled($store = null)
    {
        /** @var Mage_Customer_Helper_Address $helper */
        $helper = Mage::helper('customer/address');
        return $helper->isVatValidationEnabled($store);
    }

    /**
     * Get restore password params.
     *
     * @return array array ($customerId, $resetPasswordToken)
     */
    protected function _getRestorePasswordParameters(Mage_Customer_Model_Session $session)
    {
        return [
            (int) $session->getData(self::CUSTOMER_ID_SESSION_NAME),
            (string) $session->getData(self::TOKEN_SESSION_NAME),
        ];
    }

    /**
     * Save restore password params to session.
     *
     * @param int $customerId
     * @param  string $resetPasswordLinkToken
     * @return $this
     */
    protected function _saveRestorePasswordParameters($customerId, $resetPasswordLinkToken)
    {
        $this->_getSession()
            ->setData(self::CUSTOMER_ID_SESSION_NAME, $customerId)
            ->setData(self::TOKEN_SESSION_NAME, $resetPasswordLinkToken);

        return $this;
    }

    protected function getService(): Mage_Customer_Service_Customer
    {
        return Mage::getService('customer/customer');
    }
}
