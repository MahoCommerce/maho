<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * System cache model, supporting id and tags prefix
 */
class Mage_Core_Model_Cache
{
    /**
     * Cache settings
     */
    public const DEFAULT_LIFETIME  = 7200;
    public const OPTIONS_CACHE_ID  = 'core_cache_options';
    public const INVALIDATED_TYPES = 'core_cache_invalidate';
    public const XML_PATH_TYPES    = 'global/cache/types';

    protected string $_idPrefix    = '';

    /**
     * Cache frontend API
     * @phpstan-ignore property.internalClass
     */
    protected \Symfony\Component\Cache\Adapter\AbstractTagAwareAdapter $_frontend;

    /**
     * Default cache backend type
     */
    protected string $_defaultBackend = 'file';

    /**
     * Default options for default backend
     *
     * @var array
     */
    protected $_defaultBackendOptions = [];

    /**
     * Disallow cache saving
     *
     * @var bool
     */
    protected $_disallowSave = false;

    /**
     * List of allowed cache options
     *
     * @var array|null
     */
    protected $_allowedCacheOptions;

    /**
     * DB connection
     *
     * @var string|null
     */
    protected $_dbConnection = 'core_write';

    /**
     * Class constructor. Initialize cache instance based on options
     */
    public function __construct(array $options = [])
    {
        $this->_defaultBackendOptions['cache_dir'] = $options['cache_dir'] ?? Mage::getBaseDir('cache');

        // Initialize id prefix
        $this->_idPrefix = $options['id_prefix'] ?? '';
        if (!$this->_idPrefix && isset($options['prefix'])) {
            $this->_idPrefix = $options['prefix'];
        }
        if (empty($this->_idPrefix)) {
            $this->_idPrefix = substr(md5(Mage::getConfig()->getOptions()->getEtcDir()), 0, 3) . '_';
        }

        $backend    = $this->_getBackendOptions($options);
        $frontend   = $this->_getFrontendOptions($options);

        $this->_frontend = match ($backend['type']) {
            'redis' => new \Symfony\Component\Cache\Adapter\RedisTagAwareAdapter(
                \Symfony\Component\Cache\Adapter\RedisTagAwareAdapter::createConnection($backend['options']['dsn']),
                'maho',
                $frontend['lifetime'],
            ),
            default => new \Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter(
                'maho',
                $frontend['lifetime'],
                $this->_defaultBackendOptions['cache_dir'],
            ),
        };
    }

    protected function _getBackendOptions(array $cacheOptions): array
    {
        $type = strtolower($cacheOptions['backend'] ?? $this->_defaultBackend);
        if (isset($cacheOptions['backend_options']) && is_array($cacheOptions['backend_options'])) {
            $options = $cacheOptions['backend_options'];
        } else {
            $options = [];
        }

        $backendType = $type;
        if (!in_array($type, ['file', 'redis'])) {
            throw new Exception("Supported cache backend are file/redis, $type passed.");
        }

        if (!$backendType) {
            $backendType = $this->_defaultBackend;
            foreach ($this->_defaultBackendOptions as $option => $value) {
                if (!array_key_exists($option, $options)) {
                    $options[$option] = $value;
                }
            }
        }

        $backendOptions = ['type' => $backendType, 'options' => $options];
        return $backendOptions;
    }

    /**
     * Get options for database backend type
     *
     * @return array
     */
    protected function getDbAdapterOptions(array $options = [])
    {
        if (isset($options['connection'])) {
            $this->_dbConnection = $options['connection'];
        }

        $options['adapter_callback'] = [$this, 'getDbAdapter'];
        $options['data_table'] = Mage::getSingleton('core/resource')->getTableName('core/cache');
        $options['tags_table'] = Mage::getSingleton('core/resource')->getTableName('core/cache_tag');
        return $options;
    }

