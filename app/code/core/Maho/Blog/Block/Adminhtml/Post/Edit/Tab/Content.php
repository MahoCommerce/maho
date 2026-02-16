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

class Maho_Blog_Block_Adminhtml_Post_Edit_Tab_Content extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
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

        $form = new \Maho\Data\Form();
        $form->setHtmlIdPrefix('post_');

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
                'name' => 'stores[]',
                'label' => Mage::helper('blog')->__('Store View'),
                'title' => Mage::helper('blog')->__('Store View'),
                'required' => true,
                'values' => Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm(false, true),
            ]);
            $renderer = $this->getStoreSwitcherRenderer();
            $field->setRenderer($renderer);
        } else {
            $fieldset->addField('stores', 'hidden', [
                'name' => 'stores[]',
                'value' => Mage::app()->getStore(true)->getId(),
            ]);
            $model->setStoreId(Mage::app()->getStore(true)->getId());
        }

        $fieldset->addField('is_active', 'select', [
            'label' => Mage::helper('blog')->__('Status'),
            'title' => Mage::helper('blog')->__('Status'),
            'name' => 'is_active',
            'required' => true,
            'options' => [
                '1' => Mage::helper('blog')->__('Enabled'),
                '0' => Mage::helper('blog')->__('Disabled'),
            ],
        ]);
        if (!$model->getId()) {
            $model->setData('is_active', '1');
        }

        $fieldset->addField('publish_date', 'date', [
            'name' => 'publish_date',
            'label' => Mage::helper('blog')->__('Publishing Date'),
            'format' => Mage_Core_Model_Locale::DATE_FORMAT,
            'required' => true,
            'class' => 'required-entry',
        ]);

        if (!$model->getId() && !$model->getData('publish_date')) {
            $model->setData('publish_date', Mage_Core_Model_Locale::today());
        }

        $fieldset->addField('content', 'editor', [
            'name' => 'content',
            'label' => Mage::helper('blog')->__('Content'),
            'title' => Mage::helper('blog')->__('Content'),
            'style' => 'height:36em;',
            'required' => true,
            'config' => Mage::getSingleton('cms/wysiwyg_config')->getConfig(),
        ]);

        $form->setValues($model->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('blog')->__('Content');
    }

    #[\Override]
    public function getTabTitle()
    {
        return Mage::helper('blog')->__('Post Content');
    }

    #[\Override]
    public function canShowTab()
    {
        return true;
    }

    #[\Override]
    public function isHidden()
    {
        return false;
    }
}
