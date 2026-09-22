<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2017-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/**
 * @method Mage_Sales_Model_Resource_Order_Invoice _getResource()
 * @method Mage_Sales_Model_Resource_Order_Invoice getResource()
 * @method float getShippingAmount()
 * @method float getTotalQty()
 * @method float getDiscountAmount()
 */
class Mage_Sales_Model_Order_Invoice extends Mage_Sales_Model_Abstract
{
    /**
     * Invoice states
     */
    public const STATE_OPEN       = 1;
    public const STATE_PAID       = 2;
    public const STATE_CANCELED   = 3;

    public const CAPTURE_ONLINE   = 'online';
    public const CAPTURE_OFFLINE  = 'offline';
    public const NOT_CAPTURE      = 'not_capture';

    public const XML_PATH_EMAIL_TEMPLATE               = 'sales_email/invoice/template';
    public const XML_PATH_EMAIL_GUEST_TEMPLATE         = 'sales_email/invoice/guest_template';
    public const XML_PATH_EMAIL_IDENTITY               = 'sales_email/invoice/identity';
    public const XML_PATH_EMAIL_COPY_TO                = 'sales_email/invoice/copy_to';
    public const XML_PATH_EMAIL_COPY_METHOD            = 'sales_email/invoice/copy_method';
    public const XML_PATH_EMAIL_ENABLED                = 'sales_email/invoice/enabled';
    public const XML_PATH_EMAIL_ATTACH_PDF             = 'sales_email/invoice/attach_pdf';

    public const XML_PATH_UPDATE_EMAIL_TEMPLATE        = 'sales_email/invoice_comment/template';
    public const XML_PATH_UPDATE_EMAIL_GUEST_TEMPLATE  = 'sales_email/invoice_comment/guest_template';
    public const XML_PATH_UPDATE_EMAIL_IDENTITY        = 'sales_email/invoice_comment/identity';
    public const XML_PATH_UPDATE_EMAIL_COPY_TO         = 'sales_email/invoice_comment/copy_to';
    public const XML_PATH_UPDATE_EMAIL_COPY_METHOD     = 'sales_email/invoice_comment/copy_method';
    public const XML_PATH_UPDATE_EMAIL_ENABLED         = 'sales_email/invoice_comment/enabled';

    public const REPORT_DATE_TYPE_ORDER_CREATED        = 'order_created';
    public const REPORT_DATE_TYPE_INVOICE_CREATED      = 'invoice_created';

    /**
     * Identifier for order history item
     */
    public const HISTORY_ENTITY_NAME = 'invoice';

    protected static $_states;

    /**
     * @var Mage_Sales_Model_Resource_Order_Invoice_Item_Collection|Mage_Sales_Model_Order_Invoice_Item[]|null
     */
    protected $_items;

    /**
     * @var Mage_Sales_Model_Resource_Order_Invoice_Comment_Collection|Mage_Sales_Model_Order_Invoice_Comment[]|null
     */
    protected $_comments;

    /**
     * @var Mage_Sales_Model_Order|null
     */
    protected $_order;

    /**
     * Calculator instances for delta rounding of prices
     *
     * @var array
     */
    protected $_rounders = [];

    protected $_saveBeforeDestruct = false;

    #[\Override]
    protected $_eventPrefix = 'sales_order_invoice';
    #[\Override]
    protected $_eventObject = 'invoice';

    /**
     * Whether the pay() was called
     * @var bool
     */
    protected $_wasPayCalled = false;

    /**
     * Uploader clean on shutdown
     */
    public function destruct()
    {
        if ($this->_saveBeforeDestruct) {
            $this->save();
        }
    }

    /**
     * Initialize invoice resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/order_invoice');
    }

    /**
     * Init mapping array of short fields to its full names
     *
     * @return $this
     */
    #[\Override]
    protected function _initOldFieldsMap()
    {
        return $this;
    }

    /**
     * Load invoice by increment id
     *
     * @param string $incrementId
     * @return $this
     */
    public function loadByIncrementId($incrementId)
    {
        $ids = $this->getCollection()
            ->addAttributeToFilter('increment_id', $incrementId)
            ->getAllIds();

        if (!empty($ids)) {
            reset($ids);
            $this->load(current($ids));
        }
        return $this;
    }

    /**
     * Retrieve invoice configuration model
     *
     * @return Mage_Sales_Model_Order_Invoice_Config
     */
    public function getConfig()
    {
        return Mage::getSingleton('sales/order_invoice_config');
    }

    /**
     * Retrieve store model instance
     *
     * @return Mage_Core_Model_Store
     */
    #[\Override]
    public function getStore()
    {
        return $this->getOrder()->getStore();
    }

    /**
     * Declare order for invoice
     *
     * @return  $this
     */
    public function setOrder(Mage_Sales_Model_Order $order)
    {
        $this->_order = $order;
        $this->setOrderId($order->getId())
            ->setStoreId($order->getStoreId());
        return $this;
    }

