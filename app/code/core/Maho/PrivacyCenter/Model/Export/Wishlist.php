<?php

/**
 * The products in the wishlist of the customer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Subject as Subject;

class Maho_PrivacyCenter_Model_Export_Wishlist extends Maho_PrivacyCenter_Model_Export_AbstractExporter
{
    #[\Override]
    public function getLabel(): string
    {
        return Mage::helper('privacycenter')->__('Wishlist');
    }

    #[\Override]
    public function export(Subject $subject): array
    {
        if ($subject->isGuest()) {
            return [];
        }
        $wishlist = Mage::getModel('wishlist/wishlist')->loadByCustomer($subject->customerId);
        if (!$wishlist->getId()) {
            return [];
        }
        $data = [];
        foreach ($wishlist->getItemCollection() as $item) {
            /** @var Mage_Wishlist_Model_Item $item */
            $data[] = $this->clean([
                'sku' => $item->getProduct()?->getSku(),
                'product' => $item->getProduct()?->getName(),
                'qty' => (float) $item->getQty(),
                'comment' => $item->getDescription(),
                'added_at' => $item->getAddedAt(),
            ]);
        }
        return $data;
    }
}
