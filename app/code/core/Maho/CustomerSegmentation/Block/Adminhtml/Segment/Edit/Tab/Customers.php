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

class Maho_CustomerSegmentation_Block_Adminhtml_Segment_Edit_Tab_Customers extends Mage_Adminhtml_Block_Widget_Grid implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('segment_customers_grid');
        $this->setDefaultSort('entity_id');
        $this->setDefaultDir('ASC');
        $this->setUseAjax(true);
        $this->setSaveParametersInSession(false);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        $segment = Mage::registry('current_customer_segment');

        $collection = Mage::getResourceModel('customer/customer_collection')
            ->addNameToSelect()
            ->addAttributeToSelect('email')
            ->addAttributeToSelect('created_at')
            ->addAttributeToSelect('group_id');

        if ($segment && $segment->getId()) {
            $segment->getResource()->applySegmentToCollection($segment, $collection);
        } else {
            // New segment, show empty grid
            $collection->addFieldToFilter('entity_id', ['in' => [0]]);
        }

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('entity_id', [
            'header'    => Mage::helper('customersegmentation')->__('ID'),
            'width'     => '50px',
            'index'     => 'entity_id',
            'type'      => 'number',
        ]);

        $this->addColumn('name', [
            'header'    => Mage::helper('customersegmentation')->__('Name'),
            'index'     => 'name',
        ]);

        $this->addColumn('email', [
            'header'    => Mage::helper('customersegmentation')->__('Email'),
            'width'     => '150px',
            'index'     => 'email',
        ]);

        $groups = Mage::getResourceModel('customer/group_collection')
            ->addFieldToFilter('customer_group_id', ['gt' => 0])
            ->load()
            ->toOptionHash();

        $this->addColumn('group', [
            'header'    => Mage::helper('customersegmentation')->__('Group'),
            'width'     => '100px',
            'index'     => 'group_id',
            'type'      => 'options',
            'options'   => $groups,
        ]);

        $this->addColumn('created_at', [
            'header'    => Mage::helper('customersegmentation')->__('Customer Since'),
            'type'      => 'datetime',
            'align'     => 'center',
            'index'     => 'created_at',
            'gmtoffset' => true,
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getGridUrl(): string
    {
        return $this->getUrl('*/*/customersGrid', ['_current' => true]);
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('adminhtml/customer/edit', [
            'id' => $row->getId(),
            'store' => $this->getRequest()->getParam('store'),
        ]);
    }

    #[\Override]
    public function getTabLabel(): string
    {
        return Mage::helper('customersegmentation')->__('Customers');
    }

    #[\Override]
    public function getTabTitle(): string
    {
        return Mage::helper('customersegmentation')->__('Customers in Segment');
    }

    #[\Override]
    public function canShowTab(): bool
    {
        $segment = Mage::registry('current_customer_segment');
        return $segment && $segment->getId();
    }

    #[\Override]
    public function isHidden(): bool
    {
        return false;
    }

    public function getTabClass(): string
    {
        return 'ajax';
    }

    public function getSkipGenerateContent(): bool
    {
        return true;
    }

    public function getTabUrl(): string
    {
        return $this->getUrl('*/*/customersTab', ['_current' => true]);
    }
}
