<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

class Mage_Catalog_Model_Resource_Layer_Filter_Attribute extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalog/product_index_eav', 'entity_id');
    }

    /**
     * Apply attribute filter to product collection
     *
     * @param Mage_Catalog_Model_Layer_Filter_Attribute $filter
     * @param int|array $value single option id, or a list of option ids for a
     *                         multi-select filter (matched with IN(), i.e. OR)
     * @return $this
     */
    public function applyFilterToCollection($filter, $value)
    {
        $collection = $filter->getLayer()->getProductCollection();
        $attribute  = $filter->getAttributeModel();
        $connection = $this->_getReadAdapter();
        $tableAlias = $attribute->getAttributeCode() . '_idx';

        if (is_array($value)) {
            // The index holds one row per value, and a product can own several
            // (multiselect attributes, configurables inheriting their children's
            // values), so a plain IN() join would return it once per match. Join a
            // de-duplicated subselect to keep one row per product, under the same
            // alias getCount() drops below.
            $subSelect = $connection->select()
                ->distinct(true)
                ->from($this->getMainTable(), ['entity_id'])
                ->where('attribute_id = ?', $attribute->getAttributeId())
                ->where('store_id = ?', $collection->getStoreId())
                ->where('value IN (?)', $value);

            $collection->getSelect()->join(
                [$tableAlias => new Maho\Db\Expr('(' . $subSelect . ')')],
                "{$tableAlias}.entity_id = e.entity_id",
                [],
            );

            return $this;
        }

        $conditions = [
            "{$tableAlias}.entity_id = e.entity_id",
            $connection->quoteInto("{$tableAlias}.attribute_id = ?", $attribute->getAttributeId()),
            $connection->quoteInto("{$tableAlias}.store_id = ?", $collection->getStoreId()),
            $connection->quoteInto("{$tableAlias}.value = ?", $value),
        ];

        $collection->getSelect()->join(
            [$tableAlias => $this->getMainTable()],
            implode(' AND ', $conditions),
            [],
        );

        return $this;
    }

    /**
     * Retrieve array with products counts per attribute option
     *
     * @param Mage_Catalog_Model_Layer_Filter_Attribute $filter
     * @return array
     */
    public function getCount($filter)
    {
        // clone select from collection with filters
        $select = clone $filter->getLayer()->getProductCollection()->getSelect();
        // reset columns, order and limitation conditions
        $select->reset(Maho\Db\Select::COLUMNS);
        $select->reset(Maho\Db\Select::ORDER);
        $select->reset(Maho\Db\Select::LIMIT_COUNT);
        $select->reset(Maho\Db\Select::LIMIT_OFFSET);

        $connection = $this->_getReadAdapter();
        $attribute  = $filter->getAttributeModel();
        $tableAlias = sprintf('%s_idx', $attribute->getAttributeCode());

        // For a multi-select filter this facet's own values are already joined
        // into the collection (OR within the facet). Drop that join before
        // counting so every option is counted against the *other* active facets
        // only, keeping the whole facet visible instead of collapsing to the
        // selected values. For any other facet the alias is absent and this is a
        // no-op, so cross-facet AND filtering is preserved.
        $fromPart = (array) $select->getPart(Maho\Db\Select::FROM);
        if (isset($fromPart[$tableAlias])) {
            unset($fromPart[$tableAlias]);
            $select->setPart(Maho\Db\Select::FROM, $fromPart);
        }

        $conditions = [
            "{$tableAlias}.entity_id = e.entity_id",
            $connection->quoteInto("{$tableAlias}.attribute_id = ?", $attribute->getAttributeId()),
            $connection->quoteInto("{$tableAlias}.store_id = ?", $filter->getStoreId()),
        ];

        $select
            ->join(
                [$tableAlias => $this->getMainTable()],
                implode(' AND ', $conditions),
                ['value', 'count' => new Maho\Db\Expr("COUNT({$tableAlias}.entity_id)")],
            )
            ->group("{$tableAlias}.value");

        return $connection->fetchPairs($select);
    }
}
