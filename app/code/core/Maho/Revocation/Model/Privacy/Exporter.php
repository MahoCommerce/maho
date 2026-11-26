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
     * A customer gets the requests of the orders of the account, because the session does not prove the email.
     * A guest gets the requests of the email, which the confirmation link proved.
     */
    #[\Override]
    public function export(Subject $subject): array
    {
        $requests = Mage::getResourceModel('revocation/request_collection');
        if ($subject->isGuest()) {
            $requests->getSelect()->where(
                (string) $requests->getConnection()->getCaseInsensitiveEqualsSql('main_table.email', '?'),
                $subject->email,
            );
        } else {
            $orderIds = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('customer_id', $subject->customerId)
                ->getAllIds();
            if ($orderIds === []) {
                return [];
            }
            $requests->addFieldToFilter('order_id', ['in' => $orderIds]);
        }

        $data = [];
        foreach ($requests as $request) {
            $data[] = $this->clean(array_intersect_key($request->getData(), array_flip(self::FIELDS)));
        }
        return $data;
    }
}