    /**
     * Get options of cache frontend (options of symfony/cache)
     *
     * @return  array
     */
    protected function _getFrontendOptions(array $cacheOptions)
    {
        $options = $cacheOptions['frontend_options'] ?? [];
        if (!array_key_exists('caching', $options)) {
            $options['caching'] = true;
        }
        if (!array_key_exists('lifetime', $options)) {
            $options['lifetime'] = $cacheOptions['lifetime'] ?? self::DEFAULT_LIFETIME;
        }
        if (!array_key_exists('automatic_cleaning_factor', $options)) {
            $options['automatic_cleaning_factor'] = 0;
        }
        $options['cache_id_prefix'] = $this->_idPrefix;
        return $options;
    }

    /**
     * Prepare unified valid identifier with prefix
     *
     * @param   string $id
     * @return  string
     */
    protected function _id($id)
    {
        if ($id) {
            $id = strtoupper($id);
        }
        return $id;
    }

    /**
     * Prepare cache tags.
     *
     * @param   array $tags
     * @return  array
     */
    protected function _tags($tags = [])
    {
        foreach ($tags as $key => $value) {
            $tags[$key] = $this->_id($value);
        }
        return $tags;
    }

    // @phpstan-ignore return.internalClass
    public function getFrontend(): \Symfony\Component\Cache\Adapter\AbstractTagAwareAdapter
    {
        return $this->_frontend;
    }

    /**
     * Load data from cache by id
     */
    public function load(string $id): mixed
    {
        $item = $this->getFrontend()->getItem($this->_id($id)); // @phpstan-ignore method.internalClass
        if ($item->isHit()) {
            return $item->get();
        }
        return false;
    }

    public function save(mixed $data, string $id, array $tags = [], ?int $lifeTime = null): bool
    {
        if ($this->_disallowSave) {
            return true;
        }

        $cacheItem = $this->_frontend->getItem($this->_id($id)) // @phpstan-ignore method.internalClass
            ->set($data)
            ->tag($this->_tags($tags));

        if ($lifeTime) {
            $cacheItem->expiresAfter($lifeTime);
        }

        return $this->_frontend->save($cacheItem); // @phpstan-ignore method.internalClass
    }

    /**
     * Check if a key is a cache hit
     */
    public function test(string $id): bool
    {
        return $this->_frontend->getItem($this->_id($id))->isHit(); // @phpstan-ignore method.internalClass
    }

    /**
     * Remove cached data by identifier
     */
    public function remove(string $id): bool
    {
        return $this->_frontend->deleteItem($this->_id($id)); // @phpstan-ignore method.internalClass
    }

    /**
     * Clean cached data by specific tag
     *
     * @param   array|string $tags
     * @return  bool
     */
    public function clean($tags = [])
    {
        if (!empty($tags)) {
            if (!is_array($tags)) {
                $tags = [$tags];
            }
            return $this->_frontend->invalidateTags($this->_tags($tags)); // @phpstan-ignore method.internalClass
        }

        return $this->flush();
    }

    public function flush(): bool
    {
        return $this->_frontend->clear($this->_idPrefix); // @phpstan-ignore method.internalClass
    }

    /**
     * Get cache resource model
     */
    protected function _getResource(): Mage_Core_Model_Resource_Cache
    {
        return Mage::getResourceSingleton('core/cache');
    }

    /**
     * Initialize cache types options
     *
     * @return $this
     */
    protected function _initOptions()
    {
        $options = $this->load(self::OPTIONS_CACHE_ID);
        if ($options === false) {
            $options = $this->_getResource()->getAllOptions();
            if (is_array($options)) {
                $this->_allowedCacheOptions = $options;
                $this->save($this->_allowedCacheOptions, self::OPTIONS_CACHE_ID);
            } else {
                $this->_allowedCacheOptions = [];
            }
        } else {
            $this->_allowedCacheOptions = $options;
        }

        if (Mage::getConfig()->getOptions()->getData('global_ban_use_cache')) {
            foreach ($this->_allowedCacheOptions as $key => $val) {
                $this->_allowedCacheOptions[$key] = false;
            }
        }

        return $this;
    }

    /**
     * Save cache usage options
     *
     * @param array $options
     * @return $this
     */
    public function saveOptions($options)
    {
        $this->remove(self::OPTIONS_CACHE_ID);
        $this->_getResource()->saveAllOptions($options);
        return $this;
    }

