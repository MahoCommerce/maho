<?php

/**
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ProductAlert
 */

declare(strict_types=1);

class Mage_ProductAlert_Model_Resource_Stock_Customer_Collection extends Mage_Customer_Model_Resource_Customer_Collection
{
    /**
     * join productalert stock data to customer collection
     *
     * @param int $productId
     * @param int $websiteId
     * @return $this
     */
    public function join($productId, $websiteId)
    {
        $this->getSelect()->join(
            ['alert' => $this->getTable('productalert/stock')],
            'alert.customer_id=e.entity_id',
            ['alert_stock_id', 'add_date', 'send_date', 'send_count', 'status'],
        );

        $this->getSelect()->where('alert.product_id=?', $productId);
        if ($websiteId) {
            $this->getSelect()->where('alert.website_id=?', $websiteId);
        }
        $this->_setIdFieldName('alert_stock_id');
        $this->addAttributeToSelect('*');

        return $this;
    }
}
