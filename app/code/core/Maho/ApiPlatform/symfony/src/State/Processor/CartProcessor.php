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

namespace Maho\ApiPlatform\State\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Maho\ApiPlatform\ApiResource\Cart;
use Maho\ApiPlatform\ApiResource\CartItem;
use Maho\ApiPlatform\ApiResource\CartPrices;
use Maho\ApiPlatform\ApiResource\Address;
use Maho\ApiPlatform\Service\CartService;

/**
 * Cart State Processor - Handles cart mutations for API Platform
 *
 * @implements ProcessorInterface<Cart, Cart>
 */
final class CartProcessor implements ProcessorInterface
{
    private CartService $cartService;

    public function __construct()
    {
        $this->cartService = new CartService();
    }

    /**
     * Process cart mutations
     */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Cart
    {
        $operationName = $operation->getName();

        return match ($operationName) {
            'createCart' => $this->createEmptyCart($context),
            'addToCart' => $this->addItemToCart($context),
            'updateCartItemQty' => $this->updateCartItem($context),
            'removeCartItem' => $this->removeItemFromCart($context),
            'setCartItemFulfillment' => $this->setCartItemFulfillment($context),
            'applyCouponToCart' => $this->applyCouponToCart($context),
            'removeCouponFromCart' => $this->removeCouponFromCart($context),
            'setShippingAddressOnCart' => $this->setShippingAddressOnCart($context),
            'setBillingAddressOnCart' => $this->setBillingAddressOnCart($context),
            'setShippingMethodOnCart' => $this->setShippingMethodOnCart($context),
            'setPaymentMethodOnCart' => $this->setPaymentMethodOnCart($context),
            'assignCustomerToCart' => $this->assignCustomerToCart($context),
            'applyGiftcardToCart' => $this->applyGiftcardToCart($context),
            'removeGiftcardFromCart' => $this->removeGiftcardFromCart($context),
            default => $data instanceof Cart ? $data : new Cart(),
        };
    }

    /**
     * Create an empty cart
     */
    private function createEmptyCart(array $context): Cart
    {
        $customerId = $context['customer_id'] ?? null;
        $storeId = $context['args']['input']['storeId'] ?? null;

        $result = $this->cartService->createEmptyCart($customerId, $storeId);
        $quote = $result['quote'];
        $maskedId = $result['maskedId'];

        $cart = new Cart();
        $cart->id = (int) $quote->getId();
        $cart->maskedId = $maskedId;
        $cart->customerId = $customerId ? (int) $customerId : null;
        $cart->storeId = (int) $quote->getStoreId();
        $cart->isActive = true;
        $cart->itemsCount = 0;
        $cart->itemsQty = 0;
        $cart->createdAt = $quote->getCreatedAt();

        return $cart;
    }

    /**
     * Add item to cart
     */
    private function addItemToCart(array $context): Cart
    {
        $args = $context['args']['input'] ?? [];
        $cartId = $args['cartId'] ?? null;
        $maskedId = $args['maskedId'] ?? null;
        $sku = $args['sku'] ?? '';
        $qty = (float) ($args['qty'] ?? 1);
        $fulfillmentType = strtoupper($args['fulfillmentType'] ?? 'SHIP');

        // Validate fulfillment type
        if (!in_array($fulfillmentType, ['SHIP', 'PICKUP'], true)) {
            $fulfillmentType = 'SHIP';
        }

        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId
        );

        if (!$quote) {
            throw new \RuntimeException('Cart not found');
        }

        $quote = $this->cartService->addItem($quote, $sku, $qty);

        // Set fulfillment type on the newly added item
        if ($fulfillmentType !== 'SHIP') {
            // Find the item we just added (by SKU, get the last one in case of duplicates)
            $addedItem = null;
            foreach ($quote->getAllVisibleItems() as $item) {
                if ($item->getSku() === $sku) {
                    $addedItem = $item;
                }
            }

            if ($addedItem) {
                $this->setItemFulfillmentType($addedItem, $fulfillmentType);
            }
        }

