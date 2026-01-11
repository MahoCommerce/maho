<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CustomerSegmentation
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
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

    #[\Override]
    public function getNewChildSelectOptions(): array
    {
        return [
            'value' => $this->getType(),
            'label' => Mage::helper('customersegmentation')->__('Viewed Products'),
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
            'view_count' => Mage::helper('customersegmentation')->__('View Count'),
            'last_viewed_at' => Mage::helper('customersegmentation')->__('Last Viewed Date'),
            'days_since_last_view' => Mage::helper('customersegmentation')->__('Days Since Last View'),
        ];

        asort($attributes);
        $this->setAttributeOption($attributes);
        return $this;
    }

    #[\Override]
    public function getAttributeElement(): \Maho\Data\Form\Element\AbstractElement
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
            'product_id', 'category_id', 'view_count', 'days_since_last_view' => 'numeric',
            'last_viewed_at' => 'date',
            default => 'string',
        };
    }

    #[\Override]
    public function getValueElementType(): string
    {
        return match ($this->getAttribute()) {
            'last_viewed_at' => 'date',
            'category_id' => 'select',
            default => 'text',
        };
    }

    #[\Override]
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
                        'label' => $category->getName(),
                    ];
                }
                break;
        }
        return $options;
    }

    #[\Override]
    public function getConditionsSql(\Maho\Db\Adapter\AdapterInterface $adapter, ?int $websiteId = null): string|false
    {
        $attribute = $this->getAttribute();
        $operator = $this->getMappedSqlOperator();
        $value = $this->getValue();
        return match ($attribute) {
            'product_id' => $this->buildProductViewCondition($adapter, $operator, $value),
            'product_name' => $this->buildProductNameViewCondition($adapter, $operator, $value),
            'product_sku' => $this->buildProductSkuViewCondition($adapter, $operator, $value),
            'category_id' => $this->buildCategoryViewCondition($adapter, $operator, $value),
            'view_count' => $this->buildViewCountCondition($adapter, $operator, $value),
            'last_viewed_at' => $this->buildLastViewedCondition($adapter, $operator, $value),
            'days_since_last_view' => $this->buildDaysSinceViewCondition($adapter, $operator, $value),
            default => false,
        };
    }

    protected function buildProductViewCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['rv' => $this->getReportViewedTable()], ['customer_id'])
            ->where('rv.customer_id IS NOT NULL')
            ->where($this->buildSqlCondition($adapter, 'rv.product_id', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildProductNameViewCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['rv' => $this->getReportViewedTable()], ['customer_id'])
            ->join(['p' => $this->getProductTable()], 'rv.product_id = p.entity_id', [])
            ->join(['pv' => $this->getProductVarcharTable()], 'p.entity_id = pv.entity_id AND pv.attribute_id = ' . $this->getNameAttributeId(), [])
            ->where('rv.customer_id IS NOT NULL')
            ->where($this->buildSqlCondition($adapter, 'pv.value', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildProductSkuViewCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['rv' => $this->getReportViewedTable()], ['customer_id'])
            ->join(['p' => $this->getProductTable()], 'rv.product_id = p.entity_id', [])
            ->where('rv.customer_id IS NOT NULL')
            ->where($this->buildSqlCondition($adapter, 'p.sku', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildCategoryViewCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['rv' => $this->getReportViewedTable()], ['customer_id'])
            ->join(['ccp' => $this->getCatalogCategoryProductTable()], 'rv.product_id = ccp.product_id', [])
            ->where('rv.customer_id IS NOT NULL')
            ->where($this->buildSqlCondition($adapter, 'ccp.category_id', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildViewCountCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['rv' => $this->getReportViewedTable()], ['customer_id'])
            ->where('rv.customer_id IS NOT NULL')
            ->group('rv.customer_id')
            ->having($this->buildSqlCondition($adapter, 'COUNT(*)', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildLastViewedCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $operator, mixed $value): string
    {
        $subselect = $adapter->select()
            ->from(['rv' => $this->getReportViewedTable()], ['customer_id'])
            ->where('rv.customer_id IS NOT NULL')
            ->group('rv.customer_id')
            ->having($this->buildSqlCondition($adapter, 'MAX(rv.added_at)', $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function buildDaysSinceViewCondition(\Maho\Db\Adapter\AdapterInterface $adapter, string $operator, mixed $value): string
    {
        $currentDate = Mage::app()->getLocale()->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT);
        $dateDiff = $adapter->getDateDiffSql("'{$currentDate}'", 'MAX(rv.added_at)');
        $subselect = $adapter->select()
            ->from(['rv' => $this->getReportViewedTable()], ['customer_id'])
            ->where('rv.customer_id IS NOT NULL')
            ->group('rv.customer_id')
            ->having($this->buildSqlCondition($adapter, (string) $dateDiff, $operator, $value));

        return 'e.entity_id IN (' . $subselect . ')';
    }

    protected function getReportViewedTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('reports/viewed_product_index');
    }

    protected function getProductTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('catalog/product');
    }

    protected function getProductVarcharTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('catalog_product_entity_varchar');
    }

    protected function getCatalogCategoryProductTable(): string
    {
        return Mage::getSingleton('core/resource')->getTableName('catalog/category_product');
    }

    protected function getNameAttributeId(): int
    {
        return (int) Mage::getResourceModel('eav/entity_attribute')
            ->getIdByCode('catalog_product', 'name');
    }

    #[\Override]
    public function getAttributeName(): string
    {
        $attributeName = parent::getAttributeName();
        return Mage::helper('customersegmentation')->__('Viewed') . ':' . ' ' . $attributeName;
    }

    #[\Override]
    public function asString($format = ''): string
    {
        $attribute = $this->getAttribute();
        $attributeOptions = $this->loadAttributeOptions()->getAttributeOption();
        $attributeLabel = (is_array($attributeOptions) && isset($attributeOptions[$attribute]) && is_string($attributeOptions[$attribute]))
            ? $attributeOptions[$attribute]
            : (string) $attribute;

        $attributeLabel = Mage::helper('customersegmentation')->__('Viewed') . ':' . ' ' . $attributeLabel;

        $operatorName = $this->getOperatorName();
        $valueName = $this->getValueName();
        return $attributeLabel . ' ' . $operatorName . ' ' . $valueName;
    }
}
