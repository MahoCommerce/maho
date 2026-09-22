<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

/**
 * @method Mage_Cms_Model_Resource_Block _getResource()
 * @method Mage_Cms_Model_Resource_Block getResource()
 * @method Mage_Cms_Model_Resource_Block_Collection getCollection()
 */
class Mage_Cms_Model_Block extends Mage_Core_Model_Abstract
{
    public const CACHE_TAG     = 'cms_block';
    #[\Override]
    protected $_cacheTag = 'cms_block';
    #[\Override]
    protected $_eventPrefix = 'cms_block';

    #[\Override]
    protected function _construct()
    {
        $this->_init('cms/block');
    }

    /**
     * Prevent blocks recursion
     *
     * @throws Mage_Core_Exception
     * @return Mage_Core_Model_Abstract
     */
    #[\Override]
    protected function _beforeSave()
    {
        $needle = 'block_id="' . $this->getBlockId() . '"';
        if (!str_contains($this->getContent(), $needle)) {
            return parent::_beforeSave();
        }
        Mage::throwException(
            Mage::helper('cms')->__('The static block content cannot contain  directive with its self.'),
        );
    }

    public function getBlockId(): ?int
    {
        $value = $this->getData('block_id');
        return $value === null ? null : (int) $value;
    }

    public function getContent(): ?string
    {
        $value = $this->getData('content');
        return $value === null ? null : (string) $value;
    }

    public function setContent(?string $value): static
    {
        return $this->setData('content', $value);
    }

    public function getCreationTime(): ?string
    {
        $value = $this->getData('creation_time');
        return $value === null ? null : (string) $value;
    }

    public function setCreationTime(?string $value): static
    {
        return $this->setData('creation_time', $value);
    }

    public function getIdentifier(): ?string
    {
        $value = $this->getData('identifier');
        return $value === null ? null : (string) $value;
    }

    public function setIdentifier(?string $value): static
    {
        return $this->setData('identifier', $value);
    }

    public function getIsActive(): ?bool
    {
        $value = $this->getData('is_active');
        return $value === null ? null : (bool) $value;
    }

    public function setIsActive(?bool $value = true): static
    {
        return $this->setData('is_active', $value);
    }

    public function getStoreId(): array|int|string|null
    {
        return $this->getData('store_id');
    }

    public function setStoreId(array|int|string|null $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getTitle(): ?string
    {
        $value = $this->getData('title');
        return $value === null ? null : (string) $value;
    }

    public function setTitle(?string $value): static
    {
        return $this->setData('title', $value);
    }

    public function getUpdateTime(): ?string
    {
        $value = $this->getData('update_time');
        return $value === null ? null : (string) $value;
    }

    public function setUpdateTime(?string $value): static
    {
        return $this->setData('update_time', $value);
    }

}
