<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Block_Adminhtml_Post_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (Mage::getSingleton('cms/wysiwyg_config')->isEnabled()) {
            $this->getLayout()->getBlock('head')->setCanLoadWysiwyg(true);
        }
        return $this;
    }

    #[\Override]
    protected function _prepareForm()
    {
        $model = Mage::registry('blog_post');

        $form = new \Maho\Data\Form([
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
            'name' => 'url_key',
        ]);

        $fieldset->addField('image', 'image', [
            'label' => Mage::helper('blog')->__('Image'),
            'name' => 'image',
            'required' => false,
            'base_url' => Mage::getBaseUrl('media') . 'blog/',
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
            'format'    => Mage_Core_Model_Locale::DATE_FORMAT,
            'required'  => true,
            'class'     => 'required-entry',
        ]);

        // Pre-fill with today's date for new posts
        if (!$model->getId() && !$model->getData('publish_date')) {
            $model->setData('publish_date', Mage_Core_Model_Locale::today());
        }

        $wysiwygConfig = Mage::getSingleton('cms/wysiwyg_config')->getConfig();

        $fieldset->addField('content', 'editor', [
            'name' => 'content',
            'label' => Mage::helper('blog')->__('Content'),
            'title' => Mage::helper('blog')->__('Content'),
            'style' => 'height:36em;',
            'required' => true,
            'config' => $wysiwygConfig,
        ]);

        $metaFieldset = $form->addFieldset('meta_fieldset', [
            'legend' => Mage::helper('blog')->__('Meta Information'),
            'class' => 'fieldset-wide',
        ]);

        $metaFieldset->addField('meta_title', 'text', [
            'name' => 'meta_title',
            'label' => Mage::helper('blog')->__('Meta Title'),
            'title' => Mage::helper('blog')->__('Meta Title'),
        ]);

        $metaFieldset->addField('meta_keywords', 'textarea', [
            'name' => 'meta_keywords',
            'label' => Mage::helper('blog')->__('Meta Keywords'),
            'title' => Mage::helper('blog')->__('Meta Keywords'),
        ]);

        $metaFieldset->addField('meta_description', 'textarea', [
            'name' => 'meta_description',
            'label' => Mage::helper('blog')->__('Meta Description'),
            'title' => Mage::helper('blog')->__('Meta Description'),
        ]);

        $metaFieldset->addField('meta_robots', 'select', [
            'name' => 'meta_robots',
            'label' => Mage::helper('blog')->__('Meta Robots'),
            'title' => Mage::helper('blog')->__('Meta Robots'),
            'options' => [
                '' => Mage::helper('blog')->__('-- Use System Default --'),
                'index,follow' => Mage::helper('blog')->__('INDEX, FOLLOW'),
                'noindex,follow' => Mage::helper('blog')->__('NOINDEX, FOLLOW'),
                'index,nofollow' => Mage::helper('blog')->__('INDEX, NOFOLLOW'),
                'noindex,nofollow' => Mage::helper('blog')->__('NOINDEX, NOFOLLOW'),
            ],
        ]);

        if (Mage::registry('blog_post')) {
            $form->setValues(Mage::registry('blog_post')->getData());
        }

        return parent::_prepareForm();
    }

    #[\Override]
    protected function getStoreSwitcherRenderer(): Mage_Adminhtml_Block_Store_Switcher_Form_Renderer_Fieldset_Element
    {
        return $this->getLayout()->createBlock('adminhtml/store_switcher_form_renderer_fieldset_element');
    }
}
