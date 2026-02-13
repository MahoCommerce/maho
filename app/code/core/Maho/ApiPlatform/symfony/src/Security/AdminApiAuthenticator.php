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

namespace Maho\ApiPlatform\Security;

use Mage;
use Mage_Oauth_Model_Consumer;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Admin API Authenticator
 * Validates Bearer key:secret tokens for admin API endpoints using OAuth consumers.
 * Permissions are loaded from api_role/api_rule tables at authentication time.
 */
final class AdminApiAuthenticator extends AbstractAuthenticator
{
    public const CONSUMER_ATTRIBUTE = '_admin_api_consumer';

    private const AUTHORIZATION_HEADER = 'Authorization';
    private const BEARER_PREFIX = 'Bearer ';

    /**
     * Check if this authenticator supports the request
     */
    #[\Override]
    public function supports(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/admin/');
    }

    /**
     * Authenticate the request and return a passport
     */
    #[\Override]
    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get(self::AUTHORIZATION_HEADER, '');

        if (!str_starts_with($authHeader, self::BEARER_PREFIX)) {
            throw new CustomUserMessageAuthenticationException('Missing or invalid Authorization header');
        }

        $token = substr($authHeader, strlen(self::BEARER_PREFIX));
        $parts = explode(':', $token, 2);

        if (count($parts) !== 2) {
            throw new CustomUserMessageAuthenticationException('Invalid token format. Expected key:secret');
        }

        [$key, $secret] = $parts;

        /** @var Mage_Oauth_Model_Consumer $consumer */
        $consumer = Mage::getModel('oauth/consumer')->load($key, 'key');

        if (!$consumer->getId()) {
            throw new CustomUserMessageAuthenticationException('Invalid consumer key');
        }

        if ($consumer->getSecret() !== $secret) {
            throw new CustomUserMessageAuthenticationException('Invalid consumer secret');
        }

        // Check if consumer has an assigned API role
        $roleId = $consumer->getData('api_role_id');
        if (!$roleId) {
            throw new CustomUserMessageAuthenticationException('Consumer does not have admin API access');
        }

        // Check expiration
        $expiresAt = $consumer->getData('expires_at');
        if ($expiresAt && strtotime($expiresAt) < time()) {
            throw new CustomUserMessageAuthenticationException('Consumer token has expired');
        }

        // Load permissions from api_rule table
        $permissions = $this->loadPermissions((int) $roleId);

        // Parse store access
        $storeIds = $this->parseStoreIds($consumer->getData('store_ids'));

        // Update last used timestamp (inline, no model save for efficiency)
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $write->update(
            $resource->getTableName('oauth/consumer'),
            ['last_used_at' => (new \DateTime('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s')],
            ['entity_id = ?' => (int) $consumer->getId()],
        );

        $user = new AdminApiUser(
            consumer: $consumer,
            permissions: $permissions,
            allowedStoreIds: $storeIds,
        );

        // Store on request for processor access
        $request->attributes->set(self::CONSUMER_ATTRIBUTE, $user);

        return new SelfValidatingPassport(
            new UserBadge($consumer->getKey(), fn() => $user),
        );
    }

    /**
     * Handle successful authentication
     */
    #[\Override]
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Return null to allow the request to continue
        return null;
    }

    /**
     * Handle authentication failure
     */
    #[\Override]
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new JsonResponse([
            '@context' => '/api/contexts/Error',
            '@type' => 'hydra:Error',
            'hydra:title' => 'Unauthorized',
            'hydra:description' => $exception->getMessage(),
        ], Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Load permission strings from api_rule table for a role
     *
     * @return array<string>
     */
    private function loadPermissions(int $roleId): array
    {
        $resource = Mage::getSingleton('core/resource');
        $read = $resource->getConnection('core_read');
        $ruleTable = $resource->getTableName('api/rule');

        $rules = $read->fetchAll(
            $read->select()
                ->from($ruleTable, ['resource_id'])
                ->where('role_id = ?', $roleId)
                ->where('role_type = ?', 'G')
                ->where('api_permission = ?', 'allow'),
        );

        $permissions = [];
        foreach ($rules as $rule) {
            if ($rule['resource_id'] === 'all') {
                return ['all'];
            }
            $permissions[] = $rule['resource_id'];
        }

        return $permissions;
    }

    /**
     * Parse store_ids from consumer data
     *
     * @return array<int>|null null means all stores
     */
    private function parseStoreIds(?string $storeIds): ?array
    {
        if (empty($storeIds) || $storeIds === 'all') {
            return null; // null = all stores allowed
        }
        $decoded = json_decode($storeIds, true);
        if (!is_array($decoded)) {
            return null;
        }
        return array_map('intval', $decoded);
    }
}
