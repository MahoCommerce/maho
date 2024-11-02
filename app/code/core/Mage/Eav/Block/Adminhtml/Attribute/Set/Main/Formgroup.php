<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Block_Adminhtml_Attribute_Set_Main_Formgroup extends Mage_Adminhtml_Block_Widget_Form
{
    public function __construct()
    {
        parent::__construct();
    }

    #[\Override]
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form();

        $fieldset = $form->addFieldset('set_fieldset', ['legend' => Mage::helper('eav')->__('Add New Group')]);

        $fieldset->addField('attribute_group_name', 'text', [
            'label' => Mage::helper('eav')->__('Name'),
            'name' => 'attribute_group_name',
            'required' => true,
        ]);

        $fieldset->addField('submit', 'note', [
            'text' => $this->getLayout()->createBlock('adminhtml/widget_button')
                           ->setData([
                               'label'     => Mage::helper('eav')->__('Add Group'),
                               'onclick'   => 'this.form.submit();',
                               'class' => 'add'
                           ])
                           ->toHtml(),
        ]);

        $fieldset->addField('attribute_set_id', 'hidden', [
            'name' => 'attribute_set_id',
            'value' => $this->_getSetId(),
        ]);

        $form->setUseContainer(true);
        $form->setMethod('post');
        $form->setAction($this->getUrl('*/*/save'));
        $this->setForm($form);
        return $this;
    }

    protected function _getSetId()
    {
        return ((int) $this->getRequest()->getParam('id') > 0)
                    ? (int) $this->getRequest()->getParam('id')
                    : Mage::registry('entity_type')->getDefaultAttributeSetId();
    }
}
