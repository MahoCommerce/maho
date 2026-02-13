<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\State\Provider;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\ApiResource\GiftCard;
use Maho\ApiPlatform\Pagination\ArrayPaginator;

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
     * @return GiftCard|ArrayPaginator|null
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): GiftCard|ArrayPaginator|null
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

        // Handle collection query (list gift cards)
        if ($operation instanceof CollectionOperationInterface) {
            return $this->getGiftCardCollection($context);
        }

        // Handle REST GET or item_query by ID
        $id = $uriVariables['id'] ?? null;
        if ($id) {
            return $this->getGiftCardById((int) $id);
        }

        return null;
    }

    /**
     * Get gift card by ID
     */
    private function getGiftCardById(int $id): GiftCard
    {
        $giftcard = \Mage::getModel('giftcard/giftcard')->load($id);

        if (!$giftcard->getId()) {
            throw new \RuntimeException('Gift card not found');
        }

        return $this->mapToDto($giftcard);
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
     * Get gift card collection with pagination (admin/API user only)
     */
    private function getGiftCardCollection(array $context): ArrayPaginator
    {
        $filters = $context['filters'] ?? [];
        $page = (int) ($filters['page'] ?? 1);
        $pageSize = min((int) ($filters['itemsPerPage'] ?? $filters['pageSize'] ?? 20), 100);

        $collection = \Mage::getResourceModel('giftcard/giftcard_collection');
        $collection->setOrder('created_at', 'DESC');

        $total = $collection->getSize();

        $collection->setPageSize($pageSize);
        $collection->setCurPage($page);

        $items = [];
        foreach ($collection as $giftcard) {
            $items[] = $this->mapToDto($giftcard);
        }

        return new ArrayPaginator(
            items: $items,
            currentPage: $page,
            itemsPerPage: $pageSize,
            totalItems: $total,
        );
    }

    /**
     * Map Maho gift card model to GiftCard DTO
     */
    private function mapToDto(\Maho_Giftcard_Model_Giftcard $giftcard): GiftCard
    {
        $dto = new GiftCard();
        $dto->id = (int) $giftcard->getId();
        $dto->code = $giftcard->getCode();
        $dto->balance = (float) $giftcard->getBalance();
        $dto->initialBalance = (float) $giftcard->getInitialBalance();
        $dto->status = $giftcard->getStatus();
        $dto->expirationDate = $giftcard->getExpiresAt();
        $dto->currencyCode = $giftcard->getCurrencyCode();
        $dto->createdAt = $giftcard->getCreatedAt();
        $dto->recipientName = $giftcard->getData('recipient_name');
        $dto->recipientEmail = $giftcard->getData('recipient_email');
        $dto->senderName = $giftcard->getData('sender_name');
        $dto->senderEmail = $giftcard->getData('sender_email');
        $dto->message = $giftcard->getData('message');

        return $dto;
    }
}
