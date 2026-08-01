<?php

/**
 * Stock conditions for dynamic category rules.
 *
 * Inventory lives in cataloginventory_stock_item / cataloginventory_stock_status rather than EAV, so
 * these values can never surface through the product attribute conditions. Each attribute joins its
 * source onto the product collection once, up front, and validates in memory against the walked row.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

class Mage_Catalog_Model_Category_Dynamic_Rule_Condition_Stock extends Mage_Rule_Model_Condition_Abstract
{
    public const ATTRIBUTE_IS_SALABLE   = 'is_salable';
    public const ATTRIBUTE_IS_IN_STOCK  = 'is_in_stock';
    public const ATTRIBUTE_QTY          = 'qty';
    public const ATTRIBUTE_CHILDREN_QTY = 'children_qty';

    /**
     * Row keys are namespaced so a joined inventory column can never collide with an EAV attribute
     * of the same name already selected on the collection (qty and is_in_stock are common codes).
     */
    public const COLUMN_PREFIX = 'dyncat_stock_';

    public function __construct()
    {
        parent::__construct();
        $this->setType('catalog/category_dynamic_rule_condition_stock');
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $helper = Mage::helper('catalog');
        $this->setAttributeOption([
            self::ATTRIBUTE_IS_SALABLE   => $helper->__('Is Salable'),
            self::ATTRIBUTE_IS_IN_STOCK  => $helper->__('In Stock'),
            self::ATTRIBUTE_QTY          => $helper->__('Quantity In Stock'),
            self::ATTRIBUTE_CHILDREN_QTY => $helper->__('Total Child Products Qty'),
        ]);

        return $this;
    }

    protected function _isBooleanAttribute(): bool
    {
        return in_array($this->getAttribute(), [self::ATTRIBUTE_IS_SALABLE, self::ATTRIBUTE_IS_IN_STOCK], true);
    }

    #[\Override]
    public function getInputType(): string
    {
        return $this->_isBooleanAttribute() ? 'select' : 'numeric';
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return $this->_isBooleanAttribute() ? 'select' : 'text';
    }

    #[\Override]
    public function getValueSelectOptions(): array
    {
        if (!$this->_isBooleanAttribute()) {
            return parent::getValueSelectOptions();
        }

        return [
            ['value' => '1', 'label' => Mage::helper('catalog')->__('Yes')],
            ['value' => '0', 'label' => Mage::helper('catalog')->__('No')],
        ];
    }

    #[\Override]
    public function loadArray($arr): self
    {
        parent::loadArray($arr);

        // Quantities are typed by a human, so accept the admin's locale decimal separator
        $value = $this->getData('value');
        if (!$this->_isBooleanAttribute() && !$this->isArrayOperatorType() && is_string($value) && $value !== '') {
            $this->setValue((string) Mage::app()->getLocale()->getNumber($value));
        }

        return $this;
    }

    /**
     * Join this attribute's source onto the collection the rule is about to walk.
     *
     * Called once per condition instance before the walk, mirroring
     * Mage_Rule_Model_Condition_Product_Abstract::collectValidatedAttributes(). A collection flag keeps
     * repeated conditions on the same attribute from joining the same table twice.
     */
    public function collectValidatedAttributes(Mage_Catalog_Model_Resource_Product_Collection $productCollection): self
    {
        $attribute = $this->getAttribute();
        $flag = 'dyncat_stock_joined_' . $attribute;
        if ($productCollection->getFlag($flag)) {
            return $this;
        }

        match ($attribute) {
            self::ATTRIBUTE_IS_SALABLE   => $this->_joinStockStatus($productCollection),
            self::ATTRIBUTE_CHILDREN_QTY => $this->_joinChildrenQty($productCollection),
            self::ATTRIBUTE_QTY, self::ATTRIBUTE_IS_IN_STOCK => $this->_joinStockItem($productCollection),
            default => null,
        };

        $productCollection->setFlag($flag, true);

        return $this;
    }

    /**
     * qty and is_in_stock both come from the same row, so one join serves either condition.
     */
    protected function _joinStockItem(Mage_Catalog_Model_Resource_Product_Collection $productCollection): void
    {
        if ($productCollection->getFlag('dyncat_stock_item_joined')) {
            return;
        }

        $productCollection->getSelect()->joinLeft(
            ['dyncat_stock_item' => $this->_getTable('cataloginventory/stock_item')],
            'dyncat_stock_item.product_id = e.entity_id AND dyncat_stock_item.stock_id = ' . $this->_getStockId(),
            [
                self::COLUMN_PREFIX . self::ATTRIBUTE_QTY => new Maho\Db\Expr('COALESCE(dyncat_stock_item.qty, 0)'),
                self::COLUMN_PREFIX . self::ATTRIBUTE_IS_IN_STOCK => new Maho\Db\Expr('COALESCE(dyncat_stock_item.is_in_stock, 0)'),
            ],
        );

        $productCollection->setFlag('dyncat_stock_item_joined', true);
    }

    /**
     * Salability is website-scoped in the index; a category is not, so a product counts as salable
     * when it is salable on at least one website.
     */
    protected function _joinStockStatus(Mage_Catalog_Model_Resource_Product_Collection $productCollection): void
    {
        $statusSelect = $productCollection->getConnection()->select()
            ->from(
                ['dyncat_status_src' => $this->_getTable('cataloginventory/stock_status')],
                ['product_id', 'is_salable' => new Maho\Db\Expr('MAX(dyncat_status_src.stock_status)')],
            )
            ->where('dyncat_status_src.stock_id = ?', $this->_getStockId())
            ->group('dyncat_status_src.product_id');

        $productCollection->getSelect()->joinLeft(
            ['dyncat_status' => $statusSelect],
            'dyncat_status.product_id = e.entity_id',
            [self::COLUMN_PREFIX . self::ATTRIBUTE_IS_SALABLE => new Maho\Db\Expr('COALESCE(dyncat_status.is_salable, 0)')],
        );
    }

    /**
     * Summed qty of every child of a composite product; 0 for products that have no children.
     */
    protected function _joinChildrenQty(Mage_Catalog_Model_Resource_Product_Collection $productCollection): void
    {
        $childrenSelect = $productCollection->getConnection()->select()
            ->from(['dyncat_rel' => $this->_getTable('catalog/product_relation')], ['parent_id'])
            ->join(
                ['dyncat_child_stock' => $this->_getTable('cataloginventory/stock_item')],
                'dyncat_child_stock.product_id = dyncat_rel.child_id'
                    . ' AND dyncat_child_stock.stock_id = ' . $this->_getStockId(),
                ['children_qty' => new Maho\Db\Expr('SUM(dyncat_child_stock.qty)')],
            )
            ->group('dyncat_rel.parent_id');

        $productCollection->getSelect()->joinLeft(
            ['dyncat_children' => $childrenSelect],
            'dyncat_children.parent_id = e.entity_id',
            [self::COLUMN_PREFIX . self::ATTRIBUTE_CHILDREN_QTY => new Maho\Db\Expr('COALESCE(dyncat_children.children_qty, 0)')],
        );
    }

    protected function _getTable(string $alias): string
    {
        return Mage::getSingleton('core/resource')->getTableName($alias);
    }

    protected function _getStockId(): int
    {
        return Mage_CatalogInventory_Model_Stock::DEFAULT_STOCK_ID;
    }

    #[\Override]
    public function validate(\Maho\DataObject $object): bool
    {
        return $this->validateAttribute($object->getData(self::COLUMN_PREFIX . $this->getAttribute()));
    }
}
