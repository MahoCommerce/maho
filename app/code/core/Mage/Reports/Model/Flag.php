<?php

/**
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

class Mage_Reports_Model_Flag extends Mage_Core_Model_Flag
{
    public const REPORT_ORDER_FLAG_CODE    = 'report_order_aggregated';
    public const REPORT_TAX_FLAG_CODE      = 'report_tax_aggregated';
    public const REPORT_SHIPPING_FLAG_CODE = 'report_shipping_aggregated';
    public const REPORT_INVOICE_FLAG_CODE  = 'report_invoiced_aggregated';
    public const REPORT_REFUNDED_FLAG_CODE = 'report_refunded_aggregated';
    public const REPORT_COUPONS_FLAG_CODE  = 'report_coupons_aggregated';
    public const REPORT_BESTSELLERS_FLAG_CODE = 'report_bestsellers_aggregated';
    public const REPORT_PRODUCT_VIEWED_FLAG_CODE = 'report_product_viewed_aggregated';

    /**
     * Setter for flag code
     *
     * @param string $code
     * @return $this
     */
    public function setReportFlagCode($code)
    {
        $this->_flagCode = $code;
        return $this;
    }
}
