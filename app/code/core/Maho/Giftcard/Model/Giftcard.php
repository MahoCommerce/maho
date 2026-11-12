<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/**
 * Gift Card Model
 */
class Maho_Giftcard_Model_Giftcard extends Mage_Core_Model_Abstract
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_USED = 'used';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_DISABLED = 'disabled';

    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_USED, self::STATUS_EXPIRED, self::STATUS_DISABLED];

    public const ACTION_CREATED = 'created';
    public const ACTION_USED = 'used';
    public const ACTION_REFUNDED = 'refunded';
    public const ACTION_ADJUSTED = 'adjusted';
    public const ACTION_EXPIRED = 'expired';

    public const XML_PATH_MAX_BALANCE = 'giftcard/general/max_balance';

    private bool $isCreation = false;

    private ?string $creationComment = null;

    #[\Override]
    protected function _construct()
    {
        $this->_init('giftcard/giftcard');
    }

    #[\Override]
    protected function _beforeSave()
    {
        // Mage_Core_Model_Abstract sets _isObjectNew=true on first save and never
        // resets it, so isObjectNew() keeps returning true on subsequent saves. Use
        // !getId() instead so the creation defaults below run exactly once.
        if ($this->getId() && $this->dataHasChangedFor('balance')) {
            throw new LogicException('A save() does not write the balance of a saved gift card. Call adjustBalance(), use(), useUpTo() or refund().');
        }
        if (!$this->getId()) {
            $this->isCreation = true;
            $helper = Mage::helper('giftcard');

            if (!$this->getCode()) {
                $this->setCode($helper->generateCode());
            }

            // Default to the current website so programmatic creations never orphan the card
            if ($this->getData('website_ids') === null) {
                $this->setWebsiteIds([(int) Mage::app()->getStore()->getWebsiteId()]);
            }

            // Explicit null means "never expires"; only default when the field is absent
            if (!$this->hasData('expires_at')) {
                $this->setExpiresAt($helper->calculateExpirationDate());
            }

            // Explicit 0 counts as set (refund cards have balance=0); forms post '' for empty
            $balance = $this->getData('balance');
            $initialBalance = $this->getData('initial_balance');
            $hasBalance = $balance !== null && $balance !== '';
            $hasInitialBalance = $initialBalance !== null && $initialBalance !== '';

            if (!$hasInitialBalance && $hasBalance) {
                $this->setInitialBalance((float) $balance);
            } elseif (!$hasBalance && $hasInitialBalance) {
                $this->setBalance((float) $initialBalance);
            }

            if (!$this->getStatus()) {
                $this->setStatus(self::STATUS_ACTIVE);
            }

            $this->setData('created_at', Mage::app()->getLocale()->formatDateForDb('now'));
        }

        $this->setData('updated_at', Mage::app()->getLocale()->formatDateForDb('now'));

        $helper = Mage::helper('giftcard');
        $errors = new Mage_Core_Exception_Input();
        if (!in_array($this->getStatus(), self::STATUSES, true)) {
            $errors->addError('status', $helper->__('Invalid gift card status; allowed: %s', implode(', ', self::STATUSES)));
        }
        if ((float) $this->getData('balance') < 0) {
            $errors->addError('balance', $helper->__('Gift card balance cannot be negative'));
        }
        // A used card can be created with a balance of zero, but a new card holds money at the start
        if ($this->isCreation && (float) $this->getData('initial_balance') <= 0) {
            $errors->addError('initial_balance', $helper->__('Gift card balance must be greater than zero'));
        }
        // A save writes the balance of a new card only: adjustBalance() checks the limit for an existing card
        $maxBalance = $this->getId() ? null : $this->getMaxBalance();
        if ($maxBalance !== null) {
            $fields = ['initial_balance'];
            if ($this->getData('balance') != $this->getData('initial_balance')) {
                $fields[] = 'balance';
            }
            foreach ($fields as $field) {
                if ((float) $this->getData($field) > $maxBalance) {
                    $errors->addError($field, $helper->__('Gift card balance cannot exceed %s', $maxBalance));
                }
            }
        }
        $errors->throwIfErrors();

        return parent::_beforeSave();
    }

    /**
     * The highest balance that this card can get from an admin, the API or an import, in the base currency, or
     * null for no limit. A purchased card has no limit. A card of several websites gets the lowest limit of them.
     */
    public function getMaxBalance(): ?float
    {
        if ($this->getPurchaseOrderId()) {
            return null;
        }
        $websites = Mage::app()->getWebsites();
        $limits = [];
        foreach ($this->getWebsiteIds() as $websiteId) {
            if (isset($websites[$websiteId])) {
                $limits[] = (float) $websites[$websiteId]->getConfig(self::XML_PATH_MAX_BALANCE);
            }
        }
        if ($limits === []) {
            $limits[] = (float) Mage::getStoreConfig(self::XML_PATH_MAX_BALANCE, Mage_Core_Model_App::ADMIN_STORE_ID);
        }
        $limits = array_filter($limits, fn(float $limit) => $limit > 0);
        return $limits === [] ? null : min($limits);
    }

    /**
     * Load gift card by code
     *
     * @return $this
     */
    public function loadByCode(string $code): self
    {
        $this->_getResource()->loadByCode($this, $code);
        return $this;
    }

    /**
     * First associated website. All associated websites share one base
     * currency (enforced on save), so any is a valid currency source.
     *
     * Throws for a card with no associations: Mage::app()->getWebsite(null)
     * would silently substitute the current context's website, misreporting
     * the currency the balance is denominated in.
     */
    public function getWebsite(): Mage_Core_Model_Website
    {
        $websiteIds = $this->getWebsiteIds();
        if ($websiteIds === []) {
            throw new Mage_Core_Exception(
                Mage::helper('giftcard')->__('Gift card is not associated with any website.'),
            );
        }
        return Mage::app()->getWebsite($websiteIds[0]);
    }

    /**
     * Get currency code (derived from website's base currency)
     */
    public function getCurrencyCode(): string
    {
        return $this->getWebsite()->getBaseCurrencyCode();
    }

    /**
     * Get balance in a specific currency (converts if needed)
     */
    public function getBalance(?string $currencyCode = null): float
    {
        if (!$currencyCode) {
            return (float) $this->getData('balance');
        }

        $balance = $this->getBalanceIn($currencyCode);
        if ($balance === null) {
            Mage::throwException(Mage::helper('giftcard')->__(
                'This gift card is in %s, which cannot be converted to %s.',
                $this->getCurrencyCode(),
                $currencyCode,
            ));
        }

        return $balance;
    }

    /**
     * The balance in another currency, or null when no rate values it.
     *
     * @throws Mage_Core_Exception for a card with no website associations, which prices in no
     *         currency at all; check getWebsiteIds() first
     */
    public function getBalanceIn(?string $currencyCode): ?float
    {
        $balance = (float) $this->getData('balance');

        if ($currencyCode === null || $currencyCode === '' || $currencyCode === $this->getCurrencyCode()) {
            return $balance;
        }

        // Either direction: rate rows are written base to allowed, so the valuing row may go the other way
        $rate = Mage::helper('directory')->getAnyRate((string) $this->getCurrencyCode(), $currencyCode);

        return $rate === null ? null : $balance * $rate;
    }

    /**
     * Check if gift card is valid for use
     */
    public function isValid(): bool
    {
        if ($this->getStatus() !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->getBalance() <= 0) {
            return false;
        }

        // Check expiration (expires_at is stored in UTC)
        if ($this->getExpiresAt()) {
            $now = Mage::app()->getLocale()->storeToUtc();
            $expires = new DateTime($this->getExpiresAt(), new DateTimeZone('UTC'));

            if ($now > $expires) {
                // In-memory only; the cron job persists the flip
                $this->setStatus(self::STATUS_EXPIRED);
                return false;
            }
        }

        return true;
    }

    /**
     * Fails closed: a card with no associated websites is valid nowhere.
     */
    public function isValidForWebsite(int $websiteId): bool
    {
        return $this->isValid() && $this->isAvailableOnWebsite($websiteId);
    }

    /**
     * Pure membership check, without the status/expiry/balance validity gate.
     */
    public function isAvailableOnWebsite(int $websiteId): bool
    {
        return in_array($websiteId, $this->getWebsiteIds(), true);
    }

    /**
     * Canonical form of a website-id set: positive ints, deduplicated,
     * sorted ascending. Matches the order the junction is read back in,
     * so canonical sets compare with a plain ===.
     *
     * @param array<int|string> $websiteIds
     * @return int[]
     */
    public static function canonicalizeWebsiteIds(array $websiteIds): array
    {
        $clean = [];
        foreach ($websiteIds as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $clean[$id] = true;
            }
        }
        $ids = array_keys($clean);
        sort($ids);
        return $ids;
    }

    /**
     * Read cache for the junction rows, keyed by card id. Kept out of the
     * `website_ids` data key: that key means "pending change to persist",
     * so caching a read there would re-sync the junction on every save.
     *
     * @var int[]|null
     */
    private ?array $loadedWebsiteIds = null;
    private ?int $loadedWebsiteIdsCardId = null;

    /**
     * Pending set from setWebsiteIds() when present, otherwise the junction
     * rows. Grid collections hydrate the key as a CSV string; parse it.
     *
     * @return int[]
     */
    public function getWebsiteIds(): array
    {
        $ids = $this->getData('website_ids');
        if (is_string($ids)) {
            $ids = $ids === '' ? [] : explode(',', $ids);
        }
        if ($ids !== null) {
            return self::canonicalizeWebsiteIds((array) $ids);
        }
        $cardId = $this->getId();
        if ($cardId === null || $cardId <= 0) {
            return [];
        }
        if ($this->loadedWebsiteIds === null || $this->loadedWebsiteIdsCardId !== $cardId) {
            /** @var Maho_Giftcard_Model_Resource_Giftcard $resource */
            $resource = $this->getResource();
            $this->loadedWebsiteIds = $resource->getWebsiteIds($cardId);
            $this->loadedWebsiteIdsCardId = $cardId;
        }
        return $this->loadedWebsiteIds;
    }

    /**
     * Persisted to the junction by the resource's _afterSave.
     *
     * @param int[] $websiteIds
     */
    public function setWebsiteIds(array $websiteIds): self
    {
        $this->setData('website_ids', self::canonicalizeWebsiteIds($websiteIds));
        return $this;
    }

    /**
     * Set the comment of the "created" history entry, which the first save of this card writes.
     */
    public function setCreationComment(?string $comment): static
    {
        $this->creationComment = $comment;
        return $this;
    }

    /**
     * Write the "created" history entry of a new card. Drop the pending-change key (already synced by the
     * resource) so a second save of this instance does not re-run the junction sync.
     */
    #[\Override]
    protected function _afterSave()
    {
        if ($this->isCreation) {
            $this->isCreation = false;
            $balance = (float) $this->getData('balance');
            $orderId = $this->getPurchaseOrderId();
            $this->_addHistory(self::ACTION_CREATED, $balance, 0.0, $balance, $orderId ?: null, $this->creationComment);
            // The next save() of this object compares the balance with the stored one
            $this->setOrigData('balance', $this->getData('balance'));
        }
        if (is_array($this->getData('website_ids'))) {
            $this->unsetData('website_ids');
            $this->loadedWebsiteIds = null;
        }
        return parent::_afterSave();
    }

    /**
     * Use gift card for payment
     *
     * @param float $baseAmount Amount to deduct (in base currency)
     * @param int|null $orderId Order ID for history
     * @param string|null $comment Optional comment
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function use(float $baseAmount, ?int $orderId = null, ?string $comment = null): self
    {
        if (!$this->isValid()) {
            throw new Mage_Core_Exception('This gift card is not valid for use.');
        }

        if ($baseAmount <= 0) {
            throw new Mage_Core_Exception('Amount must be greater than zero.');
        }

        $this->_debit($baseAmount, false, $orderId, $comment);

        return $this;
    }

    /**
     * Take $baseAmount from the balance, or the whole balance when it is lower, and return the amount taken.
     *
     * A placed order calls this: the customer already got the discount, so the card gives what it has left.
     */
    public function useUpTo(float $baseAmount, ?int $orderId = null, ?string $comment = null): float
    {
        return $baseAmount > 0 ? $this->_debit($baseAmount, true, $orderId, $comment) : 0.0;
    }

    /**
     * @throws Mage_Core_Exception when $baseAmount exceeds the balance and $upToBalance is false
     */
    protected function _debit(float $baseAmount, bool $upToBalance, ?int $orderId, ?string $comment): float
    {
        $resource = $this->getResource();
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = $resource->getMainTable();

        $resource->beginTransaction();
        try {
            // The row lock stops two requests from spending the same balance
            $balanceBefore = (float) $write->fetchOne(
                $write->select()
                    ->from($table, ['balance'])
                    ->where('giftcard_id = ?', $this->getId())
                    ->forUpdate(),
            );

            if (!$upToBalance && $baseAmount > $balanceBefore) {
                throw new Mage_Core_Exception('Amount exceeds gift card balance.');
            }

            $amount = min($baseAmount, $balanceBefore);
            if ($amount > 0) {
                $balanceAfter = $balanceBefore - $amount;
                $data = [
                    'balance' => $balanceAfter,
                    'updated_at' => Mage::app()->getLocale()->formatDateForDb('now'),
                ];
                if ($balanceAfter <= 0) {
                    $data['status'] = self::STATUS_USED;
                }
                $write->update($table, $data, ['giftcard_id = ?' => $this->getId()]);

                $this->_addHistory(self::ACTION_USED, -$amount, $balanceBefore, $balanceAfter, $orderId, $comment);
            }

            $resource->commit();
        } catch (\Throwable $e) {
            $resource->rollBack();
            throw $e;
        }

        $this->load($this->getId());

        return max(0.0, $amount);
    }

    /**
     * Add $baseAmount to the balance and record the refund in the history.
     *
     * The card becomes active. When the refund expiration extension of $storeId is above 0, a card that
     * expires within that many days gets an expiry date that many days from now. When the extension is 0,
     * an expired card stays expired. A null $storeId reads the extension of the current store.
     *
     * @throws Mage_Core_Exception when $baseAmount is not a positive number
     * @throws Mage_Core_Exception_NoSuchEntity when the card no longer exists
     */
    public function refund(float $baseAmount, ?int $orderId = null, ?string $comment = null, ?int $storeId = null): self
    {
        if (!is_finite($baseAmount) || $baseAmount <= 0) {
            throw new Mage_Core_Exception('Amount must be greater than zero.');
        }

        $resource = $this->getResource();
        $table = $resource->getMainTable();
        $extensionDays = (int) Mage::getStoreConfig('giftcard/general/refund_expiration_extension', $storeId);

        $resource->beginTransaction();
        // Resource reads use its writer while the transaction is open.
        $write = $resource->getReadConnection();
        $savepoint = null;
        try {
            // Adapter nesting counts transactions without creating savepoints.
            if ($write->getTransactionLevel() > 1) {
                $name = 'giftcard_refund_' . $write->getTransactionLevel();
                $write->getConnection()->createSavepoint($name);
                $savepoint = $name;
            }

            // The row lock stops two requests from adding to the same balance
            $stored = $write->fetchRow(
                $write->select()
                    ->from($table, ['balance', 'status', 'expires_at'])
                    ->where('giftcard_id = ?', $this->getId())
                    ->forUpdate(),
            );
            if ($stored === false) {
                throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('giftcard')->__('Gift card not found.'));
            }

            $balanceBefore = (float) $stored['balance'];
            $balanceAfter = $balanceBefore + $baseAmount;
            $data = [
                'balance' => $balanceAfter,
                'updated_at' => Mage::app()->getLocale()->formatDateForDb('now'),
            ];

            $isExpired = $stored['status'] === self::STATUS_EXPIRED;
            if ($extensionDays > 0) {
                $minimumExpiresAt = new DateTimeImmutable('now', new DateTimeZone('UTC'))->modify("+{$extensionDays} days");
                $needsExtension = $stored['expires_at']
                    ? new DateTimeImmutable($stored['expires_at'], new DateTimeZone('UTC')) < $minimumExpiresAt
                    : $isExpired;
                if ($needsExtension) {
                    $data['expires_at'] = $minimumExpiresAt->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
                    $extended = "expiration extended to {$extensionDays} days from now";
                    $comment = $comment === null ? ucfirst($extended) : "{$comment} ({$extended})";
                }
            }

            if (!$isExpired || $extensionDays > 0) {
                $data['status'] = self::STATUS_ACTIVE;
            }

            $write->update($table, $data, ['giftcard_id = ?' => $this->getId()]);

            $this->_addHistory(self::ACTION_REFUNDED, $baseAmount, $balanceBefore, $balanceAfter, $orderId, $comment);

            $this->load($this->getId());
            if ($savepoint !== null) {
                $write->getConnection()->releaseSavepoint($savepoint);
            }
            $resource->commit();
        } catch (\Throwable $e) {
            // After a deadlock or a lock timeout the adapter has rolled back everything, and the savepoint is gone
            if ($savepoint !== null && $write->getTransactionLevel() > 0) {
                $write->getConnection()->rollbackSavepoint($savepoint);
                $write->getConnection()->releaseSavepoint($savepoint);
            }
            $resource->rollBack();
            throw $e;
        }

        return $this;
    }

    /**
     * Set the balance to $newBalance and record the change in the history.
     *
     * A balance of 0 makes the card used, and a positive balance makes a used card active.
     * Give $expectedBalance when the person saw a balance before they typed the new one:
     * the method then refuses to replace a balance that changed since.
     *
     * The card keeps its other unsaved changes, so a save after this method writes them.
     *
     * @throws Mage_Core_Exception_Input when $newBalance is below zero, or above the highest balance and not the stored one
     * @throws Mage_Core_Exception_Conflict when the stored balance is not $expectedBalance
     * @throws Mage_Core_Exception_NoSuchEntity when the card no longer exists
     */
    public function adjustBalance(float $newBalance, ?string $comment = null, ?float $expectedBalance = null): self
    {
        $resource = $this->getResource();
        $table = $resource->getMainTable();

        $resource->beginTransaction();
        try {
            $write = $resource->getReadConnection();
            $stored = $write->fetchRow(
                $write->select()
                    ->from($table, ['balance', 'status'])
                    ->where('giftcard_id = ?', $this->getId())
                    ->forUpdate(),
            );
            if ($stored === false) {
                throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('giftcard')->__('Gift card not found.'));
            }

            $balanceBefore = (float) $stored['balance'];
            if ($expectedBalance !== null && round($balanceBefore - $expectedBalance, 4) !== 0.0) {
                throw new Mage_Core_Exception_Conflict(Mage::helper('giftcard')->__(
                    'The balance of this gift card changed to %s while you edited it. Check the balance, then save again.',
                    Mage::app()->getLocale()->formatCurrency($balanceBefore, $this->getCurrencyCode()),
                ));
            }

            $helper = Mage::helper('giftcard');
            $errors = new Mage_Core_Exception_Input();
            if ($newBalance < 0) {
                $errors->addError('balance', $helper->__('Gift card balance cannot be negative'));
            }
            $maxBalance = $this->getMaxBalance();
            if ($maxBalance !== null && $newBalance > $maxBalance && $newBalance !== $balanceBefore) {
                $errors->addError('balance', $helper->__('Gift card balance cannot exceed %s', $maxBalance));
            }
            $errors->throwIfErrors();

            $data = [
                'balance' => $newBalance,
                'updated_at' => Mage::app()->getLocale()->formatDateForDb('now'),
            ];
            if ($newBalance <= 0) {
                $data['status'] = self::STATUS_USED;
            } elseif ($stored['status'] === self::STATUS_USED) {
                $data['status'] = self::STATUS_ACTIVE;
            }
            $write->update($table, $data, ['giftcard_id = ?' => $this->getId()]);

            $this->_addHistory(self::ACTION_ADJUSTED, $newBalance - $balanceBefore, $balanceBefore, $newBalance, null, $comment);

            // A load() would drop the other changes that Maho_Giftcard_Service_Giftcard::update() saves after this call
            foreach ($data as $key => $value) {
                $this->setData($key, $value)->setOrigData($key, $value);
            }
            $resource->commit();
        } catch (\Throwable $e) {
            $resource->rollBack();
            throw $e;
        }

        return $this;
    }

    /**
     * Add history entry
     */
    protected function _addHistory(
        string $action,
        float $baseAmount,
        float $baseBalanceBefore,
        float $baseBalanceAfter,
        ?int $orderId = null,
        ?string $comment = null,
    ): void {
        $adminUserId = null;
        // Creating the admin session starts a PHP session, so read it only when the request already has it
        $adminSession = Mage::registry('_singleton/admin/session');
        if ($adminSession instanceof Mage_Admin_Model_Session && $adminSession->isLoggedIn()) {
            $adminUserId = $adminSession->getUser()->getId();
        }

        $history = Mage::getModel('giftcard/history');
        $history->setData([
            'giftcard_id' => $this->getId(),
            'action' => $action,
            'base_amount' => (float) $baseAmount,
            'balance_before' => (float) $baseBalanceBefore,
            'balance_after' => (float) $baseBalanceAfter,
            'order_id' => $orderId,
            'comment' => $comment,
            'admin_user_id' => $adminUserId,
            'created_at' => Mage::app()->getLocale()->formatDateForDb('now'),
        ]);
        $history->save();
    }

    /**
     * Get history collection
     *
     * @return Maho_Giftcard_Model_Resource_History_Collection
     */
    public function getHistoryCollection()
    {
        // Two entries of the same second keep the order in which they were written
        return Mage::getResourceModel('giftcard/history_collection')
            ->addFieldToFilter('giftcard_id', $this->getId())
            ->setOrder('created_at', 'DESC')
            ->setOrder('history_id', 'DESC');
    }

    #[\Override]
    public function getId(): ?int
    {
        $value = $this->getData($this->getIdFieldName());
        return $value === null ? null : (int) $value;
    }

    public function getCode(): ?string
    {
        $value = $this->getData('code');
        return $value === null ? null : (string) $value;
    }

    public function setCode(?string $value): static
    {
        return $this->setData('code', $value);
    }

    public function getStatus(): ?string
    {
        $value = $this->getData('status');
        return $value === null ? null : (string) $value;
    }

    public function setStatus(?string $value): static
    {
        return $this->setData('status', $value);
    }

    public function setBalance(?float $value): static
    {
        return $this->setData('balance', $value);
    }

    public function getInitialBalance(): ?float
    {
        $value = $this->getData('initial_balance');
        return $value === null ? null : (float) $value;
    }

    public function setInitialBalance(?float $value): static
    {
        return $this->setData('initial_balance', $value);
    }

    public function getRecipientName(): ?string
    {
        $value = $this->getData('recipient_name');
        return $value === null ? null : (string) $value;
    }

    public function setRecipientName(?string $value): static
    {
        return $this->setData('recipient_name', $value);
    }

    public function getRecipientEmail(): ?string
    {
        $value = $this->getData('recipient_email');
        return $value === null ? null : (string) $value;
    }

    public function setRecipientEmail(?string $value): static
    {
        return $this->setData('recipient_email', $value);
    }

    public function getSenderName(): ?string
    {
        $value = $this->getData('sender_name');
        return $value === null ? null : (string) $value;
    }

    public function setSenderName(?string $value): static
    {
        return $this->setData('sender_name', $value);
    }

    public function getSenderEmail(): ?string
    {
        $value = $this->getData('sender_email');
        return $value === null ? null : (string) $value;
    }

    public function setSenderEmail(?string $value): static
    {
        return $this->setData('sender_email', $value);
    }

    public function getMessage(): ?string
    {
        $value = $this->getData('message');
        return $value === null ? null : (string) $value;
    }

    public function setMessage(?string $value): static
    {
        return $this->setData('message', $value);
    }

    public function getPurchaseOrderId(): ?int
    {
        $value = $this->getData('purchase_order_id');
        return $value === null ? null : (int) $value;
    }

    public function setPurchaseOrderId(?int $value): static
    {
        return $this->setData('purchase_order_id', $value);
    }

    public function getPurchaseOrderItemId(): ?int
    {
        $value = $this->getData('purchase_order_item_id');
        return $value === null ? null : (int) $value;
    }

    public function setPurchaseOrderItemId(?int $value): static
    {
        return $this->setData('purchase_order_item_id', $value);
    }

    public function getExpiresAt(): ?string
    {
        $value = $this->getData('expires_at');
        return $value === null ? null : (string) $value;
    }

    public function setExpiresAt(?string $value): static
    {
        return $this->setData('expires_at', $value);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }
}
