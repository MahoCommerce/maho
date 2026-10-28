<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Catalog_Category_AttributeController extends Mage_Adminhtml_Controller_Eav_Attribute
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'catalog/attributes/category_attributes';

    #[\Override]
    protected function getEntityTypeCode(): string
    {
        return Mage_Catalog_Model_Category::ENTITY;
    }

    #[\Override]
    protected function getPageTitles(): array
    {
        return [$this->__('Catalog'), $this->__('Attributes'), $this->__('Manage Category Attributes')];
    }

    #[\Override]
    protected function getMainTabBlock(): string
    {
        return 'adminhtml/catalog_category_attribute_edit_tab_main';
    }

    #[\Override]
    protected function getAllowedTags(): array
    {
        return explode(',', (string) Mage::getStoreConfig('system/catalog/frontend/allowed_html_tags_list'));
    }

    #[\Override]
    #[Maho\Config\Route('/admin/catalog_category_attribute/index')]
    public function indexAction(): void
    {
        parent::indexAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/catalog_category_attribute/new')]
    public function newAction(): void
    {
        parent::newAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/catalog_category_attribute/edit')]
    public function editAction(): void
    {
        parent::editAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/catalog_category_attribute/validate')]
    public function validateAction(): void
    {
        parent::validateAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/catalog_category_attribute/save')]
    public function saveAction(): void
    {
        parent::saveAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/catalog_category_attribute/delete')]
    public function deleteAction(): void
    {
        parent::deleteAction();
    }
}
