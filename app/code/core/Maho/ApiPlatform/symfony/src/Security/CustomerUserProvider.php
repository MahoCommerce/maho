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

namespace Maho\ApiPlatform\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Customer User Provider
 * Loads customer users from Maho's customer model
 *
 * @implements UserProviderInterface<ApiUser>
 */
class CustomerUserProvider implements UserProviderInterface
{
    /**
     * Load a user by their unique identifier (customer ID)
     */
    #[\Override]
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        // Extract customer ID from identifier
        // The identifier format can be "customer_123" or just "123"
        $customerId = $this->extractCustomerId($identifier);

        if ($customerId === null) {
            throw new UserNotFoundException(
                sprintf('Customer identifier "%s" is not valid.', $identifier),
            );
        }

        $customer = $this->loadCustomer($customerId);

        if ($customer === null) {
            throw new UserNotFoundException(
                sprintf('Customer with ID "%d" not found.', $customerId),
            );
        }

        return new ApiUser(
            identifier: $identifier,
            roles: ['ROLE_USER'],
            customerId: $customerId,
        );
    }

    /**
     * Refresh the user
     * Since we use stateless JWT auth, we reload from database
     */
    #[\Override]
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof ApiUser) {
            throw new UnsupportedUserException(
                sprintf('Instances of "%s" are not supported.', get_class($user)),
            );
        }

        // For stateless authentication, we can return the same user
        // or reload from database if needed
        $customerId = $user->getCustomerId();

        if ($customerId === null) {
            throw new UnsupportedUserException('User is not a customer.');
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    /**
     * Check if this provider supports the given user class
     */
    #[\Override]
    public function supportsClass(string $class): bool
    {
        return $class === ApiUser::class || is_subclass_of($class, ApiUser::class);
    }

    /**
     * Extract customer ID from identifier string
     */
    private function extractCustomerId(string $identifier): ?int
    {
        // Handle "customer_123" format
        if (str_starts_with($identifier, 'customer_')) {
            $id = substr($identifier, strlen('customer_'));
            return is_numeric($id) ? (int) $id : null;
        }

        // Handle numeric ID format
        if (is_numeric($identifier)) {
            return (int) $identifier;
        }

        // Handle email format - look up customer by email
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $customer = \Mage::getModel('customer/customer')
                ->setWebsiteId(\Mage::app()->getStore()->getWebsiteId())
                ->loadByEmail($identifier);

            return $customer->getId() ? (int) $customer->getId() : null;
        }

        return null;
    }

    /**
     * Load customer from Maho
     */
    private function loadCustomer(int $customerId): ?\Mage_Customer_Model_Customer
    {
        try {
            $customer = \Mage::getModel('customer/customer')->load($customerId);

            if (!$customer->getId()) {
                return null;
            }

            return $customer;
        } catch (\Exception $e) {
            \Mage::logException($e);
            return null;
        }
    }
}
