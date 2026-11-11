<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
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

        $service = Mage::getService('sales/order_document');
        $shipmentId = (int) $this->getRequest()->getParam('shipment_id');
        try {
            $shipment = $shipmentId
                ? $service->requireShipment($shipmentId)
                : $service->prepareShipment(Mage::getService('sales/order')->getById((int) $this->getRequest()->getParam('order_id')), $this->_getItemQtys());
        } catch (Mage_Core_Exception_NoSuchEntity|Mage_Core_Exception_Conflict $e) {
            $this->_getSession()->addError($e->getMessage());
            return false;
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
    #[\Deprecated('No core code calls it. Use Mage_Sales_Service_Order_Document::cancelShipment().', since: '27.1')]
    protected function _saveShipment($shipment)
    {
        $shipment->getOrder()->setIsInProcess();
        Mage::getModel('core/resource_transaction')
            ->addObject($shipment)
            ->addObject($shipment->getOrder())
            ->save();

        return $this;
    }

    /**
     * Shipment information page
     */
    #[\Override]
    #[Maho\Config\Route('/admin/sales_order_shipment/view')]
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
    #[Maho\Config\Route('/admin/sales_order_shipment/start')]
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
    #[Maho\Config\Route('/admin/sales_order_shipment/new')]
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
    #[Maho\Config\Route('/admin/sales_order_shipment/save')]
    public function saveAction(): void
    {
        $data = (array) $this->getRequest()->getPost('shipment');
        if (!empty($data['comment_text'])) {
            Mage::getSingleton('adminhtml/session')->setCommentText($data['comment_text']);
        }

        $orderId = $this->getRequest()->getParam('order_id');
        $responseAjax = new \Maho\DataObject();
        $isNeedCreateLabel = !empty($data['create_shipping_label']);

        try {
            $service = Mage::getService('sales/order_document');
            $tracks = [];
            foreach ((array) $this->getRequest()->getPost('tracking') as $track) {
                $tracks[] = Mage::getModel('sales/order_shipment_track')->addData($track);
            }
            $shipment = $service->ship(
                Mage::getService('sales/order')->getById((int) $orderId),
                $this->_getItemQtys(),
                $tracks,
                ($data['comment_text'] ?? '') !== '' ? (string) $data['comment_text'] : null,
                isset($data['comment_customer_notify']),
                isset($data['is_visible_on_front']),
                !empty($data['send_email']),
                $isNeedCreateLabel ? (array) $this->getRequest()->getParam('packages') : null,
            );
            if ($isNeedCreateLabel && $shipment->getShippingLabel()) {
                $responseAjax->setOk(true);
            }

            $this->_getSession()->addSuccess($isNeedCreateLabel
                ? $this->__('The shipment has been created.') . ' ' . $this->__('The shipping label has been created.')
                : $this->__('The shipment has been created.'));
            Mage::getSingleton('adminhtml/session')->getCommentText(true);
        } catch (Mage_Core_Exception $e) {
            $message = $e->getMessage();
        } catch (Exception $e) {
            Mage::logException($e);
            $message = $isNeedCreateLabel
                ? Mage::helper('sales')->__('An error occurred while creating shipping label.')
                : $this->__('Cannot save shipment.');
        }

        if ($isNeedCreateLabel) {
            if (isset($message)) {
                $responseAjax->setError(true)->setMessage($message);
            }
            $this->getResponse()->setBodyJson($responseAjax);
        } elseif (isset($message)) {
            $this->_getSession()->addError($message);
            $this->_redirect('*/*/new', ['order_id' => $orderId]);
        } else {
            $this->_redirect('*/sales_order/view', ['order_id' => $orderId]);
        }
    }

    #[Maho\Config\Route('/admin/sales_order_shipment/cancel')]
    public function cancelAction(): void
    {
        if ($shipment = $this->_initShipment()) {
            try {
                Mage::getService('sales/order_document')->cancelShipment($shipment);
                $this->_getSession()->addSuccess($this->__('The shipment has been canceled.'));
            } catch (Mage_Core_Exception $e) {
                $this->_getSession()->addError($e->getMessage());
            } catch (Exception) {
                $this->_getSession()->addError($this->__('Cannot cancel the shipment.'));
            }
            $this->_redirect('*/*/view', ['shipment_id' => $shipment->getId()]);
        } else {
            $this->_forward('noRoute');
        }
    }

    /**
     * Send email with shipment data to customer
     */
    #[Maho\Config\Route('/admin/sales_order_shipment/email')]
    public function emailAction(): void
    {
        try {
            $shipment = $this->_initShipment();
            if ($shipment) {
                Mage::getService('sales/order')->resendEmail($shipment);
                $this->_getSession()->addSuccess($this->__('The shipment has been sent.'));
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError($e->getMessage());
        } catch (Exception) {
            $this->_getSession()->addError($this->__('Cannot send shipment information.'));
        }
        $this->_redirect('*/*/view', [
            'shipment_id' => $this->getRequest()->getParam('shipment_id'),
        ]);
    }

    /**
     * Add new tracking number action
     */
    #[Maho\Config\Route('/admin/sales_order_shipment/addTrack')]
    public function addTrackAction(): void
    {
        $this->_changeTracks(fn(Mage_Sales_Model_Order_Shipment $shipment) => Mage::getService('sales/order_document')->addTrack(
            $shipment,
            Mage::getModel('sales/order_shipment_track')
                ->setNumber((string) $this->getRequest()->getPost('number'))
                ->setCarrierCode((string) $this->getRequest()->getPost('carrier'))
                ->setTitle((string) $this->getRequest()->getPost('title')),
        ), $this->__('Cannot add tracking number.'));
    }

    /**
     * Remove tracking number from shipment
     */
    #[Maho\Config\Route('/admin/sales_order_shipment/removeTrack')]
    public function removeTrackAction(): void
    {
        $this->_changeTracks(fn(Mage_Sales_Model_Order_Shipment $shipment) => Mage::getService('sales/order_document')->removeTrack(
            $shipment,
            (int) $this->getRequest()->getParam('track_id'),
        ), $this->__('Cannot delete tracking number.'));
    }

    /**
     * Run $change on the shipment of the request, then render its tracking block, or the error as JSON.
     *
     * @param callable(Mage_Sales_Model_Order_Shipment): mixed $change
     */
    protected function _changeTracks(callable $change, string $failure): void
    {
        try {
            $change($this->_initShipment() ?: Mage::throwException($this->__('The shipment no longer exists.')));
            $this->loadLayout();
            $this->getResponse()->setBody($this->getLayout()->getBlock('shipment_tracking')->toHtml());
            return;
        } catch (Mage_Core_Exception $e) {
            $message = $e->getMessage();
        } catch (Exception $e) {
            Mage::logException($e);
            $message = $failure;
        }
        $this->getResponse()->setBodyJson(['error' => true, 'message' => $message]);
    }

    /**
     * View shipment tracking information
     */
    #[Maho\Config\Route('/admin/sales_order_shipment/viewTrack')]
    public function viewTrackAction(): void
    {
        $trackId    = $this->getRequest()->getParam('track_id');
        $track = Mage::getModel('sales/order_shipment_track')->load($trackId);
        if ($track->getId()) {
            try {
                $response = $track->getNumberDetail();
            } catch (Exception) {
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
    #[Maho\Config\Route('/admin/sales_order_shipment/addComment')]
    public function addCommentAction(): void
    {
        try {
            $this->getRequest()->setParam('shipment_id', $this->getRequest()->getParam('id'));
            $data = (array) $this->getRequest()->getPost('comment');
            Mage::getService('sales/order_document')->addComment(
                $this->_initShipment() ?: Mage::throwException($this->__('The shipment no longer exists.')),
                (string) ($data['comment'] ?? ''),
                !empty($data['is_customer_notified']),
                isset($data['is_visible_on_front']),
            );

            $this->loadLayout(false);
            $response = $this->getLayout()->getBlock('shipment_comments')->toHtml();
        } catch (Mage_Core_Exception $e) {
            $response = Mage::helper('core')->jsonEncode(['error' => true, 'message' => $e->getMessage()]);
        } catch (Exception $e) {
            Mage::logException($e);
            $response = Mage::helper('core')->jsonEncode(['error' => true, 'message' => $this->__('Cannot add new comment.')]);
        }
        $this->getResponse()->setBody($response);
    }

    /**
     * Create shipping label action for specific shipment
     */
    #[Maho\Config\Route('/admin/sales_order_shipment/createLabel')]
    public function createLabelAction(): void
    {
        try {
            $shipment = $this->_initShipment() ?: Mage::throwException($this->__('The shipment no longer exists.'));
            if (!Mage::getService('sales/order_document')->createShippingLabel($shipment, (array) $this->getRequest()->getParam('packages'))) {
                Mage::throwException(Mage::helper('sales')->__('An error occurred while creating shipping label.'));
            }
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
    #[Maho\Config\Route('/admin/sales_order_shipment/printLabel')]
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
    #[Maho\Config\Route('/admin/sales_order_shipment/printPackage')]
    public function printPackageAction(): void
    {
        $shipment = $this->_initShipment();

        if ($shipment) {
            $pdf = Mage::getModel('sales/order_pdf_shipment_packaging')->getPdf([$shipment]);
            $this->_prepareDownloadResponse(
                'packingslip' . Mage::app()->getLocale()->utcToStore()->format('Y-m-d_H-i-s') . '.pdf',
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
    #[Maho\Config\Route('/admin/sales_order_shipment/massPrintShippingLabel')]
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
                // Keep only values that are non-zero when cast to int
                $ids = array_filter($ids, fn(mixed $o) => (int) $o !== 0);
                if (!empty($ids)) {
                    $shipments = Mage::getResourceModel('sales/order_shipment_collection')
                        ->addFieldToFilter('entity_id', ['in' => $ids]);
                }
                break;
            case 'order_ids':
                $ids = $request->getParam('order_ids');
                // Keep only values that are non-zero when cast to int
                $ids = array_filter($ids, fn(mixed $o) => (int) $o !== 0);
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
     * @throws \RuntimeException when the archive cannot be written
     */
    protected function createZipArchive(array $files, string $tempFilePrefix = 'shipping_labels_'): string
    {
        return Mage::getService('sales/order_document')->zipShippingLabels($files, $tempFilePrefix);
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
    <img src="' . $dataUri . '" class="shipping-label" alt="Shipping Label">
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
    #[Maho\Config\Route('/admin/sales_order_shipment/getShippingItemsGrid')]
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
