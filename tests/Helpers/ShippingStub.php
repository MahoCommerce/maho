<?php

/**
 * A shipping collector that uses the given carriers. With $packages, it uses these packages for every carrier.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

namespace Tests\Helpers;

final class ShippingStub extends \Mage_Shipping_Model_Shipping
{
    /**
     * @param array<string, \Mage_Shipping_Model_Carrier_Abstract> $carriers
     * @param array<int|string, int>|null $packages
     */
    public function __construct(
        private readonly array $carriers,
        private readonly ?array $packages = null,
    ) {}

    #[\Override]
    public function getCarrierByCode($carrierCode, $storeId = null): \Mage_Shipping_Model_Carrier_Abstract|false
    {
        return $this->carriers[$carrierCode] ?? false;
    }

    #[\Override]
    public function composePackagesForCarrier($carrier, $request): array
    {
        return $this->packages ?? parent::composePackagesForCarrier($carrier, $request);
    }
}
