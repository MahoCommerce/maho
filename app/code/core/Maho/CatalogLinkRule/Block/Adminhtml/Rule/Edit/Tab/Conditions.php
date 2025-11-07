<?php
/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Maho\Data\Form;

class Maho_CatalogLinkRule_Block_Adminhtml_Rule_Edit_Tab_Conditions
    extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    protected function _prepareForm()
    {
        $model = Mage::registry('current_linkrule');

        $form = new Form();

        $form->setHtmlIdPrefix('rule_');

        $renderer = Mage::getBlockSingleton('adminhtml/widget_form_renderer_fieldset')
            ->setTemplate('promo/fieldset.phtml')
            ->setNewChildUrl($this->getUrl('*/*/newConditionHtml/form/rule_conditions_fieldset'));

        $fieldset = $form->addFieldset('conditions_fieldset', [
            'legend' => Mage::helper('cataloglinkrule')->__(
                'Apply to products matching the following conditions (leave blank for all products)'
            )
        ])->setRenderer($renderer);

        $fieldset->addField('conditions', 'text', [
            'name'     => 'conditions',
            'label'    => Mage::helper('cataloglinkrule')->__('Conditions'),
            'title'    => Mage::helper('cataloglinkrule')->__('Conditions'),
            'required' => true,
        ])->setRule($model)->setRenderer(Mage::getBlockSingleton('rule/conditions'));

        $form->setValues($model->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('cataloglinkrule')->__('Source Product Conditions');
    }

    #[\Override]
    public function getTabTitle()
    {
        return Mage::helper('cataloglinkrule')->__('Source Product Conditions');
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
