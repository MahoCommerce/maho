<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Customer_Edit_Tab_Newsletter_Grid_Filter_Status extends Mage_Adminhtml_Block_Widget_Grid_Column_Filter_Select
{
    protected static $_statuses;

    public function __construct()
    {
        self::$_statuses = [
            null                                        => null,
            Mage_Newsletter_Model_Queue::STATUS_SENT    => Mage::helper('customer')->__('Sent'),
            Mage_Newsletter_Model_Queue::STATUS_CANCEL  => Mage::helper('customer')->__('Cancel'),
            Mage_Newsletter_Model_Queue::STATUS_NEVER   => Mage::helper('customer')->__('Not Sent'),
            Mage_Newsletter_Model_Queue::STATUS_SENDING => Mage::helper('customer')->__('Sending'),
            Mage_Newsletter_Model_Queue::STATUS_PAUSE   => Mage::helper('customer')->__('Paused'),
        ];
        parent::__construct();
    }

    /**
     * @return array
     */
    #[\Override]
    protected function _getOptions()
    {
        $result = [];
        foreach (self::$_statuses as $code => $label) {
            $result[] = ['value' => $code, 'label' => Mage::helper('customer')->__($label)];
        }

        return $result;
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
