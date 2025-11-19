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

class Mage_Sales_Model_Order_Pdf_Items_Creditmemo_Grouped extends Mage_Sales_Model_Order_Pdf_Items_Creditmemo_Default
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/order/pdf/creditmemo/items/grouped.phtml');
    }

    /**
     * Get children items for grouped product
     *
     * @return array
     */
    public function getChildrenItems()
    {
        $orderItem = $this->getOrderItem();
        if (!$orderItem) {
            return [];
        }

        $children = [];
        foreach ($orderItem->getChildrenItems() as $child) {
            if ($child->getParentItem()) {
                $children[] = $child;
            }
        }

        return $children;
    }
}
