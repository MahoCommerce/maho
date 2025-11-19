<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Directory
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Directory_Block_Adminhtml_Region_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('regionGrid');
        $this->setDefaultSort('region_id');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        $collection = Mage::getResourceModel('directory/region_collection');
        $collection->getSelect()->reset(Maho\Db\Select::ORDER);
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('region_id', [
            'header' => Mage::helper('directory')->__('Region ID'),
            'align' => 'right',
            'width' => '50px',
            'index' => 'region_id',
            'type' => 'number',
        ]);

        $this->addColumn('country_id', [
            'header' => Mage::helper('directory')->__('Country'),
            'align' => 'left',
            'width' => '200px',
            'index' => 'country_id',
            'type' => 'country',
        ]);

        $this->addColumn('code', [
            'header' => Mage::helper('directory')->__('Region Code'),
            'align' => 'left',
            'width' => '200px',
            'index' => 'code',
            'type' => 'text',
        ]);

        $this->addColumn('default_name', [
            'header' => Mage::helper('directory')->__('Default Name'),
            'align' => 'left',
            'index' => 'default_name',
            'type' => 'text',
        ]);

        $this->addColumn('action', [
            'header' => Mage::helper('adminhtml')->__('Action'),
            'width' => '70px',
            'type' => 'action',
            'index' => 'region_id',
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
                    'confirm' => Mage::helper('directory')->__('Are you sure you want to delete this region?'),
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
        $this->setMassactionIdField('region_id');
        $this->getMassactionBlock()->setFormFieldName('regions');

        $this->getMassactionBlock()->addItem('delete', [
            'label' => Mage::helper('adminhtml')->__('Delete'),
            'url' => $this->getUrl('*/*/massDelete'),
            'confirm' => Mage::helper('directory')->__('Are you sure you want to delete the selected regions?'),
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
        return $this->getUrl('*/*/edit', ['id' => $row->getRegionId()]);
    }
}
