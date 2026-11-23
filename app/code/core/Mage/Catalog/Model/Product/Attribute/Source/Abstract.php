<?php

/**
 * Source of a product attribute whose options a class defines in code. The collection sorts by the stored value.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

abstract class Mage_Catalog_Model_Product_Attribute_Source_Abstract extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    /**
     * Return the label of each option, by option value
     *
     * @return array<int, string>
     */
    abstract public function getOptionArray(): array;

    #[\Override]
    public function getAllOptions(bool $withEmpty = true, bool $defaultValues = false): array
    {
        $options = [];
        if ($withEmpty) {
            $options[] = ['value' => '', 'label' => Mage::helper('catalog')->__('-- Please Select --')];
        }
        foreach ($this->getOptionArray() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        return $options;
    }

    /**
     * @param string|int|null $value
     */
    #[\Override]
    public function getOptionText($value): string|false
    {
        return $this->getOptionArray()[(int) $value] ?? false;
    }

    /**
     * @param Mage_Catalog_Model_Resource_Product_Collection $collection
     * @param string $dir
     */
    #[\Override]
    public function addValueSortToCollection($collection, $dir = Maho\Db\Select::SQL_ASC): static
    {
        $attributeCode  = $this->getAttribute()->getAttributeCode();
        $attributeId    = (int) $this->getAttribute()->getId();
        $attributeTable = $this->getAttribute()->getBackend()->getTable();
        $valueTable1    = $attributeCode . '_t1';
        $valueTable2    = $attributeCode . '_t2';

        $collection->getSelect()
            ->joinLeft(
                [$valueTable1 => $attributeTable],
                "e.entity_id={$valueTable1}.entity_id"
                    . " AND {$valueTable1}.attribute_id={$attributeId}"
                    . " AND {$valueTable1}.store_id=0",
                [],
            )
            ->joinLeft(
                [$valueTable2 => $attributeTable],
                "e.entity_id={$valueTable2}.entity_id"
                    . " AND {$valueTable2}.attribute_id={$attributeId}"
                    . " AND {$valueTable2}.store_id=" . (int) $collection->getStoreId(),
                [],
            );
        $valueExpr = $collection->getConnection()->getCheckSql(
            $valueTable2 . '.value_id > 0',
            $valueTable2 . '.value',
            $valueTable1 . '.value',
        );

        $collection->getSelect()->order($valueExpr . ' ' . $dir);
        return $this;
    }
}
