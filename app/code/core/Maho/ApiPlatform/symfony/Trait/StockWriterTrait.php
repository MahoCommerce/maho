<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

use Mage;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Shared stock-write helpers for direct (model-bypassing) inventory updates.
 *
 * Centralises the qty validation, the cataloginventory_stock_item column
 * building, and the SQL upsert that were previously copied between
 * ProductProcessor (product create/update fast path) and StockUpdateProcessor
 * (single and bulk stock endpoints).
 */
trait StockWriterTrait
{
    /**
     * Reject negative stock. These direct-SQL paths bypass the stock item model,
     * so this is the only guard; the DECIMAL(12,4) column rejects oversized values
     * on its own, so there's no arbitrary upper cap here.
     */
    protected function validateStockQty(float $qty): void
    {
        if ($qty < 0) {
            throw new BadRequestHttpException('Quantity cannot be negative');
        }
    }

    /**
     * Build the cataloginventory_stock_item column map for a write.
     *
     * Only includes columns the caller actually provided: a null qty leaves the
     * stored quantity untouched (partial updates that only flip availability),
     * and manage_stock is omitted unless explicitly set so the existing flag is
     * preserved. When qty is given and is_in_stock is not, availability defaults
     * to qty > 0.
     *
     * @return array<string, mixed>
     */
    protected function buildStockData(?float $qty, ?bool $isInStock, ?bool $manageStock): array
    {
        $stockData = [];

        if ($manageStock !== null) {
            $stockData['manage_stock'] = $manageStock ? 1 : 0;
        }

        if ($qty !== null) {
            $stockData['qty'] = $qty;
            $isInStock ??= $qty > 0;
        }

        if ($isInStock !== null) {
            $stockData['is_in_stock'] = $isInStock ? 1 : 0;
        }

        return $stockData;
    }

    /**
     * Upsert a product's default (stock_id = 1) stock row via direct SQL,
     * bypassing the stock item model. Returns the persisted manage_stock flag
     * (re-read when the caller didn't set it) and the qty stored before this
     * write, so callers can report previousQty without a second lookup.
     *
     * @param array<string, mixed> $stockData from buildStockData()
     * @return array{manageStock: int, previousQty: float}
     */
    protected function upsertStockItemRow(int $productId, array $stockData): array
    {
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName('cataloginventory/stock_item');

        $previousQty = (float) $write->fetchOne(
            "SELECT qty FROM {$table} WHERE product_id = ? AND stock_id = 1",
            [$productId],
        );

        $stockItemId = $write->fetchOne(
            "SELECT item_id FROM {$table} WHERE product_id = ? AND stock_id = 1",
            [$productId],
        );

        if ($stockItemId) {
            $write->update($table, $stockData, 'item_id = ' . (int) $stockItemId);
            $manageStock = array_key_exists('manage_stock', $stockData)
                ? (int) $stockData['manage_stock']
                : (int) $write->fetchOne(
                    "SELECT manage_stock FROM {$table} WHERE item_id = ?",
                    [(int) $stockItemId],
                );
        } else {
            // New stock item: default manage_stock to enabled when not provided.
            $stockData['manage_stock'] ??= 1;
            $stockData['product_id'] = $productId;
            $stockData['stock_id'] = 1;
            $write->insert($table, $stockData);
            $manageStock = (int) $stockData['manage_stock'];
        }

        return ['manageStock' => $manageStock, 'previousQty' => $previousQty];
    }
}
