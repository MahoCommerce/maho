<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Adminhtml_Blog_Post_SetController extends Mage_Adminhtml_Controller_Eav_Set
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'cms/blog/post_sets';

    #[\Override]
    protected function getEntityTypeCode(): string
    {
        return Maho_Blog_Model_Post::ENTITY;
    }

    #[\Override]
    protected function getPageTitles(): array
    {
        return [$this->__('CMS'), $this->__('Blog'), $this->__('Post Attribute Sets')];
    }

    #[\Override]
    #[Maho\Config\Route('/admin/blog_post_set/index')]
    public function indexAction(): void
    {
        parent::indexAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/blog_post_set/setGrid')]
    public function setGridAction(): void
    {
        parent::setGridAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/blog_post_set/add')]
    public function addAction(): void
    {
        parent::addAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/blog_post_set/edit')]
    public function editAction(): void
    {
        parent::editAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/blog_post_set/save')]
    public function saveAction(): void
    {
        parent::saveAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/blog_post_set/delete')]
    public function deleteAction(): void
    {
        parent::deleteAction();
    }
}
