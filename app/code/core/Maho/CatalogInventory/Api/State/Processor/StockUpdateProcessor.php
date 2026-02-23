<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CatalogInventory
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\CatalogInventory\Api\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Maho\CatalogInventory\Api\Resource\StockUpdate;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Stock Update Processor - Fast direct SQL stock updates
 *
 * @implements ProcessorInterface<StockUpdate, StockUpdate>
 */
final class StockUpdateProcessor implements ProcessorInterface
{
    use AuthenticationTrait;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StockUpdate
    {
        $this->requireAdminOrApiUser('Stock update requires admin or API access');
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

        $this->validateQty($qty);

        /** @var \Mage_Catalog_Model_Resource_Product $productResource */
        $productResource = \Mage::getResourceSingleton('catalog/product');
        $productId = $productResource->getIdBySku($sku);

        if (!$productId) {
            throw new NotFoundHttpException("Product not found for SKU: {$sku}");
        }

        $resource = \Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName('cataloginventory/stock_item');

        // Get current stock for previousQty
        $currentQty = (float) $write->fetchOne(
            "SELECT qty FROM {$table} WHERE product_id = ? AND stock_id = 1",
            [$productId],
        );

        // Build update data
        $stockData = [];
        $stockData['qty'] = $qty;
        $stockData['manage_stock'] = ($manageStock !== null) ? ($manageStock ? 1 : 0) : 1;

        if ($isInStock === null) {
            $stockData['is_in_stock'] = $qty > 0 ? 1 : 0;
        } else {
            $stockData['is_in_stock'] = $isInStock ? 1 : 0;
        }

        // Check if stock item exists
        $stockItemId = $write->fetchOne(
            "SELECT item_id FROM {$table} WHERE product_id = ? AND stock_id = 1",
            [$productId],
        );

        if ($stockItemId) {
            $write->update($table, $stockData, 'item_id = ' . (int) $stockItemId);
        } else {
            $stockData['product_id'] = $productId;
            $stockData['stock_id'] = 1;
            $write->insert($table, $stockData);
        }

        // Invalidate cache
        \Mage::app()->cleanCache(["API_PRODUCT_{$productId}"]);

        $dto = new StockUpdate();
        $dto->sku = $sku;
        $dto->qty = $qty;
        $dto->isInStock = (bool) $stockData['is_in_stock'];
        $dto->manageStock = (bool) $stockData['manage_stock'];
        $dto->previousQty = $currentQty;
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
            $this->validateQty($qty);

            $productId = $productResource->getIdBySku($sku);
            if (!$productId) {
                throw new BadRequestHttpException("Item at index {$index}: Product not found for SKU: {$sku}");
            }
            $skuToProductId[$sku] = $productId;
        }

        $resource = \Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName('cataloginventory/stock_item');

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

                // Get current qty
                $currentQty = (float) $write->fetchOne(
                    "SELECT qty FROM {$table} WHERE product_id = ? AND stock_id = 1",
                    [$productId],
                );

                $stockData = [];
                $stockData['qty'] = $qty;
                $stockData['manage_stock'] = ($manageStock !== null) ? ($manageStock ? 1 : 0) : 1;
                $stockData['is_in_stock'] = ($isInStock ?? ($qty > 0)) ? 1 : 0;

                $stockItemId = $write->fetchOne(
                    "SELECT item_id FROM {$table} WHERE product_id = ? AND stock_id = 1",
                    [$productId],
                );

                if ($stockItemId) {
                    $write->update($table, $stockData, 'item_id = ' . (int) $stockItemId);
                } else {
                    $stockData['product_id'] = $productId;
                    $stockData['stock_id'] = 1;
                    $write->insert($table, $stockData);
                }

                $cacheTags[] = "API_PRODUCT_{$productId}";

                $result = new StockUpdate();
                $result->sku = $sku;
                $result->qty = $qty;
                $result->isInStock = (bool) $stockData['is_in_stock'];
                $result->manageStock = (bool) $stockData['manage_stock'];
                $result->previousQty = $currentQty;
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

        // Return wrapper DTO with results
        $dto = new StockUpdate();
        $dto->sku = 'bulk';
        $dto->success = true;
        $dto->results = $results;

        return $dto;
    }

    private function validateQty(float $qty): void
    {
        if ($qty < 0 || $qty > 99999999) {
            throw new BadRequestHttpException('Quantity must be between 0 and 99999999');
        }
    }
}
