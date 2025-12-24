<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
            $now = Mage::app()->getLocale()->utcDate(null, null, true);
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

        if ($baseAmount > $this->getBalance()) {
            throw new Mage_Core_Exception('Amount exceeds gift card balance.');
        }

        $balanceBefore = (float) $this->getBalance();
        $balanceAfter = $balanceBefore - $baseAmount;

        // Update balance
        $this->setBalance($balanceAfter);

        // Update status if fully used
        if ($balanceAfter <= 0) {
            $this->setStatus(self::STATUS_USED);
        }

        $this->save();

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
            'created_at' => Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT),
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
