<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Sales_Order_ShipmentController extends Mage_Adminhtml_Controller_Sales_Shipment
{
    /**
     * Initialize shipment items QTY
     */
    protected function _getItemQtys()
    {
        $data = $this->getRequest()->getParam('shipment');
        $qtys = $data['items'] ?? [];
        return $qtys;
    }

    /**
     * Initialize shipment model instance
     *
     * @return Mage_Sales_Model_Order_Shipment|bool
     * @throws Mage_Core_Exception
     */
    protected function _initShipment()
    {
        $this->_title($this->__('Sales'))->_title($this->__('Shipments'));

        $shipment = false;
        $shipmentId = $this->getRequest()->getParam('shipment_id');
        $orderId = $this->getRequest()->getParam('order_id');
        if ($shipmentId) {
            $shipment = Mage::getModel('sales/order_shipment')->load($shipmentId);
            if (!$shipment->getId()) {
                $this->_getSession()->addError($this->__('The shipment no longer exists.'));
                return false;
            }
        } elseif ($orderId) {
            $order      = Mage::getModel('sales/order')->load($orderId);

            /**
             * Check order existing
             */
            if (!$order->getId()) {
                $this->_getSession()->addError($this->__('The order no longer exists.'));
                return false;
            }
            /**
             * Check shipment is available to create separate from invoice
             */
            if ($order->getForcedDoShipmentWithInvoice()) {
                $this->_getSession()->addError($this->__('Cannot do shipment for the order separately from invoice.'));
                return false;
            }
            /**
             * Check shipment create availability
             */
            if (!$order->canShip()) {
                $this->_getSession()->addError($this->__('Cannot do shipment for the order.'));
                return false;
            }
            $savedQtys = $this->_getItemQtys();
            $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($savedQtys);

            $tracks = $this->getRequest()->getPost('tracking');
            if ($tracks) {
                foreach ($tracks as $data) {
                    if (empty($data['number'])) {
                        Mage::throwException($this->__('Tracking number cannot be empty.'));
                    }
                    $track = Mage::getModel('sales/order_shipment_track')
                        ->addData($data);
                    $shipment->addTrack($track);
                }
            }
        }

        Mage::register('current_shipment', $shipment);
        return $shipment;
    }

    /**
     * Save shipment and order in one transaction
     *
     * @param Mage_Sales_Model_Order_Shipment $shipment
     * @return $this
     * @throws Exception
     */
    protected function _saveShipment($shipment)
    {
        $shipment->getOrder()->setIsInProcess(true);
        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($shipment)
            ->addObject($shipment->getOrder())
            ->save();

        return $this;
    }

    /**
     * Shipment information page
     */
    #[\Override]
    public function viewAction(): void
    {
        $shipment = $this->_initShipment();
        if ($shipment) {
            $this->_title(sprintf('#%s', $shipment->getIncrementId()));

            $this->loadLayout();

            /** @var Mage_Adminhtml_Block_Sales_Order_Shipment_View $block */
            $block = $this->getLayout()->getBlock('sales_shipment_view');
            $block->updateBackButtonUrl($this->getRequest()->getParam('come_from'));

            $this->_setActiveMenu('sales/shipment')
                ->renderLayout();
        } else {
            $this->_redirect('*/*/');
        }
    }

    /**
     * Start create shipment action
     */
    public function startAction(): void
    {
        /**
         * Clear old values for shipment qty's
         */
        $this->_redirect('*/*/new', ['order_id' => $this->getRequest()->getParam('order_id')]);
    }

    /**
     * Shipment create page
     */
    public function newAction(): void
    {
        if ($shipment = $this->_initShipment()) {
            $this->_title($this->__('New Shipment'));

            $comment = Mage::getSingleton('adminhtml/session')->getCommentText(true);
            if ($comment) {
                $shipment->setCommentText($comment);
            }

            $this->loadLayout()
                ->_setActiveMenu('sales/shipment')
                ->renderLayout();
        } else {
            $this->_redirect('*/sales_order/view', ['order_id' => $this->getRequest()->getParam('order_id')]);
        }
    }

    /**
     * Save shipment
     * We can save only new shipment. Existing shipments are not editable
     */
    public function saveAction(): void
    {
        $data = $this->getRequest()->getPost('shipment');
        if (!empty($data['comment_text'])) {
            Mage::getSingleton('adminhtml/session')->setCommentText($data['comment_text']);
        }

        $shipment = $this->_initShipment();
        if (!$shipment) {
            $this->_forward('noRoute');
            return;
        }

        $responseAjax = new \Maho\DataObject();
        $isNeedCreateLabel = isset($data['create_shipping_label']) && $data['create_shipping_label'];

        try {
            $shipment->register();
            $comment = '';
            if (!empty($data['comment_text'])) {
                $shipment->addComment(
                    $data['comment_text'],
                    isset($data['comment_customer_notify']),
                    isset($data['is_visible_on_front']),
                );
                if (isset($data['comment_customer_notify'])) {
                    $comment = $data['comment_text'];
                }
            }

            if (!empty($data['send_email'])) {
                $shipment->setEmailSent(true);
            }

            $shipment->getOrder()->setCustomerNoteNotify(!empty($data['send_email']));

            if ($isNeedCreateLabel && $this->_createShippingLabel($shipment)) {
                $responseAjax->setOk(true);
            }

            $this->_saveShipment($shipment);

            $shipment->sendEmail(!empty($data['send_email']), $comment);

            $shipmentCreatedMessage = $this->__('The shipment has been created.');
            $labelCreatedMessage    = $this->__('The shipping label has been created.');

            $this->_getSession()->addSuccess($isNeedCreateLabel ? $shipmentCreatedMessage . ' ' . $labelCreatedMessage
                : $shipmentCreatedMessage);
            Mage::getSingleton('adminhtml/session')->getCommentText(true);
        } catch (Mage_Core_Exception $e) {
            if ($isNeedCreateLabel) {
                $responseAjax->setError(true);
                $responseAjax->setMessage($e->getMessage());
            } else {
                $this->_getSession()->addError($e->getMessage());
                $this->_redirect('*/*/new', ['order_id' => $this->getRequest()->getParam('order_id')]);
            }
        } catch (Exception $e) {
            Mage::logException($e);
            if ($isNeedCreateLabel) {
                $responseAjax->setError(true);
                $responseAjax->setMessage(
                    Mage::helper('sales')->__('An error occurred while creating shipping label.'),
                );
            } else {
                $this->_getSession()->addError($this->__('Cannot save shipment.'));
                $this->_redirect('*/*/new', ['order_id' => $this->getRequest()->getParam('order_id')]);
            }
        }
        if ($isNeedCreateLabel) {
            $this->getResponse()->setBodyJson($responseAjax);
        } else {
            $this->_redirect('*/sales_order/view', ['order_id' => $shipment->getOrderId()]);
        }
    }

    /**
     * Send email with shipment data to customer
     */
    public function emailAction(): void
    {
        try {
            $shipment = $this->_initShipment();
            if ($shipment) {
                $shipment->sendEmail(true)
                    ->setEmailSent(true)
                    ->save();
                $historyItem = Mage::getResourceModel('sales/order_status_history_collection')
                    ->getUnnotifiedForInstance($shipment, Mage_Sales_Model_Order_Shipment::HISTORY_ENTITY_NAME);
                if ($historyItem) {
                    $historyItem->setIsCustomerNotified(1);
                    $historyItem->save();
                }
                $this->_getSession()->addSuccess($this->__('The shipment has been sent.'));
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            $this->_getSession()->addError($this->__('Cannot send shipment information.'));
        }
        $this->_redirect('*/*/view', [
            'shipment_id' => $this->getRequest()->getParam('shipment_id'),
        ]);
    }

    /**
     * Add new tracking number action
     */
    public function addTrackAction(): void
    {
        try {
            $carrier = $this->getRequest()->getPost('carrier');
            $number  = $this->getRequest()->getPost('number');
            $title  = $this->getRequest()->getPost('title');
            if (empty($carrier)) {
                Mage::throwException($this->__('The carrier needs to be specified.'));
            }
            if (empty($number)) {
                Mage::throwException($this->__('Tracking number cannot be empty.'));
            }
            $shipment = $this->_initShipment();
            if ($shipment) {
                $track = Mage::getModel('sales/order_shipment_track')
                    ->setNumber($number)
                    ->setCarrierCode($carrier)
                    ->setTitle($title);
                $shipment->addTrack($track)
                    ->save();

                $this->loadLayout();
                $response = $this->getLayout()->getBlock('shipment_tracking')->toHtml();
            } else {
                $response = [
                    'error'     => true,
                    'message'   => $this->__('Cannot initialize shipment for adding tracking number.'),
                ];
            }
        } catch (Mage_Core_Exception $e) {
            $response = [
                'error'     => true,
                'message'   => $e->getMessage(),
            ];
        } catch (Exception $e) {
            $response = [
                'error'     => true,
                'message'   => $this->__('Cannot add tracking number.'),
            ];
        }
        if (is_array($response)) {
            $response = Mage::helper('core')->jsonEncode($response);
        }
        $this->getResponse()->setBody($response);
    }

    /**
     * Remove tracking number from shipment
     */
    public function removeTrackAction(): void
    {
        $trackId    = $this->getRequest()->getParam('track_id');
        $shipmentId = $this->getRequest()->getParam('shipment_id');
        $track = Mage::getModel('sales/order_shipment_track')->load($trackId);
        if ($track->getId()) {
            try {
                if ($this->_initShipment()) {
                    $track->delete();

                    $this->loadLayout();
                    $response = $this->getLayout()->getBlock('shipment_tracking')->toHtml();
                } else {
                    $response = [
                        'error'     => true,
                        'message'   => $this->__('Cannot initialize shipment for delete tracking number.'),
                    ];
                }
            } catch (Exception $e) {
                $response = [
                    'error'     => true,
                    'message'   => $this->__('Cannot delete tracking number.'),
                ];
            }
        } else {
            $response = [
                'error'     => true,
                'message'   => $this->__('Cannot load track with retrieving identifier.'),
            ];
        }
        if (is_array($response)) {
            $response = Mage::helper('core')->jsonEncode($response);
        }
        $this->getResponse()->setBody($response);
    }

    /**
     * View shipment tracking information
     */
    public function viewTrackAction(): void
    {
        $trackId    = $this->getRequest()->getParam('track_id');
        $shipmentId = $this->getRequest()->getParam('shipment_id');
        $track = Mage::getModel('sales/order_shipment_track')->load($trackId);
        if ($track->getId()) {
            try {
                $response = $track->getNumberDetail();
            } catch (Exception $e) {
                $response = [
                    'error'     => true,
                    'message'   => $this->__('Cannot retrieve tracking number detail.'),
                ];
            }
        } else {
            $response = [
                'error'     => true,
                'message'   => $this->__('Cannot load track with retrieving identifier.'),
            ];
        }

        if (is_object($response)) {
            $className = Mage::getConfig()->getBlockClassName('adminhtml/template');
            $block = new $className();
            $block->setType('adminhtml/template')
                ->setIsAnonymous(true)
                ->setTemplate('sales/order/shipment/tracking/info.phtml');

            $block->setTrackingInfo($response);

            $this->getResponse()->setBody($block->toHtml());
        } else {
            if (is_array($response)) {
                $response = Mage::helper('core')->jsonEncode($response);
            }

            $this->getResponse()->setBody($response);
        }
    }

    /**
     * Add comment to shipment history
     */
    public function addCommentAction(): void
    {
        try {
            $this->getRequest()->setParam(
                'shipment_id',
                $this->getRequest()->getParam('id'),
            );
            $data = $this->getRequest()->getPost('comment');
            if (empty($data['comment'])) {
                Mage::throwException($this->__('Comment text field cannot be empty.'));
            }
            $shipment = $this->_initShipment();
            $shipment->addComment(
                $data['comment'],
                isset($data['is_customer_notified']),
                isset($data['is_visible_on_front']),
            );
            $shipment->sendUpdateEmail(!empty($data['is_customer_notified']), $data['comment']);
            $shipment->save();

            $this->loadLayout(false);
            $response = $this->getLayout()->getBlock('shipment_comments')->toHtml();
        } catch (Mage_Core_Exception $e) {
            $response = [
                'error'     => true,
                'message'   => $e->getMessage(),
            ];
            $response = Mage::helper('core')->jsonEncode($response);
        } catch (Exception $e) {
            $response = [
                'error'     => true,
                'message'   => $this->__('Cannot add new comment.'),
            ];
            $response = Mage::helper('core')->jsonEncode($response);
        }
        $this->getResponse()->setBody($response);
    }

    /**
     * Create shipping label for specific shipment with validation.
     *
     * @return bool
     */
    protected function _createShippingLabel(Mage_Sales_Model_Order_Shipment $shipment)
    {
        if (!$shipment) {
            return false;
        }
        $carrier = $shipment->getOrder()->getShippingCarrier();
        if (!$carrier->isShippingLabelsAvailable()) {
            return false;
        }
        $shipment->setPackages($this->getRequest()->getParam('packages'));
        $response = Mage::getModel('shipping/shipping')->requestToShipment($shipment);
        if ($response->hasErrors()) {
            Mage::throwException($response->getErrors());
        }
        if (!$response->hasInfo()) {
            return false;
        }
        $labelsContent = [];
        $trackingNumbers = [];
        $info = $response->getInfo();
        foreach ($info as $inf) {
            if (!empty($inf['tracking_number']) && !empty($inf['label_content'])) {
                $labelsContent[] = $inf['label_content'];
                $trackingNumbers[] = $inf['tracking_number'];
            }
        }

        // Handle single or multiple labels appropriately
        if (!empty($labelsContent)) {
            if (count($labelsContent) === 1) {
                // Single label - store directly
                $shipment->setShippingLabel($labelsContent[0]);
            } else {
                // Multiple labels - combine into ZIP archive
                try {
                    $zipContent = $this->createLabelsZipForSingleShipment($labelsContent, $shipment);
                    $shipment->setShippingLabel($zipContent);
                } catch (Exception $e) {
                    Mage::logException($e);
                    return false;
                }
            }
        } else {
            // No labels generated
            return false;
        }
        $carrierCode = $carrier->getCarrierCode();
        $carrierTitle = Mage::getStoreConfig('carriers/' . $carrierCode . '/title', $shipment->getStoreId());
        if ($trackingNumbers) {
            foreach ($trackingNumbers as $trackingNumber) {
                $track = Mage::getModel('sales/order_shipment_track')
                        ->setNumber($trackingNumber)
                        ->setCarrierCode($carrierCode)
                        ->setTitle($carrierTitle);
                $shipment->addTrack($track);
            }
        }
        return true;
    }

    /**
     * Create shipping label action for specific shipment
     */
    public function createLabelAction(): void
    {
        $response = new \Maho\DataObject();
        try {
            $shipment = $this->_initShipment();
            if (!$this->_createShippingLabel($shipment)) {
                Mage::throwException(Mage::helper('sales')->__('An error occurred while creating shipping label.'));
            }
            $shipment->save();
            $this->_getSession()->addSuccess(Mage::helper('sales')->__('The shipping label has been created.'));
            $this->getResponse()->setBodyJson([ 'ok' => true ]);
        } catch (Mage_Core_Exception $e) {
            $this->getResponse()->setBodyJson([ 'error' => true, 'message' => $e->getMessage() ]);
        } catch (Exception $e) {
            Mage::logException($e);
            $this->getResponse()->setBodyJson([
                'error' => true,
                'message' => Mage::helper('sales')->__('An error occurred while creating shipping label.'),
            ]);
        }
    }

    /**
     * Print label for one specific shipment
     */
    public function printLabelAction()
    {
        try {
            $shipment = $this->_initShipment();
            $labelContent = $shipment->getShippingLabel();
            if ($labelContent) {
                // Check if content is ZIP file (multiple labels)
                if (str_starts_with($labelContent, 'PK')) {
                    // ZIP file signature detected - return as ZIP
                    return $this->_prepareDownloadResponse(
                        'ShippingLabels(' . $shipment->getIncrementId() . ').zip',
                        $labelContent,
                        'application/zip',
                    );
                }
                if (stripos($labelContent, '%PDF-') !== false) {
                    // Single PDF file
                    return $this->_prepareDownloadResponse(
                        'ShippingLabel(' . $shipment->getIncrementId() . ').pdf',
                        $labelContent,
                        'application/pdf',
                    );
                }
                // Image content - convert to PDF
                $pdfContent = $this->createPdfFromImageString($labelContent, $shipment->getIncrementId());
                if (!$pdfContent) {
                    $this->_getSession()->addError(Mage::helper('sales')->__('File extension not known or unsupported type in the following shipment: %s', $shipment->getIncrementId()));
                } else {
                    return $this->_prepareDownloadResponse(
                        'ShippingLabel(' . $shipment->getIncrementId() . ').pdf',
                        $pdfContent,
                        'application/pdf',
                    );
                }
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_getSession()
                ->addError(Mage::helper('sales')->__('An error occurred while creating shipping label.'));
        }
        $this->_redirect('*/sales_order_shipment/view', [
            'shipment_id' => $this->getRequest()->getParam('shipment_id'),
        ]);
    }

    /**
     * Create pdf document with information about packages
     */
    public function printPackageAction(): void
    {
        $shipment = $this->_initShipment();

        if ($shipment) {
            $pdf = Mage::getModel('sales/order_pdf_shipment_packaging')->getPdf([$shipment]);
            $this->_prepareDownloadResponse(
                'packingslip' . Mage::getSingleton('core/date')->date('Y-m-d_H-i-s') . '.pdf',
                $pdf,
                'application/pdf',
            );
        } else {
            $this->_forward('noRoute');
        }
    }

    /**
     * Batch print shipping labels for whole shipments.
     * Push pdf document with shipping labels to user browser
     */
    public function massPrintShippingLabelAction(): void
    {
        $request = $this->getRequest();
        $ids = $request->getParam('order_ids');
        $createdFromOrders = !empty($ids);
        $shipments = null;
        $labelsContent = [];
        switch ($request->getParam('massaction_prepare_key')) {
            case 'shipment_ids':
                $ids = $request->getParam('shipment_ids');
                array_filter($ids, '\intval');
                if (!empty($ids)) {
                    $shipments = Mage::getResourceModel('sales/order_shipment_collection')
                        ->addFieldToFilter('entity_id', ['in' => $ids]);
                }
                break;
            case 'order_ids':
                $ids = $request->getParam('order_ids');
                array_filter($ids, '\intval');
                if (!empty($ids)) {
                    $shipments = Mage::getResourceModel('sales/order_shipment_collection')
                        ->setOrderFilter(['in' => $ids]);
                }
                break;
        }

        if ($shipments && $shipments->getSize()) {
            foreach ($shipments as $shipment) {
                $labelContent = $shipment->getShippingLabel();
                if ($labelContent) {
                    $labelsContent[] = $labelContent;
                }
            }
        }

        if (!empty($labelsContent)) {
            if (count($labelsContent) === 1) {
                // Single label - direct PDF download
                $this->_prepareDownloadResponse('ShippingLabel.pdf', $labelsContent[0], 'application/pdf');
            } else {
                // Multiple labels - create ZIP archive
                $zipFile = $this->createLabelsZip($labelsContent, $shipments);
                $this->_prepareDownloadResponse('ShippingLabels.zip', $zipFile, 'application/zip');
            }
            return;
        }

        if ($createdFromOrders) {
            $this->_getSession()
                ->addError(Mage::helper('sales')->__('There are no shipping labels related to selected orders.'));
            $this->_redirect('*/sales_order/index');
        } else {
            $this->_getSession()
                ->addError(Mage::helper('sales')->__('There are no shipping labels related to selected shipments.'));
            $this->_redirect('*/sales_order_shipment/index');
        }
    }

    /**
     * Create ZIP archive with shipping labels
     *
     * @param array $files Array of ['filename' => 'content'] pairs
     * @param string $tempFilePrefix Prefix for temporary file
     * @return string ZIP file binary content
     * @throws Mage_Core_Exception
     */
    protected function createZipArchive(array $files, string $tempFilePrefix = 'shipping_labels_'): string
    {
        $tempFile = tempnam(Mage::getBaseDir('var') . DS . 'tmp', $tempFilePrefix);
        if ($tempFile === false) {
            throw new Mage_Core_Exception(
                Mage::helper('sales')->__('Cannot create temporary file for shipping labels archive.'),
            );
        }

        $zip = new ZipArchive();
        $result = $zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($result !== true) {
            @unlink($tempFile);
            throw new Mage_Core_Exception(
                Mage::helper('sales')->__('Cannot create ZIP archive for shipping labels. Error code: %s', $result),
            );
        }

        try {
            foreach ($files as $filename => $content) {
                $zip->addFromString($filename, $content);
            }

            $zip->close();

            // Read the ZIP file content
            $zipContent = file_get_contents($tempFile);
            @unlink($tempFile);

            if ($zipContent === false) {
                throw new Mage_Core_Exception(
                    Mage::helper('sales')->__('Cannot read created ZIP archive.'),
                );
            }

            return $zipContent;

        } catch (Exception $e) {
            $zip->close();
            @unlink($tempFile);
            throw new Mage_Core_Exception(
                Mage::helper('sales')->__('Error creating shipping labels archive: %s', $e->getMessage()),
            );
        }
    }

    /**
     * Create ZIP archive containing multiple shipping labels for a single shipment
     *
     * @param array $labelsContent Array of label content (PDF binary data)
     * @param Mage_Sales_Model_Order_Shipment $shipment Single shipment
     * @return string ZIP file binary content
     * @throws Mage_Core_Exception
     */
    protected function createLabelsZipForSingleShipment(array $labelsContent, Mage_Sales_Model_Order_Shipment $shipment): string
    {
        $files = [];
        foreach ($labelsContent as $index => $content) {
            $filename = sprintf('label_%s_package_%d.pdf', $shipment->getIncrementId(), $index + 1);
            $files[$filename] = $content;
        }

        return $this->createZipArchive($files, 'shipping_label_');
    }

    /**
     * Create ZIP archive containing multiple shipping labels
     *
     * @param array $labelsContent Array of label content (PDF binary data)
     * @param Mage_Sales_Model_Resource_Order_Shipment_Collection $shipments Shipment collection
     * @return string ZIP file binary content
     * @throws Mage_Core_Exception
     */
    protected function createLabelsZip(array $labelsContent, $shipments): string
    {
        $files = [];
        $labelIndex = 0;
        foreach ($shipments as $shipment) {
            if (isset($labelsContent[$labelIndex])) {
                $filename = sprintf('label_%s.pdf', $shipment->getIncrementId());
                $files[$filename] = $labelsContent[$labelIndex];
                $labelIndex++;
            }
        }

        return $this->createZipArchive($files);
    }

    /**
     * Create PDF from image string using HTML/CSS approach
     *
     * @param string $imageString
     * @param string $filename
     * @return string|false
     */
    protected function createPdfFromImageString($imageString, $filename = 'label')
    {
        $html = $this->createHtmlFromImageString($imageString);
        if (!$html) {
            return false;
        }

        return $this->_generatePdfFromHtml($html);
    }

    /**
     * Create HTML from image string
     *
     * @param string $imageString
     * @return string|false
     */
    protected function createHtmlFromImageString($imageString)
    {
        $image = imagecreatefromstring($imageString);
        if (!$image) {
            return false;
        }

        // Convert image to base64 for embedding in HTML
        ob_start();
        imagepng($image);
        $imageData = ob_get_contents();
        ob_end_clean();
        imagedestroy($image);

        $base64 = base64_encode($imageData);
        $dataUri = 'data:image/png;base64,' . $base64;

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 0; }
        body { margin: 0; padding: 0; }
        .shipping-label {
            width: 100%;
            height: auto;
            display: block;
            margin: 0 auto;
        }
    </style>
</head>
<body>
    <img src="' . $dataUri . '" class="shipping-label" alt="Shipping Label" />
</body>
</html>';

        return $html;
    }

    /**
     * Generate PDF from HTML using dompdf
     *
     * @param string $html
     * @return string
     */
    protected function _generatePdfFromHtml($html)
    {
        $pdfModel = Mage::getModel('sales/order_pdf_invoice');
        return $pdfModel->generatePdfFromHtml($html);
    }

    /**
     * Return grid with shipping items for Ajax request
     *
     * @return Mage_Core_Controller_Response_Http
     */
    public function getShippingItemsGridAction()
    {
        $this->_initShipment();
        return $this->getResponse()->setBody(
            $this->getLayout()
                ->createBlock('adminhtml/sales_order_shipment_packaging_grid')
                ->setIndex($this->getRequest()->getParam('index'))
                ->toHtml(),
        );
    }
}
