<?php

/**
 * The types of a privacy request.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

class Maho_PrivacyCenter_Model_Source_Type
{
    /**
     * @return array<string, string>
     */
    public function toOptionHash(): array
    {
        $helper = Mage::helper('privacycenter');
        return [
            Maho_PrivacyCenter_Model_Request::TYPE_EXPORT => $helper->__('Data Export'),
            Maho_PrivacyCenter_Model_Request::TYPE_ERASURE => $helper->__('Data Erasure'),
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
