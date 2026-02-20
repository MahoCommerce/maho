<?php

/**
 * Maho
 *
 * @package    Mage_Cms
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Cms_Model_Wysiwyg_Images_Storage_Collection extends \Maho\Data\Collection\Filesystem
{
    #[\Override]
    protected function _generateRow($filename)
    {
        $row = parent::_generateRow($filename);
        $row['filename'] = preg_replace('~[/\\\]+~', DIRECTORY_SEPARATOR, $row['filename']);
        return $row;
    }
}
