<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Maho\ApiPlatform\Service\CartService;

/**
 * Guest Cart Controller
 * Provides REST endpoints for guest shopping cart operations
 */
class GuestCartController extends AbstractController
{
    private CartService $cartService;

    public function __construct()
    {
        $this->cartService = new CartService();
    }

    /**
     * Create a new guest cart
     */
    #[Route('/api/guest-carts', name: 'api_guest_cart_create', methods: ['POST'])]
    public function createCart(Request $request): JsonResponse
    {
        try {
            $result = $this->cartService->createEmptyCart();
            $quote = $result['quote'];

            return new JsonResponse([
                'id' => (int) $quote->getId(),
                'maskedId' => $result['maskedId'],
                'itemsCount' => 0,
                'itemsQty' => 0,
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'cart_creation_failed',
                'message' => 'Unable to create cart. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get guest cart by ID
     */
    #[Route('/api/guest-carts/{cartId}', name: 'api_guest_cart_get', methods: ['GET'])]
    public function getCart(string $cartId): JsonResponse
    {
        try {
            $quote = $this->loadCart($cartId);

            if (!$quote) {
                return new JsonResponse([
                    'error' => 'cart_not_found',
                    'message' => 'Cart not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return new JsonResponse($this->mapCartToArray($quote));
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'error',
                'message' => 'An error occurred while loading the cart.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Add item to guest cart
     */
    #[Route('/api/guest-carts/{cartId}/items', name: 'api_guest_cart_item_create', methods: ['POST'])]
    public function addItem(string $cartId, Request $request): JsonResponse
    {
        try {
            $quote = $this->loadCart($cartId);

            if (!$quote) {
                return new JsonResponse([
                    'error' => 'cart_not_found',
                    'message' => 'Cart not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);
            $sku = $data['sku'] ?? '';
            $qty = (float) ($data['qty'] ?? 1);
            $options = $data['options'] ?? [];

            if (!$sku) {
                return new JsonResponse([
                    'error' => 'invalid_request',
                    'message' => 'SKU is required',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Convert flat options to structured format when grouped/bundle params are present
            if (!empty($data['super_group']) || !empty($data['bundle_option'])) {
                $structured = [];
                if (!empty($options)) {
                    $structured['options'] = $options;
                }
                if (!empty($data['super_group'])) {
                    $structured['super_group'] = $data['super_group'];
                }
                if (!empty($data['bundle_option'])) {
                    $structured['bundle_option'] = $data['bundle_option'];
                }
                if (!empty($data['bundle_option_qty'])) {
                    $structured['bundle_option_qty'] = $data['bundle_option_qty'];
                }
                $options = $structured;
            }

            $quote = $this->cartService->addItem($quote, $sku, $qty, $options);

            return new JsonResponse($this->mapCartToArray($quote));
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'error',
                'message' => 'An error occurred while processing your request',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Update item quantity
     */
    #[Route('/api/guest-carts/{cartId}/items/{itemId}', name: 'api_guest_cart_item_update', methods: ['PUT'])]
    public function updateItem(string $cartId, int $itemId, Request $request): JsonResponse
    {
        try {
            $quote = $this->loadCart($cartId);

            if (!$quote) {
                return new JsonResponse([
                    'error' => 'cart_not_found',
                    'message' => 'Cart not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);
            $qty = (float) ($data['qty'] ?? 1);

            $quote = $this->cartService->updateItem($quote, $itemId, $qty);

            return new JsonResponse($this->mapCartToArray($quote));
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'error',
                'message' => 'An error occurred while processing your request',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Remove item from cart
     */
    #[Route('/api/guest-carts/{cartId}/items/{itemId}', name: 'api_guest_cart_item_delete', methods: ['DELETE'])]
    public function removeItem(string $cartId, int $itemId): JsonResponse
    {
        try {
            $quote = $this->loadCart($cartId);

            if (!$quote) {
                return new JsonResponse([
                    'error' => 'cart_not_found',
                    'message' => 'Cart not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $quote = $this->cartService->removeItem($quote, $itemId);

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'error',
                'message' => 'An error occurred while processing your request',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get cart totals
     */
    #[Route('/api/guest-carts/{cartId}/totals', name: 'api_guest_cart_totals', methods: ['GET'])]
    public function getTotals(string $cartId): JsonResponse
    {
        try {
            $quote = $this->loadCart($cartId);

            if (!$quote) {
                return new JsonResponse([
                    'error' => 'cart_not_found',
                    'message' => 'Cart not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Ensure totals are collected (cart may have been freshly loaded)
            if (!$quote->getTotalsCollectedFlag()) {
                $quote->collectTotals();
            }

            $shippingAddress = $quote->getShippingAddress();

            return new JsonResponse([
                'subtotal' => (float) $quote->getSubtotal(),
                'discount' => $shippingAddress ? (float) abs($shippingAddress->getDiscountAmount()) : 0,
                'shipping' => $shippingAddress ? (float) $shippingAddress->getShippingAmount() : 0,
                'tax' => $shippingAddress ? (float) $shippingAddress->getTaxAmount() : 0,
                'grandTotal' => (float) $quote->getGrandTotal(),
            ]);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'error',
                'message' => 'An error occurred while loading cart totals.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Apply coupon code
     */
    #[Route('/api/guest-carts/{cartId}/coupon', name: 'api_guest_cart_apply_coupon', methods: ['PUT'])]
    public function applyCoupon(string $cartId, Request $request): JsonResponse
    {
        try {
            $quote = $this->loadCart($cartId);

            if (!$quote) {
                return new JsonResponse([
                    'error' => 'cart_not_found',
                    'message' => 'Cart not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);
            $couponCode = $data['couponCode'] ?? '';

            if (!$couponCode) {
                return new JsonResponse([
                    'error' => 'invalid_request',
                    'message' => 'Coupon code is required',
                ], Response::HTTP_BAD_REQUEST);
            }

            $quote = $this->cartService->applyCoupon($quote, $couponCode);

            return new JsonResponse([
                'success' => true,
                'couponCode' => $quote->getCouponCode(),
            ]);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'invalid_coupon',
                'message' => 'Could not apply coupon code',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Apply gift card code
     */
    #[Route('/api/guest-carts/{cartId}/giftcard', name: 'api_guest_cart_apply_giftcard', methods: ['POST'])]
    public function applyGiftcard(string $cartId, Request $request): JsonResponse
    {
        try {
            $quote = $this->loadCart($cartId);

            if (!$quote) {
                return new JsonResponse([
                    'error' => 'cart_not_found',
                    'message' => 'Cart not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);
            $giftcardCode = trim($data['giftcardCode'] ?? '');

            if (!$giftcardCode) {
                return new JsonResponse([
                    'error' => 'invalid_request',
                    'message' => 'Gift card code is required',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Check if cart has gift card products
            foreach ($quote->getAllItems() as $item) {
                if ($item->getProductType() === 'giftcard') {
                    return new JsonResponse([
                        'error' => 'invalid_request',
                        'message' => 'Gift cards cannot be used to purchase gift card products',
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            // Load gift card by code
            $giftcard = \Mage::getModel('giftcard/giftcard')->loadByCode($giftcardCode);

            if (!$giftcard->getId()) {
                return new JsonResponse([
                    'error' => 'invalid_giftcard',
                    'message' => 'Gift card is not valid',
                ], Response::HTTP_BAD_REQUEST);
            }

            if (!$giftcard->isValid()) {
                $status = $giftcard->getStatus();
                $msg = match ($status) {
                    'pending' => 'Gift card is pending activation',
                    'expired' => 'Gift card has expired',
                    'used' => 'Gift card has been fully used',
                    default => 'Gift card is not active',
                };
                return new JsonResponse([
                    'error' => 'invalid_giftcard',
                    'message' => $msg,
                ], Response::HTTP_BAD_REQUEST);
            }

            // Get currently applied codes
            $appliedCodes = $quote->getGiftcardCodes();
            $appliedCodes = $appliedCodes ? json_decode($appliedCodes, true) : [];

            if (isset($appliedCodes[$giftcardCode])) {
                return new JsonResponse([
                    'error' => 'already_applied',
                    'message' => 'Gift card is already applied to this cart',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Apply gift card
            $quoteCurrency = $quote->getQuoteCurrencyCode();
            $appliedCodes[$giftcardCode] = $giftcard->getBalance($quoteCurrency);

            $quote->setGiftcardCodes(json_encode($appliedCodes));
            $quote->collectTotals()->save();

            return new JsonResponse([
                'success' => true,
                'appliedGiftcards' => $this->mapAppliedGiftcards($quote),
            ]);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'error',
                'message' => 'An error occurred while processing your request',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Remove gift card code
     */
    #[Route('/api/guest-carts/{cartId}/giftcard', name: 'api_guest_cart_remove_giftcard', methods: ['DELETE'])]
    public function removeGiftcard(string $cartId, Request $request): JsonResponse
    {
        try {
            $quote = $this->loadCart($cartId);

            if (!$quote) {
                return new JsonResponse([
                    'error' => 'cart_not_found',
                    'message' => 'Cart not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);
            $giftcardCode = trim($data['giftcardCode'] ?? '');

            if (!$giftcardCode) {
                return new JsonResponse([
                    'error' => 'invalid_request',
                    'message' => 'Gift card code is required',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Get currently applied codes
            $appliedCodes = $quote->getGiftcardCodes();
            $appliedCodes = $appliedCodes ? json_decode($appliedCodes, true) : [];

            if (!isset($appliedCodes[$giftcardCode])) {
                return new JsonResponse([
                    'error' => 'not_applied',
                    'message' => 'Gift card is not applied to this cart',
                ], Response::HTTP_BAD_REQUEST);
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

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'error',
                'message' => 'An error occurred while processing your request',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Remove coupon code
     */
    #[Route('/api/guest-carts/{cartId}/coupon', name: 'api_guest_cart_remove_coupon', methods: ['DELETE'])]
    public function removeCoupon(string $cartId): JsonResponse
    {
        try {
            $quote = $this->loadCart($cartId);

            if (!$quote) {
                return new JsonResponse([
                    'error' => 'cart_not_found',
                    'message' => 'Cart not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $quote = $this->cartService->removeCoupon($quote);

            return new JsonResponse(['success' => true]);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'error',
                'message' => 'An error occurred while processing your request',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Get available shipping methods
     */
    #[Route('/api/guest-carts/{cartId}/shipping-methods', name: 'api_guest_cart_shipping_methods', methods: ['POST'])]
    public function getShippingMethods(string $cartId, Request $request): JsonResponse
    {
        try {
            $quote = $this->loadCart($cartId);

            if (!$quote) {
                return new JsonResponse([
                    'error' => 'cart_not_found',
                    'message' => 'Cart not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);
            $address = $data['address'] ?? [];

            // Set shipping address if provided
            if (!empty($address)) {
                $countryId = $address['countryId'] ?? 'AU';
                $regionText = $address['region'] ?? '';
                $regionId = $address['regionId'] ?? null;

                // Look up region_id if not provided but region text is
                if (!$regionId && $regionText && $countryId) {
                    $region = \Mage::getModel('directory/region')->loadByCode($regionText, $countryId);
                    if (!$region->getId()) {
                        // Try loading by name
                        $region = \Mage::getModel('directory/region')->loadByName($regionText, $countryId);
                    }
                    if ($region->getId()) {
                        $regionId = (int) $region->getId();
                        $regionText = $region->getName(); // Use official name
                    }
                }

                $addressData = [
                    'firstname' => $address['firstName'] ?? '',
                    'lastname' => $address['lastName'] ?? '',
                    'street' => $address['street'] ?? '',
                    'city' => $address['city'] ?? '',
                    'region' => $regionText,
                    'region_id' => $regionId,
                    'postcode' => $address['postcode'] ?? '',
                    'country_id' => $countryId,
                    'telephone' => $address['telephone'] ?? '',
                ];
                $quote = $this->cartService->setShippingAddress($quote, $addressData);
            }

            // Get shipping rates
            $shippingAddress = $quote->getShippingAddress();
            $shippingAddress->collectShippingRates();

            $methods = [];
            foreach ($shippingAddress->getAllShippingRates() as $rate) {
                $methods[] = [
                    'code' => $rate->getCarrier() . '_' . $rate->getMethod(),
                    'carrierCode' => $rate->getCarrier(),
                    'methodCode' => $rate->getMethod(),
                    'title' => $rate->getCarrierTitle() . ' - ' . $rate->getMethodTitle(),
                    'description' => $rate->getMethodDescription() ?? '',
                    'price' => (float) $rate->getPrice(),
                ];
            }

            return new JsonResponse($methods);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'error',
                'message' => 'An error occurred while loading shipping methods.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get available payment methods for cart
     *
     * Returns payment methods that are:
     * - Enabled in configuration
     * - Applicable for the cart (based on total, country, etc.)
     */
    #[Route('/api/guest-carts/{cartId}/payment-methods', name: 'api_guest_cart_payment_methods', methods: ['GET'])]
    public function getPaymentMethods(string $cartId): JsonResponse
    {
        try {
            $quote = $this->loadCart($cartId);

            if (!$quote) {
                return new JsonResponse([
                    'error' => 'cart_not_found',
                    'message' => 'Cart not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Ensure totals are collected for payment method filtering
            if (!$quote->getTotalsCollectedFlag()) {
                $quote->collectTotals();
            }

            $store = $quote->getStore();
            $methods = [];

            // Get all active payment methods
            /** @var \Mage_Payment_Model_Config $paymentConfig */
            $paymentConfig = \Mage::getSingleton('payment/config');
            $allMethods = $paymentConfig->getActiveMethods($store);

            foreach ($allMethods as $code => $methodModel) {
                try {
                    // Check if method is applicable for this quote
                    if (!$methodModel->isAvailable($quote)) {
                        continue;
                    }

                    // Get method info
                    $methods[] = [
                        'code' => $code,
                        'title' => $methodModel->getTitle(),
                        'description' => $methodModel->getConfigData('description') ?: null,
                        'sortOrder' => (int) ($methodModel->getConfigData('sort_order') ?: 0),
                        'isOffline' => $this->isOfflinePaymentMethod($code),
                    ];
                } catch (\Exception $e) {
                    // Skip methods that throw exceptions during availability check
                    continue;
                }
            }

            // Sort by sort_order
            usort($methods, fn($a, $b) => $a['sortOrder'] <=> $b['sortOrder']);

            return new JsonResponse($methods);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'error',
                'message' => 'An error occurred while loading payment methods.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Check if payment method is an offline method (no online processing required)
     */
    private function isOfflinePaymentMethod(string $code): bool
    {
        $offlineMethods = [
            'checkmo',
            'cashondelivery',
            'banktransfer',
            'purchaseorder',
            'free',
        ];
        return in_array($code, $offlineMethods, true);
    }

    /**
     * Place order
     */
    #[Route('/api/guest-carts/{cartId}/place-order', name: 'api_guest_cart_place_order', methods: ['POST'])]
    public function placeOrder(string $cartId, Request $request): JsonResponse
    {
        try {
            $quote = $this->loadCart($cartId);

            if (!$quote) {
                return new JsonResponse([
                    'error' => 'cart_not_found',
                    'message' => 'Cart not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $data = json_decode($request->getContent(), true);

            // Set email for guest
            if (isset($data['email'])) {
                $quote->setCustomerEmail($data['email']);
            }

            // Set shipping address
            if (isset($data['shippingAddress'])) {
                $addressData = [
                    'firstname' => $data['shippingAddress']['firstName'] ?? '',
                    'lastname' => $data['shippingAddress']['lastName'] ?? '',
                    'street' => $data['shippingAddress']['street'] ?? '',
                    'city' => $data['shippingAddress']['city'] ?? '',
                    'region' => $data['shippingAddress']['region'] ?? '',
                    'postcode' => $data['shippingAddress']['postcode'] ?? '',
                    'country_id' => $data['shippingAddress']['countryId'] ?? 'AU',
                    'telephone' => $data['shippingAddress']['telephone'] ?? '',
                ];
                $this->cartService->setShippingAddress($quote, $addressData);
            }

            // Set billing address
            if (isset($data['billingAddress'])) {
                $addressData = [
                    'firstname' => $data['billingAddress']['firstName'] ?? '',
                    'lastname' => $data['billingAddress']['lastName'] ?? '',
                    'street' => $data['billingAddress']['street'] ?? '',
                    'city' => $data['billingAddress']['city'] ?? '',
                    'region' => $data['billingAddress']['region'] ?? '',
                    'postcode' => $data['billingAddress']['postcode'] ?? '',
                    'country_id' => $data['billingAddress']['countryId'] ?? 'AU',
                    'telephone' => $data['billingAddress']['telephone'] ?? '',
                ];
                $this->cartService->setBillingAddress($quote, $addressData);
            }

            // Set shipping method
            if (isset($data['shippingMethod'])) {
                $parts = explode('_', $data['shippingMethod'], 2);
                if (count($parts) === 2) {
                    $this->cartService->setShippingMethod($quote, $parts[0], $parts[1]);
                }
            }

            // Set payment method
            $paymentMethod = $data['paymentMethod'] ?? 'checkmo';
            $this->cartService->setPaymentMethod($quote, $paymentMethod);

            // Place order
            $result = $this->cartService->placeOrder($quote, $paymentMethod);

            return new JsonResponse([
                'orderId' => $result['order_id'],
                'incrementId' => $result['increment_id'],
                'status' => $result['status'],
                'grandTotal' => $result['grand_total'],
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'order_failed',
                'message' => 'Failed to place order',
            ], Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * Load cart by masked ID only
     *
     * Security: Guest carts are only accessible via masked IDs (32-char hex tokens).
     * Numeric IDs are sequential and would allow cart enumeration attacks.
     */
    private function loadCart(string $cartId): ?\Mage_Sales_Model_Quote
    {
        return $this->cartService->getCart(null, $cartId);
    }

    /**
     * Map quote to array for JSON response
     */
    private function mapCartToArray(\Mage_Sales_Model_Quote $quote): array
    {
        // Only collect totals if not already collected (avoid duplicate expensive operation)
        // CartService methods already call collectTotals() after modifications
        if (!$quote->getTotalsCollectedFlag()) {
            $quote->collectTotals();
        }

        $items = [];
        foreach ($quote->getAllVisibleItems() as $item) {
            $itemData = [
                'id' => (int) $item->getId(),
                'sku' => $item->getSku(),
                'name' => $item->getName(),
                'qty' => (float) $item->getQty(),
                'price' => (float) $item->getPrice(),
                'priceInclTax' => (float) $item->getPriceInclTax(),
                'rowTotal' => (float) $item->getRowTotal(),
                'rowTotalInclTax' => (float) $item->getRowTotalInclTax(),
                'taxAmount' => (float) ($item->getTaxAmount() ?? 0),
                'taxPercent' => (float) ($item->getTaxPercent() ?? 0),
                'productId' => (int) $item->getProductId(),
                'options' => $this->getItemConfigurationOptions($item),
            ];

            // Get product thumbnail for cart display
            $productId = (int) $item->getProductId();
            $product = \Mage::getModel('catalog/product')->load($productId);
            $thumbnail = $product->getThumbnail();

            // If simple product has no thumbnail, check for configurable parent
            if (!$thumbnail || $thumbnail === 'no_selection') {
                $parentIds = \Mage::getModel('catalog/product_type_configurable')
                    ->getParentIdsByChild($productId);

                if (!empty($parentIds)) {
                    $parentProduct = \Mage::getModel('catalog/product')->load($parentIds[0]);
                    if ($parentProduct->getThumbnail() && $parentProduct->getThumbnail() !== 'no_selection') {
                        $product = $parentProduct;
                        $thumbnail = $parentProduct->getThumbnail();
                    }
                }
            }

            if ($thumbnail && $thumbnail !== 'no_selection') {
                try {
                    // Use small_image with width-only resize (more reliable than thumbnail with square resize)
                    $itemData['thumbnailUrl'] = (string) \Mage::helper('catalog/image')
                        ->init($product, 'small_image')
                        ->resize(100);
                } catch (\Exception $e) {
                    // Fallback to media URL if image helper fails
                    $mediaConfig = \Mage::getModel('catalog/product_media_config');
                    $itemData['thumbnailUrl'] = $mediaConfig->getMediaUrl($thumbnail);
                }
            }

            $items[] = $itemData;
        }

        $shippingAddress = $quote->getShippingAddress();

        $result = [
            'id' => (int) $quote->getId(),
            'maskedId' => $quote->getData('masked_quote_id'),
            'itemsCount' => (int) $quote->getItemsCount(),
            'itemsQty' => (float) $quote->getItemsQty(),
            'items' => $items,
            'prices' => [
                'subtotal' => (float) ($quote->getSubtotal() ?? 0),
                'discountAmount' => $shippingAddress ? (float) abs($shippingAddress->getDiscountAmount() ?? 0) : 0,
                'shippingAmount' => $shippingAddress ? (float) ($shippingAddress->getShippingAmount() ?? 0) : 0,
                'taxAmount' => $shippingAddress ? (float) ($shippingAddress->getTaxAmount() ?? 0) : 0,
                'grandTotal' => (float) ($quote->getGrandTotal() ?? 0),
            ],
            'couponCode' => $quote->getCouponCode(),
            'appliedGiftcards' => $this->mapAppliedGiftcards($quote),
        ];

        // Add giftcard amount to prices if present
        $giftcardAmount = (float) $quote->getData('giftcard_amount');
        if ($giftcardAmount > 0) {
            $result['prices']['giftcardAmount'] = $giftcardAmount;
        }

        return $result;
    }

    /**
     * Map applied gift cards from quote to response array
     *
     * @return array<array{code: string, balance: float, appliedAmount: float}>|null
     */
    private function mapAppliedGiftcards(\Mage_Sales_Model_Quote $quote): ?array
    {
        $giftcardCodesJson = $quote->getData('giftcard_codes');
        if (!$giftcardCodesJson) {
            return null;
        }

        $giftcardCodes = json_decode($giftcardCodesJson, true);
        if (!is_array($giftcardCodes) || empty($giftcardCodes)) {
            return null;
        }

        $cards = [];
        foreach ($giftcardCodes as $code => $balance) {
            $cards[] = [
                'code' => (string) $code,
                'balance' => (float) $balance,
                'appliedAmount' => 0.0,
            ];
        }

        return $cards;
    }

    /**
     * Get configured product options for a cart item (works for all product types)
     *
     * @return array<array{label: string, value: string}>
     */
    private function getItemConfigurationOptions(\Mage_Sales_Model_Quote_Item $item): array
    {
        try {
            $typeId = $item->getProductType() ?: $item->getProduct()->getTypeId();

            $rawOptions = match ($typeId) {
                'bundle' => \Mage::helper('bundle/catalog_product_configuration')->getOptions($item),
                'downloadable' => \Mage::helper('downloadable/catalog_product_configuration')->getOptions($item),
                default => \Mage::helper('catalog/product_configuration')->getOptions($item),
            };

            $options = [];
            foreach ($rawOptions as $option) {
                $label = $option['label'] ?? '';
                $value = $option['value'] ?? '';

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
            \Mage::log('Error getting item configuration options: ' . $e->getMessage(), \Mage::LOG_WARNING);
            return [];
        }
    }

    /**
     * Get product custom options (for add to cart)
     * Returns custom options that must be selected when adding product to cart
     */
    #[Route('/api/products/{sku}/options', name: 'api_product_options', methods: ['GET'])]
    public function getProductOptions(string $sku): JsonResponse
    {
        try {
            $productId = \Mage::getResourceModel('catalog/product')->getIdBySku($sku);

            if (!$productId) {
                return new JsonResponse([
                    'error' => 'product_not_found',
                    'message' => 'Product not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $product = \Mage::getModel('catalog/product')->load($productId);

            // If simple product with configurable parent, get options from parent
            if ($product->getTypeId() === \Mage_Catalog_Model_Product_Type::TYPE_SIMPLE) {
                $parentIds = \Mage::getModel('catalog/product_type_configurable')
                    ->getParentIdsByChild((int) $productId);

                if (!empty($parentIds)) {
                    $product = \Mage::getModel('catalog/product')->load($parentIds[0]);
                }
            }

            $options = [];
            foreach ($product->getOptions() as $option) {
                $optionData = [
                    'id' => (int) $option->getId(),
                    'title' => $option->getTitle(),
                    'type' => $option->getType(),
                    'required' => (bool) $option->getIsRequire(),
                    'sort_order' => (int) $option->getSortOrder(),
                    'values' => [],
                ];

                if ($option->getValues()) {
                    foreach ($option->getValues() as $value) {
                        $optionData['values'][] = [
                            'id' => (int) $value->getId(),
                            'title' => $value->getTitle(),
                            'price' => (float) $value->getPrice(),
                            'price_type' => $value->getPriceType(),
                            'sort_order' => (int) $value->getSortOrder(),
                        ];
                    }
                }

                $options[] = $optionData;
            }

            return new JsonResponse([
                'sku' => $product->getSku(),
                'name' => $product->getName(),
                'has_required_options' => $product->getRequiredOptions() ? true : false,
                'options' => $options,
            ]);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'error',
                'message' => 'An error occurred while loading product options.',
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
