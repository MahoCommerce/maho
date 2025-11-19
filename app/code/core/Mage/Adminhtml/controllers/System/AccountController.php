<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_System_AccountController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/myaccount';

    public function indexAction(): void
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
    public function saveAction(): void
    {
        $userId = Mage::getSingleton('admin/session')->getUser()->getId();
        $user = Mage::getModel('admin/user')->load($userId);

        $user->setId($userId)
            ->setUsername($this->getRequest()->getPost('username', false))
            ->setFirstname($this->getRequest()->getPost('firstname', false))
            ->setLastname($this->getRequest()->getPost('lastname', false))
            ->setEmail(strtolower($this->getRequest()->getPost('email', false)));

        if ($this->getRequest()->getPost('password_change') !== null) {
            if ($this->getRequest()->getPost('new_password', false)) {
                $user->setNewPassword($this->getRequest()->getPost('new_password', false));
            }
            if ($this->getRequest()->getPost('password_confirmation', false)) {
                $user->setPasswordConfirmation($this->getRequest()->getPost('password_confirmation', false));
            }
        }

        $backendLocale = $this->getRequest()->getPost('backend_locale', false);
        $backendLocale = $backendLocale == 0 ? null : $backendLocale;
        $user->setBackendLocale($backendLocale);

        // Validate current admin password
        $currentPassword = $this->getRequest()->getPost('current_password');
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

        try {
            $passkeyValue = $this->getRequest()->getPost('passkey_value');
            if (json_validate($passkeyValue)) {
                $user->setPasskeyData(json_decode($passkeyValue, true));
            } elseif ($passkeyValue === 'deleted') {
                $user->setPasskeyCredentialIdHash(null);
                $user->setPasskeyPublicKey(null);
            }

            if ($user->getPasskeyCredentialIdHash()) {
                $user->setPasswordEnabled((bool) $this->getRequest()->getPost('password_enabled'));
            } else {
                $user->setPasswordEnabled(true);
            }

            $user->setTwofaEnabled((bool) $this->getRequest()->getPost('twofa_enabled'));
            $twofaCode = $this->getRequest()->getPost('twofa_verification_code', '');
            if ($user->getTwofaEnabled() && $twofaCode) {
                if (!Mage::helper('admin/auth')->verifyTwofaCode($user->getTwofaSecret(), $twofaCode)) {
                    Mage::throwException(Mage::helper('adminhtml')->__('Invalid 2FA verification code'));
                }
            }

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
