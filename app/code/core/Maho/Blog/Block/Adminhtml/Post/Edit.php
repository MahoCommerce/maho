<?php

declare(strict_types=1);

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
            'onclick'   => Mage::helper('core/js')->getSaveAndContinueEditJs($this->_getSaveAndContinueUrl()),
            'class'     => 'save',
        ], -100);
    }

    #[\Override]
    public function getHeaderText()
    {
        if (Mage::registry('blog_post')->getId()) {
            return Mage::helper('blog')->__("Edit Post '%s'", $this->escapeHtml(Mage::registry('blog_post')->getTitle()));
        }
        return Mage::helper('blog')->__('New Post');
    }

    /**
     * Getter of url for "Save and Continue" button
     * tab_id will be replaced by desired by JS later
     */
    protected function _getSaveAndContinueUrl(): string
    {
        return $this->getUrl('*/*/save', [
            '_current'   => true,
            'back'       => 'edit',
            'active_tab' => '{{tab_id}}',
        ]);
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $tabsBlock = $this->getLayout()->getBlock('blog_post_edit_tabs');
        if ($tabsBlock) {
            $tabsBlockJsObject = $tabsBlock->getJsObjectName();
            $tabsBlockPrefix   = $tabsBlock->getId() . '_';
        } else {
            $tabsBlockJsObject = 'blog_post_tabsJsTabs';
            $tabsBlockPrefix   = 'blog_post_tabs_';
        }

        $this->_formScripts[] = '
            function saveAndContinueEdit(urlTemplate) {
                var tabsIdValue = ' . $tabsBlockJsObject . ".activeTab.id;
                var tabsBlockPrefix = '" . $tabsBlockPrefix . "';
                if (tabsIdValue.startsWith(tabsBlockPrefix)) {
                    tabsIdValue = tabsIdValue.substr(tabsBlockPrefix.length)
                }
                var url = urlTemplate.replace(/{{(\\w+)}}/g, function(match, key) {
                    return key === 'tab_id' ? tabsIdValue : match;
                });
                editForm.submit(url);
            }
        ";
        return parent::_prepareLayout();
    }
}
