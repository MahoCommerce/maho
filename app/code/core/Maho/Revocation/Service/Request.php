<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

use Maho_Revocation_Model_Channel as Channel;

/**
 * Records a revocation declaration and dispatches the receipt/notification emails.
 *
 * Input keys of submit(): customer_name, email, order_reference, reason, ip, user_agent, locale,
 * store_id, received_at_microtime (float), customer_id (?int), session_order_id (?int).
 * The web channel adds form_fields (the POST, for the honeypot) and render_token (the frt field).
 * The API channel gives customer_id and order_id or order_reference. The service then takes the
 * name, the email and the store from the customer and the order.
 */
class Maho_Revocation_Service_Request
{
    public const RATE_LIMIT_WINDOW = 3600;

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id): Maho_Revocation_Model_Request
    {
        $request = Mage::getModel('revocation/request')->load($id);
        if (!$request->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('revocation')->__('Revocation request not found'));
        }
        return $request;
    }

    /**
     * @throws Mage_Core_Exception_Input when the processing status is not valid
     */
    public function save(Maho_Revocation_Model_Request $request): Maho_Revocation_Model_Request
    {
        $request->save();
        return $request;
    }

    /**
     * Save the processing status $status of $request. When the status changes to accepted or rejected, add a
     * note to the history of the matched order. The status and the state of the order do not change.
     *
     * @throws Mage_Core_Exception_Input when the processing status is not valid
     */
    public function decide(Maho_Revocation_Model_Request $request, string $status): Maho_Revocation_Model_Request
    {
        $changed = $request->setProcessedStatus($status)->dataHasChangedFor('processed_status');
        $this->save($request);
        if ($changed) {
            try {
                $this->_addDecisionComment($request);
            } catch (Throwable $e) {
                Mage::logException($e);
            }
        }
        return $request;
    }

    /**
     * Record the declaration in $input and send its emails. Return null and record nothing when the
     * web form fills the honeypot or comes back too fast, so that a bot cannot detect the trap.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when revocation is disabled, or the customer of the API does not exist
     * @throws Mage_Core_Exception_TooManyRequests when the client is over its limit
     * @throws Mage_Core_Exception on validation failure (before any row is written)
     */
    public function submit(\Maho\DataObject $input, Channel $channel): ?Maho_Revocation_Model_Request
    {
        $helper = Mage::helper('revocation');
        $coreHelper = Mage::helper('core');

        $input->setStoreId((int) ($input->getStoreId() ?? Mage::app()->getStore()->getId()));
        if (!$helper->isEnabled($input->getStoreId())) {
            throw new Mage_Core_Exception_NoSuchEntity($helper->__('Revocation is not available'));
        }
        if ($channel === Channel::Web && $this->_isBotSubmission($input)) {
            return null;
        }
        if ($channel === Channel::Api) {
            $this->_readCustomerOrder($input);
        }
        if ($helper->isIpRateLimited($input->getStoreId())) {
            throw new Mage_Core_Exception_TooManyRequests(match ($channel) {
                Channel::Web => $helper->__('Your request could not be processed right now. Please try again later or contact us directly.'),
                Channel::Api => $helper->__('Too many requests. Please try again later.'),
            }, self::RATE_LIMIT_WINDOW);
        }

        $customerName = trim((string) $input->getCustomerName());
        $email = trim((string) $input->getEmail());
        $orderReference = trim((string) $input->getOrderReference());

        if ($customerName === '' || $orderReference === '' || $email === '') {
            Mage::throwException($helper->__('Please fill in your name, your order reference and your email address.'));
        }
        if (!$coreHelper->isValidEmail($email)) {
            Mage::throwException($helper->__('Please enter a valid email address.'));
        }

        [$orderId, $verified] = $this->_resolveOrder($input, $customerName, $email, $orderReference);

        $receivedMicrotime = (float) ($input->getReceivedAtMicrotime() ?: microtime(true));

        $request = Mage::getModel('revocation/request');
        $request->setStoreId((int) $input->getStoreId())
            ->setOrderId($orderId)
            ->setOrderReference(mb_substr($orderReference, 0, 64))
            ->setCustomerName(mb_substr($customerName, 0, 255))
            ->setEmail(mb_substr($email, 0, 255))
            ->setReason(trim((string) $input->getReason()) !== '' ? mb_substr(trim((string) $input->getReason()), 0, 2000) : null)
            ->setVerified($verified)
            ->setReceivedAt((string) Mage::app()->getLocale()->formatDateForDb((int) $receivedMicrotime))
            ->setIp(mb_substr((string) $input->getIp(), 0, 45) ?: null)
            ->setUserAgent(mb_substr((string) $input->getUserAgent(), 0, 512) ?: null)
            ->setLocale(mb_substr((string) $input->getLocale(), 0, 16) ?: null);

        // The row is the legal receipt: nothing after this insert may abort the flow.
        $request->save();

        try {
            $this->_sendEmails($request);
        } catch (Throwable $e) {
            Mage::logException($e);
        }

        if ($verified && $orderId) {
            try {
                $this->_addOrderHistoryComment($request);
            } catch (Throwable $e) {
                Mage::logException($e);
            }
        }

        return $request;
    }

    /**
     * Sends the receipt email and clears the suppression flag, recording the resend.
     */
    public function resendReceipt(Maho_Revocation_Model_Request $request): bool
    {
        $sent = Mage::getModel('revocation/email')->sendReceipt($request);
        if ($sent) {
            $request->setSuppressedAt(null)
                ->setSuppressedReason(null)
                ->appendAdminNote('Receipt email resent to ' . $request->getEmail())
                ->save();
        }
        return $sent;
    }

    /**
     * Whether the web form filled the honeypot or came back faster than the minimum time.
     * The form embeds an encrypted render timestamp. A missing or unreadable one counts as too fast.
     */
    protected function _isBotSubmission(\Maho\DataObject $input): bool
    {
        $storeId = (int) $input->getStoreId();
        $fields = $input->getFormFields();
        if (Mage::getStoreConfigFlag(Maho_Revocation_Helper_Data::XML_PATH_HONEYPOT_ENABLED, $storeId)
            && Mage::helper('core')->isHoneypotTriggered(is_array($fields) ? $fields : [])) {
            return true;
        }

        $minSeconds = (int) Mage::getStoreConfig(Maho_Revocation_Helper_Data::XML_PATH_MIN_SUBMIT_SECONDS, $storeId);
        if ($minSeconds <= 0) {
            return false;
        }
        $renderedAt = Mage::helper('core')->decrypt((string) $input->getRenderToken());
        return !ctype_digit($renderedAt)
            || ((float) $input->getReceivedAtMicrotime() - (float) $renderedAt) < $minSeconds;
    }

    /**
     * Fill the declaration of the API from the account of the customer. An order of the customer gives a verified
     * declaration, as the link on the order page of the storefront does. Any other order, and an order after its
     * revocation period, is recorded too, as on the public form: the merchant decides in the admin.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the customer does not exist
     * @throws Mage_Core_Exception when the input names no order
     */
    protected function _readCustomerOrder(\Maho\DataObject $input): void
    {
        $helper = Mage::helper('revocation');
        $customer = Mage::getService('customer/customer')->getById((int) $input->getCustomerId());
        if (!$customer) {
            throw new Mage_Core_Exception_NoSuchEntity($helper->__('Customer not found'));
        }

        $order = Mage::getModel('sales/order');
        $orderReference = trim((string) $input->getOrderReference());
        if ($input->getOrderId()) {
            $order->load((int) $input->getOrderId());
            $orderReference = $orderReference !== '' ? $orderReference : (string) (int) $input->getOrderId();
        } elseif ($orderReference !== '') {
            $order->loadByIncrementId($orderReference);
        } else {
            Mage::throwException($helper->__('An order reference is required'));
        }

        $input->addData([
            'customer_name' => trim($customer->getName() ?: $customer->getFirstname() . ' ' . $customer->getLastname()),
            'email' => (string) $customer->getEmail(),
            'order_reference' => $orderReference,
        ]);
        if ($order->getId() && (int) $order->getCustomerId() === (int) $customer->getId()) {
            $input->addData([
                'order_reference' => (string) $order->getIncrementId(),
                'store_id' => (int) $order->getStoreId(),
                'session_order_id' => (int) $order->getId(),
            ]);
        }
    }

    /**
     * @return array{0: ?int, 1: int} [order entity id or null, verified flag]
     */
    protected function _resolveOrder(\Maho\DataObject $input, string $customerName, #[\SensitiveParameter]
        string $email, string $orderReference): array
    {
        // Session-authenticated path: the customer owns the order, full trust.
        $customerId = (int) $input->getCustomerId();
        $sessionOrderId = (int) $input->getSessionOrderId();
        if ($customerId && $sessionOrderId) {
            $order = Mage::getModel('sales/order')->load($sessionOrderId);
            if ($order->getId() && (int) $order->getCustomerId() === $customerId) {
                return [(int) $order->getId(), 1];
            }
            return [null, 0];
        }

        // Public path: best-effort match, all three of order reference, name and email
        // must agree. A partial match never links the order: linking is what later
        // allows admin status changes, and an unverified assertion must not enable that.
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderReference);
        if (!$order->getId()) {
            return [null, 0];
        }

        $emailMatches = strcasecmp(trim((string) $order->getCustomerEmail()), $email) === 0;

        $billingAddress = $order->getBillingAddress();
        $lastname = $billingAddress ? trim((string) $billingAddress->getLastname()) : '';
        $nameMatches = $lastname !== '' && mb_stripos($customerName, $lastname) !== false;

        if ($emailMatches && $nameMatches) {
            return [(int) $order->getId(), 0];
        }

        return [null, 0];
    }

    protected function _sendEmails(Maho_Revocation_Model_Request $request): void
    {
        $helper = Mage::helper('revocation');
        $emailModel = Mage::getModel('revocation/email');
        $store = Mage::app()->getStore($request->getStoreId());

        if ($helper->isRecipientRateLimited($request->getEmail(), $store)) {
            // Still a valid legal receipt: the row stands and the merchant is notified,
            // only the courtesy email to the (possibly victimized) address is withheld.
            $request->setSuppressedAt($request->getReceivedAt())
                ->setSuppressedReason(Maho_Revocation_Model_Request::SUPPRESSED_REASON_RATE_LIMIT)
                ->save();
            $request->setData('receipt_email_sent', false);
        } else {
            $request->setData('receipt_email_sent', $emailModel->sendReceipt($request));
        }

        if ($helper->isMerchantNotificationRateLimited((int) $request->getStoreId(), $store)) {
            Mage::log(
                sprintf('Revocation request #%d: merchant notification suppressed by rate limit', $request->getId()),
                Mage::LOG_WARNING,
            );
            $request->setData('merchant_email_sent', false);
        } else {
            $request->setData('merchant_email_sent', $emailModel->sendMerchantNotification($request));
        }
    }

    protected function _addOrderHistoryComment(Maho_Revocation_Model_Request $request): void
    {
        $order = $request->getOrder();
        if (!$order) {
            return;
        }

        $history = $order->addStatusHistoryComment(
            Mage::helper('revocation')->__('Customer submitted revocation request #%s via their account.', $request->getId()),
        );
        $history->setIsVisibleOnFront();
        $history->setIsCustomerNotified(false);
        $order->save();
    }

    protected function _addDecisionComment(Maho_Revocation_Model_Request $request): void
    {
        $helper = Mage::helper('revocation');
        $comment = match ($request->getProcessedStatus()) {
            Maho_Revocation_Model_Request::PROCESSED_STATUS_ACCEPTED => $helper->__('Revocation request #%s accepted.', $request->getId()),
            Maho_Revocation_Model_Request::PROCESSED_STATUS_REJECTED => $helper->__('Revocation request #%s rejected.', $request->getId()),
            default => null,
        };
        $order = $comment === null ? null : $request->getOrder();
        if (!$order) {
            return;
        }

        $order->addStatusHistoryComment($comment)->setIsCustomerNotified(false);
        $order->save();
    }
}
