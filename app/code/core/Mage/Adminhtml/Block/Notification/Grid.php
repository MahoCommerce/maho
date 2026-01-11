<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

use Mage_Adminhtml_Block_Widget_Grid_Massaction_Abstract as MassAction;

class Mage_Adminhtml_Block_Notification_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    #[\Override]
    protected function _construct()
    {
        $this->setSaveParametersInSession(true);
        $this->setId('notificationGrid');
        $this->setIdFieldName('notification_id');
        $this->setDefaultSort('date_added');
        $this->setDefaultDir('desc');
        $this->setFilterVisibility(false);
    }

    /**
     * @throws Mage_Core_Exception
     */
    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('adminnotification/inbox')
            ->getCollection()
            ->addRemoveFilter();
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('severity', [
            'header'    => Mage::helper('adminnotification')->__('Severity'),
            'width'     => '60px',
            'index'     => 'severity',
            'renderer'  => 'adminhtml/notification_grid_renderer_severity',
        ]);

        $this->addColumn('date_added', [
            'header'    => Mage::helper('adminnotification')->__('Date Added'),
            'index'     => 'date_added',
            'type'      => 'datetime',
        ]);

        $this->addColumn('title', [
            'header'    => Mage::helper('adminnotification')->__('Message'),
            'index'     => 'title',
            'renderer'  => 'adminhtml/notification_grid_renderer_notice',
        ]);

        $this->addColumn('actions', [
            'header'    => Mage::helper('adminnotification')->__('Actions'),
            'width'     => '250px',
            'sortable'  => false,
            'renderer'  => 'adminhtml/notification_grid_renderer_actions',
        ]);

        return parent::_prepareColumns();
    }

    /**
     * Prepare mass action
     * @return $this
     */
    #[\Override]
    protected function _prepareMassaction()
    {
        $this->setMassactionIdField('notification_id');
        $this->getMassactionBlock()->setFormFieldName('notification');

        $this->getMassactionBlock()->addItem(MassAction::MARK_AS_READ, [
            'label'    => Mage::helper('adminnotification')->__('Mark as Read'),
            'url'      => $this->getUrl('*/*/massMarkAsRead', ['_current' => true]),
        ]);

        $this->getMassactionBlock()->addItem(MassAction::REMOVE, [
            'label'    => Mage::helper('adminnotification')->__('Remove'),
            'url'      => $this->getUrl('*/*/massRemove'),
        ]);

        return $this;
    }

    /**
     * @param Mage_AdminNotification_Model_Inbox $row
     * @return string
     */
    public function getRowClass(\Maho\DataObject $row)
    {
        return $row->getIsRead() ? 'read' : 'unread';
    }

    /**
     * @return false
     */
    public function getRowClickCallback()
    {
        return false;
    }
}
