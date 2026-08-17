<?php

/**
 * Values of the "use" content signal: what a crawler may keep after reading the page.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sitemap
 */

declare(strict_types=1);

class Mage_Sitemap_Model_Source_Signal_Usage
{
    public const IMMEDIATE = 'immediate';
    public const REFERENCE = 'reference';
    public const FULL = 'full';

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        $helper = Mage::helper('sitemap');

        return [
            ['value' => '', 'label' => $helper->__('Not stated')],
            ['value' => self::IMMEDIATE, 'label' => $helper->__('Immediate: answer the question, keep nothing')],
            ['value' => self::REFERENCE, 'label' => $helper->__('Reference: index, excerpt and link back')],
            ['value' => self::FULL, 'label' => $helper->__('Full: keep and reuse the content')],
        ];
    }
}
