<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

declare(strict_types=1);

/**
 * Downloadable sample model
 *
 * @package    Mage_Downloadable
 *
 * @method Mage_Downloadable_Model_Resource_Sample _getResource()
 * @method Mage_Downloadable_Model_Resource_Sample getResource()
 * @method Mage_Downloadable_Model_Resource_Sample_Collection getCollection()
 */
class Mage_Downloadable_Model_Sample extends Mage_Core_Model_Abstract
{
    public const XML_PATH_SAMPLES_TITLE = 'catalog/downloadable/samples_title';

    /**
     * Initialize resource
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('downloadable/sample');
        parent::_construct();
    }

    /**
     * Return sample files path
     *
     * @return string
     */
    public static function getSampleDir()
    {
        return Mage::getBaseDir();
    }

    #[\Override]
    protected function _afterSave()
    {
        $this->getResource()
            ->saveItemTitle($this);
        return parent::_afterSave();
    }

    /**
     * Retrieve sample URL
     *
     * @return string
     */
    public function getUrl()
    {
        if ($this->getSampleUrl()) {
            return $this->getSampleUrl();
        }
        return $this->getSampleFile();
    }

    /** Directory of temporary sample files on the downloadable mount. */
    public static function getTmpStoragePath(): string
    {
        return 'tmp/samples';
    }

    /** Directory of sample files on the downloadable mount. */
    public static function getStoragePath(): string
    {
        return 'files/samples';
    }

    /**
     * Retrieve base tmp path
     *
     * @return string
     * @deprecated since 26.11 the file is on the media mount, use getTmpStoragePath()
     */
    public static function getBaseTmpPath()
    {
        return Mage::getBaseDir('media') . DS . 'downloadable' . DS . 'tmp' . DS . 'samples';
    }

    /**
     * Retrieve sample files path
     *
     * @return string
     * @deprecated since 26.11 the file is on the media mount, use getStoragePath()
     */
    public static function getBasePath()
    {
        return Mage::getBaseDir('media') . DS . 'downloadable' . DS . 'files' . DS . 'samples';
    }

    /**
     * Retrieve links searchable data
     *
     * @param int $productId
     * @param int $storeId
     * @return array
     */
    public function getSearchableData($productId, $storeId)
    {
        return $this->_getResource()
            ->getSearchableData($productId, $storeId);
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

    public function getUseDefaultTitle(): ?bool
    {
        $value = $this->getData('use_default_title');
        return $value === null ? null : (bool) $value;
    }

}
