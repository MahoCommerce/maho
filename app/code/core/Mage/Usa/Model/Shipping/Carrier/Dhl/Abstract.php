<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

abstract class Mage_Usa_Model_Shipping_Carrier_Dhl_Abstract extends Mage_Usa_Model_Shipping_Carrier_Abstract
{
    /**
     * Response condition code for service is unavailable at the requested date
     */
    public const int CONDITION_CODE_SERVICE_DATE_UNAVAILABLE = 1003;

    /**
     * Count of days to look forward if day is not unavailable
     */
    public const int UNAVAILABLE_DATE_LOOK_FORWARD = 5;

    public const string REQUEST_DATE_FORMAT = 'Y-m-d';

    /**
     * Get shipping date
     */
    protected function _getShipDate(bool $domestic = true): string
    {
        return $this->_determineShippingDay(
            $this->getConfigData($domestic ? 'shipment_days' : 'intl_shipment_days') ?: '',
            date(self::REQUEST_DATE_FORMAT),
        );
    }

    /**
     * Determine shipping day according to configuration settings
     *
     * $shippingDays is a comma separated list of weekdays, such as "Mon,Tue".
     */
    protected function _determineShippingDay(string $shippingDays, string $date): string
    {
        if ($shippingDays === '') {
            return $date;
        }

        $shippingDays = explode(',', $shippingDays);

        $i = 0;
        $weekday = date('D', strtotime($date));
        while (!in_array($weekday, $shippingDays) && $i < 10) {
            $i++;
            $weekday = date('D', strtotime("$date +$i day"));
        }

        return date(self::REQUEST_DATE_FORMAT, strtotime("$date +$i day"));
    }
}
