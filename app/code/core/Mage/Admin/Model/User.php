<?php

/**
 * Maho
 *
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2018-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Admin_Model_Resource_User _getResource()
 * @method Mage_Admin_Model_Resource_User getResource()
 * @method Mage_Admin_Model_Resource_User_Collection getResourceCollection()
 *
 * @method string getFirstname()
 * @method $this setFirstname(string $value)
 * @method string getLastname()
 * @method $this setLastname(string $value)
 * @method string getEmail()
 * @method $this setEmail(string $value)
 * @method string getUsername()
 * @method $this setUsername(string $value)
 * @method string getPassword()
 * @method $this setPassword(string $value)
 * @method string getCreated()
 * @method $this setCreated(string $value)
 * @method string getModified()
 * @method $this setModified(string $value)
 * @method string getLogdate()
 * @method $this setLogdate(string $value)
 * @method int getLognum()
 * @method $this setLognum(int $value)
 * @method int getReloadAclFlag()
 * @method $this setReloadAclFlag(int $value)
 * @method int getIsActive()
 * @method $this setIsActive(int $value)
 * @method array getExtra()
 * @method $this setExtra(string $value)
 * @method int getUserId()
 * @method int getRoleId()
 * @method bool hasNewPassword()
 * @method string getNewPassword()
 * @method $this setNewPassword(string $value)
 * @method $this unsNewPassword()
 * @method bool hasPassword()
 * @method bool hasPasswordConfirmation()
 * @method string getPasswordConfirmation()
 * @method $this setPasswordConfirmation(string $value)
 * @method $this unsPasswordConfirmation()
 * @method $this setRoleId(int $value)
 * @method array getRoleIds()
 * @method $this setRoleIds(array $value)
 * @method $this setRoleUserId(int $value)
 * @method string getRpToken()
 * @method $this setRpToken(string $value)
 * @method string getRpTokenCreatedAt()
 * @method $this setRpTokenCreatedAt(string $value)
 * @method $this setUserId(int $value)
 * @method int getTwofaEnabled()
 * @method $this setTwofaEnabled(int $value)
 * @method string getPasskeyCredentialIdHash()
 * @method $this setPasskeyCredentialIdHash(string $value)
 * @method string getPasskeyPublicKey()
 * @method $this setPasskeyPublicKey(string $value)
 * @method int getPasswordEnabled()
 * @method $this setPasswordEnabled(int $value)
 */
class Mage_Admin_Model_User extends Mage_Core_Model_Abstract
{
    /**
     * Configuration paths for email templates and identities
     */
    public const XML_PATH_FORGOT_EMAIL_TEMPLATE    = 'admin/emails/forgot_email_template';
    public const XML_PATH_FORGOT_EMAIL_IDENTITY    = 'admin/emails/forgot_email_identity';
    public const XML_PATH_STARTUP_PAGE             = 'admin/startup/page';

    /** Configuration paths for notifications */
    public const XML_PATH_ADDITIONAL_EMAILS             = 'general/additional_notification_emails/admin_user_create';
    public const XML_PATH_NOTIFICATION_EMAILS_TEMPLATE  = 'admin/emails/admin_notification_email_template';

    /**
     * Configuration path for minimum length of admin password
     */
    public const XML_PATH_MIN_ADMIN_PASSWORD_LENGTH = 'admin/security/min_admin_password_length';

    /**
     * Length of salt
     */
    public const HASH_SALT_LENGTH = 32;

    /**
     * Empty hash salt
     */
    public const HASH_SALT_EMPTY = null;

    /**
     * Authentication error codes
     */
    public const AUTH_ERR_ACCOUNT_INACTIVE = 1;
    public const AUTH_ERR_ACCESS_DENIED = 2;
    public const AUTH_ERR_2FA_INVALID = 3;

    /**
     * Model event prefix
     *
     * @var string
     */
    protected $_eventPrefix = 'admin_user';

    /**
     * Admin role
     *
     * @var Mage_Admin_Model_Roles
     */
    protected $_role;

