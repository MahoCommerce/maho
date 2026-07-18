<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

class Mage_CatalogRule_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_CatalogRule';

    /**
     * Algorithm for calculating price rule
     *
     * @param  string $actionOperator
     * @param  int $ruleAmount
     * @param  float $price
     * @return float|int
     */
    public function calcPriceRule($actionOperator, $ruleAmount, $price)
    {
        $priceRule = 0;
        $priceRule = match ($actionOperator) {
            'to_fixed' => min($ruleAmount, $price),
            'to_percent' => $price * $ruleAmount / 100,
            'by_fixed' => max(0, $price - $ruleAmount),
            'by_percent' => $price * (1 - $ruleAmount / 100),
            default => $priceRule,
        };
        return $priceRule;
    }
}
