<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_FeedManager
 */

declare(strict_types=1);

/**
 * Upload Destination model
 *
 * Error Handling Pattern:
 * - Getter methods (getConfigArray, getConfigValue): Return empty array/null if not found, never throw
 * - Boolean checks (isEnabled, isSftp, isFtp): Return false on failure, never throw
 * - Connection testing: Throws Exception with descriptive message for caller to handle
 *
 * @method Maho_FeedManager_Model_Resource_Destination getResource()
 * @method Maho_FeedManager_Model_Resource_Destination _getResource()
 */
class Maho_FeedManager_Model_Destination extends Mage_Core_Model_Abstract
{
    public const TYPE_SFTP = 'sftp';
    public const TYPE_FTP = 'ftp';
    public const TYPE_GOOGLE_API = 'google_api';
    public const TYPE_FACEBOOK_API = 'facebook_api';

    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    protected $_eventPrefix = 'feedmanager_destination';
    protected $_eventObject = 'destination';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('feedmanager/destination');
    }

    /**
     * Get config as decrypted array
     *
     * Handles both encrypted config (from database) and plaintext JSON (before save).
     */
    public function getConfigArray(): array
    {
        $config = $this->getConfig();
        if (empty($config)) {
            return [];
        }

        $helper = Mage::helper('core');
        return $helper->jsonDecode($helper->tryDecrypt($config) ?? $config) ?: [];
    }

    /**
     * Set config from array (will be encrypted on save)
     */
    public function setConfigArray(array $config): self
    {
        // Store as JSON, encryption happens in _beforeSave
        $this->setConfig(Mage::helper('core')->jsonEncode($config));
        return $this;
    }

    /**
     * Get a specific config value
     */
    public function getConfigValue(string $key, mixed $default = null): mixed
    {
        $config = $this->getConfigArray();
        return $config[$key] ?? $default;
    }

    /**
     * Set a specific config value
     */
    public function setConfigValue(string $key, mixed $value): self
    {
        $config = $this->getConfigArray();
        $config[$key] = $value;
        return $this->setConfigArray($config);
    }

    /**
     * Check if destination is enabled
     */
    public function isEnabled(): bool
    {
        return (int) $this->getIsEnabled() === 1;
    }

    /**
     * Get feeds using this destination
     */
    public function getFeeds(): Maho_FeedManager_Model_Resource_Feed_Collection
    {
        return Mage::getResourceModel('feedmanager/feed_collection')
            ->addFieldToFilter('destination_id', $this->getId());
    }

    /**
     * Get destination type options
     */
    public static function getTypeOptions(): array
    {
        return [
            ''                    => '-- Select Type --',
            self::TYPE_SFTP       => 'SFTP',
            self::TYPE_FTP        => 'FTP',
            // TODO: Implement Google Merchant Centre API upload
            // self::TYPE_GOOGLE_API => 'Google Merchant Centre API',
            // TODO: Implement Facebook/Meta Catalog API upload
            // self::TYPE_FACEBOOK_API => 'Facebook/Meta Catalog API',
        ];
    }

    /**
     * Get required config fields for each type
     */
    public static function getRequiredConfigFields(string $type): array
    {
        return match ($type) {
            self::TYPE_SFTP => ['host', 'username', 'auth_type'],
            self::TYPE_FTP => ['host', 'username', 'password'],
            self::TYPE_GOOGLE_API => ['merchant_id', 'service_account_json'],
            self::TYPE_FACEBOOK_API => ['catalog_id', 'access_token'],
            default => [],
        };
    }

    /**
     * Get config field definitions for admin form
     */
    public static function getConfigFieldDefinitions(string $type): array
    {
        $fields = [
            self::TYPE_SFTP => [
                'host' => ['label' => 'Host', 'type' => 'text', 'required' => true],
                'port' => ['label' => 'Port', 'type' => 'text', 'required' => true, 'default' => '22'],
                'username' => ['label' => 'Username', 'type' => 'text', 'required' => true],
                'auth_type' => ['label' => 'Authentication', 'type' => 'select', 'required' => true,
                    'options' => ['password' => 'Password', 'key' => 'Private Key']],
                'password' => ['label' => 'Password', 'type' => 'password', 'required' => false],
                'private_key' => ['label' => 'Private Key', 'type' => 'textarea', 'required' => false,
                    'note' => 'Paste your private key content here'],
                'remote_path' => ['label' => 'Remote Path', 'type' => 'text', 'required' => true,
                    'default' => '/', 'note' => 'Directory path on remote server'],
            ],
            self::TYPE_FTP => [
                'host' => ['label' => 'Host', 'type' => 'text', 'required' => true],
                'port' => ['label' => 'Port', 'type' => 'text', 'required' => true, 'default' => '21'],
                'username' => ['label' => 'Username', 'type' => 'text', 'required' => true],
                'password' => ['label' => 'Password', 'type' => 'password', 'required' => true],
                'passive_mode' => ['label' => 'Passive Mode', 'type' => 'select', 'required' => false,
                    'options' => ['1' => 'Yes', '0' => 'No'], 'default' => '1'],
                'ssl' => ['label' => 'Use SSL (FTPS)', 'type' => 'select', 'required' => false,
                    'options' => ['1' => 'Yes', '0' => 'No'], 'default' => '0'],
                'remote_path' => ['label' => 'Remote Path', 'type' => 'text', 'required' => true,
                    'default' => '/'],
            ],
            self::TYPE_GOOGLE_API => [
                'merchant_id' => ['label' => 'Merchant ID', 'type' => 'text', 'required' => true],
                'target_country' => ['label' => 'Target Country', 'type' => 'text', 'required' => true,
                    'note' => 'ISO 3166-1 alpha-2 code (e.g., AU, US, GB)'],
                'service_account_json' => ['label' => 'Service Account JSON', 'type' => 'textarea',
                    'required' => true, 'note' => 'Paste your Google service account JSON key'],
            ],
            self::TYPE_FACEBOOK_API => [
                'business_id' => ['label' => 'Business ID', 'type' => 'text', 'required' => false],
                'catalog_id' => ['label' => 'Catalog ID', 'type' => 'text', 'required' => true],
                'access_token' => ['label' => 'Access Token', 'type' => 'textarea', 'required' => true,
                    'note' => 'Long-lived access token with catalog_management permission'],
            ],
        ];

        return $fields[$type] ?? [];
    }

    /**
     * Validate config for this destination type
     */
    public function validateConfig(): array
    {
        $errors = [];
        $config = $this->getConfigArray();
        $required = self::getRequiredConfigFields($this->getType());

        foreach ($required as $field) {
            if (empty($config[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Type-specific validation
        if ($this->getType() === self::TYPE_SFTP) {
            $authType = $config['auth_type'] ?? '';
            if ($authType === 'password' && empty($config['password'])) {
                $errors[] = 'Password is required for password authentication';
            }
            if ($authType === 'key' && empty($config['private_key'])) {
                $errors[] = 'Private key is required for key authentication';
            }
        }

        return $errors;
    }

    /**
     * Encrypt config and set timestamps before save
     */
    #[\Override]
    protected function _beforeSave(): self
    {
        $config = $this->getConfig();
        if (!empty($config)) {
            $this->setData('config', Mage::helper('core')->encryptIdempotent($config));
        }

        $now = Mage::app()->getLocale()->formatDateForDb('now');
        if (!$this->getCreatedAt()) {
            $this->setCreatedAt($now);
        }
        $this->setUpdatedAt($now);

        return parent::_beforeSave();
    }

    public function getDestinationId(): ?int
    {
        $value = $this->getData('destination_id');
        return $value === null ? null : (int) $value;
    }

    public function getName(): ?string
    {
        $value = $this->getData('name');
        return $value === null ? null : (string) $value;
    }

    public function setName(?string $value): static
    {
        return $this->setData('name', $value);
    }

    public function getType(): ?string
    {
        $value = $this->getData('type');
        return $value === null ? null : (string) $value;
    }

    public function setType(?string $value): static
    {
        return $this->setData('type', $value);
    }

    public function getConfig(): ?string
    {
        $value = $this->getData('config');
        return $value === null ? null : (string) $value;
    }

    public function setConfig(?string $value): static
    {
        return $this->setData('config', $value);
    }

    public function getIsEnabled(): ?int
    {
        $value = $this->getData('is_enabled');
        return $value === null ? null : (int) $value;
    }

    public function setIsEnabled(?int $value): static
    {
        return $this->setData('is_enabled', $value);
    }

    public function getLastUploadAt(): ?string
    {
        $value = $this->getData('last_upload_at');
        return $value === null ? null : (string) $value;
    }

    public function setLastUploadAt(?string $value): static
    {
        return $this->setData('last_upload_at', $value);
    }

    public function getLastUploadStatus(): ?string
    {
        $value = $this->getData('last_upload_status');
        return $value === null ? null : (string) $value;
    }

    public function setLastUploadStatus(?string $value): static
    {
        return $this->setData('last_upload_status', $value);
    }

    public function getCreatedAt(): ?string
    {
        $value = $this->getData('created_at');
        return $value === null ? null : (string) $value;
    }

    public function setCreatedAt(?string $value): static
    {
        return $this->setData('created_at', $value);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }

    public function setUpdatedAt(?string $value): static
    {
        return $this->setData('updated_at', $value);
    }
}
