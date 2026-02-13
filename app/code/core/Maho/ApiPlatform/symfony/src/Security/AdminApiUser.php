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
 * Admin API User - Represents an authenticated OAuth consumer with admin access.
 *
 * Permissions are loaded from api_role/api_rule at authentication time.
 * Store access and consumer metadata are also resolved at auth time.
 */
final class AdminApiUser implements UserInterface
{
    /**
     * @param Mage_Oauth_Model_Consumer $consumer  The underlying OAuth consumer
     * @param array<string> $permissions  Permission strings like 'admin/cms-pages/write', or ['all']
     * @param array<int>|null $allowedStoreIds  Allowed store IDs, null = all stores
     */
    public function __construct(
        private readonly Mage_Oauth_Model_Consumer $consumer,
        private readonly array $permissions = [],
        private readonly ?array $allowedStoreIds = null,
    ) {}

    /**
     * Get the underlying OAuth consumer (for backward compat — name, ID, logging)
     */
    public function getConsumer(): Mage_Oauth_Model_Consumer
    {
        return $this->consumer;
    }

    #[\Override]
    public function getRoles(): array
    {
        return ['ROLE_ADMIN_API'];
    }

    #[\Override]
    public function eraseCredentials(): void {}

    #[\Override]
    public function getUserIdentifier(): string
    {
        return $this->consumer->getKey();
    }

    public function getConsumerId(): int
    {
        return (int) $this->consumer->getId();
    }

    public function getConsumerName(): string
    {
        return $this->consumer->getName() ?? '';
    }

    /**
     * Check if user has a specific permission.
     *
     * @param string $permission  Full permission string e.g. 'admin/blog-posts/write'
     */
    public function hasPermission(string $permission): bool
    {
        if (in_array('all', $this->permissions, true)) {
            return true;
        }
        return in_array($permission, $this->permissions, true);
    }

    /**
     * Get all loaded permissions
     *
     * @return array<string>
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * Get allowed store IDs
     *
     * @return array<int>|null  null means all stores allowed
     */
    public function getAllowedStoreIds(): ?array
    {
        return $this->allowedStoreIds;
    }

    /**
     * Check if user can access a specific store
     */
    public function canAccessStore(int $storeId): bool
    {
        if ($this->allowedStoreIds === null) {
            return true; // null = all stores
        }
        return in_array($storeId, $this->allowedStoreIds, true);
    }
}
