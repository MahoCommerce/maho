<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogInventory
 */

declare(strict_types=1);

namespace Mage\CatalogInventory\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Trait\StockWriterTrait;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Stock Update Processor - Fast direct SQL stock updates.
 */
final class StockUpdateProcessor extends \Maho\ApiPlatform\Processor
{
    use StockWriterTrait;

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StockUpdate
    {
        $this->requireAdminOrApiUser('Stock update requires admin or API access');
        $this->requireApiPermission('inventory/write');
        $operationName = $operation->getName();

        return match ($operationName) {
            'updateStock' => $this->updateStockFromGraphQl($context),
            'updateStockBulk' => $this->updateStockBulkFromGraphQl($context),
            default => $this->handleRestRequest($operation, $context),
        };
    }

    private function handleRestRequest(Operation $operation, array $context): StockUpdate
    {
        $operationName = $operation->getName();
        $body = $context['request']?->toArray() ?? [];

        if (str_contains($operationName, 'bulk')) {
            return $this->doBulkUpdate($body['items'] ?? []);
        }

        return $this->doSingleUpdate(
            $body['sku'] ?? '',
            (float) ($body['qty'] ?? 0),
            isset($body['isInStock']) ? (bool) $body['isInStock'] : null,
            isset($body['manageStock']) ? (bool) $body['manageStock'] : null,
        );
    }

    private function updateStockFromGraphQl(array $context): StockUpdate
    {
        $args = $context['args']['input'] ?? [];

        return $this->doSingleUpdate(
            $args['sku'] ?? '',
            (float) ($args['qty'] ?? 0),
            isset($args['isInStock']) ? (bool) $args['isInStock'] : null,
            isset($args['manageStock']) ? (bool) $args['manageStock'] : null,
        );
    }

    private function updateStockBulkFromGraphQl(array $context): StockUpdate
    {
        $args = $context['args']['input'] ?? [];
        return $this->doBulkUpdate($args['items'] ?? []);
    }

    private function doSingleUpdate(string $sku, float $qty, ?bool $isInStock, ?bool $manageStock): StockUpdate
    {
        if (empty($sku)) {
            throw new BadRequestHttpException('SKU is required');
        }

        $this->validateStockQty($qty);

        /** @var \Mage_Catalog_Model_Resource_Product $productResource */
        $productResource = \Mage::getResourceSingleton('catalog/product');
        $productId = $productResource->getIdBySku($sku);

        if (!$productId) {
            throw new NotFoundHttpException("Product not found for SKU: {$sku}");
        }

        $stockData = $this->buildStockData($qty, $isInStock, $manageStock);
        $upsert = $this->upsertStockItemRow((int) $productId, $stockData);

        // Invalidate cache
        \Mage::app()->cleanCache(["API_PRODUCT_{$productId}"]);

        // Keep the stock_status index in sync. Direct SQL writes bypass the stock
        // item model's afterCommit reindex, so catalog listings and layered nav
        // would otherwise show stale availability until a manual reindex.
        $this->reindexStockStatus((int) $productId);

        $dto = new StockUpdate();
        $dto->sku = $sku;
        $dto->qty = $qty;
        $dto->isInStock = (bool) $stockData['is_in_stock'];
        $dto->manageStock = (bool) $upsert['manageStock'];
        $dto->previousQty = $upsert['previousQty'];
        $dto->success = true;

        return $dto;
    }

    private function doBulkUpdate(array $items): StockUpdate
    {
        if (empty($items)) {
            throw new BadRequestHttpException('Items array is required and cannot be empty');
        }

        if (count($items) > 100) {
            throw new BadRequestHttpException('Maximum 100 items per bulk update');
        }

        // Validate all SKUs first
        /** @var \Mage_Catalog_Model_Resource_Product $productResource */
        $productResource = \Mage::getResourceSingleton('catalog/product');
        $skuToProductId = [];

        foreach ($items as $index => $item) {
            $sku = $item['sku'] ?? '';
            if (empty($sku)) {
                throw new BadRequestHttpException("Item at index {$index}: SKU is required");
            }
            $qty = (float) ($item['qty'] ?? 0);
            $this->validateStockQty($qty);

            $productId = $productResource->getIdBySku($sku);
            if (!$productId) {
                throw new BadRequestHttpException("Item at index {$index}: Product not found for SKU: {$sku}");
            }
            $skuToProductId[$sku] = $productId;
        }

        $write = \Mage::getSingleton('core/resource')->getConnection('core_write');

        $results = [];
        $cacheTags = [];

        $write->beginTransaction();
        try {
            foreach ($items as $item) {
                $sku = $item['sku'];
                $qty = (float) $item['qty'];
                $isInStock = isset($item['isInStock']) ? (bool) $item['isInStock'] : null;
                $manageStock = isset($item['manageStock']) ? (bool) $item['manageStock'] : null;
                $productId = $skuToProductId[$sku];

                $stockData = $this->buildStockData($qty, $isInStock, $manageStock);
                $upsert = $this->upsertStockItemRow((int) $productId, $stockData);

                $cacheTags[] = "API_PRODUCT_{$productId}";

                $result = new StockUpdate();
                $result->sku = $sku;
                $result->qty = $qty;
                $result->isInStock = (bool) $stockData['is_in_stock'];
                $result->manageStock = (bool) $upsert['manageStock'];
                $result->previousQty = $upsert['previousQty'];
                $result->success = true;
                $results[] = $result;
            }

            $write->commit();
        } catch (\Exception $e) {
            $write->rollBack();
            throw new BadRequestHttpException('Bulk stock update failed: ' . $e->getMessage());
        }

        // Invalidate cache for all updated products
        \Mage::app()->cleanCache($cacheTags);

        // Keep the stock_status index in sync for every updated product (see
        // doSingleUpdate). Runs after commit so updateStatus() reads the persisted
        // values.
        foreach ($skuToProductId as $productId) {
            $this->reindexStockStatus((int) $productId);
        }

        // Return wrapper DTO with results
        $dto = new StockUpdate();
        $dto->sku = 'bulk';
        $dto->success = true;
        $dto->results = $results;

        return $dto;
    }

    /**
     * Recompute the cataloginventory_stock_status index for a product (and its
     * configurable/grouped parents and children). The stock data is already
     * persisted at this point, so a reindex failure is logged but not fatal.
     */
    private function reindexStockStatus(int $productId): void
    {
        try {
            /** @var \Mage_CatalogInventory_Model_Stock_Status $stockStatus */
            $stockStatus = \Mage::getSingleton('cataloginventory/stock_status');
            $stockStatus->updateStatus($productId);
        } catch (\Exception $e) {
            \Mage::log(
                "StockUpdateProcessor: failed to reindex stock status for product {$productId}: " . $e->getMessage(),
                \Mage::LOG_ERROR,
            );
        }
    }
}
