<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Segment_Condition_Product_Wishlist extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_product_wishlist');
        $this->setValue(null);
    }

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return [
            'value' => $this->getType(),
            'label' => Mage::helper('customersegmentation')->__('Wishlist'),
        ];
    }

    #[\Override]
    public function loadAttributeOptions(): self
    {
        $attributes = [
            'product_id' => Mage::helper('customersegmentation')->__('Product ID'),
            'product_name' => Mage::helper('customersegmentation')->__('Product Name'),
            'product_sku' => Mage::helper('customersegmentation')->__('Product SKU'),
            'category_id' => Mage::helper('customersegmentation')->__('Category'),
            'wishlist_items_count' => Mage::helper('customersegmentation')->__('Wishlist Items Count'),
            'added_at' => Mage::helper('customersegmentation')->__('Added to Wishlist Date'),
            'days_since_added' => Mage::helper('customersegmentation')->__('Days Since Added to Wishlist'),
            'wishlist_shared' => Mage::helper('customersegmentation')->__('Wishlist Shared'),
        ];

        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getAttributeElement(): Varien_Data_Form_Element_Abstract
    {
        if (!$this->hasAttributeOption()) {
            $this->loadAttributeOptions();
        }

        $element = parent::getAttributeElement();
        return $element;
    }

    #[\Override]
    public function getInputType(): string
    {
        return match ($this->getAttribute()) {
            'product_id', 'category_id', 'wishlist_items_count', 'days_since_added' => 'numeric',
            'added_at' => 'date',
            'wishlist_shared' => 'select',
            default => 'string',
        };
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return match ($this->getAttribute()) {
            'added_at' => 'date',
            'wishlist_shared', 'category_id' => 'select',
            default => 'text',
        };
    }

    #[\Override]
    public function getValueSelectOptions(): array
    {
        $options = [];
        switch ($this->getAttribute()) {
            case 'wishlist_shared':
                $options = [
                    ['value' => '', 'label' => Mage::helper('customersegmentation')->__('Please select...')],
                    ['value' => '1', 'label' => Mage::helper('customersegmentation')->__('Yes')],
                    ['value' => '0', 'label' => Mage::helper('customersegmentation')->__('No')],
                ];
                break;
            case 'category_id':
                // Get categories
                $categories = Mage::getResourceModel('catalog/category_collection')
                    ->addAttributeToSelect('name')
                    ->addAttributeToFilter('level', ['gt' => 1])
                    ->addAttributeToFilter('is_active', 1)
                    ->setOrder('name', 'ASC');

                $options = [['value' => '', 'label' => Mage::helper('customersegmentation')->__('Please select...')]];
                foreach ($categories as $category) {
                    $options[] = [
                        'value' => $category->getId(),
                        'label' => $category->getName(),
                    ];
                }
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
            'product_id' => $this->_buildProductWishlistCondition($adapter, $operator, $value),
            'product_name' => $this->_buildProductNameWishlistCondition($adapter, $operator, $value),
            'product_sku' => $this->_buildProductSkuWishlistCondition($adapter, $operator, $value),
            'category_id' => $this->_buildCategoryWishlistCondition($adapter, $operator, $value),
            'wishlist_items_count' => $this->_buildWishlistItemsCountCondition($adapter, $operator, $value),
            'added_at' => $this->_buildAddedAtCondition($adapter, $operator, $value),
            'days_since_added' => $this->_buildDaysSinceAddedCondition($adapter, $operator, $value),
            'wishlist_shared' => $this->_buildWishlistSharedCondition($adapter, $operator, $value),
            default => false,
        };
    }

    protected function _buildProductWishlistCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['wi' => $this->_getWishlistItemTable()], [])
            ->join(['w' => $this->_getWishlistTable()], 'wi.wishlist_id = w.wishlist_id', ['customer_id'])
            ->where('w.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, 'wi.product_id', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildProductNameWishlistCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['wi' => $this->_getWishlistItemTable()], [])
            ->join(['w' => $this->_getWishlistTable()], 'wi.wishlist_id = w.wishlist_id', ['customer_id'])
            ->join(['p' => $this->_getProductTable()], 'wi.product_id = p.entity_id', [])
            ->join(['pv' => $this->_getProductVarcharTable()], 'p.entity_id = pv.entity_id AND pv.attribute_id = ' . $this->_getNameAttributeId(), [])
            ->where('w.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, 'pv.value', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildProductSkuWishlistCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['wi' => $this->_getWishlistItemTable()], [])
            ->join(['w' => $this->_getWishlistTable()], 'wi.wishlist_id = w.wishlist_id', ['customer_id'])
            ->join(['p' => $this->_getProductTable()], 'wi.product_id = p.entity_id', [])
            ->where('w.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, 'p.sku', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildCategoryWishlistCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['wi' => $this->_getWishlistItemTable()], [])
            ->join(['w' => $this->_getWishlistTable()], 'wi.wishlist_id = w.wishlist_id', ['customer_id'])
            ->join(['ccp' => $this->_getCatalogCategoryProductTable()], 'wi.product_id = ccp.product_id', [])
            ->where('w.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, 'ccp.category_id', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildWishlistItemsCountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['wi' => $this->_getWishlistItemTable()], [])
            ->join(['w' => $this->_getWishlistTable()], 'wi.wishlist_id = w.wishlist_id', ['customer_id', 'items_count' => 'COUNT(*)'])
            ->where('w.customer_id IS NOT NULL')
            ->group('w.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'items_count', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildAddedAtCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['wi' => $this->_getWishlistItemTable()], [])
            ->join(['w' => $this->_getWishlistTable()], 'wi.wishlist_id = w.wishlist_id', ['customer_id'])
            ->where('w.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, 'wi.added_at', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildDaysSinceAddedCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['wi' => $this->_getWishlistItemTable()], [])
            ->join(['w' => $this->_getWishlistTable()], 'wi.wishlist_id = w.wishlist_id', ['customer_id', 'last_added' => 'MAX(wi.added_at)'])
            ->where('w.customer_id IS NOT NULL')
            ->group('w.customer_id')
            ->having($this->_buildSqlCondition($adapter, "DATEDIFF('" . Mage::app()->getLocale()->utcDate(null, 'now', false, Mage_Core_Model_Locale::DATETIME_FORMAT) . "', last_added)", $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildWishlistSharedCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['w' => $this->_getWishlistTable()], ['customer_id'])
            ->where('w.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, 'w.shared', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _getWishlistTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('wishlist/wishlist');
    }

    protected function _getWishlistItemTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('wishlist/item');
    }

    protected function _getProductTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('catalog/product');
    }

    protected function _getProductVarcharTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar');
    }

    protected function _getCatalogCategoryProductTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('catalog/category_product');
    }

    protected function _getNameAttributeId(): int
    {
        return (int) Mage::getResourceModel('eav/entity_attribute')
            ->getIdByCode('catalog_product', 'name');
    }

    #[\Override]
    public function getAttributeName(): string
    {
        $attributeName = parent::getAttributeName();
        return Mage::helper('customersegmentation')->__('Wishlist') . ':' . ' ' . $attributeName;
    }

    #[\Override]
    public function asString($format = ''): string
    {
        $attribute = $this->getAttribute();
        $attributeOptions = $this->loadAttributeOptions()->getAttributeOption();
        $attributeLabel = (is_array($attributeOptions) && isset($attributeOptions[$attribute]) && is_string($attributeOptions[$attribute]))
            ? $attributeOptions[$attribute]
            : (string) $attribute;

        $attributeLabel = Mage::helper('customersegmentation')->__('Wishlist') . ':' . ' ' . $attributeLabel;

        $operatorName = $this->getOperatorName();
        $valueName = $this->getValueName();
        return $attributeLabel . ' ' . $operatorName . ' ' . $valueName;
    }
}
