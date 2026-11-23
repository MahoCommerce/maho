<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Mage;
use Maho\DataObject;

/**
 * @template TItem of DataObject = DataObject
 * @implements IteratorAggregate<array-key, TItem>
 */
class Collection implements IteratorAggregate, Countable
{
    public const string SORT_ORDER_ASC    = 'ASC';
    public const string SORT_ORDER_DESC   = 'DESC';

    /**
     * Collection items
     *
     * @var array<array-key, TItem>
     */
    protected $_items = [];

    /**
     * Item object class name
     *
     * @var string
     */
    protected $_itemObjectClass = DataObject::class;

    /**
     * Order configuration
     *
     * @var array<string, string>
     */
    protected $_orders = [];

    /**
     * Filters configuration
     *
     * @var array<int, DataObject|array{field: string, value: mixed, is_and: bool, callback: callable, is_inverted: bool}>
     */
    protected $_filters = [];

    /**
     * Filter rendered flag
     *
     * @var bool
     */
    protected $_isFiltersRendered = false;

    /**
     * Current page number for items pager
     *
     * @var int|null
     */
    protected $_curPage = 1;

    /**
     * Pager page size
     *
     * if page size is false, then we works with all items
     *
     * @var int|false|null
     */
    protected $_pageSize = false;

    /**
     * Total items number
     *
     * @var int|null
     */
    protected $_totalRecords;

    /**
     * Loading state flag
     *
     * @var bool
     */
    protected $_isCollectionLoaded;

    protected ?string $_cacheKey = null;

    /**
     * @var list<string>
     */
    protected array $_cacheTags = [];

    protected int $_cacheLifetime = 86400;

    /**
     * Additional collection flags
     *
     * @var array<string, mixed>
     */
    protected $_flags = [];

    public function __construct() {}

    /**
     * Add collection filter
     *
     * @param int|string|array<mixed> $value
     * @param string $type and|or|string|public
     */
    public function addFilter(string $field, int|string|array $value, string $type = 'and'): static
    {
        $filter = new DataObject(); // implements ArrayAccess
        $filter['field']   = $field;
        $filter['value']   = $value;
        $filter['type']    = strtolower($type);

        $this->_filters[] = $filter;
        $this->_isFiltersRendered = false;
        return $this;
    }

    /**
     * Search for a filter by specified field
     *
     * Multiple filters can be matched if an array is specified:
     * - 'foo' -- get the first filter with field name 'foo'
     * - array('foo') -- get all filters with field name 'foo'
     * - array('foo', 'bar') -- get all filters with field name 'foo' or 'bar'
     * - array() -- get all filters
     *
     * @param string|list<string> $field
     * @return DataObject|array<int, DataObject|array{field: string, value: mixed, is_and: bool, callback: callable, is_inverted: bool}>|null
     */
    public function getFilter($field)
    {
        if (is_array($field)) {
            // empty array: get all filters
            if (empty($field)) {
                return $this->_filters;
            }
            // non-empty array: collect all filters that match specified field names
            $result = [];
            foreach ($this->_filters as $filter) {
                if (in_array($filter['field'], $field)) {
                    $result[] = $filter;
                }
            }
            return $result;
        }

        // get a first filter by specified name
        foreach ($this->_filters as $filter) {
            if ($filter['field'] === $field) {
                return $filter;
            }
        }
        return null;
    }

    /**
     * Retrieve collection loading status
     *
     * @return bool
     */
    public function isLoaded()
    {
        return $this->_isCollectionLoaded;
    }

    /**
     * Set collection loading status flag
     *
     * @param bool $flag
     * @return $this
     */
    protected function _setIsLoaded($flag = true)
    {
        $this->_isCollectionLoaded = $flag;
        return $this;
    }

