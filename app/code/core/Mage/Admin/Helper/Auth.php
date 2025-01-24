<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use \lbuchs\WebAuthn\WebAuthn;
use \lbuchs\WebAuthn\Attestation\AuthenticatorData;

/**
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Admin_Helper_Auth extends Mage_Core_Helper_Abstract
{
    public function getWebAuthn(): WebAuthn
    {
        return new WebAuthn(
            Mage::getStoreConfig('web/secure/name') ?: 'Maho',
            parse_url(Mage::getBaseUrl(), PHP_URL_HOST),
        );
    }

    public function getWebAuthnAttestationAuthenticatorData(#[\SensitiveParameter] string $authenticatorData): AuthenticatorData
    {
        return new AuthenticatorData($authenticatorData);
    }

    public function getTwofaSecret(): string
    {
        return \OTPHP\TOTP::create()->getSecret();
    }

    public function getTwofaQRCode(#[\SensitiveParameter] string $username, #[\SensitiveParameter] string $secret): string
    {
        $otp = \OTPHP\TOTP::create($secret);
        $otp->setLabel($username);
        $otp->setParameter('image', 'https://mahocommerce.com/assets/maho-logo-square.png');
        if ($storeName = Mage::getStoreConfig('general/store_information/name')) {
            $otp->setIssuer($storeName);
        } else {
            $otp->setIssuer('Maho Admin');
        }

        $qrWriter = new \BaconQrCode\Writer(
            new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(300),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd(),
            ),
        );
        return $qrWriter->writeString($otp->getProvisioningUri());
    }

    public function verifyTwofaCode(#[\SensitiveParameter] string $secret, #[\SensitiveParameter] string $code): bool
    {
        $otp = \OTPHP\TOTP::create($secret);
        return $otp->verify($code);
    }
}
