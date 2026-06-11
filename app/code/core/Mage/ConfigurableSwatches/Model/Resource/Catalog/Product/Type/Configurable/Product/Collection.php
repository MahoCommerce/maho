<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ConfigurableSwatches
 */

class Mage_ConfigurableSwatches_Model_Resource_Catalog_Product_Type_Configurable_Product_Collection extends Mage_Catalog_Model_Resource_Product_Type_Configurable_Product_Collection
{
    /**
     * Filter by parent product set
     */
    public function addProductSetFilter(array $productIds)
    {
        $this->getSelect()->where('link_table.parent_id in (?)', $productIds);
    }

    /**
     * Load unique entities records into items
     *
     * @param bool $printQuery
     * @param bool $logQuery
     * @throws Exception
     * @return $this
     */
    #[\Override]
    public function _loadEntities($printQuery = false, $logQuery = false)
    {
        if ($this->_pageSize) {
            $this->getSelect()->limitPage($this->getCurPage(), $this->_pageSize);
        }

        $this->printLogQuery($printQuery, $logQuery);

        try {
            /**
             * Prepare select query
             */
            $query = $this->_prepareSelect($this->getSelect());
            $rows = $this->_fetchAll($query);
        } catch (Exception $e) {
            Mage::printException($e, $query);
        }

        foreach ($rows as $v) {
            if (!isset($this->_items[$v['entity_id']])) {
                $object = $this->getNewEmptyItem()
                    ->setData($v)
                    ->setParentIds([$v['parent_id']]);
                $this->addItem($object);
                if (isset($this->_itemsById[$object->getId()])) {
                    $this->_itemsById[$object->getId()][] = $object;
                } else {
                    $this->_itemsById[$object->getId()] = [$object];
                }
            } else {
                $parents = $this->_items[$v['entity_id']]->getParentIds();
                $parents[] = $v['parent_id'];
                $this->_items[$v['entity_id']]->setParentIds($parents);
            }
        }

        return $this;
    }
}
