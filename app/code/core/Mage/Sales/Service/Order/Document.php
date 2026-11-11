<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

declare(strict_types=1);

use Mage_Sales_Model_Order as Order;
use Mage_Sales_Model_Order_Creditmemo as Creditmemo;
use Mage_Sales_Model_Order_Invoice as Invoice;
use Mage_Sales_Model_Order_Item as OrderItem;
use Mage_Sales_Model_Order_Shipment as Shipment;

/**
 * Creates and changes the invoices, the shipments and the credit memos of orders, for the admin, the API, the
 * importer and the payment methods.
 */
class Mage_Sales_Service_Order_Document
{
    public const CAPTURE_CASES = [Invoice::CAPTURE_ONLINE, Invoice::CAPTURE_OFFLINE, Invoice::NOT_CAPTURE];

    /** The field of a new document that tells that its email was not sent. */
    public const EMAIL_FAILED = 'email_failed';

    /** The field of a new invoice that tells that the email of its shipment was not sent. */
    public const SHIPMENT_EMAIL_FAILED = 'shipment_email_failed';

    /**
     * Load the invoice $invoiceId. With $orderId, only an invoice of that order exists.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function requireInvoice(int $invoiceId, ?int $orderId = null): Invoice
    {
        return $this->requireDocument(Mage::getModel('sales/order_invoice'), $invoiceId, $orderId, Mage::helper('sales')->__('Invoice not found'));
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function requireShipment(int $shipmentId): Shipment
    {
        return $this->requireDocument(Mage::getModel('sales/order_shipment'), $shipmentId, null, Mage::helper('sales')->__('Shipment not found'));
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function requireCreditmemo(int $creditmemoId): Creditmemo
    {
        return $this->requireDocument(Mage::getModel('sales/order_creditmemo'), $creditmemoId, null, Mage::helper('sales')->__('Credit memo not found'));
    }

    /**
     * @template T of Mage_Sales_Model_Abstract
     * @param T $document an empty document model
     * @return T
     */
    private function requireDocument(Mage_Sales_Model_Abstract $document, int $id, ?int $orderId, string $notFound): Mage_Sales_Model_Abstract
    {
        $document->load($id);
        if (!$document->getId() || ($orderId !== null && (int) $document->getOrderId() !== $orderId)) {
            throw new Mage_Core_Exception_NoSuchEntity($notFound);
        }
        return $document;
    }

    /**
     * Get the shipments of an order, with their comments preloaded. A customer or guest reader gets
     * only the shipment comments that are visible on the storefront.
     *
     * @return list<\Mage_Sales_Model_Order_Shipment>
     */
    public function getShipments(\Mage_Sales_Model_Order $order, bool $visibleCommentsOnly = false): array
    {
        $models = [];
        foreach ($order->getShipmentsCollection() as $shipment) {
            $models[] = $shipment;
        }

        $commentsByShipment = [];
        if ($visibleCommentsOnly && $models !== []) {
            $comments = \Mage::getResourceModel('sales/order_shipment_comment_collection')
                ->addFieldToFilter('parent_id', ['in' => array_map(static fn($s): int => (int) $s->getId(), $models)])
                ->addVisibleOnFrontFilter();
            foreach ($comments as $comment) {
                $commentsByShipment[(int) $comment->getParentId()][] = $comment;
            }
        }

        // getShipmentsCollection() is cached on the order, so a preload left over from an earlier
        // call would otherwise leak the filtered set into a later backend read.
        foreach ($models as $shipment) {
            if ($visibleCommentsOnly) {
                $shipment->setData('_preloaded_comments', $commentsByShipment[(int) $shipment->getId()] ?? []);
            } else {
                $shipment->unsetData('_preloaded_comments');
            }
        }

        return $models;
    }

    /**
     * Prepare an invoice of the order $order, which nothing saves yet.
     *
     * @param array<int, float|string> $qtys Order item id => qty to invoice. An empty map invoices every item.
     * @throws Mage_Core_Exception_Conflict when the order cannot be invoiced
     * @throws Mage_Core_Exception when a qty is not valid, or the invoice has no items
     */
    public function prepareInvoice(Order $order, array $qtys = []): Invoice
    {
        if (!$order->canInvoice()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('sales')->__('Order cannot be invoiced (already fully invoiced or not in an invoiceable state)'));
        }

        $qtys = $this->parseLocaleNumbers($qtys);
        $this->assertOrderItemQtys($order, $qtys);
        foreach ($qtys as $orderItemId => $qty) {
            $orderItem = $order->getItemById($orderItemId);
            // Dummy (bundle/configurable parent) items take their qty from the order, so neither check applies to them
            if (!$orderItem || $orderItem->isDummy()) {
                continue;
            }
            if (!$orderItem->getIsQtyDecimal() && fmod((float) $qty, 1.0) !== 0.0) {
                throw new Mage_Core_Exception(Mage::helper('sales')->__('Order item %s does not accept a fractional qty', $orderItemId));
            }
            if ((float) $qty > (float) $orderItem->getQtyToInvoice()) {
                throw new Mage_Core_Exception(Mage::helper('sales')->__('Qty to invoice for order item %s exceeds the qty available to invoice', $orderItemId));
            }
        }

