<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Queue
 */

declare(strict_types=1);

use Maho\Queue\Transport\DbTransport;

/**
 * Grid-backing model over the maho_queue_message table. Rows are written by
 * Maho\Queue\Transport\DbTransport, never through this model.
 *
 * @method string getQueue()
 * @method string getStatus()
 * @method string getMessageClass()
 * @method string getBody()
 * @method ?string getErrorMessage()
 * @method int getRetries()
 * @method string getAvailableAt()
 * @method ?string getClaimedAt()
 * @method ?string getProcessedAt()
 * @method string getCreatedAt()
 * @method string getUpdatedAt()
 */
class Maho_Queue_Model_Message extends Mage_Core_Model_Abstract
{
    public const STATUS_PENDING = DbTransport::STATUS_PENDING;
    public const STATUS_PROCESSING = DbTransport::STATUS_PROCESSING;
    public const STATUS_FAILED = DbTransport::STATUS_FAILED;
    public const STATUS_COMPLETED = DbTransport::STATUS_COMPLETED;

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('queue/message');
    }

    /**
     * @return array<string, string>
     */
    public static function getStatusOptions(): array
    {
        $helper = Mage::helper('queue');

        return [
            self::STATUS_PENDING => $helper->__('Pending'),
            self::STATUS_PROCESSING => $helper->__('Processing'),
            self::STATUS_FAILED => $helper->__('Failed'),
            self::STATUS_COMPLETED => $helper->__('Completed'),
        ];
    }
}
