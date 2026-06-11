<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Notification_Grid_Renderer_Actions extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Renders grid column
     *
     * @return  string
     */
    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $readDetailsHtml = ($row->getUrl())
            ? '<a target="_blank" href="' . $row->getUrl() . '">' .
                Mage::helper('adminnotification')->__('Read Details') . '</a> | '
            : '';

        $markAsReadHtml = ($row->getIsRead())
            ? ''
            : '<a href="' . $this->getUrl('*/*/markAsRead/', ['_current' => true, 'id' => $row->getId()]) . '">' .
                Mage::helper('adminnotification')->__('Mark as Read') . '</a> | ';

        /** @var Mage_Core_Helper_Url $helper */
        $helper = $this->helper('core/url');
        return sprintf(
            '%s%s<a href="%s" onClick="deleteConfirm(\'%s\', this.href); return false;">%s</a>',
            $readDetailsHtml,
            $markAsReadHtml,
            $this->getUrl('*/*/remove/', [
                '_current' => true,
                'id' => $row->getId(),
                Mage_Core_Controller_Front_Action::PARAM_NAME_URL_ENCODED => $helper->getEncodedUrl()]),
            Mage::helper('adminnotification')->__('Are you sure?'),
            Mage::helper('adminnotification')->__('Remove'),
        );
    }
}
