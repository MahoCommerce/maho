<?php

/**
 * Give the privacy request actions of customers, guests and the staff to the privacy request service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

namespace Maho\PrivacyCenter\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Http\RequestInput;
use Maho\ApiPlatform\Security\AdminAcl;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho_PrivacyCenter_Model_Request as Request;

final class PrivacyRequestProcessor extends \Maho\ApiPlatform\Processor
{
    private const array CUSTOMER_FIELDS = ['type'];
    private const array GUEST_FIELDS = ['email', 'type', 'captchaToken'];
    private const array CONFIRM_FIELDS = ['token'];
    private const array REJECT_FIELDS = ['reason'];
    private const array PROCESS_FIELDS = ['force'];

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Request
    {
        StoreContext::ensureStore();
        $this->normalizeGraphQlInput($context);
        $body = self::withoutUriVariables($context['args']['input'] ?? [], $uriVariables);
        $service = \Mage::getService('privacycenter/request');
        $id = (int) ($uriVariables['id'] ?? 0);

        return match ($operation->getName()) {
            'submit_my_privacy_request' => $this->submitForCustomer($body, $context),
            'cancel_my_privacy_request' => $this->cancelForCustomer($id, $body),
            'submit_guest_privacy_request' => $this->submitForGuest($body, $context),
            'confirm_privacy_request' => $this->confirm($body),
            'approve_privacy_request' => $this->staffAction($id, $body, [], fn(Request $request): Request => $service->approve($request, $this->adminUserId())),
            'reject_privacy_request' => $this->staffAction($id, $body, self::REJECT_FIELDS, fn(Request $request): Request => $service->reject(
                $request,
                $this->adminUserId(),
                RequestInput::of($body)->string('reason') ?? '',
            )),
            'process_privacy_request' => $this->staffAction($id, $body, self::PROCESS_FIELDS, fn(Request $request): Request => $service->process(
                $request,
                RequestInput::of($body)->bool('force') ?? false,
            )),
            default => throw new \LogicException('Unknown privacy request operation ' . $operation->getName()),
        };
    }

    /**
     * @param array<array-key, mixed> $body
     * @param array<string, mixed> $context
     */
    private function submitForCustomer(array $body, array $context): Request
    {
        self::rejectUnknownFields($body, self::CUSTOMER_FIELDS);
        $type = RequestInput::of($body)->string('type') ?? throw ValidationException::requiredField('type');
        $customer = \Mage::getService('customer/customer')->getById($this->requireCustomerId());
        return \Mage::getService('privacycenter/request')->submitForCustomer($customer, $type, $this->client($context));
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function cancelForCustomer(int $id, array $body): Request
    {
        self::rejectUnknownFields($body, []);
        return \Mage::getService('privacycenter/request')->cancelForCustomer($id, $this->requireCustomerId());
    }

    /**
     * Answer with the type and the email only, so that the answer does not tell whether the email has an account.
     *
     * @param array<array-key, mixed> $body
     * @param array<string, mixed> $context
     */
    private function submitForGuest(array $body, array $context): Request
    {
        self::rejectUnknownFields($body, self::GUEST_FIELDS);
        $input = RequestInput::of($body);
        $email = $input->string('email') ?? throw ValidationException::requiredField('email');
        $type = $input->string('type') ?? throw ValidationException::requiredField('type');
        \Mage::getService('privacycenter/request')->submit($this->client($context)->addData([
            'email' => $email,
            'type' => $type,
            'form_fields' => $body,
        ]));
        return \Mage::getModel('privacycenter/request')
            ->setEmail($email)
            ->setType($type)
            ->setStatus(Request::STATUS_PENDING);
    }

    /**
     * @param array<array-key, mixed> $body
     */
    private function confirm(array $body): Request
    {
        self::rejectUnknownFields($body, self::CONFIRM_FIELDS);
        $token = RequestInput::of($body)->string('token') ?? throw ValidationException::requiredField('token');
        return \Mage::getService('privacycenter/request')->confirm($token);
    }

    /**
     * @param array<array-key, mixed> $body
     * @param list<string> $fields
     * @param callable(Request): Request $action
     */
    private function staffAction(int $id, array $body, array $fields, callable $action): Request
    {
        AdminAcl::checkPath(PrivacyRequest::ACL_PROCESS);
        self::rejectUnknownFields($body, $fields);
        $request = \Mage::getService('privacycenter/request')->getById($id);
        $this->assertStoreAllowed($request->getStoreId(), $this->requireUser(), 'privacy request');
        $oldData = $request->getData();
        $request = $action($request);
        $this->logApiActivity('privacy_request', 'update', $oldData, $request);
        return $request;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function client(array $context): \Maho\DataObject
    {
        $http = \Mage::helper('core/http');
        return new \Maho\DataObject([
            'store_id' => StoreContext::getStoreId(),
            'remote_ip' => $http->getRemoteAddr() ?: null,
            'user_agent' => ($context['request'] ?? null)?->headers->get('User-Agent'),
        ]);
    }

    private function adminUserId(): ?int
    {
        return $this->requireUser()->getAdminId();
    }
}
