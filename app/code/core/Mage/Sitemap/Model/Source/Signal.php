<?php

/**
 * Values of a single content signal: stated as granted, stated as reserved, or left unstated.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

class Mage_Sitemap_Model_Source_Signal
{
    public const YES = 'yes';
    public const NO = 'no';

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $helper = Mage::helper('sitemap');

        return [
            ['value' => '', 'label' => $helper->__('Not stated')],
            ['value' => self::YES, 'label' => $helper->__('Yes, allowed')],
            ['value' => self::NO, 'label' => $helper->__('No, reserved')],
        ];
    }
}
