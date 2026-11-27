<?php

/**
 * The price and stock alerts of the customer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_PrivacyCenter_Model_Export_ProductAlerts extends Maho_PrivacyCenter_Model_Export_AbstractExporter
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('privacycenter')->__('Product Alerts');
    }

    #[\Override]
    public function export(Subject $subject): array
    {
        if ($subject->isGuest()) {
            return [];
        }
        $data = [];
        foreach (['price' => 'productalert/price_collection', 'stock' => 'productalert/stock_collection'] as $type => $collection) {
            $alerts = Mage::getResourceModel($collection)->addFieldToFilter('customer_id', $subject->customerId);
            foreach ($alerts as $alert) {
                $data[] = $this->clean(['type' => $type] + array_intersect_key(
                    $alert->getData(),
                    array_flip(['product_id', 'price', 'add_date', 'send_date', 'send_count', 'status']),
                ));
            }
        }
        return $data;
    }
}
