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

    public function getQRCodeUrl(#[\SensitiveParameter] string $username, #[\SensitiveParameter] string $secret): string
    {
        $storeName = Mage::getStoreConfig('general/store_information/name');
        $otp = \OTPHP\TOTP::create($secret);
        $otp->setLabel($username . '@' . $storeName);
        $otp->setIssuer('Maho Admin');

        return $otp->getQrCodeUri(
            'https://api.qrserver.com/v1/create-qr-code/?color=000000&bgcolor=FFFFFF&data=[DATA]&qzone=2&margin=0&size=300x300&ecc=M',
            '[DATA]',
        );
    }

    public function verifyCode(string $secret, string $code): bool
    {
        $otp = \OTPHP\TOTP::create($secret);
        return $otp->verify($code);
    }
}
