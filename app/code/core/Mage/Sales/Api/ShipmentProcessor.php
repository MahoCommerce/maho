<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

namespace Mage\Sales\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Http\RequestInput;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Shipment State Processor - Handles shipment creation for API Platform.
 */
final class ShipmentProcessor extends \Maho\ApiPlatform\Processor
{
    use \Maho\ApiPlatform\Trait\OrderItemsTrait;

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Shipment
    {
        $operationName = $operation->getName();

        // Bridge the raw REST body into $context['args']['input'] so the track /
        // shipment handlers read it uniformly; GraphQL already populates it.
        $this->normalizeGraphQlInput($context);

        return match ($operationName) {
            'create' => $this->createShipment($context),
            'add_shipment_track', 'addTrack' => $this->addTrack($uriVariables, $context),
            'remove_shipment_track', 'removeTrack' => $this->removeTrack($uriVariables, $context),
            'shipment_add_comment' => $this->addComment($uriVariables, $context),
            default => $this->createShipmentFromRest($uriVariables, $context),
        };
    }

    /**
     * Resolve a shipment from the REST {id} URI variable or the GraphQL
     * shipmentId arg.
     */
    private function resolveShipment(array $uriVariables, array $context): \Mage_Sales_Model_Order_Shipment
    {
        $args = $context['args']['input'] ?? [];
        $shipmentId = (int) ($uriVariables['id'] ?? RequestInput::of($args)->int('shipmentId') ?? 0);
        if (!$shipmentId) {
            throw new UnprocessableEntityHttpException('Shipment ID is required');
        }

        $shipment = \Mage::getModel('sales/order_shipment')->load($shipmentId);
        if (!$shipment->getId()) {
            throw new NotFoundHttpException('Shipment not found');
        }

        $this->assertStoreAllowed($shipment->getStoreId(), $this->requireUser(), 'shipment');

        return $shipment;
    }

    /**
     * Add a tracking number to an existing shipment.
     */
    private function addTrack(array $uriVariables, array $context): Shipment
    {
        $args = $context['args']['input'] ?? [];
        $input = RequestInput::of($args);
        $trackNumber = trim($input->string('trackNumber') ?? '');
        if ($trackNumber === '') {
            throw new UnprocessableEntityHttpException('Track number is required');
        }
        $carrierCode = $input->string('carrierCode') ?? 'custom';
        $title = $input->string('title') ?? $carrierCode;

        $shipment = $this->resolveShipment($uriVariables, $context);

        $track = \Mage::getModel('sales/order_shipment_track');
        $track->setCarrierCode($carrierCode);
        $track->setTitle($title);
        $track->setTrackNumber($trackNumber);
        $shipment->addTrack($track);
        $shipment->save();

        return Shipment::fromModel($shipment->load($shipment->getId()));
    }

    /**
     * Add a comment to an existing shipment, optionally emailing the customer.
     */
    private function addComment(array $uriVariables, array $context): Shipment
    {
        $args = $context['args']['input'] ?? [];
        $input = RequestInput::of($args);
        $comment = trim($input->string('comment') ?? '');
        if ($comment === '') {
            throw new UnprocessableEntityHttpException('Comment text is required');
        }
        $notifyCustomer = $input->bool('notifyCustomer') ?? false;
        $visibleOnFront = $input->bool('visibleOnFront') ?? false;

        $shipment = $this->resolveShipment($uriVariables, $context);

        $shipment->addComment($comment, $notifyCustomer, $visibleOnFront);
        $shipment->save();
        if ($notifyCustomer) {
            $shipment->sendUpdateEmail(true, $comment);
        }

        return Shipment::fromModel(\Mage::getModel('sales/order_shipment')->load($shipment->getId()));
    }

    /**
     * Remove a tracking number from a shipment. The track must belong to the
     * referenced shipment, otherwise a 404 is returned (no cross-shipment delete).
     */
    private function removeTrack(array $uriVariables, array $context): Shipment
    {
        $args = $context['args']['input'] ?? [];
        // The {trackId} URI placeholder isn't in the operation's uriVariables map,
        // so recover it from the route params when absent.
        $trackId = (int) ($uriVariables['trackId'] ?? RequestInput::of($args)->int('trackId') ?? 0);
        if (!$trackId) {
            $request = $context['request'] ?? null;
            if ($request instanceof \Symfony\Component\HttpFoundation\Request) {
                $trackId = (int) ($request->attributes->get('_route_params')['trackId'] ?? 0);
            }
        }
        if (!$trackId) {
            throw new UnprocessableEntityHttpException('Track ID is required');
        }

        $shipment = $this->resolveShipment($uriVariables, $context);

        $track = \Mage::getModel('sales/order_shipment_track')->load($trackId);
        if (!$track->getId() || (int) $track->getParentId() !== (int) $shipment->getId()) {
            throw new NotFoundHttpException('Tracking entry not found for this shipment');
        }

        $track->delete();

        return Shipment::fromModel(\Mage::getModel('sales/order_shipment')->load($shipment->getId()));
    }

    private function createShipmentFromRest(array $uriVariables, array $context): Shipment
    {
        $orderId = (int) ($uriVariables['orderId'] ?? 0);

        $body = $this->parseRequestBody($context['request'] ?? null);
        $input = RequestInput::of($body);

        return $this->doCreateShipment(
            $orderId,
            $input->list('items'),
            $input->list('tracks') ?? [],
            $input->string('comment'),
            $input->bool('notifyCustomer') ?? false,
        );
    }

    private function createShipment(array $context): Shipment
    {
        $args = $context['args']['input'] ?? [];
        $input = RequestInput::of($args);
        $orderId = $input->int('orderId') ?? 0;

        if (!$orderId) {
            throw new UnprocessableEntityHttpException('Order ID is required');
        }

        return $this->doCreateShipment(
            $orderId,
            $input->list('items'),
            $input->list('tracks') ?? [],
            $input->string('comment'),
            $input->bool('notifyCustomer') ?? false,
        );
    }

    private function doCreateShipment(
        int $orderId,
        ?array $items,
        array $tracks,
        ?string $comment,
        bool $notifyCustomer,
    ): Shipment {
        $order = \Mage::getModel('sales/order')->load($orderId);
        if (!$order->getId()) {
            throw new NotFoundHttpException('Order not found');
        }

        $this->assertStoreAllowed($order->getStoreId(), $this->requireUser(), 'order');

        $qtys = [];
        foreach ($items ?? [] as $itemData) {
            $entry = $this->parseOrderItemEntry($itemData, $order);
            $qtys[(int) $entry['item']->getId()] = $entry['qty'];
        }

        $trackModels = [];
        foreach ($tracks as $index => $trackData) {
            if (!is_array($trackData)) {
                throw new BadRequestHttpException('Each tracking entry must be an object');
            }
            $input = RequestInput::of($trackData, "tracks[{$index}]");
            $carrierCode = $input->string('carrierCode') ?? 'custom';
            $trackNumber = $input->string('trackNumber') ?? '';
            if (empty($trackNumber)) {
                throw new UnprocessableEntityHttpException('Track number is required for each tracking entry');
            }

            $trackModels[] = \Mage::getModel('sales/order_shipment_track')
                ->setCarrierCode($carrierCode)
                ->setTitle($input->string('title') ?? $carrierCode)
                ->setTrackNumber($trackNumber);
        }

        $shipment = \Mage::getService('sales/order')->ship($order, $qtys, $trackModels, $comment, $notifyCustomer);

        return Shipment::fromModel($shipment);
    }
}
