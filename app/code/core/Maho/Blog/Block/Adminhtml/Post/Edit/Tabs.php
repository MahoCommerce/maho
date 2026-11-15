<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Block_Adminhtml_Post_Edit_Tabs extends Maho_Blog_Block_Adminhtml_Edit_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('post_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('blog')->__('Post Information'));
    }

    #[\Override]
    public function getEntity(): Maho_Blog_Model_Post
    {
        return Mage::registry('blog_post');
    }

    #[\Override]
    protected function _beforeToHtml()
    {
        $this->addAttributeTabs();
        return parent::_beforeToHtml();
    }
}
