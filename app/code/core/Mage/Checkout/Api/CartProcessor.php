<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Checkout
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Checkout\Api;

use ApiPlatform\Metadata\Operation;
use Symfony\Bundle\SecurityBundle\Security;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Cart State Processor - Handles cart mutations for API Platform
 */
final class CartProcessor extends \Maho\ApiPlatform\Processor
{
    private CartMapper $cartMapper;
    private CartService $cartService;

    public function __construct(Security $security)
    {
        parent::__construct($security);
        $this->cartMapper = new CartMapper();
        $this->cartService = new CartService();
    }

    /**
     * Process cart mutations
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Cart
    {
        StoreContext::ensureStore();

        $operationName = $operation->getName();

        // Bridge REST request body into context args (GraphQL populates args natively)
        if (empty($context['args']['input'])) {
            $context['args']['input'] = [];
            $request = $context['request'] ?? null;
            if ($request instanceof \Symfony\Component\HttpFoundation\Request) {
                $body = json_decode($request->getContent(), true);
                if (is_array($body)) {
                    $context['args']['input'] = $body;
                }
            }
        }

        // Map uriVariables for sub-resource params
        if (isset($uriVariables['itemId']) && !isset($context['args']['input']['itemId'])) {
            $context['args']['input']['itemId'] = $uriVariables['itemId'];
        }
        if (isset($uriVariables['code']) && !isset($context['args']['input']['giftcardCode'])) {
            $context['args']['input']['giftcardCode'] = (string) $uriVariables['code'];
        }

        return match ($operationName) {
            'createCart', 'create_guest_cart' => $this->createEmptyCart($context),
            'addToCart', 'add_guest_item', 'add_cart_item' => $this->addItemToCart($context, $uriVariables),
            'updateCartItemQty', 'update_guest_item', 'update_cart_item' => $this->updateCartItem($context, $uriVariables),
            'removeCartItem', 'remove_guest_item', 'remove_cart_item' => $this->removeItemFromCart($context, $uriVariables),
            'setCartItemFulfillment' => $this->setCartItemFulfillment($context, $uriVariables),
            'applyCouponToCart', 'apply_guest_coupon' => $this->applyCouponToCart($context, $uriVariables),
            'removeCouponFromCart', 'remove_guest_coupon' => $this->removeCouponFromCart($context, $uriVariables),
            'setShippingAddressOnCart' => $this->setShippingAddressOnCart($context, $uriVariables),
            'setBillingAddressOnCart' => $this->setBillingAddressOnCart($context, $uriVariables),
            'setShippingMethodOnCart' => $this->setShippingMethodOnCart($context, $uriVariables),
            'setPaymentMethodOnCart' => $this->setPaymentMethodOnCart($context, $uriVariables),
            'assignCustomerToCart' => $this->assignCustomerToCart($context),
            'applyGiftcardToCart', 'apply_guest_giftcard' => $this->applyGiftcardToCart($context, $uriVariables),
            'removeGiftcardFromCart', 'remove_guest_giftcard' => $this->removeGiftcardFromCart($context, $uriVariables),
            default => $data instanceof Cart ? $data : new Cart(),
        };
    }

    /**
     * Resolve cart and verify access — shared by all operation methods
     */
    private function resolveAndVerify(array $context, array $uriVariables): \Mage_Sales_Model_Quote
    {
        ['quote' => $quote, 'accessedByMaskedId' => $byMasked] =
            $this->cartService->resolveCartFromRequest($uriVariables, $context);

        if (!$quote) {
            throw new NotFoundHttpException('Cart not found');
        }

        $this->cartService->verifyCartAccess(
            $quote,
            $byMasked,
            $this->getAuthenticatedCustomerId(),
            $this->isAdmin() || $this->isPosUser() || $this->isApiUser(),
        );

        return $quote;
    }

    /**
     * Create an empty cart
     */
    private function createEmptyCart(array $context): Cart
    {
        $customerId = $context['customer_id'] ?? null;
        $storeId = $context['args']['input']['storeId'] ?? null;

        $result = $this->cartService->createEmptyCart($customerId, $storeId);

        return $this->cartMapper->mapQuoteToCart($result['quote'], false);
    }

    /**
     * Add item to cart
     */
    private function addItemToCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $sku = $args['sku'] ?? '';
        $qty = (float) ($args['qty'] ?? 1);
        $fulfillmentType = strtoupper($args['fulfillmentType'] ?? 'SHIP');

        // Build buy request options
        $buyOptions = [];
        if (!empty($args['options'])) {
            $buyOptions['options'] = $args['options'];
        }
        if (!empty($args['links'])) {
            $buyOptions['links'] = $args['links'];
        }
        if (!empty($args['superGroup'])) {
            $buyOptions['super_group'] = $args['superGroup'];
        }
        if (!empty($args['bundleOption'])) {
            $buyOptions['bundle_option'] = $args['bundleOption'];
        }
        if (!empty($args['bundleOptionQty'])) {
            $buyOptions['bundle_option_qty'] = $args['bundleOptionQty'];
        }

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->addItem($quote, $sku, $qty, $buyOptions);

