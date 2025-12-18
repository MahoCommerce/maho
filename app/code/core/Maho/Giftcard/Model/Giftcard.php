<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Gift Card Model
 *
 * @method string getCode()
 * @method $this setCode(string $value)
 * @method string getStatus()
 * @method $this setStatus(string $value)
 * @method float getBalance()
 * @method $this setBalance(float $value)
 * @method float getInitialBalance()
 * @method $this setInitialBalance(float $value)
 * @method string getCurrencyCode()
 * @method $this setCurrencyCode(string $value)
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
    public const STATUS_PENDING = 'pending';
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
        $this->_init('maho_giftcard/giftcard');
    }

    /**
     * Load gift card by code
     *
     * @param string $code
     * @return $this
     */
    public function loadByCode(string $code): self
    {
        $this->_getResource()->loadByCode($this, $code);
        return $this;
    }

    /**
     * Check if gift card is valid for use
     *
     * @return bool
     */
    public function isValid(): bool
    {
        if ($this->getStatus() !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->getBalance() <= 0) {
            return false;
        }

        // Check expiration
        if ($this->getExpiresAt()) {
            $now = new DateTime();
            $expires = new DateTime($this->getExpiresAt());

            if ($now > $expires) {
                $this->setStatus(self::STATUS_EXPIRED)->save();
                return false;
            }
        }

        return true;
    }

    /**
     * Use gift card for payment
     *
     * @param float $amount Amount to deduct
     * @param int|null $orderId Order ID for history
     * @param string|null $comment Optional comment
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function use(float $amount, ?int $orderId = null, ?string $comment = null): self
    {
        if (!$this->isValid()) {
            throw new Mage_Core_Exception('This gift card is not valid for use.');
        }

        if ($amount <= 0) {
            throw new Mage_Core_Exception('Amount must be greater than zero.');
        }

        if ($amount > $this->getBalance()) {
            throw new Mage_Core_Exception('Amount exceeds gift card balance.');
        }

        $balanceBefore = (float) $this->getBalance();
        $balanceAfter = $balanceBefore - $amount;

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
            -$amount,
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
     * @param float $amount
     * @param int|null $orderId
     * @param string|null $comment
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function refund(float $amount, ?int $orderId = null, ?string $comment = null): self
    {
        if ($amount <= 0) {
            throw new Mage_Core_Exception('Amount must be greater than zero.');
        }

        $balanceBefore = (float) $this->getBalance();
        $balanceAfter = $balanceBefore + $amount;

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
            $amount,
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
     * @param float $newBalance
     * @param string|null $comment
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
     *
     * @param string $action
     * @param float $amount
     * @param float $balanceBefore
     * @param float $balanceAfter
     * @param int|null $orderId
     * @param string|null $comment
     * @return void
     */
    protected function _addHistory(
        string $action,
        float $amount,
        float $balanceBefore,
        float $balanceAfter,
        ?int $orderId = null,
        ?string $comment = null,
    ): void {
        $history = Mage::getModel('maho_giftcard/history');
        $history->setData([
            'giftcard_id' => $this->getId(),
            'action' => $action,
            'amount' => (float) $amount,
            'balance_before' => (float) $balanceBefore,
            'balance_after' => (float) $balanceAfter,
            'order_id' => $orderId,
            'comment' => $comment,
            'admin_user_id' => Mage::getSingleton('admin/session')->getUser()?->getId(),
            'created_at' => date('Y-m-d H:i:s'),
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
        return Mage::getResourceModel('maho_giftcard/history_collection')
            ->addFieldToFilter('giftcard_id', $this->getId())
            ->setOrder('created_at', 'DESC');
    }
}
