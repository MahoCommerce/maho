<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_MediaCleaner
 */

declare(strict_types=1);

class Maho_MediaCleaner_Block_Adminhtml_Mediacleaner_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('mediaCleanerGrid');
        $this->setDefaultSort('image_id');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('mediacleaner/image_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('image_id', [
            'header' => $this->__('ID'),
            'type'   => 'number',
            'width'  => '100px',
            'align'  => 'right',
            'index'  => 'image_id',
        ]);

        $this->addColumn('type', [
            'header'  => $this->__('Type'),
            'type'    => 'options',
            'width'   => '100px',
            'align'   => 'center',
            'index'   => 'type',
            'options' => [
                'category'      => $this->__('Category'),
                'product'       => $this->__('Product'),
                'product_cache' => $this->__('Product Cache'),
                'wysiwyg'       => $this->__('WYSIWYG'),
            ],
        ]);

        $this->addColumn('filename', [
            'header'   => $this->__('File Name'),
            'type'     => 'text',
            'index'    => 'path',
            'sortable' => false,
        ]);

        $this->addColumn('image', [
            'header'    => $this->__('Image'),
            'type'      => 'text',
            'width'     => '250px',
            'align'     => 'center',
            'index'     => 'path',
            'filter'    => false,
            'sortable'  => false,
            'is_system' => true,
            'renderer'  => 'Maho_MediaCleaner_Block_Adminhtml_Mediacleaner_Grid_Renderer_Image',
        ]);

        $this->addColumn('actions', [
            'header'    => $this->__('Actions'),
            'width'     => '180px',
            'align'     => 'center',
            'filter'    => false,
            'sortable'  => false,
            'is_system' => true,
            'renderer'  => 'Maho_MediaCleaner_Block_Adminhtml_Mediacleaner_Grid_Renderer_Actions',
        ]);

        $this->addExportType('*/*/exportCsv', $this->__('CSV'));
        $this->addExportType('*/*/exportExcel', $this->__('Excel XML'));

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return '';
    }

    #[\Override]
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('image_id');
        $this->getMassactionBlock()->setFormFieldName('ids');
        $this->getMassactionBlock()->addItem('delete', [
            'label'   => $this->__('Delete'),
            'url'     => $this->getUrl('*/*/massDelete'),
            'confirm' => $this->__('Are you sure?'),
        ]);
        return $this;
    }
}
