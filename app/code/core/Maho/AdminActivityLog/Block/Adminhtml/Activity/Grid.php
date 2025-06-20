<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_AdminActivityLog_Block_Adminhtml_Activity_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('adminActivityLogGrid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('adminactivitylog/activity_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('activity_id', [
            'header'    => Mage::helper('adminactivitylog')->__('ID'),
            'align'     => 'right',
            'width'     => '50px',
            'index'     => 'activity_id',
            'type'      => 'number',
        ]);

        $this->addColumn('created_at', [
            'header'    => Mage::helper('adminactivitylog')->__('Date/Time'),
            'align'     => 'left',
            'width'     => '160px',
            'index'     => 'created_at',
            'type'      => 'datetime',
        ]);

        $this->addColumn('username', [
            'header'    => Mage::helper('adminactivitylog')->__('Username'),
            'align'     => 'left',
            'index'     => 'username',
        ]);

        $this->addColumn('fullname', [
            'header'    => Mage::helper('adminactivitylog')->__('Full Name'),
            'align'     => 'left',
            'index'     => 'fullname',
        ]);

        $this->addColumn('action_type', [
            'header'    => Mage::helper('adminactivitylog')->__('Action'),
            'align'     => 'left',
            'width'     => '100px',
            'index'     => 'action_type',
            'type'      => 'options',
            'options'   => [
                'create' => $this->__('Create'),
                'update' => $this->__('Update'),
                'delete' => $this->__('Delete'),
                'mass_update' => $this->__('Mass Update'),
                'page_visit' => $this->__('Page Visit'),
            ],
        ]);

        $this->addColumn('entity_type', [
            'header'    => Mage::helper('adminactivitylog')->__('Entity Type'),
            'align'     => 'left',
            'index'     => 'entity_type',
        ]);

        $this->addColumn('entity_name', [
            'header'    => Mage::helper('adminactivitylog')->__('Entity'),
            'align'     => 'left',
            'index'     => 'entity_name',
        ]);

        $this->addColumn('ip_address', [
            'header'    => Mage::helper('adminactivitylog')->__('IP Address'),
            'align'     => 'left',
            'width'     => '120px',
            'index'     => 'ip_address',
        ]);

        $this->addColumn('request_url', [
            'header'    => Mage::helper('adminactivitylog')->__('URL'),
            'align'     => 'left',
            'index'     => 'request_url',
            'renderer'  => 'adminactivitylog/adminhtml_activity_grid_renderer_url',
        ]);

        $this->addColumn('action', [
            'header'    => Mage::helper('adminactivitylog')->__('Action'),
            'width'     => '100',
            'type'      => 'action',
            'getter'    => 'getId',
            'actions'   => [
                [
                    'caption'   => Mage::helper('adminactivitylog')->__('View'),
                    'url'       => ['base' => '*/*/view'],
                    'field'     => 'id',
                ],
            ],
            'filter'    => false,
            'sortable'  => false,
            'index'     => 'activity_id',
            'is_system' => true,
        ]);

        $this->addExportType('*/*/exportCsv', Mage::helper('adminactivitylog')->__('CSV'));
        $this->addExportType('*/*/exportXml', Mage::helper('adminactivitylog')->__('Excel XML'));

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/view', ['id' => $row->getId()]);
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }
}
