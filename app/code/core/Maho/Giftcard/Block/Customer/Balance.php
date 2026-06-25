<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/**
 * Block for the customer-facing gift card balance lookup page.
 *
 * Pulls the most recent lookup result out of giftcard/session (set by
 * BalanceController::checkAction) so the template can render the balance
 * panel below the form without the result surviving across navigations or
 * leaking through browser history.
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
        if (!is_array($data)) {
            return null;
        }
        // One-shot: consume on render so a back/forward navigation doesn't
        // re-display the previous customer's check result if the customer
        // shares a device.
        $session->setLastGiftcardLookup(null);
        return $data;
    }

    public function getCheckUrl(): string
    {
        return $this->getUrl('giftcard/balance/check');
    }

    public function formatBalance(float $amount, string $currency): string
    {
        // PHP NumberFormatter (Maho's replacement for the removed Zend_Currency)
        // takes (amount, currency_code) on formatCurrency rather than the old
        // Zend_Currency->toCurrency($amount) call.
        return Mage::app()->getLocale()->currency($currency)->formatCurrency($amount, $currency);
    }
}
