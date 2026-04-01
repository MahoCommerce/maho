<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Pos
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 *
 * @method int getOrderId()
 * @method $this setOrderId(int $value)
 * @method int|null getRegisterId()
 * @method $this setRegisterId(int|string $value)
 * @method string getMethodCode()
 * @method $this setMethodCode(string $value)
 * @method float getAmount()
 * @method $this setAmount(float $value)
 * @method float getBaseAmount()
 * @method $this setBaseAmount(float $value)
 * @method string getCurrencyCode()
 * @method $this setCurrencyCode(string $value)
 * @method string getStatus()
 * @method $this setStatus(string $value)
 * @method string|null getCardType()
 * @method $this setCardType(string $value)
 * @method string|null getCardLast4()
 * @method $this setCardLast4(string $value)
 * @method string|null getAuthCode()
 * @method $this setAuthCode(string $value)
 * @method string|null getTransactionId()
 * @method $this setTransactionId(string $value)
 * @method string|null getTerminalId()
 * @method $this setTerminalId(string $value)
 * @method string|null getReceiptData()
 * @method $this setReceiptData(string $value)
 * @method string getCreatedAt()
 */
class Maho_Pos_Model_Payment extends Mage_Core_Model_Abstract
{
    public const STATUS_CAPTURED = 'captured';
    public const STATUS_PENDING = 'pending';
    public const STATUS_VOIDED = 'voided';
    public const STATUS_REFUNDED = 'refunded';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('maho_pos/payment');
    }

    /**
     * Get available POS payment methods
     *
     * @return array<string, string>
     */
    public static function getPaymentMethods(): array
    {
        return [
            'cashondelivery' => 'Cash',
            'purchaseorder' => 'EFTPOS/Card',
            'free' => 'Gift Card',
            'maho_pos_split' => 'Split Payment',
        ];
    }
}
