<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Model_Resource_Eav_Attribute extends Mage_Eav_Model_Entity_Attribute
{
    public const SCOPE_STORE   = 0;
    public const SCOPE_GLOBAL  = 1;
    public const SCOPE_WEBSITE = 2;

    /**
     * Event prefix
     */
    #[\Override]
    protected $_eventPrefix = 'blog_entity_attribute';

    /**
     * Event object name
     */
    #[\Override]
    protected $_eventObject = 'attribute';

    /**
     * Array with labels
     */
    protected static ?array $_labels = null;

    #[\Override]
    protected function _construct()
    {
        $this->_init('blog/attribute');
    }

    #[\Override]
    public function getAdminPageSettings(): ?array
    {
        $helper = Mage::helper('blog');
        return match ($this->getEntityType()->getEntityTypeCode()) {
            Maho_Blog_Model_Post::ENTITY => [
                'acl' => 'system/attributes/blog_post',
                'title' => $helper->__('Blog Post Attributes'),
                'sets_title' => $helper->__('Blog Post Attribute Sets'),
                'main_tab' => 'blog/adminhtml_attribute_edit_tab_main',
            ],
            Maho_Blog_Model_Category::ENTITY => [
                'acl' => 'system/attributes/blog_category',
                'title' => $helper->__('Blog Category Attributes'),
                'sets_title' => $helper->__('Blog Category Attribute Sets'),
                'main_tab' => 'blog/adminhtml_attribute_edit_tab_main',
            ],
            default => null,
        };
    }

    /**
     * Processing object before save data
     *
     * @throws Mage_Core_Exception
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        $this->setData('modulePrefix', 'Maho_Blog');
        // The blog resources save every value for store 0
        $this->setData('is_global', self::SCOPE_GLOBAL);
        return parent::_beforeSave();
    }

    /**
     * Return is attribute global
     *
     * @return integer
     */
    public function getIsGlobal()
    {
        return $this->_getData('is_global');
    }

    /**
     * Retrieve attribute is global scope flag
     *
     * @return bool
     */
    public function isScopeGlobal()
    {
        return $this->getIsGlobal() == self::SCOPE_GLOBAL;
    }

    /**
     * Retrieve attribute is website scope website
     *
     * @return bool
     */
    public function isScopeWebsite()
    {
        return $this->getIsGlobal() == self::SCOPE_WEBSITE;
    }

    /**
     * Retrieve attribute is store scope flag
     *
     * @return bool
     */
    public function isScopeStore()
    {
        return !$this->isScopeGlobal() && !$this->isScopeWebsite();
    }
}
