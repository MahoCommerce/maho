<?php

/**
 * Deletes the carts of the subject and their gift messages. An order keeps its own copy of the data of its cart.
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
        return Mage::helper('privacycenter')->__('Carts and their gift messages');
    }

    #[\Override]
    public function erase(Subject $subject): string
    {
        $helper = Mage::helper('privacycenter');
        $adapter = $this->getConnection();
        // A guest cart holds customer_id NULL or 0, as Mage_Sales_Model_Quote::loadByCustomer() says
        $quoteIds = array_map(intval(...), $adapter->fetchCol(
            $adapter->select()
                ->from($this->getTable('sales/quote'), ['entity_id'])
                ->where($subject->getRowsCondition($adapter, 'customer_id', 'customer_email', [null, 0]), $subject->email),
        ));
        if ($quoteIds === []) {
            return $helper->__('%d carts deleted', 0);
        }

        $giftMessageIds = $this->getGiftMessageIds($quoteIds);
        $count = $adapter->delete($this->getTable('sales/quote'), ['entity_id IN (?)' => $quoteIds]);
        $giftMessages = $giftMessageIds === []
            ? 0
            : $adapter->delete($this->getTable('giftmessage/message'), ['gift_message_id IN (?)' => $giftMessageIds]);

        return $helper->__('%d carts and %d gift messages deleted', $count, $giftMessages);
    }

    /**
     * The gift messages of the carts $quoteIds that no order uses. An order shares the gift message row of its cart.
     *
     * @param list<int> $quoteIds
     * @return list<int>
     */
    protected function getGiftMessageIds(array $quoteIds): array
    {
        $adapter = $this->getConnection();
        $selects = [
            $adapter->select()->from(['t' => $this->getTable('sales/quote')], ['gift_message_id'])->where('t.entity_id IN (?)', $quoteIds),
            $adapter->select()->from(['t' => $this->getTable('sales/quote_item')], ['gift_message_id'])->where('t.quote_id IN (?)', $quoteIds),
            $adapter->select()->from(['t' => $this->getTable('sales/quote_address')], ['gift_message_id'])->where('t.quote_id IN (?)', $quoteIds),
            $adapter->select()
                ->from(['t' => $this->getTable('sales_flat_quote_address_item')], ['gift_message_id'])
                ->join(['address' => $this->getTable('sales/quote_address')], 'address.address_id = t.quote_address_id', [])
                ->where('address.quote_id IN (?)', $quoteIds),
        ];
        $ids = [];
        foreach ($selects as $select) {
            foreach ($adapter->fetchCol($select->where('t.gift_message_id > 0')) as $id) {
                $ids[(int) $id] = true;
            }
        }
        if ($ids === []) {
            return [];
        }

        $usedByOrders = [];
        foreach (['sales/order', 'sales/order_item'] as $alias) {
            $select = $adapter->select()
                ->from($this->getTable($alias), ['gift_message_id'])
                ->where('gift_message_id IN (?)', array_keys($ids));
            foreach ($adapter->fetchCol($select) as $id) {
                $usedByOrders[(int) $id] = true;
            }
        }
        return array_keys(array_diff_key($ids, $usedByOrders));
    }
}
