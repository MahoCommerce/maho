<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
    protected \Symfony\Component\Cache\Adapter\AbstractTagAwareAdapter $cacheAdapter;

    protected string $defaultAdapter = 'file';
    protected array $defaultAdapterOptions = [];
    protected ?array $allowedCacheOptions = null;

    /**
     * Class constructor. Initialize cache instance based on options
     */
    public function __construct(array $options = [])
    {
        $this->defaultAdapterOptions['cache_dir'] = $options['cache_dir'] ?? Mage::getBaseDir('cache');

        // Initialize id prefix
        $this->_idPrefix = $options['id_prefix'] ?? '';
        if (!$this->_idPrefix && isset($options['prefix'])) {
            $this->_idPrefix = $options['prefix'];
        }
        if (empty($this->_idPrefix)) {
            $this->_idPrefix = substr(md5(Mage::getConfig()->getOptions()->getEtcDir()), 0, 3) . '_';
        }

        $cacheAdapterOptions = $this->getCacheAdapterOptions($options);
        $this->cacheAdapter = match ($cacheAdapterOptions['type']) {
            'redis' => new \Symfony\Component\Cache\Adapter\RedisTagAwareAdapter(
                \Symfony\Component\Cache\Adapter\RedisTagAwareAdapter::createConnection($cacheAdapterOptions['options']['dsn']),
                "maho-{$this->_idPrefix}",
                $cacheAdapterOptions['lifetime'],
                Mage::getModel('core/cache_marshaller'),
            ),
            default => new \Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter(
                "maho-{$this->_idPrefix}",
                $cacheAdapterOptions['lifetime'],
                $this->defaultAdapterOptions['cache_dir'],
                Mage::getModel('core/cache_marshaller'),
            ),
        };
    }

    protected function getCacheAdapterOptions(array $cacheOptions): array
    {
        $type = strtolower($cacheOptions['backend'] ?? $this->defaultAdapter);
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
            $backendType = $this->defaultAdapter;
            foreach ($this->defaultAdapterOptions as $option => $value) {
                if (!array_key_exists($option, $options)) {
                    $options[$option] = $value;
                }
            }
        }

        return [
            'lifetime' => $cacheOptions['lifetime'] ?? self::DEFAULT_LIFETIME,
            'type' => $backendType,
            'options' => $options,
        ];
    }

    /**
     * Prepare unified valid identifier with prefix
     */
    protected function _id(string $id): string
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
    public function getCacheAdapter(): \Symfony\Component\Cache\Adapter\AbstractTagAwareAdapter
    {
        return $this->cacheAdapter;
    }

    /**
     * Load data from cache by id
     */
    public function load(string $id): mixed
    {
        \Maho\Profiler::start('cache.load', ['cache.key' => $id]);
        $item = $this->getCacheAdapter()->getItem($this->_id($id)); // @phpstan-ignore method.internalClass
        $result = $item->isHit() ? $item->get() : false;
        \Maho\Profiler::stop('cache.load');
        return $result;
    }

    public function save(mixed $data, string $id, array $tags = [], ?int $lifeTime = null): bool
    {
        \Maho\Profiler::start('cache.save', ['cache.key' => $id, 'cache.tags' => implode(',', $tags)]);
        $cacheItem = $this->cacheAdapter->getItem($this->_id($id)) // @phpstan-ignore method.internalClass
            ->set($data)
            ->tag($this->_tags($tags));

        if ($lifeTime) {
            $cacheItem->expiresAfter($lifeTime);
        }

        $result = $this->cacheAdapter->save($cacheItem); // @phpstan-ignore method.internalClass
        \Maho\Profiler::stop('cache.save');
        return $result;
    }

    /**
     * Check if a key is a cache hit
     */
    public function test(string $id): bool
    {
        return $this->cacheAdapter->getItem($this->_id($id))->isHit(); // @phpstan-ignore method.internalClass
    }

    /**
     * Remove cached data by identifier
     */
    public function remove(string $id): bool
    {
        \Maho\Profiler::start('cache.remove', ['cache.key' => $id]);
        $result = $this->cacheAdapter->deleteItem($this->_id($id)); // @phpstan-ignore method.internalClass
        \Maho\Profiler::stop('cache.remove');
        return $result;
    }

    /**
     * Clean cached data by specific tag
     */
    public function clean(array|string $tags = []): bool
    {
        \Maho\Profiler::start('cache.clean');
        $args = func_get_args();
        if (count($args) > 1 && is_array($args[1])) {
            $tags = $args[1];
        }

        if (!empty($tags) && !is_array($tags)) {
            $tags = [$tags];
        }

        if (is_array($tags) && count($tags) > 0) {
            $result = $this->cacheAdapter->invalidateTags($this->_tags($tags)); // @phpstan-ignore method.internalClass
        } else {
            $result = $this->flush();
        }
        \Maho\Profiler::stop('cache.clean');
        return $result;
    }

    public function prune(): bool
    {
        if ($this->cacheAdapter instanceof \Symfony\Component\Cache\PruneableInterface) {
            return $this->cacheAdapter->prune();
        }
        return true;
    }

    public function flush(): bool
    {
        \Maho\Profiler::start('cache.flush');
        $result = $this->cacheAdapter->clear(); // @phpstan-ignore method.internalClass
        \Maho\Profiler::stop('cache.flush');
        return $result;
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
                $this->allowedCacheOptions = $options;
                $this->save($this->allowedCacheOptions, self::OPTIONS_CACHE_ID);
            } else {
                $this->allowedCacheOptions = [];
            }
        } else {
            $this->allowedCacheOptions = $options;
        }

        if (Mage::getConfig()->getOptions()->getData('global_ban_use_cache')) {
            foreach ($this->allowedCacheOptions as $key => $val) {
                $this->allowedCacheOptions[$key] = false;
            }
        }

        return $this;
    }

    /**
     * Save cache usage options
     */
    public function saveOptions(array $options): self
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
        if (is_null($this->allowedCacheOptions)) {
            $this->_initOptions();
        }

        if (empty($typeCode)) {
            return $this->allowedCacheOptions;
        }

        if (isset($this->allowedCacheOptions[$typeCode])) {
            return (bool) $this->allowedCacheOptions[$typeCode];
        }
        return false;
    }

    /**
     * Disable cache usage for specific data type
     *
     * @param string $typeCode
     * @return $this
     */
    public function banUse($typeCode)
    {
        $this->allowedCacheOptions[$typeCode] = false;
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
        $this->allowedCacheOptions[$typeCode] = true;
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
                $types[$type] = new \Maho\DataObject([
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
        if (!$types) {
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
        $this->save($types, self::INVALIDATED_TYPES);
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
