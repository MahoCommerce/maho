<?php

/**
 * Maho
 *
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Admin_Model_Observer
{
    public const FLAG_NO_LOGIN = 'no-login';

    /**
     * Handler for controller_action_predispatch event
     *
     * @param \Maho\Event\Observer $observer
     */
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
}
