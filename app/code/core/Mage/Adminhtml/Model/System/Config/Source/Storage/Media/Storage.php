<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Model_System_Config_Source_Storage_Media_Storage
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Mage_Core_Model_File_Storage::STORAGE_MEDIA_FILE_SYSTEM,
                'label' => Mage::helper('adminhtml')->__('File System'),
            ],
            [
                'value' => Mage_Core_Model_File_Storage::STORAGE_MEDIA_DATABASE,
                'label' => Mage::helper('adminhtml')->__('Database'),
            ],
        ];
    }
}
