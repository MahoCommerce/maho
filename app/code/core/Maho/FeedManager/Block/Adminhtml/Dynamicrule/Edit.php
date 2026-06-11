<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

class Maho_FeedManager_Block_Adminhtml_Dynamicrule_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        $this->_objectId = 'id';
        $this->_blockGroup = 'feedmanager';
        $this->_controller = 'adminhtml_dynamicrule';

        parent::__construct();

        $this->_updateButton('save', 'label', $this->__('Save Rule'));

        $rule = $this->_getRule();

        // Only show delete button for non-system rules
        if ($rule->getId() && $rule->getIsSystem()) {
            $this->_removeButton('delete');
        } else {
            $this->_updateButton('delete', 'label', $this->__('Delete Rule'));
        }

        $this->_addButton('saveandcontinue', [
            'label' => $this->__('Save and Continue Edit'),
            'onclick' => 'saveAndContinueEdit()',
            'class' => 'save',
        ], -100);

        $this->_formScripts[] = "
            function saveAndContinueEdit() {
                var form = document.getElementById('edit_form');
                if (form) {
                    form.action = form.action + 'back/edit/';
                    form.submit();
                }
            }
        ";
    }

    #[\Override]
    public function getHeaderText(): string
    {
        $rule = $this->_getRule();
        if ($rule->getId()) {
            $suffix = $rule->getIsSystem() ? ' <small>(' . $this->__('System Rule') . ')</small>' : '';
            return $this->__("Edit Rule '%s'", $this->escapeHtml($rule->getName())) . $suffix;
        }
        return $this->__('New Dynamic Rule');
    }

    protected function _getRule(): Maho_FeedManager_Model_DynamicRule
    {
        return Mage::registry('current_dynamic_rule') ?: Mage::getModel('feedmanager/dynamicRule');
    }
}
