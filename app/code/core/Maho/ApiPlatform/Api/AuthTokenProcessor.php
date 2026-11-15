<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Api;

use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Http\RequestInput;
use Maho\ApiPlatform\Service\JwtService;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\ApiPlatform\Service\TokenBlacklist;
use Maho\ApiPlatform\Trait\RateLimitTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthTokenProcessor extends \Maho\ApiPlatform\Processor
{
    use RateLimitTrait;

    private readonly \Mage_Checkout_Service_Cart $cartService;

    public function __construct(
        Security $security,
        private JwtService $jwtService,
        private TokenBlacklist $tokenBlacklist,
    ) {
        parent::__construct($security);
        $this->cartService = \Mage::getService('checkout/cart');
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): AuthToken
    {
        $operationName = $operation->getName();

        return match ($operationName) {
            'get_token', '_api_AuthToken_get_token' => $this->handleGetToken($context),
            'refresh_token', '_api_AuthToken_refresh_token' => $this->handleRefreshToken($context),
            'logout', '_api_AuthToken_logout' => $this->handleLogout($context),
            default => throw new BadRequestHttpException('Unknown operation'),
        };
    }

    private function handleGetToken(array $context): AuthToken
    {
        StoreContext::ensureStore();

        // A body that does not decode reads as empty, so it gets the invalid_request error of the grant
        $body = RequestInput::tryDecode($context['request'] ?? null) ?? [];

        $this->checkRateLimitByIp('auth_token', 'auth_token_ip', 60);

        $grantType = $body['grant_type'] ?? 'customer';

        return match ($grantType) {
            'customer' => $this->handleCustomerGrant($body),
            'magic_link' => $this->handleMagicLinkGrant($body),
            'client_credentials' => $this->handleClientCredentialsGrant($body),
            'api_user' => $this->handleApiUserGrant($body),
            default => throw new BadRequestHttpException('Unsupported grant type. Use: customer, magic_link, client_credentials, api_user', null, 0, ['X-Api-Error-Code' => 'unsupported_grant_type']),
        };
    }

    private function handleCustomerGrant(array $data): AuthToken
    {
        // A value of a wrong type reads as missing, so it gets the invalid_request error of the grant
        $email = RequestInput::toString($data['email'] ?? $data['username'] ?? null) ?? '';
        $password = RequestInput::toString($data['password'] ?? null) ?? '';

        $coreHelper = \Mage::helper('core');

        if (!$coreHelper->isValidNotBlank($email) || !$coreHelper->isValidNotBlank($password)) {
            throw new BadRequestHttpException('Email and password are required', null, 0, ['X-Api-Error-Code' => 'invalid_request']);
        }

        if (!$coreHelper->isValidEmail($email)) {
            throw new BadRequestHttpException('Invalid email format', null, 0, ['X-Api-Error-Code' => 'invalid_request']);
        }

        // Scalar, not string: clients routinely send a TOTP code as a JSON number
        $rawTwofaCode = $data['twofa_code'] ?? null;
        $twofaCode = is_scalar($rawTwofaCode) ? trim((string) $rawTwofaCode) : null;

        return $this->generateCustomerTokenResponse($this->authenticateCustomer($email, $password, $twofaCode), $data);
    }

    /**
     * Give a customer token for the token of a magic login link. A customer who uses 2FA sends the code with the
     * same link: the link stays valid until the code is right.
     */
    private function handleMagicLinkGrant(array $data): AuthToken
    {
        $token = $data['token'] ?? '';
        if (!is_string($token) || trim($token) === '') {
            throw new BadRequestHttpException('token is required', null, 0, ['X-Api-Error-Code' => 'invalid_request']);
        }
        $rawTwofaCode = $data['twofa_code'] ?? null;
        $twofaCode = is_scalar($rawTwofaCode) ? trim((string) $rawTwofaCode) : null;

        /** @var \Mage_Customer_Service_Authentication $service */
        $service = \Mage::getService('customer/authentication');

        return $this->generateCustomerTokenResponse($service->authenticateByMagicLink(trim($token), $twofaCode), $data);
    }

    private function generateCustomerTokenResponse(\Mage_Customer_Model_Customer $customer, array $data): AuthToken
    {
        try {
            $token = $this->jwtService->generateCustomerToken($customer);

            $customerCart = $this->cartService->mergeAtLogin(
                $data['maskedId'] ?? $data['cartId'] ?? $data['guestCartId'] ?? null,
                (int) $customer->getId(),
            );

            $dto = new AuthToken();
            $dto->token = $token;
            $dto->tokenType = 'Bearer';
            $dto->expiresIn = $this->jwtService->getTokenExpiry();
            $dto->customer = [
                'id' => (int) $customer->getId(),
                'email' => $customer->getEmail(),
                'firstName' => $customer->getFirstname(),
                'lastName' => $customer->getLastname(),
            ];
            $dto->cartId = $customerCart ? (int) $customerCart->getId() : null;
            $dto->cartMaskedId = $customerCart?->getData('masked_quote_id');
            $dto->cartItemsQty = $customerCart ? (float) $customerCart->getItemsQty() : 0;

            return $dto;
        } catch (UnauthorizedHttpException|HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Mage::logException($e);
            throw new HttpException(500, 'An error occurred during authentication');
        }
    }

    /**
     * Check the login with the customer service, in the website of the request first and then in the others, so that
     * a client does not need to send X-Store-Code. A wrong email, password or 2FA code is a 401 with an error code.
     */
    private function authenticateCustomer(
        #[\SensitiveParameter]
        string $email,
        #[\SensitiveParameter]
        string $password,
        #[\SensitiveParameter]
        ?string $twofaCode,
    ): \Mage_Customer_Model_Customer {
        /** @var \Mage_Customer_Service_Authentication $service */
        $service = \Mage::getService('customer/authentication');

        return $service->authenticateInAnyWebsite($email, $password, (int) \Mage::app()->getStore()->getWebsiteId(), $twofaCode);
    }

    private function handleClientCredentialsGrant(array $data): AuthToken
    {
        $clientId = RequestInput::toString($data['client_id'] ?? null) ?? '';
        $clientSecret = RequestInput::toString($data['client_secret'] ?? null) ?? '';

        if (empty($clientId) || empty($clientSecret)) {
            throw new BadRequestHttpException('client_id and client_secret are required', null, 0, ['X-Api-Error-Code' => 'invalid_request']);
        }

        // Per-client cap mirrors the customer/api_user grants so an attacker
        // who's identified or guessed a client_id can't brute-force the
        // matching secret faster than the per-client bucket allows, even when
        // rotating IPs to dodge the IP-level limit applied in handleGetToken().
        $this->checkRateLimit('auth_token:client:' . $clientId, 'customer_login', 60);

        try {
            $apiUser = \Mage::getModel('apiplatform/user')->loadByClientId($clientId);

            // Always run password_verify (constant cost) even when the client_id
            // is unknown, using a dummy hash. This keeps the timing and the error
            // message identical whether the row is missing or the secret is wrong,
            // so a valid client_id can't be enumerated via response differences.
            $secretValid = $apiUser->getId()
                ? $apiUser->verifyClientSecret($clientSecret)
                : password_verify($clientSecret, '$2y$12$RlGJvrrS3GC1gKQvcwvjHedpfOFOSifqxMHE5umNj0nelSZsQqdYO');

            if (!$apiUser->getId() || !$secretValid) {
                throw new UnauthorizedHttpException('Bearer', 'Invalid client credentials', null, 0, ['X-Api-Error-Code' => 'invalid_client']);
            }

            // Only disclose account state once the secret is proven correct.
            if (!$apiUser->getIsActive()) {
                throw new UnauthorizedHttpException('Bearer', 'API user account is inactive');
            }

            return $this->generateApiUserTokenResponse($apiUser);
        } catch (UnauthorizedHttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Mage::logException($e);
            throw new HttpException(500, 'An error occurred during authentication');
        }
    }

    private function handleApiUserGrant(array $data): AuthToken
    {
        $username = RequestInput::toString($data['username'] ?? null) ?? '';
        $apiKey = RequestInput::toString($data['api_key'] ?? null) ?? '';

        if (empty($username) || empty($apiKey)) {
            throw new BadRequestHttpException('username and api_key are required', null, 0, ['X-Api-Error-Code' => 'invalid_request']);
        }

        $this->checkRateLimit('auth_token:api_user:' . strtolower($username), 'customer_login', 60);

        try {
            $apiUser = \Mage::getModel('apiplatform/user')->loadByUsername($username);

            // Always run the hash check (constant cost) even when the username is
            // unknown, using a dummy hash. This keeps the timing and the error
            // message identical whether the user is missing or the key is wrong,
            // so a valid username can't be enumerated via response differences.
            $storedHash = $apiUser->getId()
                ? (string) $apiUser->getApiKey()
                : '$2y$12$RlGJvrrS3GC1gKQvcwvjHedpfOFOSifqxMHE5umNj0nelSZsQqdYO';
            $keyValid = \Mage::helper('core')->validateHash($apiKey, $storedHash);

            if (!$apiUser->getId() || !$keyValid) {
                throw new UnauthorizedHttpException('Bearer', 'Invalid API credentials', null, 0, ['X-Api-Error-Code' => 'invalid_client']);
            }

            // Only disclose account state once the key is proven correct.
            if (!$apiUser->getIsActive()) {
                throw new UnauthorizedHttpException('Bearer', 'API user account is inactive');
            }

            return $this->generateApiUserTokenResponse($apiUser);
        } catch (UnauthorizedHttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Mage::logException($e);
            throw new HttpException(500, 'An error occurred during authentication');
        }
    }

    private function generateApiUserTokenResponse(\Maho_ApiPlatform_Model_User $apiUser): AuthToken
    {
        $permissions = $this->jwtService->loadApiUserPermissions($apiUser);
        $token = $this->jwtService->generateApiUserToken($apiUser, $permissions);

        $dto = new AuthToken();
        $dto->token = $token;
        $dto->tokenType = 'Bearer';
        $dto->expiresIn = $this->jwtService->getTokenExpiry();
        $dto->apiUser = [
            'id' => (int) $apiUser->getId(),
            'username' => $apiUser->getUsername(),
        ];
        $dto->permissions = $permissions;

        return $dto;
    }

    private function handleRefreshToken(array $context): AuthToken
    {
        $request = $context['request'] ?? null;
        $authHeader = $request?->headers->get('Authorization', '') ?? '';

        if (!str_starts_with($authHeader, 'Bearer ')) {
            throw new BadRequestHttpException('Bearer token required');
        }

        $tokenString = substr($authHeader, 7);

        $this->checkRateLimitByIp('refresh_token', 'auth_token_ip', 60);

        try {
            $payload = $this->jwtService->decodeToken($tokenString);

            if (!isset($payload->jti)) {
                throw new UnauthorizedHttpException('Bearer', 'Token missing required jti claim');
            }
            if ($this->tokenBlacklist->isRevoked($payload->jti)) {
                throw new UnauthorizedHttpException('Bearer', 'Token has been revoked');
            }

            if (!isset($payload->customer_id)) {
                // Refresh is only defined for customer tokens. Admin and API-user
                // integrations must re-authenticate with their own grant instead.
                throw new UnauthorizedHttpException('Bearer', 'Token refresh is only supported for customer tokens; re-authenticate to obtain a new token');
            }

            // The service refuses a customer that an admin deleted or set to not active after the token was issued.
            // A token endpoint answers each refused token with a 401.
            /** @var \Mage_Customer_Service_Customer $service */
            $service = \Mage::getService('customer/customer');
            try {
                $customer = $service->requireActiveById((int) $payload->customer_id);
            } catch (\Mage_Core_Exception_NoSuchEntity) {
                throw new UnauthorizedHttpException('Bearer', 'Customer not found');
            } catch (\Mage_Core_Exception_Forbidden) {
                throw new UnauthorizedHttpException('Bearer', 'Customer account is inactive');
            }

            // Issue the replacement token first; only revoke the old one once we
            // hold a valid new token, so a generation failure can't strand the
            // customer with no usable token (forcing a full re-login).
            $newToken = $this->jwtService->generateCustomerToken($customer);
            $this->tokenBlacklist->revoke($payload->jti, (int) ($payload->exp ?? time() + 86400));

            $dto = new AuthToken();
            $dto->token = $newToken;
            $dto->tokenType = 'Bearer';
            $dto->expiresIn = $this->jwtService->getTokenExpiry();

            return $dto;
        } catch (UnauthorizedHttpException $e) {
            throw $e;
        } catch (\Exception) {
            throw new UnauthorizedHttpException('Bearer', 'Invalid or expired token');
        }
    }

    private function handleLogout(array $context): AuthToken
    {
        $request = $context['request'] ?? null;
        $authHeader = $request?->headers->get('Authorization', '') ?? '';

        if (str_starts_with($authHeader, 'Bearer ')) {
            $tokenString = substr($authHeader, 7);
            try {
                $payload = $this->jwtService->decodeToken($tokenString);
                if (isset($payload->jti)) {
                    $this->tokenBlacklist->revoke($payload->jti, (int) ($payload->exp ?? time() + 86400));
                }
            } catch (\Exception) {
                // Token is already invalid/expired, consider it logged out
            }
        }

        $dto = new AuthToken();
        $dto->success = true;
        $dto->message = 'Successfully logged out';

        return $dto;
    }

}
