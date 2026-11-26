<?php

/**
 * The statuses of a privacy request.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

class Maho_PrivacyCenter_Model_Source_Status
{
    /**
     * @return array<string, string>
     */
    public function toOptionHash(): array
    {
        $helper = Mage::helper('privacycenter');
        return [
            Maho_PrivacyCenter_Model_Request::STATUS_PENDING => $helper->__('Awaiting Confirmation'),
            Maho_PrivacyCenter_Model_Request::STATUS_CONFIRMED => $helper->__('Confirmed'),
            Maho_PrivacyCenter_Model_Request::STATUS_PROCESSING => $helper->__('Processing'),
            Maho_PrivacyCenter_Model_Request::STATUS_COMPLETED => $helper->__('Completed'),
            Maho_PrivacyCenter_Model_Request::STATUS_REJECTED => $helper->__('Rejected'),
            Maho_PrivacyCenter_Model_Request::STATUS_CANCELED => $helper->__('Canceled'),
            Maho_PrivacyCenter_Model_Request::STATUS_EXPIRED => $helper->__('Expired'),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->toOptionHash() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        return $options;
    }
}
