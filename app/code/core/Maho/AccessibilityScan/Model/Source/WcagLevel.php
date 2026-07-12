<?php

/**
 * WCAG conformance level source model for admin configuration.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AccessibilityScan
 */

declare(strict_types=1);

class Maho_AccessibilityScan_Model_Source_WcagLevel
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'A', 'label' => Mage::helper('accessibilityscan')->__('WCAG 2.1 Level A')],
            ['value' => 'AA', 'label' => Mage::helper('accessibilityscan')->__('WCAG 2.1 Level AA')],
            ['value' => 'AAA', 'label' => Mage::helper('accessibilityscan')->__('WCAG 2.1 Level AAA')],
        ];
    }
}
