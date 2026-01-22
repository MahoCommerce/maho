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

/**
 * API Platform Helper
 */
class Maho_ApiPlatform_Helper_Data extends Mage_Core_Helper_Abstract
{
    public const XML_PATH_ENABLED = 'maho_apiplatform/general/enabled';
    public const XML_PATH_LEGACY_SUNSET_DATE = 'maho_apiplatform/general/legacy_sunset_date';
    public const XML_PATH_TOKEN_LIFETIME = 'maho_apiplatform/oauth2/token_lifetime';
    public const XML_PATH_REFRESH_TOKEN_LIFETIME = 'maho_apiplatform/oauth2/refresh_token_lifetime';
    public const XML_PATH_NAMING_CONVENTION = 'maho_apiplatform/graphql/naming_convention';

    public const DEFAULT_LEGACY_SUNSET_DATE = '2028-01-01';
    public const DEFAULT_TOKEN_LIFETIME = 3600;
    public const DEFAULT_REFRESH_TOKEN_LIFETIME = 86400;
    public const NAMING_GRAPHQL = 'graphql';
    public const NAMING_MAGENTO2 = 'magento2';

    /**
     * Check if API Platform is enabled
     */
    public function isEnabled(): bool
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED);
    }

    /**
     * Get the sunset date for legacy APIs (SOAP/REST)
     */
    public function getLegacySunsetDate(): string
    {
        return Mage::getStoreConfig(self::XML_PATH_LEGACY_SUNSET_DATE)
            ?: self::DEFAULT_LEGACY_SUNSET_DATE;
    }

    /**
     * Get OAuth2 access token lifetime in seconds
     */
    public function getTokenLifetime(): int
    {
        $value = Mage::getStoreConfig(self::XML_PATH_TOKEN_LIFETIME);
        return $value !== null ? (int) $value : self::DEFAULT_TOKEN_LIFETIME;
    }

    /**
     * Get OAuth2 refresh token lifetime in seconds
     */
    public function getRefreshTokenLifetime(): int
    {
        $value = Mage::getStoreConfig(self::XML_PATH_REFRESH_TOKEN_LIFETIME);
        return $value !== null ? (int) $value : self::DEFAULT_REFRESH_TOKEN_LIFETIME;
    }

    /**
     * Get the configured naming convention
     */
    public function getNamingConvention(): string
    {
        return Mage::getStoreConfig(self::XML_PATH_NAMING_CONVENTION) ?: self::NAMING_GRAPHQL;
    }

    /**
     * Check if using Magento 2 naming convention
     */
    public function useMagento2Naming(): bool
    {
        return $this->getNamingConvention() === self::NAMING_MAGENTO2;
    }

    /**
     * Transform GraphQL response based on naming convention setting
     *
     * @param array $data Response data
     * @return array Transformed response
     */
    public function transformResponse(array $data): array
    {
        if (!$this->useMagento2Naming()) {
            return $data;
        }

        return $this->toSnakeCase($data);
    }

    /**
     * Recursively convert array keys from camelCase to snake_case
     */
    private function toSnakeCase(array $data): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $snakeKey = $this->camelToSnake($key);

            if (is_array($value)) {
                $result[$snakeKey] = $this->toSnakeCase($value);
            } else {
                $result[$snakeKey] = $value;
            }
        }
        return $result;
    }

    /**
     * Convert a camelCase string to snake_case
     */
    private function camelToSnake(string $input): string
    {
        // Don't convert if it's already snake_case or all uppercase
        if (strpos($input, '_') !== false || strtoupper($input) === $input) {
            return $input;
        }

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }
}
