<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Cms_Page_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    /**
     * Initialize cms page edit block
     */
    public function __construct()
    {
        $this->_objectId   = 'page_id';
        $this->_controller = 'cms_page';

        parent::__construct();

        if ($this->_isAllowedAction('save')) {
            $this->_updateButton('save', 'label', Mage::helper('cms')->__('Save Page'));
            $this->_addButton('saveandcontinue', [
                'label'     => Mage::helper('adminhtml')->__('Save and Continue Edit'),
                'onclick'   => Mage::helper('core/js')->getSaveAndContinueEditJs($this->_getSaveAndContinueUrl()),
                'class'     => 'save',
            ], -100);
        } else {
            $this->_removeButton('save');
        }

        if ($this->_isAllowedAction('delete')) {
            $this->_updateButton('delete', 'label', Mage::helper('cms')->__('Delete Page'));
        } else {
            $this->_removeButton('delete');
        }
    }

    /**
     * Retrieve text for header element depending on loaded page
     *
     * @return string
     */
    #[\Override]
    public function getHeaderText()
    {
        if (Mage::registry('cms_page')->getId()) {
            return Mage::helper('cms')->__("Edit Page '%s'", $this->escapeHtml(Mage::registry('cms_page')->getTitle()));
        }
        return Mage::helper('cms')->__('New Page');
    }

    /**
     * Check permission for passed action
     *
     * @param string $action
     * @return bool
     */
    protected function _isAllowedAction($action)
    {
        return Mage::getSingleton('admin/session')->isAllowed('cms/page/' . $action);
    }

    /**
     * Getter of url for "Save and Continue" button
     * tab_id will be replaced by desired by JS later
     *
     * @return string
     */
    protected function _getSaveAndContinueUrl()
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
        $tabsBlock = $this->getLayout()->getBlock('cms_page_edit_tabs');
        if ($tabsBlock) {
            $tabsBlockJsObject = $tabsBlock->getJsObjectName();
            $tabsBlockPrefix   = $tabsBlock->getId() . '_';
        } else {
            $tabsBlockJsObject = 'page_tabsJsTabs';
            $tabsBlockPrefix   = 'page_tabs_';
        }

        $this->_formScripts[] = '
            function saveAndContinueEdit(urlTemplate) {
                var tabsIdValue = ' . $tabsBlockJsObject . ".activeTab.id;
                var tabsBlockPrefix = '" . $tabsBlockPrefix . "';
                if (tabsIdValue.startsWith(tabsBlockPrefix)) {
                    tabsIdValue = tabsIdValue.substr(tabsBlockPrefix.length)
                }
                var url = urlTemplate.replace(/{{(\w+)}}/g, function(match, key) {
                    return key === 'tab_id' ? tabsIdValue : match;
                });
                editForm.submit(url);
            }
        ";
        return parent::_prepareLayout();
    }
}
