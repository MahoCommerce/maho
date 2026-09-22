<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

/**
 * @method Mage_Api_Model_Resource_User _getResource()
 * @method Mage_Api_Model_Resource_User getResource()
 * @method bool hasApiKey()
 * @method bool hasApiKeyConfirmation()
 * @method bool hasNewApiKey()
 *
 * @deprecated since 26.7 Use Maho_ApiPlatform instead.
 */
class Mage_Api_Model_User extends Mage_Core_Model_Abstract
{
    /**
     * @var string
     */
    #[\Override]
    protected $_eventPrefix = 'api_user';

    #[\Override]
    protected function _construct()
    {
        $this->_init('api/user');
    }

    /**
     * @return $this
     */
    #[\Override]
    public function save()
    {
        $this->_beforeSave();
        $data = [
            'firstname' => $this->getFirstname(),
            'lastname'  => $this->getLastname(),
            'email'     => $this->getEmail(),
            'modified'  => Mage::app()->getLocale()->formatDateForDb('now'),
        ];

        if ($this->getId() > 0) {
            $data['user_id']   = $this->getId();
        }

        if ($this->getUsername()) {
            $data['username']   = $this->getUsername();
        }

        // A loaded user carries the stored hash in api_key, and the edit form leaves the field
        // blank to keep the current key: only encode a value that differs from what was loaded
        if ($this->getApiKey() && $this->getApiKey() !== $this->getOrigData('api_key')) {
            $data['api_key']   = $this->_getEncodedApiKey($this->getApiKey());
        }

        if ($this->getNewApiKey()) {
            $data['api_key']   = $this->_getEncodedApiKey($this->getNewApiKey());
        }

        if (!is_null($this->getIsActive())) {
            $data['is_active']  = (int) $this->getIsActive();
        }

        // setData() below replaces the whole data array; carry the store restriction over
        if ($this->hasData('allowed_store_ids')) {
            $data['allowed_store_ids'] = $this->getData('allowed_store_ids');
        }

        $this->setData($data);
        $this->_getResource()->save($this);
        $this->_afterSave();
        return $this;
    }

    /**
     * Delete user
     *
     * @return $this|Mage_Core_Model_Abstract
     * @throws Mage_Core_Exception
     */
    #[\Override]
    public function delete()
    {
        $this->_beforeDelete();
        $this->_getResource()->delete($this);
        $this->_afterDelete();
        return $this;
    }

    /**
     * Save relations for users
     *
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function saveRelations()
    {
        $this->_getResource()->_saveRelations($this);
        return $this;
    }

    /**
     * Get user roles
     *
     * @return array
     */
    public function getRoles()
    {
        return $this->_getResource()->_getRoles($this);
    }

    /**
     * Delete user from role
     *
     * @return $this
     */
    public function deleteFromRole()
    {
        $this->_getResource()->deleteFromRole($this);
        return $this;
    }

    /**
     * Check is user role exists
     *
     * @return bool
     */
    public function roleUserExists()
    {
        $result = $this->_getResource()->roleUserExists($this);
        return is_array($result) && count($result) > 0;
    }

    /**
     * Add user
     *
     * @return $this
     */
    public function add()
    {
        $this->_getResource()->add($this);
        return $this;
    }

    /**
     * Check if user exists
     *
     * @return bool
     */
    public function userExists()
    {
        $result = $this->_getResource()->userExists($this);
        return is_array($result) && count($result) > 0;
    }

    /**
     * Get collection of users
     *
     * @return Object|Mage_Api_Model_Resource_User_Collection
     */
    #[\Override]
    public function getCollection()
    {
        return Mage::getResourceModel('api/user_collection');
    }

    /**
     * Get user's name
     *
     * @param string $separator
     * @return string
     */
    public function getName($separator = ' ')
    {
        return $this->getFirstname() . $separator . $this->getLastname();
    }

    /**
     * Get user's id
     *
     * @return string
     */
    #[\Override]
    public function getId()
    {
        return $this->getUserId();
    }

    /**
     * Get user ACL role
     *
     * @return string
     */
    public function getAclRole()
    {
        return 'U' . $this->getUserId();
    }