        return $this->mapQuoteToCart($quote);
    }

    /**
     * Update cart item quantity
     */
    private function updateCartItem(array $context): Cart
    {
        $args = $context['args']['input'] ?? [];
        $cartId = $args['cartId'] ?? null;
        $maskedId = $args['maskedId'] ?? null;
        $itemId = $args['itemId'] ?? null;
        $qty = (float) ($args['qty'] ?? 1);

        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId
        );

        if (!$quote) {
            throw new \RuntimeException('Cart not found');
        }

        if (!$itemId) {
            throw new \RuntimeException('Item ID is required');
        }

        $quote = $this->cartService->updateItem($quote, (int) $itemId, $qty);

        return $this->mapQuoteToCart($quote);
    }

    /**
     * Remove item from cart
     */
    private function removeItemFromCart(array $context): Cart
    {
        $args = $context['args']['input'] ?? [];
        $cartId = $args['cartId'] ?? null;
        $maskedId = $args['maskedId'] ?? null;
        $itemId = $args['itemId'] ?? null;

        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId
        );

        if (!$quote) {
            throw new \RuntimeException('Cart not found');
        }

        if (!$itemId) {
            throw new \RuntimeException('Item ID is required');
        }

        $quote = $this->cartService->removeItem($quote, (int) $itemId);

        return $this->mapQuoteToCart($quote);
    }

    /**
     * Set fulfillment type for a cart item (SHIP or PICKUP)
     * Used for omnichannel scenarios like BOPIS (Buy Online, Pickup In Store)
     */
    private function setCartItemFulfillment(array $context): Cart
    {
        $args = $context['args']['input'] ?? [];
        $cartId = $args['cartId'] ?? null;
        $maskedId = $args['maskedId'] ?? null;
        $itemId = $args['itemId'] ?? null;
        $fulfillmentType = strtoupper($args['fulfillmentType'] ?? 'SHIP');

        // Validate fulfillment type
        if (!in_array($fulfillmentType, ['SHIP', 'PICKUP'], true)) {
            throw new \RuntimeException('Invalid fulfillment type. Must be SHIP or PICKUP');
        }

        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId
        );

        if (!$quote) {
            throw new \RuntimeException('Cart not found');
        }

        if (!$itemId) {
            throw new \RuntimeException('Item ID is required');
        }

        // Find the item
        $targetItem = null;
        foreach ($quote->getAllVisibleItems() as $item) {
            if ((int) $item->getId() === (int) $itemId) {
                $targetItem = $item;
                break;
            }
        }

        if (!$targetItem) {
            throw new \RuntimeException('Cart item not found');
        }

        $this->setItemFulfillmentType($targetItem, $fulfillmentType);

        return $this->mapQuoteToCart($quote);
    }

    /**
     * Set fulfillment type on a quote item using additional_data field
     */
    private function setItemFulfillmentType(\Mage_Sales_Model_Quote_Item $item, string $fulfillmentType): void
    {
        $additionalData = $item->getAdditionalData();
        $data = $additionalData ? json_decode($additionalData, true) : [];

        if (!is_array($data)) {
            $data = [];
        }

        $data['fulfillment_type'] = $fulfillmentType;

        $item->setAdditionalData(json_encode($data));
        $item->save();
    }

    /**
     * Get fulfillment type from a quote item's additional_data
     */
    private function getItemFulfillmentType(\Mage_Sales_Model_Quote_Item $item): string
    {
        $additionalData = $item->getAdditionalData();

        if ($additionalData) {
            $data = json_decode($additionalData, true);
            if (is_array($data) && isset($data['fulfillment_type'])) {
                return strtoupper($data['fulfillment_type']);
            }
        }

        return 'SHIP'; // Default
    }

    /**
     * Apply coupon code to cart
     */
    private function applyCouponToCart(array $context): Cart
    {
        $args = $context['args']['input'] ?? [];
        $cartId = $args['cartId'] ?? null;
        $maskedId = $args['maskedId'] ?? null;
        $couponCode = $args['couponCode'] ?? '';

        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId
        );

        if (!$quote) {
            throw new \RuntimeException('Cart not found');
        }

        if (!$couponCode) {
            throw new \RuntimeException('Coupon code is required');
        }

        $quote = $this->cartService->applyCoupon($quote, $couponCode);

        return $this->mapQuoteToCart($quote);
    }

    /**
     * Remove coupon code from cart
     */
    private function removeCouponFromCart(array $context): Cart
    {
        $args = $context['args']['input'] ?? [];
        $cartId = $args['cartId'] ?? null;
        $maskedId = $args['maskedId'] ?? null;

        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId
        );

        if (!$quote) {
            throw new \RuntimeException('Cart not found');
        }

        $quote = $this->cartService->removeCoupon($quote);

        return $this->mapQuoteToCart($quote);
    }

    /**
     * Set shipping address on cart
     */
    private function setShippingAddressOnCart(array $context): Cart
    {
        $args = $context['args']['input'] ?? [];
        $cartId = $args['cartId'] ?? null;
        $maskedId = $args['maskedId'] ?? null;
        $address = $args['address'] ?? [];

        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId
        );

        if (!$quote) {
            throw new \RuntimeException('Cart not found');
        }

        $addressData = $this->mapInputToAddressData($address);
        $quote = $this->cartService->setShippingAddress($quote, $addressData);

        return $this->mapQuoteToCart($quote);
    }

    /**
     * Set billing address on cart
     */
    private function setBillingAddressOnCart(array $context): Cart
    {
        $args = $context['args']['input'] ?? [];
        $cartId = $args['cartId'] ?? null;
        $maskedId = $args['maskedId'] ?? null;
        $address = $args['address'] ?? [];
        $sameAsShipping = $args['sameAsShipping'] ?? false;

        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId
        );

        if (!$quote) {
            throw new \RuntimeException('Cart not found');
        }

        $addressData = $sameAsShipping ? [] : $this->mapInputToAddressData($address);
        $quote = $this->cartService->setBillingAddress($quote, $addressData, $sameAsShipping);

        return $this->mapQuoteToCart($quote);
    }

    /**
     * Set shipping method on cart
     */
    private function setShippingMethodOnCart(array $context): Cart
    {
        $args = $context['args']['input'] ?? [];
        $cartId = $args['cartId'] ?? null;
        $maskedId = $args['maskedId'] ?? null;
        $carrierCode = $args['carrierCode'] ?? '';
        $methodCode = $args['methodCode'] ?? '';

        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId
        );

        if (!$quote) {
            throw new \RuntimeException('Cart not found');
        }

        if (!$carrierCode || !$methodCode) {
            throw new \RuntimeException('Carrier code and method code are required');
        }

        $quote = $this->cartService->setShippingMethod($quote, $carrierCode, $methodCode);

        return $this->mapQuoteToCart($quote);
    }

    /**
     * Set payment method on cart
     */
    private function setPaymentMethodOnCart(array $context): Cart
    {
        $args = $context['args']['input'] ?? [];
        $cartId = $args['cartId'] ?? null;
        $maskedId = $args['maskedId'] ?? null;
        $methodCode = $args['methodCode'] ?? '';
        $additionalData = $args['additionalData'] ?? null;

        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId
        );

        if (!$quote) {
            throw new \RuntimeException('Cart not found');
        }

        if (!$methodCode) {
            throw new \RuntimeException('Payment method code is required');
        }

        $quote = $this->cartService->setPaymentMethod($quote, $methodCode, $additionalData);

        return $this->mapQuoteToCart($quote);
    }

    /**
     * Assign customer to cart (merge guest cart)
     */
    private function assignCustomerToCart(array $context): Cart
    {
        $args = $context['args']['input'] ?? [];
        $maskedId = $args['maskedId'] ?? null;
        $customerId = $args['customerId'] ?? $context['customer_id'] ?? null;

        if (!$maskedId) {
            throw new \RuntimeException('Masked cart ID is required');
        }

        if (!$customerId) {
            throw new \RuntimeException('Customer ID is required');
        }

        $quote = $this->cartService->mergeCarts($maskedId, (int) $customerId);

        return $this->mapQuoteToCart($quote);
    }

    /**
     * Apply giftcard to cart
     */
    private function applyGiftcardToCart(array $context): Cart
    {
        $args = $context['args']['input'] ?? [];
        $cartId = $args['cartId'] ?? null;
        $maskedId = $args['maskedId'] ?? null;
        $giftcardCode = trim($args['giftcardCode'] ?? '');

        if (!$giftcardCode) {
            throw new \RuntimeException('Gift card code is required');
        }

        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId
        );

        if (!$quote) {
            throw new \RuntimeException('Cart not found');
        }

        // Check if cart has gift card products
        foreach ($quote->getAllItems() as $item) {
            if ($item->getProductType() === 'giftcard') {
                throw new \RuntimeException('Gift cards cannot be used to purchase gift card products');
            }
        }

        // Load gift card by code
        $giftcard = \Mage::getModel('giftcard/giftcard')->loadByCode($giftcardCode);

        if (!$giftcard->getId()) {
            throw new \RuntimeException('Gift card "' . $giftcardCode . '" is not valid');
        }

        if (!$giftcard->isValid()) {
            $status = $giftcard->getStatus();
            if ($status === 'pending') {
                throw new \RuntimeException('Gift card "' . $giftcardCode . '" is pending activation');
            } elseif ($status === 'expired') {
                throw new \RuntimeException('Gift card "' . $giftcardCode . '" has expired');
            } elseif ($status === 'used') {
                throw new \RuntimeException('Gift card "' . $giftcardCode . '" has been fully used');
            } else {
                throw new \RuntimeException('Gift card "' . $giftcardCode . '" is not active');
            }
        }

        // Get currently applied codes
        $appliedCodes = $quote->getGiftcardCodes();
        if ($appliedCodes) {
            $appliedCodes = json_decode($appliedCodes, true);
        } else {
            $appliedCodes = [];
        }

        // Check if already applied
        if (isset($appliedCodes[$giftcardCode])) {
            throw new \RuntimeException('Gift card "' . $giftcardCode . '" is already applied');
        }

        // Apply gift card - store max amount available (in quote currency)
        $quoteCurrency = $quote->getQuoteCurrencyCode();
        $appliedCodes[$giftcardCode] = $giftcard->getBalance($quoteCurrency);

        $quote->setGiftcardCodes(json_encode($appliedCodes));
        $quote->collectTotals()->save();

        return $this->mapQuoteToCart($quote);
    }

    /**
     * Remove giftcard from cart
     */
    private function removeGiftcardFromCart(array $context): Cart
    {
        $args = $context['args']['input'] ?? [];
        $cartId = $args['cartId'] ?? null;
        $maskedId = $args['maskedId'] ?? null;
        $giftcardCode = trim($args['giftcardCode'] ?? '');

        if (!$giftcardCode) {
            throw new \RuntimeException('Gift card code is required');
        }

        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId
        );

        if (!$quote) {
            throw new \RuntimeException('Cart not found');
        }

        // Get currently applied codes
        $appliedCodes = $quote->getGiftcardCodes();
        if ($appliedCodes) {
            $appliedCodes = json_decode($appliedCodes, true);
        } else {
            $appliedCodes = [];
        }

        // Check if gift card is applied
        if (!isset($appliedCodes[$giftcardCode])) {
            throw new \RuntimeException('Gift card "' . $giftcardCode . '" is not applied to this cart');
        }

        // Remove the code
        unset($appliedCodes[$giftcardCode]);

        if (empty($appliedCodes)) {
            $quote->setGiftcardCodes(null);
            $quote->setGiftcardAmount(0);
            $quote->setBaseGiftcardAmount(0);
        } else {
            $quote->setGiftcardCodes(json_encode($appliedCodes));
        }

        $quote->collectTotals()->save();

        return $this->mapQuoteToCart($quote);
    }

    /**
     * Map input array to address data array
     */
    private function mapInputToAddressData(array $input): array
    {
        return [
            'firstname' => $input['firstName'] ?? '',
            'lastname' => $input['lastName'] ?? '',
            'company' => $input['company'] ?? null,
            'street' => $input['street'] ?? [],
            'city' => $input['city'] ?? '',
            'region' => $input['region'] ?? null,
            'region_id' => $input['regionId'] ?? null,
            'postcode' => $input['postcode'] ?? '',
            'country_id' => $input['countryId'] ?? '',
            'telephone' => $input['telephone'] ?? '',
        ];
    }

    /**
     * Map Maho quote model to Cart DTO
     */
    private function mapQuoteToCart(\Mage_Sales_Model_Quote $quote): Cart
    {
        $cart = new Cart();
        $cart->id = (int) $quote->getId();
        $cart->maskedId = $quote->getData('masked_id');
        $cart->customerId = $quote->getCustomerId() ? (int) $quote->getCustomerId() : null;
        $cart->storeId = (int) $quote->getStoreId();
        $cart->isActive = (bool) $quote->getIsActive();
        $cart->currency = $quote->getQuoteCurrencyCode() ?: 'AUD';
        $cart->itemsCount = (int) $quote->getItemsCount();
        $cart->itemsQty = (float) $quote->getItemsQty();
        $cart->createdAt = $quote->getCreatedAt();
        $cart->updatedAt = $quote->getUpdatedAt();

        // Map items
        $cart->items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $cart->items[] = $this->mapItemToDto($item);
        }

        // Map prices
        $cart->prices = $this->mapPricesToDto($quote);

        // Map billing address
        $billingAddress = $quote->getBillingAddress();
        if ($billingAddress && $billingAddress->getFirstname()) {
            $cart->billingAddress = $this->mapAddressToDto($billingAddress);
        }

        // Map shipping address
        $shippingAddress = $quote->getShippingAddress();
        if ($shippingAddress && $shippingAddress->getFirstname()) {
            $cart->shippingAddress = $this->mapAddressToDto($shippingAddress);

            // Get selected shipping method
            $selectedMethod = $shippingAddress->getShippingMethod();
            if ($selectedMethod) {
                $parts = explode('_', $selectedMethod, 2);
                if (count($parts) >= 2) {
                    $cart->selectedShippingMethod = [
                        'carrierCode' => $parts[0],
                        'methodCode' => $parts[1],
                        'carrierTitle' => $shippingAddress->getShippingDescription() ?? '',
                        'methodTitle' => $shippingAddress->getShippingDescription() ?? '',
                        'price' => (float) $shippingAddress->getShippingAmount(),
                    ];
                }
            }
        }

        // Get selected payment method
        $payment = $quote->getPayment();
        if ($payment && $payment->getMethod()) {
            try {
                $cart->selectedPaymentMethod = [
                    'code' => $payment->getMethod(),
                    'title' => $payment->getMethodInstance()->getTitle(),
                ];
            } catch (\Exception $e) {
                // Payment method may not be valid
                $cart->selectedPaymentMethod = [
                    'code' => $payment->getMethod(),
                    'title' => $payment->getMethod(),
                ];
            }
        }

        // Get applied coupon
        $couponCode = $quote->getCouponCode();
        if ($couponCode) {
            $cart->appliedCoupon = [
                'code' => $couponCode,
                'discountAmount' => (float) abs($shippingAddress ? $shippingAddress->getDiscountAmount() : 0),
            ];
        }

        return $cart;
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
        $dto->productId = $item->getProductId() ? (int) $item->getProductId() : null;
        $dto->productType = $item->getProductType();
        $dto->fulfillmentType = $this->getItemFulfillmentType($item);

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
}
