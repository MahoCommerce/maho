<?php

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

        $token = $data['cf-turnstile-response'] ?? '';
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

        $token = $data['cf-turnstile-response'] ?? '';
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

        /** @var Mage_Adminhtml_IndexController $controller */
        $controller = $observer->getControllerAction();
        $request = $controller->getRequest();
        if (!$request->isPost()) {
            return;
        }



        $data = $request->getPost();
        $token = $data['cf-turnstile-response'] ?? '';
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
            $controller->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
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