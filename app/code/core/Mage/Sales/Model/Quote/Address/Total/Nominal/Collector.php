<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

class Mage_Sales_Model_Quote_Address_Total_Nominal_Collector extends Mage_Sales_Model_Quote_Address_Total_Collector
{
    /**
     * Conf. node for nominal totals declaration
     *
     * @var string
     */
    protected $_totalsConfigNode = 'global/sales/quote/nominal_totals';

    /**
     * Custom cache key to not confuse with regular totals
     *
     * @var string
     */
    protected $_collectorsCacheKey = 'sorted_quote_nominal_collectors';
}