        // Set fulfillment type on the newly added item
        if ($fulfillmentType !== 'SHIP') {
            $addedItem = null;
            foreach ($quote->getAllVisibleItems() as $item) {
                if ($item->getSku() === $sku) {
                    $addedItem = $item;
                }
            }
            if ($addedItem) {
                $this->cartService->setItemFulfillmentType($quote, (int) $addedItem->getId(), $fulfillmentType);
            }
        }

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Update cart item quantity
     */
    private function updateCartItem(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $itemId = $args['itemId'] ?? null;
        $qty = (float) ($args['qty'] ?? 1);

        if (!$itemId) {
            throw new \RuntimeException('Item ID is required');
        }

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->updateItem($quote, (int) $itemId, $qty);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Remove item from cart
     */
    private function removeItemFromCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $itemId = $args['itemId'] ?? null;

        if (!$itemId) {
            throw new \RuntimeException('Item ID is required');
        }

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->removeItem($quote, (int) $itemId);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Set fulfillment type for a cart item
     */
    private function setCartItemFulfillment(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $itemId = $args['itemId'] ?? null;
        $fulfillmentType = $args['fulfillmentType'] ?? 'SHIP';

        if (!$itemId) {
            throw new \RuntimeException('Item ID is required');
        }

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->setItemFulfillmentType($quote, (int) $itemId, $fulfillmentType);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Apply coupon code to cart
     */
    private function applyCouponToCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $couponCode = $args['couponCode'] ?? '';

        if (!$couponCode) {
            throw new \RuntimeException('Coupon code is required');
        }

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->applyCoupon($quote, $couponCode);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Remove coupon code from cart
     */
    private function removeCouponFromCart(array $context, array $uriVariables): Cart
    {
        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->removeCoupon($quote);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Set shipping address on cart
     */
    private function setShippingAddressOnCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->setShippingAddress($quote, $this->mapInputToAddressData($args));

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Set billing address on cart
     */
    private function setBillingAddressOnCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $sameAsShipping = $args['sameAsShipping'] ?? false;

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $addressData = $sameAsShipping ? [] : $this->mapInputToAddressData($args);
        $quote = $this->cartService->setBillingAddress($quote, $addressData, $sameAsShipping);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Set shipping method on cart
     */
    private function setShippingMethodOnCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $carrierCode = $args['carrierCode'] ?? '';
        $methodCode = $args['methodCode'] ?? '';

        if (!$carrierCode || !$methodCode) {
            throw new \RuntimeException('Carrier code and method code are required');
        }

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->setShippingMethod($quote, $carrierCode, $methodCode);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Set payment method on cart
     */
    private function setPaymentMethodOnCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $methodCode = $args['methodCode'] ?? '';
        $additionalData = $args['additionalData'] ?? null;

        if (!$methodCode) {
            throw new \RuntimeException('Payment method code is required');
        }

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->setPaymentMethod($quote, $methodCode, $additionalData);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Assign customer to cart (merge guest cart)
     */
    private function assignCustomerToCart(array $context, array $uriVariables = []): Cart
    {
        $args = $context['args']['input'] ?? [];
        $cartId = $args['cartId'] ?? null;
        $maskedId = $args['maskedId'] ?? null;
        $requestedCustomerId = $args['customerId'] ?? null;

        // Admin/POS users can assign any customer to any cart
        if ($this->isAdmin() || $this->isPosUser() || $this->isApiUser()) {
            if (!$requestedCustomerId) {
                throw new \RuntimeException('Customer ID is required');
            }
            $quote = $this->cartService->getCart(
                $cartId ? (int) $cartId : null,
                $maskedId,
            );
            if (!$quote) {
                throw new \RuntimeException('Cart not found');
            }

            $customerId = (int) $requestedCustomerId;
            $customer = \Mage::getModel('customer/customer')->load($customerId);
            if (!$customer->getId()) {
                throw new \RuntimeException('Customer not found');
            }

            $quote->assignCustomer($customer);
            $quote->collectTotals()->save();

            return $this->cartMapper->mapQuoteToCart($quote, false);
        }

        // Customer self-assignment (merge guest cart)
        $authenticatedCustomerId = $this->getAuthenticatedCustomerId();

        if (!$maskedId) {
            throw new \RuntimeException('Masked cart ID is required');
        }
        if (!$authenticatedCustomerId) {
            throw new \RuntimeException('Authentication required');
        }

        $customerId = (int) $authenticatedCustomerId;
        if ($requestedCustomerId && (int) $requestedCustomerId !== $customerId) {
            throw new \RuntimeException('Cannot assign a different customer to this cart');
        }

        $quote = $this->cartService->mergeCarts($maskedId, $customerId);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Apply gift card to cart
     */
    private function applyGiftcardToCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $giftcardCode = trim($args['giftcardCode'] ?? '');

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->applyGiftcard($quote, $giftcardCode);

        return $this->cartMapper->mapQuoteToCart($quote, false);
    }

    /**
     * Remove gift card from cart
     */
    private function removeGiftcardFromCart(array $context, array $uriVariables): Cart
    {
        $args = $context['args']['input'] ?? [];
        $giftcardCode = trim($args['giftcardCode'] ?? '');

        $quote = $this->resolveAndVerify($context, $uriVariables);
        $quote = $this->cartService->removeGiftcard($quote, $giftcardCode);

        return $this->cartMapper->mapQuoteToCart($quote, false);
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

}
