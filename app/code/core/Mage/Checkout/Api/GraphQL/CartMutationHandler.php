<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

namespace Mage\Checkout\Api\GraphQL;

use Mage\Checkout\Api\CartRequest;
use Mage\Checkout\Api\Cart;
use Mage\Checkout\Api\CartMapper;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Security\AdminAcl;
use Maho\ApiPlatform\Trait\AdminQuoteTrait;
use Maho\Giftcard\Api\GiftCard;

/**
 * Cart Mutation Handler.
 *
 * Handles all cart-related GraphQL operations for admin API.
 * Uses CartMapper::mapQuoteToCart() for DTO building to ensure
 * events (api_cart_dto_build) and extensions fire consistently.
 */
class CartMutationHandler
{
    use AdminQuoteTrait;

    private readonly \Mage_Checkout_Service_Cart $cartService;

    public function __construct(private CartMapper $cartMapper)
    {
        $this->cartService = \Mage::getService('checkout/cart');
    }

    /**
     * Handle getCart query
     */
    public function handleGetCart(array $variables): array
    {
        AdminAcl::checkResource(Cart::class);
        $cartId = $variables['cartId'] ?? $variables['id'] ?? null;
        $maskedId = $variables['maskedId'] ?? null;
        $quote = CartRequest::load($cartId ? (int) $cartId : null, $maskedId);
        return ['cart' => $quote ? $this->mapCart($quote) : null];
    }

    /**
     * Handle createCart mutation
     */
    public function handleCreateCart(array $variables, array $context): array
    {
        AdminAcl::checkResource(Cart::class);
        $customerId = $variables['customerId'] ?? $context['customer_id'] ?? null;
        $storeId = $variables['storeId'] ?? $context['store_id'] ?? 1;
        $quote = $this->cartService->create($customerId ? (int) $customerId : null, (int) $storeId);
        $mapped = $this->mapCart($quote);
        $mapped['maskedId'] = $quote->getData('masked_quote_id');
        return ['createCart' => $mapped];
    }

    /**
     * Handle addToCart mutation
     */
    public function handleAddToCart(array $variables): array
    {
        AdminAcl::checkResource(Cart::class);
        $cartId = $variables['cartId'] ?? $variables['input']['cartId'] ?? null;
        $sku = $variables['sku'] ?? $variables['input']['sku'] ?? null;
        $qty = $variables['qty'] ?? $variables['input']['qty'] ?? null;
        // Admin-only surface (AdminAcl gate above), so no extra gate on the price override
        $customPrice = $variables['customPrice'] ?? $variables['input']['customPrice'] ?? null;
        if ($customPrice === '') {
            $customPrice = null;
        }
        if ($customPrice !== null && !is_numeric($customPrice)) {
            throw new \Mage_Core_Exception_Input()->addError('customPrice', 'customPrice must be a number');
        }

        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        if (!$sku) {
            throw ValidationException::requiredField('sku');
        }
        $quote = $this->loadAdminQuote($cartId);
        $this->cartService->addProduct(
            $quote,
            $this->cartService->getProductBySku((string) $sku, (int) $quote->getStoreId()),
            new \Maho\DataObject($qty === null ? [] : ['qty' => (float) $qty]),
            \Mage_Checkout_Model_Actor::Staff,
            $customPrice !== null ? (float) $customPrice : null,
        );

        return ['addToCart' => $this->mapCart($quote)];
    }

    /**
     * Handle updateQty mutation
     */
    public function handleUpdateQty(array $variables): array
    {
        AdminAcl::checkResource(Cart::class);
        $cartId = $variables['cartId'] ?? null;
        $itemId = $variables['itemId'] ?? null;
        $qty = $variables['qty'] ?? null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        if (!$itemId) {
            throw ValidationException::requiredField('itemId');
        }
        if ($qty === null) {
            throw ValidationException::requiredField('qty');
        }
        $quote = $this->loadAdminQuote($cartId);
        $quote = $this->cartService->updateItems($quote, [(int) $itemId => (float) $qty]);
        return ['updateQty' => $this->mapCart($quote)];
    }

    /**
     * Handle removeItem mutation
     */
    public function handleRemoveItem(array $variables): array
    {
        AdminAcl::checkResource(Cart::class);
        $cartId = $variables['cartId'] ?? null;
        $itemId = $variables['itemId'] ?? null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        if (!$itemId) {
            throw ValidationException::requiredField('itemId');
        }
        $quote = $this->loadAdminQuote($cartId);
        $quote = $this->cartService->removeItem($quote, (int) $itemId);
        return ['removeItem' => $this->mapCart($quote)];
    }


    /**
     * Handle applyCoupon mutation
     */
    public function handleApplyCoupon(array $variables): array
    {
        AdminAcl::checkResource(Cart::class);
        $cartId = $variables['cartId'] ?? null;
        $couponCode = is_scalar($variables['couponCode'] ?? null) ? (string) $variables['couponCode'] : '';
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        $quote = $this->loadAdminQuote($cartId);

        // One code field takes a coupon code or a gift card code
        $this->cartService->applyCode($quote, $couponCode, \Mage_Checkout_Model_Actor::Staff);

        return ['applyCoupon' => $this->mapCart($quote)];
    }

