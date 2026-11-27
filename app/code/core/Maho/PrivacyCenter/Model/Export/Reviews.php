<?php

/**
 * The product reviews that the customer wrote.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_PrivacyCenter_Model_Export_Reviews extends Maho_PrivacyCenter_Model_Export_AbstractExporter
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('privacycenter')->__('Product Reviews');
    }

    #[\Override]
    public function export(Subject $subject): array
    {
        if ($subject->isGuest()) {
            return [];
        }
        $reviews = Mage::getResourceModel('review/review_product_collection')
            ->addCustomerFilter($subject->customerId)
            ->setDateOrder();
        $data = [];
        foreach ($reviews as $review) {
            $data[] = $this->clean(array_intersect_key(
                $review->getData(),
                array_flip(['name', 'sku', 'nickname', 'title', 'detail', 'review_created_at']),
            ));
        }
        return $data;
    }
}
