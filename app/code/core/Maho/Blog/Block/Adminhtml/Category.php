<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Block_Adminhtml_Category extends Mage_Adminhtml_Block_Widget_Container
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('blog/category.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $this->_addButton('add_new', [
            'label'   => Mage::helper('blog')->__('Add Category'),
            'onclick' => Mage::helper('core/js')->getSetLocationJs($this->getUrl('*/*/new')),
            'class'   => 'add',
        ]);

        $this->setChild('grid', $this->getLayout()->createBlock('blog/adminhtml_category_grid', 'category.grid'));
        return parent::_prepareLayout();
    }
}
