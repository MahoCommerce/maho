<?php

/**
 * Maho
 *
 * @package    Maho_Captcha
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Captcha_Model_Observer
{
    #[Maho\Config\Observer('controller_action_predispatch_checkout_onepage_savebilling', area: 'frontend')]
    #[Maho\Config\Observer('controller_action_predispatch_contacts_index_post', area: 'frontend')]
    #[Maho\Config\Observer('controller_action_predispatch_customer_account_createpost', area: 'frontend')]
    #[Maho\Config\Observer('controller_action_predispatch_customer_account_forgotpasswordpost', area: 'frontend')]
    #[Maho\Config\Observer('controller_action_predispatch_newsletter_subscriber_new', area: 'frontend')]
    #[Maho\Config\Observer('controller_action_predispatch_review_product_post', area: 'frontend')]
    #[Maho\Config\Observer('controller_action_predispatch_wishlist_index_send', area: 'frontend')]
    public function verify(\Maho\Event\Observer $observer): void
    {
        $helper = Mage::helper('captcha');
        if (!$helper->isEnabled()) {
            return;
        }

        /** @var Mage_Core_Controller_Front_Action $controller */
        $controller = $observer->getControllerAction();
        $data = $controller->getRequest()->getPost();
        $token = $data['maho_captcha'] ?? '';
        if ($helper->verify((string) $token)) {
            return;
        }

        $isAjax = $controller->getRequest()->isAjax();
        $this->failedVerification($controller, $isAjax);
    }

    public function verifyApi(\Maho\Event\Observer $observer): void
    {
        $helper = Mage::helper('captcha');
        if (!$helper->isEnabled()) {
            return;
        }

        $data = $observer->getEvent()->getData('data');
        $token = $data['captchaToken'] ?? $data['maho_captcha'] ?? '';

        /** @var \Maho\DataObject $result */
        $result = $observer->getEvent()->getResult();

        if (!$helper->verify((string) $token)) {
            $result->setVerified(false);
            $result->setError(Mage::helper('captcha')->__('Incorrect CAPTCHA.'));
        }
    }

    public function getCaptchaConfig(\Maho\Event\Observer $observer): void
    {
        $helper = Mage::helper('captcha');
        if (!$helper->isEnabled()) {
            return;
        }

        /** @var \Maho\DataObject $config */
        $config = $observer->getEvent()->getConfig();
        $config->setEnabled(true);
        $config->setProvider('altcha');
        $config->setChallengeUrl($helper->getChallengeUrl());
    }

    #[Maho\Config\Observer('admin_user_authenticate_before', area: 'adminhtml')]
    #[Maho\Config\Observer('controller_action_predispatch_adminhtml_index_forgotpassword', area: 'adminhtml')]
    public function verifyAdmin(\Maho\Event\Observer $observer): void
    {
        $helper = Mage::helper('captcha');
        if (!$helper->isEnabled()) {
            return;
        }

        $request = Mage::app()->getRequest();
        if ($request->getActionName() == 'prelogin' || !$request->isPost()) {
            return;
        }

        $data = $request->getPost();
        $token = $data['maho_captcha'] ?? '';

        if ($helper->verify((string) $token)) {
            return;
        }

        Mage::throwException(Mage::helper('captcha')->__('Incorrect CAPTCHA.'));
    }

    protected function failedVerification(Mage_Core_Controller_Front_Action $controller, bool $isAjax = false): void
    {
        $controller->setFlag('', Mage_Core_Controller_Varien_Action::FLAG_NO_DISPATCH, true);
        $errorMessage = Mage::helper('captcha')->__('Incorrect CAPTCHA.');

        if ($isAjax) {
            $result = ['error' => true, 'message' => $errorMessage];
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
