<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\EventListener;

use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Idempotency Key Listener
 *
 * Provides replay protection for POST/PUT/PATCH requests using the X-Idempotency-Key header.
 * Stored responses are replayed for duplicate requests within 24 hours.
 */
class IdempotencyListener
{
    private const HEADER_KEY = 'X-Idempotency-Key';
    private const HEADER_REPLAYED = 'X-Idempotency-Replayed';
    public const TABLE = 'maho_api_idempotency_keys';
    private const MAX_KEY_LENGTH = 255;
    public const TTL_HOURS = 24;

    /**
     * Cap stored response bodies to keep `maho_api_idempotency_keys` from
     * growing unboundedly. Above the cap we skip storage entirely, a duplicate
     * request will re-run the operation rather than replay; given idempotency
     * keys are advisory and the underlying writes are themselves idempotent
     * (same payload + caller scope), that's the safe failure mode.
     */
    private const MAX_STORED_BODY_BYTES = 65536;

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    #[AsEventListener(event: KernelEvents::REQUEST, priority: 100)]
    public function onRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Only for mutating methods
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
            return;
        }

        $idempotencyKey = $request->headers->get(self::HEADER_KEY);
        if ($idempotencyKey === null) {
            return;
        }

        // Reject idempotency keys on auth endpoints to prevent response replay attacks
        $path = $request->getPathInfo();
        if (str_contains($path, '/auth/token') || str_contains($path, '/auth/login')) {
            return;
        }

        // Validate key format
        if (strlen($idempotencyKey) < 1 || strlen($idempotencyKey) > self::MAX_KEY_LENGTH) {
            throw new BadRequestHttpException('X-Idempotency-Key must be between 1 and 255 characters');
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $idempotencyKey)) {
            throw new BadRequestHttpException('X-Idempotency-Key may only contain alphanumeric characters, dashes, and underscores');
        }

        // Without a stable caller identity we can't safely scope replays:
        // every unauthenticated request would share the same bucket, so guest
        // A's cached response (potentially containing their masked cart ID or
        // order data) would replay for guest B reusing the same key. Skip
        // idempotency entirely for unauthenticated callers.
        $scope = $this->getUserScope();
        if ($scope === null) {
            return;
        }

        $path = $request->getPathInfo();
        $method = $request->getMethod();

        // Check for existing response
        $resource = \Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $table = $resource->getTableName(self::TABLE);

        // Only replay records inside the TTL window, older keys are treated as
        // expired so callers can safely reuse them and the table can be pruned.
        $cutoff = \Mage::app()->getLocale()->formatDateForDb('-' . self::TTL_HOURS . ' hours');

        $select = $read->select()
            ->from($table, ['response_code', 'response_body', 'response_headers'])
            ->where('idempotency_key = ?', $idempotencyKey)
            ->where('user_scope = ?', $scope)
            ->where('request_path = ?', $path)
            ->where('request_method = ?', $method)
            ->where('created_at >= ?', $cutoff);
        $existing = $read->fetchRow($select);

        if ($existing) {
            try {
                $headers = (array) \Mage::helper('core')->jsonDecode($existing['response_headers'] ?? '{}');
            } catch (\JsonException) {
                $headers = [];
            }
            $headers[self::HEADER_REPLAYED] = 'true';

            $response = new Response(
                $existing['response_body'] ?? '',
                (int) $existing['response_code'],
                $headers,
            );

            // Preserve content type
            if (!$response->headers->has('Content-Type')) {
                $response->headers->set('Content-Type', 'application/json');
            }

            $event->setResponse($response);
            return;
        }

        // Store key info on request for the response listener
        $request->attributes->set('_idempotency_key', $idempotencyKey);
        $request->attributes->set('_idempotency_scope', $scope);
    }

    #[AsEventListener(event: KernelEvents::RESPONSE, priority: -100)]
    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();

        // Only store if we had an idempotency key
        $idempotencyKey = $request->attributes->get('_idempotency_key');
        if ($idempotencyKey === null) {
            return;
        }

        // Only for mutating methods
        if (!in_array($request->getMethod(), ['POST', 'PUT', 'PATCH'], true)) {
            return;
        }

        // _idempotency_scope is always set alongside _idempotency_key in
        // onRequest for authenticated callers; anonymous requests never make
        // it past that point.
        $scope = $request->attributes->get('_idempotency_scope');
        $response = $event->getResponse();

        // Only store successful responses. Replaying a 4xx (validation error,
        // conflict) would return the stale failure after the client corrected
        // the request; 5xx is transient. Both must stay retryable.
        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            return;
        }

        // Skip storage for oversized bodies, see MAX_STORED_BODY_BYTES.
        $responseBody = (string) $response->getContent();
        if (strlen($responseBody) > self::MAX_STORED_BODY_BYTES) {
            return;
        }

        $resource = \Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName(self::TABLE);

        // Collect relevant response headers
        $headersToStore = [];
        foreach (['Content-Type', 'ETag', 'Location'] as $header) {
            if ($response->headers->has($header)) {
                $headersToStore[$header] = $response->headers->get($header);
            }
        }

        try {
            $write->insert($table, [
                'idempotency_key' => $idempotencyKey,
                'user_scope' => $scope,
                'request_path' => $request->getPathInfo(),
                'request_method' => $request->getMethod(),
                'response_code' => $response->getStatusCode(),
                'response_body' => $responseBody,
                'response_headers' => \Mage::helper('core')->jsonEncode($headersToStore),
                'created_at' => \Mage::app()->getLocale()->formatDateForDb('now'),
            ]);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            // Duplicate key, another concurrent request stored it first, that's fine
        }
    }

    /**
     * Returns the per-caller idempotency scope, or null when the request has
     * no stable caller identity (so idempotency must be skipped to avoid
     * leaking one anonymous caller's response to another).
     */
    private function getUserScope(): ?string
    {
        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return null;
        }

        $user = $token->getUser();
        if ($user instanceof ApiUser) {
            if ($user->getCustomerId()) {
                return 'customer:' . $user->getCustomerId();
            }
            if ($user->getAdminId()) {
                return 'admin:' . $user->getAdminId();
            }
            if ($user->isApiUser()) {
                return 'api:' . $user->getUserIdentifier();
            }
        }

        return null;
    }
}
