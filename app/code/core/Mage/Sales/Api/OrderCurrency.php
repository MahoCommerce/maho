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
     * is reading. A row carrying neither column falls back to its own store's
     * base currency, and never to an empty string.
     */
    public static function of(
        \Mage_Sales_Model_Order|\Mage_Sales_Model_Order_Invoice|\Mage_Sales_Model_Order_Creditmemo $document,
    ): string {
        $code = $document->getOrderCurrencyCode() ?: $document->getBaseCurrencyCode();
        if ($code) {
            return (string) $code;
        }

        // getStore() resolves an empty id to the reader's store rather than
        // throwing, so keep those out. Same test App::getStore() applies.
        $storeId = $document->getStoreId();
        if (isset($storeId) && $storeId !== '' && $storeId !== true) {
            try {
                return (string) \Mage::app()->getStore($storeId)->getBaseCurrencyCode();
            } catch (\Mage_Core_Model_Store_Exception) {
                // The store was deleted since the order was placed.
            }
        }

        return \Mage::app()->getBaseCurrencyCode();
    }
}
