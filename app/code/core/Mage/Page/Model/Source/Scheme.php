<?php

/**
 * Color scheme options for the storefront data-theme attribute.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Page
 */

class Mage_Page_Model_Source_Scheme
{
    /**
     * DaisyUI schemes compiled into the maho design package's stylesheet.
     * Only schemes that pass WCAG AA (4.5:1) on every token pair Maho renders
     * as text are shipped - re-audit before adding one here (see
     * public/skin/frontend/maho/default/src/_theme.css).
     */
    public const SCHEMES = [
        'coffee',
        'dim',
        'dracula',
        'forest',
        'luxury',
        'night',
        'sunset',
        'synthwave',
    ];

    public function toOptionArray(): array
    {
        $options = [
            ['value' => '', 'label' => Mage::helper('page')->__('Theme default')],
        ];
        foreach (self::SCHEMES as $scheme) {
            $options[] = ['value' => $scheme, 'label' => ucfirst($scheme)];
        }
        return $options;
    }
}
