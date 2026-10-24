<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

uses(Tests\MahoBackendTestCase::class);

/**
 * The order create controller moves an item with moveQuoteItem($id, $to), without a quantity.
 * The quantity was a required parameter, so that request ended in an ArgumentCountError.
 */
it('moves a quote item without a quantity', function () {
    $quote = Mage::getModel('sales/quote');
    $quote->setStoreId(1);
    $quote->save();
    $create = Mage::getModel('adminhtml/sales_order_create')->setQuote($quote);
    $create->addCustomItem('Installation', 40.0, 2, false, 0.0);
    $quote->save();

    try {
        $items = $quote->getAllItems();
        expect($items)->toHaveCount(1);

        $create->moveQuoteItem($items[0], 'remove');

        expect($quote->getAllItems())->toBeEmpty();
    } finally {
        $quote->delete();
    }
});
