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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GiftCardProcessor extends \Maho\ApiPlatform\CrudProcessor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        return match ($operation->getName()) {
            'createGiftcard' => $this->createGiftcardFromGraphQl($context),
            'adjustGiftcardBalance' => $this->adjustBalance($context),
            default => parent::process($data, $operation, $uriVariables, $context),
        };
    }

    #[\Override]
    protected function afterSave(object $model, CrudResource $data): void
    {
        $this->sendEmailToRecipient($model);
    }

    private function createGiftcardFromGraphQl(array $context): GiftCard
    {
        $this->requireAdminOrApiUser('Gift card management requires admin or API access');
        $args = $context['args']['input'] ?? [];

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
