<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

/**
 * @method Mage_Admin_Model_Acl getAcl()
 * @method $this setAcl(Mage_Admin_Model_Acl $acl)
 * @method int getActiveTabId()
 * @method $this setActiveTabId(int $value)
 * @method $this unsActiveTabId()
 * @method $this setAttributeData(array|false $data)
 * @method bool getIndirectLogin()
 * @method $this setIndirectLogin(bool $value)
 * @method $this setIsFirstVisit(bool $value)
 * @method string getPasskeyChallenge()
 * @method $this setPasskeyChallenge(string $value)
 * @method $this unsPasskeyChallenge()
 * @method bool getUserPasswordChanged()
 * @method $this setUserPasswordChanged(bool $value)
 * @method bool hasSyncProcessStopWatch()
 * @method bool getSyncProcessStopWatch()
 * @method $this setSyncProcessStopWatch(bool $value)
 * @method bool getShowTwofaVerificationCode()
 * @method $this setShowTwofaVerificationCode(bool $value)
 * @method Mage_Admin_Model_User getUser()
 * @method $this setUser(Mage_Admin_Model_User $user)
 */
class Mage_Admin_Model_Session extends Mage_Core_Model_Session_Abstract
{
    /**
     * Whether it is the first page after successful login
     *
     * @var bool|null
     */
    protected $_isFirstPageAfterLogin;

    /**
     * @var Mage_Admin_Model_Redirectpolicy
     */
    protected $_urlPolicy;

    /**
     * @var Mage_Core_Controller_Response_Http
     */
    protected $_response;

    /**
     * @var Mage_Core_Model_Factory
     */
    protected $_factory;

    /**
     * Class constructor
     * @param array $parameters
     */
    public function __construct($parameters = [])
    {
        $this->_urlPolicy = (empty($parameters['redirectPolicy'])) ?
            Mage::getModel('admin/redirectpolicy') : $parameters['redirectPolicy'];

        $this->_response = (empty($parameters['response'])) ?
            new Mage_Core_Controller_Response_Http() : $parameters['response'];

        $this->_factory = (empty($parameters['factory'])) ?
            Mage::getModel('core/factory') : $parameters['factory'];

        $this->init('admin');
        $this->logoutIndirect();
    }

    /**
     * Pull out information from session whether there is currently the first page after log in
     *
     * The idea is to set this value on login(), then redirect happens,
     * after that on next request the value is grabbed once the session is initialized
     * Since the session is used as a singleton, the value will be in $_isFirstPageAfterLogin until the end of request,
     * unless it is reset intentionally from somewhere
     *
     * @see self::login()
     */
    #[\Override]
    public function init(string $namespace, ?string $sessionName = null): self
    {
        parent::init($namespace, $sessionName);
        $this->isFirstPageAfterLogin();
        return $this;
    }

    /**
     * Logout user if was logged not from admin
     */
    protected function logoutIndirect()
    {
        $user = $this->getUser();
        if ($user) {
            $extraData = $user->getExtra();
            if (isset($extraData['indirect_login']) && $this->getIndirectLogin()) {
                $this->unsetData('user');
                $this->setIndirectLogin(false);
            }
        }
    }

    /**
     * Logout user from admin
     */
    public function logout(): void
    {
        $user = $this->getUser();
        if ($user) {
            Mage::dispatchEvent('admin_session_user_logout', ['user' => $user]);
        }

        $this->unsetAll();
        $this->getCookie()->delete($this->getSessionName());
    }

