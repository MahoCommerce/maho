<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Captcha
 */

class Maho_Captcha_IndexController extends Mage_Core_Controller_Front_Action
{
    #[Maho\Config\Route('/captcha/index/challenge')]
    public function challengeAction(): void
    {
        $helper = Mage::helper('captcha');
        try {
            if (!$helper->isEnabled()) {
                Mage::throwException($helper->__('Captcha is disabled'));
            }
            $this->getResponse()->setBodyJson($helper->createChallenge()->toArray());
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
