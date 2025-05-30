<?php

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Block_Adminhtml_Country_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('countryGrid');
        $this->setDefaultSort('country_id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        $collection = Mage::getResourceModel('directory/country_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('country_id', [
            'header' => Mage::helper('adminhtml')->__('Country ID'),
            'align' => 'left',
            'width' => '80px',
            'index' => 'country_id',
            'type' => 'text',
        ]);

        $this->addColumn('iso2_code', [
            'header' => Mage::helper('adminhtml')->__('ISO2 Code'),
            'align' => 'left',
            'width' => '80px',
            'index' => 'iso2_code',
            'type' => 'text',
        ]);

        $this->addColumn('iso3_code', [
            'header' => Mage::helper('adminhtml')->__('ISO3 Code'),
            'align' => 'left',
            'width' => '80px',
            'index' => 'iso3_code',
            'type' => 'text',
        ]);

        $this->addColumn('name', [
            'header' => Mage::helper('adminhtml')->__('Country Name'),
            'align' => 'left',
            'index' => 'name',
            'type' => 'text',
            'renderer' => 'directory/adminhtml_country_grid_renderer_name',
        ]);

        $this->addColumn('action', [
            'header' => Mage::helper('adminhtml')->__('Action'),
            'width' => '50px',
            'type' => 'action',
            'getter' => 'getCountryId',
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
                    'confirm' => Mage::helper('adminhtml')->__('Are you sure you want to delete this country?'),
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
        $this->setMassactionIdField('country_id');
        $this->getMassactionBlock()->setFormFieldName('country');

        $this->getMassactionBlock()->addItem('delete', [
            'label' => Mage::helper('adminhtml')->__('Delete'),
            'url' => $this->getUrl('*/*/massDelete'),
            'confirm' => Mage::helper('adminhtml')->__('Are you sure you want to delete the selected countries?'),
        ]);

        return $this;
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getCountryId()]);
    }
}