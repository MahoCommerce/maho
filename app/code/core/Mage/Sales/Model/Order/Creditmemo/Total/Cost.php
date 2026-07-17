<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

class Mage_Sales_Model_Order_Creditmemo_Total_Cost extends Mage_Sales_Model_Order_Creditmemo_Total_Abstract
{
    /**
     * Collect total cost of refunded items
     *
     * @return $this
     */
    #[\Override]
    public function collect(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        $baseRefundTotalCost = 0;
        foreach ($creditmemo->getAllItems() as $item) {
            if (!$item->getOrderItem()->getHasChildren()) {
                $baseRefundTotalCost += $item->getBaseCost() * $item->getQty();
            }
        }
        $creditmemo->setBaseCost($baseRefundTotalCost);
        return $this;
    }
}
