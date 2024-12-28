<?php

class Mage_Adminhtml_Helper_TwoFactorAuthentication extends Mage_Core_Helper_Abstract
{
    public function getSecret(): string
    {
        return \OTPHP\TOTP::create()->getSecret();
    }

    public function getQRCodeUrl($username, $secret): string
    {
        $storeName = Mage::getStoreConfig('general/store_information/name');
        $otp = \OTPHP\TOTP::create($secret);
        $otp->setLabel($username . '@' . $storeName);
        $otp->setIssuer('Maho Admin');

        return $otp->getQrCodeUri(
            'https://api.qrserver.com/v1/create-qr-code/?color=000000&bgcolor=FFFFFF&data=[DATA]&qzone=2&margin=0&size=300x300&ecc=M',
            '[DATA]'
        );
    }

    public function verifyCode($secret, $code): bool
    {
        $otp = \OTPHP\TOTP::create($secret);
        return $otp->verify($code);
    }
}
