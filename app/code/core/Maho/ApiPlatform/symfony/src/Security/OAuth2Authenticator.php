<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Security;

use Maho\ApiPlatform\Service\JwtService;
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

    private JwtService $jwtService;

    public function __construct()
    {
        $this->jwtService = new JwtService();
    }

    /**
     * Check if this authenticator supports the request
     */
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
    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get(self::AUTHORIZATION_HEADER, '');
        $token = substr($authHeader, strlen(self::BEARER_PREFIX));

        if (empty($token)) {
            throw new CustomUserMessageAuthenticationException('No API token provided');
        }

        try {
            $payload = $this->jwtService->decodeToken($token);
        } catch (\Exception $e) {
            throw new CustomUserMessageAuthenticationException(
                'Invalid or expired token: ' . $e->getMessage(),
            );
        }

        // Validate required payload fields
        if (!isset($payload->sub)) {
            throw new CustomUserMessageAuthenticationException('Invalid token: missing subject');
        }

        // Build user badge with loader callback
        $userBadge = new UserBadge(
            $payload->sub,
            function (string $userIdentifier) use ($payload): ApiUser {
                return $this->createUserFromPayload($payload);
            },
        );

        return new SelfValidatingPassport($userBadge);
    }

    /**
     * Handle successful authentication
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Return null to allow the request to continue
        return null;
    }

    /**
     * Handle authentication failure
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            'error' => 'authentication_error',
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    /**
     * Create ApiUser from JWT payload
     */
    private function createUserFromPayload(object $payload): ApiUser
    {
        $roles = $this->extractRoles($payload);
        $customerId = null;
        $adminId = null;
        $apiUserId = null;
        $permissions = [];

        if (isset($payload->customer_id)) {
            $customerId = (int) $payload->customer_id;
        }

        if (isset($payload->admin_id)) {
            $adminId = (int) $payload->admin_id;
        }

        if (isset($payload->api_user_id)) {
            $apiUserId = (int) $payload->api_user_id;
        }

        if (isset($payload->permissions) && is_array($payload->permissions)) {
            $permissions = $payload->permissions;
        }

        return new ApiUser(
            identifier: $payload->sub,
            roles: $roles,
            customerId: $customerId,
            adminId: $adminId,
            apiUserId: $apiUserId,
            permissions: $permissions,
        );
    }

    /**
     * Extract roles from JWT payload
     *
     * @return array<string>
     */
    private function extractRoles(object $payload): array
    {
        $roles = [];

        // Check for explicit roles in payload
        if (isset($payload->roles) && is_array($payload->roles)) {
            $roles = $payload->roles;
        }

        // Check for type-based roles
        if (isset($payload->type)) {
            switch ($payload->type) {
                case 'admin':
                    if (!in_array('ROLE_ADMIN', $roles, true)) {
                        $roles[] = 'ROLE_ADMIN';
                    }
                    break;
                case 'pos':
                    if (!in_array('ROLE_POS', $roles, true)) {
                        $roles[] = 'ROLE_POS';
                    }
                    break;
                case 'api_user':
                    if (!in_array('ROLE_API_USER', $roles, true)) {
                        $roles[] = 'ROLE_API_USER';
                    }
                    break;
                case 'customer':
                default:
                    if (!in_array('ROLE_USER', $roles, true)) {
                        $roles[] = 'ROLE_USER';
                    }
                    break;
            }
        }

        // Ensure at least ROLE_USER is present
        if (empty($roles)) {
            $roles[] = 'ROLE_USER';
        }

        return $roles;
    }
}
