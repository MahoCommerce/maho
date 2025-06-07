<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Block_Adminhtml_Regionname_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('regionNameGrid');
        $this->setDefaultSort('region_id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        // Create a database collection that properly handles the region name table
        $collection = new Varien_Data_Collection_Db(Mage::getSingleton('core/resource')->getConnection('core_read'));
        $resource = Mage::getSingleton('core/resource');

        $collection->getSelect()
            ->from(['rn' => $resource->getTableName('directory_country_region_name')], [
                'locale', 'region_id', 'name',
            ])
            ->joinLeft(
                ['r' => $resource->getTableName('directory_country_region')],
                'rn.region_id = r.region_id',
                ['country_id', 'code', 'default_name'],
            )
            ->columns([
                'composite_id' => new Zend_Db_Expr("CONCAT(rn.locale, '|', rn.region_id)"),
                'id' => new Zend_Db_Expr("CONCAT(rn.locale, '_', rn.region_id)"),
            ])
            ->order(['rn.region_id ASC', 'rn.locale ASC']);

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _setCollectionOrder($column): self
    {
        $collection = $this->getCollection();
        if ($collection instanceof Varien_Data_Collection_Db) {
            $columnIndex = $column->getIndex();
            $dir = strtoupper($column->getDir());

            // Map column indexes to proper database fields
            $fieldMap = [
                'region_id' => 'rn.region_id',
                'country_id' => 'r.country_id',
                'default_name' => 'r.default_name',
                'locale' => 'rn.locale',
                'name' => 'rn.name',
            ];

            if (isset($fieldMap[$columnIndex])) {
                $collection->getSelect()->reset(Zend_Db_Select::ORDER);
                $collection->getSelect()->order($fieldMap[$columnIndex] . ' ' . $dir);
            }
        }

        return $this;
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

        // Get country options for the filter
        $countries = Mage::getResourceModel('directory/country_collection')
            ->loadData()
            ->toOptionArray(false);

        $countryOptions = [];
        foreach ($countries as $country) {
            $countryOptions[$country['value']] = $country['label'];
        }

        $this->addColumn('country_id', [
            'header' => Mage::helper('adminhtml')->__('Country'),
            'align' => 'left',
            'width' => '200px',
            'index' => 'country_id',
            'type' => 'options',
            'options' => $countryOptions,
            'renderer' => 'directory/adminhtml_regionname_grid_renderer_country',
        ]);

        $this->addColumn('default_name', [
            'header' => Mage::helper('adminhtml')->__('Default Name'),
            'align' => 'left',
            'width' => '200px',
            'index' => 'default_name',
            'type' => 'text',
        ]);

        $this->addColumn('locale', [
            'header' => Mage::helper('adminhtml')->__('Locale'),
            'align' => 'left',
            'width' => '80px',
            'index' => 'locale',
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
            'width' => '100px',
            'type' => 'text',
            'filter' => false,
            'sortable' => false,
            'index' => 'action',
            'renderer' => 'directory/adminhtml_regionname_grid_renderer_action',
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    protected function _prepareMassaction(): self
    {
        $this->setMassactionIdField('composite_id');
        $this->getMassactionBlock()->setFormFieldName('region_name');

        $this->getMassactionBlock()->addItem('delete', [
            'label' => Mage::helper('adminhtml')->__('Delete'),
            'url' => $this->getUrl('*/*/massDelete'),
            'confirm' => Mage::helper('adminhtml')->__('Are you sure you want to delete the selected region names?'),
        ]);

        return $this;
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('*/*/edit', [
            'locale' => $row->getLocale(),
            'region_id' => $row->getRegionId(),
        ]);
    }
}
