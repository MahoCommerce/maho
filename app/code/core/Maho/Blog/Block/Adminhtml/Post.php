<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

class Maho_Blog_Block_Adminhtml_Post extends Mage_Adminhtml_Block_Widget_Container
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('blog/post.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->_addButton('add_new', [
            'label'   => Mage::helper('blog')->__('Add Post'),
            'onclick' => Mage::helper('core/js')->getSetLocationJs($this->getUrl('*/*/new')),
            'class'   => 'add',
        ]);

        $this->setChild('grid', $this->getLayout()->createBlock('blog/adminhtml_post_grid', 'post.grid'));
        return parent::_prepareLayout();
    }

    public function isSingleStoreMode(): bool
    {
        if (!Mage::app()->isSingleStoreMode()) {
            return false;
        }
        return true;
    }
}
