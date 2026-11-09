<?php

/**
 * Check and save the stock of a product for the admin product page, the product API and the stock API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogInventory
 */

declare(strict_types=1);

use Mage_CatalogInventory_Model_Stock_Item as StockItem;

class Mage_CatalogInventory_Service_Stock
{
    /** The greatest value that the DECIMAL(12,4) column qty holds. */
    public const MAX_QTY = 99999999.9999;

    /** The columns of a stock change, with the type of each value. */
    public const COLUMNS = [
        'qty' => 'float',
        'is_in_stock' => 'bool',
        'manage_stock' => 'bool',
        'min_qty' => 'float',
        'min_sale_qty' => 'float',
        'max_sale_qty' => 'float',
        'backorders' => 'int',
        'notify_stock_qty' => 'float',
        'is_qty_decimal' => 'bool',
        'qty_increments' => 'float',
        'enable_qty_increments' => 'bool',
        'use_config_min_qty' => 'bool',
        'use_config_min_sale_qty' => 'bool',
        'use_config_max_sale_qty' => 'bool',
        'use_config_backorders' => 'bool',
        'use_config_notify_stock_qty' => 'bool',
        'use_config_qty_increments' => 'bool',
        'use_config_enable_qty_inc' => 'bool',
        'use_config_manage_stock' => 'bool',
    ];

    /** Each value that can take the configuration, with the flag that tells it to. */
    public const USE_CONFIG_FLAGS = [
        'min_qty' => 'use_config_min_qty',
        'min_sale_qty' => 'use_config_min_sale_qty',
        'max_sale_qty' => 'use_config_max_sale_qty',
        'backorders' => 'use_config_backorders',
        'notify_stock_qty' => 'use_config_notify_stock_qty',
        'enable_qty_increments' => 'use_config_enable_qty_inc',
        'qty_increments' => 'use_config_qty_increments',
        'manage_stock' => 'use_config_manage_stock',
    ];

    private const NON_NEGATIVE_COLUMNS = ['min_qty', 'min_sale_qty', 'max_sale_qty', 'notify_stock_qty', 'qty_increments'];

    /**
     * Return the stock change in $input, as column => value. A key is a column of COLUMNS, in snake case or in camel
     * case. An unknown key and a null value are left out. A flag becomes 0 or 1.
     *
     * @param array<array-key, mixed> $input
     * @return array<string, float|int>
     */
    public function toColumns(array $input): array
    {
        $columns = [];
        foreach ($input as $key => $value) {
            $column = strtolower((string) preg_replace('/[A-Z]/', '_$0', (string) $key));
            $type = self::COLUMNS[$column] ?? null;
            if ($type === null || $value === null) {
                continue;
            }
            $columns[$column] = match ($type) {
                'float' => (float) $value,
                'int' => (int) $value,
                default => (int) (bool) $value,
            };
        }
        return $columns;
    }

    /**
     * Check the stock change $data and return it with the stock status: a quantity without a stock status puts the
     * product in stock when the quantity is above 0. The quantity can be negative: with backorders it is the
     * number of units that customers ordered and the store does not have, and without backorders the product is
     * out of stock.
     *
     * @param array<string, mixed> $data the columns of the change
     * @return array<string, mixed>
     * @throws Mage_Core_Exception_Input
     */
    public function prepare(array $data): array
    {
        $errors = new Mage_Core_Exception_Input();
        $helper = Mage::helper('cataloginventory');
        foreach (self::NON_NEGATIVE_COLUMNS as $column) {
            if (isset($data[$column]) && is_numeric($data[$column]) && (float) $data[$column] < 0) {
                $errors->addError($column, $helper->__('%s cannot be negative', $column));
            }
        }
        if (isset($data['qty']) && is_numeric($data['qty']) && abs((float) $data['qty']) > self::MAX_QTY) {
            $errors->addError('qty', $helper->__('Quantity must be between -%1$s and %1$s', self::MAX_QTY));
        }
        $backorders = [
            Mage_CatalogInventory_Model_Stock::BACKORDERS_NO,
            Mage_CatalogInventory_Model_Stock::BACKORDERS_YES_NONOTIFY,
            Mage_CatalogInventory_Model_Stock::BACKORDERS_YES_NOTIFY,
        ];
        if (isset($data['backorders']) && !in_array((int) $data['backorders'], $backorders, true)) {
            $errors->addError('backorders', $helper->__('backorders must be one of: %s', implode(', ', $backorders)));
        }
        $errors->throwIfErrors();

        if (isset($data['qty']) && !isset($data['is_in_stock'])) {
            $data['is_in_stock'] = (float) $data['qty'] > 0 ? 1 : 0;
        }
        if (array_key_exists('is_qty_decimal', $data) && (empty($data['is_qty_decimal']) || !isset($data['is_decimal_divided']))) {
            $data['is_decimal_divided'] = 0;
        }
        return $data;
    }

