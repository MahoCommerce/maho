<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

namespace Maho\Giftcard\Api;

use Maho\ApiPlatform\CrudProvider;

/**
 * Gift Card Provider, only needs the checkGiftcardBalance named query.
 *
 * All standard CRUD (get, list) is handled by CrudProvider + CrudResource.
 */
final class GiftCardProvider extends CrudProvider
{
    protected array $defaultSort = ['created_at' => 'DESC'];

    #[\Override]
    protected function handleOperation(string $name, array $context, array $uriVariables): mixed
    {
        if ($name === 'checkGiftcardBalance') {
            // Public balance check: throttle to stop code probing / enumeration.
            $this->checkRateLimitByIp('giftcard_balance', 'giftcard_balance', 60);

            $code = $context['args']['code'] ?? null;
            if (!$code) {
                throw new \RuntimeException('Gift card code is required');
            }

            $giftcard = \Mage::getModel('giftcard/giftcard')->loadByCode(trim($code));
            if (!$giftcard->getId()) {
                throw new \RuntimeException('Gift card not found');
            }

            return $this->toBalanceDto($giftcard);
        }

        return null;
    }

    /**
     * Minimal projection for the public balance check: only spendable/status
     * fields, never the recipient/sender PII or message the full DTO carries.
     * The code is masked so a balance probe can't echo back full codes.
     */
    private function toBalanceDto(\Maho_Giftcard_Model_Giftcard $giftcard): GiftCard
    {
        $dto = new GiftCard();
        $dto->id = 0;
        $dto->code = \Mage::helper('giftcard')->maskCode((string) $giftcard->getCode());
        $dto->balance = (float) $giftcard->getBalance();
        $dto->status = $giftcard->getStatus();
        $dto->currencyCode = $giftcard->getCurrencyCode();
        $dto->expiresAt = $giftcard->getExpiresAt();

        return $dto;
    }
}
