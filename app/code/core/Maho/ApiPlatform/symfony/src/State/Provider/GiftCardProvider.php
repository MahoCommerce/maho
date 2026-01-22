<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\ApiResource\GiftCard;

/**
 * Gift Card State Provider - Fetches gift card data for API Platform
 *
 * @implements ProviderInterface<GiftCard>
 */
final class GiftCardProvider implements ProviderInterface
{
    /**
     * Provide gift card data based on operation type
     *
     * @return GiftCard|null
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?GiftCard
    {
        $operationName = $operation->getName();

        // Handle checkGiftcardBalance query
        if ($operationName === 'checkGiftcardBalance') {
            $code = $context['args']['code'] ?? null;
            if (!$code) {
                throw new \RuntimeException('Gift card code is required');
            }

            return $this->getGiftCardByCode(trim($code));
        }

        // Handle REST GET by code
        $code = $uriVariables['code'] ?? null;
        if ($code) {
            return $this->getGiftCardByCode(trim($code));
        }

        return null;
    }

    /**
     * Get gift card by code
     */
    private function getGiftCardByCode(string $code): GiftCard
    {
        $giftcard = \Mage::getModel('giftcard/giftcard')->loadByCode($code);

        if (!$giftcard->getId()) {
            throw new \RuntimeException('Gift card "' . $code . '" not found');
        }

        return $this->mapToDto($giftcard);
    }

    /**
     * Map Maho gift card model to GiftCard DTO
     */
    private function mapToDto(\Maho_Giftcard_Model_Giftcard $giftcard): GiftCard
    {
        $dto = new GiftCard();
        $dto->id = (int) $giftcard->getId();
        $dto->code = $giftcard->getCode();
        $dto->balance = (float) $giftcard->getBaseBalance();
        $dto->initialBalance = (float) $giftcard->getBaseInitialBalance();
        $dto->status = $giftcard->getStatus();
        $dto->expirationDate = $giftcard->getExpiresAt();
        $dto->currencyCode = $giftcard->getBaseCurrencyCode();
        $dto->createdAt = $giftcard->getCreatedAt();

        return $dto;
    }
}