    /**
     * Available resources flag
     *
     * @var bool
     */
    protected $_hasAvailableResources = true;

    /**
     * Initialize user model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('admin/user');
    }

    #[\Override]
    protected function _beforeSave()
    {
        $data = [
            'firstname' => $this->getFirstname(),
            'lastname'  => $this->getLastname(),
            'email'     => $this->getEmail(),
            'modified'  => Mage_Core_Model_Locale::now(),
            'extra'     => Mage::helper('core')->jsonEncode($this->getExtra()),
        ];

        if ($this->getId() > 0) {
            $data['user_id'] = $this->getId();
        }

        if ($this->getUsername()) {
            $data['username'] = $this->getUsername();
        }

        if ($this->getNewPassword()) {
            // Change user password
            $data['password'] = $this->_getEncodedPassword($this->getNewPassword());
            $data['new_password'] = $data['password'];
            $sessionUser = $this->getSession()->getUser();
            if ($sessionUser && $sessionUser->getId() == $this->getId()) {
                $this->getSession()->setUserPasswordChanged(true);
            }
        } elseif ($this->getPassword() && $this->getPassword() != $this->getOrigData('password')) {
            // New user password
            $data['password'] = $this->_getEncodedPassword($this->getPassword());
        } elseif (!$this->getPassword() && $this->getOrigData('password') // Change user data
            || $this->getPassword() == $this->getOrigData('password')     // Retrieve user password
        ) {
            $data['password'] = $this->getOrigData('password');
        }

        $this->cleanPasswordsValidationData();

        if (!is_null($this->getIsActive())) {
            $data['is_active'] = (int) $this->getIsActive();
        }

        $this->addData($data);

        return parent::_beforeSave();
    }

    /**
     * @return Mage_Admin_Model_Session
     */
    protected function getSession()
    {
        return  Mage::getSingleton('admin/session');
    }

    #[\Override]
    public function save()
    {
        if (!$this->getPasswordEnabled() && !($this->getPasskeyPublicKey() || $this->getPasskeyCredentialIdHash())) {
            // Forcing password-enabled if there's no passkey
            $this->setPasswordEnabled(1);
        }
        return parent::save();
    }

    /**
     * Save admin user extra data (like configuration sections state)
     *
     * @param   array|string $data
     * @return  $this
     */
    public function saveExtra($data)
    {
        if (is_array($data)) {
            $data = Mage::helper('core')->jsonEncode($data);
        }
        $this->_getResource()->saveExtra($this, $data);
        return $this;
    }

    /**
     * Save user roles
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
     * Retrieve user roles
     *
     * @return array
     */
    public function getRoles()
    {
        return $this->_getResource()->getRoles($this);
    }

    /**
     * Get admin role model
     *
     * @return Mage_Admin_Model_Roles
     */
    public function getRole()
    {
        if ($this->_role === null) {
            $this->_role = Mage::getModel('admin/roles');
            $roles = $this->getRoles();
            if ($roles && isset($roles[0]) && $roles[0]) {
                $this->_role->load($roles[0]);
            }
        }
        return $this->_role;
    }

    /**
     * Unassign user from his current role
     *
     * @return $this
     */
    public function deleteFromRole()
    {
        $this->_getResource()->deleteFromRole($this);
        return $this;
    }

    /**
     * Check if such combination role/user exists
     *
     * @return bool
     */
    public function roleUserExists()
    {
        $result = $this->_getResource()->roleUserExists($this);
        return is_array($result) && count($result) > 0;
    }

    /**
     * Assign user to role
     *
     * @return $this
     */
    public function add()
    {
        $this->_getResource()->add($this);
        return $this;
    }

    /**
     * Check if user exists based on its id, username and email
     *
     * @return bool
     */
    public function userExists()
    {
        $result = $this->_getResource()->userExists($this);
        return is_array($result) && count($result) > 0;
    }

    /**
     * Retrieve admin user collection
     *
     * @return Mage_Admin_Model_Resource_User_Collection
     */
    #[\Override]
    public function getCollection()
    {
        return Mage::getResourceModel('admin/user_collection');
    }

