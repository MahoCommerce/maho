<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Block_Product_View_Options_Type_File extends Mage_Catalog_Block_Product_View_Options_Abstract
{
    /**
     * Returns info of file
     *
     * @return \Maho\DataObject
     */
    public function getFileInfo()
    {
        $info = $this->getProduct()->getPreconfiguredValues()->getData('options/' . $this->getOption()->getId());
        if (empty($info)) {
            $info = new \Maho\DataObject();
        } elseif (is_array($info)) {
            $info = new \Maho\DataObject($info);
        }
        return $info;
    }

    /**
     * Get sanitized file extensions for display (removes forbidden extensions)
     */
    public function getSanitizedFileExtension(): string
    {
        $option = $this->getOption();
        $originalExtensions = $option->getFileExtension();

        $result = Mage::helper('catalog')->validateFileExtensionsAgainstForbiddenList($originalExtensions);

        return empty($result['allowed']) ? '' : implode(', ', $result['allowed']);
    }

    public function getMaxFileSizeMb(): int
    {
        $uploadMaxFilesize = ini_parse_quantity(ini_get('upload_max_filesize'));
        $postMaxSize = ini_parse_quantity(ini_get('post_max_size'));
        $maxBytes = min($uploadMaxFilesize, $postMaxSize);

        return (int) ($maxBytes / (1024 * 1024));
    }
}
