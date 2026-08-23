<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Admin
 */

class Mage_Admin_Model_Observer
{
    public const FLAG_NO_LOGIN = 'no-login';

    /**
     * Reachable while mandatory 2FA blocks the rest of the backend: the enrollment page,
     * its POST handler, the passkey registration calls it makes, and logging out.
     */
    private const TWOFA_ALLOWED_ACTIONS = [
        'system_account/index',
        'system_account/save',
        'index/passkeyregisterstart',
        'index/logout',
    ];

    /**
     * Handler for controller_action_predispatch event
     *
     * @param \Maho\Event\Observer $observer
     */
    #[Maho\Config\Observer('controller_action_predispatch', area: 'adminhtml', id: 'auth')]
    public function actionPreDispatchAdmin($observer)
    {
        /** @var Mage_Admin_Model_Session $session */
        $session = Mage::getSingleton('admin/session');

        $request = Mage::app()->getRequest();
        $user = $session->getUser();

        $requestedActionName = strtolower($request->getActionName());
        $openActions = [
            'passkeyloginstart',
            'forgotpassword',
            'resetpassword',
            'resetpasswordpost',
            'prelogin',
            'logout',
            'refresh', // captcha refresh
        ];
        if (in_array($requestedActionName, $openActions)) {
            $request->setDispatched(true);
        } else {
            if ($user) {
                $user->reload();
            }
            if (!$user || !$user->getId()) {
                if ($request->getPost('login')) {
                    /** @var Mage_Core_Model_Session $coreSession */
                    $coreSession = Mage::getSingleton('core/session');

                    if ($coreSession->validateFormKey($request->getPost('form_key'))) {
                        $postLogin = $request->getPost('login');
                        $username = $postLogin['username'] ?? '';
                        $password = $postLogin['password'] ?? '';
                        $twofaVerificationCode = $postLogin['twofa_verification_code'] ?? '';
                        $session->login($username, $password, $request, $twofaVerificationCode);
                        $request->setPost('login');
                    } else {
                        if (!$request->getParam('messageSent')) {
                            Mage::getSingleton('adminhtml/session')->addError(
                                Mage::helper('adminhtml')->__('Invalid Form Key. Please refresh the page.'),
                            );
                            $request->setParam('messageSent', true);
                        }
                    }

                    $coreSession->renewFormKey();
                }
                if (!$request->getInternallyForwarded()) {
                    $request->setInternallyForwarded();
                    if ($request->getParam('isIframe')) {
                        $request->setParam('forwarded', true)
                            ->setControllerName('index')
                            ->setActionName('deniedIframe')
                            ->setDispatched(false);
                    } elseif ($request->getParam('isAjax')) {
                        $request->setParam('forwarded', true)
                            ->setControllerName('index')
                            ->setActionName('deniedJson')
                            ->setDispatched(false);
                    } else {
                        $request->setParam('forwarded', true)
                            ->setRouteName('adminhtml')
                            ->setControllerName('index')
                            ->setActionName('login')
                            ->setDispatched(false);
                    }
                    return;
                }
            }
        }

        $session->refreshAcl();
    }

    /**
     * Send an admin user who did not enroll in 2FA to the enrollment page once the store
     * requires it. Before the configured date the user only sees a reminder.
     */
    #[Maho\Config\Observer('controller_action_predispatch', area: 'adminhtml', id: 'twofa')]
    public function enforceMandatoryTwofa(\Maho\Event\Observer $observer): void
    {
        /** @var Mage_Adminhtml_Controller_Action $action */
        $action = $observer->getControllerAction();
        $request = $action->getRequest();

        if ($request->getParam('isAjax') || $request->getParam('ajax') || $request->getParam('isIframe')) {
            return;
        }

        /** @var Mage_Admin_Model_Session $session */
        $session = Mage::getSingleton('admin/session');
        $user = $session->getUser();
        if (!$user || !$user->getId()) {
            return;
        }

        $helper = Mage::helper('admin');
        $state = $helper->getTwofaEnforcementState($user);
        if ($state === Mage_Admin_Helper_Data::TWOFA_STATE_OFF
            || $state === Mage_Admin_Helper_Data::TWOFA_STATE_COMPLIANT
        ) {
            return;
        }

        $adminSession = Mage::getSingleton('adminhtml/session');

        if ($state === Mage_Admin_Helper_Data::TWOFA_STATE_WARN) {
            // Once per session: the reminder belongs to the sign-in, not to every page load
            if (!$session->getTwofaReminderShown()) {
                $session->setTwofaReminderShown(true);
                $adminSession->addNotice(Mage::helper('adminhtml')->__(
                    'Two-factor authentication becomes mandatory in %s day(s). Set it up in System > My Account.',
                    $helper->getTwofaDaysUntilEnforcement(),
                ));
            }
            return;
        }

        if (in_array($this->_getActionPath($request), self::TWOFA_ALLOWED_ACTIONS, true)) {
            return;
        }

        if ($session->isAllowed('system/myaccount')) {
            $adminSession->addError(Mage::helper('adminhtml')->__(
                'Two-factor authentication is required. Set it up to continue.',
            ));
        } else {
            $adminSession->addError(Mage::helper('adminhtml')->__(
                'Two-factor authentication is required, but your role cannot open My Account. Ask an administrator to grant you access to it.',
            ));
        }

        $action->setFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH, true);
        $action->getResponse()->setRedirect(Mage::helper('adminhtml')->getUrl('adminhtml/system_account/index'));
    }

    protected function _getActionPath(Mage_Core_Controller_Request_Http $request): string
    {
        return strtolower($request->getControllerName() . '/' . $request->getActionName());
    }
}
