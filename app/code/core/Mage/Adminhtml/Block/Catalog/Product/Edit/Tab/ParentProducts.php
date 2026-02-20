<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Mage_Adminhtml_Block_Catalog_Product_Edit_Tab_ParentProducts extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('parent_products_grid');
        $this->setDefaultSort('entity_id');
        $this->setDefaultDir('ASC');
        $this->setUseAjax(true);
    }

    protected function _getProduct(): Mage_Catalog_Model_Product
    {
        return Mage::registry('current_product');
    }

    /**
     * Get all parent product IDs (configurable, grouped, bundle)
     */
    protected function _getParentProductIds(): array
    {
        $productId = $this->_getProduct()->getId();
        if (!$productId) {
            return [];
        }

        $parentIds = [];

        // Configurable parents
        $configurableParents = Mage::getResourceSingleton('catalog/product_type_configurable')
            ->getParentIdsByChild($productId);
        $parentIds = array_merge($parentIds, $configurableParents);

        // Grouped parents
        $groupedParents = Mage::getResourceSingleton('catalog/product_link')
            ->getParentIdsByChild($productId, Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED);
        $parentIds = array_merge($parentIds, $groupedParents);

        // Bundle parents
        if ($this->isModuleEnabled('Mage_Bundle')) {
            $bundleParents = Mage::getResourceSingleton('bundle/selection')
                ->getParentIdsByChild($productId);
            $parentIds = array_merge($parentIds, $bundleParents);
        }

        return array_unique($parentIds);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        $parentIds = $this->_getParentProductIds();

        $collection = Mage::getResourceModel('catalog/product_collection')
            ->addAttributeToSelect(['name', 'sku', 'type_id', 'status']);

        if (empty($parentIds)) {
            // No parents - filter to empty result
            $collection->addFieldToFilter('entity_id', ['in' => [0]]);
        } else {
            $collection->addFieldToFilter('entity_id', ['in' => $parentIds]);
        }

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('entity_id', [
            'header'    => Mage::helper('catalog')->__('ID'),
            'index'     => 'entity_id',
            'width'     => '60px',
        ]);

        $this->addColumn('sku', [
            'header'    => Mage::helper('catalog')->__('SKU'),
            'index'     => 'sku',
            'width'     => '120px',
        ]);

        $this->addColumn('name', [
            'header'    => Mage::helper('catalog')->__('Name'),
            'index'     => 'name',
        ]);

        $this->addColumn('type_id', [
            'header'    => Mage::helper('catalog')->__('Type'),
            'index'     => 'type_id',
            'width'     => '120px',
            'type'      => 'options',
            'options'   => Mage::getSingleton('catalog/product_type')->getOptionArray(),
        ]);

        $this->addColumn('status', [
            'header'    => Mage::helper('catalog')->__('Status'),
            'index'     => 'status',
            'width'     => '90px',
            'type'      => 'options',
            'options'   => Mage::getSingleton('catalog/product_status')->getOptionArray(),
        ]);

        $this->addColumn('action', [
            'header'    => Mage::helper('catalog')->__('Action'),
            'width'     => '70px',
            'type'      => 'action',
            'getter'    => 'getId',
            'actions'   => [[
                'caption'   => Mage::helper('catalog')->__('Edit'),
                'url'       => ['base' => '*/*/edit'],
                'field'     => 'id',
            ]],
            'filter'    => false,
            'sortable'  => false,
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getGridUrl(): string
    {
        return $this->getUrl('*/*/parentProductsGrid', ['_current' => true]);
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }
}
