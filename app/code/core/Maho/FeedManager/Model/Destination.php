<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Upload Destination model
 *
 * Error Handling Pattern:
 * - Getter methods (getConfigArray, getConfigValue): Return empty array/null if not found, never throw
 * - Boolean checks (isEnabled, isSftp, isFtp): Return false on failure, never throw
 * - Connection testing: Throws Exception with descriptive message for caller to handle
 *
 * @method int getDestinationId()
 * @method string getName()
 * @method $this setName(string $name)
 * @method string getType()
 * @method $this setType(string $type)
 * @method string|null getConfig()
 * @method $this setConfig(string|null $config)
 * @method int getIsEnabled()
 * @method $this setIsEnabled(int $isEnabled)
 * @method string|null getLastUploadAt()
 * @method $this setLastUploadAt(string|null $datetime)
 * @method string|null getLastUploadStatus()
 * @method $this setLastUploadStatus(string|null $status)
 * @method string getCreatedAt()
 * @method string getUpdatedAt()
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
     * Get config as array
     */
    public function getConfigArray(): array
    {
        $config = $this->getConfig();
        if (empty($config)) {
            return [];
        }
        return Mage::helper('core')->jsonDecode($config) ?: [];
    }

    /**
     * Set config from array
     */
    public function setConfigArray(array $config): self
    {
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
            self::TYPE_GOOGLE_API => 'Google Merchant Centre API',
            self::TYPE_FACEBOOK_API => 'Facebook/Meta Catalog API',
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
}
