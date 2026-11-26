<?php

/**
 * Deletes the logs that name the customer or the email: visits, reports, password reset requests and sent emails.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_PrivacyCenter_Model_Eraser_Logs extends Maho_PrivacyCenter_Model_Eraser_AbstractEraser
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('privacycenter')->__('Visit and email logs');
    }

    #[\Override]
    public function erase(Subject $subject): string
    {
        $adapter = $this->getConnection();
        $count = 0;
        if (!$subject->isGuest()) {
            foreach (['log/customer', 'log/visitor_online'] as $alias) {
                $table = $this->getTable($alias);
                if ($adapter->isTableExists($table)) {
                    $count += $adapter->delete($table, ['customer_id = ?' => $subject->customerId]);
                }
            }
            // Subtype 0 is an event of a customer, subtype 1 an event of a visitor
            $count += $adapter->delete($this->getTable('reports/event'), ['subject_id = ?' => $subject->customerId, 'subtype = 0']);
        }
        $count += $adapter->delete($this->getTable('customer/flowpassword'), ['email = ?' => $subject->email]);
        $count += $adapter->delete($this->getTable('core/email_log'), ['email_to = ?' => $subject->email]);
        return Mage::helper('privacycenter')->__('%d log entries deleted', $count);
    }
}
