<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

namespace Maho\Giftcard\Api;

use Maho\ApiPlatform\CrudProvider;
use Maho\ApiPlatform\Resource;
use Maho\ApiPlatform\Service\StoreContext;

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
        if ($name === 'checkBalance') {
            // Public balance check: throttle to stop code probing / enumeration.
            $this->checkRateLimitByIp('giftcard_balance', 'giftcard_balance', 60);

            $code = $context['args']['code'] ?? null;
            if (!$code) {
                throw new \RuntimeException('Gift card code is required');
            }

            $giftcard = \Mage::getModel('giftcard/giftcard')->loadByCode(trim($code));
            // A website the card is not associated with must not answer for it
            if (!$giftcard->getId()
                || !$giftcard->isAvailableOnWebsite((int) StoreContext::getStore()->getWebsiteId())
            ) {
                throw new \RuntimeException('Gift card not found');
            }

            return $this->toBalanceDto($giftcard);
        }

        return null;
    }

    /**
     * Restricted tokens only see cards reaching one of their allowed websites.
     */
    #[\Override]
    protected function provideItem(int|string $id): ?Resource
    {
        $dto = parent::provideItem($id);
        if ($dto instanceof GiftCard) {
            $this->assertAnyWebsiteAllowed($dto->websiteIds ?? [], $this->requireUser(), 'gift card');
        }
        return $dto;
    }

    /**
     * Membership lives in the junction, so the shared main_table.website_id
     * filter does not apply here.
     */
    #[\Override]
    protected function applyCollectionFilters(object $collection, array $filters): void
    {
        parent::applyCollectionFilters($collection, $filters);
        // Hydrate the association up front; afterMap() reads it per item, and
        // without the aggregated column that is one junction query per card
        $collection->addWebsiteIdsToSelect();
        $allowedWebsiteIds = $this->allowedWebsiteIds($this->requireUser());
        if ($allowedWebsiteIds !== null) {
            $collection->addWebsiteIdsFilter($allowedWebsiteIds);
        }
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
