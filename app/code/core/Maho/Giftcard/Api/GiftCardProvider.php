<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Giftcard\Api;

use Maho\ApiPlatform\Resource;

/**
 * Gift Card State Provider - Fetches gift card data for API Platform
 */
final class GiftCardProvider extends \Maho\ApiPlatform\Provider
{
    protected ?string $modelAlias = 'giftcard/giftcard';
    protected array $defaultSort = ['created_at' => 'DESC'];

    #[\Override]
    protected function handleOperation(string $name, array $context, array $uriVariables): mixed
    {
        if ($name === 'checkGiftcardBalance') {
            $code = $context['args']['code'] ?? null;
            if (!$code) {
                throw new \RuntimeException('Gift card code is required');
            }

            return $this->getGiftCardByCode(trim($code));
        }

        return null;
    }

    /**
     * Get gift card by code
     */
    private function getGiftCardByCode(string $code): Resource
    {
        $giftcard = \Mage::getModel('giftcard/giftcard')->loadByCode($code);

        if (!$giftcard->getId()) {
            throw new \RuntimeException('Gift card "' . $code . '" not found');
        }

        return $this->toDto($giftcard);
    }

    #[\Override]
    protected function toDto(object $model): Resource
    {
        $dto = new GiftCard();
        $dto->id = (int) $model->getId();
        $dto->code = $model->getCode();
        $dto->balance = (float) $model->getBalance();
        $dto->initialBalance = (float) $model->getInitialBalance();
        $dto->status = $model->getStatus();
        $dto->expirationDate = $model->getExpiresAt();
        $dto->currencyCode = $model->getCurrencyCode();
        $dto->createdAt = $model->getCreatedAt();
        $dto->recipientName = $model->getData('recipient_name');
        $dto->recipientEmail = $model->getData('recipient_email');
        $dto->senderName = $model->getData('sender_name');
        $dto->senderEmail = $model->getData('sender_email');
        $dto->message = $model->getData('message');

        return $dto;
    }
}
