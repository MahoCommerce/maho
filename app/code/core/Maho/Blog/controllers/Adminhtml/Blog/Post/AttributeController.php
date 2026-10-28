<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Adminhtml_Blog_Post_AttributeController extends Mage_Adminhtml_Controller_Eav_Attribute
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'cms/blog/post_attributes';

    #[\Override]
    protected function getEntityTypeCode(): string
    {
        return Maho_Blog_Model_Post::ENTITY;
    }

    #[\Override]
    protected function getPageTitles(): array
    {
        return [$this->__('CMS'), $this->__('Blog'), $this->__('Post Attributes')];
    }

    #[\Override]
    protected function getMainTabBlock(): string
    {
        return 'blog/adminhtml_attribute_edit_tab_main';
    }

    #[\Override]
    protected function prepareSaveData(array $data, Mage_Eav_Model_Entity_Attribute $model): array
    {
        $data['is_global'] = Maho_Blog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL;
        return $data;
    }

    #[\Override]
    #[Maho\Config\Route('/admin/blog_post_attribute/index')]
    public function indexAction(): void
    {
        parent::indexAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/blog_post_attribute/new')]
    public function newAction(): void
    {
        parent::newAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/blog_post_attribute/edit')]
    public function editAction(): void
    {
        parent::editAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/blog_post_attribute/validate')]
    public function validateAction(): void
    {
        parent::validateAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/blog_post_attribute/save')]
    public function saveAction(): void
    {
        parent::saveAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/blog_post_attribute/delete')]
    public function deleteAction(): void
    {
        parent::deleteAction();
    }
}
