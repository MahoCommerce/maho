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

class Mage_Adminhtml_Block_Sales_Order_Creditmemo_Totals extends Mage_Adminhtml_Block_Sales_Totals
{
    /**
     * @var Mage_Sales_Model_Order_Creditmemo|null
     */
    protected $_creditmemo;

    /**
     * @return Mage_Sales_Model_Order_Creditmemo|null
     */
    public function getCreditmemo()
    {
        if ($this->_creditmemo === null) {
            if ($this->hasData('creditmemo')) {
                $this->_creditmemo = $this->_getData('creditmemo');
            } elseif (Mage::registry('current_creditmemo')) {
                $this->_creditmemo = Mage::registry('current_creditmemo');
            } elseif ($this->getParentBlock() && $this->getParentBlock()->getCreditmemo()) {
                $this->_creditmemo = $this->getParentBlock()->getCreditmemo();
            }
        }
        return $this->_creditmemo;
    }

    /**
     * @return Mage_Sales_Model_Order_Creditmemo|null
     */
    #[\Override]
    public function getSource()
    {
        return $this->getCreditmemo();
    }

    /**
     * Initialize creditmemo totals array
     *
     * @return Mage_Sales_Block_Order_Totals
     */
    #[\Override]
    protected function _initTotals()
    {
        parent::_initTotals();
        $this->addTotal(new \Maho\DataObject([
            'code'      => 'adjustment_positive',
            'value'     => $this->getSource()->getAdjustmentPositive(),
            'base_value' => $this->getSource()->getBaseAdjustmentPositive(),
            'label'     => $this->helper('sales')->__('Adjustment Refund'),
        ]));
        $this->addTotal(new \Maho\DataObject([
            'code'      => 'adjustment_negative',
            'value'     => $this->getSource()->getAdjustmentNegative(),
            'base_value' => $this->getSource()->getBaseAdjustmentNegative(),
            'label'     => $this->helper('sales')->__('Adjustment Fee'),
        ]));
        return $this;
    }
}
