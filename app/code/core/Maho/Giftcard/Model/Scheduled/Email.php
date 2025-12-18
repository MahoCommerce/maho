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
 * Scheduled Email Model
 *
 * @method int getScheduledEmailId()
 * @method $this setScheduledEmailId(int $value)
 * @method int getGiftcardId()
 * @method $this setGiftcardId(int $value)
 * @method string getRecipientEmail()
 * @method $this setRecipientEmail(string $value)
 * @method string getRecipientName()
 * @method $this setRecipientName(string $value)
 * @method string getScheduledAt()
 * @method $this setScheduledAt(string $value)
 * @method string getStatus()
 * @method $this setStatus(string $value)
 * @method string getSentAt()
 * @method $this setSentAt(string $value)
 * @method string getErrorMessage()
 * @method $this setErrorMessage(string $value)
 * @method string getCreatedAt()
 * @method string getUpdatedAt()
 */
class Maho_Giftcard_Model_Scheduled_Email extends Mage_Core_Model_Abstract
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    #[\Override]
    protected function _construct()
    {
        $this->_init('giftcard/scheduled_email');
    }

    /**
     * Process scheduled email
     */
    public function process(): bool
    {
        if ($this->getStatus() !== self::STATUS_PENDING) {
            return false;
        }

        try {
            $giftcard = Mage::getModel('giftcard/giftcard')->load($this->getGiftcardId());

            if (!$giftcard->getId()) {
                throw new Mage_Core_Exception('Gift card not found.');
            }

            // Send the email
            Mage::helper('giftcard')->sendGiftcardEmail($giftcard);

            // Mark as sent
            $this->setStatus(self::STATUS_SENT);
            $this->setSentAt(date('Y-m-d H:i:s'));
            $this->save();

            return true;
        } catch (Exception $e) {
            // Mark as failed
            $this->setStatus(self::STATUS_FAILED);
            $this->setErrorMessage($e->getMessage());
            $this->save();

            Mage::logException($e);

            return false;
        }
    }
}