    /**
     * Get current collection page
     *
     * @param  int $displacement
     * @return int
     */
    public function getCurPage($displacement = 0)
    {
        if ($this->_curPage + $displacement <= 1) {
            return 1;
        }
        if ($this->_curPage + $displacement > $this->getLastPageNumber()) {
            return $this->getLastPageNumber();
        }
        return $this->_curPage + $displacement;
    }

    /**
     * Retrieve collection last page number
     *
     * @return int
     */
    public function getLastPageNumber()
    {
        $collectionSize = (int) $this->getSize();
        if (0 === $collectionSize) {
            return 1;
        }
        if ($this->_pageSize) {
            return (int) ceil($collectionSize / $this->_pageSize);
        }
        return 1;
    }

    /**
     * Retrieve collection page size
     *
     * @return int
     */
    public function getPageSize()
    {
        return $this->_pageSize;
    }

    /**
     * Retrieve collection all items count
     *
     * @return int
     */
    public function getSize()
    {
        $this->load();
        $this->_totalRecords ??= count($this->getItems());
        return (int) $this->_totalRecords;
    }

    /**
     * Retrieve collection first item
     *
     * @return TItem
     */
    public function getFirstItem(): DataObject
    {
        $this->load();

        return array_first($this->_items) ?? new $this->_itemObjectClass();
    }

    /**
     * Retrieve collection last item
     *
     * @return TItem
     */
    public function getLastItem(): DataObject
    {
        $this->load();

        return array_last($this->_items) ?? new $this->_itemObjectClass();
    }

    /**
     * Retrieve collection items
     *
     * @return array<array-key, TItem>
     */
    public function getItems(): array
    {
        $this->load();
        return $this->_items;
    }

    /**
     * Retrieve field values from all items
     *
     * @param   string $colName
     * @return  list<mixed>
     */
    public function getColumnValues($colName)
    {
        $this->load();

        $col = [];
        foreach ($this->getItems() as $item) {
            $col[] = $item->getData($colName);
        }
        return $col;
    }

    /**
     * Search all items by field value
     *
     * @return list<TItem>
     */
    public function getItemsByColumnValue(string $column, int|string|null $value): array
    {
        $this->load();

        $res = [];
        foreach ($this as $item) {
            if ($item->getData($column) == $value) {
                $res[] = $item;
            }
        }
        return $res;
    }

