<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
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
        $path ??= $helper->getStorageRootPath();
        $collection = Mage::registry('storage')->getDirsCollection($path);
        $jsonArray = [];
        foreach ($collection as $item) {
            // A child folder loads its own children on expand, so one listing serves one folder
            $jsonArray[] = [
                'text'  => $helper->getShortFilename($item->getName(), 20),
                'id'    => $item->getId(),
                'cls'   => 'folder',
                'children' => null,
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
        $root = $helper->getStorageRootPath();
        if (str_starts_with($path, $root . '/')) {
            $relative = '';
            foreach (explode('/', substr($path, strlen($root) + 1)) as $dirName) {
                if ($dirName) {
                    $relative .= '/' . $dirName;
                    $treePath .= '/' . $helper->idEncode($relative);
                }
            }
        }
        return $treePath;
    }
}
