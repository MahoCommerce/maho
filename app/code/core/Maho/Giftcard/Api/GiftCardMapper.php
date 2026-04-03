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

final class GiftCardMapper
{
    public static function mapToDto(\Maho_Giftcard_Model_Giftcard $giftcard): GiftCard
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
