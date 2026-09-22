<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

/**
 * @method Mage_Cms_Model_Resource_Page _getResource()
 * @method Mage_Cms_Model_Resource_Page getResource()
 * @method Mage_Cms_Model_Resource_Page_Collection getCollection()
 *
 * @method bool hasCreationTime()
 * @method bool hasStores()
 */
class Mage_Cms_Model_Page extends Mage_Core_Model_Abstract
{
    public const NOROUTE_PAGE_ID = 'no-route';

    /**
     * Page's Statuses
     */
    public const STATUS_ENABLED = 1;
    public const STATUS_DISABLED = 0;

    public const CACHE_TAG              = 'cms_page';
    #[\Override]
    protected $_cacheTag         = 'cms_page';

    /**
     * Prefix of model events names
     *
     * @var string
     */
    #[\Override]
    protected $_eventPrefix = 'cms_page';

    #[\Override]
    protected function _construct()
    {
        $this->_init('cms/page');
    }

    #[\Override]
    public function load($id, $field = null)
    {
        if (is_null($id)) {
            return $this->noRoutePage();
        }
        return parent::load($id, $field);
    }

    /**
     * Load No-Route Page
     *
     * @return $this
     */
    public function noRoutePage()
    {
        return $this->load(self::NOROUTE_PAGE_ID, $this->getIdFieldName());
    }

    /**
     * Check if page identifier exist for specific store
     * return page id if page exists
     *
     * @param string $identifier
     * @param int $storeId
     * @return string
     */
    public function checkIdentifier($identifier, $storeId)
    {
        return $this->_getResource()->checkIdentifier($identifier, $storeId);
    }

    /**
     * Prepare page's statuses.
     * Available event cms_page_get_available_statuses to customize statuses.
     *
     * @return array
     */
    public function getAvailableStatuses()
    {
        $statuses = new \Maho\DataObject([
            self::STATUS_ENABLED => Mage::helper('cms')->__('Enabled'),
            self::STATUS_DISABLED => Mage::helper('cms')->__('Disabled'),
        ]);

        Mage::dispatchEvent('cms_page_get_available_statuses', ['statuses' => $statuses]);

        return $statuses->getData();
    }

    public function getUsedInStoreConfigCollection(?array $paths = []): Mage_Core_Model_Resource_Db_Collection_Abstract
    {
        return $this->_getResource()->getUsedInStoreConfigCollection($this, $paths);
    }

    public function isUsedInStoreConfig(?array $paths = []): bool
    {
        return $this->_getResource()->isUsedInStoreConfig($this, $paths);
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

    public function getContentHeading(): ?string
    {
        $value = $this->getData('content_heading');
        return $value === null ? null : (string) $value;
    }

    public function setContentHeading(?string $value): static
    {
        return $this->setData('content_heading', $value);
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

    public function getCustomLayoutUpdateXml(): ?string
    {
        $value = $this->getData('custom_layout_update_xml');
        return $value === null ? null : (string) $value;
    }

    public function setCustomLayoutUpdateXml(?string $value): static
    {
        return $this->setData('custom_layout_update_xml', $value);
    }

    public function getCustomRootTemplate(): ?string
    {
        $value = $this->getData('custom_root_template');
        return $value === null ? null : (string) $value;
    }

    public function setCustomRootTemplate(?string $value): static
    {
        return $this->setData('custom_root_template', $value);
    }

    public function getCustomTheme(): ?string
    {
        $value = $this->getData('custom_theme');
        return $value === null ? null : (string) $value;
    }

    public function setCustomTheme(?string $value): static
    {
        return $this->setData('custom_theme', $value);
    }

    public function getCustomThemeFrom(): ?string
    {
        $value = $this->getData('custom_theme_from');
        return $value === null ? null : (string) $value;
    }

    public function setCustomThemeFrom(?string $value): static
    {
        return $this->setData('custom_theme_from', $value);
    }

    public function getCustomThemeTo(): ?string
    {
        $value = $this->getData('custom_theme_to');
        return $value === null ? null : (string) $value;
    }

    public function setCustomThemeTo(?string $value): static
    {
        return $this->setData('custom_theme_to', $value);
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

    public function getLayoutUpdateXml(): ?string
    {
        $value = $this->getData('layout_update_xml');
        return $value === null ? null : (string) $value;
    }

    public function setLayoutUpdateXml(?string $value): static
    {
        return $this->setData('layout_update_xml', $value);
    }

    public function getMetaDescription(): ?string
    {
        $value = $this->getData('meta_description');
        return $value === null ? null : (string) $value;
    }

    public function setMetaDescription(?string $value): static
    {
        return $this->setData('meta_description', $value);
    }

    public function getMetaKeywords(): ?string
    {
        $value = $this->getData('meta_keywords');
        return $value === null ? null : (string) $value;
    }

    public function setMetaKeywords(?string $value): static
    {
        return $this->setData('meta_keywords', $value);
    }

    public function getPreviewUrl(): ?string
    {
        $value = $this->getData('preview_url');
        return $value === null ? null : (string) $value;
    }

    public function getRootTemplate(): ?string
    {
        $value = $this->getData('root_template');
        return $value === null ? null : (string) $value;
    }

    public function setRootTemplate(?string $value): static
    {
        return $this->setData('root_template', $value);
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

    public function getStoreCode(): ?string
    {
        $value = $this->getData('store_code');
        return $value === null ? null : (string) $value;
    }

    public function getStoreId(): array|int|string|null
    {
        return $this->getData('store_id');
    }

    public function setStoreId(array|int|string|null $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getStores(): ?array
    {
        return $this->getData('stores');
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
