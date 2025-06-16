<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Block_Adminhtml_Segment_Edit_Tab_Conditions extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    protected function _prepareForm(): self
    {
        $model = Mage::registry('current_customer_segment');
        $form = new Varien_Data_Form();

        $form->setHtmlIdPrefix('rule_');

        $renderer = Mage::getBlockSingleton('adminhtml/widget_form_renderer_fieldset')
            ->setTemplate('promo/fieldset.phtml')
            ->setNewChildUrl($this->getUrl('*/*/newConditionHtml/form/rule_conditions_fieldset'));

        $fieldset = $form->addFieldset('conditions_fieldset', [
            'legend' => Mage::helper('customersegmentation')->__('Conditions (leave blank for all customers)'),
        ])->setRenderer($renderer);

        $fieldset->addField('conditions', 'text', [
            'name'           => 'conditions',
            'label'          => Mage::helper('customersegmentation')->__('Conditions'),
            'title'          => Mage::helper('customersegmentation')->__('Conditions'),
            'required'       => true,
        ])->setRule($model)->setRenderer(Mage::getBlockSingleton('rule/conditions'));

        $form->setValues($model->getData());
        $this->setForm($form);
        return parent::_prepareForm();
    }

    public function getTabLabel(): string
    {
        return Mage::helper('customersegmentation')->__('Conditions');
    }

    public function getTabTitle(): string
    {
        return Mage::helper('customersegmentation')->__('Conditions');
    }

    public function canShowTab(): bool
    {
        return true;
    }

    public function isHidden(): bool
    {
        return false;
    }
}
