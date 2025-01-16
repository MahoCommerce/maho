<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Adminhtml account controller
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_System_AccountController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/myaccount';

    protected $_publicActions = [
        'passkeyregisterstart',
        'passkeyregistersave',
    ];

    public function indexAction()
    {
        $this->_title($this->__('System'))->_title($this->__('My Account'));

        $this->loadLayout();
        $this->_setActiveMenu('system/myaccount');
        $this->_addContent($this->getLayout()->createBlock('adminhtml/system_account_edit'));
        $this->renderLayout();
    }

    /**
     * Saving edited user information
     */
    public function saveAction()
    {
        $userId = Mage::getSingleton('admin/session')->getUser()->getId();
        $user = Mage::getModel('admin/user')->load($userId);

        $user->setId($userId)
            ->setUsername($this->getRequest()->getParam('username', false))
            ->setFirstname($this->getRequest()->getParam('firstname', false))
            ->setLastname($this->getRequest()->getParam('lastname', false))
            ->setEmail(strtolower($this->getRequest()->getParam('email', false)));
        if ($this->getRequest()->getParam('new_password', false)) {
            $user->setNewPassword($this->getRequest()->getParam('new_password', false));
        }

        if ($this->getRequest()->getParam('password_confirmation', false)) {
            $user->setPasswordConfirmation($this->getRequest()->getParam('password_confirmation', false));
        }

        $backendLocale = $this->getRequest()->getParam('backend_locale', false);
        $backendLocale = $backendLocale == 0 ? null : $backendLocale;
        $user->setBackendLocale($backendLocale);

        //Validate current admin password
        $currentPassword = $this->getRequest()->getParam('current_password', null);
        $this->getRequest()->setParam('current_password', null);
        $result = $this->_validateCurrentPassword($currentPassword);

        if (!is_array($result)) {
            $result = $user->validate();
        }
        if (is_array($result)) {
            foreach ($result as $error) {
                Mage::getSingleton('adminhtml/session')->addError($error);
            }
            $this->getResponse()->setRedirect($this->getUrl('*/*/'));
            return;
        }

        $twoFactorEnabled = (bool) $this->getRequest()->getParam('twofa_enabled', 0);
        $twoFactorVerificationCode = (string) $this->getRequest()->getParam('twofa_verification_code', '');
        if ($twoFactorEnabled && $twoFactorVerificationCode) {
            $twoFactorAuthenticationHelper = Mage::helper('adminhtml/twoFactorAuthentication');
            if ($twoFactorAuthenticationHelper->verifyCode($user->getTwofaSecret(), $twoFactorVerificationCode)) {
                $user->setTwofaEnabled(1);
            } else {
                $user->setTwofaEnabled(0);
                $user->setTwofaSecret(null);
                Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('Invalid 2FA verification code'));
            }
        } else {
            $user->setTwofaEnabled(0);
            $user->setTwofaSecret(null);
        }

        try {
            $user->save();
            Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('The account has been saved.'));
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('An error occurred while saving account.'));
        }
        $this->getResponse()->setRedirect($this->getUrl('*/*/'));
    }

    public function passkeyregisterstartAction()
    {
        try {
            $user = Mage::getSingleton('admin/session')->getUser();
            if (!$user) {
                throw new Exception('Not authenticated');
            }

            $challenge = base64_encode(random_bytes(32));
            $this->_getSession()->setPasskeyChallenge($challenge);

            $options = [
                'challenge' => $challenge,
                'rp' => [
                    'name' => Mage::getStoreConfig('web/secure/name'),
                    'id' => parse_url(Mage::getBaseUrl(), PHP_URL_HOST),
                ],
                'user' => [
                    'id' => base64_encode($user->getId()),
                    'name' => $user->getUsername(),
                    'displayName' => $user->getName(),
                ],
                'pubKeyCredParams' => [
                    ['type' => 'public-key', 'alg' => -7], // ES256
                ],
                'timeout' => 60000,
                'attestation' => 'none',
                'authenticatorSelection' => [
                    'userVerification' => 'required',
                ],
            ];

            $this->getResponse()->setBodyJson($options);
        } catch (Exception $e) {
            $this->_getSession()->addError($e->getMessage());
            $this->getResponse()
                ->setHttpResponseCode(400)
                ->setBodyJson(['error' => $e->getMessage()]);
        }
    }

    public function passkeyregistersaveAction()
    {
        try {
            if (!$this->getRequest()->isPost()) {
                throw new Exception('Invalid request method');
            }

            $user = Mage::getSingleton('admin/session')->getUser();
            if (!$user) {
                throw new Exception('Not authenticated');
            }

            // Get POST parameters
            $credentialId = $this->getRequest()->getPost('passkey_credential_id');
            $publicKey = $this->getRequest()->getPost('passkey_credential_public_key');
            $attestationObject = $this->getRequest()->getPost('attestation_object');
            $clientDataJSON = $this->getRequest()->getPost('client_data_json');

            // Verify required fields
            if (!$credentialId || !$publicKey || !$attestationObject || !$clientDataJSON) {
                throw new Exception('Missing required fields');
            }

            // Decode the client data JSON
            $clientData = json_decode(base64_decode($clientDataJSON), true);

            // Verify challenge
            $expectedChallenge = $this->_getSession()->getPasskeyChallenge() ?? '';
            $expectedChallenge = rtrim($expectedChallenge, '=');
            $clientData['challenge'] = rtrim($clientData['challenge'], '=');

            if (!$expectedChallenge || $clientData['challenge'] !== $expectedChallenge) {
                throw new Exception('Invalid challenge');
            }

            // Verify origin
            $expectedOrigin = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB);
            if ($clientData['origin'] !== rtrim($expectedOrigin, '/')) {
                throw new Exception('Invalid origin');
            }

            // Check if another user already has this credential
            $existingUser = Mage::getModel('admin/user')->getCollection()
                ->addFieldToFilter('passkey_credential_id_hash', $credentialId)
                ->getFirstItem();

            if ($existingUser->getId() && $existingUser->getId() != $user->getId()) {
                throw new Exception('Credential already registered to another user');
            }

            try {
                // Update the user with the new credential data
                $user->setPasskeyCredentialIdHash($credentialId)
                    ->setPasskeyPublicKey($publicKey)
                    ->save();

                // Clear the challenge from session
                $this->_getSession()->unsPasskeyChallenge();

                $this->getResponse()->setBodyJson([
                    'success' => true,
                    'message' => Mage::helper('adminhtml')->__('Passkey registered successfully'),
                ]);
            } catch (Exception $e) {
                throw new Exception('Failed to save credential: ' . $e->getMessage());
            }
        } catch (Exception $e) {
            $this->getResponse()
                ->setHttpResponseCode(400)
                ->setBodyJson([
                    'success' => false,
                    'error' => $e->getMessage(),
                ]);
        }
    }

    public function passkeyauthenticateAction()
    {
        try {
            $username = $this->getRequest()->getPost('username');
            $user = Mage::getModel('admin/user')->loadByUsername($username);

            if (!$user->getId()) {
                throw new Exception('User not found');
            }

            $credentials = $user->getResource()->getReadConnection()->fetchAll(
                "SELECT credential_id_hash FROM {$user->getResource()->getTable('admin/user_credentials')} WHERE user_id = ?",
                [$user->getId()],
            );

            $allowCredentials = array_map(function ($cred) {
                return [
                    'type' => 'public-key',
                    'id' => $cred['credential_id_hash'],
                ];
            }, $credentials);

            $options = [
                'rpId' => parse_url(Mage::getBaseUrl(), PHP_URL_HOST),
                'timeout' => 60000,
                'allowCredentials' => $allowCredentials,
                'userVerification' => 'required',
            ];

            $this->getResponse()
                ->setBodyJson($options);
            ;
        } catch (Exception $e) {
            $this->getResponse()
                ->setHttpResponseCode(400)
                ->setBody(json_encode(['error' => $e->getMessage()]));
        }
    }

    public function removepasskeyAction()
    {
        $userId = Mage::getSingleton('admin/session')->getUser()->getId();
        $user = Mage::getModel('admin/user')->load($userId);

        //Validate current admin password
        $currentPassword = $this->getRequest()->getParam('current_password', null);
        $this->getRequest()->setParam('current_password', null);
        $result = $this->_validateCurrentPassword($currentPassword);

        if (!is_array($result)) {
            $result = $user->validate();
        }
        if (is_array($result)) {
            foreach ($result as $error) {
                Mage::getSingleton('adminhtml/session')->addError($error);
            }
            $this->getResponse()->setRedirect($this->getUrl('*/*/'));
            return;
        }

        $user->setPasskeyCredentialIdHash(null);
        $user->setPasskeyPublicKey(null);

        try {
            $user->save();
            Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('adminhtml')->__('The account has been saved.'));
        } catch (Mage_Core_Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('adminhtml')->__('An error occurred while saving account.'));
        }
        $this->getResponse()->setRedirect($this->getUrl('*/*/'));
    }
}