    /**
     * Check if 2fa is required
     */
    public function prelogin(#[\SensitiveParameter] string $username, #[\SensitiveParameter] string $password, ?Mage_Core_Controller_Request_Http $request = null): void
    {
        try {
            if (!empty($username) && !empty($password)) {
                /** @var Mage_Admin_Model_User $user */
                $user = $this->_factory->getModel('admin/user');
                $user->authenticate($username, $password);
            }
        } catch (Mage_Core_Exception $e) {
            if ($e->getCode() === Mage_Admin_Model_User::AUTH_ERR_2FA_INVALID) {
                $this->setRequireTwofa(true);
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Try to login user in admin
     * @return Mage_Admin_Model_User|null
     */
    public function login(#[\SensitiveParameter] string $username, #[\SensitiveParameter] string $password, ?Mage_Core_Controller_Request_Http $request = null, #[\SensitiveParameter] ?string $twofaVerificationCode = null)
    {
        if (empty($username) || empty($password)) {
            return null;
        }

        $user = null;

        try {
            /** @var Mage_Admin_Model_User $user */
            $user = $this->_factory->getModel('admin/user');
            $user->login($username, $password, $twofaVerificationCode);
            if ($user->getId()) {
                $this->renewSession();

                // Skip the admin-menu cache flush for keyless (RSS basic-auth) logins, which
                // re-run login() on every poll: they never render the admin menu, so flushing
                // it each poll would rebuild it for every real admin on their next page view.
                if (Mage::getSingleton('adminhtml/url')->useSecretKey()) {
                    Mage::getSingleton('adminhtml/url')->renewSecretUrls();
                }
                $this->setIsFirstPageAfterLogin(true);
                $this->setUser($user);
                $this->setAcl(Mage::getResourceModel('admin/acl')->loadAcl());
                if ($backendLocale = $user->getBackendLocale()) {
                    Mage::getSingleton('adminhtml/session')->setLocale($backendLocale);
                }

                // The redirect policy bails out on an empty request (RSS basic-auth logins),
                // so do not pay for building the keyed alternative url on that path.
                $redirectUrl = $request
                    ? $this->_urlPolicy->getRedirectUrl($user, $request, $this->_getRequestUri())
                    : null;
                if ($redirectUrl) {
                    Mage::dispatchEvent('admin_session_user_login_success', ['user' => $user]);
                    $this->_response->clearHeaders()
                        ->setRedirect($redirectUrl)
                        ->sendHeadersAndExit();
                }
            } else {
                Mage::throwException(Mage::helper('adminhtml')->__('Invalid User Name or Password.'));
            }
        } catch (Mage_Core_Exception $e) {
            $e->setMessage(
                Mage::helper('adminhtml')->__('You did not sign in correctly or your account is temporarily disabled.'),
            );
            $this->_loginFailed($e, $request, $username, $e->getMessage());
        } catch (Exception $e) {
            $message = Mage::helper('adminhtml')->__('An error occurred while logging in.');
            $this->_loginFailed($e, $request, $username, $message);
        }

        return $user;
    }

    /**
     * Refresh ACL resources stored in session
     *
     * @param  Mage_Admin_Model_User $user
     * @return $this
     */
    public function refreshAcl($user = null)
    {
        if (is_null($user)) {
            $user = $this->getUser();
        }
        if (!$user) {
            return $this;
        }
        if (!$this->getAcl() || $user->getReloadAclFlag()) {
            $this->setAcl(Mage::getResourceModel('admin/acl')->loadAcl());
        }
        if ($user->getReloadAclFlag()) {
            $user->getResource()->saveReloadAclFlag($user, 0);
        }
        return $this;
    }

    /**
     * Check current user permission on resource and privilege
     *
     * Mage::getSingleton('admin/session')->isAllowed('admin/catalog')
     * Mage::getSingleton('admin/session')->isAllowed('catalog')
     *
     * @param   string $resource
     * @param   string $privilege
     * @return bool
     */
    public function isAllowed($resource, $privilege = null)
    {
        $user = $this->getUser();
        $acl = $this->getAcl();

        if ($user && $acl) {
            if (!preg_match('/^admin/', $resource)) {
                $resource = 'admin/' . $resource;
            }

            try {
                return $acl->isAllowed($user->getAclRole(), $resource, $privilege);
            } catch (Exception) {
                try {
                    if (!$acl->hasResource($resource)) {
                        return $acl->isAllowed($user->getAclRole(), null, $privilege);
                    }
                } catch (Exception) {
                }
            }
        }
        return false;
    }

    /**
     * Check if user is logged in
     *
     * @return bool
     */
    public function isLoggedIn()
    {
        return $this->getUser() && $this->getUser()->getId();
    }

    /**
     * Check if it is the first page after successful login
     *
     * @return bool
     */
    public function isFirstPageAfterLogin()
    {
        if (is_null($this->_isFirstPageAfterLogin)) {
            $this->_isFirstPageAfterLogin = $this->getData('is_first_visit', true);
        }
        return $this->_isFirstPageAfterLogin;
    }

    /**
     * Setter whether the current/next page should be treated as first page after login
     *
     * @param bool $value
     * @return $this
     */
    public function setIsFirstPageAfterLogin($value)
    {
        $this->_isFirstPageAfterLogin = (bool) $value;
        return $this->setIsFirstVisit($this->_isFirstPageAfterLogin);
    }

    /**
     * The requested url rebuilt with a fresh secret key, for the post-login redirect
     */
    protected function _getRequestUri(): string
    {
        return Mage::getSingleton('adminhtml/url')->getUrl('*/*/*', ['_current' => true]);
    }

    /**
     * Login failed process
     *
     * @param Exception $e
     * @param Mage_Core_Controller_Request_Http|null $request
     * @param string $username
     * @param string $message
     */
    protected function _loginFailed($e, $request, #[\SensitiveParameter] $username, $message)
    {
        try {
            Mage::dispatchEvent('admin_session_user_login_failed', [
                'user_name' => $username,
                'exception' => $e,
            ]);
        } catch (Exception) {
        }

        if ($request && !$request->getParam('messageSent')) {
            Mage::getSingleton('adminhtml/session')->addError($message);
            $request->setParam('messageSent', true);
        }
    }
}
