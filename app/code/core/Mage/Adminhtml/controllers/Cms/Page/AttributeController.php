<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Cms_Page_AttributeController extends Mage_Adminhtml_Controller_Eav_Attribute
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'cms/page_attributes';

    #[\Override]
    protected function getEntityTypeCode(): string
    {
        return Mage_Cms_Model_Page::ENTITY;
    }

    #[\Override]
    protected function getPageTitles(): array
    {
        return [$this->__('CMS'), $this->__('Page Fields')];
    }

    #[\Override]
    protected function getSavedMessage(): string
    {
        return Mage::helper('cms')->__('The field has been saved.');
    }

    #[\Override]
    protected function getDeletedMessage(): string
    {
        return Mage::helper('cms')->__('The field has been deleted. The pages keep its values.');
    }

    #[\Override]
    #[Maho\Config\Route('/admin/cms_page_attribute/index')]
    public function indexAction(): void
    {
        parent::indexAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/cms_page_attribute/new')]
    public function newAction(): void
    {
        parent::newAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/cms_page_attribute/edit')]
    public function editAction(): void
    {
        parent::editAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/cms_page_attribute/validate')]
    public function validateAction(): void
    {
        parent::validateAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/cms_page_attribute/save')]
    public function saveAction(): void
    {
        parent::saveAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/cms_page_attribute/delete')]
    public function deleteAction(): void
    {
        parent::deleteAction();
    }
}
