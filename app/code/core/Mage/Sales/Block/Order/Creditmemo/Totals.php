<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Block_Order_Creditmemo_Totals extends Mage_Sales_Block_Order_Totals
{
    protected $_creditmemo = null;

    /**
     * @return mixed|null
     */
    public function getCreditmemo()
    {
        if ($this->_creditmemo === null) {
            if ($this->hasData('creditmemo')) {
                $this->_creditmemo = $this->_getData('creditmemo');
            } elseif (Mage::registry('current_creditmemo')) {
                $this->_creditmemo = Mage::registry('current_creditmemo');
            } elseif ($this->getParentBlock()->getCreditmemo()) {
                $this->_creditmemo = $this->getParentBlock()->getCreditmemo();
            }
        }
        return $this->_creditmemo;
    }

    /**
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @return $this
     */
    public function setCreditmemo($creditmemo)
    {
        $this->_creditmemo = $creditmemo;
        return $this;
    }

    /**
     * Get totals source object
     *
     * @return Mage_Sales_Model_Order
     */
    #[\Override]
    public function getSource()
    {
        return $this->getCreditmemo();
    }

    /**
     * Initialize order totals array
     *
     * @return Mage_Sales_Block_Order_Totals
     */
    #[\Override]
    protected function _initTotals()
    {
        parent::_initTotals();
        $this->removeTotal('base_grandtotal');
        if ((float) $this->getSource()->getAdjustmentPositive()) {
            $total = new Varien_Object([
                'code'  => 'adjustment_positive',
                'value' => $this->getSource()->getAdjustmentPositive(),
                'label' => $this->__('Adjustment Refund'),
            ]);
            $this->addTotal($total);
        }
        if ((float) $this->getSource()->getAdjustmentNegative()) {
            $total = new Varien_Object([
                'code'  => 'adjustment_negative',
                'value' => $this->getSource()->getAdjustmentNegative(),
                'label' => $this->__('Adjustment Fee'),
            ]);
            $this->addTotal($total);
        }
        /**
        <?php if ($this->getCanDisplayTotalPaid()): ?>
        <tr>
            <td colspan="6" class="a-right"><strong><?php echo $this->__('Total Paid') ?></strong></td>
            <td class="last a-right"><strong><?php echo $_order->formatPrice($_creditmemo->getTotalPaid()) ?></strong></td>
        </tr>
        <?php endif ?>
        <?php if ($this->getCanDisplayTotalRefunded()): ?>
        <tr>
            <td colspan="6" class="a-right"><strong><?php echo $this->__('Total Refunded') ?></strong></td>
            <td class="last a-right"><strong><?php echo $_order->formatPrice($_creditmemo->getTotalRefunded()) ?></strong></td>
        </tr>
        <?php endif ?>
        <?php if ($this->getCanDisplayTotalDue()): ?>
        <tr>
            <td colspan="6" class="a-right"><strong><?php echo $this->__('Total Due') ?></strong></td>
            <td class="last a-right"><strong><?php echo $_order->formatPrice($_creditmemo->getTotalDue()) ?></strong></td>
        </tr>
        <?php endif ?>
         */
        return $this;
    }
}
