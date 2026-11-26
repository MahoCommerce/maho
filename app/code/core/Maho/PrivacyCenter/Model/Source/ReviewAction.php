<?php

/**
 * What an erasure does with the reviews of the customer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

class Maho_PrivacyCenter_Model_Source_ReviewAction
{
    /**
     * @return array<string, string>
     */
    public function toOptionHash(): array
    {
        $helper = Mage::helper('privacycenter');
        return [
            Maho_PrivacyCenter_Helper_Data::REVIEW_ACTION_ANONYMIZE => $helper->__('Keep Them Without the Name'),
            Maho_PrivacyCenter_Helper_Data::REVIEW_ACTION_DELETE => $helper->__('Delete Them'),
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
