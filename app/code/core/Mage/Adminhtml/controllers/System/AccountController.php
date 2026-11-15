<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_System_AccountController extends Mage_Adminhtml_Controller_Action
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/myaccount';

    #[Maho\Config\Route('/admin/system_account/index')]
    public function indexAction(): void
    {
        $this->_title($this->__('System'))->_title($this->__('My Account'));

        $this->loadLayout();
        $this->_setActiveMenu('system/myaccount');
        $this->_addContent($this->getLayout()->createBlock('adminhtml/system_account_edit'));
        $this->renderLayout();
    }

    #[Maho\Config\Route('/admin/system_account/save')]
    public function saveAction(): void
    {
        $session = Mage::getSingleton('adminhtml/session');
        $request = $this->getRequest();
        $currentPassword = $request->getPost('current_password');
        $request->setParam('current_password', null);
        $result = $this->_validateCurrentPassword($currentPassword);
        if (is_array($result)) {
            foreach ($result as $error) {
                $session->addError($error);
            }
            $this->getResponse()->setRedirect($this->getUrl('*/*/'));
            return;
        }

        try {
            /** @var Mage_Admin_Service_User $service */
            $service = Mage::getService('admin/user');
            $userId = (int) Mage::getSingleton('admin/session')->getUser()->getId();
            $user = $service->getById($userId)
                ->setUsername($request->getPost('username', false))
                ->setFirstname($request->getPost('firstname', false))
                ->setLastname($request->getPost('lastname', false))
                ->setEmail(strtolower((string) $request->getPost('email', false)));

            if ($request->getPost('password_change') !== null) {
                if ($request->getPost('new_password', false)) {
                    $user->setNewPassword($request->getPost('new_password', false));
                }
                if ($request->getPost('password_confirmation', false)) {
                    $user->setPasswordConfirmation($request->getPost('password_confirmation', false));
                }
            }

            $backendLocale = $request->getPost('backend_locale', false);
            $user->setBackendLocale($backendLocale == 0 ? null : $backendLocale);

            $passkeyValue = $request->getPost('passkey_value');
            if (json_validate((string) $passkeyValue)) {
                $user->setPasskeyData(json_decode($passkeyValue, true));
            } elseif ($passkeyValue === 'deleted') {
                $user->setPasskeyCredentialIdHash(null);
                $user->setPasskeyPublicKey(null);
            }

            if ($user->getPasskeyCredentialIdHash()) {
                $user->setPasswordEnabled((bool) $request->getPost('password_enabled'));
            } else {
                $user->setPasswordEnabled();
            }

            $user->applyTwofaChange(
                (bool) $request->getPost('twofa_enabled'),
                (string) $request->getPost('twofa_verification_code', ''),
            );

            $service->save($user, actingUserId: $userId);
            $session->addSuccess(Mage::helper('adminhtml')->__('The account has been saved.'));
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $session->addError($error['message']);
            }
        } catch (Mage_Core_Exception $e) {
            $session->addError($e->getMessage());
        } catch (Throwable $e) {
            Mage::logException($e);
            $session->addError(Mage::helper('adminhtml')->__('An error occurred while saving account.'));
        }
        $this->getResponse()->setRedirect($this->getUrl('*/*/'));
    }
}
