<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_AdminActivityLog
 */

class Maho_AdminActivityLog_Block_Adminhtml_Activity_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('adminActivityLogGrid');
        $this->setDefaultSort('created_at');
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceModel('adminactivitylog/activity_collection');

        // Show only one entry per action group: join a derived aggregate that picks the
        // latest activity of each group as the representative row. Grouping happens only
        // inside the subquery, so the outer main_table.* select stays valid under strict
        // GROUP BY (ONLY_FULL_GROUP_BY / PostgreSQL). The CASE expression keeps each row
        // without an action_group_id in its own group, avoiding a string/integer COALESCE.
        $subSelect = $collection->getConnection()->select()
            ->from($collection->getMainTable(), [
                'representative_id' => new Maho\Db\Expr('MAX(activity_id)'),
                'activity_count' => new Maho\Db\Expr('COUNT(*)'),
            ])
            ->group('action_group_id')
            ->group(new Maho\Db\Expr('CASE WHEN action_group_id IS NULL THEN activity_id END'));

        $collection->getSelect()->join(
            ['grp' => $subSelect],
            'main_table.activity_id = grp.representative_id',
            ['activity_count'],
        );

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

        $this->addColumn('request_url', [
            'header'    => Mage::helper('adminactivitylog')->__('URL'),
            'align'     => 'left',
            'index'     => 'request_url',
        ]);

        $this->addColumn('activity_count', [
            'header'    => Mage::helper('adminactivitylog')->__('Activities'),
            'align'     => 'center',
            'width'     => '80px',
            'index'     => 'activity_count',
            'type'      => 'number',
            'filter'    => false,
        ]);

        $this->addColumn('ip_address', [
            'header'    => Mage::helper('adminactivitylog')->__('IP Address'),
            'align'     => 'left',
            'width'     => '120px',
            'index'     => 'ip_address',
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
