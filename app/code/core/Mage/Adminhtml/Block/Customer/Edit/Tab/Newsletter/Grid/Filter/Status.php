<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
