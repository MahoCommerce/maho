<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

use Maho\Data\Form;

/**
 * Catalog Link Rule Target Conditions Tab
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 */
class Maho_CatalogLinkRule_Block_Adminhtml_Rule_Edit_Tab_TargetConditions extends Mage_Adminhtml_Block_Widget_Form implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $model = Mage::registry('current_linkrule');

        $form = new Form();
        $form->setHtmlIdPrefix('rule_');

        $renderer = Mage::getBlockSingleton('adminhtml/widget_form_renderer_fieldset')
            ->setTemplate('promo/fieldset.phtml')
            ->setNewChildUrl($this->getUrl('*/*/newActionHtml/form/rule_actions_fieldset'));

        $fieldset = $form->addFieldset('actions_fieldset', [
            'legend' => Mage::helper('cataloglinkrule')->__(
                'Link to target products matching the following conditions (leave blank for all products)',
            ),
        ])->setRenderer($renderer);

        $fieldset->addField('actions', 'text', [
            'name'     => 'actions',
            'label'    => Mage::helper('cataloglinkrule')->__('Target Conditions'),
            'title'    => Mage::helper('cataloglinkrule')->__('Target Conditions'),
            'required' => true,
        ])->setRule($model)->setRenderer(Mage::getBlockSingleton('rule/actions'));

        $form->setValues($model->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    #[\Override]
    public function getTabLabel(): string
    {
        return Mage::helper('cataloglinkrule')->__('Target Product Conditions');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return Mage::helper('cataloglinkrule')->__('Target Product Conditions');
    }

    #[\Override]
    public function canShowTab(): bool
    {
        return true;
    }

    #[\Override]
    public function isHidden(): bool
    {
        return false;
    }
}
