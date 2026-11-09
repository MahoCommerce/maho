<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogInventory
 */

declare(strict_types=1);

namespace Mage\CatalogInventory\Api;

use ApiPlatform\Metadata\Operation;
use Mage;
use Mage_Catalog_Service_Product;
use Mage_CatalogInventory_Model_Stock_Item;
use Mage_CatalogInventory_Service_Stock;
use Mage_Core_Exception;

/**
 * Give a stock update of one product or of up to 100 products, by SKU, to the stock service.
 */
final class StockUpdateProcessor extends \Maho\ApiPlatform\Processor
{
    private const MAX_BULK_ITEMS = 100;

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): StockUpdate
    {
        $name = (string) $operation->getName();
        $input = match ($name) {
            'update', 'bulkUpdate' => $context['args']['input'] ?? [],
            default => $this->parseRequestBody($context['request'] ?? null),
        };

        return $name === 'bulkUpdate' || str_contains($name, 'bulk')
            ? $this->updateMany(is_array($input['items'] ?? null) ? $input['items'] : [])
            : $this->updateOne($input);
    }

    private static function stock(): Mage_CatalogInventory_Service_Stock
    {
        /** @var Mage_CatalogInventory_Service_Stock */
        return Mage::getService('cataloginventory/stock');
    }

    private static function products(): Mage_Catalog_Service_Product
    {
        /** @var Mage_Catalog_Service_Product */
        return Mage::getService('catalog/product');
    }

    /**
     * @param array<string, mixed> $input
     */
    private function updateOne(array $input): StockUpdate
    {
        $sku = (string) ($input['sku'] ?? '');
        if ($sku === '') {
            throw new \Mage_Core_Exception_Input()->addError('sku', 'SKU is required');
        }
        // A missing qty leaves the stored quantity as it is, so a request that only changes the flags keeps the stock
        $columns = self::stock()->toColumns($input);
        if ($columns === []) {
            throw new Mage_Core_Exception('At least one stock field must be provided');
        }
        $productId = self::products()->getIdBySku($sku)
            ?? throw new \Mage_Core_Exception_NoSuchEntity("Product not found for SKU: {$sku}");
        self::products()->assertIdsInStores([$productId], $this->requireUser()->getAllowedStoreIds());

        $item = self::stock()->getByProductId($productId);
        $previousQty = (float) $item->getQty();
        self::stock()->update($item, $columns);

        return $this->toDto($sku, $item, $previousQty, $columns);
    }

    /**
     * @param array<array-key, mixed> $items
     */
    private function updateMany(array $items): StockUpdate
    {
        if ($items === []) {
            throw new Mage_Core_Exception('Items array is required and cannot be empty');
        }
        if (count($items) > self::MAX_BULK_ITEMS) {
            throw new Mage_Core_Exception('Maximum ' . self::MAX_BULK_ITEMS . ' items per bulk update');
        }

        $changes = [];
        foreach ($items as $index => $item) {
            $sku = (string) (is_array($item) ? $item['sku'] ?? '' : '');
            if ($sku === '') {
                throw new Mage_Core_Exception("Item at index {$index}: SKU is required");
            }
            $productId = self::products()->getIdBySku($sku)
                ?? throw new Mage_Core_Exception("Item at index {$index}: Product not found for SKU: {$sku}");
            $changes[$index] = ['sku' => $sku, 'id' => $productId, 'data' => self::stock()->toColumns($item)];
        }
        self::products()->assertIdsInStores(array_column($changes, 'id'), $this->requireUser()->getAllowedStoreIds());

        $stockItems = [];
        $previousQty = [];
        foreach ($changes as $index => $change) {
            $stockItems[$index] = self::stock()->getByProductId($change['id']);
            $previousQty[$index] = (float) $stockItems[$index]->getQty();
        }
        self::stock()->updateAll(array_map(
            fn(int|string $index): array => [$stockItems[$index], $changes[$index]['data']],
            array_keys($changes),
        ));

        $dto = new StockUpdate();
        $dto->sku = 'bulk';
        $dto->success = true;
        $dto->results = array_map(
            fn(int|string $index): StockUpdate => $this->toDto($changes[$index]['sku'], $stockItems[$index], $previousQty[$index], $changes[$index]['data']),
            array_keys($changes),
        );
        return $dto;
    }

    /**
     * @param array<string, mixed> $data the columns that the update wrote
     */
    private function toDto(string $sku, Mage_CatalogInventory_Model_Stock_Item $item, float $previousQty, array $data): StockUpdate
    {
        $dto = new StockUpdate();
        $dto->sku = $sku;
        $dto->qty = (float) $item->getQty();
        $dto->isInStock = (bool) $item->getData('is_in_stock');
        $dto->manageStock = (bool) $item->getData('manage_stock');
        $dto->previousQty = $previousQty;
        foreach (array_diff_key($data, ['qty' => true, 'is_in_stock' => true, 'manage_stock' => true]) as $column => $value) {
            $property = lcfirst(str_replace('_', '', ucwords((string) $column, '_')));
            if (property_exists($dto, $property)) {
                $dto->$property = Mage_CatalogInventory_Service_Stock::COLUMNS[$column] === 'bool' ? (bool) $value : $value;
            }
        }
        $dto->success = true;
        return $dto;
    }
}
