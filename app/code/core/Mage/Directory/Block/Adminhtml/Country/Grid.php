<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Block_Adminhtml_Country_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('countryGrid');
        $this->setDefaultSort('country_id');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
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
            'header' => Mage::helper('directory')->__('Country ID'),
            'align' => 'left',
            'width' => '80px',
            'index' => 'country_id',
            'filter_index' => 'main_table.country_id',
            'type' => 'text',
        ]);

        $this->addColumn('iso2_code', [
            'header' => Mage::helper('directory')->__('ISO2 Code'),
            'align' => 'left',
            'width' => '80px',
            'index' => 'iso2_code',
            'type' => 'text',
        ]);

        $this->addColumn('iso3_code', [
            'header' => Mage::helper('directory')->__('ISO3 Code'),
            'align' => 'left',
            'width' => '80px',
            'index' => 'iso3_code',
            'type' => 'text',
        ]);

        $this->addColumn('name', [
            'header' => Mage::helper('directory')->__('Country Name'),
            'align' => 'left',
            'index' => 'name',
            'type' => 'text',
            'renderer' => 'adminhtml/widget_grid_column_renderer_country',
        ]);

        $this->addColumn('action', [
            'header' => Mage::helper('adminhtml')->__('Action'),
            'width' => '70px',
            'type' => 'action',
            'index' => 'country_id',
            'actions' => [
                [
                    'caption' => Mage::helper('adminhtml')->__('Edit'),
                    'url' => ['base' => '*/*/edit'],
                    'field' => 'id',
                ],
                [
                    'caption' => Mage::helper('adminhtml')->__('Delete'),
                    'url' => ['base' => '*/*/delete', 'params' => [Mage_Core_Model_Url::FORM_KEY => $this->getFormKey()]],
                    'field' => 'id',
                    'confirm' => Mage::helper('directory')->__('Are you sure you want to delete this country?'),
                ],
            ],
            'filter' => false,
            'sortable' => false,
            'is_system' => true,
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    protected function _prepareMassaction(): self
    {
        $this->setMassactionIdField('country_id');
        $this->getMassactionBlock()->setFormFieldName('countries');

        $this->getMassactionBlock()->addItem('delete', [
            'label' => Mage::helper('adminhtml')->__('Delete'),
            'url' => $this->getUrl('*/*/massDelete'),
            'confirm' => Mage::helper('directory')->__('Are you sure you want to delete the selected countries?'),
        ]);

        return $this;
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getCountryId()]);
    }
}
