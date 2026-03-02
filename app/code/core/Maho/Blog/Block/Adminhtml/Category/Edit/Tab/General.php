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

class Maho_Blog_Block_Adminhtml_Category_Edit_Tab_General extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    protected function _prepareForm()
    {
        $model = Mage::registry('blog_category');

        $form = new \Maho\Data\Form();
        $form->setHtmlIdPrefix('category_');

        $fieldset = $form->addFieldset('blog_category_form', [
            'legend' => Mage::helper('blog')->__('General Information'),
            'class' => 'fieldset-wide',
        ]);

        $form->setFieldNameSuffix('category');

        if ($model->getEntityId()) {
            $fieldset->addField('entity_id', 'hidden', [
                'name' => 'entity_id',
            ]);
        }

        $fieldset->addField('name', 'text', [
            'label' => Mage::helper('blog')->__('Name'),
            'class' => 'required-entry',
            'required' => true,
            'name' => 'name',
        ]);

        $fieldset->addField('url_key', 'text', [
            'label' => Mage::helper('blog')->__('URL Key'),
            'name' => 'url_key',
        ]);

        // Parent category select
        $parentOptions = $this->_getParentOptions($model);
        $fieldset->addField('parent_id', 'select', [
            'label' => Mage::helper('blog')->__('Parent Category'),
            'name' => 'parent_id',
            'options' => $parentOptions,
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

        $fieldset->addField('position', 'text', [
            'label' => Mage::helper('blog')->__('Position'),
            'name' => 'position',
            'value' => '0',
        ]);

        $form->setValues($model->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Build parent category options with indentation by level
     */
    protected function _getParentOptions(?Maho_Blog_Model_Category $currentCategory): array
    {
        $options = [
            Maho_Blog_Model_Category::ROOT_PARENT_ID => Mage::helper('blog')->__('-- Top Level --'),
        ];

        $collection = Mage::getResourceModel('blog/category_collection')
            ->addRootFilter()
            ->addActiveFilter()
            ->setOrder('path', 'ASC');

        foreach ($collection as $category) {
            // Skip the current category and its children to prevent circular references
            if ($currentCategory && $currentCategory->getId()) {
                if ($category->getId() == $currentCategory->getId()) {
                    continue;
                }
                $currentPath = $currentCategory->getPath();
                if ($currentPath && str_starts_with($category->getPath(), $currentPath . '/')) {
                    continue;
                }
            }

            $indent = str_repeat('--', (int) $category->getLevel());
            $prefix = $indent ? $indent . ' ' : '';
            $options[$category->getId()] = $prefix . $category->getName();
        }

        return $options;
    }

    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('blog')->__('General');
    }

    #[\Override]
    public function getTabTitle()
    {
        return Mage::helper('blog')->__('General Information');
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
