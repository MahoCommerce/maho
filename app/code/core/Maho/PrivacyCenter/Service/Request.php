<?php

/**
 * Takes the privacy requests of customers and guests, confirms them by email, writes the exports and runs the
 * erasures.
 *
 * Input keys of submit(): email, type, store_id, remote_ip, user_agent, and form_fields (the fields that the client
 * sent, for the honeypot and the CAPTCHA: the form POST or the API body). submitForCustomer() takes remote_ip and
 * user_agent.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Request as Request;
use Maho_PrivacyCenter_Model_Subject as Subject;
use Mage_Customer_Model_Customer as Customer;

class Maho_PrivacyCenter_Service_Request
{
    public const int RATE_LIMIT_WINDOW = 3600;

    /** The order states that end an order. An erasure waits while the customer has an order in another state. */
    public const array FINAL_ORDER_STATES = [
        Mage_Sales_Model_Order::STATE_COMPLETE,
        Mage_Sales_Model_Order::STATE_CLOSED,
        Mage_Sales_Model_Order::STATE_CANCELED,
    ];

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id): Request
    {
        $request = Mage::getModel('privacycenter/request')->load($id);
        if (!$request->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('privacycenter')->__('The privacy request does not exist.'));
        }
        return $request;
    }

    /**
     * @throws Mage_Core_Exception_Input when a field of $request is not valid
     */
    public function save(Request $request): Request
    {
        $request->save();
        return $request;
    }

    public function delete(Request $request): void
    {
        $this->deleteExportFile($request);
        $request->delete();
    }

    /**
     * The requests of the customer with $customerId, newest first.
     */
    public function getForCustomer(int $customerId): Maho_PrivacyCenter_Model_Resource_Request_Collection
    {
        return Mage::getResourceModel('privacycenter/request_collection')
            ->addFieldToFilter('customer_id', $customerId)
            ->setOrder('created_at', \Maho\Data\Collection::SORT_ORDER_DESC)
            ->setOrder('request_id', \Maho\Data\Collection::SORT_ORDER_DESC);
    }

    /**
     * Take the request of a guest, and email the link that confirms it. A request for the email of a customer
     * account of the website is a request of that customer. The answer is the same for any email, so that the form
     * does not tell who has an account. Return null and record nothing when the client fills the honeypot.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the store has no privacy requests
     * @throws Mage_Core_Exception_Forbidden when the store takes no requests of guests
     * @throws Mage_Core_Exception_Input when the email or the type is not valid, or the CAPTCHA refuses the token
     * @throws Mage_Core_Exception_TooManyRequests when the client is over its limit
     */
    public function submit(\Maho\DataObject $input): ?Request
    {
        $storeId = $this->assertEnabled($input);
        $helper = Mage::helper('privacycenter');
        if (!$helper->isGuestRequestAllowed($storeId)) {
            throw new Mage_Core_Exception_Forbidden($helper->__('Please log in to your account to send a privacy request.'));
        }
        $fields = is_array($input->getData('form_fields')) ? $input->getData('form_fields') : [];
        if (Mage::getStoreConfigFlag(Maho_PrivacyCenter_Helper_Data::XML_PATH_HONEYPOT_ENABLED, $storeId)
            && Mage::helper('core')->isHoneypotTriggered($fields)) {
            return null;
        }
        Mage::helper('core')->assertCaptcha($fields);

        $email = trim((string) $input->getData('email'));
        $type = (string) $input->getData('type');
        $this->assertTypeAndEmail($type, $email);
        Mage::helper('core')->rateLimiter(
            'privacy_request',
            (int) Mage::getStoreConfig(Maho_PrivacyCenter_Helper_Data::XML_PATH_IP_RATE_LIMIT, $storeId),
            self::RATE_LIMIT_WINDOW,
            \Maho\Security\RateLimitScope::Ip,
        )->attemptOrThrow($helper->__('Too many requests. Please try again later.'));

        $customer = Mage::getModel('customer/customer')
            ->setWebsiteId((int) Mage::app()->getStore($storeId)->getWebsiteId())
            ->loadByEmail($email);

        $request = $this->newRequest($type, $email, $storeId, $input)
            ->setCustomerId($customer->getId() ? (int) $customer->getId() : null);
        $this->sendConfirmation($request);
        return $request;
    }

    /**
     * Take the request of a logged-in customer. The session proves the owner of the account, so an export starts at
     * once. An erasure deletes the account, so it waits for the link of its confirmation email.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the store has no privacy requests
     * @throws Mage_Core_Exception_Input when the type is not valid
     * @throws Mage_Core_Exception_Conflict when the customer has an open request of the same type
     */
    public function submitForCustomer(Customer $customer, string $type, \Maho\DataObject $input): Request
    {
        $input->setData('store_id', $input->getData('store_id') ?? (int) $customer->getStoreId());
        $storeId = $this->assertEnabled($input);
        $this->assertTypeAndEmail($type, (string) $customer->getEmail());
        $this->assertNoOpenRequest((int) $customer->getId(), $type);

        $request = $this->newRequest($type, (string) $customer->getEmail(), $storeId, $input)
            ->setCustomerId((int) $customer->getId());
        if ($type === Request::TYPE_ERASURE) {
            $this->sendConfirmation($request);
            return $request;
        }
        return $this->startConfirmed($request);
    }

    /**
     * Take a request that the customer sent to the staff, by letter or by phone. The staff checked the identity, so
     * the request starts confirmed.
     *
     * @throws Mage_Core_Exception_Input when the type is not valid
     * @throws Mage_Core_Exception_Conflict when the customer has an open request of the same type
     */
    public function submitByStaff(Customer $customer, string $type, ?int $adminUserId): Request
    {
        $this->assertTypeAndEmail($type, (string) $customer->getEmail());
        $this->assertNoOpenRequest((int) $customer->getId(), $type);

        $input = new \Maho\DataObject();
        $request = $this->newRequest($type, (string) $customer->getEmail(), (int) $customer->getStoreId(), $input)
            ->setCustomerId((int) $customer->getId())
            ->setAdminUserId($adminUserId)
            ->appendAdminNote(Mage::helper('privacycenter')->__('Created by the staff'));
        return $this->startConfirmed($request);
    }

    /**
     * The request that waits for the confirmation link with $token.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when no pending request has the token, or the link expired
     */
    public function getByConfirmationToken(#[\SensitiveParameter]
        string $token): Request
    {
        $request = $this->findByToken($token);
        if ($request->getStatus() !== Request::STATUS_PENDING) {
            throw $this->invalidLink();
        }
        return $request;
    }

    /**
     * Confirm the request of the link with $token. An export goes to the queue. An erasure waits for its cooling-off
     * period, and for the approval of the staff when the store asks for it.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when no pending request has the token, or the link expired
     */
    public function confirm(#[\SensitiveParameter]
        string $token): Request
    {
        $request = $this->getByConfirmationToken($token);
        if (!$request->getResource()->consumeToken((int) $request->getId(), (string) $request->getTokenHash())) {
            throw $this->invalidLink();
        }
        $request->setTokenHash(null)->setTokenExpiresAt(null);
        return $this->startConfirmed($request);
    }

    /**
     * Approve the erasure $request, so that it runs at the end of its cooling-off period.
     *
     * @throws Mage_Core_Exception_Conflict when the request is not a confirmed erasure
     */
    public function approve(Request $request, ?int $adminUserId): Request
    {
        if (!$request->isErasure() || $request->getStatus() !== Request::STATUS_CONFIRMED) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('privacycenter')->__('Only a confirmed erasure request can be approved.'));
        }
        $request->setApprovedAt(Mage::app()->getLocale()->formatDateForDb('now'))
            ->setAdminUserId($adminUserId)
            ->appendAdminNote(Mage::helper('privacycenter')->__('Approved'));
        return $this->save($request);
    }

    /**
     * Refuse the open $request, with $reason in the admin note.
     *
     * @throws Mage_Core_Exception_Conflict when the request is not open
     */
    public function reject(Request $request, ?int $adminUserId, string $reason = ''): Request
    {
        $helper = Mage::helper('privacycenter');
        if (!$request->isOpen()) {
            throw new Mage_Core_Exception_Conflict($helper->__('Only an open request can be rejected.'));
        }
        $request->setStatus(Request::STATUS_REJECTED)
            ->setTokenHash(null)
            ->setTokenExpiresAt(null)
            ->setAdminUserId($adminUserId)
            ->setProcessedAt(Mage::app()->getLocale()->formatDateForDb('now'))
            ->appendAdminNote(trim($helper->__('Rejected') . ' ' . $reason));
        return $this->save($request);
    }

    /**
     * Cancel the erasure $request before it runs.
     *
     * @throws Mage_Core_Exception_Conflict when the request cannot be canceled any more
     */
    public function cancel(Request $request): Request
    {
        if (!$request->canCancel()) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('privacycenter')->__('This request cannot be canceled any more.'));
        }
        $request->setStatus(Request::STATUS_CANCELED)
            ->setTokenHash(null)
            ->setTokenExpiresAt(null)
            ->appendAdminNote(Mage::helper('privacycenter')->__('Canceled by the customer'));
        return $this->save($request);
    }

    /**
     * Cancel the erasure request $requestId of the customer $customerId.
     *
     * @throws Mage_Core_Exception_NoSuchEntity when the customer has no request with this ID
     * @throws Mage_Core_Exception_Conflict when the request cannot be canceled any more
     */
    public function cancelForCustomer(int $requestId, int $customerId): Request
    {
        $request = Mage::getModel('privacycenter/request')->load($requestId);
        if (!$request->getId() || $request->getCustomerId() !== $customerId) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('privacycenter')->__('The privacy request does not exist.'));
        }
        return $this->cancel($request);
    }

    /**
     * Run $request now: write an export, or erase the data. $force runs an erasure before its cooling-off period ends,
     * without its approval, and while the customer has open orders.
     *
     * @throws Mage_Core_Exception_Conflict when the request is not confirmed, or an erasure must wait
     */
    public function process(Request $request, bool $force = false): Request
    {
        if (!in_array($request->getStatus(), [Request::STATUS_CONFIRMED, Request::STATUS_PROCESSING], true)) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('privacycenter')->__('Only a confirmed request can be processed.'));
        }
        return $request->isExport() ? $this->export($request) : $this->erase($request, $force);
    }

    /**
     * Write the export of $request to a file, and email the link that downloads it.
     *
     * @throws Mage_Core_Exception_Conflict when the request is not a confirmed export
     */
    public function export(Request $request): Request
    {
        if (!$request->isExport() || !in_array($request->getStatus(), [Request::STATUS_CONFIRMED, Request::STATUS_PROCESSING], true)) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('privacycenter')->__('Only a confirmed export request can be exported.'));
        }
        $helper = Mage::helper('privacycenter');
        $subject = Subject::fromRequest($request);
        $sections = [];
        foreach ($this->getExporters() as $code => $exporter) {
            $sections[$code] = ['label' => $exporter->getLabel(), 'data' => $exporter->export($subject)];
        }

        $this->deleteExportFile($request);
        $token = $helper->generateToken();
        $request->setExportFile(Mage::getModel('privacycenter/export_archive')->write($request, $sections))
            ->setStatus(Request::STATUS_COMPLETED)
            ->setProcessedAt(Mage::app()->getLocale()->formatDateForDb('now'))
            ->setTokenHash($helper->hashToken($token))
            ->setTokenExpiresAt($this->hoursFromNow($helper->getExportHours($request->getStoreId())));
        $this->save($request);

        try {
            Mage::getModel('privacycenter/email')->sendExportReady($request, $token);
        } catch (Throwable $e) {
            Mage::logException($e);
        }
        return $request;
    }

    /**
     * Erase the data of the subject of $request, and email the confirmation. The orders, the invoices, the shipments
     * and the credit memos stay, for the tax law, and so does the proof of each accepted agreement.
     *
     * @throws Mage_Core_Exception_Conflict when the request is not a confirmed erasure, or it must wait for its
     *                                      cooling-off period, its approval, or the end of the open orders
     */
    public function erase(Request $request, bool $force = false): Request
    {
        $helper = Mage::helper('privacycenter');
        if (!$request->isErasure() || !in_array($request->getStatus(), [Request::STATUS_CONFIRMED, Request::STATUS_PROCESSING], true)) {
            throw new Mage_Core_Exception_Conflict($helper->__('Only a confirmed erasure request can be processed.'));
        }
        if (!$force) {
            if ($helper->isErasureApprovalRequired($request->getStoreId()) && !$request->getApprovedAt()) {
                throw new Mage_Core_Exception_Conflict($helper->__('The erasure waits for the approval of the staff.'));
            }
            if ($request->getScheduledAt() && $request->getScheduledAt() > Mage_Core_Model_Locale::nowUtc()) {
                throw new Mage_Core_Exception_Conflict($helper->__('The erasure waits for the end of its cooling-off period.'));
            }
            $openOrders = $this->countOpenOrders(Subject::fromRequest($request));
            if ($openOrders > 0) {
                throw new Mage_Core_Exception_Conflict($helper->__('The erasure waits until %d open orders are complete, closed or canceled.', $openOrders));
            }
        }

        $subject = Subject::fromRequest($request);
        $request->setStatus(Request::STATUS_PROCESSING);
        $this->save($request);

        Mage::dispatchEvent('privacy_before_erase', ['request' => $request, 'subject' => $subject]);
        // The exports of earlier requests hold the data too. The customer eraser unlinks those requests, so this runs first.
        foreach ($this->getSubjectRequests($subject) as $other) {
            if ((int) $other->getId() !== (int) $request->getId() && $other->getExportFile() !== null) {
                $this->deleteExportFile($other);
                $this->save($other->setTokenHash(null)->setTokenExpiresAt(null));
            }
        }
        // The API and the storefront run outside the admin area, and the review and customer models refuse a delete there
        $wasSecure = Mage::registry('isSecureArea');
        if (!$wasSecure) {
            Mage::register('isSecureArea', true);
        }
        try {
            foreach ($this->getErasers() as $code => $eraser) {
                $request->appendAdminNote($code . ': ' . $eraser->erase($subject));
            }
        } finally {
            if (!$wasSecure) {
                Mage::unregister('isSecureArea');
            }
        }

        // The customer is gone, so the request keeps only the email, as the proof of the erasure
        $request->setCustomerId(null)
            ->setStatus(Request::STATUS_COMPLETED)
            ->setProcessedAt(Mage::app()->getLocale()->formatDateForDb('now'));
        $this->save($request);
        Mage::dispatchEvent('privacy_after_erase', ['request' => $request, 'customer_id' => $subject->customerId]);

        try {
            Mage::getModel('privacycenter/email')->sendErasureCompleted($request);
        } catch (Throwable $e) {
            Mage::logException($e);
        }
        return $request;
    }

    /**
     * Send again the email of $request: a new confirmation link while it waits for its confirmation, or a new
     * download link of a written export.
     *
     * @throws Mage_Core_Exception_Conflict when the request has no email to send
     */
    public function resendEmail(Request $request): Request
    {
        $helper = Mage::helper('privacycenter');
        if ($request->getStatus() === Request::STATUS_PENDING) {
            $this->sendConfirmation($request);
            return $request;
        }
        if ($request->isExport() && $request->getStatus() === Request::STATUS_COMPLETED && $request->getExportFile()) {
            $token = $helper->generateToken();
            $request->setTokenHash($helper->hashToken($token))
                ->setTokenExpiresAt($this->hoursFromNow($helper->getExportHours($request->getStoreId())))
                ->appendAdminNote($helper->__('Download link sent again'));
            $this->save($request);
            Mage::getModel('privacycenter/email')->sendExportReady($request, $token);
            return $request;
        }
        throw new Mage_Core_Exception_Conflict($helper->__('This request has no email to send.'));
    }

    /**
     * The export file of the download link with $token.
     *
     * @return array{request: Request, stream: resource, size: int, filename: string}
     * @throws Mage_Core_Exception_NoSuchEntity when no export has the token, or the link expired
     */
    public function openExportByToken(#[\SensitiveParameter]
        string $token): array
    {
        $request = $this->findByToken($token);
        if (!$request->isExportAvailable()) {
            throw $this->invalidLink();
        }
        return $this->openExport($request);
    }

    /**
     * The export file of the request $requestId of the customer $customerId.
     *
     * @return array{request: Request, stream: resource, size: int, filename: string}
     * @throws Mage_Core_Exception_NoSuchEntity when the customer has no available export with this ID
     */
    public function openExportForCustomer(int $requestId, int $customerId): array
    {
        $request = Mage::getModel('privacycenter/request')->load($requestId);
        if (!$request->getId() || $request->getCustomerId() !== $customerId || !$request->isExportAvailable()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('privacycenter')->__('The export is not available.'));
        }
        return $this->openExport($request);
    }

    /**
     * The export file of $request, for the staff, who hands it to the customer by another channel.
     *
     * @return array{request: Request, stream: resource, size: int, filename: string}
     * @throws Mage_Core_Exception_NoSuchEntity when the request has no export file
     */
    public function openExportForStaff(Request $request): array
    {
        if (!$request->isExport() || $request->getExportFile() === null) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('privacycenter')->__('The export is not available.'));
        }
        return $this->openExport($request);
    }

    /**
     * Expire the requests whose confirmation link expired.
     *
     * @return int the number of expired requests
     */
    public function expirePending(): int
    {
        $requests = Mage::getResourceModel('privacycenter/request_collection')
            ->addFieldToFilter('status', Request::STATUS_PENDING)
            ->addFieldToFilter('token_expires_at', ['lt' => Mage::app()->getLocale()->formatDateForDb('now')]);
        $count = 0;
        foreach ($requests as $request) {
            $request->setStatus(Request::STATUS_EXPIRED)->setTokenHash(null)->setTokenExpiresAt(null);
            $this->save($request);
            $count++;
        }
        return $count;
    }

    /**
     * Delete the export files whose download link expired.
     *
     * @return int the number of deleted files
     */
    public function cleanExports(): int
    {
        $requests = Mage::getResourceModel('privacycenter/request_collection')
            ->addFieldToFilter('type', Request::TYPE_EXPORT)
            ->addFieldToFilter('export_file', ['notnull' => true])
            ->addFieldToFilter('token_expires_at', [
                ['lt' => Mage::app()->getLocale()->formatDateForDb('now')],
                ['null' => true],
            ]);
        $count = 0;
        foreach ($requests as $request) {
            $this->deleteExportFile($request);
            $request->setTokenHash(null)->setTokenExpiresAt(null);
            $this->save($request);
            $count++;
        }
        return $count;
    }

    /**
     * Run the erasures whose cooling-off period ended and that need no more approval. An erasure that must wait for
     * open orders stays confirmed, with a note.
     *
     * @return int the number of erasures that ran
     */
    public function processDueErasures(): int
    {
        $requests = Mage::getResourceModel('privacycenter/request_collection')
            ->addFieldToFilter('type', Request::TYPE_ERASURE)
            ->addFieldToFilter('status', Request::STATUS_CONFIRMED)
            ->addFieldToFilter('scheduled_at', ['lteq' => Mage::app()->getLocale()->formatDateForDb('now')]);
        $count = 0;
        foreach ($requests as $request) {
            try {
                $this->erase($request);
                $count++;
            } catch (Mage_Core_Exception_Conflict $e) {
                if (!str_contains((string) $request->getAdminNote(), $e->getMessage())) {
                    $this->save($request->appendAdminNote($e->getMessage()));
                }
            } catch (Throwable $e) {
                Mage::logException($e);
            }
        }
        return $count;
    }

    /**
     * The exporters of the config node global/privacycenter/exporters and of the privacy_collect_exporters event,
     * by code.
     *
     * @return array<string, Maho_PrivacyCenter_Model_Export_AbstractExporter>
     */
    public function getExporters(): array
    {
        /** @var array<string, Maho_PrivacyCenter_Model_Export_AbstractExporter> $exporters */
        $exporters = $this->collect('exporters', Maho_PrivacyCenter_Model_Export_AbstractExporter::class);
        return $exporters;
    }

    /**
     * The erasers of the config node global/privacycenter/erasers and of the privacy_collect_erasers event, by code,
     * in the order in which they run. The customer eraser runs last.
     *
     * @return array<string, Maho_PrivacyCenter_Model_Eraser_AbstractEraser>
     */
    public function getErasers(): array
    {
        /** @var array<string, Maho_PrivacyCenter_Model_Eraser_AbstractEraser> $erasers */
        $erasers = $this->collect('erasers', Maho_PrivacyCenter_Model_Eraser_AbstractEraser::class);
        if (isset($erasers['customer'])) {
            $customer = $erasers['customer'];
            unset($erasers['customer']);
            $erasers['customer'] = $customer;
        }
        return $erasers;
    }

    /**
     * @param class-string $class
     * @return array<string, object>
     */
    protected function collect(string $kind, string $class): array
    {
        $items = [];
        foreach (Mage::getConfig()->getNode("global/privacycenter/{$kind}")?->children() ?? [] as $code => $alias) {
            $model = Mage::getModel((string) $alias);
            if ($model instanceof $class) {
                $items[(string) $code] = $model;
            }
        }
        $container = new \Maho\DataObject([$kind => $items]);
        Mage::dispatchEvent("privacy_collect_{$kind}", ['container' => $container]);
        return array_filter((array) $container->getData($kind), fn(mixed $item): bool => $item instanceof $class);
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    protected function assertEnabled(\Maho\DataObject $input): int
    {
        $storeId = (int) ($input->getData('store_id') ?? Mage::app()->getStore()->getId());
        $input->setData('store_id', $storeId);
        if (!Mage::helper('privacycenter')->isEnabled($storeId)) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('privacycenter')->__('Privacy requests are not available.'));
        }
        return $storeId;
    }

    /**
     * @throws Mage_Core_Exception_Input
     */
    protected function assertTypeAndEmail(string $type, #[\SensitiveParameter]
        string $email): void
    {
        $helper = Mage::helper('privacycenter');
        $errors = new Mage_Core_Exception_Input();
        if (!array_key_exists($type, Mage::getModel('privacycenter/source_type')->toOptionHash())) {
            $errors->addError('type', $helper->__('Please choose the type of the request.'));
        }
        if (!Mage::helper('core')->isValidEmail($email)) {
            $errors->addError('email', $helper->__('Please enter a valid email address.'));
        }
        $errors->throwIfErrors();
    }

    /**
     * @throws Mage_Core_Exception_Conflict
     */
    protected function assertNoOpenRequest(int $customerId, string $type): void
    {
        $open = Mage::getResourceModel('privacycenter/request_collection')
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('type', $type)
            ->addFieldToFilter('status', ['in' => [Request::STATUS_PENDING, Request::STATUS_CONFIRMED, Request::STATUS_PROCESSING]])
            ->getSize();
        if ($open > 0) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('privacycenter')->__('You already have an open request of this type.'));
        }
    }

    protected function newRequest(string $type, #[\SensitiveParameter]
        string $email, int $storeId, \Maho\DataObject $input): Request
    {
        return Mage::getModel('privacycenter/request')
            ->setType($type)
            ->setStatus(Request::STATUS_PENDING)
            ->setEmail(mb_substr($email, 0, 255))
            ->setStoreId($storeId)
            ->setRemoteIp(mb_substr((string) $input->getData('remote_ip'), 0, 45) ?: null)
            ->setUserAgent(mb_substr((string) $input->getData('user_agent'), 0, 512) ?: null);
    }

    /**
     * Save $request with a new confirmation token, and email the link. The email of a recipient over its limit is
     * not sent.
     */
    protected function sendConfirmation(Request $request): void
    {
        $helper = Mage::helper('privacycenter');
        $token = $helper->generateToken();
        $request->setStatus(Request::STATUS_PENDING)
            ->setTokenHash($helper->hashToken($token))
            ->setTokenExpiresAt($this->hoursFromNow($helper->getConfirmHours($request->getStoreId())));
        $this->save($request);

        if ($helper->isRecipientRateLimited((string) $request->getEmail(), $request->getStoreId())) {
            $this->save($request->appendAdminNote($helper->__('Confirmation email not sent: the recipient is over its limit')));
            return;
        }
        try {
            Mage::getModel('privacycenter/email')->sendConfirmation($request, $token);
        } catch (Throwable $e) {
            Mage::logException($e);
        }
    }

    /**
     * Mark $request confirmed and start it: an export goes to the queue, an erasure gets its date.
     */
    protected function startConfirmed(Request $request): Request
    {
        $helper = Mage::helper('privacycenter');
        $now = Mage::app()->getLocale()->formatDateForDb('now');
        $request->setStatus(Request::STATUS_CONFIRMED)->setConfirmedAt($now);

        if ($request->isExport()) {
            $request->setStatus(Request::STATUS_PROCESSING);
            $this->save($request);
            $message = new Maho_PrivacyCenter_Model_ExportMessage((int) $request->getId());
            \Maho\Queue\QueueManager::dispatch(
                $message,
                queue: Maho_PrivacyCenter_Model_ExportMessage::QUEUE_NAME,
                stamps: [new \Maho\Queue\Stamp\DedupeKeyStamp($message->getDedupeKey(), pendingOnly: true)],
            );
            return $request;
        }

        $days = $helper->getCoolingOffDays($request->getStoreId());
        $request->setScheduledAt(Mage::app()->getLocale()->formatDateForDb(time() + $days * 86400));
        $this->save($request);
        try {
            Mage::getModel('privacycenter/email')->sendErasureScheduled($request);
        } catch (Throwable $e) {
            Mage::logException($e);
        }
        return $request;
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    protected function findByToken(#[\SensitiveParameter]
        string $token): Request
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            throw $this->invalidLink();
        }
        $hash = Mage::helper('privacycenter')->hashToken($token);
        $request = Mage::getModel('privacycenter/request')->load($hash, 'token_hash');
        if (!$request->getId()
            || !hash_equals((string) $request->getTokenHash(), $hash)
            || !$request->getTokenExpiresAt()
            || $request->getTokenExpiresAt() <= Mage_Core_Model_Locale::nowUtc()) {
            throw $this->invalidLink();
        }
        return $request;
    }

    protected function invalidLink(): Mage_Core_Exception_NoSuchEntity
    {
        return new Mage_Core_Exception_NoSuchEntity(Mage::helper('privacycenter')->__('This link is not valid or has expired.'));
    }

    /**
     * @return array{request: Request, stream: resource, size: int, filename: string}
     * @throws Mage_Core_Exception_NoSuchEntity when the file is gone
     */
    protected function openExport(Request $request): array
    {
        $storage = Mage::helper('privacycenter')->getStorage();
        $path = (string) $request->getExportFile();
        if ($path === '' || !$storage->fileExists($path)) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('privacycenter')->__('The export is not available.'));
        }
        return [
            'request' => $request,
            'stream' => $storage->readStream($path),
            'size' => $storage->fileSize($path),
            'filename' => sprintf('personal-data-%d.zip', (int) $request->getId()),
        ];
    }

    protected function deleteExportFile(Request $request): void
    {
        $path = $request->getExportFile();
        if ($path === null || $path === '') {
            return;
        }
        try {
            $storage = Mage::helper('privacycenter')->getStorage();
            if ($storage->fileExists($path)) {
                $storage->delete($path);
            }
        } catch (Throwable $e) {
            Mage::logException($e);
        }
        $request->setExportFile(null);
    }

    /**
     * The requests of $subject: those of its customer, or those of its email for a guest.
     *
     * @return list<Request>
     */
    public function getSubjectRequests(Subject $subject): array
    {
        $requests = Mage::getResourceModel('privacycenter/request_collection');
        if ($subject->isGuest()) {
            $requests->addFieldToFilter('email', $subject->email);
        } else {
            $requests->addFieldToFilter('customer_id', $subject->customerId);
        }
        return array_values($requests->getItems());
    }

    protected function countOpenOrders(Subject $subject): int
    {
        $orders = Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('state', ['nin' => self::FINAL_ORDER_STATES]);
        if ($subject->isGuest()) {
            $orders->addFieldToFilter('customer_email', $subject->email);
        } else {
            $orders->addFieldToFilter('customer_id', $subject->customerId);
        }
        return (int) $orders->getSize();
    }

    protected function hoursFromNow(int $hours): string
    {
        return Mage::app()->getLocale()->formatDateForDb(time() + $hours * 3600);
    }
}
