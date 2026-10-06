<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

abstract class Mage_Sales_Block_Order_Pdf_Abstract extends Mage_Core_Block_Pdf
{
    /**
     * The section under global/pdf that maps a product type to its item renderer.
     */
    protected const ITEM_RENDERER_SECTION = '';

    protected ?Mage_Sales_Model_Order $_order = null;

    /** @var array<string, Mage_Sales_Model_Order_Pdf_Items_Abstract|null> */
    protected array $_renderers = [];

    /**
     * Get store from the order
     */
    #[\Override]
    public function getStore(): Mage_Core_Model_Store
    {
        return $this->_order ? $this->_order->getStore() : Mage::app()->getStore();
    }

    /**
     * Get store address from configuration
     */
    public function getStoreAddress(): string
    {
        $address = Mage::getStoreConfig('sales/identity/address', $this->getStore());
        return is_string($address) ? $address : '';
    }

    protected function _getItemRenderer(string $type): ?Mage_Sales_Model_Order_Pdf_Items_Abstract
    {
        if (!array_key_exists($type, $this->_renderers)) {
            $model = (string) Mage::getConfig()->getNode('global/pdf/' . static::ITEM_RENDERER_SECTION . '/' . $type);
            $renderer = $model === '' ? null : Mage::getModel($model);
            $this->_renderers[$type] = $renderer instanceof Mage_Sales_Model_Order_Pdf_Items_Abstract ? $renderer : null;
        }
        return $this->_renderers[$type];
    }
}
