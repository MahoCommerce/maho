<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Block_Adminhtml_Attribute_Set_Edit_Formset extends Mage_Adminhtml_Block_Widget_Form
{
    protected Mage_Eav_Model_Entity_Type $entityType;

    public function __construct()
    {
        $this->entityType = Mage::registry('entity_type');
        parent::__construct();
    }

    #[\Override]
    protected function _prepareForm()
    {
        $data = Mage::getModel('eav/entity_attribute_set')
            ->load($this->getRequest()->getParam('id'));

        $form = new Varien_Data_Form();

        $fieldset = $form->addFieldset('set_name', ['legend' => Mage::helper('eav')->__('Edit Set Name')]);
        $fieldset->addField('attribute_set_name', 'text', [
            'label'    => Mage::helper('eav')->__('Name'),
            'name'     => 'attribute_set_name',
            'required' => true,
            'class'    => 'required-entry validate-no-html-tags',
            'value'    => $data->getAttributeSetName()
        ]);

        if (!$this->getRequest()->getParam('id', false)) {
            $fieldset->addField('gotoEdit', 'hidden', [
                'name'  => 'gotoEdit',
                'value' => '1'
            ]);

            $sets = $this->entityType->getAttributeSetCollection()
                ->setOrder('attribute_set_name', 'asc')
                ->load()
                ->toOptionArray();

            $fieldset->addField('skeleton_set', 'select', [
                'label'    => Mage::helper('eav')->__('Based On'),
                'name'     => 'skeleton_set',
                'required' => true,
                'class'    => 'required-entry',
                'values'   => $sets,
            ]);
        }

        $form->setMethod('post');
        $form->setUseContainer(true);
        $form->setId('set_prop_form');
        $form->setAction($this->getUrl('*/*/save'));
        $form->setOnsubmit('return false;');
        $this->setForm($form);
        return $this;
    }
}
