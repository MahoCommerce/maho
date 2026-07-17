<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

class Mage_Sales_Model_Order_Invoice_Total_Grand extends Mage_Sales_Model_Order_Invoice_Total_Abstract
{
    /**
     * @return $this
     */
    #[\Override]
    public function collect(Mage_Sales_Model_Order_Invoice $invoice)
    {
        /**
         * Check order grand total and invoice amounts
         */
        if ($invoice->isLast()) {
            //
        }
        return $this;
    }
}
