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

class Maho_Blog_Block_Adminhtml_Post_Edit_Tab_Meta extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    protected function _prepareForm()
    {
        $model = Mage::registry('blog_post');

        $form = new \Maho\Data\Form();
        $form->setHtmlIdPrefix('post_');

        $fieldset = $form->addFieldset('meta_fieldset', [
            'legend' => Mage::helper('blog')->__('Meta Information'),
            'class' => 'fieldset-wide',
        ]);

        $fieldset->addField('meta_title', 'text', [
            'name' => 'meta_title',
            'label' => Mage::helper('blog')->__('Meta Title'),
            'title' => Mage::helper('blog')->__('Meta Title'),
        ]);

        $fieldset->addField('meta_keywords', 'textarea', [
            'name' => 'meta_keywords',
            'label' => Mage::helper('blog')->__('Meta Keywords'),
            'title' => Mage::helper('blog')->__('Meta Keywords'),
        ]);

        $fieldset->addField('meta_description', 'textarea', [
            'name' => 'meta_description',
            'label' => Mage::helper('blog')->__('Meta Description'),
            'title' => Mage::helper('blog')->__('Meta Description'),
        ]);

        $fieldset->addField('meta_robots', 'select', [
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

        $form->setValues($model->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('blog')->__('Meta Data');
    }

    #[\Override]
    public function getTabTitle()
    {
        return Mage::helper('blog')->__('Meta Data');
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
