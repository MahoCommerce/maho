<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

namespace Maho\Revocation\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Processor;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Adapts API requests to the existing revocation domain logic.
 *
 * The customer submit path resolves and re-checks order ownership, then hands
 * off to Maho_Revocation_Model_Service::submit() with the authenticated-session
 * input keys so the recorded declaration is verified (verified = 1). The admin
 * patch path mirrors the backend save action (status + internal note).
 */
final class RevocationRequestProcessor extends Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        StoreContext::ensureStore();

        $name = $operation->getName();

        if (isset($uriVariables['id'])) {
            return $this->processAdminUpdate((int) $uriVariables['id'], $data);
        }

        if ($name === 'submitRevocation') {
            $args = $context['args']['input'] ?? [];
            return $this->submit(
                isset($args['orderId']) ? (int) $args['orderId'] : null,
                isset($args['orderReference']) ? (string) $args['orderReference'] : null,
                isset($args['reason']) ? (string) $args['reason'] : null,
                $context['request'] ?? null,
            );
        }

        $dto = $data instanceof RevocationRequest ? $data : null;
        return $this->submit(
            $dto?->orderId,
            $dto?->orderReference,
            $dto?->reason,
            $context['request'] ?? null,
        );
    }

    private function submit(?int $orderId, ?string $orderReference, ?string $reason, mixed $request): RevocationRequest
    {
        $helper = \Mage::helper('revocation');

        if (!$helper->isEnabled()) {
            throw new NotFoundHttpException('Revocation is not available');
        }

        $customerId = $this->requireAuthentication();
        $customer = \Mage::getModel('customer/customer')->load($customerId);
        if (!$customer->getId()) {
            throw new NotFoundHttpException('Customer not found');
        }

        $order = $this->resolveOwnedOrder($orderId, $orderReference, $customerId);

        if (!$helper->isOrderWithinCoolingOffWindow($order)) {
            throw new UnprocessableEntityHttpException('The revocation period for this order has expired');
        }

        $ip = (string) \Mage::helper('core/http')->getRemoteAddr();
        if ($ip !== '' && $helper->isIpRateLimited($ip, $order->getStore())) {
            throw new TooManyRequestsHttpException(3600, 'Too many requests. Please try again later.');
        }

        $customerName = trim($customer->getName() ?: (string) $customer->getFirstname() . ' ' . $customer->getLastname());

        $input = new \Maho\DataObject([
            'customer_name' => $customerName,
            'email' => (string) $customer->getEmail(),
            'order_reference' => (string) $order->getIncrementId(),
            'reason' => $reason,
            'ip' => $ip ?: null,
            'user_agent' => $request?->headers->get('User-Agent'),
            'locale' => \Mage::app()->getLocale()->getLocaleCode(),
            'store_id' => (int) $order->getStoreId(),
            'received_at_microtime' => microtime(true),
            'customer_id' => $customerId,
            'session_order_id' => (int) $order->getId(),
        ]);

        try {
            $model = \Mage::getModel('revocation/service')->submit($input);
        } catch (\Mage_Core_Exception $e) {
            throw new UnprocessableEntityHttpException($e->getMessage());
        }

        return RevocationRequest::fromModel($model);
    }

    /**
     * Loads the referenced order and re-checks ownership server-side. Either the
     * entity id or the increment id may be supplied; ownership is enforced either way.
     */
    private function resolveOwnedOrder(?int $orderId, ?string $orderReference, int $customerId): \Mage_Sales_Model_Order
    {
        $order = \Mage::getModel('sales/order');

        if ($orderId) {
            $order->load($orderId);
        } elseif ($orderReference !== null && trim($orderReference) !== '') {
            $order->loadByIncrementId(trim($orderReference));
        } else {
            throw new BadRequestHttpException('An order reference is required');
        }

        if (!$order->getId() || (int) $order->getCustomerId() !== $customerId) {
            throw new NotFoundHttpException('Order not found');
        }

        return $order;
    }

    private function processAdminUpdate(int $id, mixed $data): RevocationRequest
    {
        $this->requireAdminOrApiUser();

        $model = \Mage::getModel('revocation/request')->load($id);
        if (!$model->getId()) {
            throw new NotFoundHttpException('Revocation request not found');
        }

        $dto = $data instanceof RevocationRequest ? $data : null;

        if ($dto?->processedStatus !== null && $dto->processedStatus !== '') {
            try {
                \Mage::getModel('revocation/service')->applyProcessedStatus($model, $dto->processedStatus);
            } catch (\Mage_Core_Exception $e) {
                throw new UnprocessableEntityHttpException($e->getMessage());
            }
        }

        if ($dto?->adminNote !== null) {
            $note = trim($dto->adminNote);
            $model->setAdminNote($note !== '' ? $note : null);
        }

        $model->save();

        return RevocationRequest::fromModel($model, adminView: true);
    }
}
