<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Block_Adminhtml_Post_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $model = Mage::registry('blog_post');

        $form = new Varien_Data_Form([
            'id' => 'edit_form',
            'action' => $this->getUrl('*/*/save', ['id' => $this->getRequest()->getParam('id')]),
            'method' => 'post',
            'enctype' => 'multipart/form-data',
        ]);

        $form->setUseContainer(true);
        $this->setForm($form);

        $fieldset = $form->addFieldset('blog_form', [
            'legend' => Mage::helper('blog')->__('General Information'),
            'class' => 'fieldset-wide',
        ]);

        if ($model->getEntityId()) {
            $fieldset->addField('entity_id', 'hidden', [
                'name' => 'entity_id',
            ]);
        }

        $fieldset->addField('title', 'text', [
            'label' => Mage::helper('blog')->__('Title'),
            'class' => 'required-entry',
            'required' => true,
            'name' => 'title',
        ]);

        $fieldset->addField('url_key', 'text', [
            'label' => Mage::helper('blog')->__('URL Key'),
            'class' => 'required-entry',
            'required' => true,
            'name' => 'url_key',
        ]);

        if (!Mage::app()->isSingleStoreMode()) {
            $field = $fieldset->addField('stores', 'multiselect', [
                'name'      => 'stores[]',
                'label'     => Mage::helper('blog')->__('Store View'),
                'title'     => Mage::helper('blog')->__('Store View'),
                'required'  => true,
                'values'    => Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm(false, true),
            ]);
            $renderer = $this->getStoreSwitcherRenderer();
            $field->setRenderer($renderer);
        } else {
            $fieldset->addField('stores', 'hidden', [
                'name'      => 'stores[]',
                'value'     => Mage::app()->getStore(true)->getId(),
            ]);
            $model->setStoreId(Mage::app()->getStore(true)->getId());
        }

        $fieldset->addField('is_active', 'select', [
            'label'     => Mage::helper('blog')->__('Status'),
            'title'     => Mage::helper('blog')->__('Status'),
            'name'      => 'is_active',
            'required'  => true,
            'options'   => [
                '1' => Mage::helper('blog')->__('Enabled'),
                '0' => Mage::helper('blog')->__('Disabled'),
            ],
        ]);
        if (!$model->getId()) {
            $model->setData('is_active', '1');
        }

        $fieldset->addField('publish_date', 'date', [
            'name'      => 'publish_date',
            'label'     => Mage::helper('blog')->__('Publishing Date'),
            'format'    => Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
            'required'  => false,
        ]);

        $fieldset->addField('content', 'editor', [
            'name' => 'content',
            'label' => Mage::helper('blog')->__('Content'),
            'title' => Mage::helper('blog')->__('Content'),
            'style'     => 'height:36em',
            'required' => true,
            'config' => Mage::getSingleton('cms/wysiwyg_config')->getConfig(),
        ]);

        if (Mage::registry('blog_post')) {
            $form->setValues(Mage::registry('blog_post')->getData());
        }

        return parent::_prepareForm();
    }
}
