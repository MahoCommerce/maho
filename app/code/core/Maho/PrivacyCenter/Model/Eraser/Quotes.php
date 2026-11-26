<?php

/**
 * Deletes the carts of the subject. An order keeps its own copy of the data of its cart.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_PrivacyCenter_Model_Eraser_Quotes extends Maho_PrivacyCenter_Model_Eraser_AbstractEraser
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('privacycenter')->__('Carts');
    }

    #[\Override]
    public function erase(Subject $subject): string
    {
        $adapter = $this->getConnection();
        $where = $subject->isGuest()
            ? ['customer_email = ?' => $subject->email, 'customer_id IS NULL']
            : [$adapter->quoteInto('customer_id = ?', $subject->customerId) . ' OR ' . $adapter->quoteInto('customer_email = ?', $subject->email)];
        $count = $adapter->delete($this->getTable('sales/quote'), $where);
        return Mage::helper('privacycenter')->__('%d carts deleted', $count);
    }
}
