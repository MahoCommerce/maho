<?php

/**
 * The privacy requests of the subject.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_PrivacyCenter_Model_Export_Requests extends Maho_PrivacyCenter_Model_Export_AbstractExporter
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('privacycenter')->__('Privacy Requests');
    }

    #[\Override]
    public function export(Subject $subject): array
    {
        $requests = Mage::getResourceModel('privacycenter/request_collection');
        if ($subject->isGuest()) {
            $requests->addFieldToFilter('email', $subject->email);
        } else {
            $requests->addFieldToFilter('customer_id', $subject->customerId);
        }
        $data = [];
        foreach ($requests as $request) {
            $data[] = $this->clean(array_intersect_key(
                $request->getData(),
                array_flip(['type', 'status', 'email', 'created_at', 'confirmed_at', 'processed_at', 'remote_ip', 'user_agent']),
            ));
        }
        return $data;
    }
}
