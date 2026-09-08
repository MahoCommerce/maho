<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

declare(strict_types=1);

class Mage_Usa_Model_Shipping_Carrier_Fedex_Source_Unitofmeasure extends Mage_Usa_Model_Shipping_Carrier_Abstract_Source_Code
{
    protected string $_carrierModel = 'usa/shipping_carrier_fedex';
    protected string $_codeType = 'unit_of_measure';
}
