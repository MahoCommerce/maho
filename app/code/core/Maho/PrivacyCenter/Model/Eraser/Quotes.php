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
        // A guest cart holds customer_id NULL or 0, as Mage_Sales_Model_Quote::loadByCustomer() says
        $count = $adapter->delete($this->getTable('sales/quote'), [
            $subject->getRowsCondition($adapter, 'customer_id', 'customer_email', [null, 0]) => $subject->email,
        ]);
        return Mage::helper('privacycenter')->__('%d carts deleted', $count);
    }
}
