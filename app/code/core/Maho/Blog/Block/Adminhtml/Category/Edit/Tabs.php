<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Block_Adminhtml_Category_Edit_Tabs extends Maho_Blog_Block_Adminhtml_Edit_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('category_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('blog')->__('Category Information'));
    }

    #[\Override]
    public function getEntity(): Maho_Blog_Model_Category
    {
        return Mage::registry('blog_category');
    }

    #[\Override]
    protected function getFieldNameSuffix(): ?string
    {
        return 'category';
    }

    #[\Override]
    protected function _beforeToHtml()
    {
        $this->addAttributeTabs();
        $this->addTab('posts_section', [
            'label' => Mage::helper('blog')->__('Posts'),
            'title' => Mage::helper('blog')->__('Category Posts'),
            'url'   => $this->getUrl('*/*/posts', ['_current' => true]),
            'class' => 'ajax',
        ]);

        return parent::_beforeToHtml();
    }
}
