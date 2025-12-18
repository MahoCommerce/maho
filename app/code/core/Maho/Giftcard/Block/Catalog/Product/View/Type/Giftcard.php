<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Giftcard_Block_Catalog_Product_View_Type_Giftcard extends Mage_Catalog_Block_Product_View_Abstract
{
    /**
     * Load gift card attributes if not already loaded
     */
    protected function _loadGiftcardAttributes(): void
    {
        $product = $this->getProduct();
        if (!$product->hasData('giftcard_type') && $product->getId()) {
            $attributes = [
                'giftcard_type',
                'giftcard_amounts',
                'giftcard_min_amount',
                'giftcard_max_amount',
                'giftcard_allow_message',
                'giftcard_lifetime',
            ];

            foreach ($attributes as $code) {
                $value = $product->getResource()->getAttributeRawValue(
                    $product->getId(),
                    $code,
                    $product->getStoreId(),
                );
                $product->setData($code, $value);
            }
        }
    }

    /**
     * Get gift card type (fixed or custom)
     */
    public function getGiftcardType(): string
    {
        $this->_loadGiftcardAttributes();
        $product = $this->getProduct();

        // Get the attribute text value, not the option ID
        $typeText = $product->getAttributeText('giftcard_type');
        if (!$typeText) {
            // Try to load it directly if not available
            $typeId = $product->getData('giftcard_type');
            if ($typeId) {
                $attribute = Mage::getSingleton('eav/config')
                    ->getAttribute('catalog_product', 'giftcard_type');
                if ($attribute->usesSource()) {
                    $typeText = $attribute->getSource()->getOptionText($typeId);
                }
            }
        }

        // Map the option text to our expected values
        if ($typeText) {
            $typeTextLower = strtolower($typeText);
            if (strpos($typeTextLower, 'custom') !== false) {
                return 'custom';
            } elseif (strpos($typeTextLower, 'fixed') !== false) {
                return 'fixed';
            }
        }

        return 'fixed'; // Default to fixed
    }

    /**
     * Get available gift card amounts as array
     */
    public function getGiftcardAmounts(): array
    {
        $this->_loadGiftcardAttributes();
        $amounts = $this->getProduct()->getData('giftcard_amounts');
        if (!$amounts) {
            return [];
        }

        $amountsArray = array_map('trim', explode(',', $amounts));
        $amountsArray = array_filter($amountsArray, function ($amount) {
            return is_numeric($amount) && $amount > 0;
        });

        sort($amountsArray, SORT_NUMERIC);
        return $amountsArray;
    }

    /**
     * Get minimum amount for custom type
     */
    public function getMinAmount(): ?float
    {
        $this->_loadGiftcardAttributes();
        $min = $this->getProduct()->getData('giftcard_min_amount');
        return $min ? (float) $min : null;
    }

    /**
     * Get maximum amount for custom type
     */
    public function getMaxAmount(): ?float
    {
        $this->_loadGiftcardAttributes();
        $max = $this->getProduct()->getData('giftcard_max_amount');
        return $max ? (float) $max : null;
    }

    /**
     * Is message allowed
     */
    public function isMessageAllowed(): bool
    {
        $this->_loadGiftcardAttributes();
        return $this->getProduct()->getData('giftcard_allow_message') !== '0';
    }

    /**
     * Get gift card lifetime in days
     */
    public function getLifetime(): int
    {
        $this->_loadGiftcardAttributes();
        return (int) ($this->getProduct()->getData('giftcard_lifetime') ?: 365);
    }

    /**
     * Format price
     */
    public function formatPrice(float $price): string
    {
        return Mage::helper('core')->currency($price, true, false);
    }
}
