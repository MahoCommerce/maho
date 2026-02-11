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

use Mage_Oauth_Model_Consumer;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Admin API User - Represents an authenticated OAuth consumer with admin access
 */
final class AdminApiUser implements UserInterface
{
    public function __construct(
        private readonly Mage_Oauth_Model_Consumer $consumer,
    ) {}

    /**
     * Get the underlying OAuth consumer
     */
    public function getConsumer(): Mage_Oauth_Model_Consumer
    {
        return $this->consumer;
    }

    /**
     * Get the user's roles
     *
     * @return array<string>
     */
    #[\Override]
    public function getRoles(): array
    {
        return ['ROLE_ADMIN_API'];
    }

    /**
     * Erase sensitive credentials
     * Required by UserInterface but not used for key:secret authentication
     */
    #[\Override]
    public function eraseCredentials(): void
    {
        // Nothing to erase
    }

    /**
     * Get the unique user identifier
     */
    #[\Override]
    public function getUserIdentifier(): string
    {
        return $this->consumer->getKey();
    }

    /**
     * Get the consumer ID
     */
    public function getConsumerId(): int
    {
        return (int) $this->consumer->getId();
    }

    /**
     * Get the consumer name
     */
    public function getConsumerName(): string
    {
        return $this->consumer->getName() ?? '';
    }

    /**
     * Get admin permissions
     *
     * @return array<string, bool>
     */
    public function getPermissions(): array
    {
        return $this->consumer->getAdminPermissionsArray();
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string $permission): bool
    {
        return $this->consumer->hasPermission($permission);
    }

    /**
     * Get allowed store IDs
     *
     * @return array<int>|string Array of store IDs or "all"
     */
    public function getAllowedStoreIds(): array|string
    {
        return $this->consumer->getAllowedStoreIds();
    }

    /**
     * Check if user can access specific store
     */
    public function canAccessStore(int $storeId): bool
    {
        return $this->consumer->canAccessStore($storeId);
    }
}
