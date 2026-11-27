<?php

/**
 * Removes from the orders of the subject the personal data that no law requires after the sale. The order keeps the
 * data of the invoice and of the shipment: the names, the email, the addresses, the phone and the VAT number. It also
 * keeps the custom customer attributes, because a store can use one for invoice data, such as a fiscal code.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_PrivacyCenter_Model_Eraser_Orders extends Maho_PrivacyCenter_Model_Eraser_AbstractEraser
{
    public const array CLEARED_COLUMNS = ['remote_ip', 'x_forwarded_for', 'customer_dob', 'customer_gender'];

    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('privacycenter')->__('The IP address, the birth date and the gender on your orders');
    }

    #[\Override]
    public function erase(Subject $subject): string
    {
        $helper = Mage::helper('privacycenter');
        $adapter = $this->getConnection();
        $orderIds = $subject->getOrderIds();
        if ($orderIds === []) {
            return $helper->__('No orders');
        }

        $adapter->update(
            $this->getTable('sales/order'),
            array_fill_keys(self::CLEARED_COLUMNS, null),
            ['entity_id IN (?)' => $orderIds],
        );
        // The foreign key of the account clears customer_id on the order only
        $adapter->update(
            $this->getTable('sales/order_address'),
            ['customer_id' => null, 'customer_address_id' => null],
            ['parent_id IN (?)' => $orderIds],
        );
        $adapter->update($this->getTable('sales/shipment'), ['customer_id' => null], ['order_id IN (?)' => $orderIds]);

        return $helper->__('IP address, birth date and gender removed from %d orders', count($orderIds));
    }
}
