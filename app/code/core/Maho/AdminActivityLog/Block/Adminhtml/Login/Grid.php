<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_AdminActivityLog_Block_Adminhtml_Login_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('adminLoginActivityGrid');
        $this->setDefaultSort('created_at');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('adminactivitylog/login_collection');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('login_id', [
            'header'    => Mage::helper('adminactivitylog')->__('ID'),
            'align'     => 'right',
            'width'     => '50px',
            'index'     => 'login_id',
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

        $this->addColumn('type', [
            'header'    => Mage::helper('adminactivitylog')->__('Type'),
            'align'     => 'left',
            'width'     => '100px',
            'index'     => 'type',
            'type'      => 'options',
            'options'   => [
                'login' => $this->__('Login'),
                'logout' => $this->__('Logout'),
                'failed' => $this->__('Failed'),
            ],
        ]);

        $this->addColumn('status', [
            'header'    => Mage::helper('adminactivitylog')->__('Status'),
            'align'     => 'left',
            'width'     => '80px',
            'index'     => 'status',
            'type'      => 'options',
            'options'   => [
                '1' => $this->__('Success'),
                '0' => $this->__('Failed'),
            ],
            'frame_callback' => [$this, 'decorateStatus'],
        ]);

        $this->addColumn('ip_address', [
            'header'    => Mage::helper('adminactivitylog')->__('IP Address'),
            'align'     => 'left',
            'width'     => '120px',
            'index'     => 'ip_address',
        ]);

        $this->addColumn('failure_reason', [
            'header'    => Mage::helper('adminactivitylog')->__('Failure Reason'),
            'align'     => 'left',
            'index'     => 'failure_reason',
        ]);

        $this->addExportType('*/*/exportCsv', Mage::helper('adminactivitylog')->__('CSV'));
        $this->addExportType('*/*/exportXml', Mage::helper('adminactivitylog')->__('Excel XML'));

        return parent::_prepareColumns();
    }

    public function decorateStatus(string $value, Varien_Object $row, Varien_Object $column, bool $isExport): string
    {
        if ($value == '1') {
            $class = 'grid-severity-notice';
        } else {
            $class = 'grid-severity-critical';
        }
        return '<span class="' . $class . '"><span>' . $this->escapeHtml($column->getOptions()[$value]) . '</span></span>';
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }
}
