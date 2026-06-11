<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

abstract class Mage_Sales_Model_Order_Creditmemo_Total_Abstract extends Mage_Sales_Model_Order_Total_Abstract
{
    /**
     * Collect credit memo subtotal
     *
     * @return Mage_Sales_Model_Order_Creditmemo_Total_Abstract
     */
    public function collect(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        return $this;
    }
}
