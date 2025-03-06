<?php

/**
 * Maho
 *
 * @package    Maho_Captcha
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Captcha_IndexController extends Mage_Core_Controller_Front_Action
{
    public function challengeAction()
    {
        try {
            $helper = Mage::helper('captcha');
            if (!$helper->isEnabled()) {
                throw new Exception('Captcha is disabled');
            }

            $options = new \AltchaOrg\Altcha\ChallengeOptions([
                'algorithm' => \AltchaOrg\Altcha\Algorithm::SHA512,
                'saltLength' => 32,
                'expires' => (new DateTime())->modify('+1 minute'),
                'hmacKey' => $helper->getHmacKey(),
            ]);
            $challenge = \AltchaOrg\Altcha\Altcha::createChallenge($options);

            $this->getResponse()->setBodyJson($challenge);
        } catch (Exception $e) {
            $this->getResponse()
                ->setHttpResponseCode(400)
                ->setBodyJson(['error' => true, 'message' => $e->getMessage()]);
        }
    }
}
