<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_System_Config_Form_Fieldset_Order_Statuses extends Mage_Adminhtml_Block_System_Config_Form_Fieldset
{
    protected ?Mage_Adminhtml_Block_System_Config_Form_Field $_fieldRenderer = null;
    protected ?array $_values = null;

    #[\Override]
    public function render(\Maho\Data\Form\Element\AbstractElement $element)
    {
        $html = ''; //$this->_getHeaderHtml($element);

        $statuses = Mage::getResourceModel('sales/order_status_collection')->load()->toOptionHash();

        foreach ($statuses as $id => $status) {
            $html .= $this->_getFieldHtml($element, (string) $id, $status);
        }
        #$html .= $this->_getFooterHtml($element);

        return $html;
    }

    protected function _getFieldRenderer(): Mage_Adminhtml_Block_System_Config_Form_Field
    {
        if (empty($this->_fieldRenderer)) {
            $this->_fieldRenderer = Mage::getBlockSingleton('adminhtml/system_config_form_field');
        }
        return $this->_fieldRenderer;
    }

    protected function _getFieldHtml(\Maho\Data\Form\Element\AbstractElement $fieldset, string $id, ?string $status): string
    {
        $configData = $this->getConfigData();
        $path = 'sales/order_statuses/status_' . $id;
        $data = $configData[$path] ?? [];

        $field = $fieldset->addField(
            $id,
            'text',
            [
                'name'          => 'groups[order_statuses][fields][status_' . $id . '][value]',
                'label'         => $status,
                'value'         => $data['value'] ?? $status,
                'default_value' => $data['default_value'] ?? '',
                'old_value'     => $data['old_value'] ?? '',
                'inherit'       => $data['inherit'] ?? '',
                'can_use_default_value' => $this->getForm()->canUseDefaultValue(true),
                'can_use_website_value' => $this->getForm()->canUseWebsiteValue(true),
            ],
        )->setRenderer($this->_getFieldRenderer());

        return $field->toHtml();
    }
}
