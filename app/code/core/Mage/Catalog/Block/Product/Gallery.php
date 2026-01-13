<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Block_Product_Gallery extends Mage_Core_Block_Template
{
    /**
     * @return Mage_Core_Block_Template
     */
    #[\Override]
    protected function _prepareLayout()
    {
        if ($headBlock = $this->getLayout()->getBlock('head')) {
            $headBlock->setTitle($this->getProduct()->getMetaTitle());
        }
        return parent::_prepareLayout();
    }

    /**
     * @return Mage_Catalog_Model_Product
     */
    public function getProduct()
    {
        return Mage::registry('product');
    }

    /**
     * @return \Maho\Data\Collection
     */
    public function getGalleryCollection()
    {
        return $this->getProduct()->getMediaGalleryImages();
    }

    /**
     * @return \Maho\DataObject|null
     * @throws Exception
     */
    public function getCurrentImage()
    {
        $imageId = $this->getRequest()->getParam('image');
        $image = null;
        if ($imageId) {
            $image = $this->getGalleryCollection()->getItemById($imageId);
        }

        if (!$image) {
            $image = $this->getGalleryCollection()->getFirstItem();
        }
        return $image;
    }

    /**
     * @return string
     */
    public function getImageUrl()
    {
        return $this->getCurrentImage()->getUrl();
    }

    /**
     * @return string
     */
    public function getImageFile()
    {
        return $this->getCurrentImage()->getFile();
    }

    /**
     * Retrieve image width
     *
     * @return false|int
     */
    public function getImageWidth()
    {
        $file = $this->getCurrentImage()->getPath();
        if (file_exists($file)) {
            $size = \Maho\Io::getImageSize($file);
            if (isset($size[0])) {
                if ($size[0] > 600) {
                    return 600;
                }
                return $size[0];
            }
        }

        return false;
    }

    /**
     * @return false|\Maho\DataObject
     * @throws Exception
     */
    public function getPreviusImage()
    {
        $current = $this->getCurrentImage();
        if (!$current) {
            return false;
        }
        $previus = false;
        foreach ($this->getGalleryCollection() as $image) {
            if ($image->getValueId() == $current->getValueId()) {
                return $previus;
            }
            $previus = $image;
        }
        return $previus;
    }

    /**
     * @return false|\Maho\DataObject
     * @throws Exception
     */
    public function getNextImage()
    {
        $current = $this->getCurrentImage();
        if (!$current) {
            return false;
        }

        $next = false;
        $currentFind = false;
        foreach ($this->getGalleryCollection() as $image) {
            if ($currentFind) {
                return $image;
            }
            if ($image->getValueId() == $current->getValueId()) {
                $currentFind = true;
            }
        }
        return $next;
    }

    /**
     * @return false|string
     */
    public function getPreviusImageUrl()
    {
        if ($image = $this->getPreviusImage()) {
            return $this->getUrl('*/*/*', ['_current' => true, 'image' => $image->getValueId()]);
        }
        return false;
    }

    /**
     * @return false|string
     */
    public function getNextImageUrl()
    {
        if ($image = $this->getNextImage()) {
            return $this->getUrl('*/*/*', ['_current' => true, 'image' => $image->getValueId()]);
        }
        return false;
    }
}
