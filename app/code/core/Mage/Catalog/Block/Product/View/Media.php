<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

/**
 * Simple product data view
 *
 * @package    Mage_Catalog
 */
class Mage_Catalog_Block_Product_View_Media extends Mage_Catalog_Block_Product_View_Abstract
{
    /**
     * Flag, that defines whether gallery is disabled
     *
     * @var bool
     */
    protected $_isGalleryDisabled;

    /**
     * Retrieve list of gallery images
     *
     * @return array|\Maho\Data\Collection
     */
    public function getGalleryImages()
    {
        if ($this->_isGalleryDisabled) {
            return [];
        }
        $product = $this->getProduct();
        Mage::getSingleton('core/image_size')->preload(
            Mage_Catalog_Model_Product_Image_Provider::ENTITY,
            [$product->getData('image'), ...array_column($product->getMediaGallery()['images'] ?? [], 'file')],
        );
        return $product->getMediaGalleryImages();
    }

    /**
     * Retrieve gallery url
     *
     * @param null|\Maho\DataObject $image
     * @return string
     */
    public function getGalleryUrl($image = null)
    {
        $params = ['id' => $this->getProduct()->getId()];
        if ($image) {
            $params['image'] = $image->getValueId();
        }
        return $this->getUrl('catalog/product/gallery', $params);
    }

    /**
     * The URL of the largest file of a gallery image.
     *
     * @param null|\Maho\DataObject $image
     * @return string|null
     */
    public function getGalleryImageUrl($image)
    {
        if ($image) {
            /** @var Mage_Catalog_Helper_Image $helper */
            $helper = $this->helper('catalog/image');
            return (string) $helper->init($this->getProduct(), 'image', $image->getFile());
        }
        return null;
    }

    /**
     * Retrieve visibility of gallery image based on gallery filter where present
     *
     * @param null|\Maho\DataObject $image
     * @return bool
     */
    public function isGalleryImageVisible($image)
    {
        if (($filterClass = $this->getGalleryFilterHelper()) && ($filterMethod = $this->getGalleryFilterMethod())) {
            return Mage::helper($filterClass)->$filterMethod($this->getProduct(), $image);
        }
        return true;
    }

    /**
     * Disable gallery
     */
    public function disableGallery()
    {
        $this->_isGalleryDisabled = true;
    }

    public function getGalleryFilterHelper(): ?string
    {
        $value = $this->getData('gallery_filter_helper');
        return $value === null ? null : (string) $value;
    }

    public function getGalleryFilterMethod(): ?string
    {
        $value = $this->getData('gallery_filter_method');
        return $value === null ? null : (string) $value;
    }
}
