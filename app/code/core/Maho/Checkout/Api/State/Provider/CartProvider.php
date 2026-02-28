<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Checkout\Api\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Maho\Checkout\Api\Resource\Cart;
use Maho\Checkout\Api\Resource\CartItem;
use Maho\ApiPlatform\Service\AddressMapper;
use Maho\ApiPlatform\Service\CartService;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Cart State Provider - Fetches cart data for API Platform
 *
 * @implements ProviderInterface<Cart>
 */
final class CartProvider implements ProviderInterface
{
    use AuthenticationTrait;

    private AddressMapper $addressMapper;
    private CartService $cartService;

    public function __construct(Security $security)
    {
        $this->addressMapper = new AddressMapper();
        $this->security = $security;
        $this->cartService = new CartService();
    }

    /**
     * Provide cart data based on operation type
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?Cart
    {
        $operationName = $operation->getName();

        // Handle customerCart query - get current authenticated customer's cart
        if ($operationName === 'customerCart') {
            $customerId = $context['customer_id'] ?? $this->getAuthenticatedCustomerId();
            if ($customerId) {
                // Verify the authenticated user matches the requested customer
                $this->authorizeCustomerAccess((int) $customerId);
                $quote = $this->cartService->getCustomerCart((int) $customerId);
                /** @phpstan-ignore ternary.alwaysTrue */
                return $quote ? $this->mapToDto($quote) : null;
            }
            return null;
        }

        // Handle getCartByMaskedId query
        if ($operationName === 'getCartByMaskedId') {
            $maskedId = $context['args']['maskedId'] ?? null;
            if (!$maskedId) {
                return null;
            }
            $quote = $this->cartService->getCart(null, $maskedId);
            if (!$quote) {
                return null;
            }
            $this->verifyCartAccess($quote);
            return $this->mapToDto($quote);
        }

        // Handle standard cart query with cartId
        $cartId = $context['args']['cartId'] ?? $uriVariables['id'] ?? null;
        $maskedId = $context['args']['maskedId'] ?? null;

        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId,
        );

        if (!$quote) {
            return null;
        }

        // Verify cart ownership for authenticated customers
        $this->verifyCartAccess($quote);

        return $this->mapToDto($quote);
    }

    /**
     * Verify the current user has access to the cart
     *
     * - Admins can access any cart
     * - Customers can only access their own cart
     * - Guest carts (no customer_id) are accessible via maskedId through public endpoints
     *
     * @throws AccessDeniedHttpException If access denied
     */
    private function verifyCartAccess(\Mage_Sales_Model_Quote $quote): void
    {
        // Admins can access any cart
        if ($this->isAdmin()) {
            return;
        }

        $cartCustomerId = $quote->getCustomerId();
        $authenticatedCustomerId = $this->getAuthenticatedCustomerId();

        // If cart belongs to a customer, verify ownership
        if ($cartCustomerId) {
            if ($authenticatedCustomerId === null || (int) $cartCustomerId !== $authenticatedCustomerId) {
                throw new AccessDeniedHttpException('You can only access your own cart');
            }
        }
        // Guest carts (no customer_id) are allowed - they're accessed via public /guest-carts routes
    }

    /**
     * Map Maho quote model to Cart DTO
     */
    private function mapToDto(\Mage_Sales_Model_Quote $quote): Cart
    {
        // Ensure totals are collected so tax values are available
        $quote->collectTotals();

        $dto = new Cart();
        $dto->id = (int) $quote->getId();
        $dto->maskedId = $quote->getData('masked_quote_id');
        $dto->customerId = $quote->getCustomerId() ? (int) $quote->getCustomerId() : null;
        $dto->storeId = (int) $quote->getStoreId();
        $dto->isActive = (bool) $quote->getIsActive();
        $dto->currency = $quote->getQuoteCurrencyCode() ?: 'AUD';
        $dto->itemsCount = (int) $quote->getItemsCount();
        $dto->itemsQty = (float) $quote->getItemsQty();
        $dto->createdAt = $quote->getCreatedAt();
        $dto->updatedAt = $quote->getUpdatedAt();

        // Batch load product thumbnails to avoid N+1 queries
        $items = $quote->getAllVisibleItems();
        $thumbnailsByProductId = $this->batchLoadCartItemThumbnails($items);

        // Map items
        $dto->items = [];
        foreach ($items as $item) {
            $productId = $item->getProductId() ? (int) $item->getProductId() : null;
            $thumbnailUrl = $productId ? ($thumbnailsByProductId[$productId] ?? null) : null;
            $dto->items[] = $this->mapItemToDto($item, $thumbnailUrl);
        }

        // Map prices
        $dto->prices = $this->mapPricesToArray($quote);

        // Map billing address
        $billingAddress = $quote->getBillingAddress();
        if ($billingAddress && $billingAddress->getId()) {
            $dto->billingAddress = $this->addressMapper->fromQuoteAddress($billingAddress);
        }

        // Map shipping address
        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress && $shippingAddress->getId()) {
            $dto->shippingAddress = $this->addressMapper->fromQuoteAddress($shippingAddress);

            // Get available shipping methods
            $dto->availableShippingMethods = $this->getAvailableShippingMethods($shippingAddress);

            // Get selected shipping method
            $selectedMethod = $shippingAddress->getShippingMethod();
            if ($selectedMethod) {
                $dto->selectedShippingMethod = $this->parseShippingMethod($shippingAddress);
            }
        }

        // Get available payment methods
        $dto->availablePaymentMethods = $this->getAvailablePaymentMethods($quote);

        // Get selected payment method
        $payment = $quote->getPayment();
        if ($payment && $payment->getMethod()) {
            $dto->selectedPaymentMethod = [
                'code' => $payment->getMethod(),
                'title' => $payment->getMethodInstance()->getTitle(),
            ];
        }

        // Get applied coupon
        $couponCode = $quote->getCouponCode();
        if ($couponCode) {
            $dto->appliedCoupon = [
                'code' => $couponCode,
                'discountAmount' => (float) abs($shippingAddress ? $shippingAddress->getDiscountAmount() : 0),
            ];
        }

        // Get applied gift cards
        $giftcardCodesJson = $quote->getData('giftcard_codes');
        if ($giftcardCodesJson) {
            $giftcardCodes = json_decode($giftcardCodesJson, true);
            if (is_array($giftcardCodes)) {
                foreach ($giftcardCodes as $code => $balance) {
                    $dto->appliedGiftcards[] = [
                        'code' => (string) $code,
                        'balance' => (float) $balance,
                        'appliedAmount' => 0.0, // Actual applied amounts calculated by totals collector
                    ];
                }
            }
        }

        // Populate giftcard amount in prices from quote
        $giftcardAmount = (float) $quote->getData('giftcard_amount');
        if ($giftcardAmount > 0) {
            $dto->prices['giftcardAmount'] = $giftcardAmount;
        }

        \Mage::dispatchEvent('api_cart_dto_build', ['quote' => $quote, 'dto' => $dto]);
        return $dto;
    }

    /**
     * Batch load thumbnails for all cart items to avoid N+1 product loads
     *
     * @param \Mage_Sales_Model_Quote_Item[] $items
     * @return array<int, string> Map of product ID => thumbnail URL
     */
    private function batchLoadCartItemThumbnails(array $items): array
    {
        $productIds = [];
        foreach ($items as $item) {
            if ($item->getProductId()) {
                $productIds[] = (int) $item->getProductId();
            }
        }

        if (empty($productIds)) {
            return [];
        }

        $collection = \Mage::getResourceModel('catalog/product_collection')
            ->addIdFilter($productIds)
            ->addAttributeToSelect(['small_image', 'thumbnail']);

        $thumbnails = [];
        $mediaConfig = \Mage::getModel('catalog/product_media_config');

        foreach ($collection as $product) {
            $image = $product->getSmallImage() ?: $product->getThumbnail();
            if ($image && $image !== 'no_selection') {
                $thumbnails[(int) $product->getId()] = $mediaConfig->getMediaUrl($image);
            }
        }

        return $thumbnails;
    }

    /**
     * Map Maho quote item model to CartItem DTO
     *
     * @param string|null $preloadedThumbnailUrl Pre-loaded thumbnail URL from batch loading
     */
    private function mapItemToDto(\Mage_Sales_Model_Quote_Item $item, ?string $preloadedThumbnailUrl = null): CartItem
    {
        $dto = new CartItem();
        $dto->id = (int) $item->getId();
        $dto->sku = $item->getSku();
        $dto->name = $item->getName() ?? '';
        $dto->qty = (float) $item->getQty();
        $dto->price = (float) $item->getPrice();
        $dto->priceInclTax = (float) $item->getPriceInclTax();
        $dto->rowTotal = (float) $item->getRowTotal();
        $dto->rowTotalInclTax = (float) $item->getRowTotalInclTax();
        $dto->rowTotalWithDiscount = (float) ($item->getRowTotal() - $item->getDiscountAmount());
        $dto->discountAmount = $item->getDiscountAmount() ? (float) $item->getDiscountAmount() : null;
        $dto->discountPercent = $item->getDiscountPercent() ? (float) $item->getDiscountPercent() : null;
        $dto->taxAmount = $item->getTaxAmount() ? (float) $item->getTaxAmount() : null;
        $dto->taxPercent = $item->getTaxPercent() ? (float) $item->getTaxPercent() : null;
        $dto->productId = $item->getProductId() ? (int) $item->getProductId() : null;
        $dto->productType = $item->getProductType();
        $dto->thumbnailUrl = $preloadedThumbnailUrl;

        // Get configured product options for display
        $dto->options = $this->getItemConfigurationOptions($item);

        // Check stock status
        $product = $item->getProduct();
        if ($product) {
            $stockItem = \Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);
            $dto->stockStatus = $stockItem->getIsInStock() ? 'in_stock' : 'out_of_stock';
        }






        \Mage::dispatchEvent('api_cart_item_dto_build', ['item' => $item, 'dto' => $dto]);
        return $dto;
    }

    /**
     * Get configured product options for a cart item (works for all product types)
     *
     * Uses Maho's built-in configuration helpers which return formatted label/value pairs:
     * - Configurable: attribute selections (e.g., "Color: Red", "Size: M")
     * - Bundle: selected options with qty and price (e.g., "Camera: 1 x Madison LX2200 $150.00")
     * - Downloadable: selected links
     * - Simple/Virtual: custom options only
     * - Grouped: shown as individual simple items, so custom options only
     *
     * @return array<array{label: string, value: string}>
     */
    private function getItemConfigurationOptions(\Mage_Sales_Model_Quote_Item $item): array
    {
        try {
            $typeId = $item->getProductType() ?: $item->getProduct()->getTypeId();

            // Use the appropriate configuration helper per product type
            $rawOptions = match ($typeId) {
                \Mage_Catalog_Model_Product_Type::TYPE_BUNDLE => \Mage::helper('bundle/catalog_product_configuration')->getOptions($item),
                \Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE => \Mage::helper('downloadable/catalog_product_configuration')->getOptions($item),
                default => \Mage::helper('catalog/product_configuration')->getOptions($item),
            };

            // Normalize to simple label/value string pairs for the API
            $options = [];
            foreach ($rawOptions as $option) {
                $label = $option['label'] ?? '';
                $value = $option['value'] ?? '';

                // Value can be an array (e.g., bundle selections)
                if (is_array($value)) {
                    $value = implode(', ', array_map(fn($v) => strip_tags((string) $v), $value));
                } else {
                    $value = strip_tags((string) $value);
                }

                if ($label !== '' && $value !== '') {
                    $options[] = ['label' => (string) $label, 'value' => $value];
                }
            }

            return $options;
        } catch (\Throwable $e) {
            // Don't let option formatting break the cart response
            \Mage::log('Error getting item configuration options: ' . $e->getMessage(), \Mage::LOG_WARNING);
            return [];
        }
    }

    /**
     * Map Maho quote to prices array
     */
    private function mapPricesToArray(\Mage_Sales_Model_Quote $quote): array
    {
        $shippingAddress = $quote->getShippingAddress();

        $prices = [
            'subtotal' => (float) $quote->getSubtotal(),
            'subtotalInclTax' => (float) ($quote->getSubtotal() + ($shippingAddress ? $shippingAddress->getTaxAmount() : 0)),
            'subtotalWithDiscount' => (float) $quote->getSubtotalWithDiscount(),
            'discountAmount' => null,
            'shippingAmount' => null,
            'shippingAmountInclTax' => null,
            'taxAmount' => 0.0,
            'grandTotal' => (float) $quote->getGrandTotal(),
            'giftcardAmount' => null,
        ];

        if ($shippingAddress) {
            $prices['discountAmount'] = $shippingAddress->getDiscountAmount()
                ? (float) abs($shippingAddress->getDiscountAmount())
                : null;
            $prices['shippingAmount'] = $shippingAddress->getShippingAmount()
                ? (float) $shippingAddress->getShippingAmount()
                : null;
            $prices['shippingAmountInclTax'] = $shippingAddress->getShippingInclTax()
                ? (float) $shippingAddress->getShippingInclTax()
                : null;
            $prices['taxAmount'] = (float) $shippingAddress->getTaxAmount();
        }

        return $prices;
    }

    /**
     * Get available shipping methods for address
     *
     * @return array<array{carrierCode: string, methodCode: string, carrierTitle: string, methodTitle: string, price: float}>
     */
    private function getAvailableShippingMethods(\Mage_Sales_Model_Quote_Address $address): array
    {
        $methods = [];

        try {
            $address->collectShippingRates();
            $rates = $address->getAllShippingRates();

            foreach ($rates as $rate) {
                $methods[] = [
                    'carrierCode' => $rate->getCarrier(),
                    'methodCode' => $rate->getMethod(),
                    'carrierTitle' => $rate->getCarrierTitle(),
                    'methodTitle' => $rate->getMethodTitle(),
                    'price' => (float) $rate->getPrice(),
                ];
            }
        } catch (\Exception $e) {
            // Log error but don't fail
            \Mage::log('Error getting shipping methods: ' . $e->getMessage());
        }

        return $methods;
    }

    /**
     * Parse selected shipping method from address
     *
     * @return array{carrierCode: string, methodCode: string, carrierTitle: string, methodTitle: string, price: float}|null
     */
    private function parseShippingMethod(\Mage_Sales_Model_Quote_Address $address): ?array
    {
        $shippingMethod = $address->getShippingMethod();
        if (!$shippingMethod) {
            return null;
        }

        $parts = explode('_', $shippingMethod, 2);
        if (count($parts) < 2) {
            return null;
        }

        return [
            'carrierCode' => $parts[0],
            'methodCode' => $parts[1],
            'carrierTitle' => $address->getShippingDescription() ?? '',
            'methodTitle' => $address->getShippingDescription() ?? '',
            'price' => (float) $address->getShippingAmount(),
        ];
    }

    /**
     * Get available payment methods for quote
     *
     * @return array<array{code: string, title: string}>
     */
    private function getAvailablePaymentMethods(\Mage_Sales_Model_Quote $quote): array
    {
        $methods = [];

        try {
            $store = $quote->getStore();
            $availableMethods = \Mage::helper('payment')->getStoreMethods($store, $quote);

            foreach ($availableMethods as $method) {
                if ($method->canUseForCountry($quote->getBillingAddress()->getCountry())) {
                    $methods[] = [
                        'code' => $method->getCode(),
                        'title' => $method->getTitle(),
                    ];
                }
            }
        } catch (\Exception $e) {
            // Log error but don't fail
            \Mage::log('Error getting payment methods: ' . $e->getMessage());
        }

        return $methods;
    }
}
