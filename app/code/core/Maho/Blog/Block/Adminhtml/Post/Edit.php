<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Block_Adminhtml_Post_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'id';
        $this->_blockGroup = 'blog';
        $this->_controller = 'adminhtml_post';

        $this->_updateButton('save', 'label', Mage::helper('blog')->__('Save Post'));
        $this->_updateButton('delete', 'label', Mage::helper('blog')->__('Delete Post'));

        $this->_addButton('saveandcontinue', [
            'label'     => Mage::helper('adminhtml')->__('Save and Continue Edit'),
            'onclick'   => 'saveAndContinueEdit()',
            'class'     => 'save',
        ], -100);

        $this->_formScripts[] = "
            function saveAndContinueEdit(){
                editForm.submit(document.getElementById('edit_form').action+'back/edit/');
            }
        ";
    }

    #[\Override]
    public function getHeaderText()
    {
        if (Mage::registry('blog_post')->getId()) {
            return Mage::helper('blog')->__("Edit Post '%s'", $this->escapeHtml(Mage::registry('blog_post')->getTitle()));
        }
        return Mage::helper('blog')->__('New Post');
    }
}