    /**
     * Check if cache can be used for specific data type
     *
     * @param string $typeCode
     * @return bool|array
     */
    public function canUse($typeCode)
    {
        if (is_null($this->_allowedCacheOptions)) {
            $this->_initOptions();
        }

        if (empty($typeCode)) {
            return $this->_allowedCacheOptions;
        }

        if (isset($this->_allowedCacheOptions[$typeCode])) {
            return (bool) $this->_allowedCacheOptions[$typeCode];
        } else {
            return false;
        }
    }

    /**
     * Disable cache usage for specific data type
     *
     * @param string $typeCode
     * @return $this
     */
    public function banUse($typeCode)
    {
        $this->_allowedCacheOptions[$typeCode] = false;
        return $this;
    }

    /**
     * Enable cache usage for specific data type
     *
     * @param string $typeCode
     * @return $this
     */
    public function unbanUse($typeCode)
    {
        $this->_allowedCacheOptions[$typeCode] = true;
        return $this;
    }

    /**
     * Get cache tags by cache type from configuration
     *
     * @param string $type
     * @return array
     */
    public function getTagsByType($type)
    {
        $path = self::XML_PATH_TYPES . '/' . $type . '/tags';
        $tagsConfig = Mage::getConfig()->getNode($path);
        if ($tagsConfig) {
            $tags = (string) $tagsConfig;
            $tags = explode(',', $tags);
        } else {
            $tags = false;
        }
        return $tags;
    }

    /**
     * Get information about all declared cache types
     *
     * @return array
     */
    public function getTypes()
    {
        $types = [];
        $config = Mage::getConfig()->getNode(self::XML_PATH_TYPES);
        if ($config) {
            foreach ($config->children() as $type => $node) {
                $types[$type] = new Varien_Object([
                    'id'            => $type,
                    'cache_type'    => Mage::helper('core')->__((string) $node->label),
                    'description'   => Mage::helper('core')->__((string) $node->description),
                    'tags'          => strtoupper((string) $node->tags),
                    'status'        => (int) $this->canUse($type),
                ]);
            }
        }
        return $types;
    }

    /**
     * Get invalidate types codes
     *
     * @return array
     */
    protected function _getInvalidatedTypes()
    {
        $types = $this->load(self::INVALIDATED_TYPES);
        if ($types) {
            $types = unserialize($types, ['allowed_classes' => false]);
        } else {
            $types = [];
        }
        return $types;
    }

    /**
     * Save invalidated cache types
     *
     * @param array $types
     * @return $this
     */
    protected function _saveInvalidatedTypes($types)
    {
        $this->save(serialize($types), self::INVALIDATED_TYPES);
        return $this;
    }

    /**
     * Get array of all invalidated cache types
     *
     * @return array
     */
    public function getInvalidatedTypes()
    {
        $invalidatedTypes = [];
        $types = $this->_getInvalidatedTypes();
        if ($types) {
            $allTypes = $this->getTypes();
            foreach (array_keys($types) as $type) {
                if (isset($allTypes[$type]) && $this->canUse($type)) {
                    $invalidatedTypes[$type] = $allTypes[$type];
                }
            }
        }
        return $invalidatedTypes;
    }

    /**
     * Mark specific cache type(s) as invalidated
     *
     * @param string|array $typeCode
     * @return $this
     */
    public function invalidateType($typeCode)
    {
        $types = $this->_getInvalidatedTypes();
        if (!is_array($typeCode)) {
            $typeCode = [$typeCode];
        }
        foreach ($typeCode as $code) {
            $types[$code] = 1;
        }
        $this->_saveInvalidatedTypes($types);
        return $this;
    }

    /**
     * Clean cached data for specific cache type
     *
     * @param string $typeCode
     * @return $this
     */
    public function cleanType($typeCode)
    {
        $tags = $this->getTagsByType($typeCode);
        $this->clean($tags);

        $types = $this->_getInvalidatedTypes();
        unset($types[$typeCode]);
        $this->_saveInvalidatedTypes($types);
        return $this;
    }
}
