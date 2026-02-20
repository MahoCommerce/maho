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

class Mage_Adminhtml_Block_Review_Add_Form extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm()
    {
        $form = new \Maho\Data\Form();

        $fieldset = $form->addFieldset('add_review_form', ['legend' => Mage::helper('review')->__('Review Details'), 'class' => 'fieldset-wide']);

        $fieldset->addField('product_name', 'note', [
            'label'     => Mage::helper('review')->__('Product'),
            'text'      => 'product_name',
        ]);

        $fieldset->addField('detailed_rating', 'note', [
            'label'     => Mage::helper('review')->__('Product Rating'),
            'required'  => true,
            'text'      => '<div id="rating_detail">'
                . $this->getLayout()->createBlock('adminhtml/review_rating_detailed')->toHtml() . '</div>',
        ]);

        $fieldset->addField('status_id', 'select', [
            'label'     => Mage::helper('review')->__('Status'),
            'required'  => true,
            'name'      => 'status_id',
            'values'    => Mage::helper('review')->getReviewStatusesOptionArray(),
        ]);

        /**
         * Check is single store mode
         */
        if (!Mage::app()->isSingleStoreMode()) {
            $field = $fieldset->addField('select_stores', 'multiselect', [
                'label'     => Mage::helper('review')->__('Visible In'),
                'required'  => true,
                'name'      => 'select_stores[]',
                'values'    => Mage::getSingleton('adminhtml/system_store')->getStoreValuesForForm(),
            ]);
            $renderer = $this->getStoreSwitcherRenderer();
            $field->setRenderer($renderer);
        }

        $fieldset->addField('nickname', 'text', [
            'name'      => 'nickname',
            'title'     => Mage::helper('review')->__('Nickname'),
            'label'     => Mage::helper('review')->__('Nickname'),
            'maxlength' => '50',
            'required'  => true,
        ]);

        $fieldset->addField('title', 'text', [
            'name'      => 'title',
            'title'     => Mage::helper('review')->__('Summary of Review'),
            'label'     => Mage::helper('review')->__('Summary of Review'),
            'maxlength' => '255',
            'required'  => true,
        ]);

        $fieldset->addField('detail', 'textarea', [
            'name'      => 'detail',
            'title'     => Mage::helper('review')->__('Review'),
            'label'     => Mage::helper('review')->__('Review'),
            'style'     => 'height:24em;',
            'required'  => true,
        ]);

        $fieldset->addField('product_id', 'hidden', [
            'name'      => 'product_id',
        ]);

        $form->setMethod('post');
        $form->setUseContainer(true);
        $form->setId('edit_form');
        $form->setAction($this->getUrl('*/*/post'));

        $this->setForm($form);
        return $this;
    }
}
