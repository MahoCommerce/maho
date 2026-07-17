<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Customer_Edit_Tab_Newsletter_Grid_Filter_Status extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Select
{
    /**
     * @return array
     */
    #[\Override]
    protected function _getOptions()
    {
        return [
            ['value' => null, 'label' => null],
            ['value' => Mage_Newsletter_Model_Queue::STATUS_SENT, 'label' => Mage::helper('customer')->__('Sent')],
            ['value' => Mage_Newsletter_Model_Queue::STATUS_CANCEL, 'label' => Mage::helper('customer')->__('Cancel')],
            ['value' => Mage_Newsletter_Model_Queue::STATUS_NEVER, 'label' => Mage::helper('customer')->__('Not Sent')],
            ['value' => Mage_Newsletter_Model_Queue::STATUS_SENDING, 'label' => Mage::helper('customer')->__('Sending')],
            ['value' => Mage_Newsletter_Model_Queue::STATUS_PAUSE, 'label' => Mage::helper('customer')->__('Paused')],
        ];
    }

    /**
     * @return array|null
     */
    #[\Override]
    public function getCondition()
    {
        if (is_null($this->getValue())) {
            return null;
        }

        return ['eq' => $this->getValue()];
    }
}
