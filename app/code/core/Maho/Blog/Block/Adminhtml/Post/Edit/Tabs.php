<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Block_Adminhtml_Post_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    protected $_attributeTabBlock = 'adminhtml/catalog_product_edit_tab_attributes';

    public function __construct()
    {
        parent::__construct();
        $this->setId('post_info_tabs');
        $this->setDestElementId('post_edit_form');
        $this->setTitle(Mage::helper('blog')->__('Post'));
    }

    public function getPost(): Maho_Blog_Model_Post
    {
        if (!($this->getData('post') instanceof Maho_Blog_Model_Post)) {
            $this->setData('post', Mage::registry('post'));
        }
        return $this->getData('post');
    }

    /**
     * Getting attribute block name for tabs
     *
     * @return string|null
     */
    public function getAttributeTabBlock()
    {
        if (is_null(Mage::helper('adminhtml/catalog')->getAttributeTabBlock())) {
            return $this->_attributeTabBlock;
        }
        return Mage::helper('adminhtml/catalog')->getAttributeTabBlock();
    }

    public function setAttributeTabBlock($attributeTabBlock): self
    {
        $this->_attributeTabBlock = $attributeTabBlock;
        return $this;
    }

    /**
     * Translate html content
     */
    protected function _translateHtml(string $html): string
    {
        Mage::getSingleton('core/translate_inline')->processResponseBody($html);
        return $html;
    }
}