    /**
     * Retrieve the order the invoice for created for
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrder()
    {
        if (!$this->_order instanceof Mage_Sales_Model_Order) {
            $this->_order = Mage::getModel('sales/order')->load($this->getOrderId());
        }
        return $this->_order->setHistoryEntityName(self::HISTORY_ENTITY_NAME);
    }

    /**
     * Retrieve the increment_id of the order
     *
     * @return string
     */
    public function getOrderIncrementId()
    {
        return Mage::getModel('sales/order')->getResource()->getIncrementId($this->getOrderId());
    }

    /**
     * Retrieve billing address
     */
    #[\Override]
    public function getBillingAddress(): ?Mage_Sales_Model_Order_Address
    {
        return $this->getOrder()->getBillingAddress();
    }

    /**
     * Retrieve shipping address
     */
    #[\Override]
    public function getShippingAddress(): ?Mage_Sales_Model_Order_Address
    {
        return $this->getOrder()->getShippingAddress();
    }

    /**
     * Check invoice cancel state
     *
     * @return bool
     */
    public function isCanceled()
    {
        return $this->getState() == self::STATE_CANCELED;
    }

    /**
     * Check invice capture action availability
     *
     * @return bool
     */
    public function canCapture()
    {
        $payment = $this->getOrder()->getPayment();
        return $this->getState() != self::STATE_CANCELED
            && $this->getState() != self::STATE_PAID
            && $payment !== false
            && $payment->canCapture();
    }

    /**
     * Check invice void action availability
     *
     * @return bool
     */
    public function canVoid()
    {
        $canVoid = false;
        if ($this->getState() == self::STATE_PAID) {
            $canVoid = $this->getCanVoidFlag();
            /**
             * If we not retrieve negative answer from payment yet
             */
            if (is_null($canVoid)) {
                $payment = $this->getOrder()->getPayment();
                $canVoid = $payment !== false ? $payment->canVoid($this) : false;
                if ($canVoid === false) {
                    $this->setCanVoidFlag(false);
                    $this->_saveBeforeDestruct = true;
                    register_shutdown_function([$this, 'destruct']);
                }
            } else {
                $canVoid = (bool) $canVoid;
            }
        }
        return $canVoid;
    }

    /**
     * Check invoice cancel action availability
     *
     * @return bool
     */
    public function canCancel()
    {
        return $this->getState() == self::STATE_OPEN;
    }

    /**
     * Check invoice refund action availability
     *
     * @return bool
     */
    public function canRefund()
    {
        if ($this->getState() != self::STATE_PAID) {
            return false;
        }
        if (abs($this->getBaseGrandTotal() - $this->getBaseTotalRefunded()) < .0001) {
            return false;
        }
        return true;
    }

    /**
     * Capture invoice
     *
     * @return $this
     */
    public function capture()
    {
        $this->getOrder()->getPayment()->capture($this);
        if ($this->getIsPaid()) {
            $this->pay();
        }
        return $this;
    }

    /**
     * Pay invoice
     *
     * @return $this
     */
    public function pay()
    {
        if ($this->_wasPayCalled) {
            return $this;
        }
        $this->_wasPayCalled = true;

        $invoiceState = self::STATE_PAID;
        if ($this->getOrder()->getPayment()->hasForcedState()) {
            $invoiceState = $this->getOrder()->getPayment()->getForcedState();
        }

        $this->setState($invoiceState);

        $this->getOrder()->getPayment()->pay($this);
        $this->getOrder()->setTotalPaid(
            $this->getOrder()->getTotalPaid() + $this->getGrandTotal(),
        );
        $this->getOrder()->setBaseTotalPaid(
            $this->getOrder()->getBaseTotalPaid() + $this->getBaseGrandTotal(),
        );
        Mage::dispatchEvent('sales_order_invoice_pay', [$this->_eventObject => $this]);
        return $this;
    }

    /**
     * Whether pay() method was called (whether order and payment totals were updated)
     * @return bool
     */
    public function wasPayCalled()
    {
        return $this->_wasPayCalled;
    }

    /**
     * Void invoice
     *
     * @return $this
     */
    public function void()
    {
        $this->getOrder()->getPayment()->void($this);
        $this->cancel();
        return $this;
    }

