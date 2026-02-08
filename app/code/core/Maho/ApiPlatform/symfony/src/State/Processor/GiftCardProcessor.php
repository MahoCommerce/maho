<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Maho\ApiPlatform\ApiResource\GiftCard;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Gift Card State Processor - Handles gift card creation and balance adjustment
 *
 * @implements ProcessorInterface<GiftCard, GiftCard>
 */
final class GiftCardProcessor implements ProcessorInterface
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): GiftCard
    {
        $operationName = $operation->getName();

        return match ($operationName) {
            'createGiftcard' => $this->createGiftcard($context),
            'adjustGiftcardBalance' => $this->adjustBalance($context),
            default => $this->createGiftcardFromRest($data, $context),
        };
    }

    private function createGiftcardFromRest(GiftCard $data, array $context): GiftCard
    {
        return $this->doCreateGiftcard(
            $data->initialBalance,
            $data->code,
            $data->recipientName,
            $data->recipientEmail,
            $data->senderName,
            $data->senderEmail,
            $data->message,
            null,
            $data->expirationDate,
        );
    }

    private function createGiftcard(array $context): GiftCard
    {
        $args = $context['args']['input'] ?? [];

        return $this->doCreateGiftcard(
            (float) ($args['initialBalance'] ?? 0),
            $args['code'] ?? null,
            $args['recipientName'] ?? null,
            $args['recipientEmail'] ?? null,
            $args['senderName'] ?? null,
            $args['senderEmail'] ?? null,
            $args['message'] ?? null,
            isset($args['websiteId']) ? (int) $args['websiteId'] : null,
            $args['expiresAt'] ?? null,
        );
    }

    private function doCreateGiftcard(
        float $initialBalance,
        ?string $code,
        ?string $recipientName,
        ?string $recipientEmail,
        ?string $senderName,
        ?string $senderEmail,
        ?string $message,
        ?int $websiteId,
        ?string $expiresAt,
    ): GiftCard {
        if ($initialBalance <= 0) {
            throw new BadRequestHttpException('Initial balance must be greater than 0');
        }

        if ($initialBalance > 10000) {
            throw new BadRequestHttpException('Initial balance cannot exceed 10,000');
        }

        $helper = \Mage::helper('giftcard');

        // Generate or validate code
        if ($code !== null) {
            $code = trim($code);
            if (strlen($code) < 4 || strlen($code) > 64) {
                throw new BadRequestHttpException('Gift card code must be between 4 and 64 characters');
            }

            // Check uniqueness
            $existing = \Mage::getModel('giftcard/giftcard')->loadByCode($code);
            if ($existing->getId()) {
                throw new ConflictHttpException('A gift card with this code already exists');
            }
        } else {
            $code = $helper->generateCode();
        }

        $websiteId = $websiteId ?: (int) \Mage::app()->getStore()->getWebsiteId();

        // Calculate expiration
        if ($expiresAt !== null) {
            $expiresAt = trim($expiresAt);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $expiresAt)) {
                throw new BadRequestHttpException('Expiration date must be in YYYY-MM-DD format');
            }
            // Ensure it includes time component for DB storage
            if (strlen($expiresAt) === 10) {
                $expiresAt .= ' 23:59:59';
            }
        } else {
            $expiresAt = $helper->calculateExpirationDate();
        }

        $now = \Mage_Core_Model_Locale::now();

        $giftcard = \Mage::getModel('giftcard/giftcard');
        $giftcard->setData([
            'code' => $code,
            'status' => \Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE,
            'website_id' => $websiteId,
            'balance' => $initialBalance,
            'initial_balance' => $initialBalance,
            'recipient_name' => $recipientName,
            'recipient_email' => $recipientEmail,
            'sender_name' => $senderName,
            'sender_email' => $senderEmail,
            'message' => $message,
            'expires_at' => $expiresAt,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $giftcard->save();

        // Send notification email if recipient email is provided
        if ($recipientEmail) {
            try {
                $helper->sendGiftcardEmail($giftcard);
            } catch (\Exception $e) {
                \Mage::logException($e);
            }
        }

        return $this->mapToDto($giftcard);
    }

    private function adjustBalance(array $context): GiftCard
    {
        $args = $context['args']['input'] ?? [];
        $code = trim((string) ($args['code'] ?? ''));
        $newBalance = (float) ($args['newBalance'] ?? 0);
        $comment = $args['comment'] ?? null;

        if ($code === '') {
            throw new BadRequestHttpException('Gift card code is required');
        }

        if ($newBalance < 0) {
            throw new BadRequestHttpException('Balance cannot be negative');
        }

        if ($newBalance > 10000) {
            throw new BadRequestHttpException('Balance cannot exceed 10,000');
        }

        $giftcard = \Mage::getModel('giftcard/giftcard')->loadByCode($code);
        if (!$giftcard->getId()) {
            throw new NotFoundHttpException('Gift card not found');
        }

        $giftcard->adjustBalance($newBalance, $comment);

        return $this->mapToDto($giftcard);
    }

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