    /**
     * Put the stock change $data into $item. A value of USE_CONFIG_FLAGS without its flag stops the item from taking
     * the configuration.
     *
     * @param array<string, mixed> $data
     */
    public function applyTo(StockItem $item, array $data): void
    {
        $item->addData($data);
        foreach (self::USE_CONFIG_FLAGS as $column => $flag) {
            if (isset($data[$column]) && !isset($data[$flag])) {
                $item->setData($flag, 0);
            }
        }
    }

    /**
     * Return the stock item of the product $productId. A product without stock gets a new item.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the product does not exist
     */
    public function getByProductId(int $productId): StockItem
    {
        $typeId = Mage::getSingleton('cataloginventory/stock_status')->getProductsType($productId)[$productId] ?? null;
        if ($typeId === null) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('catalog')->__('Product not found'));
        }
        /** @var StockItem $item */
        $item = Mage::getModel('cataloginventory/stock_item');
        $item->loadByProduct($productId);
        if (!$item->getId()) {
            $item->setProductId($productId)->setStockId($item->getStockId());
        }
        return $item->setProductTypeId($typeId);
    }

    /**
     * Check the stock change $data, put it into $item and save $item. The stock item model sets the stock status
     * from the quantity and the low stock date, and the stock index follows its index mode.
     *
     * @param array<string, mixed> $data the columns of the change
     * @throws Mage_Core_Exception_Input
     */
    public function update(StockItem $item, array $data): StockItem
    {
        $this->applyTo($item, $this->prepare($data));
        $item->save();
        return $item;
    }

    /**
     * Check each change of $changes, then save all the items in one transaction: one invalid change saves nothing.
     *
     * @param list<array{StockItem, array<string, mixed>}> $changes each item with the columns of its change
     * @throws Mage_Core_Exception_Input
     */
    public function updateAll(array $changes): void
    {
        $transaction = Mage::getModel('core/resource_transaction');
        foreach ($changes as [$item, $data]) {
            $this->applyTo($item, $this->prepare($data));
            $transaction->addObject($item);
        }
        $transaction->save();
    }

    /**
     * Give the products $productIds the same stock change $data, as the mass update of the admin does. The stock
     * index runs once, after the last item.
     *
     * @param list<int|string> $productIds
     * @param array<string, mixed> $data the columns of the change
     * @return list<int> the products whose stock changed
     * @throws Mage_Core_Exception_Input
     */
    public function updateProducts(array $productIds, array $data): array
    {
        $data = $this->prepare($data);
        $columns = [...array_keys($data), ...array_values(array_intersect_key(self::USE_CONFIG_FLAGS, $data))];
        $changed = [];
        foreach ($productIds as $productId) {
            $item = $this->getByProductId((int) $productId)->setProcessIndexEvents(false);
            $this->applyTo($item, $data);
            if (array_filter($columns, $item->dataHasChangedFor(...)) !== []) {
                $item->save();
                $changed[] = (int) $productId;
            }
        }
        if ($changed !== []) {
            Mage::getSingleton('index/indexer')->indexEvents(StockItem::ENTITY, Mage_Index_Model_Event::TYPE_SAVE);
            Mage::dispatchEvent('catalog_product_stock_item_mass_change', ['products' => $changed]);
        }
        return $changed;
    }
}