    /**
     * Authenticate user name and api key and save loaded record
     *
     * @param string $username
     * @param string $apiKey
     * @return bool
     * @throws Exception
     */
    public function authenticate(#[\SensitiveParameter] $username, #[\SensitiveParameter] $apiKey)
    {
        $this->loadByUsername($username);
        if (!$this->getId()) {
            return false;
        }
        $auth = Mage::helper('core')->validateHash($apiKey, $this->getApiKey());
        if ($auth) {
            return true;
        }

        $this->unsetData();
        return false;
    }

    /**
     * Login user
     *
     * @param string $username
     * @param string $apiKey
     * @return Mage_Api_Model_User
     * @throws Exception
     */
    public function login(#[\SensitiveParameter] $username, #[\SensitiveParameter] $apiKey)
    {
        $sessId = $this->getSessid();
        if ($this->authenticate($username, $apiKey)) {
            $this->setSessid($sessId);
            $this->getResource()->cleanOldSessions($this)
                ->recordLogin($this)
                ->recordSession($this);
            Mage::dispatchEvent('api_user_authenticated', [
                'model'    => $this,
                'api_key'  => $apiKey,
            ]);
        }

        return $this;
    }

    /**
     * Reload user
     *
     * @return $this
     */
    public function reload()
    {
        $this->load($this->getId());
        return $this;
    }

    public function loadByUsername(#[\SensitiveParameter] string $username): static
    {
        // MySQL strips NUL bytes from a varchar value and SQLite refuses to quote them,
        // so 'user\0' must never resolve to 'user' nor turn into a 500
        if (str_contains($username, "\0")) {
            return $this;
        }
        return $this->load($username, 'username');
    }

    /**
     * Load user by session id
     *
     * @param string $sessId
     * @return $this
     */
    public function loadBySessId($sessId)
    {
        $session = $this->getResource()->loadBySessId($sessId);
        if ($session) {
            $this->load($session['user_id'])->addData($session);
        }
        return $this;
    }

    /**
     * Logout user by session id
     *
     * @param string $sessid
     * @return $this
     */
    public function logoutBySessId($sessid)
    {
        $this->getResource()->clearBySessId($sessid);
        return $this;
    }

    /**
     * Check if user is assigned to role
     *
     * @param int|Mage_Core_Model_Abstract $user
     * @return array
     */
    public function hasAssigned2Role($user)
    {
        return $this->getResource()->hasAssigned2Role($user);
    }

    /**
     * Retrieve encoded api key
     *
     * @param string $apiKey
     * @return string
     */
    protected function _getEncodedApiKey(#[\SensitiveParameter] $apiKey)
    {
        return Mage::helper('core')->getHashPassword($apiKey);
    }

    /**
     * Validate user attribute values.
     *
     * @return array|true
     */
    public function validate()
    {
        $errors = new ArrayObject();

        if (!Mage::helper('core')->isValidNotBlank($this->getUsername())) {
            $errors->append(Mage::helper('api')->__('User Name is required field.'));
        }

        if (!Mage::helper('core')->isValidNotBlank($this->getFirstname())) {
            $errors->append(Mage::helper('api')->__('First Name is required field.'));
        }

        if (!Mage::helper('core')->isValidNotBlank($this->getLastname())) {
            $errors->append(Mage::helper('api')->__('Last Name is required field.'));
        }

        if (!Mage::helper('core')->isValidEmail($this->getEmail())) {
            $errors->append(Mage::helper('api')->__('Please enter a valid email.'));
        }

        if ($this->hasNewApiKey()) {
            $apiKey = $this->getNewApiKey();
        } elseif ($this->hasApiKey()) {
            $apiKey = $this->getApiKey();
        }

        if (isset($apiKey)) {
            $minCustomerPasswordLength = $this->_getMinCustomerPasswordLength();
            if (strlen($apiKey) < $minCustomerPasswordLength) {
                $errors->append(Mage::helper('api')
                    ->__('Api Key must be at least of %d characters.', $minCustomerPasswordLength));
            }

            if (!preg_match('/[a-z]/iu', $apiKey) || !preg_match('/[0-9]/u', $apiKey)) {
                $errors->append(Mage::helper('api')
                    ->__('Api Key must include both numeric and alphabetic characters.'));
            }

            if ($this->hasApiKeyConfirmation() && $apiKey != $this->getApiKeyConfirmation()) {
                $errors->append(Mage::helper('api')->__('Api Key confirmation must be same as Api Key.'));
            }
        }

        if ($this->userExists()) {
            $errors->append(Mage::helper('api')
                ->__('A user with the same user name or email already exists.'));
        }

        if (count($errors) === 0) {
            return true;
        }

        return (array) $errors;
    }

    /**
     * Get min customer password length
     *
     * @return int
     */
    protected function _getMinCustomerPasswordLength()
    {
        return Mage::getSingleton('customer/customer')->getMinPasswordLength();
    }

    public function getApiKey(): ?string
    {
        $value = $this->getData('api_key');
        return $value === null ? null : (string) $value;
    }

    public function setApiKey(?string $value): static
    {
        return $this->setData('api_key', $value);
    }

    public function getApiKeyConfirmation(): ?string
    {
        $value = $this->getData('api_key_confirmation');
        return $value === null ? null : (string) $value;
    }

    public function getCreated(): ?string
    {
        $value = $this->getData('created');
        return $value === null ? null : (string) $value;
    }

    public function setCreated(?string $value): static
    {
        return $this->setData('created', $value);
    }

    public function getEmail(): ?string
    {
        $value = $this->getData('email');
        return $value === null ? null : (string) $value;
    }

    public function setEmail(?string $value): static
    {
        return $this->setData('email', $value);
    }

    public function getFirstname(): ?string
    {
        $value = $this->getData('firstname');
        return $value === null ? null : (string) $value;
    }

    public function setFirstname(?string $value): static
    {
        return $this->setData('firstname', $value);
    }

    public function getIsActive(): ?bool
    {
        $value = $this->getData('is_active');
        return $value === null ? null : (bool) $value;
    }

    public function setIsActive(?bool $value = true): static
    {
        return $this->setData('is_active', $value);
    }

    public function getLastname(): ?string
    {
        $value = $this->getData('lastname');
        return $value === null ? null : (string) $value;
    }

    public function setLastname(?string $value): static
    {
        return $this->setData('lastname', $value);
    }

    public function getLogdate(): ?string
    {
        $value = $this->getData('logdate');
        return $value === null ? null : (string) $value;
    }

    public function setLogdate(?string $value): static
    {
        return $this->setData('logdate', $value);
    }

    public function getLognum(): ?int
    {
        $value = $this->getData('lognum');
        return $value === null ? null : (int) $value;
    }

    public function setLognum(?int $value): static
    {
        return $this->setData('lognum', $value);
    }

    public function getModified(): ?string
    {
        $value = $this->getData('modified');
        return $value === null ? null : (string) $value;
    }

    public function setModified(?string $value): static
    {
        return $this->setData('modified', $value);
    }

    public function getNewApiKey(): ?string
    {
        $value = $this->getData('new_api_key');
        return $value === null ? null : (string) $value;
    }

    public function getReloadAclFlag(): ?bool
    {
        $value = $this->getData('reload_acl_flag');
        return $value === null ? null : (bool) $value;
    }

    public function setReloadAclFlag(?bool $value = true): static
    {
        return $this->setData('reload_acl_flag', $value);
    }

    public function getRoleId(): ?int
    {
        $value = $this->getData('role_id');
        return $value === null ? null : (int) $value;
    }

    public function getRoleIds(): ?array
    {
        return $this->getData('role_ids');
    }

    public function setRoleIds(?array $value): static
    {
        return $this->setData('role_ids', $value);
    }

    public function setRoleUserId(?int $value): static
    {
        return $this->setData('role_user_id', $value);
    }

    public function getSessid(): ?string
    {
        $value = $this->getData('sessid');
        return $value === null ? null : (string) $value;
    }

    public function setSessid(?string $value): static
    {
        return $this->setData('sessid', $value);
    }

    public function getUserId(): ?int
    {
        $value = $this->getData('user_id');
        return $value === null ? null : (int) $value;
    }

    public function getUsername(): ?string
    {
        $value = $this->getData('username');
        return $value === null ? null : (string) $value;
    }

    public function setUsername(?string $value): static
    {
        return $this->setData('username', $value);
    }

}
