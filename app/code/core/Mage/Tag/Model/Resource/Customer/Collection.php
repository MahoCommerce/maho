<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tag
 */

class Mage_Tag_Model_Resource_Customer_Collection extends Mage_Customer_Model_Resource_Customer_Collection
{
    /**
     * Allows disabling grouping
     *
     * @var bool
     */
    protected $_allowDisableGrouping     = true;

    /**
     * Count attribute for count sql
     *
     * @var string
     */
    protected $_countAttribute           = 'tr.tag_id';

    /**
     * Array with joined tables
     *
     * @var array
     */
    protected $_joinFlags                = [];

    /**
     * Prepare select
     *
     * @return $this
     */
    #[\Override]
    protected function _initSelect()
    {
        parent::_initSelect();
        $this->_joinFields();
        $this->_setIdFieldName('tag_relation_id');
        return $this;
    }

    /**
     * Adds filter by tag is
     *
     * @param int $tagId
     * @return $this
     */
    public function addTagFilter($tagId)
    {
        $this->getSelect()
            ->where('tr.tag_id = ?', $tagId);
        return $this;
    }

    /**
     * adds filter by product id
     *
     * @param int $productId
     * @return $this
     */
    public function addProductFilter($productId)
    {
        $this->getSelect()
            ->where('tr.product_id = ?', $productId);
        return $this;
    }

    /**
     * Apply filter by store id(s).
     *
     * @param int|array $storeId
     * @return $this
     */
    public function addStoreFilter($storeId)
    {
        $this->getSelect()->where('tr.store_id IN (?)', $storeId);
        return $this;
    }

    /**
     * Adds filter by status
     *
     * @param int $status
     * @return $this
     */
    public function addStatusFilter($status)
    {
        $this->getSelect()
            ->where('t.status = ?', $status);
        return $this;
    }

    /**
     * Adds desc order by tag relation id
     *
     * @return $this
     */
    public function addDescOrder()
    {
        // Order by the output alias (not tr.tag_relation_id) so the ORDER BY stays
        // valid under strict GROUP BY when stacked with addGroupByTag(), which
        // rewrites the column into an aggregate expression with the same alias
        $this->getSelect()
            ->order('tag_relation_id desc');
        return $this;
    }

    /**
     * Adds grouping by tag id
     *
     * @return $this
     */
    public function addGroupByTag()
    {
        // Grouping by the tag and customer primary keys legalizes t.* and e.* under
        // strict GROUP BY (ONLY_FULL_GROUP_BY / PostgreSQL); the tag relation columns
        // are not functionally dependent on them and must be aggregated
        $this->_aggregateRelationColumns();
        $this->getSelect()
            ->group(['tr.tag_id', 't.tag_id', 'e.entity_id']);
        $this->setFlag('group_tag', true);

        /*
         * Allow analytic functions usage
         */
        $this->_useAnalyticFunction = true;

        $this->_allowDisableGrouping = true;
        return $this;
    }

    /**
     * Adds grouping by customer id
     *
     * @return $this
     */
    public function addGroupByCustomer()
    {
        // Grouping by the customer primary key legalizes e.* under strict GROUP BY;
        // the tag relation and tag table columns must be aggregated
        $this->_aggregateRelationColumns(true);
        $this->getSelect()
            ->group(['tr.customer_id', 'e.entity_id']);

        $this->_allowDisableGrouping = false;
        return $this;
    }

    /**
     * Replaces the tag relation columns (and optionally the tag table columns) in
     * the select with aggregate expressions keeping the same aliases, so grouped
     * queries stay valid under strict GROUP BY (ONLY_FULL_GROUP_BY / PostgreSQL)
     *
     * @param bool $aggregateTagColumns whether to expand t.* into aggregated columns
     * @return $this
     */
    protected function _aggregateRelationColumns($aggregateTagColumns = false)
    {
        $aggregates = [
            'tag_relation_id' => 'MAX(tr.tag_relation_id)',
            'product_id'      => 'MAX(tr.product_id)',
            'active'          => 'MAX(tr.active)',
            'added_in'        => 'MIN(tr.store_id)',
        ];

        $columns = [];
        foreach ($this->getSelect()->getPart(Maho\Db\Select::COLUMNS) as $columnEntry) {
            [$correlationName, $column, $alias] = $columnEntry;
            if ($correlationName == 'tr' && is_string($column)) {
                $alias = $alias ?: $column;
                if (isset($aggregates[$alias])) {
                    $columns[] = ['', new Maho\Db\Expr($aggregates[$alias]), $alias];
                    continue;
                }
            } elseif ($aggregateTagColumns && $correlationName == 't' && $column === '*') {
                $tagColumns = $this->getConnection()->describeTable($this->getTable('tag/tag'));
                foreach (array_keys($tagColumns) as $tagColumn) {
                    $columns[] = ['', new Maho\Db\Expr('MAX(t.' . $tagColumn . ')'), $tagColumn];
                }
                continue;
            }
            $columns[] = $columnEntry;
        }
        $this->getSelect()->setPart(Maho\Db\Select::COLUMNS, $columns);
        return $this;
    }

    /**
     * Disables grouping
     *
     * @return $this
     */
    public function addGroupByCustomerProduct()
    {
        // Nothing need to group
        $this->_allowDisableGrouping = false;
        return $this;
    }

