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

class Maho_Blog_Block_Adminhtml_Post_Edit_Tab_Categories extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    protected function _prepareForm()
    {
        $model = Mage::registry('blog_post');

        $form = new \Maho\Data\Form();
        $form->setHtmlIdPrefix('post_');

        $fieldset = $form->addFieldset('categories_fieldset', [
            'legend' => Mage::helper('blog')->__('Categories'),
            'class' => 'fieldset-wide',
        ]);

        $categoryOptions = $this->_getCategoryOptions();
        $selectedCategories = $model->getId() ? $model->getCategories() : [];

        $fieldset->addField('categories', 'multiselect', [
            'name' => 'categories[]',
            'label' => Mage::helper('blog')->__('Categories'),
            'title' => Mage::helper('blog')->__('Categories'),
            'required' => false,
            'values' => $categoryOptions,
        ]);

        $form->setValues(['categories' => $selectedCategories]);
        $this->setForm($form);

        return parent::_prepareForm();
    }

    /**
     * Get active categories as options with indentation by level
     */
    protected function _getCategoryOptions(): array
    {
        $options = [];
        $collection = Mage::getResourceModel('blog/category_collection')
            ->addRootFilter()
            ->addActiveFilter()
            ->setOrder('path', 'ASC');

        foreach ($collection as $category) {
            $indent = str_repeat('--', max(0, (int) $category->getLevel() - 1));
            $prefix = $indent ? $indent . ' ' : '';
            $options[] = [
                'value' => $category->getId(),
                'label' => $prefix . $category->getName(),
            ];
        }

        return $options;
    }

    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('blog')->__('Categories');
    }

    #[\Override]
    public function getTabTitle()
    {
        return Mage::helper('blog')->__('Post Categories');
    }

    #[\Override]
    public function canShowTab()
    {
        return Mage::helper('blog')->areCategoriesEnabled();
    }

    #[\Override]
    public function isHidden()
    {
        return !Mage::helper('blog')->areCategoriesEnabled();
    }
}
