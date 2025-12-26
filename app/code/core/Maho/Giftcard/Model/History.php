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
 * Gift Card History Model
 *
 * @method int getGiftcardId()
 * @method $this setGiftcardId(int $value)
 * @method string getAction()
 * @method $this setAction(string $value)
 * @method float getBaseAmount()
 * @method $this setBaseAmount(float $value)
 * @method float getBalanceBefore()
 * @method $this setBalanceBefore(float $value)
 * @method float getBalanceAfter()
 * @method $this setBalanceAfter(float $value)
 * @method int getOrderId()
 * @method $this setOrderId(int $value)
 * @method int getAdminUserId()
 * @method $this setAdminUserId(int $value)
 * @method string getComment()
 * @method $this setComment(string $value)
 * @method string getCreatedAt()
 */
class Maho_Giftcard_Model_History extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('giftcard/history');
    }

    /**
     * Get gift card
     *
     * @return Maho_Giftcard_Model_Giftcard
     */
    public function getGiftcard()
    {
        return Mage::getModel('giftcard/giftcard')->load($this->getGiftcardId());
    }

    /**
     * Get order
     *
     * @return Mage_Sales_Model_Order|null
     */
    public function getOrder()
    {
        if (!$this->getOrderId()) {
            return null;
        }

        return Mage::getModel('sales/order')->load($this->getOrderId());
    }
}
