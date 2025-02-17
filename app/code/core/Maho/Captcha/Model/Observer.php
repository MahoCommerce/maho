<?php

/**
 * Maho
 *
 * @package    Maho_Captcha
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Captcha_Model_Observer
{
    public function verify(Varien_Event_Observer $observer): void
    {
        $helper = Mage::helper('maho_captcha');
        if (!$helper->isEnabled()) {
            return;
        }

        /** @var Mage_Core_Controller_Front_Action $controller */
        $controller = $observer->getControllerAction();
        $data = $controller->getRequest()->getPost();

        $token = $data['altcha'] ?? '';
        if ($helper->verify((string) $token)) {
            return;
        }

        $this->failedVerification($controller);
    }

    public function verifyAjax(Varien_Event_Observer $observer): void
    {
        $helper = Mage::helper('maho_captcha');
        if (!$helper->isEnabled()) {
            return;
        }

        /** @var Mage_Core_Controller_Front_Action $controller */
        $controller = $observer->getControllerAction();
        $data = $controller->getRequest()->getPost();

        $token = $data['altcha'] ?? '';
        if ($helper->verify((string) $token)) {
            return;
        }

        $this->failedVerification($controller, true);
    }

    public function verifyAdmin(Varien_Event_Observer $observer): void
    {
        $helper = Mage::helper('maho_captcha');
        if (!$helper->isEnabled()) {
            return;
        }

        $request = Mage::app()->getRequest();
        if ($request->getActionName() == 'prelogin' || !$request->isPost()) {
            return;
        }

        $data = $request->getPost();
        $token = $data['altcha'] ?? '';

        if ($helper->verify((string) $token)) {
            return;
        }

        Mage::throwException(Mage::helper('maho_captcha')->__('Incorrect CAPTCHA.'));
    }

    protected function failedVerification(Mage_Core_Controller_Front_Action $controller, bool $isAjax = false): void
    {
        $controller->setFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH, true);
        $errorMessage = Mage::helper('maho_captcha')->__('Incorrect CAPTCHA.');

        if ($isAjax) {
            $result = ['error' => 1, 'message' => $errorMessage];
            $controller->getResponse()->setBodyJson($result);
            return;
        }

        Mage::getSingleton('core/session')->addError($errorMessage);
        $request = $controller->getRequest();
        $refererUrl = $request->getServer('HTTP_REFERER');
        if ($url = $request->getParam(Mage_Core_Controller_Varien_Action::PARAM_NAME_REFERER_URL)) {
            $refererUrl = $url;
        } elseif ($url = $request->getParam(Mage_Core_Controller_Varien_Action::PARAM_NAME_BASE64_URL)) {
            $refererUrl = Mage::helper('core')->urlDecodeAndEscape($url);
        } elseif ($url = $request->getParam(Mage_Core_Controller_Varien_Action::PARAM_NAME_URL_ENCODED)) {
            $refererUrl = Mage::helper('core')->urlDecodeAndEscape($url);
        }
        $controller->getResponse()->setRedirect($refererUrl);
    }
}
