<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

it('saves the base currency tax as the base amount of an applied tax', function () {
    $total = new class extends Mage_Tax_Model_Sales_Total_Quote_Tax {
        public function run(Mage_Sales_Model_Quote_Address $address): void
        {
            $this->_setAddress($address);
            $this->_totalBaseCalculation($address, new \Maho\DataObject());
        }

        #[\Override]
        protected function _getAddressItems(Mage_Sales_Model_Quote_Address $address)
        {
            return [Mage::getModel('sales/quote_item')];
        }

        #[\Override]
        protected function _totalBaseProcessItemTax($item, $taxRateRequest, &$taxGroups, &$itemTaxGroups, $catalogPriceInclTax)
        {
            $taxGroups[10] = [
                'applied_rates' => [['id' => 'US-CA', 'percent' => 10, 'rates' => []]],
                'tax' => [10.0],
                'base_tax' => [5.0],
            ];
        }
    };
    $total->setCode('tax');

    $quote = Mage::getModel('sales/quote')->setStoreId(1);
    $address = Mage::getModel('sales/quote_address')->setQuote($quote);
    $total->run($address);

    $applied = $address->getAppliedTaxes()['US-CA'];
    expect($applied['amount'])->toEqual(10.0)
        ->and($applied['base_amount'])->toEqual(5.0);
});
