<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

/**
 * Base class for configure totals order
 */

abstract class Mage_Sales_Model_Order_Total_Abstract extends \Maho\DataObject
{
    /**
     * Process model configuration array.
     * This method can be used for changing models apply sort order
     *
     * @param   array $config
     * @return  array
     */
    public function processConfigArray($config)
    {
        return $config;
    }

    public function setCode(?string $value): static
    {
        return $this->setData('code', $value);
    }

    public function setTotalConfigNode(Mage_Core_Model_Config_Element|array|null $value): static
    {
        return $this->setData('total_config_node', $value);
    }

    protected function getBaseDiscountOutsideItems(Mage_Sales_Model_Order_Invoice|Mage_Sales_Model_Order_Creditmemo $document): ?float
    {
        $items = $document->getAllItems();
        if ($items === [] && ($document instanceof Mage_Sales_Model_Order_Invoice || $document->getTotalQty() > 0)) {
            return null;
        }
        $itemDiscount = array_sum(array_map(
            static fn(Mage_Sales_Model_Order_Invoice_Item|Mage_Sales_Model_Order_Creditmemo_Item $item): float => (float) $item->getBaseDiscountAmount(),
            $items,
        ));
        return round(-(float) $document->getBaseDiscountAmount() - $itemDiscount, 4);
    }
}
