<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\EventListener;

use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
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
    private const TABLE = 'maho_api_idempotency_keys';
    private const MAX_KEY_LENGTH = 255;
    private const TTL_HOURS = 24;

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

        // Validate key format
        if (strlen($idempotencyKey) < 1 || strlen($idempotencyKey) > self::MAX_KEY_LENGTH) {
            throw new BadRequestHttpException('X-Idempotency-Key must be between 1 and 255 characters');
        }

        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $idempotencyKey)) {
            throw new BadRequestHttpException('X-Idempotency-Key may only contain alphanumeric characters, dashes, and underscores');
        }

        $scope = $this->getUserScope();
        $path = $request->getPathInfo();
        $method = $request->getMethod();

        // Check for existing response
        $resource = \Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $table = $resource->getTableName(self::TABLE);

        $existing = $read->fetchRow(
            "SELECT response_code, response_body, response_headers FROM {$table} "
            . 'WHERE idempotency_key = ? AND user_scope = ? AND request_path = ? AND request_method = ?',
            [$idempotencyKey, $scope, $path, $method],
        );

        if ($existing) {
            $headers = json_decode($existing['response_headers'] ?? '{}', true) ?: [];
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

        // Probabilistic cleanup: 1 in 100 requests
        if (random_int(1, 100) === 1) {
            $this->cleanup($resource);
        }
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

        $scope = $request->attributes->get('_idempotency_scope', 'anonymous');
        $response = $event->getResponse();

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
                'response_body' => $response->getContent(),
                'response_headers' => json_encode($headersToStore),
                'created_at' => \Mage::getModel('core/date')->gmtDate(),
            ]);
        } catch (\Exception $e) {
            // Duplicate key — another concurrent request stored it first, that's fine
            \Mage::logException($e);
        }
    }

    private function getUserScope(): string
    {
        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return 'anonymous';
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

        return 'anonymous';
    }

    private function cleanup(\Mage_Core_Model_Resource $resource): void
    {
        try {
            $write = $resource->getConnection('core_write');
            $table = $resource->getTableName(self::TABLE);
            $cutoff = date('Y-m-d H:i:s', time() - (self::TTL_HOURS * 3600));
            $write->delete($table, $write->quoteInto('created_at < ?', $cutoff));
        } catch (\Exception $e) {
            // Cleanup failure is non-critical
            \Mage::logException($e);
        }
    }
}
