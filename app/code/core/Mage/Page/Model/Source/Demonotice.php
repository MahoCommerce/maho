<?php

/**
 * Maho
 *
 * @package    Mage_Page
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Page_Model_Source_Demonotice
{
    public const MODE_DISABLED = '0';
    public const MODE_TEXT = '1'; // Use '1' for backward compatibility with old boolean config
    public const MODE_CMS_BLOCK = '2';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::MODE_DISABLED, 'label' => Mage::helper('page')->__('Disabled')],
            ['value' => self::MODE_TEXT, 'label' => Mage::helper('page')->__('Custom Text')],
            ['value' => self::MODE_CMS_BLOCK, 'label' => Mage::helper('page')->__('CMS Block')],
        ];
    }
}
