<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Product_Type_Configurable_Price extends Mage_Catalog_Model_Product_Type_Price
{
    /**
     * Get product final price
     *
     * @param float|null $qty
     * @param Mage_Catalog_Model_Product $product
     * @return  double
     */
    #[\Override]
    public function getFinalPrice($qty, $product)
    {
        if (is_null($qty) && !is_null($product->getCalculatedFinalPrice())) {
            return $product->getCalculatedFinalPrice();
        }

        $basePrice = $this->getBasePrice($product, $qty);
        $finalPrice = $basePrice;
        $product->setFinalPrice($finalPrice);
        Mage::dispatchEvent('catalog_product_get_final_price', ['product' => $product, 'qty' => $qty]);
        $finalPrice = $product->getData('final_price');

        $finalPrice += $this->getTotalConfigurableItemsPrice($product, $finalPrice);
        $finalPrice += $this->_applyOptionsPrice($product, $qty, $basePrice) - $basePrice;
        $finalPrice = max(0, $finalPrice);

        $product->setFinalPrice($finalPrice);
        return $finalPrice;
    }

    /**
     * Get Total price for configurable items
     *
     * @param Mage_Catalog_Model_Product $product
     * @param float $finalPrice
     * @return float
     */
    public function getTotalConfigurableItemsPrice($product, $finalPrice)
    {
        $price = 0.0;

        /** @var Mage_Catalog_Model_Product_Type_Configurable $productType */
        $productType = $product->getTypeInstance(true);
        $productType->setStoreFilter($product->getStore(), $product);
        $attributes = $productType->getConfigurableAttributes($product);

        $selectedAttributes = [];
        if ($product->getCustomOption('attributes')) {
            $selectedAttributes = unserialize($product->getCustomOption('attributes')->getValue(), ['allowed_classes' => false]);
        }

        /** @var Mage_Catalog_Model_Product_Type_Configurable_Attribute $attribute */
        foreach ($attributes as $attribute) {
            $productAttribute = $attribute->getProductAttribute();
            if (!$productAttribute) {
                continue;
            }
            $attributeId = $productAttribute->getId();
            $value = $this->_getValueByIndex(
                $attribute->getPrices() ?: [],
                $selectedAttributes[$attributeId] ?? null,
            );
            $product->setParentId(true);
            if ($value) {
                if ($value['pricing_value'] != 0) {
                    $product->setConfigurablePrice($this->_calcSelectionPrice($value, $finalPrice));
                    Mage::dispatchEvent(
                        'catalog_product_type_configurable_price',
                        ['product' => $product],
                    );
                    $price += $product->getConfigurablePrice();
                }
            }
        }
        return $price;
    }

    /**
     * Calculate configurable product selection price
     *
     * @param   array $priceInfo
     * @param   float $productPrice
     * @return  float
     */
    protected function _calcSelectionPrice($priceInfo, $productPrice)
    {
        if ($priceInfo['is_percent']) {
            $ratio = $priceInfo['pricing_value'] / 100;
            $price = $productPrice * $ratio;
        } else {
            $price = $priceInfo['pricing_value'];
        }
        return $price;
    }

    /**
     * @param array $values
     * @param string $index
     * @return array|false
     */
    protected function _getValueByIndex($values, $index)
    {
        foreach ($values as $value) {
            if ($value['value_index'] == $index) {
                return $value;
            }
        }
        return false;
    }

    /**
     * Apply tier price for configurable product based on total quantity in cart
     */
    #[\Override]
    protected function _applyTierPrice($product, $qty, $finalPrice)
    {
        if (is_null($qty)) {
            return $finalPrice;
        }

        // Calculate total quantity of this configurable product across cart
        $totalQty = $this->getConfigurableProductTotalQty($product, $qty);

        // Use the total quantity to determine tier pricing
        $tierPrice = $product->getTierPrice($totalQty);
        if (is_numeric($tierPrice)) {
            $finalPrice = min($finalPrice, $tierPrice);
        }

        return $finalPrice;
    }

    /**
     * Get total quantity of configurable product in cart
     */
    protected function getConfigurableProductTotalQty(Mage_Catalog_Model_Product $product, float $qty): float
    {
        // If we're not in cart context, return the given quantity
        if (!$product->hasCustomOptions()) {
            return $qty;
        }

        $totalQty = 0;
        $configurableProductId = $product->getId();
        $currentItemId = $product->getCustomOption('item_id') ? $product->getCustomOption('item_id')->getValue() : null;

        foreach ($this->getAllVisibleItems() as $item) {
            // Check if this is the same configurable product
            if ($item->getProduct()->getId() == $configurableProductId) {
                // If this is the current item being calculated, use the provided qty
                if ($currentItemId && $item->getId() == $currentItemId) {
                    $totalQty += $qty;
                } else {
                    $totalQty += $item->getQty();
                }
            }
        }

        // If no items found (shouldn't happen), return the provided qty
        return $totalQty ?: $qty;
    }

    /**
     * Get all visible items from cart
     */
    protected function getAllVisibleItems(): array
    {
        // Admin order create
        if (Mage::app()->getStore()->isAdmin()) {
            $adminQuote = Mage::getSingleton('adminhtml/session_quote');
            if ($adminQuote && $adminQuote->getQuote()) {
                return $adminQuote->getQuote()->getAllVisibleItems();
            }
        }

        // Frontend checkout/cart
        if (Mage::getSingleton('checkout/session')->hasQuote()) {
            return Mage::getSingleton('checkout/session')->getQuote()->getAllVisibleItems();
        }

        return [];
    }
}
