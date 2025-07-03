<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Order_Pdf_Items_Shipment_Default extends Mage_Sales_Model_Order_Pdf_Items_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/order/pdf/shipment/items/default.phtml');
    }

    /**
     * Draw item line (deprecated - now renders HTML)
     *
     * @deprecated Use toHtml() instead
     */
    #[\Override]
    public function draw()
    {
        // This method is deprecated, use toHtml() instead
        return;
    }

    /**
     * Get SKU
     *
     * @return string
     */
    public function getSku()
    {
        $item = $this->getItem();
        return $item ? $item->getSku() : '';
    }
}
