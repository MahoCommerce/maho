<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_AdminActivityLog
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
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
            'width'     => '180px',
            'index'     => 'created_at',
            'type'      => 'datetime',
        ]);

        $this->addColumn('username', [
            'header'    => Mage::helper('adminactivitylog')->__('Username'),
            'align'     => 'left',
            'index'     => 'username',
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

        $this->addColumn('type', [
            'header'    => Mage::helper('adminactivitylog')->__('Type'),
            'align'     => 'left',
            'width'     => '80px',
            'index'     => 'type',
            'type'      => 'options',
            'options'   => [
                'login' => $this->__('Login'),
                'logout' => $this->__('Logout'),
                'failed' => $this->__('Failed'),
            ],
            'frame_callback' => [$this, 'decorateType'],
        ]);

        $this->addExportType('*/*/exportCsv', Mage::helper('adminactivitylog')->__('CSV'));
        $this->addExportType('*/*/exportXml', Mage::helper('adminactivitylog')->__('Excel XML'));

        return parent::_prepareColumns();
    }

    public function decorateType(string $value, \Maho\DataObject $row, \Maho\DataObject $column, bool $isExport): string
    {
        // Get the actual type value from the row data
        $type = $row->getData('type');

        if ($type === 'failed') {
            $class = 'grid-severity-critical';
        } else {
            $class = 'grid-severity-notice';
        }

        // Use the already translated value that was passed in
        return '<span class="' . $class . '"><span>' . $this->escapeHtml($value) . '</span></span>';
    }

    #[\Override]
    public function getGridUrl()
    {
        return $this->getUrl('*/*/grid', ['_current' => true]);
    }
}
