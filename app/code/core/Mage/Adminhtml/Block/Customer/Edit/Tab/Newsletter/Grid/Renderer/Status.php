<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Customer_Edit_Tab_Newsletter_Grid_Renderer_Status extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    protected static $_statuses;

    public function __construct()
    {
        self::$_statuses = [
            Mage_Newsletter_Model_Queue::STATUS_SENT    => Mage::helper('customer')->__('Sent'),
            Mage_Newsletter_Model_Queue::STATUS_CANCEL  => Mage::helper('customer')->__('Cancel'),
            Mage_Newsletter_Model_Queue::STATUS_NEVER   => Mage::helper('customer')->__('Not Sent'),
            Mage_Newsletter_Model_Queue::STATUS_SENDING => Mage::helper('customer')->__('Sending'),
            Mage_Newsletter_Model_Queue::STATUS_PAUSE   => Mage::helper('customer')->__('Paused'),
        ];
        parent::__construct();
    }

    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        return Mage::helper('customer')->__(self::getStatus($row->getQueueStatus()));
    }

    public static function getStatus($status)
    {
        return self::$_statuses[$status] ?? Mage::helper('customer')->__('Unknown');
    }
}
