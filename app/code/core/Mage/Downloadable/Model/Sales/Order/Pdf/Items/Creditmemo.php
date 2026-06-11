<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Downloadable
 */

class Mage_Downloadable_Model_Sales_Order_Pdf_Items_Creditmemo extends Mage_Downloadable_Model_Sales_Order_Pdf_Items_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/order/pdf/creditmemo/items/downloadable.phtml');
    }

    public function getPurchasedLinks(): array
    {
        $links = $this->getLinks();
        if (!$links || !$links->getPurchasedItems()) {
            return [];
        }

        $purchasedLinks = [];
        foreach ($links->getPurchasedItems() as $link) {
            $purchasedLinks[] = [
                'title' => $link->getLinkTitle(),
                'url' => $link->getLinkUrl(),
                'type' => $link->getLinkType(),
            ];
        }

        return $purchasedLinks;
    }

    #[\Override]
    public function getLinksTitle(): string
    {
        return parent::getLinksTitle();
    }

    public function hasPurchasedLinks(): bool
    {
        $links = $this->getPurchasedLinks();
        return !empty($links);
    }
}
