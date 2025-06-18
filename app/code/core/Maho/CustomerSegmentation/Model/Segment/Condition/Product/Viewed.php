<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_CustomerSegmentation_Model_Segment_Condition_Product_Viewed extends Maho_CustomerSegmentation_Model_Segment_Condition_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('customersegmentation/segment_condition_product_viewed');
        $this->setValue(null);
    }

    public function getNewChildSelectOptions(): array
    {
        return [
            'value' => $this->getType(),
            'label' => Mage::helper('customersegmentation')->__('Viewed Products'),
        ];
    }

    public function loadAttributeOptions(): self
    {
        $attributes = [
            'product_id' => Mage::helper('customersegmentation')->__('Product ID'),
            'product_name' => Mage::helper('customersegmentation')->__('Product Name'),
            'product_sku' => Mage::helper('customersegmentation')->__('Product SKU'),
            'category_id' => Mage::helper('customersegmentation')->__('Category'),
            'view_count' => Mage::helper('customersegmentation')->__('View Count'),
            'last_viewed_at' => Mage::helper('customersegmentation')->__('Last Viewed Date'),
            'days_since_last_view' => Mage::helper('customersegmentation')->__('Days Since Last View'),
        ];

        $this->setAttributeOption($attributes);
        return $this;
    }

    public function getAttributeElement(): Varien_Data_Form_Element_Abstract
    {
        if (!$this->hasAttributeOption()) {
            $this->loadAttributeOptions();
        }
        
        $element = parent::getAttributeElement();
        return $element;
    }

    public function getInputType(): string
    {
        switch ($this->getAttribute()) {
            case 'product_id':
            case 'category_id':
            case 'view_count':
            case 'days_since_last_view':
                return 'numeric';
            case 'last_viewed_at':
                return 'date';
            default:
                return 'string';
        }
    }

    public function getValueElementType(): string
    {
        switch ($this->getAttribute()) {
            case 'last_viewed_at':
                return 'date';
            case 'category_id':
                return 'select'; // Could be enhanced with category chooser
            default:
                return 'text';
        }
    }

    public function getValueSelectOptions(): array
    {
        $options = [];
        switch ($this->getAttribute()) {
            case 'category_id':
                // Get root categories for stores
                $categories = Mage::getResourceModel('catalog/category_collection')
                    ->addAttributeToSelect('name')
                    ->addAttributeToFilter('level', ['gt' => 1])
                    ->addAttributeToFilter('is_active', 1)
                    ->setOrder('name', 'ASC');
                
                $options = [['value' => '', 'label' => Mage::helper('customersegmentation')->__('Please select...')]];
                foreach ($categories as $category) {
                    $options[] = [
                        'value' => $category->getId(),
                        'label' => $category->getName()
                    ];
                }
                break;
        }
        return $options;
    }

    public function getConditionsSql(Varien_Db_Adapter_Interface $adapter, ?int $websiteId = null): string|false
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = $this->getValue();

        switch ($attribute) {
            case 'product_id':
                return $this->_buildProductViewCondition($adapter, $operator, $value);
            case 'product_name':
                return $this->_buildProductNameViewCondition($adapter, $operator, $value);
            case 'product_sku':
                return $this->_buildProductSkuViewCondition($adapter, $operator, $value);
            case 'category_id':
                return $this->_buildCategoryViewCondition($adapter, $operator, $value);
            case 'view_count':
                return $this->_buildViewCountCondition($adapter, $operator, $value);
            case 'last_viewed_at':
                return $this->_buildLastViewedCondition($adapter, $operator, $value);
            case 'days_since_last_view':
                return $this->_buildDaysSinceViewCondition($adapter, $operator, $value);
        }

        return false;
    }

    protected function _buildProductViewCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['rv' => $this->_getReportViewedTable()], ['customer_id'])
            ->where('rv.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, 'rv.product_id', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildProductNameViewCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['rv' => $this->_getReportViewedTable()], ['customer_id'])
            ->join(['p' => $this->_getProductTable()], 'rv.product_id = p.entity_id', [])
            ->join(['pv' => $this->_getProductVarcharTable()], 'p.entity_id = pv.entity_id AND pv.attribute_id = ' . $this->_getNameAttributeId(), [])
            ->where('rv.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, 'pv.value', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildProductSkuViewCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['rv' => $this->_getReportViewedTable()], ['customer_id'])
            ->join(['p' => $this->_getProductTable()], 'rv.product_id = p.entity_id', [])
            ->where('rv.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, 'p.sku', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildCategoryViewCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['rv' => $this->_getReportViewedTable()], ['customer_id'])
            ->join(['ccp' => $this->_getCatalogCategoryProductTable()], 'rv.product_id = ccp.product_id', [])
            ->where('rv.customer_id IS NOT NULL')
            ->where($this->_buildSqlCondition($adapter, 'ccp.category_id', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildViewCountCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['rv' => $this->_getReportViewedTable()], ['customer_id', 'view_count' => 'COUNT(*)'])
            ->where('rv.customer_id IS NOT NULL')
            ->group('rv.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'view_count', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildLastViewedCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['rv' => $this->_getReportViewedTable()], ['customer_id', 'last_viewed' => 'MAX(rv.added_at)'])
            ->where('rv.customer_id IS NOT NULL')
            ->group('rv.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'last_viewed', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _buildDaysSinceViewCondition(Varien_Db_Adapter_Interface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['rv' => $this->_getReportViewedTable()], ['customer_id', 'last_viewed' => 'MAX(rv.added_at)'])
            ->where('rv.customer_id IS NOT NULL')
            ->group('rv.customer_id')
            ->having($this->_buildSqlCondition($adapter, 'DATEDIFF(NOW(), last_viewed)', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function _getReportViewedTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('reports/viewed_product_index');
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

    public function asString($format = ''): string
    {
        $attribute = $this->getAttribute();
        $attributeOptions = $this->loadAttributeOptions()->getAttributeOption();
        $attributeLabel = isset($attributeOptions[$attribute]) ? $attributeOptions[$attribute] : $attribute;

        return $attributeLabel . ' ' . $this->getOperatorName() . ' ' . $this->getValueName();
    }
}