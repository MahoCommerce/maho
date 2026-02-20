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

class Mage_Adminhtml_Block_Sales_Totals extends Mage_Sales_Block_Order_Totals
{
    /**
     * Format total value based on order currency
     *
     * @param \Maho\DataObject $total
     * @return  string
     */
    #[\Override]
    public function formatValue($total)
    {
        if (!$total->getIsFormated()) {
            /** @var Mage_Adminhtml_Helper_Sales $helper */
            $helper = $this->helper('adminhtml/sales');
            return $helper->displayPrices(
                $this->getOrder(),
                $total->getBaseValue(),
                $total->getValue(),
            );
        }
        return $total->getValue();
    }

    /**
     * Initialize order totals array
     *
     * @return Mage_Sales_Block_Order_Totals
     */
    #[\Override]
    protected function _initTotals()
    {
        $this->_totals = [];
        $this->_totals['subtotal'] = new \Maho\DataObject([
            'code'      => 'subtotal',
            'value'     => $this->getSource()->getSubtotal(),
            'base_value' => $this->getSource()->getBaseSubtotal(),
            'label'     => $this->helper('sales')->__('Subtotal'),
        ]);

        /**
         * Add shipping
         */
        if (!$this->getSource()->getIsVirtual()
            && ((float) $this->getSource()->getShippingAmount() || $this->getSource()->getShippingDescription())
        ) {
            $this->_totals['shipping'] = new \Maho\DataObject([
                'code'      => 'shipping',
                'value'     => $this->getSource()->getShippingAmount(),
                'base_value' => $this->getSource()->getBaseShippingAmount(),
                'label' => $this->helper('sales')->__('Shipping & Handling'),
            ]);
        }

        /**
         * Add discount
         */
        if ((float) $this->getSource()->getDiscountAmount() != 0) {
            if ($this->getSource()->getDiscountDescription()) {
                $discountLabel = $this->helper('sales')->__(
                    'Discount (%s)',
                    $this->getSource()->getDiscountDescription(),
                );
            } else {
                $discountLabel = $this->helper('sales')->__('Discount');
            }
            $this->_totals['discount'] = new \Maho\DataObject([
                'code'      => 'discount',
                'value'     => $this->getSource()->getDiscountAmount(),
                'base_value' => $this->getSource()->getBaseDiscountAmount(),
                'label'     => $discountLabel,
            ]);
        }

        $this->_totals['grand_total'] = new \Maho\DataObject([
            'code'      => 'grand_total',
            'strong'    => true,
            'value'     => $this->getSource()->getGrandTotal(),
            'base_value' => $this->getSource()->getBaseGrandTotal(),
            'label'     => $this->helper('sales')->__('Grand Total'),
            'area'      => 'footer',
        ]);

        return $this;
    }
}
