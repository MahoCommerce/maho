<?php

/**
 * A carrier that quotes each package separately. It records the weight of each request.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Tests
 */

declare(strict_types=1);

namespace Tests\Helpers;

final class PackageCarrierStub extends \Mage_Shipping_Model_Carrier_Abstract
{
    /**
     * The collector clones the carrier for each package, so the clones share this object.
     *
     * @var \ArrayObject<int, float>
     */
    private readonly \ArrayObject $weights;

    /**
     * @param \Closure(float): (\Mage_Shipping_Model_Rate_Result|\Mage_Shipping_Model_Rate_Result_Error|false|null) $quote
     */
    public function __construct(
        private readonly \Closure $quote,
        private readonly float $maxWeight = 70,
        private readonly bool $showErrors = false,
    ) {
        parent::__construct();
        $this->weights = new \ArrayObject();
    }

    /** @return list<float> */
    public function getRequestedWeights(): array
    {
        return array_values($this->weights->getArrayCopy());
    }

    #[\Override]
    public function getConfigData($field): float|int
    {
        return match ($field) {
            'shipment_requesttype' => 1,
            'max_package_weight' => $this->maxWeight,
            'showmethod' => (int) $this->showErrors,
            default => 0,
        };
    }

    #[\Override]
    public function checkAvailableShipCountries(\Mage_Shipping_Model_Rate_Request $request): true
    {
        return true;
    }

    #[\Override]
    public function proccessAdditionalValidation(\Mage_Shipping_Model_Rate_Request $request): true
    {
        return true;
    }

    #[\Override]
    public function collectRates(\Mage_Shipping_Model_Rate_Request $request): \Mage_Shipping_Model_Rate_Result|\Mage_Shipping_Model_Rate_Result_Error|false|null
    {
        $weight = (float) $request->getPackageWeight();
        $this->weights->append($weight);
        return ($this->quote)($weight);
    }
}
