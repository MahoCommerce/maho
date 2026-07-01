<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

namespace Mage\Checkout\Api\GraphQL;

use Mage\Checkout\Api\Cart;
use Mage\Checkout\Api\CartMapper;
use Mage\Checkout\Api\CartService;
use Maho\ApiPlatform\Exception\NotFoundException;
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

    private CartService $cartService;
    private CartMapper $cartMapper;

    public function __construct(CartService $cartService, CartMapper $cartMapper)
    {
        $this->cartService = $cartService;
        $this->cartMapper = $cartMapper;
    }

    /**
     * Handle getCart query
     */
    public function handleGetCart(array $variables): array
    {
        AdminAcl::checkResource(Cart::class);
        $cartId = $variables['cartId'] ?? $variables['id'] ?? null;
        $maskedId = $variables['maskedId'] ?? null;
        $quote = $this->cartService->getCart($cartId ? (int) $cartId : null, $maskedId);
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
        $result = $this->cartService->createEmptyCart($customerId, $storeId);
        $mapped = $this->mapCart($result['quote']);
        if (!empty($result['maskedId'])) {
            $mapped['maskedId'] = $result['maskedId'];
        }
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
        $qty = $variables['qty'] ?? $variables['input']['qty'] ?? 1;

        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        if (!$sku) {
            throw ValidationException::requiredField('sku');
        }
        $quote = $this->cartService->getCart((int) $cartId);
        if (!$quote) {
            throw NotFoundException::cart($cartId);
        }
        $quote = $this->cartService->addItem($quote, $sku, (float) $qty);

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
        $quote = $this->cartService->getCart((int) $cartId);
        if (!$quote) {
            throw NotFoundException::cart($cartId);
        }
        $quote = $this->cartService->updateItem($quote, (int) $itemId, (float) $qty);
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
        $quote = $this->cartService->getCart((int) $cartId);
        if (!$quote) {
            throw NotFoundException::cart($cartId);
        }
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
        $couponCode = $variables['couponCode'] ?? null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        if (!$couponCode) {
            throw ValidationException::requiredField('couponCode');
        }
        $quote = $this->cartService->getCart((int) $cartId);
        if (!$quote) {
            throw NotFoundException::cart($cartId);
        }

        // First, check if this is a gift card code
        /** @var \Maho_Giftcard_Model_Giftcard $giftcard */
        $giftcard = \Mage::getModel('giftcard/giftcard')->loadByCode($couponCode);
        if ($giftcard->getId() && $giftcard->isValid()) {
            // It's a valid gift card - apply via the shared REST path so the
            // website-scope and currency checks aren't bypassed.
            try {
                $this->cartService->applyGiftcard($quote, $couponCode);
            } catch (\RuntimeException $e) {
                throw ValidationException::invalidValue('couponCode', $e->getMessage());
            }
            return ['applyCoupon' => $this->mapCart($quote)];
        }

        // Not a gift card, try as coupon
        try {
            $this->cartService->applyCoupon($quote, $couponCode);
        } catch (\Exception $e) {
            \Mage::logException($e);
            throw ValidationException::invalidValue('couponCode', 'invalid or expired coupon');
        }

        $quote->collectTotals();
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
        $quote = $this->cartService->getCart((int) $cartId);
        if (!$quote) {
            throw NotFoundException::cart($cartId);
        }
        $this->cartService->removeCoupon($quote);
        $quote->collectTotals();
        return ['removeCoupon' => $this->mapCart($quote)];
    }

    /**
     * Handle assignCustomerToCart mutation
     */
    public function handleAssignCustomer(array $variables): array
    {
        AdminAcl::checkResource(Cart::class);
        $cartId = $variables['cartId'] ?? null;
        $customerId = $variables['customerId'] ?? null;
        if (!$cartId) {
            throw ValidationException::requiredField('cartId');
        }
        if (!$customerId) {
            throw ValidationException::requiredField('customerId');
        }
        $quote = $this->cartService->getCart((int) $cartId);
        if (!$quote) {
            throw NotFoundException::cart($cartId);
        }
        $customer = \Mage::getModel('customer/customer')->load($customerId);
        if (!$customer->getId()) {
            throw NotFoundException::customer($customerId);
        }
        $quote->assignCustomer($customer);
        $quote->collectTotals();
        $quote->save();
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
        /** @var \Maho_Giftcard_Model_Giftcard $giftcard */
        $giftcard = \Mage::getModel('giftcard/giftcard')->loadByCode($code);
        if (!$giftcard->getId()) {
            throw NotFoundException::giftCard($code);
        }
        return ['checkGiftCardBalance' => [
            'code' => $giftcard->getCode(),
            'currency' => \Mage::app()->getStore()->getCurrentCurrencyCode(),
            'balance' => (float) $giftcard->getBalance(),
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

        $quote = $this->cartService->getCart((int) $cartId);
        if (!$quote) {
            throw NotFoundException::cart($cartId);
        }

        // Reuse the REST path so website-scope, quote-currency balance, validity
        // and duplicate checks stay in one place (CartService::applyGiftcard also
        // collects totals and saves). Avoids the drift this handler had before.
        try {
            $this->cartService->applyGiftcard($quote, (string) $code, $amount);
        } catch (\RuntimeException $e) {
            throw ValidationException::invalidValue('code', $e->getMessage());
        }

        // Reload quote to get fresh totals, falling back to the in-memory quote
        // (already collected and saved by applyGiftcard) if the reload comes back
        // empty, so mapCart() never receives null.
        $reloaded = $this->cartService->getCart((int) $cartId);
        return ['applyGiftcardToCart' => $this->mapCart($reloaded ?? $quote)];
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

        $quote = $this->cartService->getCart((int) $cartId);
        if (!$quote) {
            throw NotFoundException::cart($cartId);
        }

        // Reuse the REST path so the giftcard_amount/base_giftcard_amount fields
        // are zeroed and the totals-collected flag is reset before re-collecting
        // (removeGiftcard also collects totals and saves). The previous inline
        // removal only unset the code from the JSON blob, leaving a stale gift
        // card discount on an already-collected quote.
        try {
            $this->cartService->removeGiftcard($quote, (string) $code);
        } catch (\RuntimeException $e) {
            throw ValidationException::invalidValue('code', $e->getMessage());
        }

        // Reload quote to get fresh totals, falling back to the in-memory quote
        // (already collected and saved above) if the reload comes back empty, so
        // mapCart() never receives null.
        $reloaded = $this->cartService->getCart((int) $cartId);
        return ['removeGiftcardFromCart' => $this->mapCart($reloaded ?? $quote)];
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

        $quote = $this->loadAdminQuote($cartId);

        if ($quote->getStoreId()) {
            \Mage::app()->setCurrentStore($quote->getStoreId());
            $quote->setStore(\Mage::app()->getStore($quote->getStoreId()));
        }

        $shippingAddress = $quote->getShippingAddress();
        if (!$shippingAddress->getCountryId()) {
            $defaults = \Maho\ApiPlatform\Service\StoreDefaults::getPosAddress($quote->getStoreId() ? (int) $quote->getStoreId() : null);
            $shippingAddress->setCountryId($defaults['country_id'])->setPostcode($defaults['postcode'])->setRegionId($defaults['region_id'])->setCollectShippingRates(1);
        }

        $shippingAddress->collectShippingRates();
        $rates = $shippingAddress->getGroupedAllShippingRates();
        $currency = $quote->getQuoteCurrencyCode();

        $methods = [];
        foreach ($rates as $carrierRates) {
            foreach ($carrierRates as $rate) {
                $methods[] = [
                    'carrierCode' => $rate->getCarrier(),
                    'carrierTitle' => $rate->getCarrierTitle(),
                    'methodCode' => $rate->getMethod(),
                    'methodTitle' => $rate->getMethodTitle(),
                    'amount' => (float) $rate->getPrice(),
                    'currency' => $currency,
                    'available' => !$rate->getErrorMessage(),
                    'errorMessage' => $rate->getErrorMessage(),
                ];
            }
        }
        $hasFreeShipping = array_any($methods, fn($m) => $m['carrierCode'] === 'freeshipping');
        if (!$hasFreeShipping) {
            array_unshift($methods, [
                'carrierCode' => 'freeshipping', 'carrierTitle' => 'Free Shipping',
                'methodCode' => 'freeshipping', 'methodTitle' => 'POS In-Store Pickup',
                'amount' => 0.0,
                'currency' => $currency,
                'available' => true, 'errorMessage' => null,
            ]);
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
