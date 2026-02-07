<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\Controller;

use Maho\ApiPlatform\Service\JwtService;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Authentication Controller
 * Handles customer login and token generation
 */
class AuthController extends AbstractController
{
    private JwtService $jwtService;

    public function __construct()
    {
        $this->jwtService = new JwtService();
    }
    /**
     * Token endpoint - supports customer, client_credentials, and api_user grant types
     */
    #[Route('/api/auth/token', name: 'api_auth_token', methods: ['POST'])]
    public function getToken(Request $request): JsonResponse
    {
        StoreContext::ensureStore();

        $data = json_decode($request->getContent(), true) ?? [];
        $grantType = $data['grant_type'] ?? 'customer';

        return match ($grantType) {
            'customer' => $this->handleCustomerGrant($data),
            'client_credentials' => $this->handleClientCredentialsGrant($data),
            'api_user' => $this->handleApiUserGrant($data),
            default => new JsonResponse([
                'error' => 'unsupported_grant_type',
                'message' => 'Unsupported grant type. Use: customer, client_credentials, api_user',
            ], Response::HTTP_BAD_REQUEST),
        };
    }

    /**
     * Handle customer email/password authentication
     */
    private function handleCustomerGrant(array $data): JsonResponse
    {
        $email = $data['email'] ?? $data['username'] ?? '';
        $password = $data['password'] ?? '';

        $coreHelper = \Mage::helper('core');

        if (!$coreHelper->isValidNotBlank($email) || !$coreHelper->isValidNotBlank($password)) {
            return new JsonResponse([
                'error' => 'invalid_request',
                'message' => 'Email and password are required',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$coreHelper->isValidEmail($email)) {
            return new JsonResponse([
                'error' => 'invalid_request',
                'message' => 'Invalid email format',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $websiteId = \Mage::app()->getStore()->getWebsiteId();

            $customer = \Mage::getModel('customer/customer')
                ->setWebsiteId($websiteId)
                ->loadByEmail($email);

            if (!$customer->getId()) {
                return new JsonResponse([
                    'error' => 'invalid_credentials',
                    'message' => 'Invalid email or password',
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (!$customer->validatePassword($password)) {
                return new JsonResponse([
                    'error' => 'invalid_credentials',
                    'message' => 'Invalid email or password',
                ], Response::HTTP_UNAUTHORIZED);
            }

            if ($customer->getConfirmation() && $customer->isConfirmationRequired()) {
                return new JsonResponse([
                    'error' => 'email_not_confirmed',
                    'message' => 'This account is not confirmed. Please check your email for the confirmation link.',
                ], Response::HTTP_FORBIDDEN);
            }

            $token = $this->jwtService->generateCustomerToken($customer);

            // Handle cart merge if guest cart ID provided
            $guestCartId = $data['cartId'] ?? $data['guestCartId'] ?? null;
            $cartId = null;

            if ($guestCartId) {
                try {
                    $guestCart = \Mage::getModel('sales/quote')->loadByIdWithoutStore((int) $guestCartId);
                    if ($guestCart->getId() && !$guestCart->getCustomerId()) {
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

            return new JsonResponse([
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => $this->jwtService->getTokenExpiry(),
                'customer' => [
                    'id' => (int) $customer->getId(),
                    'email' => $customer->getEmail(),
                    'firstName' => $customer->getFirstname(),
                    'lastName' => $customer->getLastname(),
                ],
                'cartId' => $cartId,
            ]);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'server_error',
                'message' => 'An error occurred during authentication',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Handle OAuth2 client_credentials grant (client_id + client_secret)
     */
    private function handleClientCredentialsGrant(array $data): JsonResponse
    {
        $clientId = $data['client_id'] ?? '';
        $clientSecret = $data['client_secret'] ?? '';

        if (empty($clientId) || empty($clientSecret)) {
            return new JsonResponse([
                'error' => 'invalid_request',
                'message' => 'client_id and client_secret are required',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $resource = \Mage::getSingleton('core/resource');
            $read = $resource->getConnection('core_read');
            $table = $resource->getTableName('api/user');

            $row = $read->fetchRow(
                $read->select()->from($table)->where('client_id = ?', $clientId),
            );

            if (!$row) {
                return new JsonResponse([
                    'error' => 'invalid_client',
                    'message' => 'Invalid client credentials',
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (!(int) $row['is_active']) {
                return new JsonResponse([
                    'error' => 'invalid_client',
                    'message' => 'API user account is inactive',
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (!password_verify($clientSecret, $row['client_secret'])) {
                return new JsonResponse([
                    'error' => 'invalid_client',
                    'message' => 'Invalid client credentials',
                ], Response::HTTP_UNAUTHORIZED);
            }

            $apiUser = \Mage::getModel('api/user')->load($row['user_id']);

            return $this->generateApiUserTokenResponse($apiUser);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'server_error',
                'message' => 'An error occurred during authentication',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Handle legacy api_user grant (username + api_key)
     */
    private function handleApiUserGrant(array $data): JsonResponse
    {
        $username = $data['username'] ?? '';
        $apiKey = $data['api_key'] ?? '';

        if (empty($username) || empty($apiKey)) {
            return new JsonResponse([
                'error' => 'invalid_request',
                'message' => 'username and api_key are required',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $apiUser = \Mage::getModel('api/user')->loadByUsername($username);

            if (!$apiUser->getId()) {
                return new JsonResponse([
                    'error' => 'invalid_credentials',
                    'message' => 'Invalid API credentials',
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (!(int) $apiUser->getIsActive()) {
                return new JsonResponse([
                    'error' => 'invalid_credentials',
                    'message' => 'API user account is inactive',
                ], Response::HTTP_UNAUTHORIZED);
            }

            if (!\Mage::helper('core')->validateHash($apiKey, $apiUser->getApiKey())) {
                return new JsonResponse([
                    'error' => 'invalid_credentials',
                    'message' => 'Invalid API credentials',
                ], Response::HTTP_UNAUTHORIZED);
            }

            return $this->generateApiUserTokenResponse($apiUser);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'server_error',
                'message' => 'An error occurred during authentication',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Generate JWT response for an authenticated API user
     */
    private function generateApiUserTokenResponse(\Mage_Api_Model_User $apiUser): JsonResponse
    {
        $permissions = $this->jwtService->loadApiUserPermissions($apiUser);
        $token = $this->jwtService->generateApiUserToken($apiUser, $permissions);

        return new JsonResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => $this->jwtService->getTokenExpiry(),
            'api_user' => [
                'id' => (int) $apiUser->getId(),
                'username' => $apiUser->getUsername(),
            ],
            'permissions' => $permissions,
        ]);
    }

    /**
     * Refresh token endpoint
     */
    #[Route('/api/auth/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refreshToken(Request $request): JsonResponse
    {
        // For now, just validate the current token and issue a new one
        $authHeader = $request->headers->get('Authorization', '');

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return new JsonResponse([
                'error' => 'invalid_request',
                'message' => 'Bearer token required',
            ], Response::HTTP_BAD_REQUEST);
        }

        $token = substr($authHeader, 7);

        try {
            $payload = $this->jwtService->decodeToken($token);

            if (!isset($payload->customer_id)) {
                return new JsonResponse([
                    'error' => 'invalid_token',
                    'message' => 'Token does not contain customer information',
                ], Response::HTTP_UNAUTHORIZED);
            }

            $customer = \Mage::getModel('customer/customer')->load($payload->customer_id);

            if (!$customer->getId()) {
                return new JsonResponse([
                    'error' => 'invalid_token',
                    'message' => 'Customer not found',
                ], Response::HTTP_UNAUTHORIZED);
            }

            $newToken = $this->jwtService->generateCustomerToken($customer);

            return new JsonResponse([
                'token' => $newToken,
                'token_type' => 'Bearer',
                'expires_in' => $this->jwtService->getTokenExpiry(),
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'invalid_token',
                'message' => 'Invalid or expired token',
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * Get current user info from token (includes addresses)
     */
    #[Route('/api/auth/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        return new JsonResponse($this->mapCustomerToArray($customer));
    }

    /**
     * Get customer addresses
     */
    #[Route('/api/customers/me/addresses', name: 'api_customer_addresses', methods: ['GET'])]
    public function getAddresses(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $addresses = [];
        foreach ($customer->getAddresses() as $address) {
            $addresses[] = $this->mapAddressToArray($address, $customer);
        }

        return new JsonResponse(['addresses' => $addresses]);
    }

    /**
     * Create a new customer address
     */
    #[Route('/api/customers/me/addresses', name: 'api_customer_address_create', methods: ['POST'])]
    public function createAddress(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $data = json_decode($request->getContent(), true);

        // Validate required fields
        $required = ['firstName', 'lastName', 'street', 'city', 'postcode', 'countryId', 'telephone'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return new JsonResponse([
                    'error' => 'validation_error',
                    'message' => ucfirst($field) . ' is required',
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        try {
            $address = \Mage::getModel('customer/address');
            $address->setCustomerId($customer->getId());
            $address->setFirstname($data['firstName']);
            $address->setLastname($data['lastName']);
            $address->setCompany($data['company'] ?? '');
            $address->setStreet(is_array($data['street']) ? $data['street'] : [$data['street']]);
            $address->setCity($data['city']);
            $address->setPostcode($data['postcode']);
            $address->setCountryId($data['countryId']);
            $address->setTelephone($data['telephone']);

            // Handle region
            if (!empty($data['regionId'])) {
                $address->setRegionId($data['regionId']);
            } elseif (!empty($data['region'])) {
                $address->setRegion($data['region']);
            }

            // Set as default if requested or if first address
            $isFirstAddress = count($customer->getAddresses()) === 0;
            if ($isFirstAddress || !empty($data['isDefaultBilling'])) {
                $address->setIsDefaultBilling(true);
            }
            if ($isFirstAddress || !empty($data['isDefaultShipping'])) {
                $address->setIsDefaultShipping(true);
            }

            $address->save();

            // Reload address and customer to get fresh data
            $address = \Mage::getModel('customer/address')->load($address->getId());
            $customer = \Mage::getModel('customer/customer')->load($customer->getId());

            return new JsonResponse([
                'success' => true,
                'address' => $this->mapAddressToArray($address, $customer),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'server_error',
                'message' => 'Failed to save address',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update a customer address
     */
    #[Route('/api/customers/me/addresses/{id}', name: 'api_customer_address_update', methods: ['PUT'])]
    public function updateAddress(Request $request, int $id): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        // Load and verify ownership
        $address = \Mage::getModel('customer/address')->load($id);
        if (!$address->getId() || (int) $address->getCustomerId() !== (int) $customer->getId()) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'Address not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);

        try {
            if (isset($data['firstName'])) {
                $address->setFirstname($data['firstName']);
            }
            if (isset($data['lastName'])) {
                $address->setLastname($data['lastName']);
            }
            if (isset($data['company'])) {
                $address->setCompany($data['company']);
            }
            if (isset($data['street'])) {
                $address->setStreet(is_array($data['street']) ? $data['street'] : [$data['street']]);
            }
            if (isset($data['city'])) {
                $address->setCity($data['city']);
            }
            if (isset($data['postcode'])) {
                $address->setPostcode($data['postcode']);
            }
            if (isset($data['countryId'])) {
                $address->setCountryId($data['countryId']);
            }
            if (isset($data['telephone'])) {
                $address->setTelephone($data['telephone']);
            }
            if (isset($data['regionId'])) {
                $address->setRegionId($data['regionId']);
            } elseif (isset($data['region'])) {
                $address->setRegion($data['region']);
            }

            // Handle default flags
            if (!empty($data['isDefaultBilling'])) {
                $address->setIsDefaultBilling(true);
            }
            if (!empty($data['isDefaultShipping'])) {
                $address->setIsDefaultShipping(true);
            }

            $address->save();

            // Reload to get fresh data
            $address = \Mage::getModel('customer/address')->load($address->getId());
            $customer = \Mage::getModel('customer/customer')->load($customer->getId());

            return new JsonResponse([
                'success' => true,
                'address' => $this->mapAddressToArray($address, $customer),
            ]);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'server_error',
                'message' => 'Failed to update address',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Delete a customer address
     */
    #[Route('/api/customers/me/addresses/{id}', name: 'api_customer_address_delete', methods: ['DELETE'])]
    public function deleteAddress(Request $request, int $id): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        // Load and verify ownership
        $address = \Mage::getModel('customer/address')->load($id);
        if (!$address->getId() || (int) $address->getCustomerId() !== (int) $customer->getId()) {
            return new JsonResponse([
                'error' => 'not_found',
                'message' => 'Address not found',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $address->delete();

            return new JsonResponse([
                'success' => true,
                'message' => 'Address deleted',
            ]);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'server_error',
                'message' => 'Failed to delete address',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get customer orders
     */
    #[Route('/api/customers/me/orders', name: 'api_customer_orders', methods: ['GET'])]
    public function getOrders(Request $request): JsonResponse
    {
        $customer = $this->getAuthenticatedCustomer($request);
        if ($customer instanceof JsonResponse) {
            return $customer;
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $pageSize = min(50, max(1, (int) $request->query->get('pageSize', 10)));

        $collection = \Mage::getResourceModel('sales/order_collection')
            ->addFieldToFilter('customer_id', $customer->getId())
            ->setOrder('created_at', 'DESC')
            ->setPageSize($pageSize)
            ->setCurPage($page);

        $orders = [];
        foreach ($collection as $order) {
            $orders[] = $this->mapOrderToArray($order);
        }

        return new JsonResponse([
            'orders' => $orders,
            'total' => $collection->getSize(),
            'page' => $page,
            'pageSize' => $pageSize,
            'totalPages' => (int) ceil($collection->getSize() / $pageSize),
        ]);
    }

    /**
     * Request password reset email
     */
    #[Route('/api/auth/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';

        $coreHelper = \Mage::helper('core');

        if (!$coreHelper->isValidNotBlank($email)) {
            return new JsonResponse([
                'error' => 'validation_error',
                'message' => 'Email is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$coreHelper->isValidEmail($email)) {
            return new JsonResponse([
                'error' => 'validation_error',
                'message' => 'Invalid email format',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $customer = \Mage::getModel('customer/customer')
                ->setWebsiteId(\Mage::app()->getStore()->getWebsiteId())
                ->loadByEmail($email);

            // Always return success to prevent email enumeration
            if ($customer->getId()) {
                $customer->sendPasswordResetConfirmationEmail();
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'If an account exists with this email, a password reset link has been sent.',
            ]);
        } catch (\Exception $e) {
            \Mage::logException($e);
            // Still return success to prevent enumeration
            return new JsonResponse([
                'success' => true,
                'message' => 'If an account exists with this email, a password reset link has been sent.',
            ]);
        }
    }

    /**
     * Reset password with token
     */
    #[Route('/api/auth/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';
        $token = $data['token'] ?? $data['resetToken'] ?? '';
        $newPassword = $data['password'] ?? $data['newPassword'] ?? '';

        $coreHelper = \Mage::helper('core');

        if (!$coreHelper->isValidNotBlank($email) || !$coreHelper->isValidNotBlank($token) || !$coreHelper->isValidNotBlank($newPassword)) {
            return new JsonResponse([
                'error' => 'validation_error',
                'message' => 'Email, token, and new password are required',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$coreHelper->isValidEmail($email)) {
            return new JsonResponse([
                'error' => 'validation_error',
                'message' => 'Invalid email address',
            ], Response::HTTP_BAD_REQUEST);
        }

        $minPasswordLength = (int) \Mage::getStoreConfig('customer/password/minimum_password_length') ?: 8;
        if (!$coreHelper->isValidLength($newPassword, $minPasswordLength)) {
            return new JsonResponse([
                'error' => 'validation_error',
                'message' => "Password must be at least {$minPasswordLength} characters",
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $customer = \Mage::getModel('customer/customer')
                ->setWebsiteId(\Mage::app()->getStore()->getWebsiteId())
                ->loadByEmail($email);

            if (!$customer->getId()) {
                return new JsonResponse([
                    'error' => 'invalid_token',
                    'message' => 'Invalid or expired reset token',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Validate the reset token
            $customerToken = $customer->getRpToken();
            $tokenExpiry = $customer->getRpTokenCreatedAt();

            if (empty($customerToken) || !hash_equals($customerToken, $token)) {
                return new JsonResponse([
                    'error' => 'invalid_token',
                    'message' => 'Invalid or expired reset token',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Check token expiry (24 hours)
            if ($tokenExpiry) {
                $tokenCreatedAt = strtotime($tokenExpiry);
                $expiryHours = (int) \Mage::getStoreConfig('customer/password/reset_link_expiration_period') ?: 24;
                if (time() - $tokenCreatedAt > ($expiryHours * 3600)) {
                    return new JsonResponse([
                        'error' => 'token_expired',
                        'message' => 'Reset token has expired. Please request a new one.',
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            // Reset the password
            $customer->setPassword($newPassword);
            $customer->setRpToken('');
            $customer->setRpTokenCreatedAt('');
            $customer->save();

            return new JsonResponse([
                'success' => true,
                'message' => 'Password has been reset successfully. You can now log in.',
            ]);
        } catch (\Exception $e) {
            \Mage::logException($e);
            return new JsonResponse([
                'error' => 'server_error',
                'message' => 'Failed to reset password',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get authenticated customer from request
     * @return \Mage_Customer_Model_Customer|JsonResponse
     */
    private function getAuthenticatedCustomer(Request $request): \Mage_Customer_Model_Customer|JsonResponse
    {
        $authHeader = $request->headers->get('Authorization', '');

        if (!str_starts_with($authHeader, 'Bearer ')) {
            return new JsonResponse([
                'error' => 'unauthorized',
                'message' => 'Authentication required',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = substr($authHeader, 7);

        try {
            $payload = $this->jwtService->decodeToken($token);

            if (!isset($payload->customer_id)) {
                return new JsonResponse([
                    'error' => 'invalid_token',
                    'message' => 'Token does not contain customer information',
                ], Response::HTTP_UNAUTHORIZED);
            }

            $customer = \Mage::getModel('customer/customer')->load($payload->customer_id);

            if (!$customer->getId()) {
                return new JsonResponse([
                    'error' => 'not_found',
                    'message' => 'Customer not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return $customer;
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'invalid_token',
                'message' => 'Invalid or expired token',
            ], Response::HTTP_UNAUTHORIZED);
        }
    }

    /**
     * Map customer to array (includes addresses)
     */
    private function mapCustomerToArray(\Mage_Customer_Model_Customer $customer): array
    {
        $addresses = [];
        foreach ($customer->getAddresses() as $address) {
            $addresses[] = $this->mapAddressToArray($address, $customer);
        }

        return [
            'id' => (int) $customer->getId(),
            'email' => $customer->getEmail(),
            'firstName' => $customer->getFirstname(),
            'lastName' => $customer->getLastname(),
            'groupId' => (int) $customer->getGroupId(),
            'createdAt' => $customer->getCreatedAt(),
            'addresses' => $addresses,
        ];
    }

    // TODO: Extract address mapping to a shared AddressMapper service to eliminate duplication across AuthController, AddressProcessor, AddressProvider, CustomerProvider, OrderProvider
    /**
     * Map address to array
     */
    private function mapAddressToArray(\Mage_Customer_Model_Address $address, \Mage_Customer_Model_Customer $customer): array
    {
        return [
            'id' => (int) $address->getId(),
            'firstName' => $address->getFirstname() ?? '',
            'lastName' => $address->getLastname() ?? '',
            'company' => $address->getCompany(),
            'street' => $address->getStreet(),
            'city' => $address->getCity() ?? '',
            'region' => $address->getRegion(),
            'regionId' => $address->getRegionId() ? (int) $address->getRegionId() : null,
            'postcode' => $address->getPostcode() ?? '',
            'countryId' => $address->getCountryId() ?? '',
            'telephone' => $address->getTelephone() ?? '',
            'isDefaultBilling' => $address->getId() == $customer->getDefaultBilling(),
            'isDefaultShipping' => $address->getId() == $customer->getDefaultShipping(),
        ];
    }

    /**
     * Map order to array (summary for list view)
     */
    private function mapOrderToArray(\Mage_Sales_Model_Order $order): array
    {
        $items = [];
        foreach ($order->getAllVisibleItems() as $item) {
            $items[] = [
                'name' => $item->getName(),
                'sku' => $item->getSku(),
                'qty' => (float) $item->getQtyOrdered(),
                'price' => (float) $item->getPrice(),
                'rowTotal' => (float) $item->getRowTotal(),
            ];
        }

        return [
            'id' => (int) $order->getId(),
            'incrementId' => $order->getIncrementId(),
            'status' => $order->getStatus(),
            'state' => $order->getState(),
            'grandTotal' => (float) $order->getGrandTotal(),
            'subtotal' => (float) $order->getSubtotal(),
            'shippingAmount' => (float) $order->getShippingAmount(),
            'currency' => $order->getOrderCurrencyCode(),
            'totalItemCount' => (int) $order->getTotalItemCount(),
            'createdAt' => $order->getCreatedAt(),
            'items' => $items,
        ];
    }

}
