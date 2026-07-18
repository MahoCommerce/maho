<?php

/**
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

class Mage_Customer_Model_Group_Api extends Mage_Api_Model_Resource_Abstract
{
    /**
     * Retrieve groups
     *
     * @return array
     */
    public function items()
    {
        $collection = Mage::getModel('customer/group')->getCollection();

        $result = [];
        foreach ($collection as $group) {
            /** @var Mage_Customer_Model_Group $group */
            $result[] = $group->toArray(['customer_group_id', 'customer_group_code']);
        }

        return $result;
    }
}
