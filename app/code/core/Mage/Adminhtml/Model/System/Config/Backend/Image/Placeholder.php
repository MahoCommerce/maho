<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

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
