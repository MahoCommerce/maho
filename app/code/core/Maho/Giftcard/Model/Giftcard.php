<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

/**
 * Gift Card Model
 *
 * @method string getCode()
 * @method $this setCode(string $value)
 * @method string getStatus()
 * @method $this setStatus(string $value)
 * @method $this setBalance(float $value)
 * @method float getInitialBalance()
 * @method $this setInitialBalance(float $value)
 * @method string getRecipientName()
 * @method $this setRecipientName(string $value)
 * @method string getRecipientEmail()
 * @method $this setRecipientEmail(string $value)
 * @method string getSenderName()
 * @method $this setSenderName(string $value)
 * @method string getSenderEmail()
 * @method $this setSenderEmail(string $value)
 * @method string getMessage()
 * @method $this setMessage(string $value)
 * @method int getPurchaseOrderId()
 * @method $this setPurchaseOrderId(int $value)
 * @method int getPurchaseOrderItemId()
 * @method $this setPurchaseOrderItemId(int $value)
 * @method string getExpiresAt()
 * @method $this setExpiresAt(string $value)
 * @method string getCreatedAt()
 * @method string getUpdatedAt()
 */
class Maho_Giftcard_Model_Giftcard extends Mage_Core_Model_Abstract
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_USED = 'used';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_DISABLED = 'disabled';

    public const ACTION_CREATED = 'created';
    public const ACTION_USED = 'used';
    public const ACTION_REFUNDED = 'refunded';
    public const ACTION_ADJUSTED = 'adjusted';
    public const ACTION_EXPIRED = 'expired';

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
        if (!$this->getId()) {
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

        return parent::_beforeSave();
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
        $balance = (float) $this->getData('balance');

        if (!$currencyCode) {
            return $balance;
        }

        if ($currencyCode === $this->getCurrencyCode()) {
            return $balance;
        }

        $converted = Mage::helper('directory')->convert($balance, (string) $this->getCurrencyCode(), $currencyCode);
        if ($converted === null) {
            Mage::throwException(Mage::helper('giftcard')->__(
                'This gift card is in %s, which cannot be converted to %s.',
                $this->getCurrencyCode(),
                $currencyCode,
            ));
        }

        return $converted;
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
        $cardId = (int) $this->getId();
        if ($cardId <= 0) {
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
     * Drop the pending-change key (already synced by the resource) so a
     * second save of this instance does not re-run the junction sync.
     */
    #[\Override]
    protected function _afterSave()
    {
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

        // Optimistic pre-check so callers get a clear "exceeds balance"
        // message when the model state is fresh. The atomic UPDATE below is
        // still the source of truth and will reject concurrent
        // double-decrements that race past this check.
        if ($baseAmount > $this->getBalance()) {
            throw new Mage_Core_Exception('Amount exceeds gift card balance.');
        }

        $resource = $this->getResource();
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = $resource->getMainTable();

        // Atomic decrement: WHERE balance >= ? guarantees no double-spend
        // when two carts try to consume the same card concurrently. Without
        // this, the read-modify-write sequence can pass `> 0` checks in two
        // requests and let both decrement off the same balance.
        $rowsAffected = $write->update(
            $table,
            [
                'balance' => new \Maho\Db\Expr('balance - ' . $write->quote($baseAmount)),
                'status' => new \Maho\Db\Expr(
                    'CASE WHEN balance - ' . $write->quote($baseAmount) . ' <= 0 '
                    . 'THEN ' . $write->quote(self::STATUS_USED) . ' '
                    . 'ELSE status END',
                ),
            ],
            [
                'giftcard_id = ?' => (int) $this->getId(),
                'balance >= ?' => $baseAmount,
            ],
        );

        if ($rowsAffected === 0) {
            throw new Mage_Core_Exception('Gift card balance changed concurrently or is insufficient.');
        }

        // Refresh model state from the row we just mutated
        $balanceBefore = (float) $this->getBalance();
        $this->load((int) $this->getId());
        $balanceAfter = (float) $this->getBalance();

        // Record history
        $this->_addHistory(
            self::ACTION_USED,
            -$baseAmount,
            $balanceBefore,
            $balanceAfter,
            $orderId,
            $comment,
        );

        return $this;
    }

    /**
     * Refund amount back to gift card
     *
     * @param float $baseAmount Amount to refund (in base currency)
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function refund(float $baseAmount, ?int $orderId = null, ?string $comment = null): self
    {
        if ($baseAmount <= 0) {
            throw new Mage_Core_Exception('Amount must be greater than zero.');
        }

        $balanceBefore = (float) $this->getBalance();
        $balanceAfter = $balanceBefore + $baseAmount;

        // Update balance
        $this->setBalance($balanceAfter);

        // Reactivate if was used
        if ($this->getStatus() === self::STATUS_USED) {
            $this->setStatus(self::STATUS_ACTIVE);
        }

        $this->save();

        // Record history
        $this->_addHistory(
            self::ACTION_REFUNDED,
            $baseAmount,
            $balanceBefore,
            $balanceAfter,
            $orderId,
            $comment,
        );

        return $this;
    }

    /**
     * Adjust balance (admin action)
     *
     * @param float $newBalance New balance (in base currency)
     * @return $this
     */
    public function adjustBalance(float $newBalance, ?string $comment = null): self
    {
        $balanceBefore = (float) $this->getBalance();
        $amount = $newBalance - $balanceBefore;

        $this->setBalance($newBalance);

        // Update status based on new balance
        if ($newBalance <= 0) {
            $this->setStatus(self::STATUS_USED);
        } elseif ($this->getStatus() === self::STATUS_USED) {
            $this->setStatus(self::STATUS_ACTIVE);
        }

        $this->save();

        // Record history
        $this->_addHistory(
            self::ACTION_ADJUSTED,
            $amount,
            $balanceBefore,
            $newBalance,
            null,
            $comment,
        );

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
        $adminSession = Mage::getSingleton('admin/session');
        if ($adminSession->isLoggedIn()) {
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
        return Mage::getResourceModel('giftcard/history_collection')
            ->addFieldToFilter('giftcard_id', $this->getId())
            ->setOrder('created_at', 'DESC');
    }
}
