<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api
 */

declare(strict_types=1);

namespace Mage\Api\Api;

use ApiPlatform\Metadata\Operation;
use Mage\Checkout\Api\CartService;
use Maho\ApiPlatform\Service\JwtService;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\ApiPlatform\Service\TokenBlacklist;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthTokenProcessor extends \Maho\ApiPlatform\Processor
{
    private JwtService $jwtService;
    private TokenBlacklist $tokenBlacklist;
    private CartService $cartService;

    public function __construct(
        Security $security,
        JwtService $jwtService,
        TokenBlacklist $tokenBlacklist,
        CartService $cartService,
    ) {
        parent::__construct($security);
        $this->jwtService = $jwtService;
        $this->tokenBlacklist = $tokenBlacklist;
        $this->cartService = $cartService;
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

        $request = $context['request'] ?? null;
        if ($request === null) {
            $body = [];
        } else {
            try {
                $body = (array) \Mage::helper('core')->jsonDecode($request->getContent() ?: '[]');
            } catch (\JsonException) {
                $body = [];
            }
        }

        $this->checkRateLimitByIp('auth_token', 'auth_token_ip', 60);

        $grantType = $body['grant_type'] ?? 'customer';

        return match ($grantType) {
            'customer' => $this->handleCustomerGrant($body),
            'client_credentials' => $this->handleClientCredentialsGrant($body),
            'api_user' => $this->handleApiUserGrant($body),
            default => throw new BadRequestHttpException('Unsupported grant type. Use: customer, client_credentials, api_user'),
        };
    }

    private function handleCustomerGrant(array $data): AuthToken
    {
        $email = $data['email'] ?? $data['username'] ?? '';
        $password = $data['password'] ?? '';

        $coreHelper = \Mage::helper('core');

        if (!$coreHelper->isValidNotBlank($email) || !$coreHelper->isValidNotBlank($password)) {
            throw new BadRequestHttpException('Email and password are required');
        }

        if (!$coreHelper->isValidEmail($email)) {
            throw new BadRequestHttpException('Invalid email format');
        }

        $this->checkRateLimit('auth_token:email:' . strtolower($email), 'customer_login', 60);

        try {
            $customer = $this->authenticateCustomerAcrossWebsites($email, $password);

            $token = $this->jwtService->generateCustomerToken($customer);

            $guestCartMaskedId = $data['maskedId'] ?? $data['cartId'] ?? $data['guestCartId'] ?? null;
            $cartId = null;
            $customerCart = null;

            if ($guestCartMaskedId && is_string($guestCartMaskedId) && preg_match('/^[a-f0-9]{32}$/i', $guestCartMaskedId)) {
                try {
                    $guestCart = $this->cartService->getCart(null, $guestCartMaskedId);

                    if ($guestCart && $guestCart->getId() && !$guestCart->getCustomerId()) {
                        $customerCart = \Mage::getModel('sales/quote')
                            ->setSharedStoreIds([\Mage::app()->getStore()->getId()])
                            ->loadByCustomer((int) $customer->getId());

                        if (!$customerCart->getId()) {
                            $customerCart = \Mage::getModel('sales/quote');
                            $customerCart->setStoreId(\Mage::app()->getStore()->getId());
                            $customerCart->assignCustomer($customer);
                            $customerCart->setIsActive(1);
                            $customerCart->save();
                        }

                        $customerCart->merge($guestCart);

                        // Import customer default addresses so shipping quotes work on cart page
                        $defaultShipping = $customer->getDefaultShippingAddress();
                        if ($defaultShipping && $defaultShipping->getId()) {
                            $shippingAddress = $customerCart->getShippingAddress();
                            if (!$shippingAddress->getFirstname()) {
                                $shippingAddress->importCustomerAddress($defaultShipping);
                                $shippingAddress->setSaveInAddressBook(0);
                            }
                        }
                        $defaultBilling = $customer->getDefaultBillingAddress();
                        if ($defaultBilling && $defaultBilling->getId()) {
                            $billingAddress = $customerCart->getBillingAddress();
                            if (!$billingAddress->getFirstname()) {
                                $billingAddress->importCustomerAddress($defaultBilling);
                                $billingAddress->setSaveInAddressBook(0);
                            }
                        }

                        $customerCart->collectTotals();
                        $customerCart->save();

                        $guestCart->setIsActive(0);
                        $guestCart->save();

                        $cartId = (int) $customerCart->getId();
                    }
                } catch (\Exception $e) {
                    \Mage::log('Cart merge failed: ' . $e->getMessage(), \Mage::LOG_WARNING);
                }
            }

            if (!$cartId) {
                $customerCart = \Mage::getModel('sales/quote')
                    ->setSharedStoreIds([\Mage::app()->getStore()->getId()])
                    ->loadByCustomer((int) $customer->getId());
                $cartId = $customerCart->getId() ? (int) $customerCart->getId() : null;
            }

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
            $dto->cartId = $cartId;
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
     * Authenticate a customer in a way that works in single- and multi-website
     * setups without requiring the client to send X-Store-Code.
     *
     * - When customer accounts are GLOBAL: walk every website and try the
     *   credentials. The customer exists on exactly one (the email is unique
     *   within a sharing scope), so at most one website yields a match. We
     *   surface the same "Invalid email or password" message for misses to
     *   keep responses indistinguishable.
     * - When customer accounts are PER-WEBSITE: try the request store's
     *   resolved website first; on a miss with EMAIL_NOT_CONFIRMED, propagate
     *   the confirmation error. On any other miss we still try other websites
     *   (the email is unique per website, so trying the rest is cheap and
     *   covers clients that didn't send X-Store-Code).
     *
     * @throws UnauthorizedHttpException for invalid credentials
     * @throws HttpException 403 when the matched account isn't confirmed
     */
    private function authenticateCustomerAcrossWebsites(#[\SensitiveParameter]
        string $email, #[\SensitiveParameter]
        string $password): \Mage_Customer_Model_Customer
    {
        $primaryWebsiteId = (int) \Mage::app()->getStore()->getWebsiteId();
        $websiteIds = [$primaryWebsiteId];
        foreach (\Mage::app()->getWebsites() as $website) {
            $wid = (int) $website->getId();
            if ($wid !== $primaryWebsiteId) {
                $websiteIds[] = $wid;
            }
        }

        $confirmation = null;
        foreach ($websiteIds as $websiteId) {
            $customer = \Mage::getModel('customer/customer')->setWebsiteId($websiteId);
            try {
                $customer->authenticate($email, $password);
                return $customer;
            } catch (\Mage_Core_Exception $e) {
                if ($e->getCode() === \Mage_Customer_Model_Customer::EXCEPTION_EMAIL_NOT_CONFIRMED) {
                    // Save and keep trying — another website may have a confirmed account.
                    $confirmation ??= $e;
                }
            }
        }

        if ($confirmation !== null) {
            throw new HttpException(403, 'This account is not confirmed. Please check your email for the confirmation link.');
        }
        throw new UnauthorizedHttpException('Bearer', 'Invalid email or password');
    }

    private function handleClientCredentialsGrant(array $data): AuthToken
    {
        $clientId = $data['client_id'] ?? '';
        $clientSecret = $data['client_secret'] ?? '';

        if (empty($clientId) || empty($clientSecret)) {
            throw new BadRequestHttpException('client_id and client_secret are required');
        }

        // Per-client cap mirrors the customer/api_user grants so an attacker
        // who's identified or guessed a client_id can't brute-force the
        // matching secret faster than the per-client bucket allows, even when
        // rotating IPs to dodge the IP-level limit applied in handleGetToken().
        $this->checkRateLimit('auth_token:client:' . $clientId, 'customer_login', 60);

        try {
            $resource = \Mage::getSingleton('core/resource');
            $read = $resource->getConnection('core_read');
            $table = $resource->getTableName('api/user');

            $row = $read->fetchRow(
                $read->select()->from($table)->where('client_id = ?', $clientId),
            );

            if (!$row) {
                throw new UnauthorizedHttpException('Bearer', 'Invalid client credentials');
            }

            if (!(int) $row['is_active']) {
                throw new UnauthorizedHttpException('Bearer', 'API user account is inactive');
            }

            if (!password_verify($clientSecret, $row['client_secret'])) {
                throw new UnauthorizedHttpException('Bearer', 'Invalid client credentials');
            }

            $apiUser = \Mage::getModel('api/user')->load($row['user_id']);

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
        $username = $data['username'] ?? '';
        $apiKey = $data['api_key'] ?? '';

        if (empty($username) || empty($apiKey)) {
            throw new BadRequestHttpException('username and api_key are required');
        }

        $this->checkRateLimit('auth_token:api_user:' . strtolower($username), 'customer_login', 60);

        try {
            $apiUser = \Mage::getModel('api/user')->loadByUsername($username);

            if (!$apiUser->getId()) {
                throw new UnauthorizedHttpException('Bearer', 'Invalid API credentials');
            }

            if (!(int) $apiUser->getIsActive()) {
                throw new UnauthorizedHttpException('Bearer', 'API user account is inactive');
            }

            if (!\Mage::helper('core')->validateHash($apiKey, $apiUser->getApiKey())) {
                throw new UnauthorizedHttpException('Bearer', 'Invalid API credentials');
            }

            return $this->generateApiUserTokenResponse($apiUser);
        } catch (UnauthorizedHttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            \Mage::logException($e);
            throw new HttpException(500, 'An error occurred during authentication');
        }
    }

    private function generateApiUserTokenResponse(\Mage_Api_Model_User $apiUser): AuthToken
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

        try {
            $payload = $this->jwtService->decodeToken($tokenString);

            if (!isset($payload->jti)) {
                throw new UnauthorizedHttpException('Bearer', 'Token missing required jti claim');
            }
            if ($this->tokenBlacklist->isRevoked($payload->jti)) {
                throw new UnauthorizedHttpException('Bearer', 'Token has been revoked');
            }

            if (!isset($payload->customer_id)) {
                throw new UnauthorizedHttpException('Bearer', 'Token does not contain customer information');
            }

            $customer = \Mage::getModel('customer/customer')->load($payload->customer_id);
            if (!$customer->getId()) {
                throw new UnauthorizedHttpException('Bearer', 'Customer not found');
            }

            // Honour deactivation between issuance and refresh — without this,
            // a banned/disabled customer can keep refreshing tokens indefinitely
            // until the original token's expiry.
            if (!$customer->getIsActive()) {
                throw new UnauthorizedHttpException('Bearer', 'Customer account is inactive');
            }

            $this->tokenBlacklist->revoke($payload->jti, (int) ($payload->exp ?? time() + 86400));
            $newToken = $this->jwtService->generateCustomerToken($customer);

            $dto = new AuthToken();
            $dto->token = $newToken;
            $dto->tokenType = 'Bearer';
            $dto->expiresIn = $this->jwtService->getTokenExpiry();

            return $dto;
        } catch (UnauthorizedHttpException $e) {
            throw $e;
        } catch (\Exception $e) {
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
