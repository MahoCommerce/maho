<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\Operation;
use Mage\Checkout\Api\CartService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Order State Processor - Handles order mutations for API Platform
 */
final class OrderProcessor extends \Maho\ApiPlatform\Processor
{
    private CartService $cartService;
    private OrderProvider $orderProvider;
    private OrderService $orderService;

    public function __construct(Security $security)
    {
        parent::__construct($security);
        $this->cartService = new CartService();
        $this->orderProvider = new OrderProvider($security);
        $this->orderService = new OrderService();
    }

    /**
     * Process order mutations
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Order
    {
        $operationName = $operation->getName();

        // Pass incrementId from URI for verify_order
        if (isset($uriVariables['incrementId'])) {
            $context['args']['input']['incrementId'] = (string) $uriVariables['incrementId'];
        }

        return match ($operationName) {
            'placeOrder', '_api_/orders_post', 'place_guest_order' => $this->placeOrder($context),
            'cancelOrder' => $this->cancelOrder($context),
            'verify_order' => $this->verifyOrder($context),
            default => $data instanceof Order ? $data : new Order(),
        };
    }

    /**
     * Place order from cart
     */
    private function placeOrder(array $context): Order
    {
        $args = $context['args']['input'] ?? $context['request_data'] ?? [];
        $cartId = $args['cartId'] ?? null;
        $maskedId = $args['maskedId'] ?? null;
        $guestEmail = $args['guestEmail'] ?? null;
        $orderNote = $args['orderNote'] ?? null;
        $cashTendered = isset($args['cashTendered']) ? (float) $args['cashTendered'] : null;
        $employeeId = isset($args['employeeId']) ? (int) $args['employeeId'] : null;
        $paymentMethod = $args['paymentMethod'] ?? null;
        $shippingMethod = $args['shippingMethod'] ?? null;

        // Get cart/quote
        $quote = $this->cartService->getCart(
            $cartId ? (int) $cartId : null,
            $maskedId,
        );

        if (!$quote) {
            throw new NotFoundHttpException('Cart not found');
        }

        // Verify cart ownership
        $this->verifyCartOwnership($quote, $maskedId !== null);

        // For admin/POS orders, apply default addresses and checkout settings
        // so orders can be placed without the full checkout flow
        if ($this->isAdmin() || $this->isPosUser()) {
            $this->cartService->preparePosQuote(
                $quote,
                $shippingMethod,
                $paymentMethod,
            );
            $quote->collectTotals()->save();
        } else {
            // Set payment method on quote if provided
            if ($paymentMethod) {
                $quote->getPayment()->setMethod($paymentMethod);
            }

            // Set shipping method on quote if provided
            if ($shippingMethod && !$quote->isVirtual()) {
                $shippingAddress = $quote->getShippingAddress();
                $shippingAddress->setShippingMethod($shippingMethod);
                $shippingAddress->setCollectShippingRates(1);
            }

            if ($paymentMethod || $shippingMethod) {
                $quote->collectTotals()->save();
            }
        }

        // Place order
        $result = $this->orderService->placeAdminOrder(
            $quote,
            $guestEmail,
            $orderNote,
            $cashTendered,
            $employeeId,
        );

        $order = $result['order'];
        $accessToken = $result['accessToken'];
        $changeAmount = $result['changeAmount'];

        $dto = $this->orderProvider->mapToDto($order, $accessToken);
        if ($changeAmount !== null) {
            $dto->changeAmount = $changeAmount;
        }
        return $dto;
    }

    /**
     * Cancel order
     */
    private function cancelOrder(array $context): Order
    {
        $args = $context['args']['input'] ?? [];
        $orderId = $args['orderId'] ?? null;
        $incrementId = $args['incrementId'] ?? null;
        $reason = $args['reason'] ?? null;

        // Get order
        $order = $this->orderService->getOrder(
            $orderId ? (int) $orderId : null,
            $incrementId,
        );

        if (!$order) {
            throw new NotFoundHttpException('Order not found');
        }

        // Verify access: customers can only cancel their own orders
        if (!$this->isAdmin() && !$this->isApiUser()) {
            $customerId = $this->getAuthenticatedCustomerId();
            if (!$customerId) {
                throw new BadRequestHttpException('Authentication required to cancel orders');
            }
            if ((int) $order->getCustomerId() !== $customerId) {
                throw new NotFoundHttpException('Order not found');
            }
        }

        // Cancel order
        $order = $this->orderService->cancelOrder($order, $reason);

        return $this->orderProvider->mapToDto($order);
    }

    /**
     * Verify the current user has access to place an order for this cart
     */
    private function verifyCartOwnership(\Mage_Sales_Model_Quote $quote, bool $accessedByMaskedId): void
    {
        $this->cartService->verifyCartAccess(
            $quote,
            $accessedByMaskedId,
            $this->getAuthenticatedCustomerId(),
            $this->isAdmin() || $this->isApiUser(),
        );
    }

    /**
     * Verify a placed order by one-time storefront token
     */
    private function verifyOrder(array $context): Order
    {
        $request = $context['request'] ?? null;
        $body = $request ? (json_decode($request->getContent(), true) ?? []) : [];
        $args = $context['args']['input'] ?? $body;

        $incrementId = $args['incrementId'] ?? '';
        $token = $args['orderToken'] ?? $body['orderToken'] ?? '';

        if (!$token || !$incrementId) {
            throw new BadRequestHttpException('Order increment ID and order token are required');
        }

        $order = \Mage::getModel('sales/order')->loadByIncrementId($incrementId);
        if (!$order || !$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        $storedToken = $order->getData('storefront_order_token');
        if (!$storedToken || !hash_equals($storedToken, $token)) {
            throw new HttpException(403, 'Invalid order token');
        }

        // Clear the token after successful verification (one-time use)
        $resource = \Mage::getSingleton('core/resource');
        $resource->getConnection('core_write')->update(
            $resource->getTableName('sales/order'),
            ['storefront_order_token' => null],
            ['entity_id = ?' => $order->getId()],
        );

        $dto = $this->orderProvider->mapToDto($order);

        // Generate account creation token for guest orders without existing account
        $orderEmail = $order->getCustomerEmail();
        $existingCustomer = \Mage::getModel('customer/customer')
            ->setWebsiteId(\Mage::app()->getStore()->getWebsiteId())
            ->loadByEmail($orderEmail);

        if (!$existingCustomer->getId()) {
            $dto->accessToken = AccountTokenService::generate((int) $order->getId(), $orderEmail);
        }

        return $dto;
    }
}
