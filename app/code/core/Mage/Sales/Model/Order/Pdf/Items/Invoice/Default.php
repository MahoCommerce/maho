<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Order_Pdf_Items_Invoice_Default extends Mage_Sales_Model_Order_Pdf_Items_Abstract
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('sales/order/pdf/invoice/items/default.phtml');
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
     * Get item prices for display
     *
     * @return array
     */
    public function getItemPricesForDisplay()
    {
        $order = $this->getOrder();
        $item = $this->getItem();

        if (!$order || !$item) {
            return [];
        }

        $prices = [];

        if ($order->formatPriceTxt($item->getPrice()) != $order->formatPriceTxt($item->getPriceInclTax())) {
            $prices[] = [
                'label' => $this->__('Excl. Tax:'),
                'price' => $order->formatPriceTxt($item->getPrice()),
                'subtotal' => $order->formatPriceTxt($item->getRowTotal()),
            ];
            $prices[] = [
                'label' => $this->__('Incl. Tax:'),
                'price' => $order->formatPriceTxt($item->getPriceInclTax()),
                'subtotal' => $order->formatPriceTxt($item->getRowTotalInclTax()),
            ];
        } else {
            $prices[] = [
                'price' => $order->formatPriceTxt($item->getPrice()),
                'subtotal' => $order->formatPriceTxt($item->getRowTotal()),
            ];
        }

        return $prices;
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
