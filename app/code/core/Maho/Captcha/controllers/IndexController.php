<?php

/**
 * Maho
 *
 * @package    Maho_Captcha
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Captcha_IndexController extends Mage_Core_Controller_Front_Action
{
    public function challengeAction(): void
    {
        $helper = Mage::helper('captcha');
        try {
            if (!$helper->isEnabled()) {
                Mage::throwException($helper->__('Captcha is disabled'));
            }
            $this->getResponse()->setBodyJson($helper->createChallenge());
        } catch (Mage_Core_Exception $e) {
            $error = $e->getMessage();
        } catch (Exception $e) {
            $error = $helper->__('Internal Error');
        }
        if (isset($error)) {
            $this->getResponse()
                ->setHttpResponseCode(400)
                ->setBodyJson(['error' => true, 'message' => $error]);
        }
    }
}
