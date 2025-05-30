<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Adminhtml_Block_Directory_Region_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('regionGrid');
        $this->setDefaultSort('region_id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        $collection = Mage::getResourceModel('directory/region_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('region_id', [
            'header' => Mage::helper('adminhtml')->__('Region ID'),
            'align' => 'right',
            'width' => '50px',
            'index' => 'region_id',
            'type' => 'number',
        ]);

        $this->addColumn('country_id', [
            'header' => Mage::helper('adminhtml')->__('Country'),
            'align' => 'left',
            'width' => '100px',
            'index' => 'country_id',
            'type' => 'text',
            'renderer' => 'adminhtml/directory_region_grid_renderer_country',
        ]);

        $this->addColumn('code', [
            'header' => Mage::helper('adminhtml')->__('Region Code'),
            'align' => 'left',
            'width' => '100px',
            'index' => 'code',
            'type' => 'text',
        ]);

        $this->addColumn('default_name', [
            'header' => Mage::helper('adminhtml')->__('Default Name'),
            'align' => 'left',
            'index' => 'default_name',
            'type' => 'text',
        ]);

        $this->addColumn('name', [
            'header' => Mage::helper('adminhtml')->__('Localized Name'),
            'align' => 'left',
            'index' => 'name',
            'type' => 'text',
        ]);

        $this->addColumn('action', [
            'header' => Mage::helper('adminhtml')->__('Action'),
            'width' => '50px',
            'type' => 'action',
            'getter' => 'getRegionId',
            'actions' => [
                [
                    'caption' => Mage::helper('adminhtml')->__('Edit'),
                    'url' => ['base' => '*/*/edit'],
                    'field' => 'id',
                ],
                [
                    'caption' => Mage::helper('adminhtml')->__('Delete'),
                    'url' => ['base' => '*/*/delete'],
                    'field' => 'id',
                    'confirm' => Mage::helper('adminhtml')->__('Are you sure you want to delete this region?'),
                ],
            ],
            'filter' => false,
            'sortable' => false,
            'index' => 'stores',
            'is_system' => true,
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    protected function _prepareMassaction(): self
    {
        $this->setMassactionIdField('region_id');
        $this->getMassactionBlock()->setFormFieldName('region');

        $this->getMassactionBlock()->addItem('delete', [
            'label' => Mage::helper('adminhtml')->__('Delete'),
            'url' => $this->getUrl('*/*/massDelete'),
            'confirm' => Mage::helper('adminhtml')->__('Are you sure you want to delete the selected regions?'),
        ]);

        return $this;
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getRegionId()]);
    }
}
