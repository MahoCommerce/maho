<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Helper_Security extends Mage_Core_Helper_Abstract
{
    /** @var array<int, array{block: class-string<Mage_Core_Block_Abstract>, method: string}> */
    private array $invalidBlockActions = [
        ['block' => Mage_Page_Block_Html_Topmenu_Renderer::class, 'method' => 'render'],
        ['block' => Mage_Core_Block_Template::class, 'method' => 'fetchView'],
    ];

    /**
     * @param string[] $args
     * @throws Mage_Core_Exception
     */
    public function ensureBlockMethodAllowed(Mage_Core_Block_Abstract $block, string $method, array $args): void
    {
        foreach ($this->invalidBlockActions as $action) {
            $calledMethod = strtolower($method);
            if (str_contains($calledMethod, '::')) {
                $calledMethod = explode('::', $calledMethod)[1];
            }
            if ($block instanceof $action['block'] && strtolower($action['method']) === $calledMethod) {
                Mage::throwException(
                    sprintf('Action with combination block %s and method %s is forbidden.', $block::class, $method),
                );
            }
        }
    }

    #[\Deprecated(message: 'since 26.1, use ensureBlockMethodAllowed() instead')]
    public function validateAgainstBlockMethodBlacklist(Mage_Core_Block_Abstract $block, string $method, array $args): void
    {
        $this->ensureBlockMethodAllowed($block, $method, $args);
    }

    /**
     * Generate a new random TOTP secret.
     */
    public function generateTotpSecret(): string
    {
        return \OTPHP\TOTP::generate()->getSecret();
    }

    /**
     * Build an SVG QR code for the given TOTP secret, ready to be scanned by an authenticator app.
     */
    public function getTotpQrCode(#[\SensitiveParameter] string $label, #[\SensitiveParameter] string $secret, ?string $issuer = null): string
    {
        $issuer ??= Mage::getStoreConfig('general/store_information/name') ?: 'Maho';

        $otp = \OTPHP\TOTP::createFromSecret($secret)
            ->withLabel($label)
            ->withIssuer($issuer)
            ->withParameter('image', 'https://mahocommerce.com/assets/maho-logo-square.png');

        $qrWriter = new \BaconQrCode\Writer(
            new \BaconQrCode\Renderer\ImageRenderer(
                new \BaconQrCode\Renderer\RendererStyle\RendererStyle(300),
                new \BaconQrCode\Renderer\Image\SvgImageBackEnd(),
            ),
        );
        return $qrWriter->writeString($otp->getProvisioningUri());
    }

    /**
     * Verify a 6-digit TOTP code against the given secret.
     */
    public function verifyTotpCode(#[\SensitiveParameter] string $secret, #[\SensitiveParameter] string $code): bool
    {
        $otp = \OTPHP\TOTP::createFromSecret($secret);
        return $otp->verify($code);
    }
}