    /**
     * Handle removeCoupon mutation
     */
    public function handleRemoveCoupon(array $variables): array
    {
        AdminAcl::checkResource(Cart::class);
        $cartId = $variables['cartId'] ?? null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        $quote = $this->loadAdminQuote($cartId);
        $this->cartService->removeCoupon($quote);
        return ['removeCoupon' => $this->mapCart($quote)];
    }

    /**
     * Handle assignCustomerToCart mutation
     */
    public function handleAssignCustomer(array $variables): array
    {
        AdminAcl::checkResource(Cart::class);
        $cartId = $variables['cartId'] ?? null;
        $customerId = (int) ($variables['customerId'] ?? 0);
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        $quote = $this->loadAdminQuote($cartId);
        $customer = \Mage::getModel('customer/customer');
        if ($customerId) {
            $customer->load($customerId);
        }
        $quote = $this->cartService->assignCustomer($quote, $customer);
        return ['assignCustomerToCart' => $this->mapCart($quote)];
    }

    /**
     * Handle checkGiftCardBalance query
     */
    public function handleCheckGiftCardBalance(array $variables): array
    {
        AdminAcl::checkResource(GiftCard::class);
        $code = $variables['code'] ?? null;
        if (!$code) {
            throw ValidationException::requiredField('code');
        }
        /** @var \Maho_Giftcard_Service_Giftcard $service */
        $service = \Mage::getService('giftcard/giftcard');
        $giftcard = $service->getByCode((string) $code);
        ['balance' => $balance, 'currency' => $currencyCode] = $service->getDisplayBalance($giftcard, \Mage::app()->getStore());
        return ['checkGiftCardBalance' => [
            'code' => $giftcard->getCode(),
            'currency' => $currencyCode,
            'balance' => $balance,
            'status' => $giftcard->getStatus(),
            'isValid' => $giftcard->isValid(),
            'expiresAt' => $giftcard->getExpiresAt(),
        ]];
    }

    /**
     * Handle applyGiftCard mutation
     */
    public function handleApplyGiftCard(array $variables): array
    {
        AdminAcl::checkResource(GiftCard::class);
        $cartId = $variables['cartId'] ?? null;
        // Accept both 'code' and 'giftcardCode' parameter names
        $code = $variables['code'] ?? $variables['giftcardCode'] ?? null;
        $amount = isset($variables['amount']) ? (float) $variables['amount'] : null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        if (!$code) {
            throw ValidationException::requiredField('code');
        }

        $quote = $this->loadAdminQuote($cartId);

        \Mage::getService('giftcard/giftcard')->applyToCart($quote, (string) $code, $amount, \Mage_Checkout_Model_Actor::Staff);

        return ['applyGiftcardToCart' => $this->mapCart($quote)];
    }

    /**
     * Handle removeGiftCard mutation
     */
    public function handleRemoveGiftCard(array $variables): array
    {
        AdminAcl::checkResource(GiftCard::class);
        $cartId = $variables['cartId'] ?? null;
        // Accept both 'code' and 'giftcardCode' parameter names
        $code = $variables['code'] ?? $variables['giftcardCode'] ?? null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        if (!$code) {
            throw ValidationException::requiredField('code');
        }

        $quote = $this->loadAdminQuote($cartId);

        \Mage::getService('giftcard/giftcard')->removeFromCart($quote, (string) $code);

        return ['removeGiftcardFromCart' => $this->mapCart($quote)];
    }

    /**
     * Handle availableShippingMethods query
     */
    public function handleShippingMethods(array $variables): array
    {
        AdminAcl::checkResource(Cart::class);
        $cartId = $variables['cartId'] ?? null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }

        $quote = $this->cartService->refreshShippingRates($this->loadAdminQuote($cartId));
        $currency = $this->cartService->inQuoteStoreScope($quote, fn(): string => $quote->getStore()->getCurrentCurrencyCode());

        // CartMapper owns the rate mapping, so REST and GraphQL give the same methods at the same price
        $methods = [];
        foreach ($this->cartMapper->getShippingMethodsOfCart($quote) as $method) {
            $methods[] = [
                'carrierCode' => $method['carrierCode'],
                'carrierTitle' => $method['carrierTitle'],
                'methodCode' => $method['methodCode'],
                'methodTitle' => $method['methodTitle'],
                'amount' => $method['price'],
                'currency' => $currency,
                'available' => $method['available'],
                'errorMessage' => $method['errorMessage'],
            ];
        }

        return ['availableShippingMethods' => $methods];
    }

    /**
     * Map cart/quote to array format
     *
     * @param \Mage_Sales_Model_Quote|array $quote
     */
    public function mapCart($quote): array
    {
        if (is_array($quote)) {
            return $quote;
        }

        // Use CartMapper for consistent DTO building (fires api_cart_dto_build + api_cart_item_dto_build)
        $dto = $this->cartMapper->mapQuoteToCart($quote);
        $data = $dto->toArray();

        // maskedId is the real, CSPRNG-generated masked_quote_id (set by CartMapper).
        // Never derive it from the quote id, that would be reversible and guessable.

        // appliedGiftcards and per-item data are already populated by
        // CartMapper::mapQuoteToCart(), so REST and GraphQL share one source.
        return $data;
    }
}
