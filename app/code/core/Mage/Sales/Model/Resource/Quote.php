<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2025 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Resource_Quote extends Mage_Sales_Model_Resource_Abstract
{
    /**
     * Initialize table nad PK name
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/quote', 'entity_id');
    }

    /**
     * Retrieve select object for load object data
     *
     * @param string $field
     * @param mixed $value
     * @param Mage_Core_Model_Abstract|Mage_Sales_Model_Quote $object
     * @return Maho\Db\Select
     */
    #[\Override]
    protected function _getLoadSelect($field, $value, $object)
    {
        $select   = parent::_getLoadSelect($field, $value, $object);
        $storeIds = $object->getSharedStoreIds();
        if ($storeIds) {
            $select->where('store_id IN (?)', $storeIds);
        } else {
            /**
             * For empty result
             */
            $select->where('store_id < ?', 0);
        }

        return $select;
    }

    /**
     * Load quote data by customer identifier
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param int $customerId
     * @return $this
     */
    public function loadByCustomerId($quote, $customerId)
    {
        $adapter = $this->_getReadAdapter();
        $select  = $this->_getLoadSelect('customer_id', $customerId, $quote)
            ->where('is_active = ?', 1)
            ->order('updated_at ' . Maho\Db\Select::SQL_DESC)
            ->limit(1);

        $data    = $adapter->fetchRow($select);

        if ($data) {
            $quote->setData($data);
        }

        $this->_afterLoad($quote);

        return $this;
    }

    /**
     * Load only active quote
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param int $quoteId
     * @return $this
     */
    public function loadActive($quote, $quoteId)
    {
        $adapter = $this->_getReadAdapter();
        $select  = $this->_getLoadSelect('entity_id', $quoteId, $quote)
            ->where('is_active = ?', 1);

        $data    = $adapter->fetchRow($select);
        if ($data) {
            $quote->setData($data);
        }

        $this->_afterLoad($quote);

        return $this;
    }

    /**
     * Load quote data by identifier without store
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param int $quoteId
     * @return $this
     */
    public function loadByIdWithoutStore($quote, $quoteId)
    {
        $read = $this->_getReadAdapter();
        if ($read) {
            $select = parent::_getLoadSelect('entity_id', $quoteId, $quote);

            $data = $read->fetchRow($select);

            if ($data) {
                $quote->setData($data);
            }
        }

        $this->_afterLoad($quote);
        return $this;
    }

    /**
     * Get reserved order id
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return string
     */
    public function getReservedOrderId($quote)
    {
        $storeId = (int) $quote->getStoreId();
        return Mage::getSingleton('eav/config')->getEntityType(Mage_Sales_Model_Order::ENTITY)
            ->fetchNewIncrementId($storeId);
    }

    /**
     * Check is order increment id use in sales/order table
     *
     * @param string|int $orderIncrementId
     *
     * @return bool
     */
    public function isOrderIncrementIdUsed($orderIncrementId)
    {
        $adapter   = $this->_getReadAdapter();
        $bind      = [':increment_id' => (string) $orderIncrementId];
        $select    = $adapter->select();
        $select->from($this->getTable('sales/order'), 'entity_id')
            ->where('increment_id = :increment_id');
        $entityId = $adapter->fetchOne($select, $bind);
        if ($entityId > 0) {
            return true;
        }

        return false;
    }

    /**
     * Mark quotes - that depend on catalog price rules - to be recollected on demand
     *
     *  @param  array|null $productIdList
     *
     * @return $this
     */
    public function markQuotesRecollectByAffectedProduct($productIdList = null)
    {
        $writeAdapter = $this->_getWriteAdapter();
        $select = $writeAdapter->select();
        $subSelect = clone $select;

        $subSelect
            ->distinct()
            ->from(
                ['qi' => $this->getTable('sales/quote_item')],
                ['entity_id' => 'quote_id'],
            )
            ->join(
                ['pp' => $this->getTable('catalogrule/rule_product_price')],
                'qi.product_id = pp.product_id',
                [],
            );
        if ($productIdList !== null) {
            $subSelect->where('qi.product_id IN (?)', $productIdList);
        }

        $select
            ->join(
                ['tmp' => $subSelect],
                'q.entity_id = tmp.entity_id',
                ['trigger_recollect' => new Maho\Db\Expr('1')],
            )
             ->where('q.is_active = ?', 1);
        $sql = $writeAdapter->updateFromSelect($select, ['q' => $this->getTable('sales/quote')]);
        $writeAdapter->query($sql);

        return $this;
    }

    /**
     * Mark quotes - that depend on catalog price rules - to be recollected on demand
     *
     * @return $this
     */
    public function markQuotesRecollectOnCatalogRules()
    {
        return $this->markQuotesRecollectByAffectedProduct();
    }

    /**
     * Subtract product from all quotes quantities
     *
     * @param Mage_Catalog_Model_Product $product
     * @return $this
     */
    public function substractProductFromQuotes($product)
    {
        $productId = (int) $product->getId();
        if (!$productId) {
            return $this;
        }
        $adapter   = $this->_getWriteAdapter();
        $subSelect = $adapter->select();

        $subSelect->columns([
            'items_qty'   => new Maho\Db\Expr(
                $adapter->quoteIdentifier('q.items_qty') . ' - ' . $adapter->quoteIdentifier('qi.qty'),
            ),
            'items_count' => new Maho\Db\Expr($adapter->quoteIdentifier('q.items_count') . ' - 1'),
        ])
        ->where('q.items_count > 0')
        ->join(
            ['qi' => $this->getTable('sales/quote_item')],
            implode(' AND ', [
                'q.entity_id = qi.quote_id',
                'qi.parent_item_id IS NULL',
                $adapter->quoteInto('qi.product_id = ?', $productId),
            ]),
            [],
        );

        $updateQuery = $adapter->updateFromSelect($subSelect, ['q' => $this->getTable('sales/quote')]);

        $adapter->query($updateQuery);

        return $this;
    }

    /**
     * Mark recollect contain product(s) quotes
     *
     * @param array|int|Maho\Db\Expr $productIds
     * @return $this
     */
    public function markQuotesRecollect($productIds)
    {
        $tableQuote = $this->getTable('sales/quote');
        $tableItem = $this->getTable('sales/quote_item');
        $subSelect = $this->_getReadAdapter()
            ->select()
            ->from($tableItem, ['entity_id' => 'quote_id'])
            ->where('product_id IN (?)', $productIds)
            ->group('quote_id');

        $select = $this->_getReadAdapter()->select()->join(
            ['t2' => $subSelect],
            't1.entity_id = t2.entity_id',
            ['trigger_recollect' => new Maho\Db\Expr('1')],
        );
        $updateQuery = $select->crossUpdateFromSelect(['t1' => $tableQuote]);
        $this->_getWriteAdapter()->query($updateQuery);

        return $this;
    }

    /**
     * Copies the uploaded option files of the items of $quote that an order was placed from to their
     * order path, where the order has no copy yet
     */
    public function copyOrderedItemFiles(Mage_Sales_Model_Quote $quote): void
    {
        if (!$quote->getId()) {
            return;
        }
        $adapter = $this->_getReadAdapter();
        $orderIds = $adapter->fetchCol(
            $adapter->select()
                ->from($this->getTable('sales/order'), ['entity_id'])
                ->where('quote_id = ?', (int) $quote->getId()),
        );
        if ($orderIds === []) {
            return;
        }
        $fileType = Mage::getModel('catalog/product_option_type_file');
        $stringHelper = Mage::helper('core/string');
        $values = [];
        foreach ($quote->getItemsCollection()->getItems() as $item) {
            foreach ($item->getOptions() as $option) {
                if (!str_starts_with((string) $option->getCode(), Mage_Catalog_Model_Product_Type_Abstract::OPTION_PREFIX)) {
                    continue;
                }
                $value = $stringHelper->unserialize($option->getValue());
                if (is_array($value) && isset($value['quote_path'])) {
                    $values[(int) $item->getId()][] = $value;
                }
            }
        }
        if ($values === []) {
            return;
        }

        foreach ($this->getOrderedItemIds($orderIds, array_keys($values)) as $itemId) {
            foreach ($values[$itemId] as $value) {
                $fileType->copyQuoteFileToOrder($value);
            }
        }
    }

    /**
     * Deletes the uploaded option files of quotes that no quote item or wishlist item names, and
     * returns how many it deleted
     *
     * The file name is the hash of the file content, so a merged cart, a cart with the same upload or
     * a wishlist item can name the file of another cart. A file younger than $minAge seconds is kept,
     * since its upload may not be saved to an item yet.
     */
    public function deleteUnusedOptionFiles(int $minAge = 86400): int
    {
        // Until the Sales 2.0.2 data upgrade ran, old orders can have only the quote copy of a file
        $dataVersion = (string) Mage::getResourceSingleton('core/resource')->getDataVersion('sales_setup');
        if (version_compare($dataVersion, '2.0.2', '<')) {
            return 0;
        }

        $used = $this->getUsedOptionFiles();
        $mount = Mage::getStorage('custom_options');
        $quoteDir = Mage::getModel('catalog/product_option_type_file')->getQuoteTargetStoragePath();
        $before = time() - $minAge;
        $deleted = 0;
        foreach ($mount->listFiles($quoteDir) as $file) {
            $path = $file->path();
            if (isset($used[$path]) || ($file->lastModified() ?? $mount->lastModified($path)) > $before) {
                continue;
            }
            $mount->delete($path);
            $deleted++;
        }
        return $deleted;
    }

    /**
     * Mount paths of the uploaded option files that a quote item or a wishlist item names
     *
     * @return array<string, true>
     */
    protected function getUsedOptionFiles(): array
    {
        $fileType = Mage::getModel('catalog/product_option_type_file');
        $stringHelper = Mage::helper('core/string');
        $adapter = $this->_getReadAdapter();
        $used = [];
        $sources = [
            'sales/quote_item_option' => ['option_id', 'value'],
            'wishlist/item_option' => ['option_id', 'value'],
        ];
        foreach ($sources as $tableAlias => [$idColumn, $valueColumn]) {
            try {
                $table = $this->getTable($tableAlias);
            } catch (Mage_Core_Exception) {
                continue;
            }
            if (!$adapter->isTableExists($table)) {
                continue;
            }
            $lastId = 0;
            do {
                $rows = $adapter->fetchPairs(
                    $adapter->select()
                        ->from($table, [$idColumn, $valueColumn])
                        ->where("{$idColumn} > ?", $lastId)
                        ->where("{$valueColumn} LIKE ?", '%quote_path%')
                        ->order($idColumn)
                        ->limit(5000),
                );
                foreach ($rows as $id => $value) {
                    $lastId = (int) $id;
                    foreach ($this->findQuotePaths($stringHelper->unserialize((string) $value), $fileType) as $path) {
                        $used[$path] = true;
                    }
                }
            } while (count($rows) === 5000);
        }
        return $used;
    }

    /**
     * Mount paths of the quote copies that an option value names, at any depth
     *
     * @return list<string>
     */
    protected function findQuotePaths(mixed $value, Mage_Catalog_Model_Product_Option_Type_File $fileType): array
    {
        if (is_string($value) && str_contains($value, 'quote_path')) {
            $value = Mage::helper('core/string')->unserialize($value);
        }
        if (!is_array($value)) {
            return [];
        }
        if (isset($value['quote_path'])) {
            $path = $fileType->resolveStoragePath($value, 'quote_path');
            return $path === null ? [] : [$path];
        }
        $paths = [];
        foreach ($value as $child) {
            array_push($paths, ...$this->findQuotePaths($child, $fileType));
        }
        return $paths;
    }

    /**
     * IDs of the quote items in $itemIds that the orders $orderIds hold
     *
     * @param list<int|string> $orderIds
     * @param list<int> $itemIds
     * @return list<int>
     */
    protected function getOrderedItemIds(array $orderIds, array $itemIds): array
    {
        $adapter = $this->_getReadAdapter();
        return array_map(intval(...), $adapter->fetchCol(
            $adapter->select()
                ->distinct()
                ->from($this->getTable('sales/order_item'), ['quote_item_id'])
                ->where('order_id IN (?)', $orderIds)
                ->where('quote_item_id IN (?)', $itemIds),
        ));
    }
}
