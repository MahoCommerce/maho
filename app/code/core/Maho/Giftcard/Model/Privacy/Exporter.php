<?php

/**
 * Exports the gift cards that the subject sent, bought or received. The code of a card never goes in the export.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_Giftcard_Model_Privacy_Exporter extends Maho_PrivacyCenter_Model_Export_AbstractExporter
{
    private const array FIELDS = [
        'sender_name', 'sender_email', 'recipient_name', 'recipient_email', 'message', 'initial_balance', 'created_at',
        'email_scheduled_at', 'email_sent_at',
    ];

    /** The state of a card is data of its owner, so a sender does not get it. */
    private const array RECIPIENT_FIELDS = ['balance', 'status', 'expires_at'];

    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('giftcard')->__('Gift Cards');
    }

    #[\Override]
    public function export(Subject $subject): array
    {
        $ids = Mage::getResourceSingleton('giftcard/giftcard')->getIdsOfPerson($subject->email, $subject->getOrderIds());
        $allIds = array_values(array_unique([...$ids['sent'], ...$ids['received']]));
        if ($allIds === []) {
            return [];
        }

        $data = [];
        foreach (Mage::getResourceModel('giftcard/giftcard_collection')->addFieldToFilter('giftcard_id', ['in' => $allIds]) as $card) {
            $received = in_array((int) $card->getId(), $ids['received'], true);
            $fields = $received ? [...self::FIELDS, ...self::RECIPIENT_FIELDS] : self::FIELDS;
            $data[] = $this->clean(array_intersect_key($card->getData(), array_flip($fields)));
        }
        return $data;
    }
}
