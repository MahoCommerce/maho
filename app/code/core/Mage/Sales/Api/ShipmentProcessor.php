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
        $shipmentId = (int) ($uriVariables['id'] ?? RequestInput::of($context['args']['input'] ?? [])->int('shipmentId') ?? 0);
        if (!$shipmentId) {
            throw new \Mage_Core_Exception_Input()->addError('shipment_id', 'Shipment ID is required');
        }

        $shipment = \Mage::getService('sales/order_document')->requireShipment($shipmentId);
        $this->assertStoreAllowed($shipment->getStoreId(), $this->requireUser(), 'shipment');

        return $shipment;
    }

    /**
     * Add a tracking number to an existing shipment.
     */
    private function addTrack(array $uriVariables, array $context): Shipment
    {
        $shipment = $this->resolveShipment($uriVariables, $context);
        \Mage::getService('sales/order_document')->addTrack($shipment, $this->buildTrack(RequestInput::of($context['args']['input'] ?? [])));

        return Shipment::fromModel($shipment->load($shipment->getId()));
    }

    /**
     * Add a comment to an existing shipment, optionally emailing the customer.
     */
    private function addComment(array $uriVariables, array $context): Shipment
    {
        $input = RequestInput::of($context['args']['input'] ?? []);
        $shipment = \Mage::getService('sales/order_document')->addComment(
            $this->resolveShipment($uriVariables, $context),
            $input->string('comment') ?? '',
            $input->bool('notifyCustomer') ?? false,
            $input->bool('visibleOnFront') ?? false,
        );

        return Shipment::fromModel(\Mage::getModel('sales/order_shipment')->load($shipment->getId()));
    }

    /**
     * Remove a tracking number from a shipment. The track must belong to the
     * referenced shipment, otherwise a 404 is returned (no cross-shipment delete).
     */
    private function removeTrack(array $uriVariables, array $context): Shipment
    {
        // The {trackId} URI placeholder isn't in the operation's uriVariables map,
        // so recover it from the route params when absent.
        $request = $context['request'] ?? null;
        $trackId = (int) ($uriVariables['trackId'] ?? RequestInput::of($context['args']['input'] ?? [])->int('trackId')
            ?? ($request instanceof \Symfony\Component\HttpFoundation\Request ? $request->attributes->get('_route_params')['trackId'] ?? 0 : 0));
        if (!$trackId) {
            throw new \Mage_Core_Exception_Input()->addError('track_id', 'Track ID is required');
        }

        $shipment = \Mage::getService('sales/order_document')->removeTrack($this->resolveShipment($uriVariables, $context), $trackId);

        return Shipment::fromModel(\Mage::getModel('sales/order_shipment')->load($shipment->getId()));
    }

    private function createShipmentFromRest(array $uriVariables, array $context): Shipment
    {
        $body = $this->parseRequestBody($context['request'] ?? null);

        return $this->doCreateShipment((int) ($uriVariables['orderId'] ?? 0), RequestInput::of($body));
    }

    private function createShipment(array $context): Shipment
    {
        $input = RequestInput::of($context['args']['input'] ?? []);
        $orderId = $input->int('orderId') ?? 0;
        if (!$orderId) {
            throw new \Mage_Core_Exception_Input()->addError('order_id', 'Order ID is required');
        }

        return $this->doCreateShipment($orderId, $input);
    }

    /**
     * @param RequestInput $input items, tracks, comment and notifyCustomer
     */
    private function doCreateShipment(int $orderId, RequestInput $input): Shipment
    {
        $service = \Mage::getService('sales/order_document');
        $order = \Mage::getService('sales/order')->getById($orderId);
        $this->assertStoreAllowed($order->getStoreId(), $this->requireUser(), 'order');

        $qtys = [];
        foreach ($input->list('items') ?? [] as $itemData) {
            $entry = $this->parseOrderItemEntry($itemData);
            $qtys[$entry['orderItemId']] = $entry['qty'];
        }

        $shipment = $service->ship(
            $order,
            $qtys,
            array_map($this->buildTrack(...), $input->rows('tracks') ?? []),
            $input->string('comment'),
            $input->bool('notifyCustomer') ?? false,
        );

        return Shipment::fromModel($shipment);
    }

    /**
     * Build a track from trackNumber, carrierCode and title. The carrier defaults to custom, the title to the carrier.
     */
    private function buildTrack(RequestInput $track): \Mage_Sales_Model_Order_Shipment_Track
    {
        $carrierCode = $track->string('carrierCode') ?? \Mage_Sales_Model_Order_Shipment_Track::CUSTOM_CARRIER_CODE;

        return \Mage::getModel('sales/order_shipment_track')
            ->setCarrierCode($carrierCode)
            ->setTitle($track->string('title') ?? $carrierCode)
            ->setNumber($track->string('trackNumber') ?? '');
    }
}
