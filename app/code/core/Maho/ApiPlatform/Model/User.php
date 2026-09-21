<?php

/**
 * An API service account: a username and API key, or OAuth2 client credentials, tied to one role.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

/**
 * @method Maho_ApiPlatform_Model_Resource_User _getResource()
 * @method Maho_ApiPlatform_Model_Resource_User getResource()
 * @method Maho_ApiPlatform_Model_Resource_User_Collection getCollection()
 */
class Maho_ApiPlatform_Model_User extends Mage_Core_Model_Abstract
{
    public const ROLE_TYPE_GROUP = 'G';
    public const ROLE_TYPE_USER = 'U';

    #[\Override]
    protected $_eventPrefix = 'api_user';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('apiplatform/user');
    }

    public function loadByUsername(#[\SensitiveParameter] string $username): static
    {
        // MySQL strips NUL bytes from a varchar value and SQLite refuses to quote them,
        // so 'user\0' must never resolve to 'user' nor turn into a 500
        if (str_contains($username, "\0")) {
            return $this;
        }
        return $this->load($username, 'username');
    }

    public function loadByClientId(#[\SensitiveParameter] string $clientId): static
    {
        if ($clientId === '' || str_contains($clientId, "\0")) {
            return $this;
        }
        return $this->load($clientId, 'client_id');
    }

    public function getUsername(): ?string
    {
        return $this->getData('username');
    }

    public function setUsername(?string $value): static
    {
        return $this->setData('username', $value);
    }

    public function getFirstname(): ?string
    {
        return $this->getData('firstname');
    }

    public function setFirstname(?string $value): static
    {
        return $this->setData('firstname', $value);
    }

    public function getLastname(): ?string
    {
        return $this->getData('lastname');
    }

    public function setLastname(?string $value): static
    {
        return $this->setData('lastname', $value);
    }

    public function getEmail(): ?string
    {
        return $this->getData('email');
    }

    public function setEmail(?string $value): static
    {
        return $this->setData('email', $value);
    }

    /**
     * The stored hash after a load, the plain key between setApiKey() and save().
     */
    public function getApiKey(): ?string
    {
        return $this->getData('api_key');
    }

    /**
     * Takes the plain key. The resource model hashes it on save.
     */
    public function setApiKey(#[\SensitiveParameter] ?string $value): static
    {
        return $this->setData('api_key', $value);
    }

    public function getIsActive(): ?bool
    {
        $value = $this->getData('is_active');
        return $value === null ? null : (bool) $value;
    }

    public function setIsActive(?bool $value): static
    {
        return $this->setData('is_active', $value);
    }

    public function getClientId(): ?string
    {
        return $this->getData('client_id');
    }

    public function getCreated(): ?string
    {
        return $this->getData('created');
    }

    public function getModified(): ?string
    {
        return $this->getData('modified');
    }

    /**
     * The store ids this user is restricted to. An empty list means every store.
     *
     * @return list<int>
     */
    public function getAllowedStoreIds(): array
    {
        $raw = $this->getData('allowed_store_ids');
        if (!is_string($raw) || $raw === '') {
            return [];
        }

        try {
            $decoded = Mage::helper('core')->jsonDecode($raw);
        } catch (JsonException) {
            return [];
        }

        if (!is_array($decoded)) {
            return [];
        }
        return array_values(array_filter(array_map(intval(...), $decoded)));
    }

    /**
     * @param list<int> $storeIds An empty list removes the restriction.
     */
    public function setAllowedStoreIds(array $storeIds): static
    {
        $storeIds = array_values(array_filter(array_map(intval(...), $storeIds)));
        return $this->setData(
            'allowed_store_ids',
            $storeIds === [] ? null : Mage::helper('core')->jsonEncode($storeIds),
        );
    }

    public function authenticate(#[\SensitiveParameter] string $username, #[\SensitiveParameter] string $apiKey): bool
    {
        $this->loadByUsername($username);
        if (!$this->getId() || !Mage::helper('core')->validateHash($apiKey, (string) $this->getApiKey())) {
            $this->unsetData();
            return false;
        }
        return true;
    }

    public function verifyClientSecret(#[\SensitiveParameter] string $clientSecret): bool
    {
        $hash = (string) $this->getData('client_secret');
        return $hash !== '' && password_verify($clientSecret, $hash);
    }

    /**
     * Set a new client id and secret on the model. Returns the plain secret, which is
     * shown once and never stored. The caller saves the model.
     */
    public function generateClientCredentials(): string
    {
        $clientSecret = bin2hex(random_bytes(32));
        $this->setData('client_id', 'maho_' . bin2hex(random_bytes(16)));
        $this->setData('client_secret', password_hash($clientSecret, PASSWORD_BCRYPT));
        return $clientSecret;
    }

    /**
     * Ids of the group roles this user is assigned to.
     *
     * @return list<int>
     */
    public function getRoleIds(): array
    {
        if (!$this->getId()) {
            return [];
        }
        return $this->_getResource()->getRoleIds((int) $this->getId());
    }

    /**
     * Replace the role assignment. Zero removes every assignment.
     */
    public function assignRole(int $roleId): static
    {
        $this->_getResource()->assignRole($this, $roleId);
        return $this;
    }
}
