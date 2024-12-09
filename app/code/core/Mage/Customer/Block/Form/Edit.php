<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer edit form block
 *
 * @category   Mage
 * @package    Mage_Customer
 */
class Mage_Customer_Block_Form_Edit extends Mage_Customer_Block_Account_Dashboard
{
    /**
     * Create form block for template file
     */
    #[\Override]
    protected function _beforeToHtml()
    {
        /** @var Mage_Customer_Model_Form $form */
        $form = Mage::getModel('customer/form');
        $form->setFormCode('customer_account_edit')
             ->setEntity($this->getCustomer())
             ->initDefaultValues();

        /** @var Mage_Eav_Block_Widget_Form $block */
        $block = $this->getLayout()->createBlock('eav/widget_form');
        $block->setTranslationHelper($this->helper('customer'));
        $block->setForm($form);

        $groups = array_keys($block->getGroupedAttributes());
        if ($groups[0] === 'General') {
            $block->setDefaultLabel('Account Information');
        }
        $this->setChild('form_customer_account_edit', $block);

        return parent::_beforeToHtml();
    }
}
