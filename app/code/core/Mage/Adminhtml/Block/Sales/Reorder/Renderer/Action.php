<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Reorder_Renderer_Action extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{
    /**
     * Array to store all options data
     *
     * @var array
     */
    protected $_actions = [];

    #[\Override]
    public function render(\Maho\DataObject $row)
    {
        $this->_actions = [];
        if (Mage::helper('sales/reorder')->canReorder($row)) {
            $reorderAction = [
                '@' => ['href' => $this->getUrl('*/sales_order_create/reorder', ['order_id' => $row->getId()])],
                '#' =>  Mage::helper('sales')->__('Reorder'),
            ];
            $this->addToActions($reorderAction);
        }
        Mage::dispatchEvent('adminhtml_customer_orders_add_action_renderer', ['renderer' => $this, 'row' => $row]);
        return $this->_actionsToHtml();
    }

    protected function _getEscapedValue($value)
    {
        return addcslashes(htmlspecialchars($value), '\\\'');
    }

    /**
     * Render options array as a HTML string
     *
     * @return string
     */
    protected function _actionsToHtml(array $actions = [])
    {
        $html = [];
        $attributesObject = new \Maho\DataObject();

        if (empty($actions)) {
            $actions = $this->_actions;
        }

        foreach ($actions as $action) {
            $attributesObject->setData($action['@']);
            $html[] = '<a ' . $attributesObject->serialize() . '>' . $action['#'] . '</a>';
        }
        return  implode('<span class="separator">|</span>', $html);
    }

    /**
     * Add one action array to all options data storage
     *
     * @param array $actionArray
     */
    public function addToActions($actionArray)
    {
        $this->_actions[] = $actionArray;
    }
}
