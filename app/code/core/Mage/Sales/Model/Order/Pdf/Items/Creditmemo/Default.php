<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Order_Pdf_Items_Creditmemo_Default extends Mage_Sales_Model_Order_Pdf_Items_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/order/pdf/creditmemo/items/default.phtml');
    }

    public function getSku(): string
    {
        $item = $this->getItem();
        return $item ? $item->getSku() : '';
    }

    /**
     * Get item total including tax and excluding discount
     *
     * @return float
     */
    public function getItemTotalInclTax()
    {
        $item = $this->getItem();
        if (!$item) {
            return 0.0;
        }

        return $item->getRowTotal() + $item->getTaxAmount() + $item->getHiddenTaxAmount() - $item->getDiscountAmount();
    }
}
