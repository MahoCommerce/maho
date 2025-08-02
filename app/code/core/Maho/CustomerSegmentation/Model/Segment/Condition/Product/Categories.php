<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Segment_Condition_Product_Categories extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_product_categories');
        $this->setValue(null);
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return [
            'value' => $this->getType(),
            'label' => Mage::helper('customersegmentation')->__('Product Categories Purchased'),
        ];
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'category_id' => Mage::helper('customersegmentation')->__('Has Purchased From Category'),
            'category_count' => Mage::helper('customersegmentation')->__('Number of Different Categories Purchased From'),
            'category_total_amount' => Mage::helper('customersegmentation')->__('Total Amount Spent in Category'),
            'category_order_count' => Mage::helper('customersegmentation')->__('Number of Orders in Category'),
            'category_product_count' => Mage::helper('customersegmentation')->__('Number of Products Purchased in Category'),
            'last_category_purchase' => Mage::helper('customersegmentation')->__('Last Purchase Date in Category'),
            'days_since_category_purchase' => Mage::helper('customersegmentation')->__('Days Since Last Category Purchase'),
            'favorite_category' => Mage::helper('customersegmentation')->__('Most Purchased Category'),
            'category_repeat_purchase' => Mage::helper('customersegmentation')->__('Has Repeat Purchases in Category'),
        ];

        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getInputType(): string
    {
        return match ($this->getAttribute()) {
            'category_id', 'favorite_category' => 'select',
            'category_count', 'category_total_amount', 'category_order_count', 'category_product_count', 'days_since_category_purchase' => 'numeric',
            'last_category_purchase' => 'date',
            'category_repeat_purchase' => 'select',
            default => 'string',
        };
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return match ($this->getAttribute()) {
            'category_id', 'favorite_category', 'category_repeat_purchase' => 'select',
            'last_category_purchase' => 'date',
            default => 'text',
        };
    }

    #[\Override]
    public function getValueSelectOptions(): array
    {
        $options = [];
        switch ($this->getAttribute()) {
            case 'category_id':
            case 'favorite_category':
                // Get categories
                $categories = Mage::getResourceModel('catalog/category_collection')
                    ->addAttributeToSelect('name')
                    ->addAttributeToFilter('level', ['gt' => 1])
                    ->addAttributeToFilter('is_active', 1)
                    ->setOrder('name', 'ASC');

                $options = [['value' => '', 'label' => Mage::helper('customersegmentation')->__('Please select...')]];
                foreach ($categories as $category) {
                    $prefix = str_repeat('--', $category->getLevel() - 2);
                    $options[] = [
                        'value' => $category->getId(),
                        'label' => $prefix . ' ' . $category->getName(),
                    ];
                }
                break;

            case 'category_repeat_purchase':
                $options = [
                    ['value' => '1', 'label' => Mage::helper('customersegmentation')->__('Yes')],
                    ['value' => '0', 'label' => Mage::helper('customersegmentation')->__('No')],
                ];
                break;
        }
        return $options;
    }

    #[\Override]
    public function getConditionsSql(Varien_Db_Adapter_Interface $adapter, ?int $websiteId = null): string|false
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = $this->getValue();
        return match ($attribute) {
            'category_id' => $this->buildCategoryPurchaseCondition($adapter, $operator, $value),
            'category_count' => $this->buildCategoryCountCondition($adapter, $operator, $value),
            'category_total_amount' => $this->buildCategoryTotalAmountCondition($adapter, $operator, $value),
            'category_order_count' => $this->buildCategoryOrderCountCondition($adapter, $operator, $value),
            'category_product_count' => $this->buildCategoryProductCountCondition($adapter, $operator, $value),
            'last_category_purchase' => $this->buildLastCategoryPurchaseCondition($adapter, $operator, $value),
            'days_since_category_purchase' => $this->buildDaysSinceCategoryPurchaseCondition($adapter, $operator, $value),
            'favorite_category' => $this->buildFavoriteCategoryCondition($adapter, $operator, $value),
            'category_repeat_purchase' => $this->buildCategoryRepeatPurchaseCondition($adapter, $operator, $value),
            default => false,
        };
    }

    protected function buildCategoryPurchaseCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id'])
            ->join(['oi' => $this->getOrderItemTable()], 'o.entity_id = oi.order_id', [])
            ->join(['ccp' => $this->getCatalogCategoryProductTable()], 'oi.product_id = ccp.product_id', [])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->where($this->_buildSqlCondition($adapter, 'ccp.category_id', $operator, $value))
            ->group('o.customer_id');

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildCategoryCountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id'])
            ->join(['oi' => $this->getOrderItemTable()], 'o.entity_id = oi.order_id', [])
            ->join(['ccp' => $this->getCatalogCategoryProductTable()], 'oi.product_id = ccp.product_id', [])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'COUNT(DISTINCT ccp.category_id)', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildCategoryTotalAmountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        // This assumes the operator context includes which category
        // In practice, this would need a compound condition with category selection
        $subselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id'])
            ->join(['oi' => $this->getOrderItemTable()], 'o.entity_id = oi.order_id', ['total' => 'SUM(oi.row_total)'])
            ->join(['ccp' => $this->getCatalogCategoryProductTable()], 'oi.product_id = ccp.product_id', [])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'total', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildCategoryOrderCountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id'])
            ->join(['oi' => $this->getOrderItemTable()], 'o.entity_id = oi.order_id', [])
            ->join(['ccp' => $this->getCatalogCategoryProductTable()], 'oi.product_id = ccp.product_id', [])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'COUNT(DISTINCT o.entity_id)', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildCategoryProductCountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id'])
            ->join(['oi' => $this->getOrderItemTable()], 'o.entity_id = oi.order_id', ['product_count' => 'SUM(oi.qty_ordered)'])
            ->join(['ccp' => $this->getCatalogCategoryProductTable()], 'oi.product_id = ccp.product_id', [])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'product_count', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildLastCategoryPurchaseCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id', 'last_purchase' => 'MAX(o.created_at)'])
            ->join(['oi' => $this->getOrderItemTable()], 'o.entity_id = oi.order_id', [])
            ->join(['ccp' => $this->getCatalogCategoryProductTable()], 'oi.product_id = ccp.product_id', [])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'last_purchase', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildDaysSinceCategoryPurchaseCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id', 'last_purchase' => 'MAX(o.created_at)'])
            ->join(['oi' => $this->getOrderItemTable()], 'o.entity_id = oi.order_id', [])
            ->join(['ccp' => $this->getCatalogCategoryProductTable()], 'oi.product_id = ccp.product_id', [])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group('o.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'DATEDIFF(NOW(), last_purchase)', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildFavoriteCategoryCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        // Find customers whose most purchased category matches the condition
        $innerSelect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id'])
            ->join(['oi' => $this->getOrderItemTable()], 'o.entity_id = oi.order_id', [])
            ->join(['ccp' => $this->getCatalogCategoryProductTable()], 'oi.product_id = ccp.product_id', ['category_id', 'purchase_count' => 'COUNT(*)'])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group(['o.customer_id', 'ccp.category_id']);

        $subselect = $adapter->select()
            ->from(['inner' => $innerSelect], ['customer_id'])
            ->join(
                ['max_cat' => new Zend_Db_Expr("(SELECT customer_id, MAX(purchase_count) as max_count FROM ({$innerSelect}) as t GROUP BY customer_id)")],
                'inner.customer_id = max_cat.customer_id AND inner.purchase_count = max_cat.max_count',
                [],
            )
            ->where($this->_buildSqlCondition($adapter, 'inner.category_id', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildCategoryRepeatPurchaseCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['o' => $this->getOrderTable()], ['customer_id'])
            ->join(['oi' => $this->getOrderItemTable()], 'o.entity_id = oi.order_id', [])
            ->join(['ccp' => $this->getCatalogCategoryProductTable()], 'oi.product_id = ccp.product_id', [])
            ->where('o.customer_id IS NOT NULL')
            ->where('o.state NOT IN (?)', ['canceled'])
            ->group(['o.customer_id', 'ccp.category_id'])
            ->having('COUNT(DISTINCT o.entity_id) > 1');

        if (($operator == '=' && $value == '0') || ($operator == '!=' && $value == '1')) {
            return 'e.entity_id NOT IN (' . $subselect . ')';
        }

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function getOrderItemTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('sales/order_item');
    }

    protected function getCatalogCategoryProductTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('catalog/category_product');
    }
}
