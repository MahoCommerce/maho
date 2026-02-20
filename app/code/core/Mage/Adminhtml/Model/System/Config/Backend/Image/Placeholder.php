<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_System_Config_Backend_Image_Placeholder extends Mage_Adminhtml_Model_System_Config_Backend_Image
{
    /**
     * Getter for allowed extensions of uploaded files
     * Includes SVG support for catalog placeholders
     */
    #[\Override]
    protected function _getAllowedExtensions(): array
    {
        return array_merge(parent::_getAllowedExtensions(), ['svg']);
    }
}
