<?php

/**
 * Resolves the currency an order document's amounts are denominated in.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

final class OrderCurrency
{
    /**
     * From the row, never from the ambient store: that one belongs to whoever
     * is reading. Rows carrying neither column (imports, migrations) fall back
     * to the base currency of the store they were placed in, which is still a
     * property of the order, and never to an empty string: the field is typed
     * string and a client cannot format one.
     *
     * @param \Mage_Sales_Model_Order|\Mage_Sales_Model_Order_Invoice|\Mage_Sales_Model_Order_Creditmemo $document
     */
    public static function of(object $document): string
    {
        $code = $document->getOrderCurrencyCode() ?: $document->getBaseCurrencyCode();
        if ($code) {
            return (string) $code;
        }

        // getStore() resolves an empty id to the ambient store rather than
        // throwing, and the ambient store is the reader's, so a row without one
        // must not reach it. Same emptiness test App::getStore() applies.
        $storeId = $document->getStoreId();
        if (isset($storeId) && $storeId !== '' && $storeId !== true) {
            // Memoised on the app, so this costs one load per store, not per row.
            try {
                return (string) \Mage::app()->getStore($storeId)->getBaseCurrencyCode();
            } catch (\Mage_Core_Model_Store_Exception) {
                // The store was deleted since the order was placed.
            }
        }

        return \Mage::app()->getBaseCurrencyCode();
    }
}
