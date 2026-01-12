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
 * Gift Card Price Block for catalog listings
 */
class Maho_Giftcard_Block_Catalog_Product_Price extends Mage_Catalog_Block_Product_Price
{
    /**
     * Get the minimum price for the gift card
     */
    public function getMinPrice(): float
    {
        $product = $this->getProduct();
        $priceModel = $product->getPriceModel();

        if (method_exists($priceModel, 'getMinimumPrice')) {
            return $priceModel->getMinimumPrice($product);
        }

        // Fallback to type instance method
        $typeInstance = $product->getTypeInstance(true);
        if (method_exists($typeInstance, 'getMinimumPrice')) {
            return $typeInstance->getMinimumPrice($product);
        }

        return 0.0;
    }

    /**
     * Get the maximum price for the gift card
     */
    public function getMaxPrice(): float
    {
        $product = $this->getProduct();
        return $this->_getMaxPriceFromProduct($product);
    }

    /**
     * Calculate maximum price from gift card configuration
     */
    protected function _getMaxPriceFromProduct(Mage_Catalog_Model_Product $product): float
    {
        $maxPrice = 0.0;

        // Check fixed amounts
        $amounts = $product->getData('giftcard_amounts');
        if ($amounts) {
            $amountsArray = array_map('trim', explode(',', $amounts));
            $amountsArray = array_filter($amountsArray, fn($a) => is_numeric($a) && $a > 0);
            if ($amountsArray !== []) {
                $maxPrice = (float) max($amountsArray);
            }
        }

        // Check custom amount max
        $giftcardType = $product->getData('giftcard_type');
        if ($giftcardType === 'custom' || $giftcardType === 'combined') {
            $customMax = (float) $product->getData('giftcard_max_amount');
            if ($customMax > 0) {
                $maxPrice = max($maxPrice, $customMax);
            }
        }

        return $maxPrice;
    }

    /**
     * Check if gift card has price range (min != max)
     */
    public function hasPriceRange(): bool
    {
        $minPrice = $this->getMinPrice();
        $maxPrice = $this->getMaxPrice();

        return $minPrice > 0 && $maxPrice > 0 && $minPrice !== $maxPrice;
    }
}
