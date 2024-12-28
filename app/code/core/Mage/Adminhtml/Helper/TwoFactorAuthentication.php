<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Mage
 * @package    Mage_Adminhtml
 */
class Mage_Adminhtml_Helper_TwoFactorAuthentication extends Mage_Core_Helper_Abstract
{
    public function getSecret(): string
    {
        return \OTPHP\TOTP::create()->getSecret();
    }

    public function getQRCode(#[\SensitiveParameter] string $username, #[\SensitiveParameter] string $secret): string
    {
        $storeName = Mage::getStoreConfig('general/store_information/name');
        $otp = \OTPHP\TOTP::create($secret);
        $otp->setLabel($username . '@' . $storeName);
        $otp->setIssuer('Maho Admin');

        $qrWriter = new \BaconQrCode\Writer(
            new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(300),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd(),
            ),
        );
        return $qrWriter->writeString($otp->getProvisioningUri());
    }

    public function verifyCode(string $secret, string $code): bool
    {
        $otp = \OTPHP\TOTP::create($secret);
        return $otp->verify($code);
    }
}
