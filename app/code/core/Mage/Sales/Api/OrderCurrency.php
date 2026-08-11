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
     * Orders, invoices and credit memos record the currency their amounts were
     * computed in, so the answer comes from the row rather than from whoever is
     * reading it. Rows predating those columns fall back to the base currency
     * recorded beside them, and report nothing when even that is absent: the
     * store is deliberately not consulted, since the ambient one belongs to the
     * reader and the row's own store may since have been deleted.
     *
     * @param \Mage_Sales_Model_Order|\Mage_Sales_Model_Order_Invoice|\Mage_Sales_Model_Order_Creditmemo $document
     */
    public static function of(object $document): string
    {
        return (string) ($document->getOrderCurrencyCode() ?: $document->getBaseCurrencyCode() ?: '');
    }
}
