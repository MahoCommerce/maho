<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * @method string getSubject()
 * @method $this setSubject(string $value)
 * @method string getEmailTo()
 * @method $this setEmailTo(string $value)
 * @method string getEmailFrom()
 * @method $this setEmailFrom(string $value)
 * @method string|null getEmailCc()
 * @method $this setEmailCc(?string $value)
 * @method string|null getEmailBcc()
 * @method $this setEmailBcc(?string $value)
 * @method string|null getTemplate()
 * @method $this setTemplate(?string $value)
 * @method string getContentType()
 * @method $this setContentType(string $value)
 * @method string getEmailBody()
 * @method $this setEmailBody(string $value)
 * @method string getStatus()
 * @method $this setStatus(string $value)
 * @method string|null getErrorMessage()
 * @method $this setErrorMessage(?string $value)
 * @method string getCreatedAt()
 * @method $this setCreatedAt(string $value)
 */
class Mage_Core_Model_Email_Log extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('core/email_log');
    }

    /**
     * Cron job: clean old log entries
     */
    #[Maho\Config\CronJob('0 2 * * *', name: 'core_email_log_clean')]
    public function cleanOldLogs(): void
    {
        $days = (int) Mage::getStoreConfig('system/smtp/log_clean_after_days');
        if ($days <= 0) {
            $days = 30;
        }

        $cutoff = new \DateTimeImmutable("-{$days} days", new \DateTimeZone('UTC'));

        $resource = $this->getResource();
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $connection->delete(
            $resource->getMainTable(),
            ['created_at < ?' => $cutoff->format('Y-m-d H:i:s')],
        );
    }
}
