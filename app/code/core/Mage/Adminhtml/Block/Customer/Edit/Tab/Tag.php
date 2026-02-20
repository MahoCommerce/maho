<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Customer's tags grid
 *
 * @package    Mage_Adminhtml
 *
 * @method Mage_Tag_Model_Resource_Customer_Collection getCollection()
 */
class Mage_Adminhtml_Block_Customer_Edit_Tab_Tag extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('tag_grid');
        $this->setDefaultSort('name');
        $this->setDefaultDir('ASC');
        $this->setUseAjax(true);
        $this->setFilterVisibility(false);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $tagId = Mage::registry('tagId');

        if ($this->getCustomerId() instanceof Mage_Customer_Model_Customer) {
            $this->setCustomerId($this->getCustomerId()->getId());
        }

        $collection = Mage::getResourceModel('tag/customer_collection')
            ->addCustomerFilter($this->getCustomerId())
            ->addGroupByTag();

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _afterLoadCollection()
    {
        $this->getCollection()->addProductName();
        return parent::_afterLoadCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('name', [
            'header'    => Mage::helper('customer')->__('Tag Name'),
            'index'     => 'name',
        ]);

        $this->addColumn('status', [
            'header'    => Mage::helper('customer')->__('Status'),
            'width'     => '90px',
            'index'     => 'status',
            'type'      => 'options',
            'options'    => [
                Mage_Tag_Model_Tag::STATUS_DISABLED => Mage::helper('customer')->__('Disabled'),
                Mage_Tag_Model_Tag::STATUS_PENDING  => Mage::helper('customer')->__('Pending'),
                Mage_Tag_Model_Tag::STATUS_APPROVED => Mage::helper('customer')->__('Approved'),
            ],
            'filter'    => false,
        ]);

        $this->addColumn('product', [
            'header'    => Mage::helper('customer')->__('Product Name'),
            'index'     => 'product',
            'filter'    => false,
            'sortable'  => false,
        ]);

        $this->addColumn('product_sku', [
            'header'    => Mage::helper('customer')->__('SKU'),
            'index'     => 'product_sku',
            'filter'    => false,
            'sortable'  => false,
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/tag/edit', [
            'tag_id' => $row->getTagId(),
            'customer_id' => $this->getCustomerId(),
        ]);
    }

    /**
     * @return string
     */
    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/customer/tagGrid', [
            '_current' => true,
            'id'       => $this->getCustomerId(),
        ]);
    }
}
