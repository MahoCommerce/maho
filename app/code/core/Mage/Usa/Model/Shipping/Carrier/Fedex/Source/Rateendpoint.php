<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Usa
 */

declare(strict_types=1);

class Mage_Usa_Model_Shipping_Carrier_Fedex_Source_Rateendpoint extends Mage_Usa_Model_Shipping_Carrier_Abstract_Source_Code
{
    #[\Override]
    protected string $_carrierModel = 'usa/shipping_carrier_fedex';
    #[\Override]
    protected string $_codeType = 'rate_endpoint';
}
