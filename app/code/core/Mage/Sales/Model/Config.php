<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Config
{
    public const XML_PATH_ORDER_STATES = 'global/sales/order/states';

    /**
     * @param string $type
     * @return Mage_Core_Model_Abstract|false
     */
    public function getQuoteRuleConditionInstance($type)
    {
        return Mage::getConfig()->getNodeClassInstance("global/sales/quote/rule/conditions/$type");
    }

    /**
     * @param string $type
     * @return Mage_Core_Model_Abstract|false
     */
    public function getQuoteRuleActionInstance($type)
    {
        return Mage::getConfig()->getNodeClassInstance("global/sales/quote/rule/actions/$type");
    }

    /**
     * Retrieve order statuses for state
     *
     * @param string $state
     * @return array
     */
    public function getOrderStatusesForState($state)
    {
        $states = Mage::getConfig()->getNode(self::XML_PATH_ORDER_STATES);
        if (!isset($states->$state) || !isset($states->$state->statuses)) {
            return [];
        }

        $statuses = [];

        foreach ($states->$state->statuses->children() as $status => $node) {
            $statuses[] = $status;
        }
        return $statuses;
    }
}
