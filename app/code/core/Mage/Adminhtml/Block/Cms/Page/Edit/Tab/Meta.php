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

class Mage_Adminhtml_Block_Cms_Page_Edit_Tab_Meta extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    protected function _prepareForm()
    {
        /*
         * Checking if user have permissions to save information
         */
        if ($this->_isAllowedAction('save')) {
            $isElementDisabled = false;
        } else {
            $isElementDisabled = true;
        }

        $form = new \Maho\Data\Form();

        $form->setHtmlIdPrefix('page_');

        $model = Mage::registry('cms_page');

        $fieldset = $form->addFieldset('meta_fieldset', ['legend' => Mage::helper('cms')->__('Meta Data'), 'class' => 'fieldset-wide']);

        $fieldset->addField('meta_keywords', 'textarea', [
            'name' => 'meta_keywords',
            'label' => Mage::helper('cms')->__('Keywords'),
            'title' => Mage::helper('cms')->__('Meta Keywords'),
            'disabled'  => $isElementDisabled,
        ]);

        $fieldset->addField('meta_description', 'textarea', [
            'name' => 'meta_description',
            'label' => Mage::helper('cms')->__('Description'),
            'title' => Mage::helper('cms')->__('Meta Description'),
            'disabled'  => $isElementDisabled,
        ]);

        $fieldset->addField('meta_robots', 'select', [
            'name' => 'meta_robots',
            'label' => Mage::helper('cms')->__('Robots'),
            'title' => Mage::helper('cms')->__('Meta Robots'),
            'values' => Mage::getSingleton('page/source_robots')->toOptionArray(),
            'disabled'  => $isElementDisabled,
        ]);

        Mage::dispatchEvent('adminhtml_cms_page_edit_tab_meta_prepare_form', ['form' => $form]);

        $form->setValues($model->getData());

        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Prepare label for tab
     *
     * @return string
     */
    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('cms')->__('Meta Data');
    }

    /**
     * Prepare title for tab
     *
     * @return string
     */
    #[\Override]
    public function getTabTitle()
    {
        return Mage::helper('cms')->__('Meta Data');
    }

    /**
     * Returns status flag about this tab can be showen or not
     *
     * @return true
     */
    #[\Override]
    public function canShowTab()
    {
        return true;
    }

    /**
     * Returns status flag about this tab hidden or not
     *
     * @return false
     */
    #[\Override]
    public function isHidden()
    {
        return false;
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
}
