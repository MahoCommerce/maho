<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

class Maho_Revocation_Model_Source_ProcessedStatus
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $helper = Mage::helper('revocation');
        return [
            ['value' => Maho_Revocation_Model_Request::PROCESSED_STATUS_ACCEPTED, 'label' => $helper->__('Accepted')],
            ['value' => Maho_Revocation_Model_Request::PROCESSED_STATUS_REJECTED, 'label' => $helper->__('Rejected')],
            ['value' => Maho_Revocation_Model_Request::PROCESSED_STATUS_INFO_REQUESTED, 'label' => $helper->__('Information Requested')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function toOptionHash(): array
    {
        $options = [];
        foreach ($this->toOptionArray() as $option) {
            $options[$option['value']] = $option['label'];
        }
        return $options;
    }
}
