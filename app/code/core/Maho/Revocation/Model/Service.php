<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

/**
 * Records a revocation declaration and dispatches the receipt/notification emails.
 *
 * Input keys: customer_name, email, order_reference, reason, ip, user_agent, locale,
 * store_id, received_at_microtime (float), customer_id (?int), session_order_id (?int).
 */
class Maho_Revocation_Model_Service
{
    /**
     * @throws Mage_Core_Exception on validation failure (before any row is written)
     */
    public function submit(\Maho\DataObject $input): Maho_Revocation_Model_Request
    {
        $helper = Mage::helper('revocation');
        $coreHelper = Mage::helper('core');

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
        $request->setStoreId((int) ($input->getStoreId() ?? Mage::app()->getStore()->getId()))
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

    public function isValidProcessedStatus(string $status): bool
    {
        return array_key_exists(
            $status,
            Mage::getModel('revocation/source_processedStatus')->toOptionHash(),
        );
    }

    /**
     * Records a processing decision on the request: sets the status and stamps
     * processed_at. Does not save (the caller persists). Throws on an unknown status.
     *
     * @throws Mage_Core_Exception
     */
    public function applyProcessedStatus(Maho_Revocation_Model_Request $request, string $status): void
    {
        if (!$this->isValidProcessedStatus($status)) {
            Mage::throwException(Mage::helper('revocation')->__('Invalid processing status.'));
        }
        $request->setProcessedStatus($status);
        $request->setProcessedAt(Mage::app()->getLocale()->formatDateForDb('now'));
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
        $history->setIsVisibleOnFront(1);
        $history->setIsCustomerNotified(false);
        $order->save();
    }
}
