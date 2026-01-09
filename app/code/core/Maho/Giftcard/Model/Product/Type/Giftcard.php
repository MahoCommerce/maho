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
 * Gift Card Product Type Model
 */
class Maho_Giftcard_Model_Product_Type_Giftcard extends Mage_Catalog_Model_Product_Type_Virtual
{
    public const TYPE_CODE = 'giftcard';

    /**
     * Check if product is virtual
     */
    #[\Override]
    public function isVirtual($product = null)
    {
        return true;
    }

    /**
     * Check if product can be configured
     */
    #[\Override]
    public function canConfigure($product = null)
    {
        return true;
    }

    /**
     * Check is product available for sale
     * Gift cards are always available (no stock management)
     */
    #[\Override]
    public function isSalable($product = null)
    {
        return true;
    }

    /**
     * Delete data specific to this product type
     */
    public function deleteTypeSpecificData(Mage_Catalog_Model_Product $product): void
    {
        // No type-specific data to delete
    }

    /**
     * Prepare additional options/information for order item
     *
     * Transfer gift card fields from request to buyRequest so they get
     * serialized into info_buyRequest by the parent _prepareProduct method.
     */
    #[\Override]
    protected function _prepareOptions(Maho\DataObject $buyRequest, $product, $processMode)
    {
        // Transfer gift card fields from request to buyRequest
        $request = Mage::app()->getRequest();
        if ($request->getParam('giftcard_amount') && !$buyRequest->getGiftcardAmount()) {
            $buyRequest->setGiftcardAmount($request->getParam('giftcard_amount'));
        }
        if ($request->getParam('giftcard_recipient_name') && !$buyRequest->getGiftcardRecipientName()) {
            $buyRequest->setGiftcardRecipientName($request->getParam('giftcard_recipient_name'));
        }
        if ($request->getParam('giftcard_recipient_email') && !$buyRequest->getGiftcardRecipientEmail()) {
            $buyRequest->setGiftcardRecipientEmail($request->getParam('giftcard_recipient_email'));
        }
        if ($request->getParam('giftcard_sender_name') && !$buyRequest->getGiftcardSenderName()) {
            $buyRequest->setGiftcardSenderName($request->getParam('giftcard_sender_name'));
        }
        if ($request->getParam('giftcard_sender_email') && !$buyRequest->getGiftcardSenderEmail()) {
            $buyRequest->setGiftcardSenderEmail($request->getParam('giftcard_sender_email'));
        }
        if ($request->getParam('giftcard_message') && !$buyRequest->getGiftcardMessage()) {
            $buyRequest->setGiftcardMessage($request->getParam('giftcard_message'));
        }
        if ($request->getParam('giftcard_delivery_date') && !$buyRequest->getGiftcardDeliveryDate()) {
            $buyRequest->setGiftcardDeliveryDate($request->getParam('giftcard_delivery_date'));
        }

        // Return parent options (product custom options only)
        return parent::_prepareOptions($buyRequest, $product, $processMode);
    }

    /**
     * Prepare product for cart
     * Set custom price based on gift card amount
     */
    #[\Override]
    public function prepareForCartAdvanced(Maho\DataObject $buyRequest, $product = null, $processMode = null)
    {
        $result = parent::prepareForCartAdvanced($buyRequest, $product, $processMode);

        if (is_string($result)) {
            return $result; // Error message
        }

        // Validate and set the price to the gift card amount
        if ($buyRequest->getGiftcardAmount()) {
            $amount = (float) $buyRequest->getGiftcardAmount();

            // Get the product from result to access its attributes
            $productInstance = $this->getProduct($product);

            // Manually load gift card attributes if not already loaded
            if (!$productInstance->hasData('giftcard_type') && $productInstance->getId()) {
                $attributes = ['giftcard_type', 'giftcard_amounts', 'giftcard_min_amount', 'giftcard_max_amount'];
                foreach ($attributes as $code) {
                    $value = $productInstance->getResource()->getAttributeRawValue(
                        $productInstance->getId(),
                        $code,
                        $productInstance->getStoreId(),
                    );
                    $productInstance->setData($code, $value);
                }
            }

            // Validate custom amount against min/max if type allows custom
            $giftcardType = $productInstance->getGiftcardType();
            if ($giftcardType === 'custom' || $giftcardType === 'combined') {
                $minAmount = $productInstance->getGiftcardMinAmount();
                $maxAmount = $productInstance->getGiftcardMaxAmount();

                // For combined type, check if amount matches a fixed value first
                $isFixedAmount = false;
                if ($giftcardType === 'combined') {
                    $allowedAmounts = $productInstance->getData('giftcard_amounts');
                    if ($allowedAmounts) {
                        $amounts = array_map('trim', explode(',', $allowedAmounts));
                        $amounts = array_map('floatval', $amounts);
                        foreach ($amounts as $allowedAmount) {
                            if (abs($amount - $allowedAmount) < 0.01) {
                                $isFixedAmount = true;
                                break;
                            }
                        }
                    }
                }

                // Only validate min/max for custom amounts
                if (!$isFixedAmount) {
                    if ($minAmount && $amount < (float) $minAmount) {
                        return Mage::helper('giftcard')->__('Gift card amount cannot be less than %s', Mage::app()->getStore()->formatPrice($minAmount));
                    }

                    if ($maxAmount && $amount > (float) $maxAmount) {
                        return Mage::helper('giftcard')->__('Gift card amount cannot be more than %s', Mage::app()->getStore()->formatPrice($maxAmount));
                    }
                }
            }

            // Validate fixed amount is in allowed list
            if ($giftcardType === 'fixed') {
                $allowedAmounts = $productInstance->getData('giftcard_amounts');
                if ($allowedAmounts) {
                    $amounts = array_map('trim', explode(',', $allowedAmounts));
                    $amounts = array_map('floatval', $amounts);

                    // Use float comparison with small epsilon for precision issues
                    $isValid = false;
                    foreach ($amounts as $allowedAmount) {
                        if (abs($amount - $allowedAmount) < 0.01) {
                            $isValid = true;
                            break;
                        }
                    }

                    if (!$isValid) {
                        return Mage::helper('giftcard')->__('Please select a valid gift card amount');
                    }
                }
            }

            // Set custom price and add additional options for each item
            foreach ($result as $item) {
                $item->setCustomPrice($amount);
                $item->setOriginalCustomPrice($amount);
                if ($item->getProduct()) {
                    $item->getProduct()->setIsSuperMode(true);
                }

                // Add additional options for display in cart/order
                $this->_addAdditionalOptions($item, $buyRequest);
            }
        }

        return $result;
    }

