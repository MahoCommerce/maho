<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_FeedManager
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Platform Factory/Registry
 *
 * Manages platform adapter instances
 */
class Maho_FeedManager_Model_Platform
{
    /**
     * Registered platform adapters
     *
     * @var array<string, string>
     */
    protected static array $_adapters = [
        'google' => Maho_FeedManager_Model_Platform_Google::class,
        'google_local_inventory' => Maho_FeedManager_Model_Platform_GoogleLocalInventory::class,
        'facebook' => Maho_FeedManager_Model_Platform_Facebook::class,
        'bing' => Maho_FeedManager_Model_Platform_Bing::class,
        'pinterest' => Maho_FeedManager_Model_Platform_Pinterest::class,
        'idealo' => Maho_FeedManager_Model_Platform_Idealo::class,
        'trovaprezzi' => Maho_FeedManager_Model_Platform_Trovaprezzi::class,
        'openai' => Maho_FeedManager_Model_Platform_Openai::class,
        'custom' => Maho_FeedManager_Model_Platform_Custom::class,
    ];

    /**
     * Cached adapter instances
     *
     * @var array<string, Maho_FeedManager_Model_Platform_AdapterInterface>
     */
    protected static array $_instances = [];

    /**
     * Get adapter instance by platform code
     */
    public static function getAdapter(string $platformCode): ?Maho_FeedManager_Model_Platform_AdapterInterface
    {
        if (!isset(self::$_adapters[$platformCode])) {
            return null;
        }

        if (!isset(self::$_instances[$platformCode])) {
            $class = self::$_adapters[$platformCode];
            self::$_instances[$platformCode] = new $class();
        }

        return self::$_instances[$platformCode];
    }

    /**
     * Get all registered platform codes
     *
     * @return string[]
     */
    public static function getAvailablePlatforms(): array
    {
        return array_keys(self::$_adapters);
    }

    /**
     * Get platform options for dropdown
     *
     * @return array<string, string>
     */
    public static function getPlatformOptions(): array
    {
        $options = ['' => Mage::helper('feedmanager')->__('-- Select Platform --')];

        foreach (array_keys(self::$_adapters) as $code) {
            $adapter = self::getAdapter($code);
            if ($adapter) {
                $options[$code] = $adapter->getName();
            }
        }

        return $options;
    }

    /**
     * Register a custom platform adapter
     */
    public static function registerAdapter(string $code, string $class): void
    {
        if (!is_subclass_of($class, Maho_FeedManager_Model_Platform_AdapterInterface::class)) {
            throw new InvalidArgumentException(
                'Adapter class must implement Maho_FeedManager_Model_Platform_AdapterInterface',
            );
        }

        self::$_adapters[$code] = $class;
        unset(self::$_instances[$code]); // Clear cached instance if exists
    }

    /**
     * Check if platform is registered
     */
    public static function hasAdapter(string $platformCode): bool
    {
        return isset(self::$_adapters[$platformCode]);
    }

    /**
     * Get supported formats for a platform
     *
     * @return string[]
     */
    public static function getPlatformFormats(string $platformCode): array
    {
        $adapter = self::getAdapter($platformCode);
        return $adapter ? $adapter->getSupportedFormats() : ['xml', 'csv', 'json'];
    }
}
