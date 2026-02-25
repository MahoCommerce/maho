<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Block_Adminhtml_Category_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('category_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('blog')->__('Category Information'));
    }

    #[\Override]
    protected function _beforeToHtml()
    {
        $this->addTab('posts_section', [
            'label' => Mage::helper('blog')->__('Posts'),
            'title' => Mage::helper('blog')->__('Category Posts'),
            'url'   => $this->getUrl('*/*/posts', ['_current' => true]),
            'class' => 'ajax',
        ]);

        return parent::_beforeToHtml();
    }
}
