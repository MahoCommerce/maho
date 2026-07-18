<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

class Mage_Sales_Model_Order_Creditmemo_Api_V2 extends Mage_Sales_Model_Order_Creditmemo_Api
{
    /**
     * Prepare data
     *
     * @param null|object $data
     * @return array
     */
    #[\Override]
    protected function _prepareCreateData($data)
    {
        // convert data object to array, if it's null turn it into empty array
        $data = (isset($data) && is_object($data)) ? get_object_vars($data) : [];
        // convert qtys object to array
        if (isset($data['qtys']) && count($data['qtys'])) {
            $qtysArray = [];
            foreach ($data['qtys'] as &$item) {
                if (isset($item->order_item_id) && isset($item->qty)) {
                    $qtysArray[$item->order_item_id] = $item->qty;
                }
            }
            $data['qtys'] = $qtysArray;
        }
        return $data;
    }
}