    /**
     * Search first item by field value
     *
     * @return TItem|null
     */
    public function getItemByColumnValue(string $column, int|string|null $value): ?DataObject
    {
        $this->load();

        foreach ($this as $item) {
            if ($item->getData($column) == $value) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Adding item to item array
     *
     * @param TItem $item
     */
    public function addItem(DataObject $item): static
    {
        $itemId = $this->_getItemId($item);

        if (!is_null($itemId)) {
            if (isset($this->_items[$itemId])) {
                throw new \Exception('Item (' . $item::class . ') with the same id "' . $item->getId() . '" already exist');
            }
            $this->_items[$itemId] = $item;
        } else {
            $this->_addItem($item);
        }
        return $this;
    }

    /**
     * Add item that has no id to collection
     *
     * @param TItem $item
     */
    protected function _addItem(DataObject $item): static
    {
        $this->_items[] = $item;
        return $this;
    }

    /**
     * Retrieve item id
     *
     * @return mixed
     */
    protected function _getItemId(DataObject $item)
    {
        return $item->getId();
    }

    /**
     * Retrieve ids of all tems
     *
     * @return list<mixed>
     */
    public function getAllIds()
    {
        $ids = [];
        foreach ($this->getItems() as $item) {
            $ids[] = $this->_getItemId($item);
        }
        return $ids;
    }

    /**
     * Remove item from collection by item key
     *
     * @param   mixed $key
     * @return  $this
     */
    public function removeItemByKey($key)
    {
        if (isset($this->_items[$key])) {
            unset($this->_items[$key]);
        }
        return $this;
    }

    /**
     * Clear collection
     *
     * @return $this
     */
    public function clear()
    {
        $this->_setIsLoaded(false);
        $this->_items = [];
        return $this;
    }

    /**
     * Walk through the collection and run model method or external callback
     * with optional arguments
     *
     * Returns array with results of callback for each item
     *
     * @param string|callable $callback
     * @param list<mixed> $args
     * @return array<array-key, mixed>
     */
    public function walk($callback, array $args = [])
    {
        $results = [];
        $useItemCallback = is_string($callback) && !str_contains($callback, '::');
        foreach ($this->getItems() as $id => $item) {
            $results[$id] = $useItemCallback ? $item->$callback(...$args) : $callback($item, ...$args);
        }
        return $results;
    }

    /**
     * Replace each item with the result of $callback for that item
     *
     * @param callable(TItem): TItem $callback
     */
    public function each(callable $callback): void
    {
        foreach ($this->getItems() as $key => $item) {
            $this->_items[$key] = $callback($item);
        }
    }

    /**
     * Setting data for all collection items
     *
     * @param   mixed $key
     * @param   mixed $value
     * @return  $this
     */
    public function setDataToAll($key, $value = null)
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->setDataToAll($k, $v);
            }
            return $this;
        }
        foreach ($this->getItems() as $item) {
            $item->setData($key, $value);
        }
        return $this;
    }

    /**
     * Set current page
     *
     * @param   int|null $page
     * @return  $this
     */
    public function setCurPage($page)
    {
        $this->_curPage = $page;
        return $this;
    }

    /**
     * Set collection page size
     *
     * @param   int|null $size
     * @return  $this
     */
    public function setPageSize($size)
    {
        $this->_pageSize = $size;
        return $this;
    }

    /**
     * Set select order
     *
     * @param   string $field
     * @param   string $direction
     * @return  $this
     */
    public function setOrder($field, $direction = self::SORT_ORDER_DESC)
    {
        $this->_orders[$field] = $direction;
        return $this;
    }

    /**
     * Set collection item class name
     *
     * @param   string $className
     * @return  $this
     */
    public function setItemObjectClass($className)
    {
        $className = Mage::getConfig()->getModelClassName($className);
        /**
         * is_subclass_of($className, DataObject::class) - Segmentation fault in php 5.2.3
         */
        /*if (!is_subclass_of($className, DataObject::class)) {
            throw new \Exception($className.' does not extends from DataObject');
        }*/
        $this->_itemObjectClass = $className;
        return $this;
    }

    /**
     * Retrieve collection empty item
     *
     * @return TItem
     */
    public function getNewEmptyItem(): DataObject
    {
        return new $this->_itemObjectClass();
    }

    /**
     * Render sql select conditions
     *
     * @return  Collection
     */
    protected function _renderFilters()
    {
        return $this;
    }

    /**
     * Render sql select orders
     *
     * @return  Collection
     */
    protected function _renderOrders()
    {
        return $this;
    }

    /**
     * Render sql select limit
     *
     * @return  Collection
     */
    protected function _renderLimit()
    {
        return $this;
    }

    /**
     * Set select distinct
     *
     * @param bool $flag
     * @return $this
     */
    public function distinct($flag)
    {
        return $this;
    }

    /**
     * Load data
     *
     * @param bool $printQuery
     * @param bool $logQuery
     * @return $this
     */
    public function loadData($printQuery = false, $logQuery = false)
    {
        return $this;
    }

    /**
     * Load data
     *
     * @param bool $printQuery
     * @param bool $logQuery
     * @return $this
     */
    public function load($printQuery = false, $logQuery = false)
    {
        return $this->loadData($printQuery, $logQuery);
    }

    /**
     * Convert collection to XML
     *
     * @return string
     */
    public function toXml()
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
        <collection>
           <totalRecords>' . $this->_totalRecords . '</totalRecords>
           <items>';

        foreach ($this as $item) {
            $xml .= $item->toXml();
        }
        $xml .= '</items>
        </collection>';
        return $xml;
    }

    /**
     * Convert collection to array
     *
     * @param list<string> $arrRequiredFields
     * @return array{totalRecords: int, items: list<array<array-key, mixed>>}|array<array-key, array<array-key, mixed>>
     */
    public function toArray($arrRequiredFields = [])
    {
        $arrItems = [];
        $arrItems['totalRecords'] = $this->getSize();

        $arrItems['items'] = [];
        foreach ($this as $item) {
            $arrItems['items'][] = $item->toArray($arrRequiredFields);
        }
        return $arrItems;
    }

    /**
     * Convert items array to array for select options
     *
     * return items array
     * array(
     *      $index => array(
     *          'value' => mixed
     *          'label' => mixed
     *      )
     * )
     *
     * @param string $valueField
     * @param string $labelField
     * @param array<string, string> $additional
     * @return ($additional is array{} ? list<array{value: mixed, label: mixed}> : list<array{value: mixed, label: mixed, ...<string, mixed>}>)
     */
    protected function _toOptionArray($valueField = 'id', $labelField = 'name', $additional = [])
    {
        $res = [];
        foreach ($this as $item) {
            $data = [];
            foreach ($additional as $code => $field) {
                $data[$code] = $item->getData($field);
            }
            $data['value'] = $item->getData($valueField);
            $data['label'] = $item->getData($labelField);
            $res[] = $data;
        }
        return $res;
    }

    /**
     * @return list<array{value: mixed, label: mixed, ...<string, mixed>}>
     */
    public function toOptionArray()
    {
        return $this->_toOptionArray();
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toOptionHash()
    {
        return $this->_toOptionHash();
    }

    /**
     * Convert items array to hash for select options
     *
     * return items hash
     * array($value => $label)
     *
     * @param   string $valueField
     * @param   string $labelField
     * @return  array<array-key, mixed>
     */
    protected function _toOptionHash($valueField = 'id', $labelField = 'name')
    {
        $res = [];
        foreach ($this as $item) {
            $res[$item->getData($valueField)] = $item->getData($labelField);
        }
        return $res;
    }

    /**
     * @return TItem|null
     */
    public function getItemById(int|string|null $idValue): ?DataObject
    {
        $this->load();
        if ($idValue === null) {
            return null;
        }
        return $this->_items[$idValue] ?? null;
    }

    /**
     * @return ArrayIterator<array-key, TItem>
     */
    #[\Override]
    public function getIterator(): ArrayIterator
    {
        $this->load();
        return new ArrayIterator($this->_items);
    }

    /**
     * Retrieve count of collection loaded items
     *
     * @return int<0, max>
     */
    #[\ReturnTypeWillChange]
    #[\Override]
    public function count()
    {
        $this->load();
        return count($this->_items);
    }

    /**
     * @param string $key
     * @return $this
     */
    public function setCacheKey($key)
    {
        $this->_cacheKey = $key;
        return $this;
    }

    /**
     * @return string
     */
    public function getCacheKey()
    {
        return $this->_cacheKey;
    }

    /**
     * @param list<string> $tags
     * @return $this
     */
    public function setCacheTags($tags)
    {
        $this->_cacheTags = $tags;
        return $this;
    }

    /**
     * @return list<string>
     */
    public function getCacheTags()
    {
        return $this->_cacheTags;
    }

    /**
     * @return int
     */
    public function getCacheLifetime()
    {
        return $this->_cacheLifetime;
    }

    /**
     * Retrieve Flag
     *
     * @param string $flag
     * @return mixed
     */
    public function getFlag($flag)
    {
        return $this->_flags[$flag] ?? null;
    }

    /**
     * Set Flag
     *
     * @param string $flag
     * @param mixed $value
     * @return $this
     */
    public function setFlag($flag, $value = null)
    {
        $this->_flags[$flag] = $value;
        return $this;
    }

    /**
     * Has Flag
     *
     * @param string $flag
     * @return bool
     */
    public function hasFlag($flag)
    {
        return array_key_exists($flag, $this->_flags);
    }
}
