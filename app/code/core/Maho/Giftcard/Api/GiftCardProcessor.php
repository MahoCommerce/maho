<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

namespace Maho\Giftcard\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\CrudResource;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GiftCardProcessor extends \Maho\ApiPlatform\CrudProcessor
{
    private const MAX_BALANCE = 10000;

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        return match ($operation->getName()) {
            'create' => $this->createGiftcardFromGraphQl($context),
            'adjustBalance' => $this->adjustBalance($context),
            default => parent::process($data, $operation, $uriVariables, $context),
        };
    }

    #[\Override]
    protected function afterSave(object $model, CrudResource $data): void
    {
        $this->sendEmailToRecipient($model);
    }

    /** Balance bounds and duplicate-code check for the REST CRUD create/update path. */
    #[\Override]
    protected function validate(CrudResource $data, object $model, bool $isNew): void
    {
        // validate() runs BEFORE the DTO is applied to the model, so read the
        // incoming values from the DTO. The create field is initialBalance.
        if (!$data instanceof GiftCard) {
            return;
        }
        $balance = $data->initialBalance ?: $data->balance;
        $this->assertValidGiftcard(
            $balance,
            ($data->code !== null && $data->code !== '') ? $data->code : null,
            $model->getId() ? (int) $model->getId() : null,
        );
    }

    /**
     * Reject a non-positive or over-limit balance, and a custom code that
     * collides with an existing card. Shared by the REST and GraphQL paths.
     */
    private function assertValidGiftcard(float $balance, ?string $code, ?int $excludeId): void
    {
        if ($balance <= 0) {
            throw new BadRequestHttpException('Gift card balance must be greater than zero');
        }
        if ($balance > self::MAX_BALANCE) {
            throw new BadRequestHttpException('Gift card balance cannot exceed ' . self::MAX_BALANCE);
        }
        if ($code !== null && $code !== '') {
            $existing = \Mage::getModel('giftcard/giftcard')->loadByCode($code);
            if ($existing->getId() && (int) $existing->getId() !== $excludeId) {
                throw new ConflictHttpException("Gift card code '{$code}' already exists");
            }
        }
    }

    private function createGiftcardFromGraphQl(array $context): GiftCard
    {
        $this->requireAdminOrApiUser('Gift card management requires admin or API access');
        $this->requireApiPermission('giftcards/create');
        $args = $context['args']['input'] ?? [];

        $this->assertValidGiftcard(
            (float) ($args['initialBalance'] ?? 0),
            $args['code'] ?? null,
            null,
        );

        $giftcard = \Mage::getModel('giftcard/giftcard');
        $giftcard->setData([
            'balance' => (float) ($args['initialBalance'] ?? 0),
            'initial_balance' => (float) ($args['initialBalance'] ?? 0),
            'code' => $args['code'] ?? null,
            'recipient_name' => $args['recipientName'] ?? null,
            'recipient_email' => $args['recipientEmail'] ?? null,
            'sender_name' => $args['senderName'] ?? null,
            'sender_email' => $args['senderEmail'] ?? null,
            'message' => $args['message'] ?? null,
            'website_id' => isset($args['websiteId']) ? (int) $args['websiteId'] : null,
            'expires_at' => $args['expiresAt'] ?? null,
        ]);
        $giftcard->save();
        $this->sendEmailToRecipient($giftcard);

        return GiftCard::fromModel($giftcard);
    }

    /**
     * Mirrors the explicit-send pattern used by the purchase flow (Observer)
     * and admin "Send email" button (PrintController). The save-lifecycle hook
     * approach causes recursion via the helper's $giftcard->save() call.
     */
    private function sendEmailToRecipient(\Maho_Giftcard_Model_Giftcard $giftcard): void
    {
        if (!$giftcard->getRecipientEmail() || $giftcard->getEmailSentAt()) {
            return;
        }
        try {
            \Mage::helper('giftcard')->sendGiftcardEmail($giftcard);
        } catch (\Exception $e) {
            \Mage::logException($e);
        }
    }

    private function adjustBalance(array $context): GiftCard
    {
        $this->requireAdminOrApiUser('Gift card management requires admin or API access');
        $this->requireApiPermission('giftcards/write');
        $args = $context['args']['input'] ?? [];

        $code = trim((string) ($args['code'] ?? ''));
        $newBalance = (float) ($args['newBalance'] ?? 0);

        if ($code === '') {
            throw new BadRequestHttpException('Gift card code is required');
        }

        $giftcard = \Mage::getModel('giftcard/giftcard')->loadByCode($code);
        if (!$giftcard->getId()) {
            throw new NotFoundHttpException('Gift card not found');
        }

        $giftcard->adjustBalance($newBalance, $args['comment'] ?? null);

        return GiftCard::fromModel($giftcard);
    }
}
