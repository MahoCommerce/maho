<?php

/**
 * Maho
 *
 * @package    Mage_Bundle
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Bundle_Model_Sales_Order_Pdf_Items_Shipment extends Mage_Bundle_Model_Sales_Order_Pdf_Items_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/order/pdf/shipment/items/bundle.phtml');
    }

    /**
     * Get bundle children items for display in shipment
     */
    public function getBundleChildren(): array
    {
        $children = $this->getChilds($this->getItem());
        if (!$children) {
            return [];
        }

        $bundleChildren = [];
        foreach ($children as $child) {
            // For shipments, show items that are shipped separately
            if ($this->isShipmentSeparately($child)) {
                $bundleChildren[] = $child;
            }
        }

        return $bundleChildren;
    }

    /**
     * Get bundle options for display
     */
    #[\Override]
    public function getBundleOptions($item = null): array
    {
        return parent::getBundleOptions($item ?? $this->getItem());
    }

    /**
     * Get formatted value HTML for bundle child item
     */
    #[\Override]
    public function getValueHtml(mixed $item): string
    {
        return parent::getValueHtml($item);
    }
}
