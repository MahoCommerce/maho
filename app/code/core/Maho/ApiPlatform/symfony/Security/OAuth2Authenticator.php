<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Security;

use Maho\ApiPlatform\Service\JwtService;
use Maho\ApiPlatform\Service\TokenBlacklist;
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
 * OAuth2 JWT Token Authenticator
 * Validates Bearer tokens from Authorization header
 */
class OAuth2Authenticator extends AbstractAuthenticator
{
    private const AUTHORIZATION_HEADER = 'Authorization';
    private const BEARER_PREFIX = 'Bearer ';

    public function __construct(
        private JwtService $jwtService,
        private TokenBlacklist $tokenBlacklist,
        private CustomerUserProvider $customerUserProvider,
        private AdminUserProvider $adminUserProvider,
    ) {}

    /**
     * Check if this authenticator supports the request
     */
    #[\Override]
    public function supports(Request $request): ?bool
    {
        return $request->headers->has(self::AUTHORIZATION_HEADER)
            && str_starts_with(
                $request->headers->get(self::AUTHORIZATION_HEADER, ''),
                self::BEARER_PREFIX,
            );
    }

    /**
     * Authenticate the request and return a passport
     */
    #[\Override]
    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get(self::AUTHORIZATION_HEADER, '');
        $token = substr($authHeader, strlen(self::BEARER_PREFIX));

        if (empty($token)) {
            throw new CustomUserMessageAuthenticationException('No API token provided');
        }

        try {
            $payload = $this->jwtService->decodeToken($token);
        } catch (\Lcobucci\JWT\Validation\RequiredConstraintsViolated $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'expired') || str_contains($message, 'used before')) {
                throw new CustomUserMessageAuthenticationException('Token has expired');
            }
            throw new CustomUserMessageAuthenticationException('Invalid or expired token');
        } catch (\Lcobucci\JWT\Token\InvalidTokenStructure|\Lcobucci\JWT\Encoding\CannotDecodeContent $e) {
            throw new CustomUserMessageAuthenticationException('Malformed token');
        } catch (\Exception $e) {
            throw new CustomUserMessageAuthenticationException('Invalid or expired token');
        }

        // Validate required payload fields
        if (!isset($payload->sub)) {
            throw new CustomUserMessageAuthenticationException('Invalid token: missing subject');
        }

        // Check token blacklist
        if (isset($payload->jti) && $this->tokenBlacklist->isRevoked($payload->jti)) {
            throw new CustomUserMessageAuthenticationException('Token has been revoked');
        }

        // Build user badge with loader callback. The identifier param is unused
        // because the JWT payload carries everything needed to recreate the user.
        $userBadge = new UserBadge(
            (string) $payload->sub,
            fn(): ApiUser => $this->createUserFromPayload($payload),
        );

        return new SelfValidatingPassport($userBadge);
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
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'error' => 'authentication_error',
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Create ApiUser from JWT payload.
     *
     * Customer/admin tokens are re-validated against the DB on every request via
     * the matching UserProvider, so deactivation takes effect immediately rather
     * than waiting for the JWT to expire. API-user tokens are also re-validated
     * (see the api_user branch below).
     */
    private function createUserFromPayload(object $payload): ApiUser
    {
        $type = $payload->type ?? 'customer';
        $allowedStoreIds = null;
        if (isset($payload->allowed_store_ids) && is_array($payload->allowed_store_ids)) {
            $allowedStoreIds = array_map('intval', $payload->allowed_store_ids);
        }

        if ($type === 'admin' && isset($payload->admin_id)) {
            $adminId = (int) $payload->admin_id;
            try {
                /** @var ApiUser $base */
                $base = $this->adminUserProvider->loadUserByIdentifier('admin_' . $adminId);
            } catch (\Symfony\Component\Security\Core\Exception\UserNotFoundException) {
                throw new CustomUserMessageAuthenticationException('Admin account is inactive or not found');
            }

            // Hydrate Mage's admin session with the authenticated admin user
            // and load their ACL. Without this, every Maho ACL check (both the
            // AdminAclListener below and any inline isAllowed() calls in
            // providers/processors) sees an unauthenticated session and denies
            // by default. Mirrors AdminBridgeListener for the JWT path.
            $admin = \Mage::getModel('admin/user')->load($adminId);
            if ($admin->getId()) {
                $session = \Mage::getSingleton('admin/session');
                $session->setUser($admin);
                $session->setAcl(\Mage::getResourceModel('admin/acl')->loadAcl());
            }

            return new ApiUser(
                identifier: (string) $payload->sub,
                roles: $base->getRoles(),
                adminId: $adminId,
                allowedStoreIds: $allowedStoreIds,
            );
        }

        if ($type === 'api_user' && isset($payload->api_user_id)) {
            $apiUserId = (int) $payload->api_user_id;
            $apiUser = \Mage::getModel('api/user')->load($apiUserId);
            if (!$apiUser->getId() || !(int) $apiUser->getIsActive()) {
                throw new CustomUserMessageAuthenticationException('API user account is inactive or not found');
            }
            return new ApiUser(
                identifier: (string) $payload->sub,
                roles: ['ROLE_API_USER'],
                apiUserId: $apiUserId,
                permissions: $this->jwtService->loadApiUserPermissions($apiUser),
                allowedStoreIds: $allowedStoreIds,
            );
        }

        // Default: customer token
        if (!isset($payload->customer_id)) {
            throw new CustomUserMessageAuthenticationException('Invalid token: missing customer subject');
        }
        $customerId = (int) $payload->customer_id;
        try {
            /** @var ApiUser $base */
            $base = $this->customerUserProvider->loadUserByIdentifier('customer_' . $customerId);
        } catch (\Symfony\Component\Security\Core\Exception\UserNotFoundException) {
            throw new CustomUserMessageAuthenticationException('Customer account is inactive or not found');
        }

        return new ApiUser(
            identifier: (string) $payload->sub,
            roles: $base->getRoles(),
            customerId: $customerId,
            allowedStoreIds: $allowedStoreIds,
        );
    }

}
