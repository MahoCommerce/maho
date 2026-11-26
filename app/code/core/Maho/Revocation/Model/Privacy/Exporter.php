<?php

/**
 * The revocation requests of the subject, for the export of the Privacy Center.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_Revocation_Model_Privacy_Exporter extends Maho_PrivacyCenter_Model_Export_AbstractExporter
{
    private const array FIELDS = [
        'order_reference', 'customer_name', 'email', 'reason', 'verified', 'received_at', 'ip', 'user_agent',
        'locale', 'processed_at', 'processed_status',
    ];

    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('revocation')->__('Revocation Requests');
    }

    /**
     * The requests of the email, which the confirmation link of the privacy request proves, and the requests of the
     * orders of the customer.
     */
    #[\Override]
    public function export(Subject $subject): array
    {
        $requests = Mage::getResourceModel('revocation/request_collection');
        $adapter = $requests->getConnection();
        $condition = (string) $adapter->getCaseInsensitiveEqualsSql('main_table.email', '?');
        if (!$subject->isGuest()) {
            $orderIds = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_id', $subject->customerId)
                ->getAllIds();
            if ($orderIds !== []) {
                $condition .= ' OR ' . $adapter->quoteInto('main_table.order_id IN (?)', array_map(intval(...), $orderIds));
            }
        }
        $requests->getSelect()->where($condition, $subject->email);

        $data = [];
        foreach ($requests as $request) {
            $data[] = $this->clean(array_intersect_key($request->getData(), array_flip(self::FIELDS)));
        }
        return $data;
    }
}