    /**
     * Send email with reset password confirmation link
     *
     * @return $this
     */
    public function sendPasswordResetConfirmationEmail()
    {
        /** @var Mage_Core_Model_Email_Template_Mailer $mailer */
        $mailer = Mage::getModel('core/email_template_mailer');
        $emailInfo = Mage::getModel('core/email_info');
        $emailInfo->addTo($this->getEmail(), $this->getName());
        $mailer->addEmailInfo($emailInfo);

        // Set all required params and send emails
        $mailer->setSender(Mage::getStoreConfig(self::XML_PATH_FORGOT_EMAIL_IDENTITY));
        $mailer->setStoreId(0);
        $mailer->setTemplateId(Mage::getStoreConfig(self::XML_PATH_FORGOT_EMAIL_TEMPLATE));
        $mailer->setTemplateParams([
            'user' => $this,
        ]);
        $mailer->send();

        return $this;
    }

    /**
     * Retrieve user name
     *
     * @param string $separator
     * @return string
     */
    public function getName($separator = ' ')
    {
        return $this->getFirstname() . $separator . $this->getLastname();
    }

    /**
     * Retrieve user identifier
     *
     * @return mixed
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
     * Authenticate username and password and save loaded record
     * @throws Mage_Core_Exception
     */
    public function authenticate(#[\SensitiveParameter] string $username, #[\SensitiveParameter] string $password, #[\SensitiveParameter] ?string $twofaVerificationCode = null): bool
    {
        try {
            Mage::dispatchEvent('admin_user_authenticate_before', [
                'username' => $username,
                'user'     => $this,
            ]);
            $this->loadByUsername($username);

            if (!$this->getId()) {
                throw new Mage_Core_Exception(
                    Mage::helper('adminhtml')->__('Access denied.'),
                    self::AUTH_ERR_ACCESS_DENIED,
                );
            }

            $useCaseSensitiveLogin = Mage::getStoreConfigFlag('admin/security/use_case_sensitive_login');
            if ($useCaseSensitiveLogin && $this->getUsername() !== $username) {
                throw new Mage_Core_Exception(
                    Mage::helper('adminhtml')->__('Access denied.'),
                    self::AUTH_ERR_ACCESS_DENIED,
                );
            }

            $usedPasskey = false;
            $needsTwofa = true;

            $passkeyEnabled = $this->isPasskeyEnabled();
            if ($passkeyEnabled && json_validate($password)) {
                $passkeyData = json_decode($password);
                $clientDataJSON = base64_decode($passkeyData->clientDataJSON ?? '');
                $authenticatorData = base64_decode($passkeyData->authenticatorData ?? '');
                $signature = base64_decode($passkeyData->signature ?? '');
                $userHandle = base64_decode($passkeyData->userHandle ?? '');
                $id = base64_decode($passkeyData->id ?? '');

                $publicKey = $this->getPasskeyPublicKey();
                $challenge = $this->getSession()->getPasskeyChallenge();
                $this->getSession()->unsPasskeyChallange();

                $webAuthn = Mage::helper('admin/auth')->getWebAuthn();
                $usedPasskey = $webAuthn->processGet($clientDataJSON, $authenticatorData, $signature, $publicKey, $challenge);

                $authenticatorObj = Mage::helper('admin/auth')->getWebAuthnAttestationAuthenticatorData($authenticatorData);
                $needsTwofa = !$authenticatorObj->getUserVerified();
            }

            if (!$usedPasskey) {
                if (!$this->getPasswordEnabled()) {
                    throw new Mage_Core_Exception(
                        Mage::helper('adminhtml')->__('Access denied.'),
                        self::AUTH_ERR_ACCESS_DENIED,
                    );
                }

                if (!$this->validatePasswordHash($password, $this->getPassword())) {
                    throw new Mage_Core_Exception(
                        Mage::helper('adminhtml')->__('Access denied.'),
                        self::AUTH_ERR_ACCESS_DENIED,
                    );
                }

                // Upgrade hash version
                if (!$this->getPasswordUpgraded() && !$this->validatePasswordHashSha256($password, $this->getPassword())) {
                    $this->setNewPassword($password)
                        ->setForceNewPassword(true)
                        ->setPasswordUpgraded(true)
                        ->save();
                }
            }

            if ($needsTwofa && $this->getTwofaEnabled()) {
                $secret = $this->getTwofaSecret();
                if (!Mage::helper('admin/auth')->verifyTwofaCode($secret, $twofaVerificationCode ?? '')) {
                    throw new Mage_Core_Exception(
                        Mage::helper('adminhtml')->__('2FA verification code is invalid.'),
                        self::AUTH_ERR_2FA_INVALID,
                    );
                }
            }

            if ($this->getIsActive() != '1') {
                throw new Mage_Core_Exception(
                    Mage::helper('adminhtml')->__('This account is inactive.'),
                    self::AUTH_ERR_ACCOUNT_INACTIVE,
                );
            }

            if (!$this->hasAssigned2Role($this->getId())) {
                throw new Mage_Core_Exception(
                    Mage::helper('adminhtml')->__('Access denied.'),
                    self::AUTH_ERR_ACCESS_DENIED,
                );
            }

            Mage::dispatchEvent('admin_user_authenticate_after', [
                'username' => $username,
                'user'     => $this,
                'result'   => true,
            ]);

            return true;

        } catch (Mage_Core_Exception $e) {
            Mage::dispatchEvent('admin_user_authenticate_failed', [
                'username' => $username,
                'user'     => $this,
                'error'    => $e,
            ]);
            $this->unsetData();
            throw $e;
        } catch (Exception $e) {
            $this->unsetData();
            throw $e;
        }
    }

    /**
     * Get Passkey CreateArgs object
     * @throws Mage_Core_Exception
     */
    public function getPasskeyCreateArgs(): stdClass
    {
        $webAuthn = Mage::helper('admin/auth')->getWebAuthn();
        $createArgs = $webAuthn->getCreateArgs(
            decbin($this->getId()), // User ID
            $this->getUsername(),   // User Name
            $this->getName(),       // Display Name
            60000,                  // Timeout
        );
        $this->getSession()->setPasskeyChallenge($webAuthn->getChallenge());

        return $createArgs;
    }

    /**
     * Get Passkey GetArgs object
     * @throws Mage_Core_Exception
     */
    public function getPasskeyGetArgs(): stdClass
    {
        if (!$this->getPasskeyCredentialIdHash() || !$this->getPasskeyPublicKey()) {
            Mage::throwException(Mage::helper('adminhtml')->__('You did not sign in correctly or your account is temporarily disabled.'));
        }

        $webAuthn = Mage::helper('admin/auth')->getWebAuthn();
        $getArgs = $webAuthn->getGetArgs([ base64_decode($this->getPasskeyCredentialIdHash()) ]);
        $this->getSession()->setPasskeyChallenge($webAuthn->getChallenge());

        return $getArgs;
    }

    /**
     * Validate and set new passkey data
     * @throws Mage_Core_Exception
     */
    public function setPasskeyData($data)
    {
        $challenge = $this->getSession()->getPasskeyChallenge();
        $attestationObject = base64_decode($data['attestationObject']);
        $clientDataJSON = base64_decode($data['clientDataJSON']);

        if (!$challenge || !$attestationObject || !$clientDataJSON) {
            Mage::throwException(Mage::helper('adminhtml')->__('Missing required fields'));
        }

        $webAuthn = Mage::helper('admin/auth')->getWebAuthn();
        $result = $webAuthn->processCreate(
            $clientDataJSON,
            $attestationObject,
            $challenge,
        );

        $credentialId = base64_encode($result->credentialId);
        $publicKey = $result->credentialPublicKey;

        // Check if another user already has this credential
        $existingUser = Mage::getModel('admin/user')->getCollection()
            ->addFieldToFilter('passkey_credential_id_hash', $credentialId)
            ->getFirstItem();
        if ($existingUser->getId() && $existingUser->getId() != $this->getId()) {
            Mage::throwException(Mage::helper('adminhtml')->__('Passkey credential already registered to another user.'));
        }

        // Store the credential and public key, pending save
        $this->setPasskeyCredentialIdHash($credentialId)
            ->setPasskeyPublicKey($publicKey);
        if ($this->getPasswordEnabled() === null) {
            $this->setPasswordEnabled(0);
        }
    }

    public function isPasskeyEnabled(): bool
    {
        return $this->getPasskeyCredentialIdHash() && $this->getPasskeyPublicKey();
    }

    public function validatePasswordHash(#[\SensitiveParameter] string $string1, string $string2): bool
    {
        return Mage::helper('core')->validateHash($string1, $string2);
    }

    public function validatePasswordHashSha256(#[\SensitiveParameter] string $string1, string $string2): bool
    {
        return Mage::helper('core')->getEncryptor()->validateHashByVersion($string1, $string2, Mage_Core_Model_Encryption::HASH_VERSION_SHA256);
    }

    /**
     * @throws Mage_Core_Exception
     */
    public function login(#[\SensitiveParameter] string $username, #[\SensitiveParameter] string $password, #[\SensitiveParameter] ?string $twofaVerificationCode = null): self
    {
        if ($this->authenticate($username, $password, $twofaVerificationCode)) {
            $this->getResource()->recordLogin($this);
            Mage::getSingleton('core/session')->renewFormKey();
        }
        return $this;
    }

    /**
     * Reload current user
     *
     * @return $this
     */
    public function reload()
    {
        $id = $this->getId();
        $oldPassword = $this->getPassword();
        $this->setId(null);
        $this->load($id);
        $isUserPasswordChanged = $this->getSession()->getUserPasswordChanged();
        if (!$isUserPasswordChanged && $this->getPassword() !== $oldPassword) {
            $this->setId(null);
        } elseif ($isUserPasswordChanged) {
            $this->getSession()->setUserPasswordChanged(false);
        }
        return $this;
    }

    /**
     * Load user by its username
     *
     * @param string $username
     * @return $this
     */
    public function loadByUsername(#[\SensitiveParameter] $username)
    {
        $this->setData($this->getResource()->loadByUsername($username));
        return $this;
    }

    /**
     * Check if user is assigned to any role
     *
     * @param int|Mage_Admin_Model_User $user
     * @return array|null
     */
    public function hasAssigned2Role($user)
    {
        return $this->getResource()->hasAssigned2Role($user);
    }

    /**
     * Retrieve encoded password
     *
     * @param string $password
     * @return string
     */
    protected function _getEncodedPassword(#[\SensitiveParameter] $password)
    {
        return Mage::helper('core')->getHash($password, self::HASH_SALT_LENGTH);
    }

    /**
     * Find first menu item that user is able to access
     *
     * @param Mage_Core_Model_Config_Element|\Maho\Simplexml\Element $parent
     * @param string $path
     * @param int $level
     * @return string
     */
    public function findFirstAvailableMenu($parent = null, $path = '', $level = 0)
    {
        if ($parent == null) {
            $parent = Mage::getSingleton('admin/config')->getAdminhtmlConfig()->getNode('menu');
        }
        foreach ($parent->children() as $childName => $child) {
            $aclResource = 'admin/' . $path . $childName;
            if (Mage::getSingleton('admin/session')->isAllowed($aclResource)) {
                if (!$child->children) {
                    return (string) $child->action;
                }
                $action = $this->findFirstAvailableMenu($child->children, $path . $childName . '/', $level + 1);
                return $action ?: (string) $child->action;
            }
        }
        $this->_hasAvailableResources = false;
        return '*/*/denied';
    }

    /**
     * Check if user has available resources
     *
     * @return bool
     */
    public function hasAvailableResources()
    {
        return $this->_hasAvailableResources;
    }

    /**
     * Find admin start page url
     *
     * @return string
     */
    public function getStartupPageUrl()
    {
        $startupPage = Mage::getStoreConfig(self::XML_PATH_STARTUP_PAGE);
        $aclResource = 'admin/' . $startupPage;
        if (Mage::getSingleton('admin/session')->isAllowed($aclResource)) {
            $nodePath = 'menu/' . implode('/children/', explode('/', $startupPage)) . '/action';
            $url = (string) Mage::getSingleton('admin/config')->getAdminhtmlConfig()->getNode($nodePath);
            if ($url) {
                return $url;
            }
        }
        return $this->findFirstAvailableMenu();
    }

    /**
     * Validate user attribute values.
     * Returns TRUE or array of errors.
     *
     * @return array|true
     */
    public function validate()
    {
        $errors = new ArrayObject();

        if (!Mage::helper('core')->isValidNotBlank($this->getUsername())) {
            $errors->append(Mage::helper('adminhtml')->__('User Name is required field.'));
        }

        if (!Mage::helper('core')->isValidNotBlank($this->getFirstname())) {
            $errors->append(Mage::helper('adminhtml')->__('First Name is required field.'));
        }

        if (!Mage::helper('core')->isValidNotBlank($this->getLastname())) {
            $errors->append(Mage::helper('adminhtml')->__('Last Name is required field.'));
        }

        if (!Mage::helper('core')->isValidEmail($this->getEmail())) {
            $errors->append(Mage::helper('adminhtml')->__('Please enter a valid email.'));
        }

        if ($this->hasNewPassword()) {
            $password = $this->getNewPassword();
        } elseif ($this->hasPassword()) {
            $password = $this->getPassword();
        }
        if (isset($password)) {
            $minAdminPasswordLength = $this->getMinAdminPasswordLength();
            if (Mage::helper('core/string')->strlen($password) < $minAdminPasswordLength) {
                $errors->append(Mage::helper('adminhtml')
                    ->__('Password must be at least of %d characters.', $minAdminPasswordLength));
            }

            if (!preg_match('/[a-z]/iu', $password) || !preg_match('/[0-9]/u', $password)) {
                $errors->append(Mage::helper('adminhtml')
                    ->__('Password must include both numeric and alphabetic characters.'));
            }

            if ($this->hasPasswordConfirmation() && $password != $this->getPasswordConfirmation()) {
                $errors->append(Mage::helper('adminhtml')->__('Password confirmation must be same as password.'));
            }

            Mage::dispatchEvent('admin_user_validate', [
                'user' => $this,
                'errors' => $errors,
            ]);
        }

        if ($this->userExists()) {
            $errors->append(Mage::helper('adminhtml')->__('A user with the same user name or email already exists.'));
        }

        if (count($errors) === 0) {
            return true;
        }

        return (array) $errors;
    }

    /**
     * Validate password against current user password
     * Returns true or array of errors.
     *
     * @param string $password
     * @return array|true
     */
    public function validateCurrentPassword(#[\SensitiveParameter] $password)
    {
        $result = [];

        if (!Mage::helper('core')->isValidNotBlank($password)) {
            $result[] = Mage::helper('adminhtml')->__('Current password field cannot be empty.');
        } elseif (is_null($this->getId()) || !Mage::helper('core')->validateHash($password, $this->getPassword())) {
            $result[] = Mage::helper('adminhtml')->__('Invalid current password.');
        }

        if (empty($result)) {
            $result = true;
        }
        return $result;
    }

    /**
     * Change reset password link token
     *
     * Stores new reset password link token and its creation time
     *
     * @param string $newResetPasswordLinkToken
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function changeResetPasswordLinkToken($newResetPasswordLinkToken)
    {
        if (!is_string($newResetPasswordLinkToken) || empty($newResetPasswordLinkToken)) {
            throw Mage::exception('Mage_Core', Mage::helper('adminhtml')->__('Invalid password reset token.'));
        }
        $this->setRpToken($newResetPasswordLinkToken);
        $currentDate = Mage_Core_Model_Locale::now();
        $this->setRpTokenCreatedAt($currentDate);

        return $this;
    }

    /**
     * Check if current reset password link token is expired
     *
     * @return bool
     */
    public function isResetPasswordLinkTokenExpired()
    {
        $resetPasswordLinkToken = $this->getRpToken();
        $resetPasswordLinkTokenCreatedAt = $this->getRpTokenCreatedAt();

        if (empty($resetPasswordLinkToken) || empty($resetPasswordLinkTokenCreatedAt)) {
            return true;
        }

        $tokenExpirationPeriod = Mage::helper('admin')->getResetPasswordLinkExpirationPeriod();

        $currentDate = Mage_Core_Model_Locale::now();
        $currentTimestamp = strtotime($currentDate);
        $tokenTimestamp = strtotime($resetPasswordLinkTokenCreatedAt);
        if ($tokenTimestamp > $currentTimestamp) {
            return true;
        }

        $hoursDifference = floor(($currentTimestamp - $tokenTimestamp) / (60 * 60));
        if ($hoursDifference >= $tokenExpirationPeriod) {
            return true;
        }

        return false;
    }

    /**
     * Clean password's validation data (password, current_password, new_password, password_confirmation)
     *
     * @return $this
     */
    public function cleanPasswordsValidationData()
    {
        $this->setData('password');
        $this->setData('current_password');
        $this->setData('new_password');
        $this->setData('password_confirmation');
        return $this;
    }

    /**
     * Send notification to general Contact and additional emails when new admin user created.
     * You can declare additional emails in Mage_Core general/additional_notification_emails/admin_user_create node.
     *
     * @param Mage_Admin_Model_User $user
     * @return $this
     */
    public function sendAdminNotification($user)
    {
        // define general contact Name and Email
        $generalContactName = Mage::getStoreConfig('trans_email/ident_general/name');
        $generalContactEmail = Mage::getStoreConfig('trans_email/ident_general/email');

        // collect general and additional emails
        $emails = $this->getUserCreateAdditionalEmail();
        $emails[] = $generalContactEmail;

        /** @var Mage_Core_Model_Email_Template_Mailer $mailer */
        $mailer    = Mage::getModel('core/email_template_mailer');
        $emailInfo = Mage::getModel('core/email_info');
        $emailInfo->addTo(array_filter($emails), $generalContactName);
        $mailer->addEmailInfo($emailInfo);

        // Set all required params and send emails
        $mailer->setSender([
            'name'  => $generalContactName,
            'email' => $generalContactEmail,
        ]);
        $mailer->setStoreId(0);
        $mailer->setTemplateId(Mage::getStoreConfig(self::XML_PATH_NOTIFICATION_EMAILS_TEMPLATE));
        $mailer->setTemplateParams([
            'user' => $user,
        ]);
        $mailer->send();

        return $this;
    }

    /**
     * Get additional emails for notification from config.
     *
     * @return array
     */
    public function getUserCreateAdditionalEmail()
    {
        $emails = str_replace(' ', '', Mage::getStoreConfig(self::XML_PATH_ADDITIONAL_EMAILS));
        return explode(',', $emails);
    }

    /**
     * Retrieve minimum length of admin password
     *
     * @return int
     */
    public function getMinAdminPasswordLength()
    {
        $minLength = Mage::getStoreConfigAsInt(self::XML_PATH_MIN_ADMIN_PASSWORD_LENGTH);
        $absoluteMinLength = Mage_Core_Model_App::ABSOLUTE_MIN_PASSWORD_LENGTH;
        return ($minLength < $absoluteMinLength) ? $absoluteMinLength : $minLength;
    }

    /**
     * Retrieve unencrypted value of twofa_secret
     */
    public function getTwofaSecret(): ?string
    {
        if ($this->hasData('twofa_secret')) {
            return Mage::helper('core')->getEncryptor()->decrypt($this->_getData('twofa_secret'));
        }
        return null;
    }

    /**
     * Store encrypted value of twofa_secret
     */
    public function setTwofaSecret(#[\SensitiveParameter] ?string $secret): self
    {
        if ($secret === null) {
            $this->setData('twofa_secret');
        } else {
            $encrypted = Mage::helper('core')->getEncryptor()->encrypt($secret);
            $this->setData('twofa_secret', $encrypted);
        }
        return $this;
    }
}
