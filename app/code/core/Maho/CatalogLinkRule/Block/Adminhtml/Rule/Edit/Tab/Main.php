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

class Maho_CatalogLinkRule_Block_Adminhtml_Rule_Edit_Tab_Main
    extends Mage_Adminhtml_Block_Widget_Form
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    protected function _prepareForm()
    {
        $model = Mage::registry('current_linkrule');

        $form = new Form();
        $form->setHtmlIdPrefix('rule_');

        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => Mage::helper('cataloglinkrule')->__('Rule Information')
        ]);

        if ($model->getId()) {
            $fieldset->addField('rule_id', 'hidden', [
                'name' => 'rule[rule_id]',
            ]);
        }

        $fieldset->addField('name', 'text', [
            'name'     => 'rule[name]',
            'label'    => Mage::helper('cataloglinkrule')->__('Rule Name'),
            'title'    => Mage::helper('cataloglinkrule')->__('Rule Name'),
            'required' => true,
        ]);

        $fieldset->addField('description', 'textarea', [
            'name'  => 'rule[description]',
            'label' => Mage::helper('cataloglinkrule')->__('Description'),
            'title' => Mage::helper('cataloglinkrule')->__('Description'),
        ]);

        $fieldset->addField('is_active', 'select', [
            'name'     => 'rule[is_active]',
            'label'    => Mage::helper('cataloglinkrule')->__('Status'),
            'title'    => Mage::helper('cataloglinkrule')->__('Status'),
            'required' => true,
            'options'  => [
                '1' => Mage::helper('cataloglinkrule')->__('Active'),
                '0' => Mage::helper('cataloglinkrule')->__('Inactive'),
            ],
        ]);

        $fieldset->addField('link_type_id', 'select', [
            'name'     => 'rule[link_type_id]',
            'label'    => Mage::helper('cataloglinkrule')->__('Link Type'),
            'title'    => Mage::helper('cataloglinkrule')->__('Link Type'),
            'required' => true,
            'options'  => Mage::helper('cataloglinkrule')->getLinkTypes(),
        ]);

        $fieldset->addField('priority', 'text', [
            'name'     => 'rule[priority]',
            'label'    => Mage::helper('cataloglinkrule')->__('Priority'),
            'title'    => Mage::helper('cataloglinkrule')->__('Priority'),
            'required' => true,
            'note'     => Mage::helper('cataloglinkrule')->__('Lower number = higher priority'),
            'class'    => 'validate-number',
        ]);

        $fieldset->addField('sort_order', 'select', [
            'name'     => 'rule[sort_order]',
            'label'    => Mage::helper('cataloglinkrule')->__('Sort Order'),
            'title'    => Mage::helper('cataloglinkrule')->__('Sort Order'),
            'required' => true,
            'options'  => Mage::helper('cataloglinkrule')->getSortOrders(),
        ]);

        $fieldset->addField('max_links', 'text', [
            'name'  => 'rule[max_links]',
            'label' => Mage::helper('cataloglinkrule')->__('Maximum Links'),
            'title' => Mage::helper('cataloglinkrule')->__('Maximum Links'),
            'note'  => Mage::helper('cataloglinkrule')->__('Maximum number of linked products per source product (leave empty for unlimited)'),
            'class' => 'validate-number',
        ]);

        $fieldset->addField('from_date', 'date', [
            'name'   => 'rule[from_date]',
            'label'  => Mage::helper('cataloglinkrule')->__('From Date'),
            'title'  => Mage::helper('cataloglinkrule')->__('From Date'),
            'image'  => $this->getSkinUrl('images/grid-cal.gif'),
            'format' => Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
        ]);

        $fieldset->addField('to_date', 'date', [
            'name'   => 'rule[to_date]',
            'label'  => Mage::helper('cataloglinkrule')->__('To Date'),
            'title'  => Mage::helper('cataloglinkrule')->__('To Date'),
            'image'  => $this->getSkinUrl('images/grid-cal.gif'),
            'format' => Mage::app()->getLocale()->getDateFormat(Mage_Core_Model_Locale::FORMAT_TYPE_SHORT),
        ]);

        $form->setValues($model->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('cataloglinkrule')->__('Rule Information');
    }

    #[\Override]
    public function getTabTitle()
    {
        return Mage::helper('cataloglinkrule')->__('Rule Information');
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
