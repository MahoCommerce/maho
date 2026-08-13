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
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class GiftCardProcessor extends \Maho\ApiPlatform\CrudProcessor
{
    private const MAX_BALANCE = 10000;

    private const STATUSES = [
        \Maho_Giftcard_Model_Giftcard::STATUS_ACTIVE,
        \Maho_Giftcard_Model_Giftcard::STATUS_USED,
        \Maho_Giftcard_Model_Giftcard::STATUS_EXPIRED,
        \Maho_Giftcard_Model_Giftcard::STATUS_DISABLED,
    ];

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

    /**
     * Default omitted websiteIds to the current website, then enforce the
     * token's website scope on the resolved set.
     */
    #[\Override]
    protected function beforeSave(object $model, CrudResource $data, ApiUser $user): void
    {
        if (!$model->getId() && $model->getData('website_ids') === null) {
            $model->setWebsiteIds([(int) StoreContext::getStore()->getWebsiteId()]);
        } elseif (is_array($model->getData('website_ids'))) {
            // The DTO mapper writes the raw client array; route it through the
            // setter so duplicates and non-positive ids never reach the junction
            $model->setWebsiteIds($model->getData('website_ids'));
        }
        if ($model->getId() && $this->allowedWebsiteIds($user) !== null) {
            // The stored set must be in scope too, or a restricted token
            // could claim a foreign card by rewriting its websiteIds
            $this->assertAllWebsitesAllowed(
                $model->getResource()->getWebsiteIds((int) $model->getId()),
                $user,
                'gift card',
            );
        }
        $this->assertAllWebsitesAllowed($model->getWebsiteIds(), $user, 'gift card');
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
        $balance = $data->initialBalance ?: (float) ($data->balance ?? 0.0);
        $this->assertValidGiftcard(
            $balance,
            ($data->code !== null && $data->code !== '') ? $data->code : null,
            $model->getId() ? (int) $model->getId() : null,
        );
        // balance is written independently of initialBalance, so it needs its own
        // bounds check or a create could smuggle a balance past the limit above.
        if ($data->balance !== null) {
            $this->assertBalanceBounds((float) $data->balance);
        }
        $this->assertValidStatus($data->status);
        if ($data->websiteIds === []) {
            // 400 here; the resource model's rejection would surface as a 500
            throw new BadRequestHttpException('A gift card must be associated with at least one website');
        }
        foreach ($data->websiteIds ?? [] as $websiteId) {
            $this->assertKnownWebsite((int) $websiteId);
        }
    }

    private function assertKnownWebsite(int $websiteId): void
    {
        // id 0 loads the admin website, which no storefront can redeem on
        if ($websiteId <= 0) {
            throw new BadRequestHttpException("Unknown website id {$websiteId}");
        }
        try {
            \Mage::app()->getWebsite($websiteId);
        } catch (\Throwable) {
            throw new BadRequestHttpException("Unknown website id {$websiteId}");
        }
    }

    private function assertValidStatus(?string $status): void
    {
        if ($status !== null && !in_array($status, self::STATUSES, true)) {
            throw new BadRequestHttpException('Invalid gift card status; allowed: ' . implode(', ', self::STATUSES));
        }
    }

    /**
     * Handles exactly the two admin actions: a status change and a balance
     * adjustment (via adjustBalance(), which writes a history row).
     */
    #[\Override]
    protected function processUpdate(int $id, mixed $data, ApiUser $user): mixed
    {
        if (!$data instanceof GiftCard) {
            return parent::processUpdate($id, $data, $user);
        }

        /** @var \Maho_Giftcard_Model_Giftcard $model */
        $model = $this->loadOrFail($this->modelAlias, $id, 'Gift card not found');
        $this->assertAllWebsitesAllowed($model->getWebsiteIds(), $user, 'gift card');
        $oldData = $model->getData();

        $this->assertValidStatus($data->status);

        // Only status and balance are updatable; ignoring websiteIds would fake
        // a re-scope. A read-modify-write PUT echoes the stored set back, so
        // only an actual change is rejected.
        if ($data->websiteIds !== null) {
            $requested = array_values(array_unique(array_map(intval(...), $data->websiteIds)));
            sort($requested);
            if ($requested !== $model->getWebsiteIds()) {
                throw new BadRequestHttpException('websiteIds cannot be changed through update');
            }
        }

        if ($data->balance !== null) {
            $newBalance = (float) $data->balance;
            $this->assertBalanceBounds($newBalance);
            if ($newBalance !== (float) $model->getBalance()) {
                $model->adjustBalance($newBalance, $data->comment ?? 'API balance adjustment');
            }
        }

        // Explicit status wins over the status adjustBalance() derives from the amount.
        if ($data->status !== null && $data->status !== $model->getStatus()) {
            $model->setStatus($data->status);
            $this->safeSave($model, "update {$this->entityLabel}");
        }

        $this->logApiActivity($this->entityType, 'update', $oldData, $model, $user);

        // Not buildResponse(): the afterSave() hook there sends the recipient
        // email, which a status/balance update must never trigger early.
        $reloaded = \Mage::getModel($this->modelAlias)->load($model->getId());
        return GiftCard::fromModel($reloaded->getId() ? $reloaded : $model);
    }

    /** Bounds every writable balance must satisfy, on create and on adjustment alike. */
    private function assertBalanceBounds(float $balance): void
    {
        if ($balance < 0) {
            throw new BadRequestHttpException('Gift card balance cannot be negative');
        }
        if ($balance > self::MAX_BALANCE) {
            throw new BadRequestHttpException('Gift card balance cannot exceed ' . self::MAX_BALANCE);
        }
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
        $args = $context['args']['input'] ?? [];

        $this->assertValidGiftcard(
            (float) ($args['initialBalance'] ?? 0),
            $args['code'] ?? null,
            null,
        );

        // Same defaulting, empty-set rejection, and token scoping as the REST create path
        if (($args['websiteIds'] ?? null) === []) {
            throw new BadRequestHttpException('A gift card must be associated with at least one website');
        }
        $websiteIds = isset($args['websiteIds'])
            ? array_map(intval(...), (array) $args['websiteIds'])
            : [(int) StoreContext::getStore()->getWebsiteId()];
        foreach ($websiteIds as $websiteId) {
            $this->assertKnownWebsite($websiteId);
        }
        $this->assertAllWebsitesAllowed($websiteIds, $this->requireUser(), 'gift card');

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
            'expires_at' => $args['expiresAt'] ?? null,
        ]);
        $giftcard->setWebsiteIds($websiteIds);
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

        $this->assertAllWebsitesAllowed($giftcard->getWebsiteIds(), $this->requireUser(), 'gift card');

        $this->assertBalanceBounds($newBalance);
        $giftcard->adjustBalance($newBalance, $args['comment'] ?? null);

        return GiftCard::fromModel($giftcard);
    }
}
