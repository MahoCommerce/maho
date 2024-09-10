<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Cms
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Wysiwyg Images storage collection
 *
 * @category   Mage
 * @package    Mage_Cms
 */
class Mage_Cms_Model_Wysiwyg_Images_Storage_Collection extends Varien_Data_Collection_Filesystem
{
    /**
     * @inheritDoc
     */
    #[\Override]
    protected function _generateRow($filename)
    {
        $filename = preg_replace('~[/\\\]+~', DIRECTORY_SEPARATOR, $filename);

        return [
            'filename' => $filename,
            'basename' => basename($filename),
            'mtime'    => filemtime($filename)
        ];
    }
}