        $invoice = $this->buildInvoice($order, $qtys);
        if (!$invoice->getTotalQty()) {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Cannot create invoice: no items to invoice'));
        }
        return $invoice;
    }

    /**
     * Invoice an order. Email the invoice when $sendEmail is true, which is $notifyCustomer when null, and put the
     * comment in the email when $notifyCustomer is true. With $ship, or when the payment of the order forces it,
     * ship the same items with the $tracks in the same transaction. A failed email is logged, and the invoice gets
     * the field EMAIL_FAILED, or SHIPMENT_EMAIL_FAILED for the email of the shipment.
     *
     * @param array<int, float|string> $qtys Order item id => qty to invoice. An empty map invoices every item.
     * @param string|null $captureCase One of self::CAPTURE_CASES.
     * @param list<\Mage_Sales_Model_Order_Shipment_Track> $tracks Tracking numbers of the shipment.
     * @throws Mage_Core_Exception_Conflict When the order cannot be invoiced, or another request holds the order lock.
     * @throws Mage_Core_Exception When a qty or the capture mode is not valid, or the invoice has no items.
     */
    public function invoice(
        Order $order,
        array $qtys = [],
        ?string $captureCase = null,
        ?string $comment = null,
        bool $notifyCustomer = false,
        bool $visibleOnFront = false,
        ?bool $sendEmail = null,
        bool $ship = false,
        array $tracks = [],
    ): Invoice {
        if ($captureCase !== null && !in_array($captureCase, self::CAPTURE_CASES, true)) {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Invalid capture mode; expected one of: %s', implode(', ', self::CAPTURE_CASES)));
        }
        $sendEmail ??= $notifyCustomer;

        [$invoice, $shipment] = $this->withOrderLock((int) $order->getId(), function () use ($order, $qtys, $captureCase, $comment, $notifyCustomer, $visibleOnFront, $sendEmail, $ship, $tracks): array {
            // Re-read under the lock so canInvoice() reflects any invoice another
            // request created while we waited, otherwise both would register one.
            $order->load((int) $order->getId());
            $invoice = $this->prepareInvoice($order, $qtys);

            if ($captureCase === Invoice::CAPTURE_ONLINE && !$invoice->canCapture()) {
                throw new Mage_Core_Exception(Mage::helper('sales')->__('The order\'s payment method does not support online capture'));
            }
            if ($captureCase !== null) {
                $invoice->setRequestedCaptureCase($captureCase);
            }
            if ($comment) {
                $invoice->addComment($comment, $notifyCustomer, $visibleOnFront);
            }

            $invoice->register();
            if ($sendEmail) {
                $invoice->setEmailSent();
            }
            $invoice->getOrder()->setCustomerNoteNotify($sendEmail)->setIsInProcess();
            $transaction = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());

            $shipment = null;
            if ($ship || $order->getForcedDoShipmentWithInvoice()) {
                $shipment = $this->buildShipment($invoice->getOrder(), $qtys);
                if ($shipment->getTotalQty()) {
                    $shipment->register();
                    foreach ($tracks as $track) {
                        $shipment->addTrack($track);
                    }
                    $transaction->addObject($shipment->setEmailSent($invoice->getEmailSent()));
                } else {
                    $shipment = null;
                }
            }
            $transaction->save();

            return [$invoice, $shipment];
        });

        $this->withCurrentDocument('current_invoice', $invoice, function () use ($invoice, $shipment, $sendEmail, $notifyCustomer, $comment): void {
            $this->sendSafely(fn() => $invoice->sendEmail($sendEmail, $notifyCustomer ? (string) $comment : ''), $invoice, self::EMAIL_FAILED);
            if ($shipment) {
                $this->sendSafely(fn() => $shipment->sendEmail($sendEmail), $invoice, self::SHIPMENT_EMAIL_FAILED);
            }
        });

        return $invoice;
    }

    /**
     * Capture an invoice.
     *
     * @throws \Mage_Core_Exception_Conflict When the invoice cannot be captured, or another request holds the order lock.
     */
    public function captureInvoice(Invoice $invoice): Invoice
    {
        return $this->changeDocument($invoice, 'capture', Mage::helper('sales')->__('The invoice cannot be captured in its current state'));
    }

    /**
     * Void an invoice.
     *
     * @throws \Mage_Core_Exception_Conflict When the invoice cannot be voided, or another request holds the order lock.
     */
    public function voidInvoice(Invoice $invoice): Invoice
    {
        return $this->changeDocument($invoice, 'void', Mage::helper('sales')->__('The invoice cannot be voided in its current state'));
    }

    /**
     * Cancel an invoice.
     *
     * @throws \Mage_Core_Exception_Conflict When the invoice cannot be canceled, or another request holds the order lock.
     */
    public function cancelInvoice(Invoice $invoice): Invoice
    {
        return $this->changeDocument($invoice, 'cancel', Mage::helper('sales')->__('The invoice cannot be canceled in its current state'));
    }

    /**
     * Prepare a shipment of the order $order with the tracking numbers $tracks, which nothing saves yet.
     *
     * @param array<int, float|string> $qtys Order item id => qty to ship. An empty map ships every item.
     * @param list<\Mage_Sales_Model_Order_Shipment_Track> $tracks
     * @throws Mage_Core_Exception_Conflict when the order cannot be shipped, or only with its invoice
     * @throws Mage_Core_Exception when the shipment has no items
     */
    public function prepareShipment(Order $order, array $qtys = [], array $tracks = []): Shipment
    {
        if ($order->getForcedDoShipmentWithInvoice()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('sales')->__('Cannot do shipment for the order separately from invoice.'));
        }
        if (!$order->canShip()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('sales')->__('Order cannot be shipped (already fully shipped or not in a shippable state)'));
        }
        $this->assertOrderItemQtys($order, $this->parseLocaleNumbers($qtys));

        $shipment = $this->buildShipment($order, $qtys);
        if (!$shipment->getTotalQty()) {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Cannot create shipment: no items to ship'));
        }
        foreach ($tracks as $track) {
            $shipment->addTrack($track);
        }
        return $shipment;
    }

    /**
     * Ship an order. Email the shipment when $sendEmail is true, which is $notifyCustomer when null, and put the
     * comment in the email when $notifyCustomer is true. With $packages, ask the carrier for the shipping labels.
     *
     * @param array<int, float|string> $qtys Order item id => qty to ship. An empty map ships every item.
     * @param list<\Mage_Sales_Model_Order_Shipment_Track> $tracks Tracking numbers to add to the shipment.
     * @param array<int, array<string, mixed>>|null $packages The packages of the admin packaging form.
     * @throws \Mage_Core_Exception_Conflict When the order cannot be shipped, or another request holds the order lock.
     * @throws \Mage_Core_Exception When the shipment has no items, a tracking number is empty, or the carrier refuses a label.
     */
    public function ship(
        Order $order,
        array $qtys = [],
        array $tracks = [],
        ?string $comment = null,
        bool $notifyCustomer = false,
        bool $visibleOnFront = false,
        ?bool $sendEmail = null,
        ?array $packages = null,
    ): Shipment {
        $sendEmail ??= $notifyCustomer;
        $shipment = $this->withOrderLock((int) $order->getId(), function () use ($order, $qtys, $tracks, $comment, $notifyCustomer, $visibleOnFront, $sendEmail, $packages): Shipment {
            // Re-read under the lock so canShip() reflects any shipment another
            // request created while we waited, otherwise both would register one
            // and decrement inventory twice.
            $order->load((int) $order->getId());
            $shipment = $this->prepareShipment($order, $qtys, $tracks);
            if ($comment) {
                $shipment->addComment($comment, $notifyCustomer, $visibleOnFront);
            }

            $shipment->register();
            if ($sendEmail) {
                $shipment->setEmailSent();
            }
            if ($packages !== null) {
                $this->requestShippingLabel($shipment, $packages);
            }

            // Without a change on the order itself its save() short-circuits, so the
            // qty_shipped that register() put on the items never persists.
            $shipment->getOrder()->setCustomerNoteNotify($sendEmail)->setIsInProcess();

            Mage::getModel('core/resource_transaction')
                ->addObject($shipment)
                ->addObject($shipment->getOrder())
                ->save();

            return $shipment;
        });

        $this->sendSafely(fn() => $shipment->sendEmail($sendEmail, $notifyCustomer ? (string) $comment : ''), $shipment, self::EMAIL_FAILED);

        return $shipment;
    }

    /**
     * Cancel the shipment $shipment: its items are not shipped any more, and the order is in processing again.
     *
     * @throws \Mage_Core_Exception_Conflict When the shipment is canceled, or another request holds the order lock.
     */
    public function cancelShipment(Shipment $shipment): Shipment
    {
        return $this->changeDocument($shipment, 'cancel', Mage::helper('sales')->__('Cannot cancel the shipment.'));
    }

    /**
     * Add the tracking number $track to the shipment $shipment. The track checks its number and its carrier.
     *
     * @throws Mage_Core_Exception_Input when the number or the carrier is empty
     */
    public function addTrack(Shipment $shipment, Mage_Sales_Model_Order_Shipment_Track $track): Shipment
    {
        $shipment->addTrack($track)->save();
        return $shipment;
    }

    /**
     * Remove the tracking number $trackId from the shipment $shipment.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the shipment has no such track
     */
    public function removeTrack(Shipment $shipment, int $trackId): Shipment
    {
        $track = Mage::getModel('sales/order_shipment_track')->load($trackId);
        if (!$track->getId() || $track->getParentId() !== (int) $shipment->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('sales')->__('Tracking entry not found for this shipment'));
        }
        $track->delete();
        return $shipment;
    }

    /**
     * Ask the carrier of the order of the saved shipment $shipment for the labels of $packages, and save the shipment
     * with the labels and their tracking numbers. Return false when the carrier makes no label.
     *
     * @param array<int, array<string, mixed>> $packages
     * @throws Mage_Core_Exception when the carrier returns an error
     */
    public function createShippingLabel(Shipment $shipment, array $packages): bool
    {
        if (!$this->requestShippingLabel($shipment, $packages)) {
            return false;
        }
        $shipment->save();

        return true;
    }

    /**
     * Ask the carrier of the order of $shipment for the labels of $packages. Put the labels and their tracking numbers
     * on the shipment, which the caller saves. Return false when the carrier makes no label.
     *
     * @param array<int, array<string, mixed>> $packages
     * @throws Mage_Core_Exception when the carrier returns an error
     */
    private function requestShippingLabel(Shipment $shipment, array $packages): bool
    {
        $carrier = $shipment->getOrder()->getShippingCarrier();
        if (!$carrier->isShippingLabelsAvailable()) {
            return false;
        }
        $shipment->setPackages($packages);
        $response = Mage::getModel('shipping/shipping')->requestToShipment($shipment);
        if ($response->hasErrors()) {
            Mage::throwException($response->getErrors());
        }

        $labels = [];
        $trackingNumbers = [];
        foreach ($response->hasInfo() ? $response->getInfo() : [] as $info) {
            if (!empty($info['tracking_number']) && !empty($info['label_content'])) {
                $labels[sprintf('label_%s_package_%d.pdf', $shipment->getIncrementId(), count($labels) + 1)] = $info['label_content'];
                $trackingNumbers[] = (string) $info['tracking_number'];
            }
        }
        if ($labels === []) {
            return false;
        }

        try {
            $shipment->setShippingLabel(count($labels) === 1 ? reset($labels) : $this->zipShippingLabels($labels, 'shipping_label_'));
        } catch (\RuntimeException $e) {
            // The shipment saves without its labels, as before: the admin can create them again
            Mage::logException($e);
            return false;
        }
        foreach ($trackingNumbers as $trackingNumber) {
            $shipment->addTrack(Mage::getModel('sales/order_shipment_track')
                ->setNumber($trackingNumber)
                ->setCarrierCode($carrier->getCarrierCode())
                ->setTitle((string) Mage::getStoreConfig('carriers/' . $carrier->getCarrierCode() . '/title', $shipment->getStoreId())));
        }
        return true;
    }

    /**
     * Return a ZIP archive of $files, which maps a file name to its content.
     *
     * @param array<string, string> $files
     * @throws \RuntimeException when the archive cannot be written
     */
    public function zipShippingLabels(array $files, string $tempFilePrefix = 'shipping_labels_'): string
    {
        $tempFile = tempnam(Mage::getBaseDir('var') . DS . 'tmp', $tempFilePrefix);
        if ($tempFile === false) {
            throw new \RuntimeException('Cannot create temporary file for shipping labels archive.');
        }

        try {
            $zip = new ZipArchive();
            $result = $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            if ($result !== true) {
                throw new \RuntimeException(sprintf('Cannot create ZIP archive for shipping labels. Error code: %s', $result));
            }
            foreach ($files as $filename => $content) {
                $zip->addFromString($filename, $content);
            }
            $zip->close();

            $zipContent = file_get_contents($tempFile);
            if ($zipContent === false) {
                throw new \RuntimeException('Cannot read created ZIP archive.');
            }
            return $zipContent;
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Prepare a credit memo of the order $order, or of its invoice $invoice, which nothing saves yet. Return to stock
     * the items in $backToStockItemIds and the children of an item in it. With null, return every item when the store
     * returns refunded items to stock.
     *
     * @param array<string, mixed> $data Credit memo data for buildCreditmemo(): qtys, shipping_amount, adjustment_positive, adjustment_negative
     * @param array<int, bool>|null $backToStockItemIds Order item id => true.
     * @throws Mage_Core_Exception_Conflict when the state of the order does not allow a refund
     * @throws Mage_Core_Exception when the shipping amount is negative
     */
    public function prepareCreditmemo(Order $order, array $data, ?array $backToStockItemIds = null, ?Invoice $invoice = null): Creditmemo
    {
        if (!$order->canCreditmemo()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('sales')->__('Order cannot be refunded (already fully refunded or not in a refundable state)'));
        }
        if (isset($data['shipping_amount']) && (float) $data['shipping_amount'] < 0) {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Shipping amount must be >= 0'));
        }
        $this->assertOrderItemQtys($order, $this->parseLocaleNumbers($data['qtys'] ?? []));

        $creditmemo = $this->buildCreditmemo($order, $data, $invoice);

        $autoReturn = Mage::helper('cataloginventory')->isAutoReturnEnabled();
        foreach ($creditmemo->getAllItems() as $creditmemoItem) {
            $orderItem = $creditmemoItem->getOrderItem();
            $creditmemoItem->setBackToStock($backToStockItemIds === null
                ? $autoReturn
                : isset($backToStockItemIds[(int) $orderItem->getId()]) || isset($backToStockItemIds[(int) $orderItem->getParentItemId()]));
        }

        return $creditmemo;
    }

    /**
     * Create a credit memo for an order, or for its invoice $invoice. Email it when $sendEmail is true, and put the
     * comment in the email when $notifyCustomer is true.
     *
     * Without qtys in $data, refund every item that the order can refund. With qtys, refund only the qtys above 0: an
     * empty map or a map of zeros refunds no item, and then no shipping unless $data gives shipping_amount. A memo
     * that refunds nothing is not a refund. The credit memo model refuses a refund above the refundable balance of
     * the order.
     *
     * The order and the invoice are loaded again under the order lock, and the credit memo is prepared from them. So
     * two requests at the same time cannot both refund the same amount.
     *
     * @param array<string, mixed> $data Credit memo data for prepareCreditmemo().
     * @param array<int, bool>|null $backToStockItemIds Order item ids to return to stock, see prepareCreditmemo(). An
     *                                                    empty array returns no item to stock. Only null follows the
     *                                                    setting of the store, which an empty array did before 27.1.
     * @param (callable(Creditmemo): void)|null $beforeRegister Gets the prepared credit memo under the lock, before
     *                                                          the checks of the total. A transport uses it to
     *                                                          dispatch its event, so that an observer can change
     *                                                          the credit memo.
     * @throws \Mage_Core_Exception_Conflict When the state of the order does not allow a refund, or another request holds the order lock.
     * @throws \Mage_Core_Exception_Input When $invoice is not a saved invoice of the order, on the field invoice_id.
     * @throws \Mage_Core_Exception When the refund breaks a money rule.
     * @throws \Exception When the refund itself fails, for example at the payment gateway.
     */
    public function refund(
        Order $order,
        array $data,
        ?string $comment = null,
        bool $offlineRefund = true,
        ?array $backToStockItemIds = null,
        bool $notifyCustomer = false,
        bool $visibleOnFront = false,
        bool $sendEmail = false,
        ?Invoice $invoice = null,
        ?callable $beforeRegister = null,
    ): Creditmemo {
        if (isset($data['qtys'])) {
            $data['qtys'] = array_filter($this->parseLocaleNumbers($data['qtys']), fn($qty) => $qty !== 0.0);
            if ($data['qtys'] === []) {
                // prepareCreditmemo() refunds every item for an empty map, so give it a key that names no order item
                $data['qtys'] = [0 => 0.0];
                // Without an amount, the credit memo refunds the shipping that the order did not refund yet
                $data['shipping_amount'] ??= 0.0;
            }
        }
        $creditmemo = $this->withOrderLock((int) $order->getId(), function () use ($order, $data, $comment, $offlineRefund, $backToStockItemIds, $notifyCustomer, $visibleOnFront, $sendEmail, $invoice, $beforeRegister): Creditmemo {
            // Re-read under the lock so canCreditmemo() sees the live
            // total_refunded, not a value another request changed while we waited.
            $order->load((int) $order->getId());
            if ($invoice) {
                if (!$invoice->getId() || (int) $invoice->getOrderId() !== (int) $order->getId()) {
                    throw new Mage_Core_Exception_Input()->addError('invoice_id', Mage::helper('sales')->__('The invoice does not belong to this order'));
                }
                $invoice->load((int) $invoice->getId())->setOrder($order);
            }
            $creditmemo = $this->prepareCreditmemo($order, $data, $backToStockItemIds, $invoice);
            if ($beforeRegister !== null) {
                $beforeRegister($creditmemo);
            }
            return $this->registerCreditmemo($creditmemo, $comment, $offlineRefund, $notifyCustomer, $visibleOnFront, $sendEmail);
        });
        $this->withCurrentDocument(
            'current_creditmemo',
            $creditmemo,
            fn() => $this->sendSafely(fn() => $creditmemo->sendEmail($sendEmail, $notifyCustomer ? (string) $comment : ''), $creditmemo, self::EMAIL_FAILED),
        );

        return $creditmemo;
    }

    private function registerCreditmemo(
        Creditmemo $creditmemo,
        ?string $comment,
        bool $offlineRefund,
        bool $notifyCustomer,
        bool $visibleOnFront,
        bool $sendEmail,
    ): Creditmemo {
        $order = $creditmemo->getOrder();
        // The payment refunds online only against the invoice of the capture. Without an invoice, the gateway refunds
        // nothing, but the order counts the credit memo as refunded online.
        if (!$offlineRefund && !$creditmemo->getInvoice()) {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Cannot refund online: the credit memo has no invoice. Refund it offline.'));
        }
        // A free order opts out through the flag.
        if ((float) $creditmemo->getBaseGrandTotal() <= 0 && !$creditmemo->getAllowZeroGrandTotal()) {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Credit memo total must be greater than zero'));
        }

        // Add the comment before register/save so it is persisted with the
        // credit memo. A separate save after the commit would re-trigger the
        // post-save observers on an already-refunded memo.
        if ($comment) {
            $creditmemo->addComment($comment, $notifyCustomer, $visibleOnFront);
        }
        $creditmemo->setOfflineRequested($offlineRefund);
        $creditmemo->register();
        if ($sendEmail) {
            $creditmemo->setEmailSent();
        }
        $order->setCustomerNoteNotify($sendEmail);

        $transaction = Mage::getModel('core/resource_transaction')
            ->addObject($creditmemo)
            ->addObject($order);
        if ($creditmemo->getInvoice()) {
            $transaction->addObject($creditmemo->getInvoice());
        }
        $transaction->save();

        return $creditmemo;
    }

    /**
     * Cancel the credit memo $creditmemo.
     *
     * @throws \Mage_Core_Exception_Conflict When the credit memo is not open, or another request holds the order lock.
     */
    public function cancelCreditmemo(Creditmemo $creditmemo): Creditmemo
    {
        return $this->changeDocument($creditmemo, 'cancel', Mage::helper('sales')->__('The credit memo cannot be canceled in its current state'));
    }

    /**
     * Void the credit memo $creditmemo. The credit memo of core cannot be voided, so only a model that an extension
     * rewrites accepts this.
     *
     * @throws \Mage_Core_Exception_Conflict When the credit memo cannot be voided, or another request holds the order lock.
     */
    public function voidCreditmemo(Creditmemo $creditmemo): Creditmemo
    {
        return $this->changeDocument($creditmemo, 'void', Mage::helper('sales')->__('The credit memo cannot be voided in its current state'));
    }

    /**
     * Run $action on $document when its can method allows it, and save the document with its order. The action
     * changes the totals or the state of the order, so it holds the order lock.
     *
     * @template T of Invoice|Shipment|Creditmemo
     * @param T $document
     * @param 'capture'|'void'|'cancel' $action
     * @return T
     */
    private function changeDocument(Invoice|Shipment|Creditmemo $document, string $action, string $refusal): Invoice|Shipment|Creditmemo
    {
        return $this->withOrderLock((int) $document->getOrderId(), function () use ($document, $action, $refusal) {
            // Re-read under the lock so the can*() checks reflect the live state.
            $document->load((int) $document->getId());
            if (!$document->{'can' . ucfirst($action)}()) {
                throw new Mage_Core_Exception_Conflict($refusal);
            }

            $document->{$action}();
            $order = $document->getOrder();
            $transaction = Mage::getModel('core/resource_transaction')
                ->addObject($document)
                ->addObject($order);
            if ($document instanceof Creditmemo) {
                if ($document->getInvoice()) {
                    $transaction->addObject($document->getInvoice());
                }
            } else {
                $order->setIsInProcess();
            }
            $transaction->save();

            return $document;
        });
    }

    /**
     * Add the comment $comment to the invoice, the shipment or the credit memo $document. Save the invoice or the
     * shipment, or only the comments of the credit memo. Email the comment to the customer when $notifyCustomer is true.
     *
     * @template T of Invoice|Shipment|Creditmemo
     * @param T $document
     * @return T
     * @throws Mage_Core_Exception when the comment is empty
     */
    public function addComment(
        Invoice|Shipment|Creditmemo $document,
        string $comment,
        bool $notifyCustomer = false,
        bool $visibleOnFront = false,
    ): Invoice|Shipment|Creditmemo {
        $comment = trim($comment);
        if ($comment === '') {
            throw new Mage_Core_Exception(Mage::helper('sales')->__('Comment text is required'));
        }

        $document->addComment($comment, $notifyCustomer, $visibleOnFront);
        if ($document instanceof Creditmemo) {
            // Save the comments only: a save of the credit memo itself would register it again
            $document->getCommentsCollection()->save();
        } else {
            $document->save();
        }
        $this->sendSafely(fn() => $document->sendUpdateEmail($notifyCustomer, $comment));

        return $document;
    }

    /**
     * Build an invoice of the order $order and add it to the invoices of the order, which nothing saves yet. Unlike
     * prepareInvoice(), do not check the state of the order or the qtys.
     *
     * A store that rewrites the model sales/service_order builds the invoice through that model, see
     * getRewrittenBuilder().
     *
     * @param array<int|string, mixed> $qtys Order item id => qty to invoice. An empty map invoices every item.
     */
    public function buildInvoice(Order $order, array $qtys = [], ?Mage_Sales_Model_Convert_Order $convertor = null): Invoice
    {
        $builder = $this->getRewrittenBuilder($order, $convertor);

        return $builder
            ? $builder->prepareInvoice($qtys)
            : $this->buildInvoiceWith($order, $qtys, $convertor ?? $this->getConvertor(), $this->canInvoiceItem(...));
    }

    /**
     * Build the invoice of buildInvoice() with the item check $canInvoiceItem. The deprecated model
     * sales/service_order calls it with its own check, so that a rewrite of the model keeps its effect.
     *
     * @param array<int|string, mixed> $qtys
     * @param callable(OrderItem): bool $canInvoiceItem
     */
    public function buildInvoiceWith(Order $order, array $qtys, Mage_Sales_Model_Convert_Order $convertor, callable $canInvoiceItem): Invoice
    {
        $qtys = $this->parseLocaleNumbers($qtys);
        $invoice = $convertor->toInvoice($order);
        $totalQty = 0;
        foreach ($order->getAllItems() as $orderItem) {
            if (!$canInvoiceItem($orderItem)) {
                continue;
            }
            $item = $convertor->itemToInvoiceItem($orderItem);
            if ($orderItem->isDummy()) {
                $qty = $orderItem->getQtyOrdered() ?: 1;
            } elseif (isset($qtys[$orderItem->getId()])) {
                $qty = (float) $qtys[$orderItem->getId()];
            } elseif (!count($qtys)) {
                $qty = $orderItem->getQtyToInvoice();
            } else {
                $qty = 0;
            }

            $totalQty += $qty;
            $item->setQty($qty);
            $invoice->addItem($item);
        }

        $invoice->setTotalQty($totalQty);
        $invoice->collectTotals();
        $order->getInvoiceCollection()->addItem($invoice);

        return $invoice;
    }

    /**
     * Build a shipment of the order $order, which nothing saves yet. Unlike prepareShipment(), do not check the state
     * of the order.
     *
     * A store that rewrites the model sales/service_order builds the shipment through that model, see
     * getRewrittenBuilder().
     *
     * @param array<int|string, mixed> $qtys Order item id => qty to ship. An empty map ships every item.
     */
    public function buildShipment(Order $order, array $qtys = [], ?Mage_Sales_Model_Convert_Order $convertor = null): Shipment
    {
        $builder = $this->getRewrittenBuilder($order, $convertor);

        return $builder
            ? $builder->prepareShipment($qtys)
            : $this->buildShipmentWith($order, $qtys, $convertor ?? $this->getConvertor(), $this->canShipItem(...));
    }

    /**
     * Build the shipment of buildShipment() with the item check $canShipItem. See buildInvoiceWith().
     *
     * @param array<int|string, mixed> $qtys
     * @param callable(OrderItem, array<int|string, mixed>): bool $canShipItem
     */
    public function buildShipmentWith(Order $order, array $qtys, Mage_Sales_Model_Convert_Order $convertor, callable $canShipItem): Shipment
    {
        $qtys = $this->parseLocaleNumbers($qtys);
        $totalQty = 0;
        $shipment = $convertor->toShipment($order);
        foreach ($order->getAllItems() as $orderItem) {
            if (!$canShipItem($orderItem, $qtys)) {
                continue;
            }

            $item = $convertor->itemToShipmentItem($orderItem);

            if ($orderItem->isDummy(true)) {
                $qty = 0;
                if (isset($qtys[$orderItem->getParentItemId()])) {
                    $productOptions = $orderItem->getProductOptions();
                    if (isset($productOptions['bundle_selection_attributes'])) {
                        $bundleSelectionAttributes = Mage::helper('core/string')->unserialize($productOptions['bundle_selection_attributes']);

                        if ($bundleSelectionAttributes) {
                            $qty = $bundleSelectionAttributes['qty'] * $qtys[$orderItem->getParentItemId()];
                            $qty = min($qty, $orderItem->getSimpleQtyToShip());

                            $item->setQty($qty);
                            $shipment->addItem($item);
                            continue;
                        }
                        $qty = 1;
                    }
                } else {
                    $qty = 1;
                }
            } elseif (isset($qtys[$orderItem->getId()])) {
                $qty = min($qtys[$orderItem->getId()], $orderItem->getQtyToShip());
            } elseif (empty($qtys)) {
                $qty = $orderItem->getQtyToShip();
            } else {
                continue;
            }
            $totalQty += $qty;
            $item->setQty($qty);
            $shipment->addItem($item);
        }

        $shipment->setTotalQty($totalQty);
        return $shipment;
    }

    /**
     * Build a credit memo of the order $order, or of its invoice $invoice, which nothing saves yet. Unlike
     * prepareCreditmemo(), do not check the state of the order or the shipping amount. Without a shipping amount in
     * $data, the credit memo of an invoice refunds the shipping of that invoice that is not refunded yet.
     *
     * A store that rewrites the model sales/service_order builds the credit memo through that model, see
     * getRewrittenBuilder().
     *
     * @param array<string, mixed> $data qtys (order item id => qty), shipping_amount, adjustment_positive, adjustment_negative
     */
    public function buildCreditmemo(
        Order $order,
        array $data = [],
        ?Invoice $invoice = null,
        ?Mage_Sales_Model_Convert_Order $convertor = null,
    ): Creditmemo {
        $builder = $this->getRewrittenBuilder($order, $convertor);
        if ($builder) {
            return $invoice ? $builder->prepareInvoiceCreditmemo($invoice, $data) : $builder->prepareCreditmemo($data);
        }

        return $this->buildCreditmemoWith(
            $order,
            $data,
            $invoice,
            $convertor ?? $this->getConvertor(),
            $this->canRefundItem(...),
            $this->setCreditmemoAmounts(...),
        );
    }

    /**
     * Build the credit memo of buildCreditmemo() with the item check $canRefundItem, and set its amounts with
     * $setAmounts. See buildInvoiceWith().
     *
     * @param array<string, mixed> $data
     * @param callable(OrderItem, array<int|string, mixed>, array<int|string, float>): bool $canRefundItem
     * @param callable(Creditmemo, array<string, mixed>): void $setAmounts
     */
    public function buildCreditmemoWith(
        Order $order,
        array $data,
        ?Invoice $invoice,
        Mage_Sales_Model_Convert_Order $convertor,
        callable $canRefundItem,
        callable $setAmounts,
    ): Creditmemo {
        $qtys = $this->parseLocaleNumbers($data['qtys'] ?? []);
        $creditmemo = $convertor->toCreditmemo($order);
        if ($invoice) {
            $creditmemo->setInvoice($invoice);
            $this->addInvoiceCreditmemoItems($creditmemo, $invoice, $qtys, $convertor, $canRefundItem);
        } else {
            $this->addOrderCreditmemoItems($creditmemo, $order, $qtys, $convertor, $canRefundItem);
        }

        $setAmounts($creditmemo, $data);
        if ($invoice && !isset($data['shipping_amount'])) {
            $invoiceOrder = $invoice->getOrder();
            if ((float) $invoice->getBaseShippingAmount() == 0) {
                $creditmemo->setBaseShippingAmount(0);
            } elseif (!Mage::getSingleton('tax/config')->displaySalesShippingInclTax($invoiceOrder->getStoreId())) {
                // Incl. tax, the shipping total offers the shipping left. It counts the shipping hidden tax refunded.
                $baseAllowedAmount = $invoiceOrder->getBaseShippingAmount() - $invoiceOrder->getBaseShippingRefunded();
                $creditmemo->setBaseShippingAmount(min($baseAllowedAmount, $invoice->getBaseShippingAmount()));
            }
        }

        $creditmemo->collectTotals();
        return $creditmemo;
    }

    /**
     * Set the shipping amount and the adjustments of $data on the credit memo $creditmemo.
     *
     * @param array<string, mixed> $data shipping_amount, adjustment_positive, adjustment_negative
     */
    public function setCreditmemoAmounts(Creditmemo $creditmemo, array $data): void
    {
        $data = $this->parseLocaleNumbers($data);
        if (isset($data['shipping_amount'])) {
            $creditmemo->setBaseShippingAmount((float) $data['shipping_amount']);
        }
        if (isset($data['adjustment_positive'])) {
            $creditmemo->setAdjustmentPositive($data['adjustment_positive']);
        }
        if (isset($data['adjustment_negative'])) {
            $creditmemo->setAdjustmentNegative($data['adjustment_negative']);
        }
    }

    /**
     * @param array<int|string, mixed> $qtys
     * @param callable(OrderItem, array<int|string, mixed>, array<int|string, float>): bool $canRefundItem
     */
    private function addOrderCreditmemoItems(Creditmemo $creditmemo, Order $order, array $qtys, Mage_Sales_Model_Convert_Order $convertor, callable $canRefundItem): void
    {
        $totalQty = 0;
        foreach ($order->getAllItems() as $orderItem) {
            if (!$canRefundItem($orderItem, $qtys, [])) {
                continue;
            }

            $item = $convertor->itemToCreditmemoItem($orderItem);
            if ($orderItem->isDummy()) {
                $qty = 1;
                $orderItem->setLockedDoShip();
            } elseif (isset($qtys[$orderItem->getId()])) {
                $qty = (float) $qtys[$orderItem->getId()];
            } elseif (!count($qtys)) {
                $qty = $orderItem->getQtyToRefund();
            } else {
                continue;
            }
            $totalQty += $qty;
            $item->setQty($qty);
            $creditmemo->addItem($item);
        }
        $creditmemo->setTotalQty($totalQty);
    }

    /**
     * Refund at most the qty of each item of $invoice that the other credit memos of the invoice do not refund yet.
     *
     * @param array<int|string, mixed> $qtys
     * @param callable(OrderItem, array<int|string, mixed>, array<int|string, float>): bool $canRefundItem
     */
    private function addInvoiceCreditmemoItems(Creditmemo $creditmemo, Invoice $invoice, array $qtys, Mage_Sales_Model_Convert_Order $convertor, callable $canRefundItem): void
    {
        $invoiceQtysRefunded = [];
        foreach ($invoice->getOrder()->getCreditmemosCollection() as $createdCreditmemo) {
            if ($createdCreditmemo->getState() != Creditmemo::STATE_CANCELED
                && $createdCreditmemo->getInvoiceId() == $invoice->getId()
            ) {
                foreach ($createdCreditmemo->getAllItems() as $createdCreditmemoItem) {
                    $orderItemId = $createdCreditmemoItem->getOrderItem()->getId();
                    $invoiceQtysRefunded[$orderItemId] = ($invoiceQtysRefunded[$orderItemId] ?? 0) + $createdCreditmemoItem->getQty();
                }
            }
        }

        $invoiceQtysRefundLimits = [];
        foreach ($invoice->getAllItems() as $invoiceItem) {
            $orderItemId = $invoiceItem->getOrderItem()->getId();
            $invoiceQtysRefundLimits[$orderItemId] = $invoiceItem->getQty() - ($invoiceQtysRefunded[$orderItemId] ?? 0);
        }

        $totalQty = 0;
        foreach ($invoice->getAllItems() as $invoiceItem) {
            $orderItem = $invoiceItem->getOrderItem();

            if (!$canRefundItem($orderItem, $qtys, $invoiceQtysRefundLimits)) {
                continue;
            }

            $item = $convertor->itemToCreditmemoItem($orderItem);
            if ($orderItem->isDummy()) {
                $qty = 1;
            } else {
                if (isset($qtys[$orderItem->getId()])) {
                    $qty = (float) $qtys[$orderItem->getId()];
                } elseif (!count($qtys)) {
                    $qty = $orderItem->getQtyToRefund();
                } else {
                    continue;
                }
                if (isset($invoiceQtysRefundLimits[$orderItem->getId()])) {
                    $qty = min($qty, $invoiceQtysRefundLimits[$orderItem->getId()]);
                }
            }
            $qty = min($qty, $invoiceItem->getQty());
            $totalQty += $qty;
            $item->setQty($qty);
            $creditmemo->addItem($item);
        }
        $creditmemo->setTotalQty($totalQty);
    }

    /**
     * Tell if the order item $item goes on an invoice. A dummy item goes on it with its children, or with its parent.
     *
     * @param array<int|string, mixed> $qtys Order item id => qty to invoice. An empty map invoices every item.
     */
    public function canInvoiceItem(OrderItem $item, array $qtys = []): bool
    {
        if ($item->getLockedDoInvoice()) {
            return false;
        }
        if (!$item->isDummy()) {
            return $item->getQtyToInvoice() > 0;
        }
        $qtys = $this->parseLocaleNumbers($qtys);
        if ($item->getHasChildren()) {
            foreach ($item->getChildrenItems() as $child) {
                if (empty($qtys) ? $child->getQtyToInvoice() > 0 : ($qtys[$child->getId()] ?? 0) > 0) {
                    return true;
                }
            }
            return false;
        }
        if ($parent = $item->getParentItem()) {
            return empty($qtys) ? $parent->getQtyToInvoice() > 0 : ($qtys[$parent->getId()] ?? 0) > 0;
        }
        return false;
    }

    /**
     * Tell if the order item $item goes on a shipment. A dummy item goes on it with its children, or with its parent.
     *
     * @param array<int|string, mixed> $qtys Order item id => qty to ship. An empty map ships every item.
     */
    public function canShipItem(OrderItem $item, array $qtys = []): bool
    {
        if ($item->getIsVirtual() || $item->getLockedDoShip()) {
            return false;
        }
        if (!$item->isDummy(true)) {
            return $item->getQtyToShip() > 0;
        }
        $qtys = $this->parseLocaleNumbers($qtys);
        if ($item->getHasChildren()) {
            if ($item->isShipSeparately()) {
                return true;
            }
            foreach ($item->getChildrenItems() as $child) {
                if ($child->getIsVirtual()) {
                    continue;
                }
                if (empty($qtys) ? $child->getQtyToShip() > 0 : ($qtys[$child->getId()] ?? 0) > 0) {
                    return true;
                }
            }
            return false;
        }
        if ($parent = $item->getParentItem()) {
            return empty($qtys) ? $parent->getQtyToShip() > 0 : ($qtys[$parent->getId()] ?? 0) > 0;
        }
        return false;
    }

    /**
     * Tell if the order item $item goes on a credit memo. A dummy item goes on it with its children, or with its
     * parent.
     *
     * @param array<int|string, mixed> $qtys Order item id => qty to refund. An empty map refunds every item.
     * @param array<int|string, float> $invoiceQtysRefundLimits Order item id => qty of the invoice that is not refunded yet.
     */
    public function canRefundItem(OrderItem $item, array $qtys = [], array $invoiceQtysRefundLimits = []): bool
    {
        if (!$item->isDummy()) {
            return $this->hasQtyToRefund($item, $invoiceQtysRefundLimits);
        }
        $qtys = $this->parseLocaleNumbers($qtys);
        if ($item->getHasChildren()) {
            foreach ($item->getChildrenItems() as $child) {
                if (empty($qtys) ? $this->hasQtyToRefund($child, $invoiceQtysRefundLimits) : ($qtys[$child->getId()] ?? 0) > 0) {
                    return true;
                }
            }
            return false;
        }
        if ($parent = $item->getParentItem()) {
            return empty($qtys) ? $this->hasQtyToRefund($parent, $invoiceQtysRefundLimits) : ($qtys[$parent->getId()] ?? 0) > 0;
        }
        return false;
    }

    /**
     * Tell if the order item $item has a qty to refund, without a look at its parent or its children.
     *
     * @param array<int|string, float> $invoiceQtysRefundLimits Order item id => qty of the invoice that is not refunded yet.
     */
    public function hasQtyToRefund(OrderItem $item, array $invoiceQtysRefundLimits = []): bool
    {
        if ($item->getQtyToRefund() < 0) {
            return false;
        }
        if (isset($invoiceQtysRefundLimits[$item->getId()])) {
            return $invoiceQtysRefundLimits[$item->getId()] > 0;
        }
        return true;
    }

    /**
     * Turn each string and number of $values into a float, with the decimal separator that the value uses. Keep the
     * other values.
     *
     * @param array<array-key, mixed> $values
     * @return array<array-key, mixed>
     */
    public function parseLocaleNumbers(array $values): array
    {
        $locale = Mage::app()->getLocale();
        foreach ($values as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $values[$key] = $locale->getNumber($value);
            }
        }
        return $values;
    }

    /**
     * Refuse a negative qty, and a qty above 0 for an ID that is not an item of $order. A qty of 0 names no item.
     *
     * @param array<array-key, mixed> $qtys Order item id => qty, after parseLocaleNumbers()
     * @throws Mage_Core_Exception
     */
    private function assertOrderItemQtys(Order $order, array $qtys): void
    {
        foreach ($qtys as $orderItemId => $qty) {
            if (!is_float($qty)) {
                continue;
            }
            if ($qty < 0) {
                throw new Mage_Core_Exception(Mage::helper('sales')->__('The qty of order item %s cannot be negative', $orderItemId));
            }
            if ($qty > 0 && !$order->getItemById($orderItemId)) {
                throw new Mage_Core_Exception(Mage::helper('sales')->__('Order item %s does not belong to this order', $orderItemId));
            }
        }
    }

    private function getConvertor(): Mage_Sales_Model_Convert_Order
    {
        return Mage::getModel('sales/convert_order');
    }

    /**
     * Return the model sales/service_order of $order when a store rewrites it, or null. Before the services, core
     * built each document through that model, so a rewrite of prepareInvoice(), prepareShipment(),
     * prepareCreditmemo(), prepareInvoiceCreditmemo(), updateLocaleNumbers() or of an item check changed core. The
     * model builds through buildInvoiceWith(), buildShipmentWith() and buildCreditmemoWith(), which do not come back
     * here.
     */
    private function getRewrittenBuilder(Order $order, ?Mage_Sales_Model_Convert_Order $convertor): ?Mage_Sales_Model_Service_Order
    {
        if (Mage::getConfig()->getModelClassName('sales/service_order') === Mage_Sales_Model_Service_Order::class) {
            return null;
        }
        $builder = Mage::getModel('sales/service_order', $order);

        return $convertor ? $builder->setConvertor($convertor) : $builder;
    }

    /**
     * Run $send, an email that follows a saved change. A failure is logged, so that a retry does not repeat the change,
     * and $document gets the field $flag, so that the caller can tell the user.
     */
    private function sendSafely(callable $send, ?\Maho\DataObject $document = null, string $flag = self::EMAIL_FAILED): void
    {
        try {
            $send();
        } catch (Exception $e) {
            Mage::logException($e);
            $document?->setData($flag, true);
        }
    }

    /**
     * Run $operation while $document is in the registry under $key. The tax block of an email reads the invoice or
     * the credit memo from the registry, to show the taxes of the document and not of the order.
     */
    private function withCurrentDocument(string $key, Invoice|Creditmemo $document, callable $operation): void
    {
        $previous = Mage::registry($key);
        Mage::unregister($key);
        Mage::register($key, $document);
        try {
            $operation();
        } finally {
            Mage::unregister($key);
            if ($previous !== null) {
                Mage::register($key, $previous);
            }
        }
    }

    /**
     * Run $operation while this request holds the lock of the order $orderId. See Mage_Sales_Service_Order::withOrderLock().
     */
    private function withOrderLock(int $orderId, callable $operation): mixed
    {
        return Mage::getService('sales/order')->withOrderLock($orderId, $operation);
    }
}
