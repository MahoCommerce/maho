<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/**
 * Customer-facing gift card balance lookup page.
 */
class Maho_Giftcard_Block_Customer_Balance extends Mage_Core_Block_Template
{
    /**
     * @return array{code: string, balance: float, currency_code: string, expires_at: ?string}|null
     */
    public function getLastLookup(): ?array
    {
        $session = Mage::getSingleton('giftcard/session');
        $data = $session->getLastGiftcardLookup();
        if (!is_array($data) || !isset($data['code'], $data['balance'], $data['currency_code'])) {
            return null;
        }
        // One-shot: back/forward navigation must not re-display the result on a shared device
        $session->setLastGiftcardLookup(null);
        return [
            'code'          => (string) $data['code'],
            'balance'       => (float) $data['balance'],
            'currency_code' => (string) $data['currency_code'],
            'expires_at'    => isset($data['expires_at']) ? (string) $data['expires_at'] : null,
        ];
    }

    public function getCheckUrl(): string
    {
        return $this->getUrl('giftcard/balance/check');
    }

    public function formatBalance(float $amount, string $currency): string
    {
        return Mage::helper('giftcard')->formatAmount($amount, $currency);
    }
}
