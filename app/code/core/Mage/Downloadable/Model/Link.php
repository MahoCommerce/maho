<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

/**
 * Downloadable link model
 *
 * @package    Mage_Downloadable
 *
 * @method Mage_Downloadable_Model_Resource_Link _getResource()
 * @method Mage_Downloadable_Model_Resource_Link getResource()
 * @method Mage_Downloadable_Model_Resource_Link_Collection getCollection()
 */
class Mage_Downloadable_Model_Link extends Mage_Core_Model_Abstract
{
    public const XML_PATH_LINKS_TITLE              = 'catalog/downloadable/links_title';
    public const XML_PATH_DEFAULT_DOWNLOADS_NUMBER = 'catalog/downloadable/downloads_number';
    public const XML_PATH_TARGET_NEW_WINDOW        = 'catalog/downloadable/links_target_new_window';
    public const XML_PATH_CONFIG_IS_SHAREABLE      = 'catalog/downloadable/shareable';

    public const LINK_SHAREABLE_YES    = 1;
    public const LINK_SHAREABLE_NO     = 0;
    public const LINK_SHAREABLE_CONFIG = 2;

    #[\Override]
    protected function _construct()
    {
        $this->_init('downloadable/link');
        parent::_construct();
    }

    /**
     * Return link files path
     *
     * @return string
     */
    public static function getLinkDir()
    {
        return Mage::getBaseDir();
    }

    #[\Override]
    protected function _afterSave()
    {
        $this->getResource()->saveItemTitleAndPrice($this);
        return parent::_afterSave();
    }

    /**
     * Retrieve base temporary path
     *
     * @return string
     */
    public static function getBaseTmpPath()
    {
        return Mage::getBaseDir('media') . DS . 'downloadable' . DS . 'tmp' . DS . 'links';
    }

    /**
     * Retrieve Base files path
     *
     * @return string
     */
    public static function getBasePath()
    {
        return Mage::getBaseDir('media') . DS . 'downloadable' . DS . 'files' . DS . 'links';
    }

    /**
     * Retrieve base sample temporary path
     *
     * @return string
     */
    public static function getBaseSampleTmpPath()
    {
        return Mage::getBaseDir('media') . DS . 'downloadable' . DS . 'tmp' . DS . 'link_samples';
    }

    /**
     * Retrieve base sample path
     *
     * @return string
     */
    public static function getBaseSamplePath()
    {
        return Mage::getBaseDir('media') . DS . 'downloadable' . DS . 'files' . DS . 'link_samples';
    }

    public function getPrice(): ?float
    {
        $product = $this->getProduct();
        $storeId = $product instanceof Mage_Catalog_Model_Product ? $product->getPriceStoreId() : null;

        return Mage::helper('catalog')->deriveOptionPrice($this, $storeId, 'website_price');
    }

    /**
     * Retrieve links searchable data
     *
     * @param int $productId
     * @param int $storeId
     * @return list<string>
     */
    public function getSearchableData($productId, $storeId): array
    {
        return $this->_getResource()
            ->getSearchableData($productId, $storeId);
    }

    public function getIsShareable(): ?int
    {
        $value = $this->getData('is_shareable');
        return $value === null ? null : (int) $value;
    }

    public function setIsShareable(?int $value): static
    {
        return $this->setData('is_shareable', $value);
    }

    public function getIsUnlimited(): ?bool
    {
        $value = $this->getData('is_unlimited');
        return $value === null ? null : (bool) $value;
    }

    public function getLinkFile(): ?string
    {
        $value = $this->getData('link_file');
        return $value === null ? null : (string) $value;
    }

    public function setLinkFile(?string $value): static
    {
        return $this->setData('link_file', $value);
    }

    public function getLinkId(): ?int
    {
        $value = $this->getData('link_id');
        return $value === null ? null : (int) $value;
    }

    public function getLinkType(): ?string
    {
        $value = $this->getData('link_type');
        return $value === null ? null : (string) $value;
    }

    public function setLinkType(?string $value): static
    {
        return $this->setData('link_type', $value);
    }

    public function getLinkUrl(): ?string
    {
        $value = $this->getData('link_url');
        return $value === null ? null : (string) $value;
    }

    public function setLinkUrl(?string $value): static
    {
        return $this->setData('link_url', $value);
    }

    public function getNumberOfDownloads(): ?int
    {
        $value = $this->getData('number_of_downloads');
        return $value === null ? null : (int) $value;
    }

    public function setNumberOfDownloads(?int $value): static
    {
        return $this->setData('number_of_downloads', $value);
    }

    public function setPrice(?float $value): static
    {
        return $this->setData('price', $value);
    }

    public function getProduct(): ?Mage_Catalog_Model_Product
    {
        return $this->getData('product');
    }

    public function setProduct(?Mage_Catalog_Model_Product $value): static
    {
        return $this->setData('product', $value);
    }

    public function getProductId(): ?int
    {
        $value = $this->getData('product_id');
        return $value === null ? null : (int) $value;
    }

    public function setProductId(?int $value): static
    {
        return $this->setData('product_id', $value);
    }

    public function getProductWebsiteIds(): ?array
    {
        return $this->getData('product_website_ids');
    }

    public function setProductWebsiteIds(?array $value): static
    {
        return $this->setData('product_website_ids', $value);
    }

    public function getSampleFile(): ?string
    {
        $value = $this->getData('sample_file');
        return $value === null ? null : (string) $value;
    }

    public function setSampleFile(?string $value): static
    {
        return $this->setData('sample_file', $value);
    }

    public function getSampleType(): ?string
    {
        $value = $this->getData('sample_type');
        return $value === null ? null : (string) $value;
    }

    public function setSampleType(?string $value): static
    {
        return $this->setData('sample_type', $value);
    }

    public function getSampleUrl(): ?string
    {
        $value = $this->getData('sample_url');
        return $value === null ? null : (string) $value;
    }

    public function setSampleUrl(?string $value): static
    {
        return $this->setData('sample_url', $value);
    }

    public function getSortOrder(): ?int
    {
        $value = $this->getData('sort_order');
        return $value === null ? null : (int) $value;
    }

    public function setSortOrder(?int $value): static
    {
        return $this->setData('sort_order', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getStoreTitle(): ?string
    {
        $value = $this->getData('store_title');
        return $value === null ? null : (string) $value;
    }

    public function getTitle(): ?string
    {
        $value = $this->getData('title');
        return $value === null ? null : (string) $value;
    }

    public function getUseDefaultPrice(): ?bool
    {
        $value = $this->getData('use_default_price');
        return $value === null ? null : (bool) $value;
    }

    public function getUseDefaultTitle(): ?bool
    {
        $value = $this->getData('use_default_title');
        return $value === null ? null : (bool) $value;
    }

    public function getWebsiteId(): ?int
    {
        $value = $this->getData('website_id');
        return $value === null ? null : (int) $value;
    }

    public function setWebsiteId(?int $value): static
    {
        return $this->setData('website_id', $value);
    }

    public function getWebsitePrice(): ?float
    {
        $value = $this->getData('website_price');
        return $value === null ? null : (float) $value;
    }

}
