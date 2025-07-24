<?php

/**
 * Maho
 *
 * @package    Mage_Bundle
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Bundle_Model_Sales_Order_Pdf_Items_Creditmemo extends Mage_Bundle_Model_Sales_Order_Pdf_Items_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/order/pdf/creditmemo/items/bundle.phtml');
    }

    public function getBundleChildren(): array
    {
        $children = $this->getChilds($this->getItem());
        if (!$children) {
            return [];
        }

        $bundleChildren = [];
        foreach ($children as $child) {
            if (!$this->isShipmentSeparately($child) || !$this->isChildCalculated($child)) {
                $bundleChildren[] = $child;
            }
        }

        return $bundleChildren;
    }

    #[\Override]
    public function getBundleOptions(): array
    {
        return parent::getBundleOptions($this->getItem());
    }

    #[\Override]
    public function canShowPriceInfo(mixed $item): bool
    {
        return parent::canShowPriceInfo($item);
    }

    #[\Override]
    public function getValueHtml(mixed $item): string
    {
        return parent::getValueHtml($item);
    }
}
