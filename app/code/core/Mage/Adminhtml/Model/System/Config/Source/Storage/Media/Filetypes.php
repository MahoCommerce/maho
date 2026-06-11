<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Model_System_Config_Source_Storage_Media_Filetypes
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => IMAGETYPE_AVIF,
                'label' => 'AVIF',
            ],
            [
                'value' => IMAGETYPE_GIF,
                'label' => 'GIF',
            ],
            [
                'value' => IMAGETYPE_JPEG,
                'label' => 'JPG',
            ],
            [
                'value' => IMAGETYPE_PNG,
                'label' => 'PNG',
            ],
            [
                'value' => IMAGETYPE_WEBP,
                'label' => 'WebP',
            ],
        ];
    }
}
