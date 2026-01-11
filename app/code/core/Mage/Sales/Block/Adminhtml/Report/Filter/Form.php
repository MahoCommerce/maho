<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Block_Adminhtml_Report_Filter_Form extends Mage_Adminhtml_Block_Report_Filter_Form
{
    /**
     * Add fields to base fieldset which are general to sales reports
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareForm()
    {
        parent::_prepareForm();
        $form = $this->getForm();
        $htmlIdPrefix = $form->getHtmlIdPrefix();
        /** @var \Maho\Data\Form\Element\Fieldset $fieldset */
        $fieldset = $this->getForm()->getElement('base_fieldset');

        if (is_object($fieldset) && $fieldset instanceof \Maho\Data\Form\Element\Fieldset) {
            $statuses = Mage::getModel('sales/order_config')->getStatuses();
            $values = [];
            foreach ($statuses as $code => $label) {
                if (!str_contains($code, 'pending')) {
                    $values[] = [
                        'label' => Mage::helper('reports')->__($label),
                        'value' => $code,
                    ];
                }
            }

            $fieldset->addField('show_order_statuses', 'select', [
                'name'      => 'show_order_statuses',
                'label'     => Mage::helper('reports')->__('Order Status'),
                'options'   => [
                    '0' => Mage::helper('reports')->__('Any'),
                    '1' => Mage::helper('reports')->__('Specified'),
                ],
                'note'      => Mage::helper('reports')->__('Applies to Any of the Specified Order Statuses'),
            ], 'to');

            $fieldset->addField('order_statuses', 'multiselect', [
                'name'      => 'order_statuses',
                'values'    => $values,
                'display'   => 'none',
            ], 'show_order_statuses');

            // define field dependencies
            /** @var Mage_Adminhtml_Block_Widget_Form_Element_Dependence $block */
            $block = $this->getLayout()->createBlock('adminhtml/widget_form_element_dependence');
            if ($this->getFieldVisibility('show_order_statuses') && $this->getFieldVisibility('order_statuses')) {
                $this->setChild('form_after', $block
                    ->addFieldMap("{$htmlIdPrefix}show_order_statuses", 'show_order_statuses')
                    ->addFieldMap("{$htmlIdPrefix}order_statuses", 'order_statuses')
                    ->addFieldDependence('order_statuses', 'show_order_statuses', '1'));
            }
        }

        return $this;
    }
}
