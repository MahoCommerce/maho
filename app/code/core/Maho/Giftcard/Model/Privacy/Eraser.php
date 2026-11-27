<?php

/**
 * Removes the name, the email and the message of the subject from the gift cards that it sent, bought or received.
 * The code and the balance stay, so the card keeps its value for its owner. A card whose email is not sent yet keeps
 * its data, because the email needs it.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_Giftcard_Model_Privacy_Eraser extends Maho_PrivacyCenter_Model_Eraser_AbstractEraser
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('giftcard')->__('Your name, email and message on gift cards');
    }

    #[\Override]
    public function erase(Subject $subject): string
    {
        $helper = Mage::helper('giftcard');
        $ids = Mage::getResourceSingleton('giftcard/giftcard')->getIdsOfPerson($subject->email, $subject->getOrderIds());
        if ($ids['sent'] === [] && $ids['received'] === []) {
            return $helper->__('No gift cards');
        }

        $adapter = $this->getConnection();
        $table = $this->getTable('giftcard/giftcard');
        $delivered = 'email_sent_at IS NOT NULL OR recipient_email IS NULL';
        $changed = [];
        foreach (['sent' => 'sender', 'received' => 'recipient'] as $role => $prefix) {
            if ($ids[$role] === []) {
                continue;
            }
            $where = ['giftcard_id IN (?)' => $ids[$role], $delivered];
            $changed = [...$changed, ...array_map(intval(...), $adapter->fetchCol(
                $adapter->select()->from($table, ['giftcard_id'])->where('giftcard_id IN (?)', $ids[$role])->where($delivered),
            ))];
            $adapter->update($table, ["{$prefix}_name" => null, "{$prefix}_email" => null, 'message' => null], $where);
        }

        $changed = array_unique($changed);
        $waiting = count(array_unique([...$ids['sent'], ...$ids['received']])) - count($changed);
        return $waiting > 0
            ? $helper->__('%d gift cards changed, %d kept until their email is sent', count($changed), $waiting)
            : $helper->__('%d gift cards changed', count($changed));
    }
}
