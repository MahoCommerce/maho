<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_FeedManager_Block_Adminhtml_Dynamicrule_Edit_Tab_General extends Mage_Adminhtml_Block_Widget_Form
{
    #[\Override]
    protected function _prepareForm(): self
    {
        $rule = $this->_getRule();

        $form = new Maho\Data\Form();
        $form->setHtmlIdPrefix('rule_');

        $fieldset = $form->addFieldset('base_fieldset', [
            'legend' => $this->__('Rule Information'),
        ]);

        if ($rule->getId()) {
            $fieldset->addField('rule_id', 'hidden', [
                'name' => 'rule_id',
            ]);
        }

        $fieldset->addField('name', 'text', [
            'name' => 'name',
            'label' => $this->__('Name'),
            'title' => $this->__('Name'),
            'required' => true,
            'note' => $this->__('A friendly name for this rule'),
        ]);

        $codeField = $fieldset->addField('code', 'text', [
            'name' => 'code',
            'label' => $this->__('Code'),
            'title' => $this->__('Code'),
            'required' => true,
            'class' => 'validate-code',
            'note' => $this->__('Unique identifier used in mappings (lowercase, underscores allowed)'),
        ]);

        // Make code read-only for system rules
        if ($rule->getId() && $rule->getIsSystem()) {
            $codeField->setReadonly(true);
            $codeField->setNote($this->__('System rule code cannot be changed'));
        }

        $fieldset->addField('description', 'textarea', [
            'name' => 'description',
            'label' => $this->__('Description'),
            'title' => $this->__('Description'),
            'note' => $this->__('Help text shown when selecting this rule in mappings'),
        ]);

        $fieldset->addField('is_enabled', 'select', [
            'name' => 'is_enabled',
            'label' => $this->__('Status'),
            'title' => $this->__('Status'),
            'required' => true,
            'values' => [
                ['value' => 1, 'label' => $this->__('Enabled')],
                ['value' => 0, 'label' => $this->__('Disabled')],
            ],
        ]);

        if ($rule->getId() && $rule->getIsSystem()) {
            $fieldset->addField('is_system_note', 'note', [
                'label' => $this->__('Type'),
                'text' => '<span class="fm-status-system">' . $this->__('System Rule') . '</span>' .
                          '<br/><small>' . $this->__('System rules can be modified but not deleted.') . '</small>',
            ]);
        }

        $form->setValues($rule->getData());
        $this->setForm($form);

        return parent::_prepareForm();
    }

    protected function _getRule(): Maho_FeedManager_Model_DynamicRule
    {
        return Mage::registry('current_dynamic_rule') ?: Mage::getModel('feedmanager/dynamicRule');
    }
}
