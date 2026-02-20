<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Cms_Wysiwyg_Images_Tree extends Mage_Adminhtml_Block_Template
{
    /**
     * Json tree builder
     *
     * @return string
     */
    public function getTreeJson(?string $path = null)
    {
        $helper = Mage::helper('cms/wysiwyg_images');
        $path ??= $helper->getStorageRoot();
        $collection = Mage::registry('storage')->getDirsCollection($path);
        $jsonArray = [];
        foreach ($collection as $item) {
            $jsonArray[] = [
                'text'  => $helper->getShortFilename($item->getBasename(), 20),
                'id'    => $helper->convertPathToId($item->getFilename()),
                'cls'   => 'folder',
                'children' => $item->getSubdirCount() === 0 ? [] : null,
            ];
        }
        return Mage::helper('core')->jsonEncode($jsonArray);
    }

    /**
     * Json source URL
     *
     * @return string
     */
    public function getTreeLoaderUrl()
    {
        return $this->getUrl('*/*/treeJson');
    }

    /**
     * Root node name of tree
     *
     * @return string
     */
    public function getRootNodeName()
    {
        return $this->helper('cms')->__('Storage Root');
    }

    /**
     * Return file to select in current path
     */
    public function getTreeCurrentFile(): string
    {
        return $this->getRequest()->getParam('filename', '');
    }

    /**
     * Return tree node full path based on current path
     *
     * @return string
     */
    public function getTreeCurrentPath()
    {
        $treePath = '/root';
        $helper = Mage::helper('cms/wysiwyg_images');
        $path = $helper->getCurrentPath();
        if ($path) {
            $path = str_replace($helper->getStorageRoot(), '', $path);
            $relative = '';
            foreach (explode(DS, $path) as $dirName) {
                if ($dirName) {
                    $relative .= DS . $dirName;
                    $treePath .= '/' . $helper->idEncode($relative);
                }
            }
        }
        return $treePath;
    }
}
