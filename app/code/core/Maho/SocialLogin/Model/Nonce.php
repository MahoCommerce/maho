<?php

/**
 * One-time nonces binding a browser session to the ID tokens it requests.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SocialLogin
 */

declare(strict_types=1);

class Maho_SocialLogin_Model_Nonce
{
    private const SESSION_KEY = 'sociallogin_nonces';
    private const MAX_PENDING = 5;

    public function issue(): string
    {
        $nonce = bin2hex(random_bytes(16));
        $nonces = $this->getPending();
        $nonces[$nonce] = time() + Mage::helper('sociallogin')->getNonceTtl();
        while (count($nonces) > self::MAX_PENDING) {
            array_shift($nonces);
        }
        $this->savePending($nonces);
        return $nonce;
    }

    public function consume(#[\SensitiveParameter] string $nonce): bool
    {
        if ($nonce === '') {
            return false;
        }
        $nonces = $this->getPending();
        $valid = isset($nonces[$nonce]) && $nonces[$nonce] >= time();
        unset($nonces[$nonce]);
        $this->savePending($nonces);
        return $valid;
    }

    /**
     * @return array<string, int> nonce => expiry timestamp
     */
    private function getPending(): array
    {
        $nonces = Mage::getSingleton('core/session')->getData(self::SESSION_KEY);
        if (!is_array($nonces)) {
            return [];
        }
        $now = time();
        return array_filter($nonces, fn($expiresAt) => (int) $expiresAt >= $now);
    }

    /**
     * @param array<string, int> $nonces
     */
    private function savePending(array $nonces): void
    {
        Mage::getSingleton('core/session')->setData(self::SESSION_KEY, $nonces);
    }
}
