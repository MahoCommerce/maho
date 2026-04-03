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

use ApiPlatform\Metadata\Operation;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Gift Card Processor — custom operations (create, adjust balance) that go beyond basic CRUD.
 *
 * Standard CRUD create uses CrudProcessor base. The createGiftcard and adjustBalance
 * mutations require custom business logic (code generation, balance validation, email).
 */
final class GiftCardProcessor extends \Maho\ApiPlatform\CrudProcessor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): GiftCard
    {
        $this->requireAdminOrApiUser('Gift card management requires admin or API access');
        $operationName = $operation->getName();

        return match ($operationName) {
            'createGiftcard' => $this->createGiftcard($context),
            'adjustGiftcardBalance' => $this->adjustBalance($context),
            default => $this->createGiftcardFromRest($data),
        };
    }

    private function createGiftcardFromRest(GiftCard $data): GiftCard
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

        if ($code !== null) {
            $code = trim($code);
            if (strlen($code) < 4 || strlen($code) > 64) {
                throw new BadRequestHttpException('Gift card code must be between 4 and 64 characters');
            }

            $existing = \Mage::getModel('giftcard/giftcard')->loadByCode($code);
            if ($existing->getId()) {
                throw new ConflictHttpException('A gift card with this code already exists');
            }
        } else {
            $code = $helper->generateCode();
        }

        $websiteId = $websiteId ?: (int) \Mage::app()->getStore()->getWebsiteId();

        if ($expiresAt !== null) {
            $expiresAt = trim($expiresAt);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}/', $expiresAt)) {
                throw new BadRequestHttpException('Expiration date must be in YYYY-MM-DD format');
            }
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

        if ($recipientEmail) {
            try {
                $helper->sendGiftcardEmail($giftcard);
            } catch (\Exception $e) {
                \Mage::logException($e);
            }
        }

        return GiftCard::fromModel($giftcard);
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

        return GiftCard::fromModel($giftcard);
    }
}
