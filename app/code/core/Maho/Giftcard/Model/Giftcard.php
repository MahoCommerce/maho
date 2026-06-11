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
 * @method int getWebsiteId()
 * @method $this setWebsiteId(int $value)
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

            if (!$this->getWebsiteId()) {
                $this->setWebsiteId((int) Mage::app()->getStore()->getWebsiteId());
            }

            // Only fill a default when the field wasn't provided. Explicit null
            // means "never expires" — both the admin form note ("Leave empty
            // for no expiration") and API callers depend on that semantic.
            if (!$this->hasData('expires_at')) {
                $this->setExpiresAt($helper->calculateExpirationDate());
            }

            // Mirror one field to the other when only one is provided, but treat
            // an explicit 0 as set — a fully-used card created for a refund has
            // balance=0 and must not be overwritten with initial_balance. Form
            // posts surface unfilled fields as '' (not null), so check both.
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
     * Get website
     */
    public function getWebsite(): Mage_Core_Model_Website
    {
        return Mage::app()->getWebsite($this->getWebsiteId());
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

        // Convert to requested currency
        return (float) Mage::helper('directory')->currencyConvert(
            $balance,
            $this->getCurrencyCode(),
            $currencyCode,
        );
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
                $this->setStatus(self::STATUS_EXPIRED)->save();
                return false;
            }
        }

        return true;
    }

    /**
     * Check if gift card is valid for use on a specific website
     */
    public function isValidForWebsite(int $websiteId): bool
    {
        if (!$this->isValid()) {
            return false;
        }

        return (int) $this->getWebsiteId() === $websiteId;
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
