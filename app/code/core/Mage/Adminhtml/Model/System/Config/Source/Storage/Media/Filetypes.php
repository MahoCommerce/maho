<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
