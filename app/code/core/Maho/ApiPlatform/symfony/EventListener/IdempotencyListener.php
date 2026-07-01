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
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Idempotency Key Listener.
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
     * Sentinel stored in response_code while the operation is still running.
     * A reservation row carrying this value means "another request holds this
     * key right now" — concurrent callers get a 409 instead of re-executing.
     * Real HTTP status codes are always >= 100, so 0 can never collide.
     */
    private const STATUS_IN_PROGRESS = 0;

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

    // Must run AFTER Symfony's security firewall (priority 8) so the security
    // token is populated; getUserScope() relies on the authenticated user.
    // Running before the firewall (e.g. at priority 100) makes getUserScope()
    // always return null, silently disabling idempotency for every request.
    #[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
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

        // Reject idempotency keys on every auth endpoint to prevent response
        // replay attacks. Covers /auth/token, /auth/refresh and /auth/logout so
        // a replayed response can never hand back JWT material or skip a real
        // token rotation/revocation.
        $path = $request->getPathInfo();
        if (str_contains($path, '/auth/')) {
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

        $resource = \Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName(self::TABLE);

        // Only replay records inside the TTL window, older keys are treated as
        // expired so callers can safely reuse them and the table can be pruned.
        $cutoff = \Mage::app()->getLocale()->formatDateForDb('-' . self::TTL_HOURS . ' hours');

        $existing = $this->fetchRecord($read, $table, $idempotencyKey, $scope, $path, $method, $cutoff);
        if ($existing) {
            $this->replayExisting($event, $existing);
            return;
        }

        // No usable record: reserve the key with an in-progress row BEFORE the
        // operation runs. The unique index makes this atomic, so exactly one of
        // N concurrent requests wins the INSERT; the losers see the reservation
        // and get a 409 instead of every request executing the operation (which
        // is the very duplicate-execution idempotency keys exist to prevent —
        // the previous SELECT-then-INSERT-after-response only caught sequential
        // replays).
        try {
            $write->insert($table, [
                'idempotency_key' => $idempotencyKey,
                'user_scope' => $scope,
                'request_path' => $path,
                'request_method' => $method,
                'response_code' => self::STATUS_IN_PROGRESS,
                'response_body' => null,
                'response_headers' => null,
                'created_at' => \Mage::app()->getLocale()->formatDateForDb('now'),
            ]);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            // A row with this key already exists: either a concurrent request
            // beat us to the reservation, or a stale row (outside the TTL
            // window) still occupies the unique slot.
            $existing = $this->fetchRecord($read, $table, $idempotencyKey, $scope, $path, $method, $cutoff);
            if ($existing) {
                $this->replayExisting($event, $existing);
                return;
            }

            // Stale row outside the TTL window: reclaim it as a fresh
            // reservation so the key becomes reusable after expiry. The
            // `created_at < cutoff` guard makes the reclaim atomic: when two
            // concurrent requests both see an expired row, only the one whose
            // UPDATE runs first matches the stale timestamp and wins; the
            // loser matches nothing (the row is now fresh) and gets a 409.
            $reclaimWhere = $this->recordWhere($idempotencyKey, $scope, $path, $method);
            $reclaimWhere['created_at < ?'] = $cutoff;
            $reclaimed = $write->update(
                $table,
                [
                    'response_code' => self::STATUS_IN_PROGRESS,
                    'response_body' => null,
                    'response_headers' => null,
                    'created_at' => \Mage::app()->getLocale()->formatDateForDb('now'),
                ],
                $reclaimWhere,
            );
            if ($reclaimed === 0) {
                // Lost a race to another request that reclaimed/completed the
                // row first; treat it as in-progress.
                throw new ConflictHttpException('A request with this idempotency key is already being processed');
            }
        }

        // We hold the reservation; record it so onResponse finalizes the row.
        $request->attributes->set('_idempotency_key', $idempotencyKey);
        $request->attributes->set('_idempotency_scope', $scope);
    }

    /**
     * Fetch a (non-expired) idempotency record, or false when none exists.
     *
     * @return array<string, mixed>|false
     */
    private function fetchRecord(
        \Maho\Db\Adapter\AdapterInterface $read,
        string $table,
        string $key,
        string $scope,
        string $path,
        string $method,
        string $cutoff,
    ): array|false {
        $select = $read->select()
            ->from($table, ['response_code', 'response_body', 'response_headers'])
            ->where('idempotency_key = ?', $key)
            ->where('user_scope = ?', $scope)
            ->where('request_path = ?', $path)
            ->where('request_method = ?', $method)
            ->where('created_at >= ?', $cutoff);

        return $read->fetchRow($select);
    }

    /**
     * @return array<string, mixed>
     */
    private function recordWhere(string $key, string $scope, string $path, string $method): array
    {
        return [
            'idempotency_key = ?' => $key,
            'user_scope = ?' => $scope,
            'request_path = ?' => $path,
            'request_method = ?' => $method,
        ];
    }

    /**
     * Replay a stored response, or reject when the key is still in progress.
     *
     * @param array<string, mixed> $existing
     */
    private function replayExisting(RequestEvent $event, array $existing): void
    {
        // A reservation that has not been finalized means a concurrent request
        // is still executing the operation. Reject rather than run it twice.
        if ((int) $existing['response_code'] === self::STATUS_IN_PROGRESS) {
            throw new ConflictHttpException('A request with this idempotency key is already being processed');
        }

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

        $resource = \Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName(self::TABLE);
        $where = $this->recordWhere($idempotencyKey, $scope, $request->getPathInfo(), $request->getMethod());

        $statusCode = $response->getStatusCode();
        $responseBody = (string) $response->getContent();

        // Only persist a replayable result for successful, reasonably-sized
        // responses. For everything else — 4xx (validation error, conflict)
        // would return the stale failure after the client corrected the
        // request, 5xx is transient, and oversized bodies (see
        // MAX_STORED_BODY_BYTES) are not stored — we DROP the reservation so the
        // key stays retryable instead of being pinned as a permanent
        // in-progress row that would 409 every future attempt.
        if ($statusCode < 200 || $statusCode >= 300 || strlen($responseBody) > self::MAX_STORED_BODY_BYTES) {
            $write->delete($table, $where);
            return;
        }

        // Collect relevant response headers
        $headersToStore = [];
        foreach (['Content-Type', 'ETag', 'Location'] as $header) {
            if ($response->headers->has($header)) {
                $headersToStore[$header] = $response->headers->get($header);
            }
        }

        // Finalize the reservation we created in onRequest.
        $write->update(
            $table,
            [
                'response_code' => $statusCode,
                'response_body' => $responseBody,
                'response_headers' => \Mage::helper('core')->jsonEncode($headersToStore),
            ],
            $where,
        );
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
