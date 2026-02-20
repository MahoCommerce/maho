<?php

/**
 * Maho
 *
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_ImportExport_Model_Source_Export_Format
{
    public function toOptionArray(): array
    {
        $formats = Mage_ImportExport_Model_Export::CONFIG_KEY_FORMATS;
        return Mage_ImportExport_Model_Config::getModelsComboOptions($formats);
    }
}
