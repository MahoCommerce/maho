<?php

/**
 * Maho
 *
 * @package    Mage_Api
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Api_Model_User getUser()
 * @method $this setUser(Mage_Api_Model_User $user)
 * @method Mage_Api_Model_Acl getAcl()
 * @method $this setAcl(Mage_Api_Model_Acl $loadAcl)
 */
class Mage_Api_Model_Session extends Mage_Core_Model_Session_Abstract
{
    public $sessionIds = [];
    protected $_currentSessId = null;

    #[\Override]
    public function start(?string $sessionName = null): self
    {
        $this->_currentSessId = md5(time() . uniqid('', true) . $sessionName);
        $this->sessionIds[] = $this->getSessionId();
        return $this;
    }

    #[\Override]
    public function init(string $namespace, ?string $sessionName = null): self
    {
        if (is_null($this->_currentSessId)) {
            $this->start();
        }
        return $this;
    }

    #[\Override]
    public function getSessionId(): string
    {
        return $this->_currentSessId;
    }

    #[\Override]
    public function setSessionId(?string $sessId = null): self
    {
        if (!is_null($sessId)) {
            $this->_currentSessId = $sessId;
        }
        return $this;
    }

    #[\Override]
    public function clear(): self
    {
        if ($sessId = $this->getSessionId()) {
            try {
                Mage::getModel('api/user')->logoutBySessId($sessId);
            } catch (Exception $e) {
                // Log error but still return $this for chaining
                Mage::logException($e);
            }
        }
        return $this;
    }

    /**
     * Flag login as HTTP Basic Auth.
     *
     * @return $this
     */
    public function setIsInstaLogin(bool $isInstaLogin = true)
    {
        $this->setData('is_insta_login', $isInstaLogin);
        return $this;
    }

    /**
     * Is insta-login?
     */
    public function getIsInstaLogin(): bool
    {
        return (bool) $this->getData('is_insta_login');
    }

    /**
     * @param string $username
     * @param string $apiKey
     * @return mixed
     * @throws Mage_Core_Exception
     */
    public function login(#[\SensitiveParameter] $username, #[\SensitiveParameter] $apiKey)
    {
        $user = Mage::getModel('api/user')
            ->setSessid($this->getSessionId());
        if ($this->getIsInstaLogin() && $user->authenticate($username, $apiKey)) {
            Mage::dispatchEvent('api_user_authenticated', [
                'model'    => $user,
                'api_key'  => $apiKey,
            ]);
        } else {
            $user->login($username, $apiKey);
        }

        if ($user->getId() && $user->getIsActive() != '1') {
            Mage::throwException(Mage::helper('api')->__('Your account has been deactivated.'));
        } elseif (!Mage::getModel('api/user')->hasAssigned2Role($user->getId())) {
            Mage::throwException(Mage::helper('api')->__('Access denied.'));
        } else {
            if ($user->getId()) {
                $this->setUser($user);
                $this->setAcl(Mage::getResourceModel('api/acl')->loadAcl());
            } else {
                Mage::throwException(Mage::helper('api')->__('Unable to login.'));
            }
        }

        return $user;
    }

    /**
     * @param Mage_Api_Model_User|null $user
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
            $this->setAcl(Mage::getResourceModel('api/acl')->loadAcl());
        }
        if ($user->getReloadAclFlag()) {
            $user->unsetData('api_key');
            $user->setReloadAclFlag('0')->save();
        }
        return $this;
    }

    /**
     * Check current user permission on resource and privilege
     *
     * @param   string $resource
     * @param   string $privilege
     * @return  bool
     */
    public function isAllowed($resource, $privilege = null)
    {
        $user = $this->getUser();
        $acl = $this->getAcl();

        if ($user && $acl) {
            try {
                if ($acl->isAllowed($user->getAclRole(), 'all')) {
                    return true;
                }
            } catch (Exception $e) {
                Mage::logException($e);
            }

            try {
                return $acl->isAllowed($user->getAclRole(), $resource, $privilege);
            } catch (Exception $e) {
                return false;
            }
        }
        return false;
    }

    /**
     *  Check session expiration
     *
     * @param Mage_Api_Model_User $user
     * @return bool
     */
    public function isSessionExpired($user)
    {
        if (!$user->getId()) {
            return true;
        }
        $timeout = strtotime(Mage_Core_Model_Locale::now()) - strtotime($user->getLogdate());
        return $timeout > Mage::getStoreConfig('api/config/session_timeout');
    }

    /**
     * @param string|false $sessId
     * @return bool
     * @throws Mage_Core_Exception
     */
    public function isLoggedIn($sessId = false)
    {
        $userExists = $this->getUser() && $this->getUser()->getId();

        if (!$userExists && $sessId !== false) {
            return $this->_renewBySessId($sessId);
        }

        if ($userExists) {
            Mage::register('isSecureArea', true, true);
        }
        return $userExists;
    }

    /**
     *  Renew user by session ID if session not expired
     *
     *  @param string $sessId
     *  @return bool
     */
    protected function _renewBySessId($sessId)
    {
        $user = Mage::getModel('api/user')->loadBySessId($sessId);
        if (!$user->getId() || !$user->getSessid()) {
            return false;
        }

        if ($user->getSessid() == $sessId && !$this->isSessionExpired($user)) {
            $this->setUser($user);
            $this->setAcl(Mage::getResourceModel('api/acl')->loadAcl());

            $user->getResource()->recordLogin($user)
                ->recordSession($user);

            return true;
        }
        return false;
    }
}
