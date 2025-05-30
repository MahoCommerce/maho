<?php

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
        $collection = new Varien_Data_Collection();

        // Get the data directly from the database
        $adapter = Mage::getSingleton('core/resource')->getConnection('core_read');
        $resource = Mage::getSingleton('core/resource');

        $select = $adapter->select()
            ->from(['rn' => $resource->getTableName('directory_country_region_name')])
            ->joinLeft(
                ['r' => $resource->getTableName('directory_country_region')],
                'rn.region_id = r.region_id',
                ['country_id', 'code', 'default_name'],
            )
            ->order(['rn.region_id ASC', 'rn.locale ASC']);

        $data = $adapter->fetchAll($select);

        foreach ($data as $row) {
            $item = new Varien_Object($row);
            // Create a composite ID for mass actions
            $item->setId($row['locale'] . '|' . $row['region_id']);
            $collection->addItem($item);
        }

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
            'renderer' => 'directory/adminhtml_regionname_grid_renderer_country',
        ]);

        $this->addColumn('default_name', [
            'header' => Mage::helper('adminhtml')->__('Default Name'),
            'align' => 'left',
            'width' => '150px',
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
            'width' => '50px',
            'type' => 'action',
            'getter' => 'getId',
            'actions' => [
                [
                    'caption' => Mage::helper('adminhtml')->__('Edit'),
                    'url' => ['base' => '*/*/edit'],
                    'field' => 'id',
                    'params' => [
                        'locale' => '$locale',
                        'region_id' => '$region_id',
                    ],
                ],
                [
                    'caption' => Mage::helper('adminhtml')->__('Delete'),
                    'url' => ['base' => '*/*/delete'],
                    'field' => 'id',
                    'params' => [
                        'locale' => '$locale',
                        'region_id' => '$region_id',
                    ],
                    'confirm' => Mage::helper('adminhtml')->__('Are you sure you want to delete this region name?'),
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
        $this->setMassactionIdField('id');
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