    /**
     * Adds filter by customer id
     *
     * @param int $customerId
     * @return $this
     */
    public function addCustomerFilter($customerId)
    {
        $this->getSelect()->where('tr.customer_id = ?', $customerId);
        return $this;
    }

    /**
     * Joins tables to select
     */
    protected function _joinFields()
    {
        $tagRelationTable = $this->getTable('tag/relation');
        $tagTable = $this->getTable('tag/tag');

        //TODO: add full name logic
        $this->addAttributeToSelect('firstname')
            ->addAttributeToSelect('middlename')
            ->addAttributeToSelect('lastname')
            ->addAttributeToSelect('email');

        $this->getSelect()
        ->join(
            ['tr' => $tagRelationTable],
            'tr.customer_id = e.entity_id',
            ['tag_relation_id', 'product_id', 'active', 'added_in' => 'store_id'],
        )
        ->join(['t' => $tagTable], 't.tag_id = tr.tag_id', ['*']);
    }

    /**
     * Gets number of rows
     *
     * @return Maho\Db\Select
     */
    #[\Override]
    public function getSelectCountSql()
    {
        if ($this->getFlag('group_tag')) {
            // The select is grouped by (tag, customer): COUNT(DISTINCT tr.tag_id)
            // would undercount when several customers share a tag, and multi-column
            // COUNT(DISTINCT) is not portable to PostgreSQL, so count the grouped
            // rows through a derived table
            $groupSelect = clone $this->getSelect();
            $groupSelect->reset(Maho\Db\Select::ORDER);
            $groupSelect->reset(Maho\Db\Select::LIMIT_COUNT);
            $groupSelect->reset(Maho\Db\Select::LIMIT_OFFSET);
            $groupSelect->reset(Maho\Db\Select::COLUMNS);
            $groupSelect->columns(new Maho\Db\Expr('1'));

            return $this->getConnection()->select()
                ->from(['counted' => $groupSelect], new Maho\Db\Expr('COUNT(*)'));
        }

        $countSelect = parent::getSelectCountSql();

        if ($this->_allowDisableGrouping) {
            $countSelect->reset(Maho\Db\Select::COLUMNS);
            $countSelect->reset(Maho\Db\Select::GROUP);
            $countSelect->columns('COUNT(DISTINCT ' . $this->getCountAttribute() . ')');
        }
        return $countSelect;
    }

    /**
     * Adds Product names to item
     *
     * @return $this
     */
    public function addProductName()
    {
        $productsId   = [];
        $productsSku  = [];
        $productsData = [];

        foreach ($this->getItems() as $item) {
            $productsId[] = $item->getProductId();
        }

        $productsId = array_unique($productsId);

        /* small fix */
        if (!count($productsId)) {
            return $this;
        }

        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToSelect('name')
            ->addAttributeToSelect('sku')
            ->addIdFilter($productsId);

        $collection->load();

        foreach ($collection->getItems() as $item) {
            $productsData[$item->getId()] = $item->getName();
            $productsSku[$item->getId()] = $item->getSku();
        }

        foreach ($this->getItems() as $item) {
            $item->setProduct($productsData[$item->getProductId()]);
            $item->setProductSku($productsSku[$item->getProductId()]);
        }
        return $this;
    }

    /**
     * Adds Product names to select
     *
     * @return $this
     */
    public function addProductToSelect()
    {
        $resource = Mage::getModel('catalog/product')->getResource();

        // add product attributes to select
        foreach (['name' => 'value'] as $field => $fieldName) {
            $attr = $resource->getAttribute($field);
            $this->_select->joinLeft(
                [$field => $attr->getBackend()->getTable()],
                'tr.product_id = ' . $field . '.entity_id AND ' . $field . '.attribute_id = ' . $attr->getId(),
                ['product_' . $field => $fieldName],
            );
        }

        // add product fields
        $this->_select->joinLeft(
            ['p' => $this->getTable('catalog/product')],
            'tr.product_id = p.entity_id',
            ['product_sku' => 'sku'],
        );

        return $this;
    }

    /**
     * Sets attribute for count
     *
     * @param string $value
     * @return $this
     */
    public function setCountAttribute($value)
    {
        $this->_countAttribute = $value;
        return $this;
    }

    /**
     * Gets attribure for count
     *
     * @return string
     */
    public function getCountAttribute()
    {
        return $this->_countAttribute;
    }

    #[\Override]
    public function addFieldToFilter($attribute, $condition = null)
    {
        if ($attribute == 'name') {
            $where = $this->_getConditionSql('t.name', $condition);
            $this->getSelect()->where($where, null, Maho\Db\Select::TYPE_CONDITION);
            return $this;
        }
        return parent::addFieldToFilter($attribute, $condition);
    }

    /**
     * Treat "order by" items as attributes to sort
     *
     * @return $this
     */
    #[\Override]
    protected function _renderOrders()
    {
        if (!$this->_isOrdersRendered) {
            parent::_renderOrders();

            $orders = $this->getSelect()
                ->getPart(Maho\Db\Select::ORDER);

            $appliedOrders = [];
            foreach ($orders as $order) {
                $appliedOrders[$order[0]] = true;
            }

            foreach ($this->_orders as $field => $direction) {
                if (empty($appliedOrders[$field])) {
                    $this->_select->order(new Maho\Db\Expr($field . ' ' . $direction));
                }
            }
        }
        return $this;
    }
}
