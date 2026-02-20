<?php

/**
 * Maho
 *
 * @package    Mage_Downloadable
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Downloadable_Model_Sales_Order_Pdf_Items_Invoice extends Mage_Downloadable_Model_Sales_Order_Pdf_Items_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/order/pdf/invoice/items/downloadable.phtml');
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