    /**
     * Add additional options for display in cart/order
     */
    protected function _addAdditionalOptions(Mage_Catalog_Model_Product $product, Maho\DataObject $buyRequest): void
    {
        $additionalOptions = Mage::helper('giftcard')->buildAdditionalOptions($buyRequest);

        if ($additionalOptions !== []) {
            $product->addCustomOption('additional_options', serialize($additionalOptions));
        }
    }

    /**
     * Get final price of product
     */
    public function getPrice(Mage_Catalog_Model_Product $product): float
    {
        // If custom price is set (from cart item), use that
        if ($product->getCustomPrice()) {
            return $product->getCustomPrice();
        }

        // Return minimum possible price for display
        return $this->getMinimumPrice($product);
    }

    /**
     * Get minimum possible price for the gift card
     */
    public function getMinimumPrice(?Mage_Catalog_Model_Product $product = null): float
    {
        $product = $this->getProduct($product);

        // Load gift card attributes if needed
        if (!$product->hasData('giftcard_type') && $product->getId()) {
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

        // For fixed amounts, return the lowest amount
        $amounts = $product->getData('giftcard_amounts');
        if ($amounts) {
            $amountsArray = array_map('trim', explode(',', $amounts));
            $amountsArray = array_filter($amountsArray, fn($a) => is_numeric($a) && $a > 0);
            if ($amountsArray !== []) {
                return (float) min($amountsArray);
            }
        }

        // For custom amounts, return the minimum amount
        $minAmount = $product->getData('giftcard_min_amount');
        if ($minAmount && $minAmount > 0) {
            return (float) $minAmount;
        }

        return 0.0;
    }

    /**
     * Check if product has required options
     */
    #[\Override]
    public function hasRequiredOptions($product = null)
    {
        // Gift cards always have required options (amount, recipient info)
        return true;
    }

    /**
     * Check if product has options
     * Gift cards always have options (amount selection, recipient info)
     */
    #[\Override]
    public function hasOptions($product = null)
    {
        return true;
    }

    /**
     * Get order options for the product
     *
     * Includes additional_options (gift card display options) in the order item
     */
    #[\Override]
    public function getOrderOptions($product = null)
    {
        $options = parent::getOrderOptions($product);

        // Add additional_options from custom option (for cart/order display)
        if ($additionalOption = $this->getProduct($product)->getCustomOption('additional_options')) {
            $options['additional_options'] = unserialize($additionalOption->getValue(), ['allowed_classes' => false]);
        }

        return $options;
    }

    /**
     * Process buy request to return preconfigured values for form pre-filling
     *
     * This method is called when editing a cart item to restore form field values.
     *
     * @param Mage_Catalog_Model_Product $product
     * @param Maho\DataObject $buyRequest
     * @return array
     */
    #[\Override]
    public function processBuyRequest($product, $buyRequest)
    {
        return [
            'giftcard_amount' => $buyRequest->getGiftcardAmount(),
            'giftcard_recipient_name' => $buyRequest->getGiftcardRecipientName(),
            'giftcard_recipient_email' => $buyRequest->getGiftcardRecipientEmail(),
            'giftcard_sender_name' => $buyRequest->getGiftcardSenderName(),
            'giftcard_sender_email' => $buyRequest->getGiftcardSenderEmail(),
            'giftcard_message' => $buyRequest->getGiftcardMessage(),
            'giftcard_delivery_date' => $buyRequest->getGiftcardDeliveryDate(),
        ];
    }
}
