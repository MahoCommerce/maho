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
            $count += $this->deleteVisits($subject->customerId);
            foreach (['log/customer', 'log/visitor_online'] as $alias) {
                $table = $this->getTable($alias);
                if ($adapter->isTableExists($table)) {
                    $count += $adapter->delete($table, ['customer_id = ?' => $subject->customerId]);
                }
            }
            // Subtype 0 is an event of a customer, subtype 1 an event of a visitor
            $count += $adapter->delete($this->getTable('reports/event'), ['subject_id = ?' => $subject->customerId, 'subtype = 0']);
        }
        $count += $adapter->delete(
            $this->getTable('customer/flowpassword'),
            [(string) $adapter->getCaseInsensitiveEqualsSql('email', '?') => $subject->email],
        );
        $count += $this->deleteEmailLogs($subject->email);
        return Mage::helper('privacycenter')->__('%d log entries deleted', $count);
    }

    /**
     * Delete the visits that log_customer links to $customerId: the IP address, the browser and the pages of each
     * visit. Run it before the delete of log_customer, which holds the only link.
     */
    protected function deleteVisits(int $customerId): int
    {
        $adapter = $this->getConnection();
        $customerTable = $this->getTable('log/customer');
        if (!$adapter->isTableExists($customerTable)) {
            return 0;
        }
        $visitorIds = array_map(intval(...), $adapter->fetchCol(
            $adapter->select()->from($customerTable, ['visitor_id'])->where('customer_id = ?', $customerId),
        ));
        if ($visitorIds === []) {
            return 0;
        }
        $count = 0;
        foreach (['log/url_table', 'log/visitor_info', 'log/quote_table', 'log/visitor'] as $alias) {
            $count += $adapter->delete($this->getTable($alias), ['visitor_id IN (?)' => $visitorIds]);
        }
        return $count;
    }

    /**
     * Delete the logged emails that have $email in the To, Cc or Bcc field. The log writes each field as a list of
     * "address" or "Name <address>", with a comma between two recipients.
     */
    protected function deleteEmailLogs(#[\SensitiveParameter]
        string $email): int
    {
        $adapter = $this->getConnection();
        $table = $this->getTable('core/email_log');
        $email = mb_strtolower($email);
        $like = Mage::getResourceHelper('core')->addLikeEscape($email, ['position' => 'any']);
        $columns = ['email_to', 'email_cc', 'email_bcc'];
        $select = $adapter->select()->from($table, ['log_id', ...$columns]);
        foreach ($columns as $column) {
            $select->orWhere("LOWER({$column}) LIKE ?", $like);
        }

        $logIds = [];
        foreach ($adapter->fetchAll($select) as $row) {
            foreach ($columns as $column) {
                foreach (explode(',', (string) $row[$column]) as $recipient) {
                    $address = preg_match('/<([^>]*)>\s*$/', $recipient, $match) ? $match[1] : $recipient;
                    if (mb_strtolower(trim($address)) === $email) {
                        $logIds[] = (int) $row['log_id'];
                        continue 3;
                    }
                }
            }
        }
        return $logIds === [] ? 0 : $adapter->delete($table, ['log_id IN (?)' => $logIds]);
    }
}
