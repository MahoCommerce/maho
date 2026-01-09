<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Gift Card Product Price Model
 */
class Maho_Giftcard_Model_Product_Price extends Mage_Catalog_Model_Product_Type_Price
{
    /**
     * Get product price
     * Returns minimum possible gift card amount for display
     */
    #[\Override]
    public function getPrice($product)
    {
        // If custom price is set (from cart), use that
        if ($product->getCustomPrice()) {
            return $product->getCustomPrice();
        }

        // Get the stored price
        $price = $product->getData('price');
        if ($price > 0) {
            return $price;
        }

        // Calculate minimum price from gift card options
        return $this->getMinimumPrice($product);
    }

    /**
     * Get minimum possible price for the gift card
     */
    public function getMinimumPrice(Mage_Catalog_Model_Product $product): float
    {
        // Load gift card attributes if needed
        if (!$product->hasData('giftcard_amounts') && $product->getId()) {
            $attributes = ['giftcard_type', 'giftcard_amounts', 'giftcard_min_amount'];
            foreach ($attributes as $code) {
                $value = $product->getResource()->getAttributeRawValue(
                    $product->getId(),
                    $code,
                    $product->getStoreId(),
                );
                $product->setData($code, $value);
            }
        }

        $minPrice = 0.0;

        // Check fixed amounts
        $amounts = $product->getData('giftcard_amounts');
        if ($amounts) {
            $amountsArray = array_map('trim', explode(',', $amounts));
            $amountsArray = array_filter($amountsArray, fn($a) => is_numeric($a) && $a > 0);
            if ($amountsArray !== []) {
                $minPrice = (float) min($amountsArray);
            }
        }

        // For combined/custom type, also check min_amount and take the lower value
        $giftcardType = $product->getData('giftcard_type');
        if ($giftcardType === 'custom' || $giftcardType === 'combined') {
            $customMin = (float) $product->getData('giftcard_min_amount');
            if ($customMin > 0) {
                $minPrice = ($minPrice > 0) ? min($minPrice, $customMin) : $customMin;
            }
        }

        return $minPrice;
    }

    /**
     * Get final price
     */
    #[\Override]
    public function getFinalPrice($qty, $product)
    {
        if (is_null($qty) && !is_null($product->getCalculatedFinalPrice())) {
            return $product->getCalculatedFinalPrice();
        }

        $finalPrice = $this->getPrice($product);
        $product->setFinalPrice($finalPrice);

        Mage::dispatchEvent('catalog_product_get_final_price', ['product' => $product, 'qty' => $qty]);

        $finalPrice = $product->getData('final_price');
        return max(0, $finalPrice);
    }
}
