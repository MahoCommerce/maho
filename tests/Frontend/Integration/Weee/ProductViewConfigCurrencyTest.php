<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

use Tests\Helpers\WeeeDiscountFixture;

uses(Tests\MahoFrontendTestCase::class);

it('sends the FPT of the product page price config in the display currency', function (): void {
    $store = Mage::app()->getDefaultStoreView();
    Mage::app()->setCurrentStore($store);
    $resource = Mage::getResourceSingleton('catalog/product');
    $feeCode = 'view_fee_' . bin2hex(random_bytes(4));

    $transactionStarted = false;
    try {
        $resource->beginTransaction();
        $transactionStarted = true;
        WeeeDiscountFixture::createFeeAttribute($feeCode);
        Mage::unregister('_singleton/weee/tax');
        $store->setConfig('tax/weee/enable', '1');
        $store->setConfig('tax/weee/display', (string) Mage_Weee_Model_Tax::DISPLAY_INCL);
        $store->setConfig('tax/weee/apply_vat', '0');
        $fixture = WeeeDiscountFixture::createProduct('view-fee-' . bin2hex(random_bytes(4)), $feeCode);
        $product = Mage::getModel('catalog/product')->setStoreId((int) $store->getId())
            ->load((int) $fixture['product']->getId());

        $baseCurrency = $store->getBaseCurrencyCode();
        $otherCurrency = $baseCurrency === 'EUR' ? 'USD' : 'EUR';
        Mage::getModel('directory/currency')->saveRates([$baseCurrency => [$otherCurrency => 1.5]]);
        $store->setConfig('currency/options/allow', $baseCurrency . ',' . $otherCurrency);
        $store->unsetData('available_currency_codes')->unsetData('disallowed_base_currency_code_index');
        $store->clearCurrencyRateMemos()->setRequestedCurrencyCode($otherCurrency);

        $response = new Maho\DataObject();
        Mage::getSingleton('weee/observer')->updateCofigurableProductOptions(new Maho\Event\Observer([
            'event' => new Maho\Event(['response_object' => $response, 'product' => $product]),
        ]));
        $options = $response->getAdditionalOptions();

        expect((float) $options['plusDisposition'])->toEqualWithDelta(15.0, 0.0001)
            ->and((float) $options['oldPlusDisposition'])->toEqualWithDelta(15.0, 0.0001)
            ->and((float) $options['plusDispositionTax'])->toEqualWithDelta(0.0, 0.0001);
    } finally {
        if ($transactionStarted) {
            $resource->rollBack();
        }
        WeeeDiscountFixture::cleanup(['products' => [], 'categories' => [], 'rules' => [], 'attribute' => $feeCode]);
        Mage::unregister('_singleton/weee/tax');
        resetCurrencyState();
    }
});