    /**
     * Cancel invoice action
     *
     * @return $this
     */
    public function cancel()
    {
        $order = $this->getOrder();
        $order->getPayment()->cancelInvoice($this);
        foreach ($this->getAllItems() as $item) {
            $item->cancel();
        }

        /**
         * Unregister order totals only for invoices in state PAID
         */
        $order->setTotalInvoiced($order->getTotalInvoiced() - $this->getGrandTotal());
        $order->setBaseTotalInvoiced($order->getBaseTotalInvoiced() - $this->getBaseGrandTotal());

        $order->setSubtotalInvoiced($order->getSubtotalInvoiced() - $this->getSubtotal());
        $order->setBaseSubtotalInvoiced($order->getBaseSubtotalInvoiced() - $this->getBaseSubtotal());

        $order->setTaxInvoiced($order->getTaxInvoiced() - $this->getTaxAmount());
        $order->setBaseTaxInvoiced($order->getBaseTaxInvoiced() - $this->getBaseTaxAmount());

        $order->setHiddenTaxInvoiced($order->getHiddenTaxInvoiced() - $this->getHiddenTaxAmount());
        $order->setBaseHiddenTaxInvoiced($order->getBaseHiddenTaxInvoiced() - $this->getBaseHiddenTaxAmount());

        $order->setShippingTaxInvoiced($order->getShippingTaxInvoiced() - $this->getShippingTaxAmount());
        $order->setBaseShippingTaxInvoiced($order->getBaseShippingTaxInvoiced() - $this->getBaseShippingTaxAmount());

        $order->setShippingInvoiced($order->getShippingInvoiced() - $this->getShippingAmount());
        $order->setBaseShippingInvoiced($order->getBaseShippingInvoiced() - $this->getBaseShippingAmount());

        $order->setDiscountInvoiced($order->getDiscountInvoiced() - $this->getDiscountAmount());
        $order->setBaseDiscountInvoiced($order->getBaseDiscountInvoiced() - $this->getBaseDiscountAmount());
        $order->setBaseTotalInvoicedCost($order->getBaseTotalInvoicedCost() - $this->getBaseCost());

        if ($this->getState() == self::STATE_PAID) {
            $this->getOrder()->setTotalPaid($this->getOrder()->getTotalPaid() - $this->getGrandTotal());
            $this->getOrder()->setBaseTotalPaid($this->getOrder()->getBaseTotalPaid() - $this->getBaseGrandTotal());
        }
        $this->setState(self::STATE_CANCELED);
        $this->getOrder()->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true);
        Mage::dispatchEvent('sales_order_invoice_cancel', [$this->_eventObject => $this]);
        return $this;
    }

    /**
     * Invoice totals collecting
     *
     * @return $this
     */
    public function collectTotals()
    {
        foreach ($this->getConfig()->getTotalModels() as $model) {
            $model->collect($this);
        }
        return $this;
    }

    /**
     * Round price considering delta
     *
     * @param float $price
     * @param string $type
     * @param bool $negative Indicates if we perform addition (true) or subtraction (false) of rounded value
     * @return float
     */
    public function roundPrice($price, $type = 'regular', $negative = false)
    {
        if ($price) {
            $this->_rounders[$type] ??= Mage::getModel('core/calculator', $this->getStore());
            $price = $this->_rounders[$type]->deltaRound($price, $negative);
        }
        return $price;
    }

    /**
     * Get invoice items collection
     *
     * @return Mage_Sales_Model_Resource_Order_Invoice_Item_Collection
     */
    public function getItemsCollection()
    {
        if (empty($this->_items)) {
            $this->_items = Mage::getResourceModel('sales/order_invoice_item_collection')
                ->setInvoiceFilter($this->getId());

            if ($this->getId()) {
                foreach ($this->_items as $item) {
                    $item->setInvoice($this);
                }
            }
        }
        return $this->_items;
    }

    /**
     * @return Mage_Sales_Model_Order_Invoice_Item[]
     */
    public function getAllItems()
    {
        $items = [];
        foreach ($this->getItemsCollection() as $item) {
            if (!$item->isDeleted()) {
                $items[] =  $item;
            }
        }
        return $items;
    }

    /**
     * @param int|string $itemId
     * @return false|Mage_Sales_Model_Order_Invoice_Item
     */
    public function getItemById($itemId)
    {
        foreach ($this->getItemsCollection() as $item) {
            if ($item->getId() == $itemId) {
                return $item;
            }
        }
        return false;
    }

    /**
     * @return $this
     * @throws Exception
     */
    public function addItem(Mage_Sales_Model_Order_Invoice_Item $item)
    {
        $item->setInvoice($this)
            ->setParentId($this->getId())
            ->setStoreId($this->getStoreId());

        if (!$item->getId()) {
            $this->getItemsCollection()->addItem($item);
        }
        return $this;
    }

    /**
     * Retrieve invoice states array
     *
     * @return array
     */
    public static function getStates()
    {
        self::$_states ??= [
            self::STATE_OPEN       => Mage::helper('sales')->__('Pending'),
            self::STATE_PAID       => Mage::helper('sales')->__('Paid'),
            self::STATE_CANCELED   => Mage::helper('sales')->__('Canceled'),
        ];
        return self::$_states;
    }

    /**
     * Retrieve invoice state name by state identifier
     *
     * @param   int $stateId
     * @return  string
     */
    public function getStateName($stateId = null)
    {
        $stateId ??= $this->getState();

        if (is_null(self::$_states)) {
            self::getStates();
        }
        return self::$_states[$stateId] ?? Mage::helper('sales')->__('Unknown State');
    }

    /**
     * Register invoice
     *
     * Apply to order, order items etc.
     *
     * @return $this
     */
    public function register()
    {
        if ($this->getId()) {
            Mage::throwException(Mage::helper('sales')->__('Cannot register existing invoice'));
        }

        foreach ($this->getAllItems() as $item) {
            if ($item->getQty() > 0) {
                $item->register();
            } else {
                $item->isDeleted(true);
            }
        }

        $order = $this->getOrder();
        $captureCase = $this->getRequestedCaptureCase();
        if ($this->canCapture()) {
            if ($captureCase) {
                if ($captureCase == self::CAPTURE_ONLINE) {
                    $this->capture();
                } elseif ($captureCase == self::CAPTURE_OFFLINE) {
                    $this->setCanVoidFlag(false);
                    $this->pay();
                }
            }
        } elseif (!$order->getPayment()->getMethodInstance()->isGateway() || $captureCase == self::CAPTURE_OFFLINE) {
            if (!$order->getPayment()->getIsTransactionPending()) {
                $this->setCanVoidFlag(false);
                $this->pay();
            }
        }

        $order->setTotalInvoiced($order->getTotalInvoiced() + $this->getGrandTotal());
        $order->setBaseTotalInvoiced($order->getBaseTotalInvoiced() + $this->getBaseGrandTotal());

        $order->setSubtotalInvoiced($order->getSubtotalInvoiced() + $this->getSubtotal());
        $order->setBaseSubtotalInvoiced($order->getBaseSubtotalInvoiced() + $this->getBaseSubtotal());

        $order->setTaxInvoiced($order->getTaxInvoiced() + $this->getTaxAmount());
        $order->setBaseTaxInvoiced($order->getBaseTaxInvoiced() + $this->getBaseTaxAmount());

        $order->setHiddenTaxInvoiced($order->getHiddenTaxInvoiced() + $this->getHiddenTaxAmount());
        $order->setBaseHiddenTaxInvoiced($order->getBaseHiddenTaxInvoiced() + $this->getBaseHiddenTaxAmount());

        $order->setShippingTaxInvoiced($order->getShippingTaxInvoiced() + $this->getShippingTaxAmount());
        $order->setBaseShippingTaxInvoiced($order->getBaseShippingTaxInvoiced() + $this->getBaseShippingTaxAmount());

        $order->setShippingInvoiced($order->getShippingInvoiced() + $this->getShippingAmount());
        $order->setBaseShippingInvoiced($order->getBaseShippingInvoiced() + $this->getBaseShippingAmount());

        $order->setDiscountInvoiced($order->getDiscountInvoiced() + $this->getDiscountAmount());
        $order->setBaseDiscountInvoiced($order->getBaseDiscountInvoiced() + $this->getBaseDiscountAmount());
        $order->setBaseTotalInvoicedCost($order->getBaseTotalInvoicedCost() + $this->getBaseCost());

        $state = $this->getState();
        if (is_null($state)) {
            $this->setState(self::STATE_OPEN);
        }

        Mage::dispatchEvent('sales_order_invoice_register', [$this->_eventObject => $this, 'order' => $order]);
        return $this;
    }

    /**
     * Checking if the invoice is last
     *
     * @return bool
     */
    public function isLast()
    {
        foreach ($this->getAllItems() as $item) {
            $orderItem = $item->getOrderItem();
            if ($orderItem->isDummy()) {
                continue;
            }

            if (!$item->isLast()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Adds comment to invoice with additional possibility to send it to customer via email
     * and show it in customer account
     *
     * @param Mage_Sales_Model_Order_Invoice_Comment|string $comment
     * @param bool $notify
     * @param bool $visibleOnFront
     *
     * @return $this
     */
    public function addComment($comment, $notify = false, $visibleOnFront = false)
    {
        if (!($comment instanceof Mage_Sales_Model_Order_Invoice_Comment)) {
            $comment = Mage::getModel('sales/order_invoice_comment')
                ->setComment($comment)
                ->setIsCustomerNotified((bool) $notify)
                ->setIsVisibleOnFront($visibleOnFront);
        }
        $comment->setInvoice($this)
            ->setStoreId($this->getStoreId())
            ->setParentId($this->getId());
        if (!$comment->getId()) {
            $this->getCommentsCollection()->addItem($comment);
        }
        $this->_hasDataChanges = true;
        return $this;
    }

    /**
     * @param bool $reload
     * @return Mage_Sales_Model_Resource_Order_Comment_Collection_Abstract
     */
    public function getCommentsCollection($reload = false)
    {
        if (is_null($this->_comments) || $reload) {
            $this->_comments = Mage::getResourceModel('sales/order_invoice_comment_collection')
                ->setInvoiceFilter($this->getId())
                ->setCreatedAtOrder();
            /**
             * When invoice created with adding comment, comments collection
             * must be loaded before we added this comment.
             */
            $this->_comments->load();

            if ($this->getId()) {
                foreach ($this->_comments as $comment) {
                    $comment->setInvoice($this);
                }
            }
        }
        return $this->_comments;
    }

    /**
     * Send email with invoice data
     *
     * @param bool $notifyCustomer
     * @param string $comment
     * @return $this
     */
    public function sendEmail($notifyCustomer = true, $comment = '')
    {
        $order = $this->getOrder();
        $storeId = $order->getStore()->getId();

        if (!Mage::helper('sales')->canSendNewInvoiceEmail($storeId)) {
            return $this;
        }
        // Get the destination email addresses to send copies to
        $copyTo = $this->_getEmails(self::XML_PATH_EMAIL_COPY_TO);
        $copyMethod = Mage::getStoreConfig(self::XML_PATH_EMAIL_COPY_METHOD, $storeId);
        // Check if at least one recipient is found
        if (!$notifyCustomer && !$copyTo) {
            return $this;
        }

        // Start store emulation process
        if ($storeId != Mage::app()->getStore()->getId()) {
            $appEmulation = Mage::getSingleton('core/app_emulation');
            $initialEnvironmentInfo = $appEmulation->startEnvironmentEmulation($storeId);
        }

        try {
            // Retrieve specified view block from appropriate design package (depends on emulated store)
            $paymentBlock = Mage::helper('payment')->getInfoBlock($order->getPayment())
                ->setIsSecureMode(true);
            $paymentBlock->getMethod()->setStore($storeId);
            $paymentBlockHtml = $paymentBlock->toHtml();
        } catch (Exception $e) {
            // Stop store emulation process
            if (isset($appEmulation, $initialEnvironmentInfo)) {
                $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
            }
            throw $e;
        }

        // Stop store emulation process
        if (isset($appEmulation, $initialEnvironmentInfo)) {
            $appEmulation->stopEnvironmentEmulation($initialEnvironmentInfo);
        }

        // Retrieve corresponding email template id and customer name
        if ($order->getCustomerIsGuest()) {
            $templateId = Mage::getStoreConfig(self::XML_PATH_EMAIL_GUEST_TEMPLATE, $storeId);
            $customerName = $order->getBillingAddress()->getName();
        } else {
            $templateId = Mage::getStoreConfig(self::XML_PATH_EMAIL_TEMPLATE, $storeId);
            $customerName = $order->getCustomerName();
        }

        $attachPdf = Mage::getStoreConfigFlag(self::XML_PATH_EMAIL_ATTACH_PDF, $storeId);

        $mailer = Mage::getModel('core/email_template_mailer');
        if ($notifyCustomer) {
            $emailInfo = Mage::getModel('core/email_info');
            $emailInfo->addTo($order->getCurrentCustomerEmail(), $customerName);
            if ($copyTo && $copyMethod == 'bcc') {
                // Add bcc to customer email
                foreach ($copyTo as $email) {
                    $emailInfo->addBcc($email);
                }
            }
            if ($attachPdf) {
                $this->_addPdfAttachment($emailInfo);
            }
            $mailer->addEmailInfo($emailInfo);
        }

        // Email copies are sent as separated emails if their copy method is 'copy' or a customer should not be notified
        if ($copyTo && ($copyMethod == 'copy' || !$notifyCustomer)) {
            foreach ($copyTo as $email) {
                $emailInfo = Mage::getModel('core/email_info');
                $emailInfo->addTo($email);
                if ($attachPdf) {
                    $this->_addPdfAttachment($emailInfo);
                }
                $mailer->addEmailInfo($emailInfo);
            }
        }

        // Set all required params and send emails
        $mailer->setSender(Mage::getStoreConfig(self::XML_PATH_EMAIL_IDENTITY, $storeId));
        $mailer->setStoreId($storeId);
        $mailer->setTemplateId($templateId);
        $mailer->setTemplateParams([
            'order'        => $order,
            'invoice'      => $this,
            'comment'      => $comment,
            'billing'      => $order->getBillingAddress(),
            'payment_html' => $paymentBlockHtml,
        ]);
        $mailer->send();

        if ($notifyCustomer) {
            $this->setEmailSent();
            $this->_getResource()->saveAttribute($this, 'email_sent');
        }

        return $this;
    }

    #[\Override]
    protected function _getPdfAttachmentInfo(): ?array
    {
        return [
            'source_model'    => 'sales/order_pdf_invoice',
            'entity_model'    => 'sales/order_invoice',
            'filename_prefix' => 'invoice',
        ];
    }

    /**
     * Send email with invoice update information
     *
     * @param bool $notifyCustomer
     * @param string $comment
     * @return $this
     */
    public function sendUpdateEmail($notifyCustomer = true, $comment = '')
    {
        $order = $this->getOrder();
        $storeId = $order->getStore()->getId();

        if (!Mage::helper('sales')->canSendInvoiceCommentEmail($storeId)) {
            return $this;
        }
        // Get the destination email addresses to send copies to
        $copyTo = $this->_getEmails(self::XML_PATH_UPDATE_EMAIL_COPY_TO);
        $copyMethod = Mage::getStoreConfig(self::XML_PATH_UPDATE_EMAIL_COPY_METHOD, $storeId);
        // Check if at least one recipient is found
        if (!$notifyCustomer && !$copyTo) {
            return $this;
        }

        // Retrieve corresponding email template id and customer name
        if ($order->getCustomerIsGuest()) {
            $templateId = Mage::getStoreConfig(self::XML_PATH_UPDATE_EMAIL_GUEST_TEMPLATE, $storeId);
            $customerName = $order->getBillingAddress()->getName();
        } else {
            $templateId = Mage::getStoreConfig(self::XML_PATH_UPDATE_EMAIL_TEMPLATE, $storeId);
            $customerName = $order->getCustomerName();
        }

        $mailer = Mage::getModel('core/email_template_mailer');
        if ($notifyCustomer) {
            $emailInfo = Mage::getModel('core/email_info');
            $emailInfo->addTo($order->getCurrentCustomerEmail(), $customerName);
            if ($copyTo && $copyMethod == 'bcc') {
                // Add bcc to customer email
                foreach ($copyTo as $email) {
                    $emailInfo->addBcc($email);
                }
            }
            $mailer->addEmailInfo($emailInfo);
        }

        // Email copies are sent as separated emails if their copy method is 'copy' or a customer should not be notified
        if ($copyTo && ($copyMethod == 'copy' || !$notifyCustomer)) {
            foreach ($copyTo as $email) {
                $emailInfo = Mage::getModel('core/email_info');
                $emailInfo->addTo($email);
                $mailer->addEmailInfo($emailInfo);
            }
        }

        // Set all required params and send emails
        $mailer->setSender(Mage::getStoreConfig(self::XML_PATH_UPDATE_EMAIL_IDENTITY, $storeId));
        $mailer->setStoreId($storeId);
        $mailer->setTemplateId($templateId);
        $mailer->setTemplateParams([
            'order'        => $order,
            'invoice'      => $this,
            'comment'      => $comment,
            'billing'      => $order->getBillingAddress(),
        ]);
        $mailer->send();

        return $this;
    }

    /**
     * @param string $configPath
     * @return array|bool
     */
    protected function _getEmails($configPath)
    {
        $data = Mage::getStoreConfig($configPath, $this->getStoreId());
        if (!empty($data)) {
            return explode(',', $data);
        }
        return false;
    }

    /**
     * @return Mage_Sales_Model_Abstract
     * @throws Mage_Core_Exception
     */
    #[\Override]
    protected function _beforeDelete()
    {
        $this->_protectFromNonAdmin();
        return parent::_beforeDelete();
    }

    /**
     * Reset invoice object
     *
     * @return $this
     */
    public function reset()
    {
        $this->unsetData();
        $this->_origData = null;
        $this->_items = null;
        $this->_comments = null;
        $this->_order = null;
        $this->_saveBeforeDestruct = false;
        $this->_wasPayCalled = false;
        return $this;
    }

    /**
     * Before object save manipulations
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        parent::_beforeSave();

        if (!$this->getOrderId() && $this->getOrder()) {
            $this->setOrderId($this->getOrder()->getId());
            $this->setBillingAddressId($this->getOrder()->getBillingAddress()->getId());
        }

        return $this;
    }

    #[\Override]
    protected function _afterSave()
    {
        if ($this->_items !== null) {
            /**
             * Save invoice items
             */
            foreach ($this->_items as $item) {
                $item->setOrderItem($item->getOrderItem());
                $item->save();
            }
        }

        if ($this->_comments !== null) {
            foreach ($this->_comments as $comment) {
                $comment->save();
            }
        }

        return parent::_afterSave();
    }

    /**
     * Get total quantity with proper float casting
     * DBAL returns DECIMAL as string, so we cast to float
     */
    public function getTotalQty(): ?float
    {
        $value = $this->getData('total_qty');
        return $value !== null ? (float) $value : null;
    }

    /**
     * Get discount amount with proper float casting
     * DBAL returns DECIMAL as string, so we cast to float
     */
    public function getDiscountAmount(): ?float
    {
        $value = $this->getData('discount_amount');
        return $value !== null ? (float) $value : null;
    }

    /**
     * Get shipping amount with proper float casting
     * DBAL returns DECIMAL as string, so we cast to float
     */
    public function getShippingAmount(): ?float
    {
        $value = $this->getData('shipping_amount');
        return $value !== null ? (float) $value : null;
    }

    public function getBackUrl(): ?string
    {
        $value = $this->getData('back_url');
        return $value === null ? null : (string) $value;
    }

    public function getBaseCost(): ?float
    {
        $value = $this->getData('base_cost');
        return $value === null ? null : (float) $value;
    }

    public function setBaseCost(?float $value): static
    {
        return $this->setData('base_cost', $value);
    }

    public function getBaseCurrencyCode(): ?string
    {
        $value = $this->getData('base_currency_code');
        return $value === null ? null : (string) $value;
    }

    public function setBaseCurrencyCode(?string $value): static
    {
        return $this->setData('base_currency_code', $value);
    }

    public function getBaseDiscountAmount(): ?float
    {
        $value = $this->getData('base_discount_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseDiscountAmount(?float $value): static
    {
        return $this->setData('base_discount_amount', $value);
    }

    public function getBaseGrandTotal(): ?float
    {
        $value = $this->getData('base_grand_total');
        return $value === null ? null : (float) $value;
    }

    public function setBaseGrandTotal(?float $value): static
    {
        return $this->setData('base_grand_total', $value);
    }

    public function getBaseHiddenTaxAmount(): ?float
    {
        $value = $this->getData('base_hidden_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseHiddenTaxAmount(?float $value): static
    {
        return $this->setData('base_hidden_tax_amount', $value);
    }

    public function getBaseShippingAmount(): ?float
    {
        $value = $this->getData('base_shipping_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseShippingAmount(?float $value): static
    {
        return $this->setData('base_shipping_amount', $value);
    }

    public function getBaseShippingHiddenTaxAmount(): ?float
    {
        $value = $this->getData('base_shipping_hidden_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseShippingHiddenTaxAmount(?float $value): static
    {
        return $this->setData('base_shipping_hidden_tax_amount', $value);
    }

    public function getBaseShippingInclTax(): ?float
    {
        $value = $this->getData('base_shipping_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function setBaseShippingInclTax(?float $value): static
    {
        return $this->setData('base_shipping_incl_tax', $value);
    }

    public function getBaseShippingTaxAmount(): ?float
    {
        $value = $this->getData('base_shipping_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseShippingTaxAmount(?float $value): static
    {
        return $this->setData('base_shipping_tax_amount', $value);
    }

    public function getBaseSubtotal(): ?float
    {
        $value = $this->getData('base_subtotal');
        return $value === null ? null : (float) $value;
    }

    public function setBaseSubtotal(?float $value): static
    {
        return $this->setData('base_subtotal', $value);
    }

    public function getBaseSubtotalInclTax(): ?float
    {
        $value = $this->getData('base_subtotal_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function setBaseSubtotalInclTax(?float $value): static
    {
        return $this->setData('base_subtotal_incl_tax', $value);
    }

    public function getBaseTaxAmount(): ?float
    {
        $value = $this->getData('base_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setBaseTaxAmount(?float $value): static
    {
        return $this->setData('base_tax_amount', $value);
    }

    public function getBaseToGlobalRate(): ?float
    {
        $value = $this->getData('base_to_global_rate');
        return $value === null ? null : (float) $value;
    }

    public function setBaseToGlobalRate(?float $value): static
    {
        return $this->setData('base_to_global_rate', $value);
    }

    public function getBaseToOrderRate(): ?float
    {
        $value = $this->getData('base_to_order_rate');
        return $value === null ? null : (float) $value;
    }

    public function setBaseToOrderRate(?float $value): static
    {
        return $this->setData('base_to_order_rate', $value);
    }

    public function getBaseTotalRefunded(): ?float
    {
        $value = $this->getData('base_total_refunded');
        return $value === null ? null : (float) $value;
    }

    public function setBaseTotalRefunded(?float $value): static
    {
        return $this->setData('base_total_refunded', $value);
    }

    public function getBillingAddressId(): ?int
    {
        $value = $this->getData('billing_address_id');
        return $value === null ? null : (int) $value;
    }

    public function setBillingAddressId(?int $value): static
    {
        return $this->setData('billing_address_id', $value);
    }

    public function getCanVoidFlag(): ?bool
    {
        $value = $this->getData('can_void_flag');
        return $value === null ? null : (bool) $value;
    }

    public function setCanVoidFlag(?bool $value = true): static
    {
        return $this->setData('can_void_flag', $value);
    }

    public function setCustomerId(?int $value): static
    {
        return $this->setData('customer_id', $value);
    }

    public function getCybersourceToken(): ?string
    {
        $value = $this->getData('cybersource_token');
        return $value === null ? null : (string) $value;
    }

    public function setCybersourceToken(?string $value): static
    {
        return $this->setData('cybersource_token', $value);
    }

    public function setDiscountAmount(?float $value): static
    {
        return $this->setData('discount_amount', $value);
    }

    public function getEmailSent(): ?bool
    {
        $value = $this->getData('email_sent');
        return $value === null ? null : (bool) $value;
    }

    public function setEmailSent(?bool $value = true): static
    {
        return $this->setData('email_sent', $value);
    }

    public function getGlobalCurrencyCode(): ?string
    {
        $value = $this->getData('global_currency_code');
        return $value === null ? null : (string) $value;
    }

    public function setGlobalCurrencyCode(?string $value): static
    {
        return $this->setData('global_currency_code', $value);
    }

    public function getGrandTotal(): ?float
    {
        $value = $this->getData('grand_total');
        return $value === null ? null : (float) $value;
    }

    public function setGrandTotal(?float $value): static
    {
        return $this->setData('grand_total', $value);
    }

    public function getHiddenTaxAmount(): ?float
    {
        $value = $this->getData('hidden_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setHiddenTaxAmount(?float $value): static
    {
        return $this->setData('hidden_tax_amount', $value);
    }

    public function getIncrementId(): ?string
    {
        $value = $this->getData('increment_id');
        return $value === null ? null : (string) $value;
    }

    public function setIncrementId(?string $value): static
    {
        return $this->setData('increment_id', $value);
    }

    public function getIsPaid(): ?bool
    {
        $value = $this->getData('is_paid');
        return $value === null ? null : (bool) $value;
    }

    public function setIsPaid(?bool $value = true): static
    {
        return $this->setData('is_paid', $value);
    }

    public function getIsUsedForRefund(): ?bool
    {
        $value = $this->getData('is_used_for_refund');
        return $value === null ? null : (bool) $value;
    }

    public function setIsUsedForRefund(?bool $value = true): static
    {
        return $this->setData('is_used_for_refund', $value);
    }

    public function getOrderCurrencyCode(): ?string
    {
        $value = $this->getData('order_currency_code');
        return $value === null ? null : (string) $value;
    }

    public function setOrderCurrencyCode(?string $value): static
    {
        return $this->setData('order_currency_code', $value);
    }

    public function getOrderId(): ?int
    {
        $value = $this->getData('order_id');
        return $value === null ? null : (int) $value;
    }

    public function setOrderId(?int $value): static
    {
        return $this->setData('order_id', $value);
    }

    public function getRequestedCaptureCase(): ?string
    {
        $value = $this->getData('requested_capture_case');
        return $value === null ? null : (string) $value;
    }

    public function getShippingAddressId(): ?int
    {
        $value = $this->getData('shipping_address_id');
        return $value === null ? null : (int) $value;
    }

    public function setShippingAddressId(?int $value): static
    {
        return $this->setData('shipping_address_id', $value);
    }

    public function setShippingAmount(?float $value): static
    {
        return $this->setData('shipping_amount', $value);
    }

    public function getShippingHiddenTaxAmount(): ?float
    {
        $value = $this->getData('shipping_hidden_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setShippingHiddenTaxAmount(?float $value): static
    {
        return $this->setData('shipping_hidden_tax_amount', $value);
    }

    public function getShippingInclTax(): ?float
    {
        $value = $this->getData('shipping_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function setShippingInclTax(?float $value): static
    {
        return $this->setData('shipping_incl_tax', $value);
    }

    public function getShippingTaxAmount(): ?float
    {
        $value = $this->getData('shipping_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setShippingTaxAmount(?float $value): static
    {
        return $this->setData('shipping_tax_amount', $value);
    }

    public function getState(): ?int
    {
        $value = $this->getData('state');
        return $value === null ? null : (int) $value;
    }

    public function setState(?int $value): static
    {
        return $this->setData('state', $value);
    }

    public function getStoreCurrencyCode(): ?string
    {
        $value = $this->getData('store_currency_code');
        return $value === null ? null : (string) $value;
    }

    public function setStoreCurrencyCode(?string $value): static
    {
        return $this->setData('store_currency_code', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function getStoreToBaseRate(): ?float
    {
        $value = $this->getData('store_to_base_rate');
        return $value === null ? null : (float) $value;
    }

    public function setStoreToBaseRate(?float $value): static
    {
        return $this->setData('store_to_base_rate', $value);
    }

    public function getStoreToOrderRate(): ?float
    {
        $value = $this->getData('store_to_order_rate');
        return $value === null ? null : (float) $value;
    }

    public function setStoreToOrderRate(?float $value): static
    {
        return $this->setData('store_to_order_rate', $value);
    }

    public function getSubtotal(): ?float
    {
        $value = $this->getData('subtotal');
        return $value === null ? null : (float) $value;
    }

    public function setSubtotal(?float $value): static
    {
        return $this->setData('subtotal', $value);
    }

    public function getSubtotalInclTax(): ?float
    {
        $value = $this->getData('subtotal_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function setSubtotalInclTax(?float $value): static
    {
        return $this->setData('subtotal_incl_tax', $value);
    }

    public function getTaxAmount(): ?float
    {
        $value = $this->getData('tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function setTaxAmount(?float $value): static
    {
        return $this->setData('tax_amount', $value);
    }

    public function setTotalQty(?float $value): static
    {
        return $this->setData('total_qty', $value);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }

}
