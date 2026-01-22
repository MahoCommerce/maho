<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\State\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Maho\ApiPlatform\ApiResource\Cart;
use Maho\ApiPlatform\ApiResource\CartItem;
use Maho\ApiPlatform\ApiResource\CartPrices;
use Maho\ApiPlatform\ApiResource\Address;
use Maho\ApiPlatform\Service\CartService;

/**
 * Cart State Provider - Fetches cart data for API Platform
 *
 * @implements ProviderInterface<Cart>
 */
final class CartProvider implements ProviderInterface
{
    private CartService $cartService;

    public function __construct()
    {
        $this->cartService = new CartService();
    }

    /**
     * Provide cart data based on operation type
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?Cart
    {
        $operationName = $operation->getName();

        // Handle customerCart query - get current authenticated customer's cart
        if ($operationName === 'customerCart') {
            $customerId = $context['customer_id'] ?? null;
            if ($customerId) {
                $quote = $this->cartService->getCustomerCart((int) $customerId);
                return $quote ? $this->mapToDto($quote) : null;
            }
            return null;
        }

        // Handle cart query with cartId or maskedId
        $cartId = $context['args']['cartId'] ?? $uriVariables['id'] ?? null;
        $maskedId = $context['args']['maskedId'] ?? null;

        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId
        );

        return $quote ? $this->mapToDto($quote) : null;
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
        $dto->maskedId = $quote->getData('masked_id');
        $dto->customerId = $quote->getCustomerId() ? (int) $quote->getCustomerId() : null;
        $dto->storeId = (int) $quote->getStoreId();
        $dto->isActive = (bool) $quote->getIsActive();
        $dto->currency = $quote->getQuoteCurrencyCode() ?: 'AUD';
        $dto->itemsCount = (int) $quote->getItemsCount();
        $dto->itemsQty = (float) $quote->getItemsQty();
        $dto->createdAt = $quote->getCreatedAt();
        $dto->updatedAt = $quote->getUpdatedAt();

        // Map items
        $dto->items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $dto->items[] = $this->mapItemToDto($item);
        }

        // Map prices
        $dto->prices = $this->mapPricesToDto($quote);

        // Map billing address
        $billingAddress = $quote->getBillingAddress();
        if ($billingAddress && $billingAddress->getId()) {
            $dto->billingAddress = $this->mapAddressToDto($billingAddress);
        }

        // Map shipping address
        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress && $shippingAddress->getId()) {
            $dto->shippingAddress = $this->mapAddressToDto($shippingAddress);

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

        return $dto;
    }

    /**
     * Map Maho quote item model to CartItem DTO
     */
    private function mapItemToDto(\Mage_Sales_Model_Quote_Item $item): CartItem
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

        // Get product thumbnail for cart display
        $product = $item->getProduct();
        if ($product) {
            $thumbnail = $product->getThumbnail();
            if ($thumbnail && $thumbnail !== 'no_selection') {
                try {
                    // Use small_image with width-only resize (more reliable than thumbnail with square resize)
                    $dto->thumbnailUrl = (string) \Mage::helper('catalog/image')
                        ->init($product, 'small_image')
                        ->resize(100);
                } catch (\Exception $e) {
                    // Fallback to media URL if image helper fails
                    $mediaConfig = \Mage::getModel('catalog/product_media_config');
                    $dto->thumbnailUrl = $mediaConfig->getMediaUrl($thumbnail);
                }
            }
        }

        return $dto;
    }

    /**
     * Map Maho quote to CartPrices DTO
     */
    private function mapPricesToDto(\Mage_Sales_Model_Quote $quote): CartPrices
    {
        $prices = new CartPrices();
        $shippingAddress = $quote->getShippingAddress();

        $prices->subtotal = (float) $quote->getSubtotal();
        $prices->subtotalInclTax = (float) ($quote->getSubtotal() + ($shippingAddress ? $shippingAddress->getTaxAmount() : 0));
        $prices->subtotalWithDiscount = (float) $quote->getSubtotalWithDiscount();

        if ($shippingAddress) {
            $prices->discountAmount = $shippingAddress->getDiscountAmount()
                ? (float) abs($shippingAddress->getDiscountAmount())
                : null;
            $prices->shippingAmount = $shippingAddress->getShippingAmount()
                ? (float) $shippingAddress->getShippingAmount()
                : null;
            $prices->shippingAmountInclTax = $shippingAddress->getShippingInclTax()
                ? (float) $shippingAddress->getShippingInclTax()
                : null;
            $prices->taxAmount = (float) $shippingAddress->getTaxAmount();
        }

        $prices->grandTotal = (float) $quote->getGrandTotal();

        return $prices;
    }

    /**
     * Map Maho quote address model to Address DTO
     */
    private function mapAddressToDto(\Mage_Sales_Model_Quote_Address $address): Address
    {
        $dto = new Address();
        $dto->id = (int) $address->getId();
        $dto->firstName = $address->getFirstname() ?? '';
        $dto->lastName = $address->getLastname() ?? '';
        $dto->company = $address->getCompany();
        $dto->street = $address->getStreet();
        $dto->city = $address->getCity() ?? '';
        $dto->region = $address->getRegion();
        $dto->regionId = $address->getRegionId() ? (int) $address->getRegionId() : null;
        $dto->postcode = $address->getPostcode() ?? '';
        $dto->countryId = $address->getCountryId() ?? '';
        $dto->telephone = $address->getTelephone() ?? '';

        return $dto;
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
